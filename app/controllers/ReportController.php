<?php
declare(strict_types=1);

class ReportController
{
    public static function index(): void
    {
        Auth::requireView('reports');

        $projects   = Database::fetchAll("SELECT id, name, color FROM projects WHERE status='active' ORDER BY name");
        $entryTypes = Database::fetchAll('SELECT id, name, color FROM entry_types ORDER BY sort_order, name');
        $testPlans  = Database::fetchAll('SELECT id, name, project_id FROM test_plans ORDER BY name');

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

        // Build entry query
        $onlyRelevant = ($_POST['only_report_relevant'] ?? '1') === '1';
        $where  = $onlyRelevant ? ['e.is_report_relevant = 1'] : ['1=1'];
        $params = [];

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
                WHERE pi.test_plan_id = ?
                ORDER BY pi.sort_order, pi.id
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
        $where  = $projectId ? 'WHERE e.project_id = ?' : 'WHERE 1=1';
        $params = $projectId ? [$projectId] : [];

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
                $ph = $projectId ? 'AND e.project_id=?' : '';
                $p  = array_merge($projectId ? [$projectId] : [], []);
                $counts = Database::fetchAll("
                    SELECT e.firmware_version, COUNT(*) cnt
                    FROM entries e
                    WHERE e.entry_type_id = ? AND e.firmware_version IS NOT NULL AND e.firmware_version != '' $ph
                    GROUP BY e.firmware_version
                ", array_merge([$t['id']], $projectId ? [$projectId] : []));
                foreach ($counts as $c) {
                    $byType[$c['firmware_version']][$t['name']] = (int)$c['cnt'];
                }
            }
        }

        $projects = Database::fetchAll("SELECT id, name FROM projects WHERE status='active' ORDER BY name");
        json(compact('rows', 'byType', 'projects'));
    }
    // ── Report Builder: template list ────────────────────────────────────────
    public static function builder(): void
    {
        Auth::requireView('reports');
        $templates = Database::fetchAll(
            'SELECT id, name, description, config, is_default, created_at FROM report_templates ORDER BY name'
        );
        $projects   = Database::fetchAll("SELECT id, name, color FROM projects WHERE status='active' ORDER BY name");
        $entryTypes = Database::fetchAll('SELECT id, name, color FROM entry_types ORDER BY sort_order, name');
        View::render('reports/builder', compact('templates', 'projects', 'entryTypes'), 'app');
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

    // ── Generate report from template ─────────────────────────────────────────
    public static function generateFromTemplate(string $id): void
    {
        Auth::requireView('reports');
        $tpl = Database::fetchOne('SELECT * FROM report_templates WHERE id=?', [(int)$id]);
        if (!$tpl) { abort(404, 'Template not found'); }
        $config = json_decode($tpl['config'], true) ?: [];

        // Fetch data based on config filters
        $projectId = (int)($config['filters']['project_id'] ?? 0);
        $dateFrom  = $config['filters']['date_from'] ?? '';
        $dateTo    = $config['filters']['date_to'] ?? '';
        $typeIds   = array_map('intval', $config['filters']['type_ids'] ?? []);

        $where = ['e.is_merged=0'];
        $params = [];
        if ($projectId) { $where[] = 'e.project_id=?'; $params[] = $projectId; }
        if ($dateFrom)  { $where[] = 'e.entry_date>=?'; $params[] = $dateFrom; }
        if ($dateTo)    { $where[] = 'e.entry_date<=?'; $params[] = $dateTo; }
        if ($typeIds)   {
            $ph = implode(',', array_fill(0, count($typeIds), '?'));
            $where[] = "e.entry_type_id IN ($ph)";
            $params  = array_merge($params, $typeIds);
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
                   ep.title epic_title,
                   pe.title parent_title,
                   u.name creator,
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

        // Project info for project_header block
        $project = $projectId
            ? Database::fetchOne("SELECT id,name,color,description,status FROM projects WHERE id=?", [$projectId])
            : null;

        // Stats
        $byType = Database::fetchAll("
            SELECT et.name, et.color, COUNT(e.id) cnt
            FROM entries e LEFT JOIN entry_types et ON et.id=e.entry_type_id
            WHERE $wStr GROUP BY e.entry_type_id ORDER BY cnt DESC
        ", $params);

        $byStatus = Database::fetchAll("
            SELECT e.status, COUNT(*) cnt FROM entries e
            WHERE $wStr GROUP BY e.status ORDER BY cnt DESC
        ", $params);

        $byPriority = Database::fetchAll("
            SELECT e.priority, COUNT(*) cnt FROM entries e
            WHERE $wStr GROUP BY e.priority ORDER BY cnt DESC
        ", $params);

        $byFirmware = Database::fetchAll("
            SELECT e.firmware_version, COUNT(*) cnt FROM entries e
            WHERE $wStr AND e.firmware_version IS NOT NULL AND e.firmware_version != ''
            GROUP BY e.firmware_version ORDER BY cnt DESC LIMIT 15
        ", $params);

        $data = compact('entries','project','byType','byStatus','byPriority','byFirmware');
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

        $where = ['e.is_merged=0']; $params = [];
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
                   e.jira_issue_key, e.zentao_bug_id, e.project_status_robot, e.parent_id,
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

        $byType = Database::fetchAll("SELECT et.name, et.color, COUNT(e.id) cnt FROM entries e LEFT JOIN entry_types et ON et.id=e.entry_type_id WHERE $wStr GROUP BY e.entry_type_id ORDER BY cnt DESC", $params);
        $byStatus = Database::fetchAll("SELECT e.status, COUNT(*) cnt FROM entries e WHERE $wStr GROUP BY e.status ORDER BY cnt DESC", $params);
        $byPriority = Database::fetchAll("SELECT e.priority, COUNT(*) cnt FROM entries e WHERE $wStr GROUP BY e.priority ORDER BY cnt DESC", $params);
        $byFirmware = Database::fetchAll("SELECT e.firmware_version, COUNT(*) cnt FROM entries e WHERE $wStr AND e.firmware_version IS NOT NULL AND e.firmware_version!='' GROUP BY e.firmware_version ORDER BY cnt DESC LIMIT 15", $params);

        // Pass runtime filter info to template for display
        $config['_runtime'] = [
            'project_id' => $projectId,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
        ];

        $data = compact('entries','project','byType','byStatus','byPriority','byFirmware');
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

        $projectId = (int)($config['filters']['project_id'] ?? 0);
        $dateFrom  = $config['filters']['date_from'] ?? '';
        $dateTo    = $config['filters']['date_to'] ?? '';
        $typeIds   = array_map('intval', $config['filters']['type_ids'] ?? []);

        $where = ['e.is_merged=0']; $params = [];
        if ($projectId) { $where[] = 'e.project_id=?'; $params[] = $projectId; }
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
                   e.jira_issue_key, e.zentao_bug_id, e.project_status_robot, e.parent_id,
                   et.name type_name, et.color type_color,
                   p.name project_name, p.color project_color, p.description project_desc,
                   ep.title epic_title, pe.title parent_title,
                   u.name creator,
                   (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') FROM entry_tags etg
                    JOIN tags t ON t.id=etg.tag_id WHERE etg.entry_id=e.id) AS tag_names
            FROM entries e
            LEFT JOIN entry_types et ON et.id=e.entry_type_id
            LEFT JOIN projects p ON p.id=e.project_id
            LEFT JOIN users u ON u.id=e.created_by
            LEFT JOIN epics ep ON ep.id=e.epic_id
            LEFT JOIN entries pe ON pe.id=e.parent_id
            WHERE $wStr ORDER BY e.entry_date DESC LIMIT 1000
        ", $params);
        $project = $projectId
            ? Database::fetchOne("SELECT id,name,color,description,status FROM projects WHERE id=?", [$projectId])
            : null;
        $byType = Database::fetchAll("
            SELECT et.name, et.color, COUNT(e.id) cnt FROM entries e
            LEFT JOIN entry_types et ON et.id=e.entry_type_id WHERE $wStr
            GROUP BY e.entry_type_id ORDER BY cnt DESC
        ", $params);
        $byStatus = Database::fetchAll("
            SELECT e.status, COUNT(*) cnt FROM entries e WHERE $wStr GROUP BY e.status ORDER BY cnt DESC
        ", $params);
        $byFirmware = Database::fetchAll("
            SELECT e.firmware_version, COUNT(*) cnt FROM entries e
            WHERE $wStr AND e.firmware_version IS NOT NULL AND e.firmware_version != ''
            GROUP BY e.firmware_version ORDER BY cnt DESC LIMIT 15
        ", $params);
        $data = compact('entries','project','byType','byStatus','byFirmware');
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        View::render('reports/pdf', compact('tpl', 'config', 'data'), 'public');
    }

}