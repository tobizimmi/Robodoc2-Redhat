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
        if (!$name) { flash('error', 'Name erforderlich.'); redirect('/test-plans/' . $planId); }
        $plan = Database::fetchOne('SELECT id FROM test_plans WHERE id=?', [(int)$planId]);
        if (!$plan) abort(404);
        $cycleId = Database::insert(
            'INSERT INTO test_cycles (test_plan_id, name, description, environment, build, status, created_by) VALUES (?,?,?,?,?,?,?)',
            [(int)$planId, $name, $description, $environment, $build, 'planned', Auth::id()]
        );
        flash('success', 'Test Cycle erstellt.');
        redirect('/test-plans/' . $planId . '#cycle-' . $cycleId);
    }

    public static function delete(string $planId, string $id): void
    {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $cycle = Database::fetchOne('SELECT * FROM test_cycles WHERE id=?', [(int)$id]);
        if (!$cycle) abort(404);
        Database::execute('DELETE FROM test_cycles WHERE id=?', [(int)$id]);
        flash('success', 'Test Cycle geloescht.');
        redirect('/test-plans/' . $cycle['test_plan_id']);
    }

    public static function index(): void
    {
        Auth::requireView('testing');
        // Load all plans with their cycles and run stats
        $plans = Database::fetchAll(
            'SELECT tp.*, p.name project_name FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id ORDER BY p.name, tp.name'
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
        // Legacy runs without a cycle
        $legacyRuns = Database::fetchAll(
            "SELECT tr.*, tp.name plan_name, COUNT(trr.id) rc, SUM(trr.status='passed') p, SUM(trr.status='failed') f FROM test_runs tr LEFT JOIN test_plans tp ON tp.id=tr.test_plan_id LEFT JOIN test_run_results trr ON trr.test_run_id=tr.id WHERE tr.test_cycle_id IS NULL GROUP BY tr.id ORDER BY tr.created_at DESC"
        );
        View::render('test-cycles/index', compact('plans', 'cyclesByPlan', 'runsByCycle', 'legacyRuns') + ['title' => 'Test Cycles']);
    }

    public static function show(string $id): void
    {
        Auth::requireView('testing');
        $cycle = Database::fetchOne(
            'SELECT tc.*, tp.name plan_name, tp.id plan_id, tp.xray_key plan_xray_key FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id WHERE tc.id=?',
            [(int)$id]
        );
        if (!$cycle) abort(404, 'Test Cycle nicht gefunden');
        $runs = Database::fetchAll(
            "SELECT tr.*, COUNT(trr.id) result_count, SUM(trr.status='passed') passed, SUM(trr.status='failed') failed, SUM(trr.status='pending') pending FROM test_runs tr LEFT JOIN test_run_results trr ON trr.test_run_id=tr.id WHERE tr.test_cycle_id=? GROUP BY tr.id ORDER BY tr.created_at DESC",
            [(int)$id]
        );
        $plan = Database::fetchOne('SELECT * FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id WHERE tp.id=?', [$cycle['plan_id']]);
        View::render('test-cycles/show', compact('cycle', 'runs', 'plan') + ['title' => $cycle['name']]);
    }

    public static function editCycle(string $id): void
    {
        Auth::requireEdit('testing');
        $cycle = Database::fetchOne('SELECT * FROM test_cycles WHERE id=?', [(int)$id]);
        if (!$cycle) abort(404);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute('UPDATE test_cycles SET name=?, description=?, environment=?, build=?, status=? WHERE id=?',
                [trim($_POST['name']??''), trim($_POST['description']??''), trim($_POST['environment']??''), trim($_POST['build']??''), $_POST['status']??'planned', (int)$id]);
            flash('success', 'Test Cycle aktualisiert.');
            redirect('/test-cycles/' . $id);
        }
        View::render('test-cycles/edit', compact('cycle') + ['title' => 'Cycle bearbeiten']);
    }

    public static function assignCycle(string $id): void
    {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $cycleId = (int)($_POST['cycle_id'] ?? 0) ?: null;
        Database::execute('UPDATE test_runs SET test_cycle_id=? WHERE id=?', [$cycleId, (int)$id]);
        $cycle = $cycleId ? Database::fetchOne('SELECT name FROM test_cycles WHERE id=?', [$cycleId]) : null;
        echo json_encode(['success' => true, 'cycle_name' => $cycle['name'] ?? '']);
        exit;
    }

    public static function updateStatus(string $id): void
    {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $status = $_POST['status'] ?? '';
        $allowed = ['planned','active','completed','aborted'];
        if (!in_array($status, $allowed)) { http_response_code(422); echo json_encode(['error' => 'Invalid status']); exit; }
        Database::execute('UPDATE test_cycles SET status=? WHERE id=?', [$status, (int)$id]);
        echo json_encode(['success' => true, 'status' => $status]);
        exit;
    }
}
