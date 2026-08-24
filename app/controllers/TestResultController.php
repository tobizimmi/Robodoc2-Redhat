<?php
declare(strict_types=1);

class TestResultController
{
    public static function index(): void
    {
        Auth::requireView('testing');

        $mowerId   = isset($_GET['mower_id']) && is_numeric($_GET['mower_id']) ? (int)$_GET['mower_id'] : null;
        $dateFrom  = $_GET['date_from'] ?? '';
        $dateTo    = $_GET['date_to']   ?? '';
        $runId     = isset($_GET['run_id'])     && is_numeric($_GET['run_id'])     ? (int)$_GET['run_id']     : null;
        $sessionId = isset($_GET['session_id']) && is_numeric($_GET['session_id']) ? (int)$_GET['session_id'] : null;
        $source    = $_GET['source'] ?? ''; // 'run', 'session', or '' = all

        $where  = ['(e.is_test_entry = 1 OR e.session_id IS NOT NULL)'];
        $params = [];

        if (!Auth::isAdmin()) {
            [$ac, $ap] = Auth::entryAccessClause();
            $where[]  = $ac;
            $params   = array_merge($params, $ap);
        }

        if ($source === 'run')     $where[] = 'e.is_test_entry = 1';
        if ($source === 'session') $where[] = 'e.session_id IS NOT NULL';

        if ($dateFrom) { $where[] = 'e.entry_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = 'e.entry_date <= ?'; $params[] = $dateTo; }

        if ($runId) {
            $where[] = 'tr.id = ?';
            $params[] = $runId;
        }
        if ($sessionId) {
            $where[] = 'e.session_id = ?';
            $params[] = $sessionId;
        }

        if ($mowerId) {
            $where[] = 'EXISTS (SELECT 1 FROM entry_mowers em WHERE em.entry_id = e.id AND em.mower_id = ?)';
            $params[] = $mowerId;
        }

        $wStr = implode(' AND ', $where);

        $entries = Database::fetchAll(
            "SELECT e.id, e.title, e.description, e.entry_date, e.entry_time,
                    e.firmware_version, e.mower_serial, e.is_test_entry,
                    e.test_run_result_id, e.session_id, e.temperature, e.weather_condition,
                    et.name type_name, et.color type_color,
                    ec.name cat_name,
                    p.name project_name, p.color project_color,
                    u.name creator,
                    ta.name area_name,
                    env.name environment_name,
                    -- test run context
                    tpi.title item_title, tpi.priority item_priority,
                    tr.id run_id, tr.name run_name,
                    -- session context
                    ts.title session_title,
                    (SELECT COUNT(*) FROM entry_attachments WHERE entry_id = e.id) att_count
             FROM entries e
             LEFT JOIN entry_types et      ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             LEFT JOIN projects p          ON p.id  = e.project_id
             LEFT JOIN users u             ON u.id  = e.created_by
             LEFT JOIN test_areas ta       ON ta.id = e.test_area_id
             LEFT JOIN test_environments env ON env.id = e.environment_id
             LEFT JOIN test_run_results trr ON trr.id = e.test_run_result_id
             LEFT JOIN test_plan_items tpi ON tpi.id = trr.test_plan_item_id
             LEFT JOIN test_runs tr        ON tr.id  = trr.test_run_id
             LEFT JOIN test_sessions ts    ON ts.id  = e.session_id
             WHERE $wStr
             ORDER BY e.entry_date DESC, e.entry_time DESC, e.id DESC",
            $params
        );

        // Attach mowers per entry
        if ($entries) {
            $ids = array_column($entries, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $mowerRows = Database::fetchAll(
                "SELECT em.entry_id, tm.id mower_id, tm.label, tm.serial_number, tm.model
                 FROM entry_mowers em JOIN test_mowers tm ON tm.id = em.mower_id
                 WHERE em.entry_id IN ($ph) ORDER BY tm.label",
                $ids
            );
            $mowersByEntry = [];
            foreach ($mowerRows as $mr) $mowersByEntry[$mr['entry_id']][] = $mr;
            foreach ($entries as &$e) $e['mowers'] = $mowersByEntry[$e['id']] ?? [];
            unset($e);
        }

        $allMowers  = Database::fetchAll('SELECT * FROM test_mowers ORDER BY label');
        $allRuns    = Database::fetchAll('SELECT id, name FROM test_runs ORDER BY created_at DESC LIMIT 50');
        $allSessions = Database::fetchAll('SELECT id, title FROM test_sessions ORDER BY created_at DESC LIMIT 50');

        View::render('test-results/index', compact(
            'entries', 'allMowers', 'allRuns', 'allSessions',
            'mowerId', 'dateFrom', 'dateTo', 'runId', 'sessionId', 'source'
        ) + ['title' => 'Test Results']);
    }
}
