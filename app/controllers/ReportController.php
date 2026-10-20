<?php
declare(strict_types=1);

class ReportController
{
    public static function index(): void
    {
        Auth::requireView('reports');

        [$pSql, $pParams]   = Auth::projectAccessClause();
        $projects   = Database::fetchAll("SELECT id, name, color FROM projects WHERE status='active' AND $pSql ORDER BY name", $pParams);
        $entryTypes = Database::fetchAll('SELECT id, name, color FROM entry_types ORDER BY sort_order, name');
        [$tpSql, $tpParams] = self::projectClause('tp');
        $testPlans  = Database::fetchAll("SELECT id, name, project_id FROM test_plans tp WHERE $tpSql ORDER BY name", $tpParams);

        $settings = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $report = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $report = self::generate();
        }

        View::render('reports/index', compact('projects', 'entryTypes', 'testPlans', 'report', 'settings'), 'app');
    }

    private static function generate(): array
    {
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $dateFrom     = $_POST['date_from'] ?? '';
        $dateTo       = $_POST['date_to'] ?? '';
        $testPlanId   = (int)($_POST['test_plan_id'] ?? 0);
        $typeIds      = array_map('intval', (array)($_POST['type_ids'] ?? []));

        if ($projectId) self::checkProjectAccess($projectId);

        // Build entry query - always scoped to this user's actual entry
        // access, not just the optional project filter, otherwise leaving
        // the project picker empty returned every project's entries.
        $onlyRelevant = ($_POST['only_report_relevant'] ?? '1') === '1';
        [$accessClause, $accessParams] = Auth::entryAccessClause();
        $where  = $onlyRelevant ? ['e.is_report_relevant = 1', $accessClause] : ['1=1', $accessClause];
        $params = $accessParams;

        if ($projectId) {
            $where[]  = 'e.project_id = ?';
            $params[] = $projectId;
        }
        if ($dateFrom) {
            $where[]  = 'e.entry_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[]  = 'e.entry_date <= ?';
            $params[] = $dateTo;
        }
        if ($typeIds) {
            $in       = implode(',', array_fill(0, count($typeIds), '?'));
            $where[]  = "e.entry_type_id IN ($in)";
            $params   = array_merge($params, $typeIds);
        }

        $whereStr = implode(' AND ', $where);

        $entries = Database::fetchAll("
            SELECT e.id, e.title, e.description, e.entry_date, e.entry_type_id,
                   et.name AS type_name, et.color AS type_color,
                   p.name AS project_name, p.color AS project_color
            FROM entries e
            LEFT JOIN entry_types et ON et.id = e.entry_type_id
            LEFT JOIN projects p ON p.id = e.project_id
            WHERE $whereStr
            ORDER BY e.entry_date DESC, e.id DESC
            LIMIT 500
        ", $params);

        // Count by type
        $byType = Database::fetchAll("
            SELECT et.name, et.color, COUNT(e.id) AS cnt
            FROM entries e
            LEFT JOIN entry_types et ON et.id = e.entry_type_id
            WHERE $whereStr
            GROUP BY e.entry_type_id
            ORDER BY cnt DESC
        ", $params);

        $planData = null;
        if ($testPlanId) {
            $plan  = Database::fetchOne('SELECT * FROM test_plans WHERE id = ?', [$testPlanId]);
            if ($plan) self::checkProjectAccess((int)($plan['project_id'] ?? 0));
            $items = Database::fetchAll("
                SELECT pi.*,
                       COALESCE(
                         (SELECT status FROM test_run_results rr
                          JOIN test_runs tr ON tr.id = rr.test_run_id
                          WHERE rr.test_plan_item_id = pi.id
                          ORDER BY tr.created_at DESC LIMIT 1),
                         'pending'
                       ) AS status
                FROM test_plan_items pi
                JOIN test_plan_item_links pil ON pil.test_plan_item_id = pi.id AND pil.test_plan_id = ?
                ORDER BY pil.sort_order, pi.id
            ", [$testPlanId]);

            $counts = [
                'passed'  => count(array_filter($items, fn($i) => $i['status'] === 'passed')),
                'failed'  => count(array_filter($items, fn($i) => $i['status'] === 'failed')),
                'skipped' => count(array_filter($items, fn($i) => $i['status'] === 'skipped')),
                'pending' => count(array_filter($items, fn($i) => $i['status'] === 'pending')),
            ];

            $planData = compact('plan', 'items', 'counts');
        }

        return [
            'entries'    => $entries,
            'total'      => count($entries),
            'byType'     => $byType,
            'planData'   => $planData,
            'filters'    => compact('projectId', 'dateFrom', 'dateTo', 'testPlanId', 'typeIds'),
        ];
    }

    public static function firmwareComparison(): void
    {
        Auth::requireView('reports');

        $projectId = (int)($_GET['project_id'] ?? 0);
        if ($projectId) self::checkProjectAccess($projectId);
        [$accessClause, $accessParams] = Auth::entryAccessClause();
        $where  = $projectId ? "WHERE e.project_id = ? AND $accessClause" : "WHERE $accessClause";
        $params = $projectId ? array_merge([$projectId], $accessParams) : $accessParams;

        $rows = Database::fetchAll("
            SELECT e.firmware_version,
                   COUNT(*)                                            total,
                   SUM(CASE WHEN et.name = 'Bug' THEN 1 ELSE 0 END)  bugs,
                   SUM(CASE WHEN e.status = 'finalized' THEN 1 ELSE 0 END) resolved,
                   MIN(e.entry_date) first_date,
                   MAX(e.entry_date) last_date
            FROM entries e
            LEFT JOIN entry_types et ON et.id = e.entry_type_id
            $where
              AND e.firmware_version IS NOT NULL AND e.firmware_version != ''
            GROUP BY e.firmware_version
            ORDER BY MIN(e.entry_date) DESC
        ", $params);

        $byType = [];
        if ($rows) {
            $types = Database::fetchAll("SELECT id, name, color FROM entry_types ORDER BY sort_order, name");
            foreach ($types as $t) {
                $counts = Database::fetchAll("
                    SELECT e.firmware_version, COUNT(*) cnt
                    FROM entries e
                    WHERE e.entry_type_id = ? AND e.firmware_version IS NOT NULL AND e.firmware_version != ''
                      AND $accessClause" . ($projectId ? ' AND e.project_id=?' : '') . "
                    GROUP BY e.firmware_version
                ", array_merge([$t['id']], $accessParams, $projectId ? [$projectId] : []));
                foreach ($counts as $c) {
                    $byType[$c['firmware_version']][$t['name']] = (int)$c['cnt'];
                }
            }
        }

        [$pSql, $pParams] = Auth::projectAccessClause();
        $projects = Database::fetchAll("SELECT id, name FROM projects WHERE status='active' AND $pSql ORDER BY name", $pParams);
        json(compact('rows', 'byType', 'projects'));
    }
    // ── Report Builder: template list ────────────────────────────────────────
    public static function builder(): void
    {
        Auth::requireView('reports');
        $templates = Database::fetchAll(
            'SELECT id, name, description, config, is_default, created_at FROM report_templates ORDER BY name'
        );
        [$pSql, $pParams] = Auth::projectAccessClause();
        $projects   = Database::fetchAll("SELECT id, name, color FROM projects WHERE status='active' AND $pSql ORDER BY name", $pParams);
        $entryTypes = Database::fetchAll('SELECT id, name, color FROM entry_types ORDER BY sort_order, name');
        $tags       = Database::fetchAll('SELECT id, name FROM tags ORDER BY name');
        [$tcSql, $tcParams] = self::projectClause('tc');
        $categories = Database::fetchAll(
            "SELECT tc.id, tc.name, tc.project_id, p.name project_name
             FROM test_categories tc LEFT JOIN projects p ON p.id = tc.project_id
             WHERE $tcSql
             ORDER BY p.name, tc.sort_order, tc.name",
            $tcParams
        );
        View::render('reports/builder', compact('templates', 'projects', 'entryTypes', 'tags', 'categories'), 'app');
    }

    // ── Save template ─────────────────────────────────────────────────────────
    public static function saveTemplate(): void
    {
        Auth::requireView('reports');
        Auth::verifyCsrf();
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $config = $_POST['config'] ?? '{}';
        // Validate JSON
        $decoded = json_decode($config, true);
        if (!$name || !$decoded) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }
        if ($id) {
            Database::execute('UPDATE report_templates SET name=?,description=?,config=?,updated_at=NOW() WHERE id=?',
                [$name, $desc, $config, $id]);
        } else {
            $id = Database::insert('INSERT INTO report_templates (name,description,config,created_by) VALUES (?,?,?,?)',
                [$name, $desc, $config, Auth::id()]);
        }
        header('Content-Type: application/json');
        echo json_encode(['id' => $id, 'ok' => true]);
    }

    // ── Delete template ───────────────────────────────────────────────────────
    public static function deleteTemplate(string $id): void
    {
        Auth::requireView('reports');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM report_templates WHERE id=?', [(int)$id]);
        redirect('/reports/builder');
    }

    // ── Shared: fetch entries + all block-level aggregates for a given filter
    // set. Used by every report-rendering entry point below so the query set
    // (and the "compare"/"trend" block data) never drifts between them.
    // $cycleIds: one or more test_cycles.id - multiple cycles are combined into
    // one aggregated result (e.g. "Regression Run 1" + "Regression Run 2"),
    // not shown as separate per-cycle sections.
    private static function fetchTestCycleData(array $cycleIds, bool $enforceAccess = true): array
    {
        $cycleIds = array_values(array_unique(array_filter(array_map('intval', $cycleIds))));
        if (!$cycleIds) return [];
        $ph = implode(',', array_fill(0, count($cycleIds), '?'));

        [$tpSql, $tpParams] = $enforceAccess ? self::projectClause('tp') : ['1=1', []];
        $cycles = Database::fetchAll(
            "SELECT tc.*, tp.name plan_name, p.name project_name
             FROM test_cycles tc
             LEFT JOIN test_plans tp ON tp.id = tc.test_plan_id
             LEFT JOIN projects p ON p.id = tp.project_id
             WHERE tc.id IN ($ph) AND $tpSql
             ORDER BY tc.created_at", array_merge($cycleIds, $tpParams)
        );
        if (!$cycles) return [];
        // Re-derive $cycleIds from the access-filtered set so every query
        // below only ever touches cycles this user (or the pre-authorized
        // public link) may actually see.
        $cycleIds = array_column($cycles, 'id');
        $ph = implode(',', array_fill(0, count($cycleIds), '?'));

        $stats = Database::fetchOne(
            "SELECT SUM(trr.status='new') new, SUM(trr.status='passed') passed, SUM(trr.status='failed') failed,
                    SUM(trr.status='pending') pending, SUM(trr.status='skipped') skipped,
                    SUM(trr.status='blocked') blocked, COUNT(trr.id) total
             FROM test_runs tr
             LEFT JOIN test_run_results trr ON trr.test_run_id = tr.id
             WHERE tr.test_cycle_id IN ($ph)", $cycleIds
        );

        $runs = Database::fetchAll(
            "SELECT tr.*,
                    COUNT(trr.id) result_count,
                    SUM(trr.status='new') new,
                    SUM(trr.status='passed') passed, SUM(trr.status='failed') failed,
                    SUM(trr.status='pending') pending
             FROM test_runs tr
             LEFT JOIN test_run_results trr ON trr.test_run_id = tr.id
             WHERE tr.test_cycle_id IN ($ph)
             GROUP BY tr.id ORDER BY tr.created_at DESC", $cycleIds
        );

        // Linked entries joined in below (via test_plan_item_entries) aren't
        // necessarily in the same project as the cycle, so gate them too.
        [$entryJoinClause, $entryJoinParams] = $enforceAccess ? Auth::entryAccessClause('e') : ['1=1', []];
        $failed = Database::fetchAll(
            "SELECT trr.*, tpi.title test_name, tpi.description test_desc,
                    u.name tester_name,
                    GROUP_CONCAT(DISTINCT e.id ORDER BY e.id SEPARATOR ',') entry_ids,
                    GROUP_CONCAT(DISTINCT e.title ORDER BY e.id SEPARATOR '||') entry_titles
             FROM test_run_results trr
             JOIN test_runs tr ON tr.id = trr.test_run_id
             LEFT JOIN test_plan_items tpi ON tpi.id = trr.test_plan_item_id
             LEFT JOIN users u ON u.id = trr.executed_by
             LEFT JOIN test_plan_item_entries tpie ON tpie.test_plan_item_id = trr.test_plan_item_id
             LEFT JOIN entries e ON e.id = tpie.entry_id AND $entryJoinClause
             WHERE tr.test_cycle_id IN ($ph) AND trr.status = 'failed'
             GROUP BY trr.id ORDER BY trr.executed_at DESC", array_merge($entryJoinParams, $cycleIds)
        );

        $pending = Database::fetchAll(
            "SELECT trr.*, tpi.title test_name, tpi.description test_desc
             FROM test_run_results trr
             JOIN test_runs tr ON tr.id = trr.test_run_id
             LEFT JOIN test_plan_items tpi ON tpi.id = trr.test_plan_item_id
             WHERE tr.test_cycle_id IN ($ph) AND trr.status = 'pending'
             ORDER BY tpi.title", $cycleIds
        );

        $linkedEntries = Database::fetchAll(
            "SELECT DISTINCT e.id, e.title, e.status, e.priority, p.name project_name,
                    GROUP_CONCAT(DISTINCT tpi.title ORDER BY tpi.title SEPARATOR ', ') test_names,
                    GROUP_CONCAT(DISTINCT trr.status ORDER BY trr.status SEPARATOR ', ') test_statuses
             FROM entries e
             LEFT JOIN projects p ON p.id = e.project_id
             JOIN test_plan_item_entries tpie ON tpie.entry_id = e.id
             JOIN test_plan_items tpi ON tpi.id = tpie.test_plan_item_id
             JOIN test_run_results trr ON trr.test_plan_item_id = tpi.id
             JOIN test_runs tr ON tr.id = trr.test_run_id
             WHERE tr.test_cycle_id IN ($ph) AND $entryJoinClause
             GROUP BY e.id ORDER BY e.title", array_merge($cycleIds, $entryJoinParams)
        );

        // Bugs created/linked from a test result (Test Run "Bug verknüpfen" /
        // "Neuen Bug erstellen") - distinct from $linkedEntries above, which
        // covers the older, separate test_plan_item_entries linkage.
        $bugs = Database::fetchAll(
            "SELECT trb.*, tpi.title test_name, tr.name run_name,
                    e.title entry_title, e.status entry_status, e.priority entry_priority
             FROM test_run_bugs trb
             JOIN test_run_results trr ON trr.id = trb.test_run_result_id
             JOIN test_runs tr ON tr.id = trr.test_run_id
             JOIN test_plan_items tpi ON tpi.id = trr.test_plan_item_id
             LEFT JOIN entries e ON e.id = trb.entry_id AND $entryJoinClause
             WHERE tr.test_cycle_id IN ($ph)
             ORDER BY trb.created_at DESC", array_merge($entryJoinParams, $cycleIds)
        );

        return compact('cycles', 'stats', 'runs', 'failed', 'pending', 'linkedEntries', 'bugs');
    }

    private static function fetchReportData(int $projectId, string $dateFrom, string $dateTo, array $typeIds, bool $enforceAccess = true): array
    {
        [$accessClause, $accessParams] = $enforceAccess ? Auth::entryAccessClause() : ['1=1', []];
        $where = ['e.is_merged=0', $accessClause]; $params = $accessParams;
        if ($projectId) { $where[] = 'e.project_id=?';  $params[] = $projectId; }
        if ($dateFrom)  { $where[] = 'e.entry_date>=?'; $params[] = $dateFrom; }
        if ($dateTo)    { $where[] = 'e.entry_date<=?'; $params[] = $dateTo; }
        if ($typeIds)   {
            $ph = implode(',', array_fill(0, count($typeIds), '?'));
            $where[] = "e.entry_type_id IN ($ph)"; $params = array_merge($params, $typeIds);
        }
        $wStr = implode(' AND ', $where);

        $entries = Database::fetchAll("
            SELECT e.id, e.title, e.description, e.entry_date, e.status, e.priority,
                   e.mower_serial, e.firmware_version, e.app_version,
                   e.jira_issue_key, e.jira_issue_url, e.jira_status,
                   e.zentao_bug_id, e.zentao_bug_url, e.zentao_status,
                   e.temperature, e.weather_condition, e.gps_lat, e.gps_lon,
                   e.project_status_robot, e.parent_id,
                   et.name type_name, et.color type_color,
                   p.name project_name, p.color project_color, p.description project_desc,
                   ep.title epic_title, pe.title parent_title, u.name creator,
                   (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') FROM entry_tags etg
                    JOIN tags t ON t.id=etg.tag_id WHERE etg.entry_id=e.id) AS tag_names
            FROM entries e
            LEFT JOIN entry_types et ON et.id=e.entry_type_id
            LEFT JOIN projects p ON p.id=e.project_id
            LEFT JOIN users u ON u.id=e.created_by
            LEFT JOIN epics ep ON ep.id=e.epic_id
            LEFT JOIN entries pe ON pe.id=e.parent_id
            WHERE $wStr ORDER BY e.entry_date DESC, e.id DESC LIMIT 1000
        ", $params);

        $project = $projectId
            ? Database::fetchOne("SELECT id,name,color,description,status FROM projects WHERE id=?", [$projectId])
            : null;

        $byType     = Database::fetchAll("SELECT et.name, et.color, COUNT(e.id) cnt FROM entries e LEFT JOIN entry_types et ON et.id=e.entry_type_id WHERE $wStr GROUP BY e.entry_type_id ORDER BY cnt DESC", $params);
        $byStatus   = Database::fetchAll("SELECT e.status, COUNT(*) cnt FROM entries e WHERE $wStr GROUP BY e.status ORDER BY cnt DESC", $params);
        $byPriority = Database::fetchAll("SELECT e.priority, COUNT(*) cnt FROM entries e WHERE $wStr GROUP BY e.priority ORDER BY cnt DESC", $params);
        $byFirmware = Database::fetchAll("SELECT e.firmware_version, COUNT(*) cnt FROM entries e WHERE $wStr AND e.firmware_version IS NOT NULL AND e.firmware_version!='' GROUP BY e.firmware_version ORDER BY cnt DESC LIMIT 15", $params);

        // Weekly trend buckets ("chart_trend" block) — new entries per ISO week.
        $trend = Database::fetchAll("SELECT YEARWEEK(e.entry_date, 3) yw, MIN(e.entry_date) wk_start, COUNT(*) cnt FROM entries e WHERE $wStr GROUP BY yw ORDER BY yw", $params);

        // Previous period of equal length ("compare" block) — only meaningful
        // once a concrete date range was actually selected.
        $prev = null;
        if ($dateFrom && $dateTo) {
            $d1   = new DateTime($dateFrom);
            $d2   = new DateTime($dateTo);
            $days = max(1, (int)$d1->diff($d2)->days + 1);
            $prevTo   = (clone $d1)->modify('-1 day');
            $prevFrom = (clone $prevTo)->modify('-' . ($days - 1) . ' days');

            $pWhere = ['e.is_merged=0', $accessClause, 'e.entry_date>=?', 'e.entry_date<=?'];
            $pParams = array_merge($accessParams, [$prevFrom->format('Y-m-d'), $prevTo->format('Y-m-d')]);
            if ($projectId) { $pWhere[] = 'e.project_id=?'; $pParams[] = $projectId; }
            if ($typeIds)   {
                $ph = implode(',', array_fill(0, count($typeIds), '?'));
                $pWhere[] = "e.entry_type_id IN ($ph)"; $pParams = array_merge($pParams, $typeIds);
            }
            $pWStr = implode(' AND ', $pWhere);
            $openSt = "'new','open','internal','reviewed','pending_at_supplier','ready_for_test'";
            $prevTotal = (int)(Database::fetchOne("SELECT COUNT(*) c FROM entries e WHERE $pWStr", $pParams)['c'] ?? 0);
            $prevOpen  = (int)(Database::fetchOne("SELECT COUNT(*) c FROM entries e WHERE $pWStr AND e.status IN ($openSt)", $pParams)['c'] ?? 0);
            $prev = ['from' => $prevFrom->format('Y-m-d'), 'to' => $prevTo->format('Y-m-d'), 'total' => $prevTotal, 'open' => $prevOpen];
        }

        // Load test cycle data if test_plan_id provided. test_cycle_id may be a
        // single value (old links) or an array (multi-select "combine cycles").
        $testPlanId   = (int)($_REQUEST['test_plan_id'] ?? 0);
        $testCycleIds = array_map('intval', (array)($_REQUEST['test_cycle_id'] ?? []));
        $testCycleIds = array_filter($testCycleIds);
        $testData = [];
        if ($testCycleIds) {
            $testData = self::fetchTestCycleData($testCycleIds, $enforceAccess);
        } elseif ($testPlanId) {
            if ($enforceAccess) {
                $planRow = Database::fetchOne('SELECT project_id FROM test_plans WHERE id=?', [$testPlanId]);
                if ($planRow) self::checkProjectAccess((int)($planRow['project_id'] ?? 0));
            }
            // Get most recent cycle
            $latestCycle = Database::fetchOne('SELECT id FROM test_cycles WHERE test_plan_id=? ORDER BY created_at DESC LIMIT 1', [$testPlanId]);
            if ($latestCycle) $testData = self::fetchTestCycleData([(int)$latestCycle['id']], $enforceAccess);
        }

        // Test Übersicht block: the whole project's test cases grouped by
        // category with their full run history - independent of which cycle(s)
        // were picked above, since the overview's whole point is cross-cycle.
        $testOverview = [];
        if ($projectId) {
            [$ovCategories, $ovItems, $ovDetailsByItem] = TestOverviewController::fetchOverviewData($projectId);
            $testOverview = ['categories' => $ovCategories, 'items' => $ovItems, 'detailsByItem' => $ovDetailsByItem];
        }

        return compact('entries', 'project', 'byType', 'byStatus', 'byPriority', 'byFirmware', 'trend', 'prev', 'testData', 'testOverview');
    }

    // ── Generate report from template ─────────────────────────────────────────
    public static function generateFromTemplate(string $id): void
    {
        Auth::requireView('reports');
        $tpl = Database::fetchOne('SELECT * FROM report_templates WHERE id=?', [(int)$id]);
        if (!$tpl) { abort(404, 'Template not found'); }
        $config = json_decode($tpl['config'], true) ?: [];

        // The builder never actually writes a config.filters block (only
        // config.preview, set from the preview panel's own fields), so
        // config.filters.project_id was always empty here - falling back to
        // config.preview mirrors what generateReport() already does.
        $projectId = (int)($config['filters']['project_id'] ?? $config['preview']['project_id'] ?? 0);
        $dateFrom  = $config['filters']['date_from'] ?? $config['preview']['date_from'] ?? '';
        $dateTo    = $config['filters']['date_to']   ?? $config['preview']['date_to']   ?? '';
        $typeIds   = array_map('intval', $config['filters']['type_ids'] ?? $config['preview']['type_ids'] ?? []);

        if ($projectId) self::checkProjectAccess($projectId);
        $data = self::fetchReportData($projectId, $dateFrom, $dateTo, $typeIds);
        View::render('reports/pdf', compact('tpl', 'config', 'data'), 'public');
    }

    // ── Generate report: POST from "Bericht erstellen" modal ────────────────
    public static function generateReport(string $id): void
    {
        Auth::requireView('reports');
        $tpl = Database::fetchOne('SELECT * FROM report_templates WHERE id=?', [(int)$id]);
        if (!$tpl) { abort(404, 'Template not found'); }
        $config = json_decode($tpl['config'], true) ?: [];

        // Override filters with user-supplied values from POST/GET
        $projectId = (int)($_REQUEST['project_id'] ?? $config['preview']['project_id'] ?? 0);
        $dateFrom  = trim($_REQUEST['date_from']  ?? $config['preview']['date_from']  ?? '');
        $dateTo    = trim($_REQUEST['date_to']    ?? $config['preview']['date_to']    ?? '');
        $typeIds   = array_map('intval', $_REQUEST['type_ids'] ?? $config['preview']['type_ids'] ?? []);

        $testPlanId = (int)($_REQUEST['test_plan_id'] ?? $config['preview']['test_plan_id'] ?? 0);
        $_REQUEST['test_plan_id'] = $testPlanId;
        if ($projectId) self::checkProjectAccess($projectId);
        $data = self::fetchReportData($projectId, $dateFrom, $dateTo, $typeIds);

        // Pass runtime filter info to template for display
        $config['_runtime'] = [
            'project_id' => $projectId,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
        ];

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        View::render('reports/pdf', compact('tpl','config','data'), 'public');
    }

    // ── PDF export of report ──────────────────────────────────────────────────
    public static function exportPdf(string $id): void
    {
        Auth::requireView('reports');
        $tpl = Database::fetchOne('SELECT * FROM report_templates WHERE id=?', [(int)$id]);
        if (!$tpl) { abort(404); }
        $config = json_decode($tpl['config'], true) ?: [];

        // Same config.filters/config.preview fallback as generateFromTemplate() -
        // see the comment there.
        $projectId = (int)($config['filters']['project_id'] ?? $config['preview']['project_id'] ?? 0);
        $dateFrom  = $config['filters']['date_from'] ?? $config['preview']['date_from'] ?? '';
        $dateTo    = $config['filters']['date_to']   ?? $config['preview']['date_to']   ?? '';
        $typeIds   = array_map('intval', $config['filters']['type_ids'] ?? $config['preview']['type_ids'] ?? []);

        if ($projectId) self::checkProjectAccess($projectId);
        $data = self::fetchReportData($projectId, $dateFrom, $dateTo, $typeIds);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        View::render('reports/pdf', compact('tpl', 'config', 'data'), 'public');
    }

    // ── Public, signed report link (used by scheduled e-mail sends so
    // recipients don't need a RoboDoc login just to open the report) ─────────
    public static function publicView(string $token): void
    {
        $payload = self::verifyPublicToken($token);
        if (!$payload) { abort(404, 'Link ungültig oder abgelaufen.'); }

        $tpl = Database::fetchOne('SELECT * FROM report_templates WHERE id=?', [(int)$payload['tpl']]);
        if (!$tpl) { abort(404, 'Template not found'); }
        $config = json_decode($tpl['config'], true) ?: [];

        // No $enforceAccess here - this is a pre-authorized, signed public
        // link (no logged-in user/session to check); the scope was already
        // decided and locked in when buildPublicUrl() created the token.
        $data = self::fetchReportData(
            (int)($payload['project_id'] ?? 0),
            (string)($payload['date_from'] ?? ''),
            (string)($payload['date_to'] ?? ''),
            (array)($payload['type_ids'] ?? []),
            false
        );
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        View::render('reports/pdf', compact('tpl', 'config', 'data'), 'public');
    }

    // Builds a self-verifying signed URL for publicView() — no DB row needed,
    // the filters + expiry are encoded into the token itself and authenticated
    // with APP_SECRET, the same secret Encryption already derives its key from.
    public static function buildPublicUrl(int $templateId, int $projectId, string $dateFrom, string $dateTo, array $typeIds, int $ttlDays = 14): string
    {
        $payload = json_encode([
            'tpl' => $templateId, 'project_id' => $projectId,
            'date_from' => $dateFrom, 'date_to' => $dateTo, 'type_ids' => $typeIds,
            'exp' => time() + $ttlDays * 86400,
        ]);
        $sig = hash_hmac('sha256', $payload, APP_SECRET);
        $token = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . $sig;
        $appUrl = rtrim(appSetting('app_url', ''), '/');
        return $appUrl . BASE_URL . '/reports/public/' . $token;
    }

    private static function verifyPublicToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;
        [$b64, $sig] = $parts;
        $payload = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($payload === false) return null;
        if (!hash_equals(hash_hmac('sha256', $payload, APP_SECRET), $sig)) return null;
        $data = json_decode($payload, true);
        if (!is_array($data) || ($data['exp'] ?? 0) < time()) return null;
        return $data;
    }

    // ── Report schedules (automatischer Versand) ────────────────────────────
    public static function listSchedules(string $id): void
    {
        Auth::requireView('reports');
        $rows = Database::fetchAll('SELECT * FROM report_schedules WHERE template_id=? ORDER BY id DESC', [(int)$id]);
        json(['schedules' => $rows]);
    }

    public static function saveSchedule(string $id): void
    {
        Auth::requireView('reports');
        Auth::verifyCsrf();
        $tplId = (int)$id;
        $tpl = Database::fetchOne('SELECT id FROM report_templates WHERE id=?', [$tplId]);
        if (!$tpl) { json(['error' => 'Template not found'], 404); }

        $name       = trim($_POST['name'] ?? '');
        $recipients = trim($_POST['recipients'] ?? '');
        if (!$name || !$recipients) { json(['error' => 'Name und Empfänger erforderlich'], 400); }

        $emails = array_filter(array_map('trim', explode(',', $recipients)));
        foreach ($emails as $addr) {
            if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                json(['error' => "Ungültige E-Mail-Adresse: $addr"], 400);
            }
        }

        $frequency  = in_array($_POST['frequency'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $_POST['frequency'] : 'weekly';
        $dow        = ($_POST['day_of_week']  ?? '') !== '' ? max(0, min(6, (int)$_POST['day_of_week']))   : null;
        $dom        = ($_POST['day_of_month'] ?? '') !== '' ? max(1, min(28, (int)$_POST['day_of_month'])) : null;
        $time       = preg_match('/^\d{2}:\d{2}$/', $_POST['time_of_day'] ?? '') ? $_POST['time_of_day'] . ':00' : '08:00:00';
        $periodMode = ($_POST['period_mode'] ?? '') === 'all' ? 'all' : 'last_n_days';
        $periodDays = max(1, (int)($_POST['period_days'] ?? 7));
        $projectId  = ((int)($_POST['project_id'] ?? 0)) ?: null;
        // A schedule mails its report to arbitrary addresses unattended, so
        // the project it's scoped to must be one the creator can actually see.
        if ($projectId) self::checkProjectAccess($projectId);
        $typeIds    = array_map('intval', (array)($_POST['type_ids'] ?? []));
        $typeIdsStr = $typeIds ? implode(',', $typeIds) : null;
        $isActive   = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;

        $schedId = (int)($_POST['schedule_id'] ?? 0);
        if ($schedId) {
            Database::execute(
                'UPDATE report_schedules SET name=?,recipients=?,frequency=?,day_of_week=?,day_of_month=?,time_of_day=?,period_mode=?,period_days=?,project_id=?,type_ids=?,is_active=? WHERE id=? AND template_id=?',
                [$name, $recipients, $frequency, $dow, $dom, $time, $periodMode, $periodDays, $projectId, $typeIdsStr, $isActive, $schedId, $tplId]
            );
        } else {
            $schedId = Database::insert(
                'INSERT INTO report_schedules (template_id,name,recipients,frequency,day_of_week,day_of_month,time_of_day,period_mode,period_days,project_id,type_ids,is_active,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$tplId, $name, $recipients, $frequency, $dow, $dom, $time, $periodMode, $periodDays, $projectId, $typeIdsStr, $isActive, Auth::id()]
            );
        }
        json(['ok' => true, 'id' => $schedId]);
    }

    public static function deleteSchedule(string $id): void
    {
        Auth::requireView('reports');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM report_schedules WHERE id=?', [(int)$id]);
        redirect('/reports/builder');
    }

    // ── Cron entry point: check every active schedule, send the ones due now.
    // Called from app/cron/report_schedules.php (registered in the cron runner).
    public static function runScheduledSends(): int
    {
        $schedules = Database::fetchAll('SELECT * FROM report_schedules WHERE is_active=1');
        $sent = 0;
        foreach ($schedules as $s) {
            if (!self::scheduleIsDue($s)) continue;
            self::sendSchedule($s);
            $sent++;
        }
        return $sent;
    }

    // Cron runs every ~15 min (same cadence as jira_sync/zentao_sync), so "due"
    // means: current time is past the configured time-of-day, the day matches
    // for weekly/monthly, and it hasn't already been sent in this period.
    private static function scheduleIsDue(array $s): bool
    {
        $now = new DateTime();
        if ($now->format('H:i') < substr($s['time_of_day'], 0, 5)) return false;
        $last = $s['last_sent_at'] ? new DateTime($s['last_sent_at']) : null;
        return match ($s['frequency']) {
            'daily'   => !$last || $last->format('Y-m-d') !== $now->format('Y-m-d'),
            'weekly'  => (int)$now->format('w') === (int)($s['day_of_week'] ?? -1)
                         && (!$last || $last->format('Y-m-d') !== $now->format('Y-m-d')),
            'monthly' => (int)$now->format('j') === (int)($s['day_of_month'] ?? -1)
                         && (!$last || $last->format('Y-m') !== $now->format('Y-m')),
            default   => false,
        };
    }

    private static function sendSchedule(array $s): void
    {
        $dateFrom = $dateTo = '';
        if ($s['period_mode'] === 'last_n_days') {
            $dateTo   = date('Y-m-d');
            $dateFrom = date('Y-m-d', strtotime('-' . max(1, (int)$s['period_days']) . ' days'));
        }
        $typeIds = $s['type_ids'] ? array_map('intval', explode(',', $s['type_ids'])) : [];
        $url = self::buildPublicUrl((int)$s['template_id'], (int)($s['project_id'] ?? 0), $dateFrom, $dateTo, $typeIds);

        $tpl     = Database::fetchOne('SELECT name FROM report_templates WHERE id=?', [$s['template_id']]);
        $tplName = $tpl['name'] ?? $s['name'];

        $emails = array_filter(array_map('trim', explode(',', $s['recipients'])));
        foreach ($emails as $addr) {
            Mailer::sendScheduledReport($addr, $tplName, $url);
        }
        Database::execute('UPDATE report_schedules SET last_sent_at=NOW() WHERE id=?', [$s['id']]);
    }

    // -- Project access helpers (same pattern as TestPlanController/TestCycleController) --
    private static function checkProjectAccess(int $projectId): void {
        if (!$projectId || Auth::isAdmin()) return;
        $ids = Auth::groupProjectIds();
        if ($ids !== null && !in_array($projectId, $ids, true)) abort(403, 'No access to this project');
    }

    private static function projectClause(string $alias): array {
        $ids = Auth::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ["$alias.project_id IS NULL", []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.project_id IS NULL OR $alias.project_id IN ($ph))", $ids];
    }
}