<?php
declare(strict_types=1);

class TestPlanController {
    public static function index(): void {
        Auth::requireView('testing');
        $projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        [$accessSql, $accessParams] = self::projectClause('tp');
        $params = $accessParams;
        $where  = $accessSql;
        if ($projectId) { $where .= ' AND tp.project_id = ?'; $params[] = $projectId; }

        $plans = Database::fetchAll(
            "SELECT tp.*, p.name project_name, COUNT(tpi.id) item_count
             FROM test_plans tp
             LEFT JOIN projects p ON p.id = tp.project_id
             LEFT JOIN test_plan_items tpi ON tpi.test_plan_id = tp.id
             WHERE $where GROUP BY tp.id ORDER BY tp.created_at DESC",
            $params
        );
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE $pSql ORDER BY name", $pParams);
        View::render('test-plans/index', compact('plans', 'projects', 'projectId') + ['title' => 'Test Plans']);
    }

    public static function show(string $id): void {
        Auth::requireView('testing');
        $plan = self::findOr404((int)$id);
        self::checkAccess($plan);
        $items = Database::fetchAll(
            'SELECT tpi.*, tr.id req_id, tr.summary req_summary, tr.status req_status
             FROM test_plan_items tpi
             LEFT JOIN test_requests tr ON tr.id = tpi.test_request_id
             WHERE tpi.test_plan_id = ? ORDER BY tpi.sort_order, tpi.id',
            [(int)$id]
        );
        $cycles = Database::fetchAll(
            "SELECT tc.*,
                    COUNT(tr.id) run_count,
                    SUM(CASE WHEN tr.status='completed' THEN 1 ELSE 0 END) completed_runs
             FROM test_cycles tc
             LEFT JOIN test_runs tr ON tr.test_cycle_id = tc.id
             WHERE tc.test_plan_id = ?
             GROUP BY tc.id
             ORDER BY tc.created_at DESC",
            [(int)$id]
        );
        // Load ALL runs for ALL cycles in one query (avoid N+1)
        $cycleIds = array_column($cycles, 'id');
        $runsByCycle = [];
        if ($cycleIds) {
            $ph = implode(',', array_fill(0, count($cycleIds), '?'));
            foreach (Database::fetchAll("SELECT tr.*, COUNT(trr.id) rc, SUM(trr.status='passed') p, SUM(trr.status='failed') f FROM test_runs tr LEFT JOIN test_run_results trr ON trr.test_run_id=tr.id WHERE tr.test_cycle_id IN ($ph) GROUP BY tr.id ORDER BY tr.created_at DESC", $cycleIds) as $cr) {
                $runsByCycle[$cr['test_cycle_id']][] = $cr;
            }
        }
        $runsWithoutCycle = Database::fetchAll(
            "SELECT tr.*,
                    COUNT(trr.id) result_count,
                    SUM(trr.status='passed') passed,
                    SUM(trr.status='failed') failed
             FROM test_runs tr
             LEFT JOIN test_run_results trr ON trr.test_run_id = tr.id
             WHERE tr.test_plan_id = ? AND (tr.test_cycle_id IS NULL)
             GROUP BY tr.id ORDER BY tr.created_at DESC",
            [(int)$id]
        );
        $runs = Database::fetchAll(
            'SELECT * FROM test_runs WHERE test_plan_id = ? ORDER BY created_at DESC',
            [(int)$id]
        );
        $customFields = Database::fetchAll('SELECT * FROM test_case_fields ORDER BY sort_order, name');
        $itemIds = array_column($items, 'id');
        $customValues = [];
        if ($itemIds) {
            $ph = implode(',', array_fill(0, count($itemIds), '?'));
            $rows = Database::fetchAll("SELECT * FROM test_case_field_values WHERE item_id IN ($ph)", $itemIds);
            foreach ($rows as $r) {
                $customValues[$r['item_id']][$r['field_id']] = $r['value'];
            }
        }
        $testRequests = Database::fetchAll('SELECT id, summary, status FROM test_requests ORDER BY created_at DESC');
        $allSteps = [];
        if ($itemIds) {
            $ph2 = implode(',', array_fill(0, count($itemIds), '?'));
            foreach (Database::fetchAll("SELECT * FROM test_case_steps WHERE test_plan_item_id IN ($ph2) ORDER BY test_plan_item_id, step_number", $itemIds) as $sr) {
                $allSteps[$sr['test_plan_item_id']][] = $sr;
            }
        }
        View::render('test-plans/show', compact('plan', 'items', 'runs', 'cycles', 'runsWithoutCycle', 'runsByCycle', 'customFields', 'customValues', 'testRequests', 'allSteps') + ['title' => $plan['name']]);
    }

    public static function cyclesJson(string $id): void {
        Auth::requireView('testing');
        header('Content-Type: application/json');
        $cycles = Database::fetchAll('SELECT id, name FROM test_cycles WHERE test_plan_id=? ORDER BY created_at DESC', [(int)$id]);
        echo json_encode($cycles);
        exit;
    }

    public static function create(): void {
        Auth::requireEdit('testing');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $projectId   = (int)($_POST['project_id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if (!$name || !$projectId) { flash('error', 'Name und Projekt sind erforderlich.'); redirect('/test-plans/create'); }
            $id = Database::insert(
                'INSERT INTO test_plans (project_id, name, description) VALUES (?,?,?)',
                [$projectId, $name, $description]
            );
            flash('success', 'Testplan erstellt.');
            redirect('/test-plans/' . $id);
        }
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE p.status='active' AND $pSql ORDER BY name", $pParams);
        View::render('test-plans/create', ['projects' => $projects, 'title' => 'Neuer Testplan']);
    }

    public static function edit(string $id): void {
        Auth::requireEdit('testing');
        $plan = self::findOr404((int)$id);
        self::checkAccess($plan);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute(
                'UPDATE test_plans SET project_id=?, name=?, description=? WHERE id=?',
                [(int)$_POST['project_id'], trim($_POST['name']), trim($_POST['description']), (int)$id]
            );
            flash('success', 'Testplan aktualisiert.');
            redirect('/test-plans/' . $id);
        }
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE $pSql ORDER BY name", $pParams);
        View::render('test-plans/edit', compact('plan', 'projects') + ['title' => 'Testplan bearbeiten']);
    }

    public static function delete(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_plans WHERE id = ?', [(int)$id]);
        flash('success', 'Test plan deleted.');
        redirect('/test-plans');
    }

    public static function addItem(string $id): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $title  = trim($_POST['title'] ?? '');
        if (!$title) { flash('error', 'Titel erforderlich.'); redirect('/test-plans/' . $id); }
        $itemId = Database::insert(
            'INSERT INTO test_plan_items (test_plan_id, title, description, expected_result, priority, status, sort_order) VALUES (?,?,?,?,?,?,?)',
            [(int)$id, $title, trim($_POST['description'] ?? ''), trim($_POST['expected_result'] ?? ''),
             $_POST['priority'] ?? 'medium', 'pending',
             (int)Database::fetchOne('SELECT COUNT(*) c FROM test_plan_items WHERE test_plan_id=?', [(int)$id])['c']]
        );
        self::saveCustomFieldValues($itemId);
        flash('success', 'Item added.');
        redirect('/test-plans/' . $id);
    }

    public static function updateItem(string $id, string $iid): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        Database::execute(
            'UPDATE test_plan_items SET title=?, description=?, expected_result=?, priority=?, status=? WHERE id=? AND test_plan_id=?',
            [trim($_POST['title']), trim($_POST['description'] ?? ''), trim($_POST['expected_result'] ?? ''),
             $_POST['priority'] ?? 'medium', $_POST['status'] ?? 'pending', (int)$iid, (int)$id]
        );
        self::saveCustomFieldValues((int)$iid);
        flash('success', 'Item aktualisiert.');
        redirect('/test-plans/' . $id);
    }

    public static function setTestRequest(string $id, string $iid): void {
        Auth::require();
        Auth::verifyCsrf();
        $reqId = !empty($_POST['test_request_id']) ? (int)$_POST['test_request_id'] : null;
        Database::execute(
            'UPDATE test_plan_items SET test_request_id=? WHERE id=? AND test_plan_id=?',
            [$reqId, (int)$iid, (int)$id]
        );
        flash('success', $reqId ? 'Test Request linked.' : 'Test Request link removed.');
        redirect('/test-plans/' . $id);
    }

    // ── Test Steps CRUD ──────────────────────────────────────────
    public static function addStep(string $id, string $iid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $action   = trim($_POST['step_action'] ?? '');
        $testData = trim($_POST['test_data'] ?? '');
        $expected = trim($_POST['expected_result'] ?? '');
        if (!$action && !$expected) { http_response_code(422); echo json_encode(['error' => 'Step oder Expected Result erforderlich']); exit; }
        $maxOrder = (int)(Database::fetchOne('SELECT MAX(step_number) m FROM test_case_steps WHERE test_plan_item_id=?', [(int)$iid])['m'] ?? 0);
        $stepId = Database::insert('INSERT INTO test_case_steps (test_plan_item_id, step_number, step_action, test_data, expected_result, created_by) VALUES (?,?,?,?,?,?)', [(int)$iid, $maxOrder + 1, $action, $testData, $expected, Auth::id()]);
        echo json_encode(['success' => true, 'id' => $stepId, 'step_number' => $maxOrder + 1]);
        exit;
    }

    public static function updateStep(string $id, string $iid, string $sid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        Database::execute('UPDATE test_case_steps SET step_action=?, test_data=?, expected_result=? WHERE id=? AND test_plan_item_id=?', [trim($_POST['step_action'] ?? ''), trim($_POST['test_data'] ?? ''), trim($_POST['expected_result'] ?? ''), (int)$sid, (int)$iid]);
        echo json_encode(['success' => true]);
        exit;
    }

    public static function deleteStep(string $id, string $iid, string $sid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_case_steps WHERE id=? AND test_plan_item_id=?', [(int)$sid, (int)$iid]);
        // Re-number remaining steps
        $steps = Database::fetchAll('SELECT id FROM test_case_steps WHERE test_plan_item_id=? ORDER BY step_number', [(int)$iid]);
        foreach ($steps as $i => $s) { Database::execute('UPDATE test_case_steps SET step_number=? WHERE id=?', [$i+1, $s['id']]); }
        echo json_encode(['success' => true]);
        exit;
    }

    public static function editItem(string $id, string $iid): void {
        Auth::requireEdit('testing');
        $plan = self::findOr404((int)$id);
        $item = Database::fetchOne('SELECT * FROM test_plan_items WHERE id=? AND test_plan_id=?', [(int)$iid, (int)$id]);
        if (!$item) abort(404, 'Test Case nicht gefunden');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute('UPDATE test_plan_items SET title=?, description=?, expected_result=?, priority=?, status=? WHERE id=? AND test_plan_id=?',
                [trim($_POST['title']), trim($_POST['description']??''), trim($_POST['expected_result']??''), $_POST['priority']??'medium', $_POST['status']??'pending', (int)$iid, (int)$id]);
            self::saveCustomFieldValues((int)$iid);
            flash('success', 'Test Case aktualisiert.');
            redirect('/test-plans/' . $id);
        }
        $customFields = Database::fetchAll('SELECT * FROM test_case_fields ORDER BY sort_order, name');
        $customValues = [];
        foreach (Database::fetchAll('SELECT * FROM test_case_field_values WHERE item_id=?', [(int)$iid]) as $r) {
            $customValues[$r['field_id']] = $r['value'];
        }
        $steps = Database::fetchAll('SELECT * FROM test_case_steps WHERE test_plan_item_id=? ORDER BY step_number', [(int)$iid]);
        View::render('test-plans/edit-item', compact('plan', 'item', 'customFields', 'customValues', 'steps') + ['title' => 'Test Case bearbeiten']);
    }

    public static function deleteItem(string $id, string $iid): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_plan_items WHERE id=? AND test_plan_id=?', [(int)$iid, (int)$id]);
        flash('success', 'Item deleted.');
        redirect('/test-plans/' . $id);
    }

    public static function import(string $id): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $plan = self::findOr404((int)$id);
        if (empty($_FILES['csv']['tmp_name'])) { flash('error', 'Keine Datei.'); redirect('/test-plans/' . $id); }
        $rows = array_map('str_getcsv', file($_FILES['csv']['tmp_name']));
        $header = array_shift($rows);
        $count = 0;
        foreach ($rows as $i => $row) {
            if (empty($row[0])) continue;
            Database::insert(
                'INSERT INTO test_plan_items (test_plan_id, title, description, expected_result, priority, status, sort_order) VALUES (?,?,?,?,?,?,?)',
                [(int)$id, $row[0] ?? '', $row[1] ?? '', $row[2] ?? '', $row[3] ?? 'medium', 'pending', $i]
            );
            $count++;
        }
        flash('success', "$count Items importiert.");
        redirect('/test-plans/' . $id);
    }

    private static function findOr404(int $id): array {
        $p = Database::fetchOne(
            'SELECT tp.*, p.name project_name FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id WHERE tp.id=?',
            [$id]
        );
        if (!$p) abort(404, 'Testplan nicht gefunden');
        return $p;
    }

    private static function checkAccess(array $plan): void {
        if (Auth::isAdmin()) return;
        $pid = $plan['project_id'] ?? null;
        if (!$pid) return;
        $ids = Auth::groupProjectIds();
        if ($ids !== null && !in_array((int)$pid, $ids, true)) abort(403, 'Kein Zugriff auf dieses Projekt');
    }

    private static function saveCustomFieldValues(int $itemId): void {
        $fields = Database::fetchAll('SELECT id, variable_name FROM test_case_fields');
        foreach ($fields as $f) {
            $key = 'cf_' . $f['variable_name'];
            if (!array_key_exists($key, $_POST)) continue;
            $val = trim($_POST[$key]);
            Database::execute(
                'INSERT INTO test_case_field_values (item_id, field_id, value) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE value=?',
                [$itemId, $f['id'], $val, $val]
            );
        }
    }

    private static function projectClause(string $alias): array {
        $ids = Auth::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ["$alias.project_id IS NULL", []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.project_id IS NULL OR $alias.project_id IN ($ph))", $ids];
    }
}
