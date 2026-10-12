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

        [$categories, $items, $detailsByItem] = $projectId
            ? self::fetchOverviewData($projectId)
            : [[], [], []];

        View::render('test-overview/index', compact('projects', 'projectId', 'categories', 'items', 'detailsByItem') + ['title' => 'Test Übersicht']);
    }

    public static function exportCsv(): void
    {
        Auth::requireView('testing');
        $projectId = (int)($_GET['project_id'] ?? 0);
        if (!$projectId) abort(404);

        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $project = Database::fetchOne("SELECT name FROM projects p WHERE id=? AND $pSql", array_merge([$projectId], $pParams));
        if (!$project) abort(403);

        [$categories, $items, $detailsByItem] = self::fetchOverviewData($projectId);
        $catById = array_column($categories, null, 'id');
        $statusLabels = ['new'=>'Neu','pending'=>'Pending','passed'=>'Passed','failed'=>'Failed','skipped'=>'Skipped','blocked'=>'Blocked'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="test-uebersicht-' . preg_replace('/[^a-z0-9-]/i', '_', $project['name']) . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['Kategorie','Test Case','Plan','Priorität','Run','Status','PQL','Tester','Ausgeführt am','Notizen'], ';');

        foreach ($items as $it) {
            $catName = $it['category_id'] && isset($catById[$it['category_id']]) ? $catById[$it['category_id']]['name'] : 'Ohne Kategorie';
            $rows = $detailsByItem[$it['id']] ?? [];
            if (!$rows) {
                fputcsv($out, [$catName, $it['title'], $it['plan_name'], $it['priority'], '', 'Neu', '', '', '', ''], ';');
                continue;
            }
            foreach ($rows as $dr) {
                fputcsv($out, [
                    $catName, $it['title'], $it['plan_name'], $it['priority'],
                    $dr['run_name'] ?: ('Run #' . $dr['run_id']),
                    $statusLabels[$dr['status']] ?? $dr['status'],
                    $dr['pql_issue_key'] ?: '',
                    $dr['tester_name'] ?: '',
                    $dr['executed_at'] ?: '',
                    $dr['notes'] ?: '',
                ], ';');
            }
        }
        fclose($out);
        exit;
    }

    // Shared by index() (page render) and exportCsv() (Excel-compatible CSV),
    // so the two can never drift apart on what "the overview" contains.
    private static function fetchOverviewData(int $projectId): array
    {
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

        // Full per-run detail, shown when a row is expanded - every result
        // this test case has ever had, across every Test Run it was part of.
        $detailRows = Database::fetchAll(
            "SELECT trr.*, tr.name run_name, tr.id run_id, u.name tester_name
             FROM test_run_results trr
             JOIN test_plan_items tpi ON tpi.id = trr.test_plan_item_id
             JOIN test_plans tp ON tp.id = tpi.test_plan_id
             JOIN test_runs tr ON tr.id = trr.test_run_id
             LEFT JOIN users u ON u.id = trr.assigned_tester
             WHERE tp.project_id = ?
             ORDER BY trr.test_plan_item_id, trr.executed_at DESC, tr.created_at DESC",
            [$projectId]
        );
        $detailsByItem = [];
        foreach ($detailRows as $dr) {
            $detailsByItem[$dr['test_plan_item_id']][] = $dr;
        }

        return [$categories, $items, $detailsByItem];
    }
}
