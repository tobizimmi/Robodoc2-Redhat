<?php
declare(strict_types=1);

class TestCycleController
{
    public static function create(string $planId): void
    {
        Auth::requireEdit('testing');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/test-plans/' . $planId); }
        Auth::verifyCsrf();
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $environment = trim($_POST['environment'] ?? '');
        $build       = trim($_POST['build'] ?? '');
        if (!$name) { flash('error', 'Name required.'); redirect('/test-plans/' . $planId); }
        $plan = Database::fetchOne('SELECT id, project_id FROM test_plans WHERE id=?', [(int)$planId]);
        if (!$plan) abort(404);
        self::checkAccess($plan);
        $cycleId = Database::insert(
            'INSERT INTO test_cycles (test_plan_id, name, description, environment, build, status, created_by) VALUES (?,?,?,?,?,?,?)',
            [(int)$planId, $name, $description, $environment, $build, 'planned', Auth::id()]
        );
        flash('success', 'Test Cycle created.');
        redirect('/test-plans/' . $planId . '#cycle-' . $cycleId);
    }

    public static function delete(string $planId, string $id): void
    {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $cycle = Database::fetchOne(
            'SELECT tc.*, tp.project_id FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id = tc.test_plan_id WHERE tc.id=?',
            [(int)$id]
        );
        if (!$cycle) abort(404);
        self::checkAccess($cycle);
        Database::execute('DELETE FROM test_cycles WHERE id=?', [(int)$id]);
        flash('success', 'Test Cycle deleted.');
        redirect('/test-plans/' . $cycle['test_plan_id']);
    }

    public static function index(): void
    {
        Auth::requireView('testing');
        // Load all plans with their cycles and run stats - scoped to the
        // projects this user may access, same as Test Plans itself (this
        // query used to be unscoped, leaking every other project's plans,
        // cycles and runs into the Test Cycles overview).
        [$accessSql, $accessParams] = self::projectClause('tp');
        $plans = Database::fetchAll(
            "SELECT tp.*, p.name project_name FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id WHERE $accessSql ORDER BY p.name, tp.name",
            $accessParams
        );
        $planIds = array_column($plans, 'id');
        $cyclesByPlan = [];
        $runsByCycle  = [];
        if ($planIds) {
            $ph = implode(',', array_fill(0, count($planIds), '?'));
            $cycles = Database::fetchAll(
                "SELECT tc.*, COUNT(tr.id) run_count, SUM(trr.status='passed') passed, SUM(trr.status='failed') failed, COUNT(trr.id) result_count FROM test_cycles tc LEFT JOIN test_runs tr ON tr.test_cycle_id=tc.id LEFT JOIN test_run_results trr ON trr.test_run_id=tr.id WHERE tc.test_plan_id IN ($ph) GROUP BY tc.id ORDER BY tc.created_at DESC",
                $planIds
            );
            foreach ($cycles as $cyc) { $cyclesByPlan[$cyc['test_plan_id']][] = $cyc; }
            $cycleIds = array_column($cycles, 'id');
            if ($cycleIds) {
                $ph2 = implode(',', array_fill(0, count($cycleIds), '?'));
                $runs = Database::fetchAll(
                    "SELECT tr.*, COUNT(trr.id) rc, SUM(trr.status='passed') p, SUM(trr.status='failed') f FROM test_runs tr LEFT JOIN test_run_results trr ON trr.test_run_id=tr.id WHERE tr.test_cycle_id IN ($ph2) GROUP BY tr.id ORDER BY tr.created_at DESC",
                    $cycleIds
                );
                foreach ($runs as $run) { $runsByCycle[$run['test_cycle_id']][] = $run; }
            }
        }
        // Legacy runs without a cycle - same project scoping as above.
        [$legacyAccessSql, $legacyAccessParams] = self::projectClause('tp');
        $legacyRuns = Database::fetchAll(
            "SELECT tr.*, tp.name plan_name, COUNT(trr.id) rc, SUM(trr.status='passed') p, SUM(trr.status='failed') f FROM test_runs tr LEFT JOIN test_plans tp ON tp.id=tr.test_plan_id LEFT JOIN test_run_results trr ON trr.test_run_id=tr.id WHERE tr.test_cycle_id IS NULL AND $legacyAccessSql GROUP BY tr.id ORDER BY tr.created_at DESC",
            $legacyAccessParams
        );
        View::render('test-cycles/index', compact('plans', 'cyclesByPlan', 'runsByCycle', 'legacyRuns') + ['title' => 'Test Cycles']);
    }

    public static function show(string $id): void
    {
        Auth::requireView('testing');
        $cycle = Database::fetchOne(
            'SELECT tc.*, tp.name plan_name, tp.id plan_id, tp.xray_key plan_xray_key, tp.project_id
             FROM test_cycles tc
             LEFT JOIN test_plans tp ON tp.id = tc.test_plan_id
             WHERE tc.id=?',
            [(int)$id]
        );
        if (!$cycle) abort(404, 'Test Cycle not found');
        self::checkAccess($cycle);

        // Runs with result stats (no executed_by on test_runs)
        $runs = Database::fetchAll(
            "SELECT tr.*,
                    COUNT(trr.id)             result_count,
                    SUM(trr.status='new')     new,
                    SUM(trr.status='passed')  passed,
                    SUM(trr.status='failed')  failed,
                    SUM(trr.status='pending') pending,
                    SUM(trr.status='skipped') skipped,
                    SUM(trr.status='blocked') blocked
             FROM test_runs tr
             LEFT JOIN test_run_results trr ON trr.test_run_id = tr.id
             WHERE tr.test_cycle_id = ?
             GROUP BY tr.id
             ORDER BY tr.created_at DESC",
            [(int)$id]
        );

        // A test case can live in several runs of the same cycle (each run
        // picks its own subset) - only the most recently executed result per
        // test case counts toward the cycle's stats/failed/open lists, so a
        // re-run doesn't double-count or leave a stale failure lingering.
        $latestResults = Database::fetchAll(
            "SELECT trr.*, tpi.title test_name, tpi.description test_desc,
                    u.name tester_name,
                    GROUP_CONCAT(DISTINCT e.id ORDER BY e.id SEPARATOR ',')     entry_ids,
                    GROUP_CONCAT(DISTINCT e.title ORDER BY e.id SEPARATOR '||') entry_titles
             FROM test_run_results trr
             JOIN test_runs tr ON tr.id = trr.test_run_id
             LEFT JOIN test_plan_items tpi ON tpi.id = trr.test_plan_item_id
             LEFT JOIN users u ON u.id = trr.executed_by
             LEFT JOIN test_plan_item_entries tpie ON tpie.test_plan_item_id = trr.test_plan_item_id
             LEFT JOIN entries e ON e.id = tpie.entry_id
             WHERE tr.test_cycle_id = ?
               AND trr.id = (
                   SELECT trr2.id FROM test_run_results trr2
                   JOIN test_runs tr2 ON tr2.id = trr2.test_run_id
                   WHERE tr2.test_cycle_id = ?
                     AND trr2.test_plan_item_id = trr.test_plan_item_id
                   ORDER BY trr2.executed_at DESC, trr2.id DESC
                   LIMIT 1
               )
             GROUP BY trr.id",
            [(int)$id, (int)$id]
        );

        $stats = ['new' => 0, 'passed' => 0, 'failed' => 0, 'pending' => 0, 'skipped' => 0, 'blocked' => 0, 'total' => count($latestResults)];
        $failedResults  = [];
        $pendingResults = [];
        foreach ($latestResults as $r) {
            if (isset($stats[$r['status']])) $stats[$r['status']]++;
            if ($r['status'] === 'failed')  $failedResults[]  = $r;
            if ($r['status'] === 'pending') $pendingResults[] = $r;
        }
        usort($failedResults, fn($a, $b) => strcmp($b['executed_at'] ?? '', $a['executed_at'] ?? ''));
        usort($pendingResults, fn($a, $b) => strcmp($a['test_name'] ?? '', $b['test_name'] ?? ''));

        $plan = Database::fetchOne(
            'SELECT * FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id WHERE tp.id=?',
            [$cycle['plan_id']]
        );

        View::render('test-cycles/show',
            compact('cycle', 'runs', 'plan', 'stats', 'failedResults', 'pendingResults')
            + ['title' => $cycle['name']]);
    }

    public static function editCycle(string $id): void
    {
        Auth::requireEdit('testing');
        $cycle = Database::fetchOne(
            'SELECT tc.*, tp.project_id FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id = tc.test_plan_id WHERE tc.id=?',
            [(int)$id]
        );
        if (!$cycle) abort(404);
        self::checkAccess($cycle);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute('UPDATE test_cycles SET name=?, description=?, environment=?, build=?, status=? WHERE id=?',
                [trim($_POST['name']??''), trim($_POST['description']??''), trim($_POST['environment']??''), trim($_POST['build']??''), $_POST['status']??'planned', (int)$id]);
            flash('success', 'Test Cycle updated.');
            redirect('/test-cycles/' . $id);
        }
        View::render('test-cycles/edit', compact('cycle') + ['title' => 'Edit Cycle']);
    }

    public static function assignCycle(string $id): void
    {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $run = Database::fetchOne(
            'SELECT tr.test_plan_id, tp.project_id FROM test_runs tr LEFT JOIN test_plans tp ON tp.id = tr.test_plan_id WHERE tr.id=?',
            [(int)$id]
        );
        if (!$run) { http_response_code(404); echo json_encode(['error' => 'Test run not found']); exit; }
        self::checkAccess($run);
        $cycleId = (int)($_POST['cycle_id'] ?? 0) ?: null;
        $cycle = null;
        if ($cycleId) {
            // Must belong to the same plan as the run - otherwise a user with
            // access to this run could pull in a cycle name/id from a
            // completely different (possibly inaccessible) plan or project.
            $cycle = Database::fetchOne('SELECT name FROM test_cycles WHERE id=? AND test_plan_id=?', [$cycleId, $run['test_plan_id']]);
            if (!$cycle) { http_response_code(422); echo json_encode(['error' => 'That cycle does not belong to this run\'s plan.']); exit; }
        }
        Database::execute('UPDATE test_runs SET test_cycle_id=? WHERE id=?', [$cycleId, (int)$id]);
        echo json_encode(['success' => true, 'cycle_name' => $cycle['name'] ?? '']);
        exit;
    }

    public static function updateStatus(string $id): void
    {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $cycle = Database::fetchOne(
            'SELECT tc.id, tp.project_id FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id = tc.test_plan_id WHERE tc.id=?',
            [(int)$id]
        );
        if (!$cycle) { http_response_code(404); echo json_encode(['error' => 'Test Cycle not found']); exit; }
        if (!Auth::isAdmin()) {
            $ids = Auth::groupProjectIds();
            $pid = $cycle['project_id'] ?? null;
            if ($pid && $ids !== null && !in_array((int)$pid, $ids, true)) {
                http_response_code(403); echo json_encode(['error' => 'No access to this project']); exit;
            }
        }
        $status = $_POST['status'] ?? '';
        $allowed = ['planned','active','completed','aborted'];
        if (!in_array($status, $allowed)) { http_response_code(422); echo json_encode(['error' => 'Invalid status']); exit; }
        Database::execute('UPDATE test_cycles SET status=? WHERE id=?', [$status, (int)$id]);
        echo json_encode(['success' => true, 'status' => $status]);
        exit;
    }

    public static function apiList(): void
    {
        Auth::require();
        header('Content-Type: application/json');
        $planId = (int)($_GET['plan_id'] ?? 0);
        if (!$planId) { echo json_encode([]); exit; }
        $plan = Database::fetchOne('SELECT project_id FROM test_plans WHERE id=?', [$planId]);
        if (!$plan) { echo json_encode([]); exit; }
        if (!Auth::isAdmin()) {
            $ids = Auth::groupProjectIds();
            $pid = $plan['project_id'] ?? null;
            if ($pid && $ids !== null && !in_array((int)$pid, $ids, true)) { echo json_encode([]); exit; }
        }
        $cycles = Database::fetchAll(
            'SELECT id, name, status FROM test_cycles WHERE test_plan_id=? ORDER BY created_at DESC',
            [$planId]
        );
        echo json_encode($cycles);
        exit;
    }

    // -- Project access helpers (same pattern as TestPlanController) --------
    private static function checkAccess(array $row): void {
        if (Auth::isAdmin()) return;
        $pid = $row['project_id'] ?? null;
        if (!$pid) return;
        $ids = Auth::groupProjectIds();
        if ($ids !== null && !in_array((int)$pid, $ids, true)) abort(403, 'No access to this project');
    }

    private static function projectClause(string $alias): array {
        $ids = Auth::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ["$alias.project_id IS NULL", []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.project_id IS NULL OR $alias.project_id IN ($ph))", $ids];
    }
}
