<?php
declare(strict_types=1);

class SynapseController
{
    // Auth helpers

    private static function auth(): array
    {
        $creds    = Database::fetchOne('SELECT jira_email, jira_api_key FROM users WHERE id=?', [Auth::id()]);
        $settings = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $jiraUrl  = rtrim($settings['jira_url'] ?? '', '/');
        $token    = trim($creds['jira_api_key'] ?? '');
        $email    = trim($creds['jira_email'] ?? '');
        $project  = strtoupper(trim($settings['xray_project_key'] ?? 'BRSQ'));
        if (!$jiraUrl || !$token) {
            return ['error' => 'Jira nicht konfiguriert.', 'jiraUrl' => '', 'jiraApi' => '', 'synapseApi' => '', 'authHeader' => '', 'project' => $project];
        }
        $authHeader = $email ? 'Basic ' . base64_encode("$email:$token") : 'Bearer ' . $token;
        return ['error' => null, 'jiraUrl' => $jiraUrl, 'jiraApi' => "$jiraUrl/rest/api/2", 'synapseApi' => "$jiraUrl/rest/synapse/latest/public", 'authHeader' => $authHeader, 'project' => $project];
    }

    private static function apiGet(string $url, string $auth): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: ' . $auth], CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, json_decode($resp ?: '{}', true) ?? []];
    }

    private static function apiPost(string $url, string $auth, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $auth], CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, json_decode($resp ?: '{}', true) ?? []];
    }

    private static function apiPut(string $url, string $auth, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $auth], CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, json_decode($resp ?: '{}', true) ?? []];
    }

    // Index: Sync Dashboard (loads from DB, no live API calls)

    public static function index(): void
    {
        Auth::requireView('synapse');
        $a = self::auth();
        $testPlans   = Database::fetchAll('SELECT tp.*, p.name project_name, COUNT(tpi.id) item_count, SUM(tpi.synapse_key IS NOT NULL) synced_count FROM test_plans tp LEFT JOIN projects p ON p.id=tp.project_id LEFT JOIN test_plan_items tpi ON tpi.test_plan_id=tp.id GROUP BY tp.id ORDER BY tp.created_at DESC LIMIT 50');
        $testRuns      = Database::fetchAll('SELECT tr.*, tp.name plan_name FROM test_runs tr LEFT JOIN test_plans tp ON tp.id=tr.test_plan_id ORDER BY tr.created_at DESC LIMIT 30');
        $testCycles    = Database::fetchAll('SELECT tc.*, tp.name plan_name, tp.xray_key plan_xray_key FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id ORDER BY tc.created_at DESC LIMIT 50');
        $cachedPlans   = Database::fetchAll('SELECT * FROM xray_test_plans ORDER BY created_at DESC');
        $onlyInSynapse = Database::fetchAll('SELECT * FROM xray_test_plans WHERE robodoc_plan_id IS NULL ORDER BY created_at DESC');
        $stats = [
            'plans_total'     => count($testPlans),
            'plans_synced'    => count(array_filter($testPlans, fn($p) => !empty($p['xray_key']))),
            'cycles_imported' => count(array_filter($testCycles, fn($c) => !empty($c['synapse_cycle_id']))),
            'only_in_synapse' => count($onlyInSynapse),
        ];
        View::render('synapse/index', ['testPlans' => $testPlans, 'testRuns' => $testRuns, 'testCycles' => $testCycles, 'cachedPlans' => $cachedPlans, 'onlyInSynapse' => $onlyInSynapse, 'stats' => $stats, 'jiraUrl' => $a['jiraUrl'], 'project' => $a['project'], 'error' => $a['error'], 'title' => 'SynapseRT Sync']);
    }

    // Full import: fetch all plans from SynapseRT into RoboDoc

    public static function importAll(): void
    {
        Auth::requireEdit('synapse');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $a = self::auth();
        if ($a['error']) { http_response_code(503); echo json_encode(['error' => $a['error']]); exit; }
        ['jiraApi' => $jiraApi, 'synapseApi' => $synapseApi, 'authHeader' => $auth, 'project' => $project] = $a;

        $projectId = (int)($_POST['project_id'] ?? 0);
        $log = []; $imported = 0; $updated = 0;

        $jql = 'project = ' . $project . ' AND issuetype = "Test Plan" ORDER BY created DESC';
        [$code, $data] = self::apiGet($jiraApi . '/search?' . http_build_query(['jql' => $jql, 'maxResults' => 50, 'fields' => 'summary,status,description']), $auth);
        if ($code !== 200) { echo json_encode(['error' => 'Jira API Fehler (HTTP ' . $code . ')']); exit; }

        foreach ($data['issues'] ?? [] as $issue) {
            $key  = $issue['key'];
            $name = $issue['fields']['summary'] ?? $key;
            $desc = is_string($issue['fields']['description'] ?? null) ? ($issue['fields']['description'] ?? '') : '';
            Database::execute('INSERT INTO xray_test_plans (jira_key,jira_id,summary,status,synced_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE summary=VALUES(summary),status=VALUES(status),synced_at=NOW()', [$key, $issue['id'], $name, $issue['fields']['status']['name'] ?? '']);
            $existing = Database::fetchOne('SELECT id FROM test_plans WHERE xray_key=?', [$key]);
            if (!$existing && $projectId) {
                $planId = Database::insert('INSERT INTO test_plans (project_id, name, description, xray_key, xray_synced_at) VALUES (?,?,?,?,NOW())', [$projectId, $name, $desc, $key]);
                Database::execute('UPDATE xray_test_plans SET robodoc_plan_id=? WHERE jira_key=?', [$planId, $key]);
                $log[] = 'Plan erstellt: ' . $name . ' (' . $key . ')';
                $imported++;
                $existing = ['id' => $planId];
            } elseif ($existing) {
                Database::execute('UPDATE test_plans SET xray_synced_at=NOW() WHERE id=?', [$existing['id']]);
                $updated++;
            }
            if (!$existing) continue;
            $planId = $existing['id'];
            [$mCode, $members] = self::apiGet($synapseApi . '/testPlan/' . $key . '/members', $auth);
            if ($mCode === 200 && is_array($members)) {
                $order = (int)(Database::fetchOne('SELECT COUNT(*) c FROM test_plan_items WHERE test_plan_id=?', [$planId])['c'] ?? 0);
                foreach ($members as $m) {
                    $tcKey  = $m['testCaseKey'] ?? '';
                    $tcName = $m['testCaseSummary'] ?? $m['testCaseName'] ?? $m['summary'] ?? '';
                    if (!$tcName) continue;
                    if ($tcKey && Database::fetchOne('SELECT id FROM test_plan_items WHERE test_plan_id=? AND synapse_key=?', [$planId, $tcKey])) continue;
                    if (!$tcKey && Database::fetchOne('SELECT id FROM test_plan_items WHERE test_plan_id=? AND title=?', [$planId, $tcName])) continue;
                    $newItemId = Database::insert('INSERT INTO test_plan_items (test_plan_id,title,description,expected_result,priority,status,sort_order,synapse_key,synapse_synced_at) VALUES (?,?,?,?,?,?,?,?,NOW())', [$planId, $tcName, '', '', 'medium', 'pending', ++$order, $tcKey ?: null]);
                    $log[] = 'TC importiert: ' . $tcName;
                    if ($tcKey && $newItemId) { $sc3 = self::importStepsForItem($tcKey, $newItemId, $synapseApi, $auth); if ($sc3 > 0) $log[] = 'Steps: ' . $tcKey . ' (' . $sc3 . ')'; }
                }
            }
            // Import cycles and test runs for this plan
            $cycleLog = self::importCyclesForPlan($key, $planId, $synapseApi, $auth);
            $log = array_merge($log, $cycleLog);
        }
        echo json_encode(['success' => true, 'imported' => $imported, 'updated' => $updated, 'log' => $log]);
        exit;
    }

    // Import cycles and their test runs from SynapseRT into RoboDoc
    // Called after plan+TC import to fill in test_cycles and test_runs
    private static function importCyclesForPlan(string $planKey, int $planId, string $synapseApi, string $auth): array
    {
        $log = [];
        [$cCode, $cycles] = self::apiGet($synapseApi . '/testPlan/' . $planKey . '/cycles', $auth);
        if ($cCode !== 200 || !is_array($cycles)) return $log;

        foreach ($cycles as $c) {
            $cycleId   = (string)($c['ID'] ?? $c['id'] ?? '');
            $cycleName = $c['cycleName'] ?? $c['name'] ?? 'Cycle ' . $cycleId;
            $status    = strtolower($c['status'] ?? 'planned');
            $rdStatus  = in_array($status, ['active','completed','aborted','planned']) ? $status : 'planned';
            $startDate = isset($c['cycleStartedDate']) ? substr($c['cycleStartedDate'], 0, 19) : null;
            $env       = $c['environment'] ?? '';
            $build     = $c['build'] ?? '';
            if (!$cycleId) continue;

            // Check if cycle already exists in RoboDoc
            $existingCycle = Database::fetchOne('SELECT id FROM test_cycles WHERE test_plan_id=? AND synapse_cycle_id=?', [$planId, $cycleId]);
            if (!$existingCycle) {
                $rdCycleId = Database::insert(
                    'INSERT INTO test_cycles (test_plan_id, name, environment, build, status, synapse_plan_key, synapse_cycle_id, synapse_synced_at, started_at, created_by) VALUES (?,?,?,?,?,?,?,NOW(),?,1)',
                    [$planId, $cycleName, $env, $build, $rdStatus, $planKey, $cycleId, $startDate]
                );
                $log[] = 'Cycle importiert: ' . $cycleName . ' (ID: ' . $cycleId . ')';
            } else {
                $rdCycleId = $existingCycle['id'];
                Database::execute('UPDATE test_cycles SET name=?,status=?,synapse_synced_at=NOW() WHERE id=?', [$cycleName, $rdStatus, $rdCycleId]);
            }

            // Import test runs for this cycle
            [$rCode, $runs] = self::apiGet($synapseApi . '/testPlan/' . $planKey . '/cycle/' . $cycleId . '/testRuns', $auth);
            if ($rCode !== 200 || !is_array($runs)) continue;

            // Check if a test run already exists for this cycle
            $existingRun = Database::fetchOne('SELECT id FROM test_runs WHERE test_cycle_id=? AND test_plan_id=?', [$rdCycleId, $planId]);
            if (!$existingRun) {
                $runId = Database::insert(
                    'INSERT INTO test_runs (test_plan_id, test_cycle_id, name, status, synapse_plan_key, synapse_cycle_id, synapse_synced_at, created_by) VALUES (?,?,?,?,?,?,NOW(),1)',
                    [$planId, $rdCycleId, $cycleName, $rdStatus, $planKey, $cycleId]
                );
                $log[] = 'Test Run erstellt: ' . $cycleName;
            } else {
                $runId = $existingRun['id'];
                Database::execute('UPDATE test_runs SET synapse_synced_at=NOW() WHERE id=?', [$runId]);
            }

            // Import individual test results
            foreach ($runs as $run) {
                $tcKey    = $run['testCaseKey'] ?? '';
                $tcName   = $run['summary'] ?? $run['testCaseName'] ?? '';
                $xStatus  = $run['status'] ?? 'Not Executed';
                $rdStatus2 = match(strtolower($xStatus)) {
                    'passed'           => 'passed',
                    'failed'           => 'failed',
                    'skipped','blocked' => 'skipped',
                    default            => 'pending',
                };
                // Find matching plan item
                $item = $tcKey
                    ? Database::fetchOne('SELECT id FROM test_plan_items WHERE test_plan_id=? AND synapse_key=?', [$planId, $tcKey])
                    : Database::fetchOne('SELECT id FROM test_plan_items WHERE test_plan_id=? AND title=?', [$planId, $tcName]);
                if (!$item) continue;
                // Upsert test run result
                $existingResult = Database::fetchOne('SELECT id FROM test_run_results WHERE test_run_id=? AND test_plan_item_id=?', [$runId, $item['id']]);
                if (!$existingResult) {
                    Database::insert('INSERT INTO test_run_results (test_run_id, test_plan_item_id, status, synapse_status, synapse_synced_at) VALUES (?,?,?,?,NOW())', [$runId, $item['id'], $rdStatus2, $xStatus]);
                } else {
                    Database::execute('UPDATE test_run_results SET status=?,synapse_status=?,synapse_synced_at=NOW() WHERE id=?', [$rdStatus2, $xStatus, $existingResult['id']]);
                }
            }
        }
        return $log;
    }

    // Import test steps from SynapseRT into test_case_steps table
    private static function importStepsForItem(string $tcKey, int $itemId, string $synapseApi, string $auth): int
    {
        [$code, $steps] = self::apiGet($synapseApi . '/testCase/' . $tcKey . '/steps', $auth);
        if ($code !== 200 || !is_array($steps) || empty($steps)) return 0;
        Database::execute('DELETE FROM test_case_steps WHERE test_plan_item_id=?', [$itemId]);
        $count = 0;
        foreach ($steps as $i => $step) {
            $num      = (int)($step['index'] ?? $step['stepNumber'] ?? $step['step'] ?? ($i + 1));
            $action   = $step['action'] ?? $step['description'] ?? $step['step'] ?? '';
            $testData = $step['data'] ?? $step['testData'] ?? '';
            $expected = $step['expectedResult'] ?? $step['expected'] ?? '';
            $stepId   = (string)($step['id'] ?? $step['ID'] ?? '');
            if (!$action && !$expected) continue;
            Database::insert(
                'INSERT INTO test_case_steps (test_plan_item_id, step_number, step_action, test_data, expected_result, synapse_step_id, synapse_synced_at) VALUES (?,?,?,?,?,?,NOW())',
                [$itemId, $num, $action, $testData, $expected, $stepId ?: null]
            );
            $count++;
        }
        return $count;
    }

    // Sync single plan bidirectional
    // Sync single plan bidirectional
    // Sync single plan bidirectional
    // Sync single plan bidirectional

    public static function syncPlan(): void
    {
        Auth::requireEdit('synapse');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $a = self::auth();
        if ($a['error']) { http_response_code(503); echo json_encode(['error' => $a['error']]); exit; }
        ['jiraApi' => $jiraApi, 'synapseApi' => $synapseApi, 'authHeader' => $auth, 'project' => $project] = $a;

        $planId    = (int)($_POST['plan_id'] ?? 0);
        $direction = $_POST['direction'] ?? 'both';
        $log = [];

        $plan = Database::fetchOne('SELECT * FROM test_plans WHERE id=?', [$planId]);
        if (!$plan) { http_response_code(404); echo json_encode(['error' => 'Plan nicht gefunden']); exit; }
        $synapseKey = $plan['xray_key'] ?? '';
        if (!$synapseKey) { echo json_encode(['error' => 'Kein SynapseRT Key verknuepft.']); exit; }

        // PUSH: RoboDoc -> SynapseRT
        // For each test plan item: create TC in Jira if missing, then link to plan via issueLink "Tests"
        if (in_array($direction, ['push', 'both'])) {
            $items = Database::fetchAll('SELECT * FROM test_plan_items WHERE test_plan_id=?', [$planId]);
            foreach ($items as $item) {
                $title = $item['title'];
                if ($item['synapse_key']) {
                    // Update existing TC title in Jira (same title in both systems)
                    self::apiPut($jiraApi . '/issue/' . $item['synapse_key'], $auth, ['fields' => ['summary' => $title]]);
                    // Re-link to plan (idempotent)
                    self::apiPost($jiraApi . '/issueLink', $auth, ['type' => ['name' => 'Tests'], 'inwardIssue' => ['key' => $synapseKey], 'outwardIssue' => ['key' => $item['synapse_key']]]);
                    Database::execute('UPDATE test_plan_items SET synapse_synced_at=NOW() WHERE id=?', [$item['id']]);
                    $log[] = 'TC aktualisiert: ' . $item['synapse_key'] . ' - ' . $title;
                } else {
                    // Create new Jira Test Case with same title as in RoboDoc
                    $createResult = self::apiPost($jiraApi . '/issue', $auth, [
                        'fields' => [
                            'project'   => ['key' => $project],
                            'summary'   => $title,
                            'issuetype' => ['name' => 'Test Case'],
                        ],
                    ]);
                    $createCode = $createResult[0];
                    $createData = $createResult[1];
                    if (in_array($createCode, [200, 201]) && !empty($createData['key'])) {
                        $newKey = $createData['key'];
                        // Save Jira key back to RoboDoc item
                        Database::execute('UPDATE test_plan_items SET synapse_key=?,synapse_synced_at=NOW() WHERE id=?', [$newKey, $item['id']]);
                        // Link TC to Test Plan in Jira
                        $linkResult = self::apiPost($jiraApi . '/issueLink', $auth, [
                            'type'         => ['name' => 'Tests'],
                            'inwardIssue'  => ['key' => $synapseKey],
                            'outwardIssue' => ['key' => $newKey],
                        ]);
                        $linkCode = $linkResult[0];
                        if ($linkCode === 201) {
                            $log[] = 'TC erstellt und in Jira verknuepft: ' . $newKey . ' - ' . $title . ' (SynapseRT zeigt TC nach manuellem Refresh)';
                        } else {
                            $log[] = 'TC erstellt: ' . $newKey . ' - ' . $title . ' (Jira-Link HTTP ' . $linkCode . ')';
                        }
                    } else {
                        $errDetail = json_encode($createData['errors'] ?? $createData['errorMessages'] ?? []);
                        $log[] = 'FEHLER TC (HTTP ' . $createCode . '): ' . $title . ' - ' . $errDetail;
                    }
                }
            }
            Database::execute('UPDATE test_plans SET xray_synced_at=NOW() WHERE id=?', [$planId]);
        }

        // PULL: SynapseRT -> RoboDoc
        if (in_array($direction, ['pull', 'both'])) {
            [$ic, $iData] = self::apiGet($jiraApi . '/issue/' . $synapseKey . '?fields=summary,description', $auth);
            if ($ic === 200) {
                $xName = $iData['fields']['summary'] ?? '';
                if ($xName && $xName !== $plan['name']) {
                    Database::execute('UPDATE test_plans SET name=?,xray_synced_at=NOW() WHERE id=?', [$xName, $planId]);
                    $log[] = 'Plan-Name gezogen: ' . $xName;
                }
            }
            [$mc, $members] = self::apiGet($synapseApi . '/testPlan/' . $synapseKey . '/members', $auth);
            if ($mc === 200 && is_array($members)) {
                $order = (int)(Database::fetchOne('SELECT MAX(sort_order) m FROM test_plan_items WHERE test_plan_id=?', [$planId])['m'] ?? 0);
                foreach ($members as $m) {
                    $tcKey  = $m['testCaseKey'] ?? '';
                    $tcName = $m['testCaseSummary'] ?? $m['testCaseName'] ?? $m['summary'] ?? '';
                    if (!$tcName) continue;
                    $exists = $tcKey
                        ? Database::fetchOne('SELECT id, synapse_key FROM test_plan_items WHERE test_plan_id=? AND synapse_key=?', [$planId, $tcKey])
                        : Database::fetchOne('SELECT id, synapse_key FROM test_plan_items WHERE test_plan_id=? AND title=?', [$planId, $tcName]);
                    $rId = null;
                    if (!$exists) {
                        $rId = Database::insert('INSERT INTO test_plan_items (test_plan_id,title,priority,status,sort_order,synapse_key,synapse_synced_at) VALUES (?,?,?,?,?,?,NOW())', [$planId, $tcName, 'medium', 'pending', ++$order, $tcKey ?: null]);
                        $log[] = 'TC importiert: ' . $tcName;
                    } elseif ($tcKey && !$exists['synapse_key']) {
                        Database::execute('UPDATE test_plan_items SET synapse_key=?,synapse_synced_at=NOW() WHERE id=?', [$tcKey, $exists['id']]);
                        $rId = $exists['id'];
                        $log[] = 'TC verknuepft: ' . $tcKey . ' - ' . $tcName;
                    } else { $rId = $exists['id']; }
                    if ($tcKey && $rId) { $sc4 = self::importStepsForItem($tcKey, $rId, $synapseApi, $auth); if ($sc4 > 0) $log[] = 'Steps: ' . $tcKey . ' (' . $sc4 . ')'; }
                }
                Database::execute('UPDATE test_plans SET xray_synced_at=NOW() WHERE id=?', [$planId]);
            }
            // Import cycles and test runs
            $cycleLog = self::importCyclesForPlan($synapseKey, $planId, $synapseApi, $auth);
            $log = array_merge($log, $cycleLog);
        }

        echo json_encode(['success' => true, 'log' => $log]);
        exit;
    }

    // Sync single run bidirectional (uses test_cycles table)

    public static function syncRun(): void
    {
        Auth::requireEdit('synapse');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $a = self::auth();
        if ($a['error']) { http_response_code(503); echo json_encode(['error' => $a['error']]); exit; }
        $jiraApi    = $a['jiraApi'];
        $synapseApi = $a['synapseApi'];
        $auth       = $a['authHeader'];

        $runId     = (int)($_POST['run_id'] ?? 0);
        $direction = $_POST['direction'] ?? 'both';
        $log = [];

        $run = Database::fetchOne(
            'SELECT tr.*, tp.xray_key plan_xray_key FROM test_runs tr LEFT JOIN test_plans tp ON tp.id=tr.test_plan_id WHERE tr.id=?',
            [$runId]
        );
        if (!$run) { http_response_code(404); echo json_encode(['error' => 'Run nicht gefunden']); exit; }

        // Get plan key: from cycle, from run, or from plan
        $planKey = '';
        $cycleId = '';
        if ($run['test_cycle_id']) {
            $cycle = Database::fetchOne('SELECT * FROM test_cycles WHERE id=?', [$run['test_cycle_id']]);
            if ($cycle) {
                $planKey = $cycle['synapse_plan_key'] ?: ($run['plan_xray_key'] ?? '');
                $cycleId = $cycle['synapse_cycle_id'] ?? '';
            }
        }
        if (!$planKey) $planKey = $run['synapse_plan_key'] ?: ($run['plan_xray_key'] ?? '');
        if (!$cycleId) $cycleId = $run['synapse_cycle_id'] ?? '';
        if (!$planKey) { echo json_encode(['error' => 'Kein SynapseRT Plan verknuepft.']); exit; }

        // Create cycle in SynapseRT if not yet linked
        if (!$cycleId) {
            $cycleName = '';
            if ($run['test_cycle_id']) {
                $cyc = Database::fetchOne('SELECT name, environment, build FROM test_cycles WHERE id=?', [$run['test_cycle_id']]);
                $cycleName = $cyc['name'] ?? $run['name'];
            } else {
                $cycleName = $run['name'];
            }
            $createResult = self::apiPost($synapseApi . '/testPlan/' . $planKey . '/cycles', $auth, ['name' => $cycleName, 'preloadRuns' => 'yes']);
            $cc = $createResult[0];
            $cd = $createResult[1];
            if (in_array($cc, [200, 201]) && ($cd['ID'] ?? $cd['id'] ?? null)) {
                $cycleId = (string)($cd['ID'] ?? $cd['id']);
                // Save cycle ID to test_cycles or test_runs
                if ($run['test_cycle_id']) {
                    Database::execute('UPDATE test_cycles SET synapse_cycle_id=?,synapse_plan_key=?,synapse_synced_at=NOW() WHERE id=?', [$cycleId, $planKey, $run['test_cycle_id']]);
                }
                Database::execute('UPDATE test_runs SET synapse_plan_key=?,synapse_cycle_id=?,synapse_synced_at=NOW() WHERE id=?', [$planKey, $cycleId, $runId]);
                $log[] = 'SynapseRT Cycle erstellt: ' . $cycleName . ' (ID: ' . $cycleId . ')';
            } else {
                echo json_encode(['error' => 'Cycle konnte nicht erstellt werden (HTTP ' . $cc . ')']);
                exit;
            }
        }

        if (in_array($direction, ['push', 'both'])) {
            $results = Database::fetchAll(
                'SELECT trr.*, tpi.title, tpi.synapse_key FROM test_run_results trr JOIN test_plan_items tpi ON tpi.id=trr.test_plan_item_id WHERE trr.test_run_id=?',
                [$runId]
            );
            foreach ($results as $r) {
                if (!$r['synapse_key']) continue;
                $xStatus = match(strtolower($r['status'] ?? '')) {
                    'passed'  => 'Passed',
                    'failed'  => 'Failed',
                    'skipped' => 'Skipped',
                    default   => 'Not Executed',
                };
                self::apiPut($synapseApi . '/testPlan/' . $planKey . '/cycle/' . $cycleId . '/run/' . $r['synapse_key'] . '/status', $auth, ['status' => $xStatus]);
                Database::execute('UPDATE test_run_results SET synapse_status=?,synapse_synced_at=NOW() WHERE id=?', [$xStatus, $r['id']]);
                $log[] = $r['synapse_key'] . ' = ' . $xStatus;
            }
            Database::execute('UPDATE test_runs SET synapse_synced_at=NOW() WHERE id=?', [$runId]);
        }

        if (in_array($direction, ['pull', 'both'])) {
            $pullResult = self::apiGet($synapseApi . '/testPlan/' . $planKey . '/cycle/' . $cycleId . '/testRuns', $auth);
            if ($pullResult[0] === 200 && is_array($pullResult[1])) {
                foreach ($pullResult[1] as $xRun) {
                    $xSummary = $xRun['summary'] ?? '';
                    $rdStatus = match(strtolower($xRun['status'] ?? '')) {
                        'passed'           => 'passed',
                        'failed'           => 'failed',
                        'skipped','blocked' => 'skipped',
                        default            => 'pending',
                    };
                    if (!$xSummary) continue;
                    $item = Database::fetchOne('SELECT id FROM test_plan_items WHERE test_plan_id=? AND title=?', [$run['test_plan_id'], $xSummary]);
                    if (!$item) continue;
                    $result = Database::fetchOne('SELECT id FROM test_run_results WHERE test_run_id=? AND test_plan_item_id=?', [$runId, $item['id']]);
                    if ($result) {
                        Database::execute('UPDATE test_run_results SET status=?,synapse_status=?,synapse_synced_at=NOW() WHERE id=?', [$rdStatus, $xRun['status'] ?? '', $result['id']]);
                        $log[] = $xSummary . ' = ' . $rdStatus;
                    }
                }
                Database::execute('UPDATE test_runs SET synapse_synced_at=NOW() WHERE id=?', [$runId]);
            }
        }

        echo json_encode(['success' => true, 'log' => $log, 'planKey' => $planKey, 'cycleId' => $cycleId]);
        exit;
    }

    // Link RoboDoc plan to SynapseRT key

    public static function linkPlan(): void
    {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $planId     = (int)($_POST['plan_id'] ?? 0);
        $synapseKey = strtoupper(trim($_POST['synapse_key'] ?? ''));
        if (!$planId || !$synapseKey) { http_response_code(422); echo json_encode(['error' => 'Plan ID und SynapseRT Key erforderlich']); exit; }
        Database::execute('UPDATE test_plans SET xray_key=?,xray_synced_at=NOW() WHERE id=?', [$synapseKey, $planId]);
        Database::execute('UPDATE xray_test_plans SET robodoc_plan_id=? WHERE jira_key=?', [$planId, $synapseKey]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Create Test Plan in SynapseRT from RoboDoc plan

    public static function createTestPlan(): void
    {
        Auth::requireEdit('synapse');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $a = self::auth();
        if ($a['error']) { http_response_code(503); echo json_encode(['error' => $a['error']]); exit; }
        ['jiraApi' => $jiraApi, 'authHeader' => $auth, 'project' => $project, 'jiraUrl' => $jiraUrl] = $a;

        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan   = Database::fetchOne('SELECT * FROM test_plans WHERE id=?', [$planId]);
        if (!$plan) { http_response_code(404); echo json_encode(['error' => 'Plan nicht gefunden']); exit; }
        if ($plan['xray_key']) { echo json_encode(['success' => true, 'key' => $plan['xray_key'], 'already' => true]); exit; }

        $createResult = self::apiPost($jiraApi . '/issue', $auth, [
            'fields' => [
                'project'   => ['key' => $project],
                'summary'   => $plan['name'],
                'issuetype' => ['name' => 'Test Plan'],
            ],
        ]);
        $code = $createResult[0];
        $data = $createResult[1];
        if (!in_array($code, [200, 201]) || empty($data['key'])) {
            http_response_code(500);
            echo json_encode(['error' => $data['errors'] ?? $data['errorMessages'] ?? 'Jira Fehler (HTTP ' . $code . ')']);
            exit;
        }
        $key = $data['key'];
        Database::execute('UPDATE test_plans SET xray_key=?,xray_synced_at=NOW() WHERE id=?', [$key, $planId]);
        Database::execute('INSERT INTO xray_test_plans (jira_key,jira_id,summary,status,robodoc_plan_id,synced_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE robodoc_plan_id=VALUES(robodoc_plan_id),synced_at=NOW()', [$key, $data['id'], $plan['name'], 'Open', $planId]);
        echo json_encode(['success' => true, 'key' => $key, 'url' => $jiraUrl . '/browse/' . $key]);
        exit;
    }

    // Refresh cache: fetch all plans from SynapseRT

    public static function refreshCache(): void
    {
        Auth::requireEdit('synapse');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $a = self::auth();
        if ($a['error']) { http_response_code(503); echo json_encode(['error' => $a['error']]); exit; }
        ['jiraApi' => $jiraApi, 'authHeader' => $auth, 'project' => $project] = $a;

        $jql = 'project = ' . $project . ' AND issuetype = "Test Plan" ORDER BY created DESC';
        $searchResult = self::apiGet($jiraApi . '/search?' . http_build_query(['jql' => $jql, 'maxResults' => 100, 'fields' => 'summary,status,description']), $auth);
        $code = $searchResult[0];
        $data = $searchResult[1];
        if ($code !== 200) { echo json_encode(['error' => 'API Fehler (HTTP ' . $code . ')']); exit; }

        $count = 0;
        foreach ($data['issues'] ?? [] as $issue) {
            Database::execute('INSERT INTO xray_test_plans (jira_key,jira_id,summary,status,synced_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE summary=VALUES(summary),status=VALUES(status),synced_at=NOW()', [$issue['key'], $issue['id'], $issue['fields']['summary'] ?? '', $issue['fields']['status']['name'] ?? '']);
            $count++;
        }
        $allCached = Database::fetchAll('SELECT jira_key FROM xray_test_plans WHERE robodoc_plan_id IS NULL');
        foreach ($allCached as $cp) {
            $rPlan = Database::fetchOne('SELECT id FROM test_plans WHERE xray_key=?', [$cp['jira_key']]);
            if ($rPlan) Database::execute('UPDATE xray_test_plans SET robodoc_plan_id=? WHERE jira_key=?', [$rPlan['id'], $cp['jira_key']]);
        }
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    // List all SynapseRT plans from cache

    public static function listAll(): void
    {
        Auth::requireView('testing');
        $a = self::auth();
        $allPlans = Database::fetchAll('SELECT x.*, tp.id robodoc_id, tp.name robodoc_name, COUNT(tpi.id) robodoc_items, SUM(tpi.synapse_key IS NOT NULL) linked_items FROM xray_test_plans x LEFT JOIN test_plans tp ON tp.id=x.robodoc_plan_id LEFT JOIN test_plan_items tpi ON tpi.test_plan_id=tp.id GROUP BY x.id ORDER BY x.created_at DESC');
        View::render('synapse/list_all', ['allPlans' => $allPlans, 'jiraUrl' => $a['jiraUrl'], 'project' => $a['project'], 'error' => $a['error'], 'title' => 'Alle SynapseRT Test Plaene']);
    }

    // Import single SynapseRT plan into RoboDoc

    public static function importSinglePlan(): void
    {
        Auth::requireEdit('synapse');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $a = self::auth();
        if ($a['error']) { http_response_code(503); echo json_encode(['error' => $a['error']]); exit; }
        ['jiraApi' => $jiraApi, 'synapseApi' => $synapseApi, 'authHeader' => $auth] = $a;

        $jiraKey   = strtoupper(trim($_POST['jira_key'] ?? ''));
        $projectId = (int)($_POST['project_id'] ?? 0);
        if (!$jiraKey || !$projectId) { http_response_code(422); echo json_encode(['error' => 'Key und Projekt erforderlich']); exit; }

        $existing = Database::fetchOne('SELECT id, name FROM test_plans WHERE xray_key=?', [$jiraKey]);
        if ($existing) {
            echo json_encode(['success' => true, 'already' => true, 'plan_id' => $existing['id'], 'plan_name' => $existing['name'], 'redirect' => url('test-plans/' . $existing['id'])]);
            exit;
        }

        $issueResult = self::apiGet($jiraApi . '/issue/' . $jiraKey . '?fields=summary,description', $auth);
        $code  = $issueResult[0];
        $issue = $issueResult[1];
        if ($code !== 200) { http_response_code(500); echo json_encode(['error' => 'Plan ' . $jiraKey . ' nicht gefunden (HTTP ' . $code . ')']); exit; }

        $name = $issue['fields']['summary'] ?? $jiraKey;
        $desc = is_string($issue['fields']['description'] ?? null) ? ($issue['fields']['description'] ?? '') : '';
        $planId = Database::insert('INSERT INTO test_plans (project_id, name, description, xray_key, xray_synced_at) VALUES (?,?,?,?,NOW())', [$projectId, $name, $desc, $jiraKey]);
        Database::execute('UPDATE xray_test_plans SET robodoc_plan_id=?,synced_at=NOW() WHERE jira_key=?', [$planId, $jiraKey]);

        $membersResult = self::apiGet($synapseApi . '/testPlan/' . $jiraKey . '/members', $auth);
        $mCode   = $membersResult[0];
        $members = $membersResult[1];
        $imported = 0;
        if ($mCode === 200 && is_array($members)) {
            $order = 0;
            foreach ($members as $m) {
                $tcKey  = $m['testCaseKey'] ?? '';
                $tcName = $m['testCaseSummary'] ?? $m['testCaseName'] ?? $m['summary'] ?? '';
                if (!$tcName) continue;
                Database::insert('INSERT INTO test_plan_items (test_plan_id,title,description,expected_result,priority,status,sort_order,synapse_key,synapse_synced_at) VALUES (?,?,?,?,?,?,?,?,NOW())', [$planId, $tcName, '', '', 'medium', 'pending', ++$order, $tcKey ?: null]);
                $imported++;
            }
        }
        echo json_encode(['success' => true, 'plan_id' => $planId, 'plan_name' => $name, 'imported_items' => $imported, 'redirect' => url('test-plans/' . $planId)]);
        exit;
    }

    // Search cached SynapseRT plans

    public static function searchPlans(): void
    {
        Auth::requireView('testing');
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        $cached = Database::fetchAll('SELECT jira_key, summary, status FROM xray_test_plans WHERE summary LIKE ? OR jira_key LIKE ? ORDER BY created_at DESC LIMIT 15', ['%' . $q . '%', '%' . $q . '%']);
        echo json_encode($cached);
        exit;
    }

    // Search test requests

    public static function searchTestRequests(): void
    {
        Auth::requireView('test_requests');
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        $where = $q ? 'WHERE tr.summary LIKE ? OR tr.jira_issue_key LIKE ?' : '';
        $params = $q ? ['%' . $q . '%', '%' . $q . '%'] : [];
        $results = Database::fetchAll('SELECT tr.id, tr.summary, tr.jira_issue_key, tr.jira_issue_url, tr.status FROM test_requests tr ' . $where . ' ORDER BY tr.created_at DESC LIMIT 20', $params);
        echo json_encode($results);
        exit;
    }

    // Link / unlink test request to test case

    public static function linkTestRequest(): void
    {
        Auth::requireEdit('test_requests');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $tcId  = (int)($_POST['item_id'] ?? 0);
        $reqId = (int)($_POST['request_id'] ?? 0);
        if (!$tcId || !$reqId) { http_response_code(422); echo json_encode(['error' => 'IDs erforderlich']); exit; }
        Database::execute('UPDATE test_plan_items SET test_request_id=? WHERE id=?', [$reqId, $tcId]);
        $req = Database::fetchOne('SELECT summary, jira_issue_key FROM test_requests WHERE id=?', [$reqId]);
        echo json_encode(['success' => true, 'summary' => $req['summary'] ?? '', 'jira_key' => $req['jira_issue_key'] ?? '']);
        exit;
    }

    public static function unlinkTestRequest(): void
    {
        Auth::requireEdit('test_requests');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $tcId = (int)($_POST['item_id'] ?? 0);
        if (!$tcId) { http_response_code(422); echo json_encode(['error' => 'ID erforderlich']); exit; }
        Database::execute('UPDATE test_plan_items SET test_request_id=NULL WHERE id=?', [$tcId]);
        echo json_encode(['success' => true]);
        exit;
    }
}
