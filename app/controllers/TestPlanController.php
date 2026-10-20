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
            "SELECT tp.*, p.name project_name, COUNT(tpil.test_plan_item_id) item_count
             FROM test_plans tp
             LEFT JOIN projects p ON p.id = tp.project_id
             LEFT JOIN test_plan_item_links tpil ON tpil.test_plan_id = tp.id
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
            'SELECT tpi.*, tr.id req_id, tr.summary req_summary, tr.status req_status,
                    tpil.sort_order link_sort_order,
                    (SELECT COUNT(*) FROM test_plan_item_links l2 WHERE l2.test_plan_item_id = tpi.id) link_count
             FROM test_plan_items tpi
             JOIN test_plan_item_links tpil ON tpil.test_plan_item_id = tpi.id AND tpil.test_plan_id = ?
             LEFT JOIN test_requests tr ON tr.id = tpi.test_request_id
             ORDER BY tpil.sort_order, tpi.id',
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
            foreach (Database::fetchAll("SELECT tr.*, COUNT(trr.id) rc, SUM(trr.status='passed') p, SUM(trr.status='failed') f, SUM(trr.status='pending') pe FROM test_runs tr LEFT JOIN test_run_results trr ON trr.test_run_id=tr.id WHERE tr.test_cycle_id IN ($ph) GROUP BY tr.id ORDER BY tr.created_at DESC", $cycleIds) as $cr) {
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
        $categories = Database::fetchAll('SELECT * FROM test_categories WHERE project_id=? ORDER BY sort_order, name', [(int)$plan['project_id']]);
        View::render('test-plans/show', compact('plan', 'items', 'runs', 'cycles', 'runsWithoutCycle', 'runsByCycle', 'customFields', 'customValues', 'testRequests', 'allSteps', 'categories') + ['title' => $plan['name']]);
    }

    public static function cyclesJson(string $id): void {
        Auth::requireView('testing');
        header('Content-Type: application/json');
        $cycles = Database::fetchAll('SELECT id, name FROM test_cycles WHERE test_plan_id=? ORDER BY created_at DESC', [(int)$id]);
        echo json_encode($cycles);
        exit;
    }

    // -- Test cases of a plan, for the Test Run creation checklist. --
    public static function itemsJson(string $id): void {
        Auth::requireView('testing');
        header('Content-Type: application/json');
        $items = Database::fetchAll(
            'SELECT tpi.id, tpi.title, tpi.priority, tpi.category_id, tc.name category_name
             FROM test_plan_items tpi
             JOIN test_plan_item_links tpil ON tpil.test_plan_item_id = tpi.id AND tpil.test_plan_id = ?
             LEFT JOIN test_categories tc ON tc.id = tpi.category_id
             ORDER BY tpil.sort_order, tpi.id',
            [(int)$id]
        );
        echo json_encode($items);
        exit;
    }

    public static function create(): void {
        Auth::requireEdit('testing');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $projectId   = (int)($_POST['project_id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if (!$name || !$projectId) { flash('error', 'Name and project are required.'); redirect('/test-plans/create'); }
            $id = Database::insert(
                'INSERT INTO test_plans (project_id, name, description) VALUES (?,?,?)',
                [$projectId, $name, $description]
            );
            flash('success', 'Test plan created.');
            redirect('/test-plans/' . $id);
        }
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE p.status='active' AND $pSql ORDER BY name", $pParams);
        View::render('test-plans/create', ['projects' => $projects, 'title' => 'New Test Plan']);
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
            flash('success', 'Test plan updated.');
            redirect('/test-plans/' . $id);
        }
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE $pSql ORDER BY name", $pParams);
        View::render('test-plans/edit', compact('plan', 'projects') + ['title' => 'Edit Test Plan']);
    }

    public static function delete(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        // Items whose home is this plan but are also linked elsewhere must
        // survive - reassign their home before the FK cascade below would
        // otherwise take them down with the plan. Items with no other link
        // are deleted along with the plan, same as before this feature.
        $homeItems = Database::fetchAll('SELECT id FROM test_plan_items WHERE test_plan_id=?', [(int)$id]);
        foreach ($homeItems as $hi) {
            $otherLink = Database::fetchOne(
                'SELECT test_plan_id FROM test_plan_item_links WHERE test_plan_item_id=? AND test_plan_id != ? LIMIT 1',
                [$hi['id'], (int)$id]
            );
            if ($otherLink) {
                Database::execute('UPDATE test_plan_items SET test_plan_id=? WHERE id=?', [(int)$otherLink['test_plan_id'], $hi['id']]);
            }
        }
        Database::execute('DELETE FROM test_plans WHERE id = ?', [(int)$id]);
        flash('success', 'Test plan deleted.');
        redirect('/test-plans');
    }

    public static function addItem(string $id): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $title  = trim($_POST['title'] ?? '');
        if (!$title) { flash('error', 'Title required.'); redirect('/test-plans/' . $id); }
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $sortOrder = (int)(Database::fetchOne('SELECT COUNT(*) c FROM test_plan_item_links WHERE test_plan_id=?', [(int)$id])['c'] ?? 0);
        $itemId = Database::insert(
            'INSERT INTO test_plan_items (test_plan_id, title, description, expected_result, priority, status, sort_order, category_id) VALUES (?,?,?,?,?,?,?,?)',
            [(int)$id, $title, trim($_POST['description'] ?? ''), trim($_POST['expected_result'] ?? ''),
             $_POST['priority'] ?? 'medium', 'pending', $sortOrder, $categoryId]
        );
        Database::insert(
            'INSERT INTO test_plan_item_links (test_plan_id, test_plan_item_id, sort_order) VALUES (?,?,?)',
            [(int)$id, $itemId, $sortOrder]
        );
        self::saveCustomFieldValues($itemId);
        flash('success', 'Item added.');
        redirect('/test-plans/' . $id);
    }

    public static function updateItem(string $id, string $iid): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        self::checkItemLinked((int)$iid, (int)$id);
        Database::execute(
            'UPDATE test_plan_items SET title=?, description=?, expected_result=?, priority=?, status=? WHERE id=?',
            [trim($_POST['title']), trim($_POST['description'] ?? ''), trim($_POST['expected_result'] ?? ''),
             $_POST['priority'] ?? 'medium', $_POST['status'] ?? 'pending', (int)$iid]
        );
        self::saveCustomFieldValues((int)$iid);
        flash('success', 'Item updated.');
        redirect('/test-plans/' . $id);
    }

    public static function setTestRequest(string $id, string $iid): void {
        Auth::require();
        Auth::verifyCsrf();
        self::checkItemLinked((int)$iid, (int)$id);
        $reqId = !empty($_POST['test_request_id']) ? (int)$_POST['test_request_id'] : null;
        Database::execute(
            'UPDATE test_plan_items SET test_request_id=? WHERE id=?',
            [$reqId, (int)$iid]
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
        if (!$action && !$expected) { http_response_code(422); echo json_encode(['error' => 'Step or Expected Result required']); exit; }
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
        $item = Database::fetchOne(
            'SELECT tpi.* FROM test_plan_items tpi
             JOIN test_plan_item_links tpil ON tpil.test_plan_item_id = tpi.id AND tpil.test_plan_id = ?
             WHERE tpi.id = ?',
            [(int)$id, (int)$iid]
        );
        if (!$item) abort(404, 'Test Case not found');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
            Database::execute('UPDATE test_plan_items SET title=?, description=?, expected_result=?, priority=?, status=?, category_id=? WHERE id=?',
                [trim($_POST['title']), trim($_POST['description']??''), trim($_POST['expected_result']??''), $_POST['priority']??'medium', $_POST['status']??'pending', $categoryId, (int)$iid]);
            self::saveCustomFieldValues((int)$iid);
            flash('success', 'Test Case updated.');
            redirect('/test-plans/' . $id);
        }
        $customFields = Database::fetchAll('SELECT * FROM test_case_fields ORDER BY sort_order, name');
        $customValues = [];
        foreach (Database::fetchAll('SELECT * FROM test_case_field_values WHERE item_id=?', [(int)$iid]) as $r) {
            $customValues[$r['field_id']] = $r['value'];
        }
        $categories = Database::fetchAll('SELECT * FROM test_categories WHERE project_id=? ORDER BY sort_order, name', [(int)$plan['project_id']]);
        $steps = Database::fetchAll('SELECT * FROM test_case_steps WHERE test_plan_item_id=? ORDER BY step_number', [(int)$iid]);
        View::render('test-plans/edit-item', compact('plan', 'item', 'customFields', 'customValues', 'steps', 'categories') + ['title' => 'Edit Test Case']);
    }

    // Removing a test case from a plan's list unlinks it from that plan; if
    // this was its only remaining link anywhere, it has nothing left to be
    // shared with and is deleted outright (steps/custom field values cascade
    // via their own FKs). If its deleted link was the item's "home" plan but
    // other links survive, reassign home to one of them so the item still
    // resolves a project.
    public static function deleteItem(string $id, string $iid): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        self::checkItemLinked((int)$iid, (int)$id);
        Database::execute('DELETE FROM test_plan_item_links WHERE test_plan_id=? AND test_plan_item_id=?', [(int)$id, (int)$iid]);
        $remaining = Database::fetchAll('SELECT test_plan_id FROM test_plan_item_links WHERE test_plan_item_id=?', [(int)$iid]);
        if (!$remaining) {
            Database::execute('DELETE FROM test_plan_items WHERE id=?', [(int)$iid]);
            flash('success', 'Item deleted.');
        } else {
            $item = Database::fetchOne('SELECT test_plan_id FROM test_plan_items WHERE id=?', [(int)$iid]);
            if ($item && (int)$item['test_plan_id'] === (int)$id) {
                Database::execute('UPDATE test_plan_items SET test_plan_id=? WHERE id=?', [(int)$remaining[0]['test_plan_id'], (int)$iid]);
            }
            flash('success', 'Item removed from this plan (still used by ' . count($remaining) . ' other plan(s)).');
        }
        redirect('/test-plans/' . $id);
    }

    // -- Link an existing test case from another plan in the same project --
    public static function linkableItemsJson(string $id): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        $plan = self::findOr404((int)$id);
        self::checkAccess($plan);
        $q = trim($_GET['q'] ?? '');
        $where = ['tp.project_id = ?', 'tpil2.test_plan_item_id IS NULL'];
        $params = [(int)$plan['project_id']];
        if ($q !== '') { $where[] = 'tpi.title LIKE ?'; $params[] = "%$q%"; }
        $wStr = implode(' AND ', $where);
        $items = Database::fetchAll(
            "SELECT DISTINCT tpi.id, tpi.title, tpi.priority, tp.name plan_name
             FROM test_plan_items tpi
             JOIN test_plan_item_links tpil ON tpil.test_plan_item_id = tpi.id
             JOIN test_plans tp ON tp.id = tpil.test_plan_id
             LEFT JOIN test_plan_item_links tpil2 ON tpil2.test_plan_item_id = tpi.id AND tpil2.test_plan_id = ?
             WHERE $wStr
             ORDER BY tpi.title LIMIT 50",
            array_merge([(int)$id], $params)
        );
        echo json_encode($items);
        exit;
    }

    public static function linkItems(string $id): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $plan = self::findOr404((int)$id);
        self::checkAccess($plan);
        $itemIds = array_filter(array_map('intval', (array)($_POST['item_ids'] ?? [])));
        if (!$itemIds) { flash('error', 'No test cases selected.'); redirect('/test-plans/' . $id); }
        // Only items whose home (or any existing link) is in this SAME
        // project may be linked in - cross-project sharing is not allowed,
        // it would reopen the project-isolation boundary.
        $ph = implode(',', array_fill(0, count($itemIds), '?'));
        $validItems = Database::fetchAll(
            "SELECT DISTINCT tpi.id FROM test_plan_items tpi
             JOIN test_plan_item_links tpil ON tpil.test_plan_item_id = tpi.id
             JOIN test_plans tp ON tp.id = tpil.test_plan_id
             WHERE tpi.id IN ($ph) AND tp.project_id = ?",
            array_merge($itemIds, [(int)$plan['project_id']])
        );
        $nextOrder = (int)(Database::fetchOne('SELECT COUNT(*) c FROM test_plan_item_links WHERE test_plan_id=?', [(int)$id])['c'] ?? 0);
        $linked = 0;
        foreach ($validItems as $vi) {
            try {
                Database::insert(
                    'INSERT INTO test_plan_item_links (test_plan_id, test_plan_item_id, sort_order) VALUES (?,?,?)',
                    [(int)$id, $vi['id'], $nextOrder + $linked]
                );
                $linked++;
            } catch (\Throwable) {
                // already linked - ignore (UNIQUE key)
            }
        }
        flash('success', $linked ? "$linked test case(s) linked." : 'Nothing to link (already linked or not in this project).');
        redirect('/test-plans/' . $id);
    }

    public static function import(string $id): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $plan = self::findOr404((int)$id);
        if (empty($_FILES['csv']['tmp_name'])) { flash('error', 'No file.'); redirect('/test-plans/' . $id); }
        $rows = array_map('str_getcsv', file($_FILES['csv']['tmp_name']));
        $header = array_shift($rows);
        $count = 0;
        $nextOrder = (int)(Database::fetchOne('SELECT COUNT(*) c FROM test_plan_item_links WHERE test_plan_id=?', [(int)$id])['c'] ?? 0);
        foreach ($rows as $i => $row) {
            if (empty($row[0])) continue;
            $itemId = Database::insert(
                'INSERT INTO test_plan_items (test_plan_id, title, description, expected_result, priority, status, sort_order) VALUES (?,?,?,?,?,?,?)',
                [(int)$id, $row[0] ?? '', $row[1] ?? '', $row[2] ?? '', $row[3] ?? 'medium', 'pending', $nextOrder + $count]
            );
            Database::insert(
                'INSERT INTO test_plan_item_links (test_plan_id, test_plan_item_id, sort_order) VALUES (?,?,?)',
                [(int)$id, $itemId, $nextOrder + $count]
            );
            $count++;
        }
        flash('success', "$count items imported.");
        redirect('/test-plans/' . $id);
    }

    // Validates that a test case is actually linked (home or otherwise) to
    // the plan the request claims to operate under - replaces the old plain
    // "id=? AND test_plan_id=?" ownership check, which would incorrectly
    // reject a legitimately shared item accessed via a non-home plan.
    private static function checkItemLinked(int $itemId, int $planId): void {
        $linked = Database::fetchOne(
            'SELECT 1 FROM test_plan_item_links WHERE test_plan_item_id=? AND test_plan_id=?',
            [$itemId, $planId]
        );
        if (!$linked) abort(404, 'Test Case not found in this plan');
    }

    private static function findOr404(int $id): array {
        $p = Database::fetchOne(
            'SELECT tp.*, p.name project_name FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id WHERE tp.id=?',
            [$id]
        );
        if (!$p) abort(404, 'Test plan not found');
        return $p;
    }

    private static function checkAccess(array $plan): void {
        if (Auth::isAdmin()) return;
        $pid = $plan['project_id'] ?? null;
        if (!$pid) return;
        $ids = Auth::groupProjectIds();
        if ($ids !== null && !in_array((int)$pid, $ids, true)) abort(403, 'No access to this project');
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
