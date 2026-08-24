<?php
declare(strict_types=1);

class ZentaoSyncController
{
    // ── API: Check a single Zentao-linked entry (no throttle) ────
    public static function checkRecord(): void
    {
        Auth::require();
        header('Content-Type: application/json');
        $sourceId = (int)($_POST['source_id'] ?? 0);
        if (!$sourceId) { echo json_encode(['error' => 'Missing source_id']); exit; }

        $rec = Database::fetchOne(
            'SELECT id, zentao_bug_id, zentao_bug_hash, zentao_synced_at, zentao_has_changes, zentao_status, status FROM entries WHERE id=?',
            [$sourceId]
        );
        if (!$rec || !$rec['zentao_bug_id']) { echo json_encode(['error' => 'No Zentao bug linked']); exit; }

        $state = ZentaoController::fetchBugState((int)$rec['zentao_bug_id']);
        if ($state['error'] ?? null) { echo json_encode(['error' => $state['error']]); exit; }

        $newHash      = $state['hash'];
        $zentaoStatus = $state['status'] ?? '';
        $mappedStatus = ZentaoController::mapZentaoStatusToLocal($zentaoStatus);
        $statusDiffers = $zentaoStatus !== '' && $mappedStatus !== ($rec['status'] ?? '');
        $hashDiffers   = $rec['zentao_bug_hash'] && $newHash !== $rec['zentao_bug_hash'];
        $hasChanges    = $statusDiffers || $hashDiffers;

        if (!$rec['zentao_synced_at'] || !$rec['zentao_bug_hash']) {
            $flagChanges = $hasChanges ? ', zentao_has_changes=1' : '';
            Database::execute(
                "UPDATE entries SET zentao_synced_at=NOW(), zentao_bug_hash=?, zentao_status=?$flagChanges WHERE id=?",
                [$newHash, $zentaoStatus, $rec['id']]
            );
            echo json_encode(['has_changes' => $hasChanges, 'baseline_set' => !$hasChanges]);
        } else {
            if ($hasChanges && !$rec['zentao_has_changes']) {
                Database::execute('UPDATE entries SET zentao_has_changes=1, zentao_synced_at=NOW(), zentao_status=? WHERE id=?',
                    [$zentaoStatus, $rec['id']]);
            } elseif (!$hasChanges) {
                Database::execute('UPDATE entries SET zentao_status=? WHERE id=?', [$zentaoStatus, $rec['id']]);
            }
            echo json_encode(['has_changes' => $hasChanges || (bool)$rec['zentao_has_changes']]);
        }
        exit;
    }

    // ── API: Bulk check all linked entries ────────────────────────
    public static function bulkCheck(): void
    {
        Auth::require();
        session_write_close(); // release session lock before slow external API calls
        header('Content-Type: application/json');

        $force     = !empty($_POST['force']);
        $lastCheck = appSetting('zentao_last_bulk_check');
        if (!$force && $lastCheck && (time() - strtotime($lastCheck)) < 900) {
            echo json_encode(['skipped' => true]); exit;
        }
        Database::execute(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES ('zentao_last_bulk_check', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
            [date('Y-m-d H:i:s')]
        );
        $changed = ZentaoController::bulkCheckChanges();
        echo json_encode(['ok' => true, 'changed' => $changed]);
        exit;
    }

    // ── Review: show diff between entry and Zentao bug ───────────
    public static function reviewEntry(string $id): void
    {
        Auth::require();
        session_write_close(); // release session lock before slow external API calls
        $entry = ZentaoController::fetchEntry((int)$id);
        if (!$entry || !$entry['zentao_bug_id']) abort(404);

        $state = ZentaoController::fetchBugState((int)$entry['zentao_bug_id']);

        // Build field diff
        $fieldDiff = [];
        if (!($state['error'] ?? null)) {
            $localStatus = $entry['status'] ?? '';
            $zentaoStatus = $state['status'];
            $allowedStatuses = ZentaoController::allowedLocalStatuses($zentaoStatus);
            $zentaoStatusLocal = $allowedStatuses[0];
            $fieldDiff[] = [
                'label'   => 'Status',
                'key'     => 'status',
                'local'   => $localStatus,
                'zentao'  => "$zentaoStatus (→ $zentaoStatusLocal)",
                'changed' => !ZentaoController::zentaoStatusMatchesLocal($zentaoStatus, $localStatus),
                'options' => $allowedStatuses,
            ];
            // Priority: compare mapped entry priority → expected Zentao pri vs actual
            $settings       = ZentaoController::settings();
            [$expPri, ]     = ZentaoController::mapEntryPriAndSeverity(['priority' => $entry['priority']??'Medium'], $settings);
            $actualPri      = (int)($state['pri'] ?? 0);
            $priDiffer      = $actualPri > 0 && $expPri !== $actualPri;
            $priColors      = [1=>'Highest',2=>'High',3=>'Medium',4=>'Low'];
            $fieldDiff[] = [
                'label'   => 'Priority',
                'key'     => 'priority',
                'local'   => ($entry['priority'] ?? 'Medium') . ' (→ Zentao ' . $expPri . ')',
                'zentao'  => 'Zentao ' . $actualPri . ' (' . ($priColors[$actualPri] ?? '?') . ')',
                'changed' => $priDiffer,
            ];
        }

        $anyChange = !empty(array_filter($fieldDiff, fn($f) => $f['changed']));

        $descLocal   = trim($entry['description'] ?? '');
        // Strip template header lines (*Field:* Value) from Zentao steps before comparing
        $rawSteps    = $state['steps'] ?? '';
        $descZentao  = self::extractFreeText($rawSteps);
        $descChanged = !($state['error'] ?? null) && self::normalizeText($descZentao) !== self::normalizeText($descLocal);

        View::render('zentao-sync/review', [
            'title'       => 'Zentao Changes — Entry #' . $id,
            'entry'       => $entry,
            'sourceId'    => (int)$id,
            'state'       => $state,
            'fieldDiff'   => $fieldDiff,
            'existingActionIds' => array_column(
                Database::fetchAll("SELECT jira_comment_id FROM jira_comments WHERE source_type='zentao_bug' AND source_id=?", [(int)$id]),
                'jira_comment_id'
            ),
            'anyChange'   => $anyChange || $descChanged,
            'descLocal'   => $descLocal,
            'descZentao'  => $descZentao,
            'descChanged' => $descChanged,
            'backUrl'     => url('entries/' . $id),
            'acceptUrl'   => url('zentao-sync/entry/' . $id . '/accept'),
        ]);
    }

    // ── Accept: import Zentao changes into entry ──────────────────
    public static function acceptEntry(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        session_write_close(); // release session lock before slow external API calls
        $entry = Database::fetchOne('SELECT * FROM entries WHERE id=?', [(int)$id]);
        if (!$entry || !$entry['zentao_bug_id']) abort(404);

        $state = ZentaoController::fetchBugState((int)$entry['zentao_bug_id']);
        if ($state['error'] ?? null) {
            flash('error', 'Zentao error: ' . $state['error']);
            redirect('/entries/' . $id);
        }

        // Accept status — if the admin configured multiple allowed RoboDoc statuses for
        // this Zentao status, use the one the user picked (validated against that list);
        // otherwise fall back to the single configured/default mapping.
        if (!empty($_POST['accept_status'])) {
            $allowed = ZentaoController::allowedLocalStatuses($state['status']);
            $chosen  = (string)($_POST['accepted_status'] ?? '');
            $mapped  = in_array($chosen, $allowed, true) ? $chosen : $allowed[0];
            Database::execute('UPDATE entries SET status=?, zentao_status=? WHERE id=?',
                [$mapped, $state['status'], (int)$id]);
        }
        // Accept priority — reverse-map Zentao pri to entry priority
        if (!empty($_POST['accept_priority']) && ($state['pri'] ?? 0)) {
            $priToEntry = [1=>'Highest',2=>'High',3=>'Medium',4=>'Low'];
            $entryPri   = $priToEntry[(int)$state['pri']] ?? 'Medium';
            Database::execute('UPDATE entries SET priority=?, zentao_pri=? WHERE id=?',
                [$entryPri, (int)$state['pri'], (int)$id]);
        }
        // Accept description
        if (!empty($_POST['accept_description']) && ($state['steps'] ?? '') !== '') {
            Database::execute('UPDATE entries SET description=? WHERE id=?', [$state['steps'], (int)$id]);
        }

        // Sync actions/comments
        foreach (($state['actions'] ?? []) as $a) {
            if (trim($a['comment'] ?? '') === '' && trim($a['action'] ?? '') === '') continue;
            $body = ($a['action'] ? '[' . $a['action'] . '] ' : '') . trim($a['comment'] ?? '');
            Database::execute(
                "INSERT INTO jira_comments (source_type, source_id, jira_comment_id, author_name, body, jira_created_at, jira_updated_at)
                 VALUES ('zentao_bug',?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE author_name=VALUES(author_name), body=VALUES(body), synced_at=NOW()",
                [(int)$id, $a['id'], $a['actor'] ?? '', $body, $a['date'] ?: null, $a['date'] ?: null]
            );
        }

        // Update hash and clear flag
        Database::execute(
            'UPDATE entries SET zentao_has_changes=0, zentao_synced_at=NOW(), zentao_bug_hash=? WHERE id=?',
            [$state['hash'], (int)$id]
        );

        flash('success', 'Zentao changes accepted.');
        redirect('/entries/' . $id);
    }

    // ── On-demand action/comment sync for entry detail page ──────
    public static function syncCommentsForEntry(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        session_write_close(); // release session lock before slow external API calls
        header('Content-Type: application/json');
        $entry = Database::fetchOne('SELECT zentao_bug_id FROM entries WHERE id=?', [(int)$id]);
        if (!$entry || !$entry['zentao_bug_id']) { echo json_encode(['error' => 'No Zentao bug linked']); exit; }
        $state = ZentaoController::fetchBugState((int)$entry['zentao_bug_id']);
        if ($state['error'] ?? null) { echo json_encode(['error' => $state['error']]); exit; }
        $count = 0;
        foreach (($state['actions'] ?? []) as $a) {
            if (trim($a['comment'] ?? '') === '' && trim($a['action'] ?? '') === '') continue;
            $body = ($a['action'] ? '[' . $a['action'] . '] ' : '') . trim($a['comment'] ?? '');
            Database::execute(
                "INSERT INTO jira_comments (source_type, source_id, jira_comment_id, author_name, body, jira_created_at, jira_updated_at)
                 VALUES ('zentao_bug',?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE author_name=VALUES(author_name), body=VALUES(body), synced_at=NOW()",
                [(int)$id, $a['id'], $a['actor'] ?? '', $body, $a['date'] ?: null, $a['date'] ?: null]
            );
            $count++;
        }
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    // ── Push: push local entry → Zentao ──────────────────────────
    public static function pushEntry(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        session_write_close(); // release session lock before slow external API calls
        $entry = ZentaoController::fetchEntry((int)$id);
        if (!$entry || !$entry['zentao_bug_id']) abort(404);

        $titleTpl = appSetting('zentao_title_template') ?: '{{title}}';
        $descTpl  = appSetting('zentao_desc_template')  ?: "*Type:* {{type}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*Date:* {{date}}\n\n{{description}}";

        $result = ZentaoController::buildAndPush((int)$id, $titleTpl, $descTpl);
        if (isset($result['error'])) {
            flash('error', 'Push to Zentao failed: ' . $result['error']);
        } else {
            flash('success', 'Entry pushed to Zentao successfully.');
        }
        redirect('/entries/' . $id);
    }

    // Strip *Field:* Value header lines from a Zentao steps/description field
    // so only the free-text portion is compared with the local description.
    private static function extractFreeText(string $text): string
    {
        $lines     = preg_split('/\r?\n/', $text);
        $freeLines = [];
        $inHeader  = true;
        foreach ($lines as $line) {
            if ($inHeader && preg_match('/^\*[^*:]+:\*\s*/', trim($line))) {
                continue;
            }
            $inHeader = false;
            $freeLines[] = $line;
        }
        return trim(implode("\n", $freeLines));
    }

    private static function normalizeText(string $s): string
    {
        $s = html_entity_decode($s, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
        $s = strip_tags($s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    // ── Unlinked Zentao bugs ──────────────────────────────────────
    public static function unlinkedBugs(): void
    {
        Auth::require();
        [$zentaoUrl, $token, $productId, $err] = ZentaoController::resolveConfig();

        $linked = array_column(
            Database::fetchAll("SELECT zentao_bug_id FROM entries WHERE zentao_bug_id IS NOT NULL"),
            'zentao_bug_id'
        );
        $linkedSet    = array_flip($linked);
        $dismissed    = Database::fetchAll('SELECT bug_id, dismissed_at FROM dismissed_zentao_bugs ORDER BY dismissed_at DESC');
        $dismissedSet = array_flip(array_column($dismissed, 'bug_id'));

        $bugs  = [];
        $error = $err;
        if (!$err && $productId) {
            $params = http_build_query(['limit' => 100, 'page' => 1]);
            [$code, $data] = ZentaoController::apiRequest('GET', "$zentaoUrl/api.php/v1/products/$productId/bugs?$params", $token, []);
            if ($code === 200) {
                $raw = $data['bugs'] ?? $data['items'] ?? [];
                if (is_array($raw)) {
                    $bugs = array_values(array_filter($raw, fn($b) =>
                        !isset($linkedSet[(int)($b['id'] ?? 0)]) && !isset($dismissedSet[(int)($b['id'] ?? 0)])
                    ));
                }
            } else {
                $error = ZentaoController::extractError($data, $code);
            }
        }

        $projects   = Database::fetchAll('SELECT id, name FROM projects ORDER BY name');
        $entryTypes = Database::fetchAll('SELECT id, name FROM entry_types ORDER BY sort_order, name');
        View::render('zentao-sync/unlinked', compact('bugs', 'dismissed', 'error', 'zentaoUrl', 'projects', 'entryTypes') + ['title' => 'Unlinked Zentao Bugs']);
    }

    // ── Dismiss / undismiss a Zentao bug from the unlinked list ──
    public static function dismissUnlinked(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $bugId = (int)($_POST['bug_id'] ?? 0);
        if (!$bugId) { json(['error' => 'Missing bug_id']); }
        Database::execute(
            'INSERT IGNORE INTO dismissed_zentao_bugs (bug_id, dismissed_by) VALUES (?,?)',
            [$bugId, Auth::id()]
        );
        json(['success' => true]);
    }

    public static function undismissUnlinked(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $bugId = (int)($_POST['bug_id'] ?? 0);
        if (!$bugId) { json(['error' => 'Missing bug_id']); }
        Database::execute('DELETE FROM dismissed_zentao_bugs WHERE bug_id=?', [$bugId]);
        json(['success' => true]);
    }

    // ── Link an existing entry to a Zentao bug (from unlinked page) ─
    public static function linkBugToEntry(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');
        $bugId   = (int)($_POST['bug_id'] ?? 0);
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if (!$bugId || !$entryId) { echo json_encode(['error' => 'Missing bug_id or entry_id']); exit; }

        $zentaoUrl = rtrim(appSetting('zentao_url', ''), '/');
        $bugUrl    = "$zentaoUrl/bug-view-$bugId.html";
        Database::execute(
            'UPDATE entries SET zentao_bug_id=?, zentao_bug_url=?, zentao_synced_at=NULL, zentao_has_changes=0 WHERE id=?',
            [$bugId, $bugUrl, $entryId]
        );
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Create a new entry from a Zentao bug ─────────────────────
    public static function createEntryFromBug(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $bugId     = (int)($_POST['bug_id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0);
        $typeId    = (int)($_POST['entry_type_id'] ?? 0);
        if (!$bugId || !$projectId || !$typeId) {
            flash('error', 'Bug ID, project and entry type are required.');
            redirect('/zentao-unlinked');
        }

        $state   = ZentaoController::fetchBugState($bugId);
        $title   = trim($_POST['title'] ?? ($state['title'] ?? "Bug #$bugId"));
        $desc    = $state['steps'] ?? '';
        $zentUrl = rtrim(appSetting('zentao_url', ''), '/');
        $bugUrl  = "$zentUrl/bug-view-$bugId.html";

        // Map Zentao priority to entry priority
        $priMap     = ['1'=>'Highest','2'=>'High','3'=>'Medium','4'=>'Low'];
        $entryPri   = $priMap[(string)($state['pri'] ?? 3)] ?? 'Medium';
        $entryStatus = ZentaoController::mapZentaoStatusToLocal($state['status'] ?? '');

        $id = Database::insert(
            'INSERT INTO entries (project_id, entry_type_id, entry_date, entry_time, title, description, status, priority, zentao_bug_id, zentao_bug_url, zentao_status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$projectId, $typeId, date('Y-m-d'), date('H:i:s'), $title, $desc, $entryStatus, $entryPri, $bugId, $bugUrl, $state['status'] ?? '', Auth::id()]
        );

        flash('success', "Entry created and linked to Zentao Bug #$bugId.");
        redirect('/entries/' . $id);
    }

    // ── Bulk create entries from multiple unlinked Zentao bugs ────
    public static function bulkCreateFromBugs(): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $items     = (array)($_POST['items'] ?? []);   // [bugId => title]
        $projectId = (int)($_POST['project_id'] ?? 0);
        $typeId    = (int)($_POST['entry_type_id'] ?? 0);
        $prefix    = trim($_POST['title_prefix'] ?? '');

        if (!$items || !$projectId || !$typeId) {
            json(['error' => 'Items, project and type are required.'], 400);
        }

        $zentUrl = rtrim(appSetting('zentao_url', ''), '/');
        $created = 0;
        $errors  = [];

        foreach ($items as $bugId => $bugTitle) {
            $bugId = (int)$bugId;
            if (!$bugId) continue;
            try {
                $title  = ($prefix ? $prefix . ' ' : '') . trim($bugTitle ?: "Bug #$bugId");
                $bugUrl = "$zentUrl/bug-view-$bugId.html";
                Database::insert(
                    'INSERT INTO entries (project_id, entry_type_id, entry_date, entry_time, title, description, status, priority, zentao_bug_id, zentao_bug_url, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$projectId, $typeId, date('Y-m-d'), date('H:i:s'), $title, '', 'new', 'Medium', $bugId, $bugUrl, Auth::id()]
                );
                $created++;
            } catch (\Throwable) {
                $errors[] = $bugId;
            }
        }

        json(['success' => true, 'created' => $created, 'errors' => $errors]);
    }

    // ── Dismiss: clear change notification without importing ──────
    public static function dismissEntry(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        session_write_close(); // release session lock before slow external API calls
        $entry = Database::fetchOne('SELECT zentao_bug_id FROM entries WHERE id=?', [(int)$id]);
        if ($entry['zentao_bug_id'] ?? null) {
            $state = ZentaoController::fetchBugState((int)$entry['zentao_bug_id']);
            if (!($state['error'] ?? null)) {
                Database::execute('UPDATE entries SET zentao_has_changes=0, zentao_synced_at=NOW(), zentao_bug_hash=? WHERE id=?',
                    [$state['hash'], (int)$id]);
            } else {
                Database::execute('UPDATE entries SET zentao_has_changes=0, zentao_synced_at=NOW() WHERE id=?', [(int)$id]);
            }
        }
        flash('info', 'Zentao change notification dismissed.');
        redirect('/entries/' . $id);
    }
}
