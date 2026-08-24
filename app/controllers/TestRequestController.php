<?php
declare(strict_types=1);

class TestRequestController
{
    // ── Index ─────────────────────────────────────────────────────
    public static function index(): void
    {
        Auth::requireTestRequests(); Auth::requireView('test_requests');
        $requests = Database::fetchAll(
            "SELECT tr.*, u.name creator_name
             FROM test_requests tr
             LEFT JOIN users u ON u.id = tr.created_by
             ORDER BY tr.created_at DESC"
        );
        View::render('test-requests/index', ['requests' => $requests, 'title' => 'Test Requests']);
    }

    // ── Show ──────────────────────────────────────────────────────
    public static function show(string $id): void
    {
        Auth::requireTestRequests(); Auth::requireView('test_requests');
        $request = self::fetchRequest((int)$id);
        if (!$request) abort(404);
        $attachments = Database::fetchAll(
            'SELECT * FROM test_request_attachments WHERE request_id=? ORDER BY created_at',
            [(int)$id]
        );
        View::render('test-requests/show', [
            'request'     => $request,
            'attachments' => $attachments,
            'title'       => e($request['summary']),
        ]);
    }

    // ── Create ────────────────────────────────────────────────────
    public static function create(): void
    {
        Auth::requireTestRequests(); Auth::requireEdit('test_requests');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $id = self::saveRequest(null);
            self::handleAttachments($id);
            if (!empty($_POST['push_to_jira'])) {
                try { JiraController::createForTestRequest($id); } catch (\Throwable $e) {
                    flash('warning', 'Saved, but Jira push failed: ' . $e->getMessage());
                }
            }
            flash('success', 'Test Request created.');
            redirect('/test-requests/' . $id);
        }
        $templates = Database::fetchAll('SELECT id, name FROM test_request_templates ORDER BY name');
        $projectStatuses = self::projectStatuses();
        View::render('test-requests/create', [
            'templates'      => $templates,
            'projectStatuses'=> $projectStatuses,
            'title'          => 'New Test Request',
        ]);
    }

    // ── Edit ──────────────────────────────────────────────────────
    public static function edit(string $id): void
    {
        Auth::requireTestRequests(); Auth::requireEdit('test_requests');
        $request = self::fetchRequest((int)$id);
        if (!$request) abort(404);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            self::saveRequest((int)$id);
            self::handleAttachments((int)$id);
            flash('success', 'Test Request updated.');
            redirect('/test-requests/' . $id);
        }
        $attachments     = Database::fetchAll('SELECT * FROM test_request_attachments WHERE request_id=? ORDER BY created_at', [(int)$id]);
        $templates       = Database::fetchAll('SELECT id, name FROM test_request_templates ORDER BY name');
        $projectStatuses = self::projectStatuses();
        View::render('test-requests/edit', [
            'request'        => $request,
            'attachments'    => $attachments,
            'templates'      => $templates,
            'projectStatuses'=> $projectStatuses,
            'title'          => 'Edit Test Request',
        ]);
    }

    // ── Delete ────────────────────────────────────────────────────
    public static function delete(string $id): void
    {
        Auth::requireTestRequests(); Auth::requireEdit('test_requests');
        Auth::verifyCsrf();
        $request = self::fetchRequest((int)$id);
        if (!$request) abort(404);
        $atts = Database::fetchAll('SELECT file_path FROM test_request_attachments WHERE request_id=?', [(int)$id]);
        foreach ($atts as $att) {
            if (file_exists($att['file_path'])) @unlink($att['file_path']);
        }
        Database::execute('DELETE FROM test_requests WHERE id=?', [(int)$id]);
        flash('success', 'Test Request deleted.');
        redirect('/test-requests');
    }

    // ── Delete attachment ─────────────────────────────────────────
    public static function deleteAttachment(string $id, string $attId): void
    {
        Auth::requireTestRequests();
        Auth::verifyCsrf();
        $att = Database::fetchOne('SELECT * FROM test_request_attachments WHERE id=? AND request_id=?', [(int)$attId, (int)$id]);
        if (!$att) abort(404);
        if (file_exists($att['file_path'])) @unlink($att['file_path']);
        Database::execute('DELETE FROM test_request_attachments WHERE id=?', [(int)$attId]);
        flash('success', 'Attachment deleted.');
        redirect('/test-requests/' . $id . '/edit');
    }

    // ── Push to Jira ──────────────────────────────────────────────
    public static function pushJira(string $id): void
    {
        Auth::requireTestRequests();
        Auth::verifyCsrf();
        header('Content-Type: application/json');
        $request = self::fetchRequest((int)$id);
        if (!$request) { echo json_encode(['error' => 'Not found']); exit; }
        try {
            JiraController::createForTestRequest((int)$id);
            $updated = self::fetchRequest((int)$id);
            echo json_encode(['success' => true, 'key' => $updated['jira_issue_key'], 'url' => $updated['jira_issue_url']]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Templates: list ───────────────────────────────────────────
    public static function templates(): void
    {
        Auth::requireTestRequests();
        $templates = Database::fetchAll(
            'SELECT t.*, u.name creator_name FROM test_request_templates t LEFT JOIN users u ON u.id = t.created_by ORDER BY t.name'
        );
        View::render('test-requests/templates', ['templates' => $templates, 'title' => 'Test Request Templates']);
    }

    // ── Templates: create ─────────────────────────────────────────
    public static function templateCreate(): void
    {
        Auth::requireTestRequests();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/test-requests/templates'); }
        Auth::verifyCsrf();
        $projectStatuses = self::projectStatuses();
        Database::execute(
            'INSERT INTO test_request_templates (name,description,labels,project_name,project_number,order_number,product,initiator,development_type,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                trim($_POST['name'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['labels'] ?? ''),
                trim($_POST['project_name'] ?? ''),
                trim($_POST['project_number'] ?? ''),
                trim($_POST['order_number'] ?? ''),
                trim($_POST['product'] ?? ''),
                trim($_POST['initiator'] ?? ''),
                trim($_POST['development_type'] ?? ''),
                Auth::id(),
            ]
        );
        flash('success', 'Template created.');
        redirect('/test-requests/templates');
    }

    // ── Templates: edit ───────────────────────────────────────────
    public static function templateEdit(string $id): void
    {
        Auth::requireTestRequests();
        $tpl = Database::fetchOne('SELECT * FROM test_request_templates WHERE id=?', [(int)$id]);
        if (!$tpl) abort(404);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute(
                'UPDATE test_request_templates SET name=?,description=?,labels=?,project_name=?,project_number=?,order_number=?,product=?,initiator=?,development_type=? WHERE id=?',
                [
                    trim($_POST['name'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    trim($_POST['labels'] ?? ''),
                    trim($_POST['project_name'] ?? ''),
                    trim($_POST['project_number'] ?? ''),
                    trim($_POST['order_number'] ?? ''),
                    trim($_POST['product'] ?? ''),
                    trim($_POST['initiator'] ?? ''),
                    trim($_POST['development_type'] ?? ''),
                    (int)$id,
                ]
            );
            flash('success', 'Template updated.');
            redirect('/test-requests/templates');
        }
        $projectStatuses = self::projectStatuses();
        View::render('test-requests/template_edit', ['tpl' => $tpl, 'projectStatuses' => $projectStatuses, 'title' => 'Edit Template']);
    }

    // ── Templates: delete ─────────────────────────────────────────
    public static function templateDelete(string $id): void
    {
        Auth::requireTestRequests();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_request_templates WHERE id=?', [(int)$id]);
        flash('success', 'Template deleted.');
        redirect('/test-requests/templates');
    }

    // ── Create from Test Case ─────────────────────────────────────
    public static function fromTestCase(string $itemId): void
    {
        Auth::requireTestRequests();
        $item = Database::fetchOne(
            'SELECT tpi.*, tp.project_id FROM test_plan_items tpi JOIN test_plans tp ON tp.id=tpi.test_plan_id WHERE tpi.id=?',
            [(int)$itemId]
        );
        if (!$item) abort(404);

        $customFields = Database::fetchAll('SELECT * FROM test_case_fields ORDER BY sort_order, name');
        $cfValues = [];
        if ($customFields) {
            $rows = Database::fetchAll('SELECT field_id, value FROM test_case_field_values WHERE item_id=?', [(int)$itemId]);
            foreach ($rows as $r) $cfValues[$r['field_id']] = $r['value'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $id = self::saveRequest(null);
            self::handleAttachments($id);
            // link this test request back to the test case item
            Database::execute('UPDATE test_plan_items SET test_request_id=? WHERE id=?', [$id, (int)$itemId]);
            flash('success', 'Test Request created and linked to test case.');
            redirect('/test-requests/' . $id);
        }

        // Build pre-filled field values using {{variable_name}} substitution
        $varMap = [];
        foreach ($customFields as $f) {
            $varMap['{{' . $f['variable_name'] . '}}'] = $cfValues[$f['id']] ?? '';
        }
        $varMap['{{title}}']       = $item['title'] ?? '';
        $varMap['{{description}}'] = $item['description'] ?? '';

        $prefill = [
            'summary'     => self::applyVars($item['title'], $varMap),
            'description' => self::applyVars($item['description'] ?? '', $varMap),
        ];

        $templates       = Database::fetchAll('SELECT id, name FROM test_request_templates ORDER BY name');
        $projectStatuses = self::projectStatuses();
        View::render('test-requests/create', [
            'templates'       => $templates,
            'projectStatuses' => $projectStatuses,
            'prefill'         => $prefill,
            'fromTestCase'    => $item,
            'customFields'    => $customFields,
            'cfValues'        => $cfValues,
            'title'           => 'Test Request from Test Case',
        ]);
    }

    private static function applyVars(string $text, array $map): string
    {
        return str_replace(array_keys($map), array_values($map), $text);
    }

    // ── Import from Jira ──────────────────────────────────────────
    public static function importJira(): void
    {
        Auth::requireTestRequests();
        $projectKey = appSetting('jira_test_request_project');
        $projectStatuses = self::projectStatuses();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $keys    = array_filter(array_map('trim', (array)($_POST['keys'] ?? [])));
            $jql     = $_POST['jql'] ?? '';
            $imported = 0;
            $skipped  = 0;

            if ($keys && $jql) {
                $result = JiraController::searchForImport($jql, 100);
                $issues = $result['issues'] ?? [];
                $issueMap = array_column($issues, null, 'key');

                $existingKeys = array_column(
                    Database::fetchAll('SELECT jira_issue_key FROM test_requests WHERE jira_issue_key IS NOT NULL'),
                    'jira_issue_key'
                );

                $jiraBase = rtrim(appSetting('jira_url'), '/');
                foreach ($keys as $key) {
                    if (in_array($key, $existingKeys, true)) { $skipped++; continue; }
                    $issue = $issueMap[$key] ?? null;
                    if (!$issue) continue;
                    Database::insert(
                        'INSERT INTO test_requests
                         (summary,description,labels,project_name,project_number,order_number,product,initiator,development_type,status,jira_issue_key,jira_issue_url,jira_synced_at,created_by)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)',
                        [
                            $issue['summary'],
                            $issue['description'],
                            $issue['labels'],
                            $issue['project_name'],
                            $issue['project_number'],
                            $issue['order_number'],
                            $issue['product'],
                            $issue['initiator'],
                            $issue['development_type'],
                            'submitted',
                            $key,
                            $jiraBase ? "$jiraBase/browse/$key" : '',
                            Auth::id(),
                        ]
                    );
                    $imported++;
                }
            }
            if ($imported) flash('success', "Imported $imported issue(s)" . ($skipped ? ", $skipped already existed." : '.'));
            elseif ($skipped) flash('warning', "All selected issues already exist locally.");
            else flash('error', 'Nothing imported.');
            redirect('/test-requests');
        }

        View::render('test-requests/import_jira', [
            'projectKey'     => $projectKey,
            'projectStatuses'=> $projectStatuses,
            'title'          => 'Import from Jira',
        ]);
    }

    // ── API: Jira issue search (AJAX) ─────────────────────────────
    public static function jiraSearch(): void
    {
        Auth::requireTestRequests();
        header('Content-Type: application/json');
        $q          = trim($_GET['q'] ?? '');
        $projectKey = appSetting('jira_test_request_project');
        if (!$projectKey) { echo json_encode(['error' => 'Jira Test Request project not configured.']); exit; }

        $jql = 'project = ' . strtoupper($projectKey) . ' AND issuetype = Request';
        if ($q) $jql .= ' AND summary ~ ' . json_encode("$q*");
        $jql .= ' ORDER BY created DESC';

        $result = JiraController::searchForImport($jql, 30);
        echo json_encode($result);
        exit;
    }

    // ── API: load template ────────────────────────────────────────
    public static function templateLoad(string $id): void
    {
        Auth::requireTestRequests();
        header('Content-Type: application/json');
        $tpl = Database::fetchOne('SELECT * FROM test_request_templates WHERE id=?', [(int)$id]);
        if (!$tpl) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
        echo json_encode($tpl);
        exit;
    }

    // ── Private helpers ───────────────────────────────────────────

    private static function fetchRequest(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT tr.*, u.name creator_name FROM test_requests tr LEFT JOIN users u ON u.id = tr.created_by WHERE tr.id=?',
            [$id]
        ) ?: null;
    }

    private static function saveRequest(?int $id): int
    {
        $data = [
            'summary'          => trim($_POST['summary'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'labels'           => trim($_POST['labels'] ?? ''),
            'project_name'     => trim($_POST['project_name'] ?? ''),
            'project_number'   => trim($_POST['project_number'] ?? ''),
            'order_number'     => trim($_POST['order_number'] ?? ''),
            'product'          => trim($_POST['product'] ?? ''),
            'initiator'        => trim($_POST['initiator'] ?? ''),
            'development_type' => trim($_POST['development_type'] ?? ''),
            'status'           => $_POST['status'] ?? 'draft',
        ];
        if ($id === null) {
            return Database::insert(
                'INSERT INTO test_requests (summary,description,labels,project_name,project_number,order_number,product,initiator,development_type,status,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                array_merge(array_values($data), [Auth::id()])
            );
        }
        Database::execute(
            'UPDATE test_requests SET summary=?,description=?,labels=?,project_name=?,project_number=?,order_number=?,product=?,initiator=?,development_type=?,status=? WHERE id=?',
            array_merge(array_values($data), [$id])
        );
        return $id;
    }

    private static function handleAttachments(int $requestId): void
    {
        $files = $_FILES['attachments'] ?? [];
        if (empty($files['name'][0])) return;
        $dir = UPLOAD_DIR . 'tr_' . $requestId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $orig = $files['name'][$i];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
            $dest = $dir . time() . '_' . $i . '_' . $safe;
            if (!move_uploaded_file($files['tmp_name'][$i], $dest)) continue;
            Database::execute(
                'INSERT INTO test_request_attachments (request_id,file_path,original_name,mime_type,file_size,uploaded_by)
                 VALUES (?,?,?,?,?,?)',
                [
                    $requestId,
                    $dest,
                    $orig,
                    $files['type'][$i] ?: 'application/octet-stream',
                    $files['size'][$i],
                    Auth::id(),
                ]
            );
        }
    }

    private static function projectStatuses(): array
    {
        $raw = appSetting('project_statuses', '["Prototype","EP0","EP1","EP2","MP","SOP"]');
        return json_decode($raw, true) ?: [];
    }
}
