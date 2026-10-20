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
            'new'     => count(array_filter($results, fn($r) => $r['status'] === 'new')),
            'passed'  => count(array_filter($results, fn($r) => $r['status'] === 'passed')),
            'failed'  => count(array_filter($results, fn($r) => $r['status'] === 'failed')),
            'pending' => count(array_filter($results, fn($r) => $r['status'] === 'pending')),
            'skipped' => count(array_filter($results, fn($r) => $r['status'] === 'skipped')),
            'blocked' => count(array_filter($results, fn($r) => $r['status'] === 'blocked')),
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
        // Plan items not yet in this run - includes test cases linked into
        // this plan from elsewhere (the shared library), not just its own.
        $missingItems = Database::fetchAll(
            'SELECT tpi.* FROM test_plan_items tpi
             JOIN test_plan_item_links tpil ON tpil.test_plan_item_id = tpi.id AND tpil.test_plan_id = ?
             WHERE tpi.id NOT IN (SELECT test_plan_item_id FROM test_run_results WHERE test_run_id = ?)',
            [$run['test_plan_id'], (int)$id]
        );

        // Bugs linked/created per result (Bug modal) - was being inserted into
        // test_run_bugs correctly but never queried back here, so the badge in
        // the result row's footer toolbar always rendered empty.
        $bugsByResult = [];
        if ($resultIds) {
            $ph3 = implode(',', array_fill(0, count($resultIds), '?'));
            $bugRows = Database::fetchAll(
                "SELECT trb.*, e.title entry_title
                 FROM test_run_bugs trb
                 LEFT JOIN entries e ON e.id = trb.entry_id
                 WHERE trb.test_run_result_id IN ($ph3)
                 ORDER BY trb.created_at",
                $resultIds
            );
            foreach ($bugRows as $br) {
                $bugsByResult[$br['test_run_result_id']][] = $br;
            }
        }
        // Same gap for tester assignment - assigned_tester was saved but never
        // resolved back to a name because $allUsers was never loaded.
        $allUsers = Database::fetchAll('SELECT id, name FROM users ORDER BY name');

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
        View::render('test-runs/show', compact('run', 'results', 'stats', 'testEntries', 'testEntryAttachments', 'missingItems', 'bugsByResult', 'allUsers', 'testAreas', 'environments', 'entryTypes', 'categories', 'customFields', 'inventoryMowers', 'activeSession') + ['title' => $run['name']]);
    }

    public static function create(): void {
        Auth::requireEdit('testing');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $planId  = (int)($_POST['test_plan_id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $cycleId = (int)($_POST['test_cycle_id'] ?? 0);
            if (!$planId || !$name) { flash('error', 'Test plan and name are required.'); redirect('/test-runs/create'); }
            $plan = Database::fetchOne('SELECT id, project_id FROM test_plans WHERE id=?', [$planId]);
            if (!$plan) abort(404);
            self::checkAccess($plan);
            // A Test Run always belongs to a Test Cycle now - the cycle is the
            // container (e.g. "Sprint 23 Regression"), the run is one scoped
            // pass through some of its test cases.
            if (!$cycleId) { flash('error', 'A Test Cycle is required. Create one on the Test Plan page first.'); redirect('/test-runs/create?plan_id=' . $planId); }
            $cycle = Database::fetchOne('SELECT id FROM test_cycles WHERE id=? AND test_plan_id=?', [$cycleId, $planId]);
            if (!$cycle) { flash('error', 'That Test Cycle does not belong to the selected plan.'); redirect('/test-runs/create?plan_id=' . $planId); }

            // Explicit test-case selection - a run only ever covers the test
            // cases chosen here, not automatically the whole plan, since in
            // practice a run rarely exercises every test case at once.
            $itemIds = array_filter(array_map('intval', (array)($_POST['item_ids'] ?? [])));
            if (!$itemIds) { flash('error', 'Select at least one test case.'); redirect('/test-runs/create?plan_id=' . $planId . '&cycle_id=' . $cycleId); }

            $runId = Database::insert(
                'INSERT INTO test_runs (test_plan_id, test_cycle_id, name, description, environment, status, created_by) VALUES (?,?,?,?,?,?,?)',
                [$planId, $cycleId, $name, trim($_POST['description'] ?? ''), trim($_POST['environment'] ?? ''), 'planned', Auth::id()]
            );
            // Re-verify each selected id actually belongs to this plan (its
            // home, or linked in from elsewhere in the shared library) before
            // creating its result row (defense against a tampered submission).
            $ph = implode(',', array_fill(0, count($itemIds), '?'));
            $validItems = Database::fetchAll(
                "SELECT tpi.id FROM test_plan_items tpi
                 JOIN test_plan_item_links tpil ON tpil.test_plan_item_id = tpi.id AND tpil.test_plan_id = ?
                 WHERE tpi.id IN ($ph)",
                array_merge([$planId], $itemIds)
            );
            foreach ($validItems as $item) {
                Database::insert(
                    'INSERT INTO test_run_results (test_run_id, test_plan_item_id, status) VALUES (?,?,?)',
                    [$runId, $item['id'], 'new']
                );
            }
            Database::execute("UPDATE test_runs SET status='active', started_at=NOW() WHERE id=?", [$runId]);
            flash('success', 'Test run started with ' . count($validItems) . ' test case(s).');
            redirect('/test-runs/' . $runId);
        }
        [$accessSql, $accessParams] = self::projectClause('tp');
        $plans = Database::fetchAll(
            "SELECT tp.id, tp.name, p.name project_name FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id WHERE $accessSql ORDER BY tp.name",
            $accessParams
        );
        View::render('test-runs/create', ['plans' => $plans, 'title' => 'New Test Run']);
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
            flash('success', 'Test run updated.');
            redirect('/test-runs/' . $id);
        }
        View::render('test-runs/edit', compact('run') + ['title' => 'Edit Test Run']);
    }

    public static function delete(string $id): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        self::findOr404((int)$id);
        Database::execute('DELETE FROM test_runs WHERE id = ?', [(int)$id]);
        flash('success', 'Test run deleted.');
        redirect('/test-runs');
    }

    public static function updateResult(string $id, string $rid): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        self::findOr404((int)$id);
        $status = $_POST['status'] ?? 'pending';
        $notes  = trim($_POST['notes'] ?? '');
        $link   = trim($_POST['report_link'] ?? '');
        // Only allow http(s) links - a stored "javascript:" URI would execute
        // in whoever's browser later clicks it.
        if ($link !== '' && !preg_match('#^https?://#i', $link)) $link = 'https://' . $link;

        $sets  = ['status=?', 'notes=?', 'executed_by=?', 'executed_at=NOW()', 'report_link=?'];
        $binds = [$status, $notes, Auth::id(), $link !== '' ? $link : null];

        // Manually created test report upload - optional, additive to the link
        // above. Leaving the file field empty on a later edit keeps whatever
        // was uploaded before (only a newly chosen file replaces it).
        $err = $_FILES['report_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_OK) {
            $origName = $_FILES['report_file']['name'];
            $safe = preg_replace('/[^a-z0-9._-]/', '_', strtolower(basename($origName)));
            if (!is_dir(rtrim(UPLOAD_DIR, '/'))) mkdir(rtrim(UPLOAD_DIR, '/'), 0755, true);
            $dest = UPLOAD_DIR . 'trr' . (int)$rid . '_' . uniqid() . '_' . $safe;
            if (move_uploaded_file($_FILES['report_file']['tmp_name'], $dest)) {
                $sets[]  = 'report_file_path=?';
                $sets[]  = 'report_file_name=?';
                $binds[] = $dest;
                $binds[] = $origName;
            }
        }

        $binds[] = (int)$rid;
        $binds[] = (int)$id;
        Database::execute(
            'UPDATE test_run_results SET ' . implode(',', $sets) . ' WHERE id=? AND test_run_id=?',
            $binds
        );
        flash('success', 'Result saved.');
        redirect('/test-runs/' . $id);
    }

    // -- Download the manually-created test report attached to a result. --
    public static function downloadReport(string $id, string $rid): void {
        Auth::requireView('testing');
        self::findOr404((int)$id);
        $result = Database::fetchOne(
            'SELECT report_file_path, report_file_name FROM test_run_results WHERE id=? AND test_run_id=?',
            [(int)$rid, (int)$id]
        );
        if (!$result || !$result['report_file_path']) abort(404);
        $path = $result['report_file_path'];
        if (!file_exists($path)) abort(404, 'File not found');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($result['report_file_name'] ?: basename($path)) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
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

        // AJAX requests come from the Bug Modal's "Neuen Bug erstellen" tab -
        // those should default to the "Bug" entry type, not "Test Result".
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

        // Entry type: use posted value or default to "Bug"/"Test Result"
        $entryTypeId = (int)($_POST['entry_type_id'] ?? 0);
        if (!$entryTypeId) {
            $defaultTypeName = $isAjax ? 'Bug' : 'Test Result';
            $type = Database::fetchOne('SELECT id FROM entry_types WHERE name=? LIMIT 1', [$defaultTypeName]);
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

        // Bug Modal sends a lowercase short scale (critical/high/medium/low);
        // the rest of the app uses the capitalized entries.priority scale.
        $priorityRaw = trim($_POST['priority'] ?? '');
        $priorityMap = ['critical' => 'Blocker', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
        $priority = $priorityMap[$priorityRaw]
            ?? (in_array($priorityRaw, ['Low', 'Medium', 'High', 'Highest', 'Blocker'], true) ? $priorityRaw : 'Medium');
        $status = array_key_exists($_POST['status'] ?? '', entryStatuses()) ? $_POST['status'] : 'new';

        // Auto-link to active test session if none specified
        $sessionId = (int)($_POST['session_id'] ?? 0) ?: null;
        if (!$sessionId && class_exists('TestSessionController')) {
            $active = TestSessionController::getActive();
            if ($active) $sessionId = (int)$active['id'];
        }

        $entryId = Database::insert(
            'INSERT INTO entries (project_id, entry_type_id, error_category_id, entry_date, entry_time, title, description,
             firmware_version, app_version, mower_serial, environment_id, priority, status, is_test_entry, test_run_result_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)',
            [$projectId, $entryTypeId, $errorCategoryId, $entryDate, $entryTime, $title, $description,
             $firmwareVersion ?: null, $appVersion ?: null, $mowerSerial ?: null, $environmentId, $priority, $status,
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

        // AJAX: return JSON + auto-link as bug
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            $jiraKey = trim($_POST['jira_key'] ?? '');
            try {
                Database::insert(
                    'INSERT IGNORE INTO test_run_bugs (test_run_result_id, entry_id, jira_key, created_by) VALUES (?,?,?,?)',
                    [(int)$rid, $entryId, $jiraKey ?: null, Auth::id()]
                );
            } catch (Throwable) {}
            // Also update result status to failed
            try {
                Database::execute('UPDATE test_run_results SET status=?,executed_by=?,executed_at=NOW() WHERE id=?',
                    ['failed', Auth::id(), (int)$rid]);
            } catch (Throwable) {}
            echo json_encode(['success' => true, 'entry_id' => $entryId]);
            exit;
        }
        flash('success', 'Test entry created.');
        redirect('/test-runs/' . $id);
    }

    // ── PQL (Jira Test Request) link - one per test result, not per run ──
    public static function linkPql(string $id, string $rid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        self::findOr404((int)$id);
        $key = strtoupper(trim($_POST['pql_issue_key'] ?? ''));
        if ($key === '') {
            Database::execute('UPDATE test_run_results SET pql_issue_key=NULL, pql_status=NULL, pql_synced_at=NULL WHERE id=? AND test_run_id=?', [(int)$rid, (int)$id]);
            echo json_encode(['success' => true, 'pql_issue_key' => null]);
            exit;
        }
        if (!preg_match('/^[A-Z][A-Z0-9]+-\d+$/', $key)) {
            echo json_encode(['error' => 'Invalid format - expected e.g. PQL-123.']);
            exit;
        }
        Database::execute('UPDATE test_run_results SET pql_issue_key=?, pql_status=NULL, pql_synced_at=NULL WHERE id=? AND test_run_id=?', [$key, (int)$rid, (int)$id]);
        $result = JiraController::fetchIssueStatus($key);
        if ($result['ok']) {
            Database::execute('UPDATE test_run_results SET pql_status=?, pql_synced_at=NOW() WHERE id=? AND test_run_id=?', [$result['status'], (int)$rid, (int)$id]);
        }
        echo json_encode(['success' => true, 'pql_issue_key' => $key] + $result);
        exit;
    }

    public static function syncPql(string $id, string $rid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        self::findOr404((int)$id);
        $result2 = Database::fetchOne('SELECT pql_issue_key FROM test_run_results WHERE id=? AND test_run_id=?', [(int)$rid, (int)$id]);
        if (!$result2 || !$result2['pql_issue_key']) {
            echo json_encode(['error' => 'No PQL ticket linked.']);
            exit;
        }
        $result = JiraController::fetchIssueStatus($result2['pql_issue_key']);
        if ($result['ok']) {
            Database::execute('UPDATE test_run_results SET pql_status=?, pql_synced_at=NOW() WHERE id=? AND test_run_id=?', [$result['status'], (int)$rid, (int)$id]);
        }
        echo json_encode($result);
        exit;
    }

    // ── Tester assignment ────────────────────────────────────────
    public static function assignTester(string $id, string $rid): void {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        self::findOr404((int)$id);
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
        self::findOr404((int)$id);
        $entryId = (int)($_POST['entry_id'] ?? 0) ?: null;
        $jiraKey = trim($_POST['jira_key'] ?? '');
        if (!$entryId && !$jiraKey) { http_response_code(422); echo json_encode(['error' => 'Entry or Jira key required']); exit; }
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
        self::findOr404((int)$id);
        Database::execute('DELETE FROM test_run_bugs WHERE id=? AND test_run_result_id=?', [(int)$bid, (int)$rid]);
        echo json_encode(['success' => true]);
        exit;
    }

    public static function addItems(string $id): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        $run = self::findOr404((int)$id);

        $itemIds = array_filter(array_map('intval', (array)($_POST['item_ids'] ?? [])));
        $added = 0;
        foreach ($itemIds as $itemId) {
            // Verify item is linked to this run's plan (home or shared in)
            $item = Database::fetchOne(
                'SELECT id FROM test_plan_item_links WHERE test_plan_item_id=? AND test_plan_id=?',
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
                [(int)$id, $itemId, 'new']
            );
            $added++;
        }
        flash('success', $added ? "$added test case(s) added to run." : 'No new items added.');
        redirect('/test-runs/' . $id);
    }

    // -- Remove a single test case from this run (its result row). --
    public static function removeItem(string $id, string $rid): void {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        self::findOr404((int)$id);
        Database::execute('DELETE FROM test_run_results WHERE id=? AND test_run_id=?', [(int)$rid, (int)$id]);
        flash('success', 'Test case removed from run.');
        redirect('/test-runs/' . $id);
    }

    private static function findOr404(int $id): array {
        $r = Database::fetchOne(
            "SELECT tr.*, tp.name plan_name, p.name project_name, tp.project_id,
                    tc.name cycle_name, tc.id cycle_id_val, tp.xray_key plan_xray_key
             FROM test_runs tr
             LEFT JOIN test_plans tp ON tp.id=tr.test_plan_id
             LEFT JOIN projects p ON p.id=tp.project_id
             LEFT JOIN test_cycles tc ON tc.id=tr.test_cycle_id
             WHERE tr.id=?",
            [$id]
        );
        if (!$r) abort(404, 'Test run not found');
        // Centralized here so every caller (show/edit/delete/results/bugs/...)
        // gets the same project-scoping as Test Plans/Test Cycles - this used
        // to be entirely missing, so anyone who could use the Testing module
        // at all could view/edit/delete any run in any project by its id.
        self::checkAccess($r);
        return $r;
    }

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
