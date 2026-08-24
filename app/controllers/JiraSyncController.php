<?php
declare(strict_types=1);

class JiraSyncController
{
    // ?? API: Bulk check all linked records ????????????????????????
    // Called fire-and-forget from the layout. Throttled by app_settings.
    public static function bulkCheck(): void
    {
        Auth::require();
        session_write_close(); // release session lock before slow external API calls
        header('Content-Type: application/json');

        // Throttle: only run once every 15 minutes (bypassed when force=1 for manual clicks)
        $force     = !empty($_POST['force']);
        $lastCheck = appSetting('jira_last_bulk_check');
        if (!$force && $lastCheck && (time() - strtotime($lastCheck)) < 900) {
            echo json_encode(['skipped' => true, 'next_in' => 900 - (time() - strtotime($lastCheck))]);
            exit;
        }

        Database::execute(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES ('jira_last_bulk_check', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
            [date('Y-m-d H:i:s')]
        );

        $changed = JiraController::bulkCheckChanges();
        echo json_encode(['ok' => true, 'changed' => $changed]);
        exit;
    }

    // ?? API: Check a single record right now (no throttle) ????????
    public static function checkRecord(): void
    {
        Auth::require();
        session_write_close();
        header('Content-Type: application/json');

        $sourceType = $_POST['source_type'] ?? '';
        $sourceId   = (int)($_POST['source_id'] ?? 0);
        if (!$sourceType || !$sourceId) { echo json_encode(['error' => 'Missing params']); exit; }

        $table = $sourceType === 'entry' ? 'entries' : 'test_requests';
        $rec   = Database::fetchOne(
            "SELECT id, jira_issue_key, jira_synced_at, jira_has_changes FROM $table WHERE id=?",
            [$sourceId]
        );
        if (!$rec || !$rec['jira_issue_key']) { echo json_encode(['error' => 'No Jira issue linked']); exit; }

        [$result] = self::checkOneRecord($table, $rec);
        echo json_encode($result);
        exit;
    }

    // Check a single record; returns [resultArray, changed bool]
    private static function checkOneRecord(string $table, array $rec): array
    {
        // Use Jira search to get current `updated` field
        $state = JiraController::fetchIssueState($rec['jira_issue_key']);
        if ($state['error'] ?? null) {
            return [['error' => $state['error']], false];
        }

        $jiraTs   = $state['updated_at'] ? strtotime($state['updated_at']) : 0;
        $syncedTs = $rec['jira_synced_at'] ? strtotime($rec['jira_synced_at']) : 0;

        if (!$syncedTs) {
            // Establish baseline
            Database::execute(
                "UPDATE $table SET jira_synced_at=? WHERE id=?",
                [$state['updated_at'], $rec['id']]
            );
            return [['has_changes' => false, 'baseline_set' => true, 'jira_updated_at' => $state['updated_at']], false];
        }

        $hasChanges = $jiraTs > $syncedTs;
        if ($hasChanges && !$rec['jira_has_changes']) {
            Database::execute("UPDATE $table SET jira_has_changes=1 WHERE id=?", [$rec['id']]);
        }

        return [[
            'has_changes'    => $hasChanges || (bool)$rec['jira_has_changes'],
            'jira_updated_at'=> $state['updated_at'],
            'synced_at'      => $rec['jira_synced_at'],
        ], $hasChanges];
    }

    // ?? Review: Entry ?????????????????????????????????????????????
    public static function reviewEntry(string $id): void
    {
        Auth::require();
        $entry = Database::fetchOne(
            "SELECT e.*, et.name type_name, p.name project_name, ec.name cat_name,
                    env.name env_name, ta.name test_area_name
             FROM entries e
             LEFT JOIN entry_types       et  ON et.id  = e.entry_type_id
             LEFT JOIN projects          p   ON p.id   = e.project_id
             LEFT JOIN error_categories  ec  ON ec.id  = e.error_category_id
             LEFT JOIN test_environments env ON env.id = e.environment_id
             LEFT JOIN test_areas        ta  ON ta.id  = e.test_area_id
             WHERE e.id=?",
            [(int)$id]
        );
        if (!$entry || !$entry['jira_issue_key']) abort(404);

        $state = JiraController::fetchIssueState($entry['jira_issue_key']);
        $existingCommentIds = array_column(
            Database::fetchAll('SELECT jira_comment_id FROM jira_comments WHERE source_type=? AND source_id=?', ['entry', (int)$id]),
            'jira_comment_id'
        );

        // Build field-level diff from parsed Jira description fields
        $fieldDiff = self::buildEntryFieldDiff($entry, $state['parsed_fields'] ?? []);

        // Native Jira status comparison
        $jiraStatusName = $state['jira_status_name'] ?? null;
        if ($jiraStatusName) {
            $mappedLocal  = JiraController::mapJiraStatusToLocal($jiraStatusName);
            $localStatus  = $entry['status'] ?? '';
            $fieldDiff[] = [
                'jira_label' => 'jira_status',
                'label'      => 'Status',
                'local'      => (entryStatuses()[$localStatus] ?? $localStatus),
                'jira'       => $jiraStatusName . ' ? ' . (entryStatuses()[$mappedLocal] ?? $mappedLocal),
                'changed'    => $mappedLocal !== $localStatus,
                '_mapped'    => $mappedLocal,
            ];
        }

        // Native Jira priority comparison — via the admin-configured priority mapping
        // (Admin > Jira > Priority Mapping), falling back to a literal name match.
        $jiraPriorityName = $state['jira_priority_name'] ?? null;
        if ($jiraPriorityName) {
            $localPriority = $entry['priority'] ?? 'Medium';
            $fieldDiff[] = [
                'jira_label' => 'jira_priority',
                'label'      => 'Priority',
                'local'      => $localPriority,
                'jira'       => $jiraPriorityName . ' → ' . JiraController::mapJiraPriorityToLocal($jiraPriorityName),
                'changed'    => !JiraController::jiraPriorityMatchesLocal($jiraPriorityName, $localPriority),
                '_mapped'    => JiraController::mapJiraPriorityToLocal($jiraPriorityName),
            ];
        }

        $localAttachments = Database::fetchAll(
            'SELECT original_name, display_name FROM entry_attachments WHERE entry_id=?',
            [(int)$id]
        );
        View::render('jira-sync/review', [
            'title'              => 'Jira Changes ? Entry #' . $id,
            'sourceType'         => 'entry',
            'sourceId'           => (int)$id,
            'backUrl'            => url('entries/' . $id),
            'localDescription'   => $entry['description'] ?? '',
            'state'              => $state,
            'existingCommentIds' => $existingCommentIds,
            'acceptUrl'          => url('jira-sync/entry/' . $id . '/accept'),
            'fieldDiff'          => $fieldDiff,
            'localAttachments'   => $localAttachments,
        ]);
    }

    // Build a diff array comparing local entry values against Jira parsed fields.
    // Returns array of ['label', 'jira_label', 'local', 'jira', 'changed']
    private static function buildEntryFieldDiff(array $entry, array $jiraFields): array
    {
        if (!$jiraFields) return [];

        // Which local value to display for each field definition
        $localValueMap = [
            'Type'           => $entry['type_name']            ?? '',
            'Category'       => $entry['cat_name']             ?? '',
            'Project'        => $entry['project_name']         ?? '',
            'Project Status' => $entry['project_status_robot'] ?? '',
            'Serial'         => $entry['mower_serial']         ?? '',
            'Firmware'       => $entry['firmware_version']     ?? '',
            'App Version'    => $entry['app_version']          ?? '',
            'Environment'    => $entry['env_name']             ?? '',
            'Test Area'      => $entry['test_area_name']       ?? '',
        ];

        $diff = [];
        foreach (JiraController::entryFieldMap() as $jiraLabel => $def) {
            if (!array_key_exists($jiraLabel, $jiraFields)) continue;
            $localVal = $localValueMap[$jiraLabel] ?? '';
            $jiraVal  = $jiraFields[$jiraLabel];
            $diff[]   = [
                'jira_label' => $jiraLabel,
                'label'      => $def['label'],
                'local'      => $localVal,
                'jira'       => $jiraVal,
                'changed'    => strtolower(trim($localVal)) !== strtolower(trim($jiraVal)),
            ];
        }

        return $diff;
    }

    // ?? Review: Test Request ??????????????????????????????????????
    public static function reviewTestRequest(string $id): void
    {
        Auth::requireTestRequests();
        $request = Database::fetchOne('SELECT * FROM test_requests WHERE id=?', [(int)$id]);
        if (!$request || !$request['jira_issue_key']) abort(404);

        $state = JiraController::fetchIssueState($request['jira_issue_key']);
        $existingCommentIds = array_column(
            Database::fetchAll('SELECT jira_comment_id FROM jira_comments WHERE source_type=? AND source_id=?', ['test_request', (int)$id]),
            'jira_comment_id'
        );

        View::render('jira-sync/review', [
            'title'              => 'Jira Changes ? Test Request #' . $id,
            'sourceType'         => 'test_request',
            'sourceId'           => (int)$id,
            'backUrl'            => url('test-requests/' . $id),
            'localDescription'   => $request['description'] ?? '',
            'state'              => $state,
            'existingCommentIds' => $existingCommentIds,
            'acceptUrl'          => url('jira-sync/test-request/' . $id . '/accept'),
        ]);
    }

    // ?? Accept: Entry ?????????????????????????????????????????????
    public static function acceptEntry(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $entry = Database::fetchOne('SELECT * FROM entries WHERE id=?', [(int)$id]);
        if (!$entry || !$entry['jira_issue_key']) abort(404);

        $state = JiraController::fetchIssueState($entry['jira_issue_key']);
        if ($state['error'] ?? null) {
            flash('error', 'Jira error: ' . $state['error']);
            redirect('/entries/' . $id);
        }

        // Per-field acceptance from structured description fields
        $acceptFields = $_POST['accept_fields'] ?? [];
        // Handle native Jira status field
        if (in_array('jira_status', $acceptFields, true) && ($state['jira_status_name'] ?? null)) {
            $mappedStatus = JiraController::mapJiraStatusToLocal($state['jira_status_name']);
            Database::execute('UPDATE entries SET status=?, jira_status=? WHERE id=?',
                [$mappedStatus, $state['jira_status_name'], (int)$id]);
            $acceptFields = array_values(array_filter($acceptFields, fn($f) => $f !== 'jira_status'));
        }
        // Handle native Jira priority field
        if (in_array('jira_priority', $acceptFields, true) && ($state['jira_priority_name'] ?? null)) {
            $pri = JiraController::mapJiraPriorityToLocal($state['jira_priority_name']);
            Database::execute('UPDATE entries SET priority=?, jira_priority=? WHERE id=?',
                [$pri, $state['jira_priority_name'], (int)$id]);
            $acceptFields = array_values(array_filter($acceptFields, fn($f) => $f !== 'jira_priority'));
        }
        if ($acceptFields && ($state['parsed_fields'] ?? [])) {
            self::applyFieldUpdates((int)$id, $acceptFields, $state['parsed_fields']);
        }

        // Free-text description acceptance
        $acceptDesc = !empty($_POST['accept_description']);
        if ($acceptDesc) {
            $descValue = ($state['parsed_free_text'] ?? '') !== '' ? $state['parsed_free_text'] : $state['description'];
            Database::execute('UPDATE entries SET description=? WHERE id=?', [$descValue, (int)$id]);
        }

        self::syncComments('entry', (int)$id, $state['comments']);

        Database::execute(
            'UPDATE entries SET jira_synced_at=?, jira_has_changes=0 WHERE id=?',
            [$state['updated_at'], (int)$id]
        );

        // Download new Jira attachments included in the form
        $attCount = 0;
        $jiraAttsJson = trim($_POST['jira_attachments'] ?? '');
        if ($jiraAttsJson) {
            $jiraAtts = json_decode($jiraAttsJson, true) ?: [];
            if ($jiraAtts) {
                [, , $authHeader, $authErr] = JiraController::resolveAuth();
                if (!$authErr) {
                    foreach ($jiraAtts as $att) {
                        $contentUrl = trim($att['content_url'] ?? '');
                        if (!$contentUrl) continue;
                        $res = self::fetchAndSaveJiraAttachment(
                            (int)$id, $contentUrl,
                            $att['filename'] ?? 'attachment',
                            $att['mime_type'] ?? '', $authHeader
                        );
                        if ($res === true) $attCount++;
                    }
                }
            }
        }

        $msg = 'Jira changes accepted and imported.';
        if ($attCount) $msg .= " $attCount attachment" . ($attCount !== 1 ? 's' : '') . " downloaded.";
        flash('success', $msg);
        redirect('/entries/' . $id);
    }

    // Apply selected per-field updates from Jira parsed fields to entries table
    private static function applyFieldUpdates(int $entryId, array $selectedFields, array $parsedFields): void
    {
        $fieldMap = JiraController::entryFieldMap();

        // Lookup tables for FK fields
        $lookupTables = [
            'entry_types'       => 'name',
            'error_categories'  => 'name',
            'projects'          => 'name',
            'test_environments' => 'name',
            'test_areas'        => 'name',
        ];

        $sets  = [];
        $binds = [];

        foreach ($selectedFields as $jiraLabel) {
            if (!isset($fieldMap[$jiraLabel]) || !array_key_exists($jiraLabel, $parsedFields)) continue;
            $def   = $fieldMap[$jiraLabel];
            $value = $parsedFields[$jiraLabel];
            $col   = $def['col'];

            if ($def['lookup']) {
                // FK: resolve name ? id
                $nameCol = $lookupTables[$def['lookup']] ?? 'name';
                $row = Database::fetchOne(
                    "SELECT id FROM {$def['lookup']} WHERE $nameCol=? LIMIT 1",
                    [$value]
                );
                if (!$row) continue; // Skip if we can't resolve the name
                $sets[]  = "$col=?";
                $binds[] = $row['id'];
            } else {
                $sets[]  = "$col=?";
                $binds[] = $value;
            }
        }

        if ($sets) {
            $binds[] = $entryId;
            Database::execute('UPDATE entries SET ' . implode(',', $sets) . ' WHERE id=?', $binds);
        }
    }

    // ?? Accept: Test Request ??????????????????????????????????????
    public static function acceptTestRequest(string $id): void
    {
        Auth::requireTestRequests();
        Auth::verifyCsrf();
        $request = Database::fetchOne('SELECT * FROM test_requests WHERE id=?', [(int)$id]);
        if (!$request || !$request['jira_issue_key']) abort(404);

        $state = JiraController::fetchIssueState($request['jira_issue_key']);
        if ($state['error'] ?? null) {
            flash('error', 'Jira error: ' . $state['error']);
            redirect('/test-requests/' . $id);
        }

        $acceptDesc = !empty($_POST['accept_description']);
        if ($acceptDesc) {
            Database::execute('UPDATE test_requests SET description=? WHERE id=?', [$state['description'], (int)$id]);
        }

        self::syncComments('test_request', (int)$id, $state['comments']);

        Database::execute(
            'UPDATE test_requests SET jira_synced_at=?, jira_has_changes=0 WHERE id=?',
            [$state['updated_at'], (int)$id]
        );

        flash('success', 'Jira changes accepted and imported.');
        redirect('/test-requests/' . $id);
    }

    // ?? Download one or all new Jira attachments to the local entry ?
    public static function downloadAttachment(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        session_write_close();
        if (!Database::fetchOne('SELECT id FROM entries WHERE id=?', [(int)$id])) abort(404);

        [, , $authHeader, $err] = JiraController::resolveAuth();
        if ($err) { flash('error', $err); redirect('/jira-sync/entry/' . $id); }

        // Support single download (content_url) or batch download (attachments JSON array)
        $batch = [];
        if (!empty($_POST['attachments'])) {
            $batch = json_decode($_POST['attachments'], true) ?: [];
        } elseif (!empty($_POST['content_url'])) {
            $batch = [[
                'content_url' => $_POST['content_url'],
                'filename'    => $_POST['filename']    ?? 'attachment',
                'mime_type'   => $_POST['mime_type']   ?? '',
            ]];
        }
        if (!$batch) { flash('error', 'No attachments specified.'); redirect('/jira-sync/entry/' . $id); }

        $ok = 0; $fail = [];
        foreach ($batch as $att) {
            $contentUrl = trim($att['content_url'] ?? '');
            $filename   = trim($att['filename']    ?? 'attachment');
            $mimeType   = trim($att['mime_type']   ?? '');
            if (!$contentUrl) { $fail[] = "$filename: no download URL available"; continue; }

            $result = self::fetchAndSaveJiraAttachment((int)$id, $contentUrl, $filename, $mimeType, $authHeader);
            if ($result === true) { $ok++; } else { $fail[] = "$filename: $result"; }
        }

        if ($ok === 0 && empty($fail)) {
            flash('warning', 'No attachments to download.');
        } elseif ($fail) {
            flash('warning', "$ok downloaded. Failed: " . implode('; ', $fail));
        } else {
            flash('success', $ok === 1 ? 'Attachment downloaded from Jira.' : "$ok attachments downloaded from Jira.");
        }
        // Redirect to entry so the user can see the attachments immediately
        redirect('/entries/' . $id);
    }

    private static function fetchAndSaveJiraAttachment(int $entryId, string $contentUrl, string $filename, string $mimeType, string $authHeader): true|string
    {
        // Step 1: request with auth ? do NOT auto-follow redirects yet
        // (Jira Cloud may redirect to an S3 presigned URL that rejects the Authorization header)
        $ch = curl_init($contentUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $authHeader, 'Accept: */*'],
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw        = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false) return 'curl error';

        $respHeaders = substr($raw, 0, $headerSize);
        $body        = substr($raw, $headerSize);

        if (in_array($httpCode, [301, 302, 303, 307, 308], true)) {
            // Extract Location header and follow without auth (e.g. S3 presigned URL)
            if (!preg_match('/^Location:\s*(\S+)/im', $respHeaders, $m)) {
                return 'Redirect with no Location header';
            }
            $redirectUrl = trim($m[1]);
            $ch2 = curl_init($redirectUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,   // follow further if needed
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => true,
                // No Authorization header ? S3 presigned URLs must not have it
            ]);
            $body     = curl_exec($ch2);
            $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
        }

        if ($body === false || $body === '' || ($httpCode !== 200 && $httpCode !== 206)) {
            return "HTTP $httpCode";
        }
        $data = $body;

        $ext     = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $internalFn = bin2hex(random_bytes(16)) . ($ext ? ".$ext" : '');
        $dir     = UPLOAD_DIR . $entryId . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'Could not create upload directory';
        $dest = $dir . $internalFn;
        if (file_put_contents($dest, $data) === false) return 'Could not write file to disk';

        if (!$mimeType) $mimeType = mime_content_type($dest) ?: 'application/octet-stream';

        Database::insert(
            'INSERT INTO entry_attachments (entry_id, filename, original_name, display_name, file_path, mime_type, file_size, jira_synced)
             VALUES (?,?,?,?,?,?,?,1)',
            [$entryId, $internalFn, $filename, $filename, $dest, $mimeType, strlen($data)]
        );
        try { Database::execute('UPDATE entries SET attachments_updated_at=NOW() WHERE id=?', [$entryId]); } catch (\Throwable) {}
        return true;
    }

    // ?? Push local entry changes ? Jira ??????????????????????????
    public static function pushEntry(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $entry = JiraController::fetchEntry((int)$id);
        if (!$entry || !$entry['jira_issue_key']) abort(404);

        $user     = Database::fetchOne('SELECT jira_title_template, jira_desc_template FROM users WHERE id=?', [Auth::id()]);
        $titleTpl = ($user['jira_title_template'] ?? '') ?: (appSetting('jira_default_title_template') ?: '[{{type}}] {{title}}');
        $descTpl  = ($user['jira_desc_template']  ?? '') ?: (appSetting('jira_default_desc_template')  ?: '');

        $result = JiraController::buildAndPushEntry((int)$id, $titleTpl, $descTpl);
        if (isset($result['error'])) {
            flash('error', 'Push to Jira failed: ' . $result['error']);
        } else {
            // priority/transition results start with "set to"/"transitioned" on success —
            // anything else means Jira rejected the field, which must not be reported as
            // a plain success or the user won't notice status/priority weren't applied.
            $notes = [];
            if (!empty($result['priority']) && !str_starts_with($result['priority'], 'set to')) {
                $notes[] = 'Priority NOT applied (' . $result['priority'] . ')';
            }
            if (!empty($result['transition']) && !str_starts_with($result['transition'], 'transitioned')) {
                $notes[] = 'Status NOT applied (' . $result['transition'] . ')';
            }
            if ($notes) {
                flash('warning', 'Pushed to Jira, but: ' . implode(' / ', $notes));
            } else {
                flash('success', 'Local changes pushed to Jira successfully.');
            }
        }
        redirect('/entries/' . $id);
    }

    // ?? Dismiss (mark as seen without importing) ??????????????????
    public static function dismissEntry(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $entry = Database::fetchOne('SELECT jira_issue_key FROM entries WHERE id=?', [(int)$id]);
        // Store Jira's actual updated_at so we don't re-flag the same update
        $syncedAt = date('Y-m-d H:i:s');
        if ($entry['jira_issue_key'] ?? null) {
            $state = JiraController::fetchIssueState($entry['jira_issue_key']);
            if (!($state['error'] ?? null) && ($state['updated_at'] ?? null)) {
                $syncedAt = $state['updated_at'];
            }
        }
        Database::execute('UPDATE entries SET jira_has_changes=0, jira_synced_at=? WHERE id=?', [$syncedAt, (int)$id]);
        flash('info', 'Jira change notification dismissed.');
        redirect('/entries/' . $id);
    }

    public static function dismissTestRequest(string $id): void
    {
        Auth::requireTestRequests();
        Auth::verifyCsrf();
        $request = Database::fetchOne('SELECT jira_issue_key FROM test_requests WHERE id=?', [(int)$id]);
        $syncedAt = date('Y-m-d H:i:s');
        if ($request['jira_issue_key'] ?? null) {
            $state = JiraController::fetchIssueState($request['jira_issue_key']);
            if (!($state['error'] ?? null) && ($state['updated_at'] ?? null)) {
                $syncedAt = $state['updated_at'];
            }
        }
        Database::execute('UPDATE test_requests SET jira_has_changes=0, jira_synced_at=? WHERE id=?', [$syncedAt, (int)$id]);
        flash('info', 'Jira change notification dismissed.');
        redirect('/test-requests/' . $id);
    }

    // ?? Helper: upsert Jira comments into local DB ????????????????
    // ?? On-demand comment sync for entry detail page ?????????????
    public static function syncCommentsForEntry(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');
        $entry = Database::fetchOne('SELECT jira_issue_key FROM entries WHERE id=?', [(int)$id]);
        if (!$entry || !$entry['jira_issue_key']) { echo json_encode(['error' => 'No Jira issue linked']); exit; }
        $state = JiraController::fetchIssueState($entry['jira_issue_key']);
        if ($state['error'] ?? null) { echo json_encode(['error' => $state['error']]); exit; }
        $comments = $state['comments'] ?? [];
        self::syncComments('entry', (int)$id, $comments);
        echo json_encode(['success' => true, 'count' => count($comments)]);
        exit;
    }

    private static function syncComments(string $sourceType, int $sourceId, array $comments): void
    {
        foreach ($comments as $c) {
            Database::execute(
                'INSERT INTO jira_comments (source_type, source_id, jira_comment_id, author_name, body, jira_created_at, jira_updated_at)
                 VALUES (?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE author_name=VALUES(author_name), body=VALUES(body), jira_updated_at=VALUES(jira_updated_at), synced_at=NOW()',
                [
                    $sourceType,
                    $sourceId,
                    $c['id'],
                    $c['author'],
                    $c['body'],
                    $c['created_at'] ?: null,
                    $c['updated_at'] ?: null,
                ]
            );
        }
    }

    // ?? Unlinked Jira issues (not yet tied to any entry) ?????????
    public static function unlinkedIssues(): void
    {
        Auth::require();
        $settings   = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $projectKey = trim($settings['jira_default_project'] ?? '');
        $defaultJql = $projectKey
            ? "project=$projectKey AND issuetype=Bug AND status not in (Closed, Declined) ORDER BY created DESC"
            : 'ORDER BY created DESC';
        $jql        = trim($_GET['jql'] ?? $defaultJql);
        $maxResults = 100;

        $linked = array_column(
            Database::fetchAll("SELECT jira_issue_key FROM entries WHERE jira_issue_key IS NOT NULL AND jira_issue_key != ''"),
            'jira_issue_key'
        );
        $dismissed    = Database::fetchAll('SELECT issue_key, dismissed_at FROM dismissed_jira_issues ORDER BY dismissed_at DESC');
        $dismissedSet = array_flip(array_column($dismissed, 'issue_key'));

        $issues = [];
        $error  = null;
        if ($projectKey || isset($_GET['jql'])) {
            $result    = JiraController::searchForImport($jql, $maxResults);
            $error     = $result['error'] ?? null;
            $all       = $result['issues'] ?? [];
            $linkedSet = array_flip(array_map('strtoupper', $linked));
            $issues    = array_values(array_filter($all, fn($i) =>
                !isset($linkedSet[strtoupper($i['key'])]) && !isset($dismissedSet[$i['key']])
            ));
        }

        $projects   = Database::fetchAll('SELECT id, name FROM projects ORDER BY name');
        $entryTypes = Database::fetchAll('SELECT id, name FROM entry_types ORDER BY sort_order, name');
        $jiraUrl    = rtrim(appSetting('jira_url', ''), '/');
        View::render('jira-sync/unlinked', compact('issues', 'dismissed', 'error', 'jql', 'projectKey', 'jiraUrl', 'projects', 'entryTypes') + ['title' => 'Unlinked Jira Issues']);
    }

    // ?? Dismiss / undismiss a Jira issue from the unlinked list ??
    public static function dismissUnlinked(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $key = strtoupper(trim($_POST['issue_key'] ?? ''));
        if (!$key) { json(['error' => 'Missing issue_key']); }
        Database::execute(
            'INSERT IGNORE INTO dismissed_jira_issues (issue_key, dismissed_by) VALUES (?,?)',
            [$key, Auth::id()]
        );
        json(['success' => true]);
    }

    public static function undismissUnlinked(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $key = strtoupper(trim($_POST['issue_key'] ?? ''));
        if (!$key) { json(['error' => 'Missing issue_key']); }
        Database::execute('DELETE FROM dismissed_jira_issues WHERE issue_key=?', [$key]);
        json(['success' => true]);
    }

    // ?? Link a Jira issue to an existing entry ????????????????????
    public static function linkIssueToEntry(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');
        $issueKey = strtoupper(trim($_POST['issue_key'] ?? ''));
        $entryId  = (int)($_POST['entry_id'] ?? 0);
        if (!$issueKey || !$entryId) { echo json_encode(['error' => 'Missing issue_key or entry_id']); exit; }

        $jiraBase = rtrim(appSetting('jira_url', ''), '/');
        $issueUrl = "$jiraBase/browse/$issueKey";
        Database::execute(
            'UPDATE entries SET jira_issue_key=?, jira_issue_url=?, jira_synced_at=NULL, jira_has_changes=0 WHERE id=?',
            [$issueKey, $issueUrl, $entryId]
        );
        echo json_encode(['success' => true]);
        exit;
    }

    // ?? Create a new entry from a Jira issue ?????????????????????
    public static function createEntryFromIssue(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $issueKey  = strtoupper(trim($_POST['issue_key'] ?? ''));
        $projectId = (int)($_POST['project_id'] ?? 0);
        $typeId    = (int)($_POST['entry_type_id'] ?? 0);
        if (!$issueKey || !$projectId || !$typeId) {
            flash('error', 'Issue key, project and entry type are required.');
            redirect('/jira-unlinked');
        }

        // Fetch issue from Jira to get title + description
        $state = JiraController::fetchIssueState($issueKey);
        $title = $state['key'] ?? $issueKey;
        if (!($state['error'] ?? null)) {
            // Extract summary from description parsed fields or use key as title
            $title = $_POST['title'] ?? $issueKey;
        }
        $title       = trim($_POST['title'] ?? ($state['description'] ? substr(strip_tags($state['description']), 0, 200) : $issueKey));
        $description = $state['description'] ?? '';
        $jiraBase    = rtrim(appSetting('jira_url', ''), '/');
        $jiraUrl     = "$jiraBase/browse/$issueKey";
        // Use Jira priority if available, fall back to Medium
        $jiraPri     = $state['jira_priority_name'] ?? null;
        $priority    = $jiraPri ? JiraController::mapJiraPriorityToLocal($jiraPri) : 'Medium';
        // Also pull mower serial and firmware from parsed description fields
        $parsedFields   = $state['parsed_fields'] ?? [];
        $mowerSerial    = $parsedFields['Serial']            ?? null;
        $firmwareVer    = $parsedFields['Firmware']          ?? null;
        $appVersion     = $parsedFields['App Version']       ?? null;
        $jiraStatusName = $state['jira_status_name']         ?? null;
        $mappedStatus   = $jiraStatusName ? JiraController::mapJiraStatusToLocal($jiraStatusName) : 'new';

        $id = Database::insert(
            'INSERT INTO entries (project_id, entry_type_id, entry_date, entry_time, title, description, status, priority,
                                  mower_serial, firmware_version, app_version,
                                  jira_issue_key, jira_issue_url, jira_status, jira_priority, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$projectId, $typeId, date('Y-m-d'), date('H:i:s'), $title ?: $issueKey, $description,
             $mappedStatus, $priority,
             $mowerSerial ?: null, $firmwareVer ?: null, $appVersion ?: null,
             $issueKey, $jiraUrl, $jiraStatusName, $jiraPri, Auth::id()]
        );

        // Download Jira attachments
        $attCount = 0;
        $jiraAtts = $state['attachments'] ?? [];
        if ($jiraAtts && $id) {
            [, , $authHeader, $authErr] = JiraController::resolveAuth();
            if (!$authErr) {
                foreach ($jiraAtts as $att) {
                    $contentUrl = trim($att['contentUrl'] ?? $att['content_url'] ?? '');
                    if (!$contentUrl) continue;
                    $res = self::fetchAndSaveJiraAttachment(
                        (int)$id,
                        $contentUrl,
                        $att['filename'] ?? 'attachment',
                        $att['mimeType'] ?? $att['mime_type'] ?? '',
                        $authHeader
                    );
                    if ($res === true) $attCount++;
                }
            }
        }
        $msg = "Entry created and linked to $issueKey.";
        if ($attCount) $msg .= " $attCount attachment" . ($attCount !== 1 ? 's' : '') . " downloaded.";
        flash('success', $msg);
        redirect('/entries/' . $id);
    }

    // ?? Bulk create entries from multiple unlinked Jira issues ????
    public static function bulkCreateFromIssues(): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $items     = (array)($_POST['items'] ?? []);
        $projectId = (int)($_POST['project_id'] ?? 0);
        $typeId    = (int)($_POST['entry_type_id'] ?? 0);
        $prefix    = trim($_POST['title_prefix'] ?? '');

        if (!$items || !$projectId || !$typeId) {
            json(['error' => 'Items, project and type are required.'], 400);
        }

        $jiraBase = rtrim(appSetting('jira_url', ''), '/');
        $created  = 0;
        $errors   = [];

        foreach ($items as $key => $summary) {
            $key = strtoupper(trim($key));
            if (!$key) continue;
            try {
                $title   = ($prefix ? $prefix . ' ' : '') . trim($summary ?: $key);
                $jiraUrl = "$jiraBase/browse/$key";
                // Fetch priority and status from Jira for each issue
                $bulkState   = JiraController::fetchIssueState($key);
                $bulkPriName = $bulkState['jira_priority_name'] ?? null;
                $bulkPri     = $bulkPriName ? JiraController::mapJiraPriorityToLocal($bulkPriName) : 'Medium';
                $bulkStat    = $bulkState['jira_status_name'] ?? null;
                $bulkStatus  = $bulkStat ? JiraController::mapJiraStatusToLocal($bulkStat) : 'new';
                $bulkParsed  = $bulkState['parsed_fields'] ?? [];
                $bulkDesc    = $bulkState['description'] ?? '';
                Database::insert(
                    'INSERT INTO entries (project_id, entry_type_id, entry_date, entry_time, title, description, status, priority,
                                          mower_serial, firmware_version, app_version,
                                          jira_issue_key, jira_issue_url, jira_status, jira_priority, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$projectId, $typeId, date('Y-m-d'), date('H:i:s'), $title, $bulkDesc,
                     $bulkStatus, $bulkPri,
                     $bulkParsed['Serial'] ?? null, $bulkParsed['Firmware'] ?? null, $bulkParsed['App Version'] ?? null,
                     $key, $jiraUrl, $bulkStat, $bulkPriName, Auth::id()]
                );
                $created++;
                // Download Jira attachments for this entry
                $bulkAtts = $bulkState['attachments'] ?? [];
                if ($bulkAtts) {
                    [, , $bulkAuthHeader, $bulkAuthErr] = JiraController::resolveAuth();
                    if (!$bulkAuthErr) {
                        $newEntryId = Database::lastInsertId();
                        foreach ($bulkAtts as $att) {
                            $contentUrl = trim($att['contentUrl'] ?? $att['content_url'] ?? $att['url'] ?? '');
                            if (!$contentUrl) continue;
                            self::fetchAndSaveJiraAttachment(
                                (int)$newEntryId,
                                $contentUrl,
                                $att['filename'] ?? 'attachment',
                                $att['mime_type'] ?? '',
                                $bulkAuthHeader
                            );
                        }
                    }
                }
            } catch (\Throwable) {
                $errors[] = $key;
            }
        }

        json(['success' => true, 'created' => $created, 'errors' => $errors]);
    }
}
