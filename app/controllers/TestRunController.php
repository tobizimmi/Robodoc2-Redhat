<?php
declare(strict_types=1);

class TestRunController {
    public static function index(): void {
        // Redirect to Test Cycles overview (runs are always part of a cycle)
        Auth::requireView('testing');
        redirect('/test-cycles');
    }

    public static function show(string $id): void {
        Auth::requireView('testing');
        $run = self::findOr404((int)$id);
        $results = Database::fetchAll(
            "SELECT trr.*, tpi.title item_title, tpi.priority item_priority, tpi.expected_result,
                    tpi.test_request_id,
                    treq.summary req_summary, treq.status req_status, treq.jira_issue_key req_jira_key, treq.jira_issue_url req_jira_url,
                    u.name executor
             FROM test_run_results trr
             JOIN test_plan_items tpi ON tpi.id = trr.test_plan_item_id
             LEFT JOIN test_requests treq ON treq.id = tpi.test_request_id
             LEFT JOIN users u ON u.id = trr.executed_by
             WHERE trr.test_run_id = ? ORDER BY tpi.sort_order, tpi.id",
            [(int)$id]
        );
        $stats = [
            'total'   => count($results),
            'passed'  => count(array_filter($results, fn($r) => $r['status'] === 'passed')),
            'failed'  => count(array_filter($results, fn($r) => $r['status'] === 'failed')),
            'pending' => count(array_filter($results, fn($r) => $r['status'] === 'pending')),
        ];
        // Load test entries per result
        $resultIds = array_column($results, 'id');
        $testEntries = [];
        if ($resultIds) {
            $ph = implode(',', array_fill(0, count($resultIds), '?'));
            $rows = Database::fetchAll(
                "SELECT e.id, e.title, e.description, e.entry_date, e.entry_time, e.test_run_result_id,
                        et.name type_name, et.color type_color, u.name creator
                 FROM entries e
                 LEFT JOIN entry_types et ON et.id = e.entry_type_id
                 LEFT JOIN users u ON u.id = e.created_by
                 WHERE e.test_run_result_id IN ($ph) ORDER BY e.entry_date, e.entry_time",
                $resultIds
            );
            foreach ($rows as $row) {
                $testEntries[$row['test_run_result_id']][] = $row;
            }
        }
        // Load attachments for test entries
        $testEntryAttachments = [];
        $allEntryIds = [];
        foreach ($testEntries as $entries) {
            foreach ($entries as $e) { $allEntryIds[] = $e['id']; }
        }
        if ($allEntryIds) {
            $ph2 = implode(',', array_fill(0, count($allEntryIds), '?'));
            $attRows = Database::fetchAll(
                "SELECT id, entry_id, original_name, display_name, mime_type, file_size, comment
                 FROM entry_attachments WHERE entry_id IN ($ph2) ORDER BY created_at",
                $allEntryIds
            );
            foreach ($attRows as $attRow) {
                $testEntryAttachments[$attRow['entry_id']][] = $attRow;
            }
        }
        // Plan items not yet in this run
        $missingItems = Database::fetchAll(
            'SELECT tpi.* FROM test_plan_items tpi
             WHERE tpi.test_plan_id = ?
               AND tpi.id NOT IN (SELECT test_plan_item_id FROM test_run_results WHERE test_run_id = ?)',
            [$run['test_plan_id'], (int)$id]
        );

        $testAreas    = Database::fetchAll('SELECT id, name FROM test_areas ORDER BY name');
        $environments = Database::fetchAll('SELECT id, name FROM test_environments ORDER BY name');
        $entryTypes   = Database::fetchAll('SELECT * FROM entry_types ORDER BY sort_order, name');
        $categories   = Database::fetchAll('SELECT * FROM error_categories ORDER BY sort_order, name');
        $customFields = Database::fetchAll('SELECT * FROM custom_fields ORDER BY sort_order, name');
        // Load inventory items for the project of this test run
        $runProjectId = Database::fetchOne('SELECT project_id FROM test_plans WHERE id=?', [$run['test_plan_id']])['project_id'] ?? null;
        $inventoryMowers = $runProjectId
            ? Database::fetchAll('SELECT id, name, serial_number, firmware_version FROM inventory_items WHERE project_id=? ORDER BY name', [$runProjectId])
            : Database::fetchAll('SELECT id, name, serial_number, firmware_version FROM inventory_items ORDER BY name');
        $activeSession = class_exists('TestSessionController') ? TestSessionController::getActive() : null;
        View::render('test-runs/show', compact('run', 'results', 'stats', 'testEntries', 'testEntryAttachments', 'missingItems', 'testAreas', 'environments', 'entryTypes', 'categories', 'customFields', 'inventoryMowers', 'activeSession') + ['title' => $run['name']]);
    }

    public static function create(): void {
        Auth::requireEdit('testing');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $planId = (int)($_POST['test_plan_id'] ?? 0);
            $name   = trim($_POST['name'] ?? '');
            if (!$planId || !$name) { flash('error', 'Testplan und Name erforderlich.'); redirect('/test-runs/create'); }
            $cycleId = (int)($_POST['test_cycle_id'] ?? 0) ?: null;
            $runId = Database::insert(
                'INSERT INTO test_runs (test_plan_id, test_cycle_id, name, description, environment, status, created_by) VALUES (?,?,?,?,?,?,?)',
                [$planId, $cycleId, $name, trim($_POST['description'] ?? ''), trim($_POST['environment'] ?? ''), 'planned', Auth::id()]
            );
            // Create result rows for all plan items
            $items = Database::fetchAll('SELECT id FROM test_plan_items WHERE test_plan_id = ?', [$planId]);
            foreach ($items as $item) {
                Database::insert(
                    'INSERT INTO test_run_results (test_run_id, test_plan_item_id, status) VALUES (?,?,?)',
                    [$runId, $item['id'], 'pending']
                );
            }
            Database::execute("UPDATE test_runs SET status='active', started_at=NOW() WHERE id=?", [$runId]);
            flash('success', 'Testlauf gestartet.');
            redirect('/test-runs/' . $runId);
        }
        $plans = Database::fetchAll("SELECT tp.id, tp.name, p.name project_name FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id ORDER BY tp.name");
        View::render('test-runs/create', ['plans' => $plans, 'title' => 'Neuer Testlauf']);
    }

    public static function edit(string $id): void {
        Auth::requireEdit('testing');
        $run = self::findOr404((int)$id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute(
                'UPDATE test_runs SET name=?, description=?, environment=?, status=? WHERE id=?',
                [trim($_POST['name']), trim($_POST['description'] ?? ''), trim($_POST['environment'] ?? ''), $_POST['status'] ?? 'planned', (int)$id]
            );
            if ($_POST['status'] === 'completed') {
                Database::execute('UPDATE test_runs SET completed_at=NOW() WHERE id=? AND completed_at IS NULL', [(int)$id]);
            }
            flash('success', 'Testlauf aktualisiert.');
            redirect('/test-runs/' . $id);
        }
        View::render('test-runs/edit', compact('run') + ['title' => 'Testlauf bearbeiten']);
    }

    public static function delete(string $id): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_runs WHERE id = ?', [(int)$id]);
        flash('success', 'Test run deleted.');
        redirect('/test-runs');
    }

    public static function updateResult(string $id, string $rid): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $status = $_POST['status'] ?? 'pending';
        $notes  = trim($_POST['notes'] ?? '');
        Database::execute(
            'UPDATE test_run_results SET status=?, notes=?, executed_by=?, executed_at=NOW() WHERE id=? AND test_run_id=?',
            [$status, $notes, Auth::id(), (int)$rid, (int)$id]
        );
        flash('success', 'Ergebnis gespeichert.');
        redirect('/test-runs/' . $id);
    }

    public static function createEntry(string $id, string $rid): void {
        Auth::require();
        Auth::verifyCsrf();
        $run = self::findOr404((int)$id);

        $result = Database::fetchOne(
            'SELECT trr.*, tpi.title item_title FROM test_run_results trr JOIN test_plan_items tpi ON tpi.id=trr.test_plan_item_id WHERE trr.id=? AND trr.test_run_id=?',
            [(int)$rid, (int)$id]
        );
        if (!$result) abort(404);

        $plan = Database::fetchOne('SELECT project_id FROM test_plans WHERE id=?', [$run['test_plan_id']]);
        $projectId = $plan['project_id'] ?? null;
        if (!$projectId) { flash('error', 'Test plan has no project.'); redirect('/test-runs/' . $id); }

        // Entry type: use posted value or default to "Test Result"
        $entryTypeId = (int)($_POST['entry_type_id'] ?? 0);
        if (!$entryTypeId) {
            $type = Database::fetchOne("SELECT id FROM entry_types WHERE name='Test Result' LIMIT 1");
            $entryTypeId = $type ? (int)$type['id'] : 1;
        }

        $title            = trim($_POST['title'] ?? '') ?: ('Test Result: ' . $result['item_title']);
        $description      = trim($_POST['description'] ?? '');
        $entryDate        = $_POST['entry_date'] ?? date('Y-m-d');
        $entryTime        = $_POST['entry_time'] ?? date('H:i:s');
        $firmwareVersion  = trim($_POST['firmware_version'] ?? '');
        $appVersion       = trim($_POST['app_version'] ?? '');
        $mowerSerial      = trim($_POST['mower_serial'] ?? '');
        $errorCategoryId  = (int)($_POST['error_category_id'] ?? 0) ?: null;
        $environmentId    = (int)($_POST['environment_id'] ?? 0) ?: null;
        $testAreaId       = (int)($_POST['test_area_id'] ?? 0) ?: null;
        $temperature      = ($_POST['temperature'] ?? '') !== '' ? (float)$_POST['temperature'] : null;
        $weatherCondition = trim($_POST['weather_condition'] ?? '') ?: null;

        // Auto-link to active test session if none specified
        $sessionId = (int)($_POST['session_id'] ?? 0) ?: null;
        if (!$sessionId && class_exists('TestSessionController')) {
            $active = TestSessionController::getActive();
            if ($active) $sessionId = (int)$active['id'];
        }

        $entryId = Database::insert(
            'INSERT INTO entries (project_id, entry_type_id, error_category_id, entry_date, entry_time, title, description,
             firmware_version, app_version, mower_serial, environment_id, is_test_entry, test_run_result_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,?)',
            [$projectId, $entryTypeId, $errorCategoryId, $entryDate, $entryTime, $title, $description,
             $firmwareVersion ?: null, $appVersion ?: null, $mowerSerial ?: null, $environmentId,
             (int)$rid, Auth::id()]
        );

        // Save test area / weather / session
        try {
            Database::execute(
                'UPDATE entries SET session_id=?, test_area_id=?, temperature=?, weather_condition=? WHERE id=?',
                [$sessionId, $testAreaId, $temperature, $weatherCondition, $entryId]
            );
        } catch (\Throwable) {}

        // Save custom field values
        $cfValues = $_POST['cf'] ?? [];
        foreach ($cfValues as $fieldId => $value) {
            $fieldId = (int)$fieldId;
            if (!$fieldId) continue;
            Database::execute(
                'INSERT INTO entry_custom_values (entry_id, field_id, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',
                [$entryId, $fieldId, trim($value)]
            );
        }

        // Save mower links
        $mowerIds = array_filter(array_map('intval', (array)($_POST['mower_ids'] ?? [])));
        foreach ($mowerIds as $mid) {
            try {
                Database::execute('INSERT IGNORE INTO entry_mowers (entry_id, mower_id) VALUES (?,?)', [$entryId, $mid]);
            } catch (\Throwable) {}
        }

        // Save uploaded files
        if (!empty($_FILES['files']['name'][0])) {
            EntryController::handleUploads($entryId, $_FILES['files']);
        }

        flash('success', 'Test entry created.');
        redirect('/test-runs/' . $id);
    }

    // ── Tester assignment ────────────────────────────────────────
    public static function assignTester(string $id, string $rid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $userId = (int)($_POST['user_id'] ?? 0) ?: null;
        Database::execute('UPDATE test_run_results SET assigned_tester=? WHERE id=? AND test_run_id=?', [$userId, (int)$rid, (int)$id]);
        $user = $userId ? Database::fetchOne('SELECT name FROM users WHERE id=?', [$userId]) : null;
        echo json_encode(['success' => true, 'name' => $user['name'] ?? '']);
        exit;
    }

    // ── Bug links ────────────────────────────────────────────────
    public static function addBug(string $id, string $rid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $entryId = (int)($_POST['entry_id'] ?? 0) ?: null;
        $jiraKey = trim($_POST['jira_key'] ?? '');
        if (!$entryId && !$jiraKey) { http_response_code(422); echo json_encode(['error' => 'Entry oder Jira Key erforderlich']); exit; }
        // If entry has a Jira key, use it
        if ($entryId && !$jiraKey) {
            $entry = Database::fetchOne('SELECT jira_issue_key FROM entries WHERE id=?', [$entryId]);
            $jiraKey = $entry['jira_issue_key'] ?? '';
        }
        $bugId = Database::insert('INSERT INTO test_run_bugs (test_run_result_id, entry_id, jira_key, created_by) VALUES (?,?,?,?)', [(int)$rid, $entryId, $jiraKey ?: null, Auth::id()]);
        $entry = $entryId ? Database::fetchOne('SELECT id, title, jira_issue_key FROM entries WHERE id=?', [$entryId]) : null;
        echo json_encode(['success' => true, 'id' => $bugId, 'entry' => $entry, 'jira_key' => $jiraKey]);
        exit;
    }

    public static function removeBug(string $id, string $rid, string $bid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_run_bugs WHERE id=? AND test_run_result_id=?', [(int)$bid, (int)$rid]);
        echo json_encode(['success' => true]);
        exit;
    }

    public static function addItems(string $id): void {
        Auth::require();
        Auth::verifyCsrf();
        $run = self::findOr404((int)$id);

        $itemIds = array_filter(array_map('intval', (array)($_POST['item_ids'] ?? [])));
        $added = 0;
        foreach ($itemIds as $itemId) {
            // Verify item belongs to the same plan
            $item = Database::fetchOne(
                'SELECT id FROM test_plan_items WHERE id=? AND test_plan_id=?',
                [$itemId, $run['test_plan_id']]
            );
            if (!$item) continue;
            // Skip if already in run
            $exists = Database::fetchOne(
                'SELECT id FROM test_run_results WHERE test_run_id=? AND test_plan_item_id=?',
                [(int)$id, $itemId]
            );
            if ($exists) continue;
            Database::insert(
                'INSERT INTO test_run_results (test_run_id, test_plan_item_id, status) VALUES (?,?,?)',
                [(int)$id, $itemId, 'pending']
            );
            $added++;
        }
        flash('success', $added ? "$added test case(s) added to run." : 'No new items added.');
        redirect('/test-runs/' . $id);
    }

    private static function findOr404(int $id): array {
        $r = Database::fetchOne(
            "SELECT tr.*, tp.name plan_name, p.name project_name,
                    tc.name cycle_name, tc.id cycle_id_val, tp.xray_key plan_xray_key
             FROM test_runs tr
             LEFT JOIN test_plans tp ON tp.id=tr.test_plan_id
             LEFT JOIN projects p ON p.id=tp.project_id
             LEFT JOIN test_cycles tc ON tc.id=tr.test_cycle_id
             WHERE tr.id=?",
            [$id]
        );
        if (!$r) abort(404, 'Testlauf nicht gefunden');
        return $r;
    }
}
