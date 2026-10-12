<?php
declare(strict_types=1);

// Per-project overview: every test case (test_plan_item), grouped by category,
// with its cumulative status history across all test runs it has ever been
// part of. Read-only - editing happens on the Test Plan / Test Run pages.
class TestOverviewController
{
    public static function index(): void
    {
        Auth::requireView('testing');
        $projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;

        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE $pSql ORDER BY name", $pParams);

        $categories = [];
        $items      = [];
        if ($projectId) {
            $categories = Database::fetchAll(
                'SELECT * FROM test_categories WHERE project_id=? ORDER BY sort_order, name',
                [$projectId]
            );

            $items = Database::fetchAll(
                "SELECT tpi.*, tp.name plan_name,
                        COUNT(trr.id)              total_runs,
                        SUM(trr.status='new')      c_new,
                        SUM(trr.status='pending')  c_pending,
                        SUM(trr.status='passed')   c_passed,
                        SUM(trr.status='failed')   c_failed,
                        SUM(trr.status='skipped')  c_skipped,
                        SUM(trr.status='blocked')  c_blocked,
                        MAX(trr.executed_at)       last_executed_at
                 FROM test_plan_items tpi
                 JOIN test_plans tp ON tp.id = tpi.test_plan_id
                 LEFT JOIN test_run_results trr ON trr.test_plan_item_id = tpi.id
                 WHERE tp.project_id = ?
                 GROUP BY tpi.id
                 ORDER BY tpi.sort_order, tpi.id",
                [$projectId]
            );

            // Latest result per item - SUM() above gives totals per status but not
            // which one happened most recently, so a separate pass picks that.
            $latestRows = Database::fetchAll(
                "SELECT trr.test_plan_item_id, trr.status, trr.executed_at
                 FROM test_run_results trr
                 JOIN test_plan_items tpi ON tpi.id = trr.test_plan_item_id
                 JOIN test_plans tp ON tp.id = tpi.test_plan_id
                 WHERE tp.project_id = ? AND trr.executed_at IS NOT NULL
                 ORDER BY trr.executed_at DESC",
                [$projectId]
            );
            $latestByItem = [];
            foreach ($latestRows as $r) {
                if (!isset($latestByItem[$r['test_plan_item_id']])) {
                    $latestByItem[$r['test_plan_item_id']] = $r['status'];
                }
            }
            foreach ($items as &$it) {
                $it['latest_status'] = $latestByItem[$it['id']] ?? 'new';
            }
            unset($it);
        }

        View::render('test-overview/index', compact('projects', 'projectId', 'categories', 'items') + ['title' => 'Test Übersicht']);
    }
}
