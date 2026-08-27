<?php
declare(strict_types=1);

class SyncReviewController
{
    // ── Dashboard: all pending changes ────────────────────────
    public static function index(): void
    {
        Auth::requireAdmin();

        $jiraEntries = Database::fetchAll(
            "SELECT e.id, e.title, e.status, e.priority, e.entry_date,
                    e.jira_issue_key, e.jira_issue_url, e.jira_status, e.jira_priority, e.jira_synced_at,
                    p.name project_name, et.name type_name, et.color type_color
             FROM entries e
             LEFT JOIN projects p  ON p.id  = e.project_id
             LEFT JOIN entry_types et ON et.id = e.entry_type_id
             WHERE e.jira_has_changes = 1 AND e.jira_issue_key IS NOT NULL AND e.jira_issue_key != ''
             ORDER BY e.entry_date DESC"
        );

        $zentaoEntries = Database::fetchAll(
            "SELECT e.id, e.title, e.status, e.priority, e.entry_date,
                    e.zentao_bug_id, e.zentao_bug_url, e.zentao_status, e.zentao_pri, e.zentao_synced_at,
                    p.name project_name, et.name type_name, et.color type_color
             FROM entries e
             LEFT JOIN projects p  ON p.id  = e.project_id
             LEFT JOIN entry_types et ON et.id = e.entry_type_id
             WHERE e.zentao_has_changes = 1 AND e.zentao_bug_id IS NOT NULL
             ORDER BY e.entry_date DESC"
        );

        // Build diff summary per entry (using stored values — no API call)
        $jiraDiffs    = [];
        $priColors    = ['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];
        foreach ($jiraEntries as $e) {
            $diffs = [];
            if ($e['jira_status']) {
                $mapped = JiraController::mapJiraStatusToLocal($e['jira_status']);
                if ($mapped !== ($e['status'] ?? '')) {
                    $diffs[] = ['field'=>'Status',
                        'local'  => entryStatuses()[$e['status']??'new'] ?? $e['status'],
                        'remote' => $e['jira_status'] . ' → ' . (entryStatuses()[$mapped] ?? $mapped),
                        'accept_value' => $mapped,
                    ];
                }
            }
            if ($e['jira_priority'] && !JiraController::jiraPriorityMatchesLocal($e['jira_priority'], $e['priority'] ?? '')) {
                $mappedPri = JiraController::mapJiraPriorityToLocal($e['jira_priority']);
                $diffs[] = ['field'=>'Priority', 'local'=>$e['priority'], 'remote'=>$e['jira_priority'] . ' → ' . $mappedPri,
                    'accept_value' => $mappedPri,
                ];
            }
            $jiraDiffs[$e['id']] = $diffs;
        }

        $zentaoDiffs     = [];
        $zentaoSettings  = ZentaoController::settings();
        $zentaoPriLabels = [1=>'Highest',2=>'High',3=>'Medium',4=>'Low'];
        $zentaoPriToEntry= [1=>'Highest',2=>'High',3=>'Medium',4=>'Low'];
        foreach ($zentaoEntries as $e) {
            $diffs = [];
            if ($e['zentao_status']) {
                $mapped = ZentaoController::mapZentaoStatusToLocal($e['zentao_status']);
                if ($mapped !== ($e['status'] ?? '')) {
                    $diffs[] = ['field'=>'Status',
                        'local'  => entryStatuses()[$e['status']??'new'] ?? $e['status'],
                        'remote' => $e['zentao_status'] . ' → ' . (entryStatuses()[$mapped] ?? $mapped),
                        'accept_value' => $mapped,
                    ];
                }
            }
            if ($e['zentao_pri']) {
                [$expPri] = ZentaoController::mapEntryPriAndSeverity(['priority' => $e['priority']??'Medium'], $zentaoSettings);
                if ((int)$e['zentao_pri'] !== $expPri) {
                    $remoteLabel = ($zentaoPriLabels[$e['zentao_pri']] ?? '?') . ' (Zentao pri ' . $e['zentao_pri'] . ')';
                    $diffs[] = ['field'=>'Priority', 'local'=>$e['priority'],
                        'remote'       => $remoteLabel,
                        'accept_value' => $zentaoPriToEntry[$e['zentao_pri']] ?? null,
                    ];
                }
            }
            $zentaoDiffs[$e['id']] = $diffs;
        }

        // -- Live-Sync: diffs come from the snapshot saved by the last manual
        // "Auf Änderungen prüfen" check (no API call at render time, like the two above).
        $liveSyncEntries = Database::fetchAll(
            "SELECT e.id, e.title, e.status, e.priority, e.entry_date, e.live_origin_id,
                    e.live_sync_remote_snapshot, e.live_sync_checked_at,
                    p.name project_name, et.name type_name, et.color type_color
             FROM entries e
             LEFT JOIN projects p  ON p.id  = e.project_id
             LEFT JOIN entry_types et ON et.id = e.entry_type_id
             WHERE e.live_sync_has_changes = 1 AND e.live_origin_id IS NOT NULL
             ORDER BY e.entry_date DESC"
        );
        $liveSyncDiffs = [];
        foreach ($liveSyncEntries as $e) {
            $snapshot = json_decode($e['live_sync_remote_snapshot'] ?? '', true) ?: [];
            $remote   = $snapshot['fields'] ?? [];
            $diffs    = [];
            foreach (LiveSyncController::TRACKED_FIELDS as $key => $label) {
                $localVal  = (string)($e[$key] ?? '');
                $remoteVal = (string)($remote[$key] ?? '');
                if ($remoteVal !== '' && $localVal !== $remoteVal) {
                    $diffs[] = ['field' => $key, 'label' => $label, 'local' => $localVal, 'remote' => $remoteVal];
                }
            }
            $liveSyncDiffs[$e['id']] = ['diffs' => $diffs, 'new_attachments' => $snapshot['new_attachments'] ?? []];
        }

        View::render('admin/sync-review', compact(
            'jiraEntries','zentaoEntries','jiraDiffs','zentaoDiffs','liveSyncEntries','liveSyncDiffs'
        ) + ['title' => 'Sync Review']);
    }

    // ── Per-entry AJAX actions ────────────────────────────────
    public static function entryAction(string $source, string $id, string $action): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        header('Content-Type: application/json');

        $entryId = (int)$id;
        $entry   = Database::fetchOne('SELECT * FROM entries WHERE id=?', [$entryId]);
        if (!$entry) { echo json_encode(['error' => 'Entry not found']); exit; }

        if ($source === 'jira') {
            self::handleJiraAction($entry, $action);
        } elseif ($source === 'zentao') {
            self::handleZentaoAction($entry, $action);
        } elseif ($source === 'livesync') {
            self::handleLiveSyncAction($entry, $action);
        } else {
            echo json_encode(['error' => 'Unknown source']);
        }
        exit;
    }

    private static function handleJiraAction(array $entry, string $action): void
    {
        $id = (int)$entry['id'];
        switch ($action) {
            case 'accept':
                // Apply stored Jira values — no API call needed
                $sets = []; $binds = [];
                if ($entry['jira_status']) {
                    $mapped = JiraController::mapJiraStatusToLocal($entry['jira_status']);
                    $sets[]  = 'status=?';    $binds[] = $mapped;
                    $sets[]  = 'jira_status=?'; $binds[] = $entry['jira_status'];
                }
                if ($entry['jira_priority']) {
                    $sets[]  = 'priority=?';    $binds[] = JiraController::mapJiraPriorityToLocal($entry['jira_priority']);
                }
                $sets[]  = 'jira_has_changes=0'; $sets[] = 'jira_synced_at=NOW()';
                Database::execute('UPDATE entries SET ' . implode(',', $sets) . ' WHERE id=?', array_merge($binds, [$id]));
                echo json_encode(['success' => true, 'action' => 'accepted']);
                break;

            case 'push':
                $user     = Database::fetchOne('SELECT jira_title_template, jira_desc_template FROM users WHERE id=?', [Auth::id()]);
                $titleTpl = ($user['jira_title_template'] ?? '') ?: (appSetting('jira_default_title_template') ?: '[{{type}}] {{title}}');
                $descTpl  = ($user['jira_desc_template']  ?? '') ?: (appSetting('jira_default_desc_template')  ?: '');
                $result   = JiraController::buildAndPushEntry($id, $titleTpl, $descTpl);
                echo json_encode($result + ['action' => 'pushed']);
                break;

            case 'dismiss':
                Database::execute('UPDATE entries SET jira_has_changes=0, jira_synced_at=NOW() WHERE id=?', [$id]);
                echo json_encode(['success' => true, 'action' => 'dismissed']);
                break;

            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    }

    private static function handleZentaoAction(array $entry, string $action): void
    {
        $id = (int)$entry['id'];
        switch ($action) {
            case 'accept':
                $sets = []; $binds = [];
                if ($entry['zentao_status']) {
                    $mapped = ZentaoController::mapZentaoStatusToLocal($entry['zentao_status']);
                    $sets[] = 'status=?';        $binds[] = $mapped;
                    $sets[] = 'zentao_status=?'; $binds[] = $entry['zentao_status'];
                }
                if ($entry['zentao_pri']) {
                    $priMap = [1=>'Highest',2=>'High',3=>'Medium',4=>'Low'];
                    $pri    = $priMap[(int)$entry['zentao_pri']] ?? null;
                    if ($pri) { $sets[] = 'priority=?'; $binds[] = $pri; }
                }
                $sets[] = 'zentao_has_changes=0'; $sets[] = 'zentao_synced_at=NOW()';
                Database::execute('UPDATE entries SET ' . implode(',', $sets) . ' WHERE id=?', array_merge($binds, [$id]));
                echo json_encode(['success' => true, 'action' => 'accepted']);
                break;

            case 'push':
                $titleTpl = appSetting('zentao_title_template') ?: '{{title}}';
                $descTpl  = appSetting('zentao_desc_template')  ?: '';
                $result   = ZentaoController::buildAndPush($id, $titleTpl, $descTpl);
                echo json_encode($result + ['action' => 'pushed']);
                break;

            case 'dismiss':
                $state = ZentaoController::fetchBugState((int)$entry['zentao_bug_id']);
                $hash  = $state['hash'] ?? null;
                Database::execute('UPDATE entries SET zentao_has_changes=0, zentao_synced_at=NOW()'
                    . ($hash ? ', zentao_bug_hash=?' : '') . ' WHERE id=?',
                    $hash ? [$hash, $id] : [$id]);
                echo json_encode(['success' => true, 'action' => 'dismissed']);
                break;

            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    }

    // -- Live-Sync review actions. Unlike Jira/Zentao's all-or-nothing accept,
    // 'accept' applies only the checkboxes the admin actually selected (fields[]
    // in the POST body, plus import_attachments=1) - the source may have several
    // independent changes and not all of them are necessarily wanted locally.
    private static function handleLiveSyncAction(array $entry, string $action): void
    {
        $id = (int)$entry['id'];
        switch ($action) {
            case 'accept':
                $fields = array_values(array_intersect(
                    (array)($_POST['fields'] ?? []),
                    array_keys(LiveSyncController::TRACKED_FIELDS)
                ));
                $result = LiveSyncController::applyRemoteChanges($id, $fields, !empty($_POST['import_attachments']));
                echo json_encode($result + ['action' => 'accepted']);
                break;

            case 'dismiss':
                Database::execute('UPDATE entries SET live_sync_has_changes=0 WHERE id=?', [$id]);
                echo json_encode(['success' => true, 'action' => 'dismissed']);
                break;

            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    }

    // ── Bulk actions ──────────────────────────────────────────
    public static function bulkAction(string $source, string $action): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        header('Content-Type: application/json');

        $col = match ($source) {
            'jira'     => 'jira_has_changes',
            'zentao'   => 'zentao_has_changes',
            'livesync' => 'live_sync_has_changes',
            default    => null,
        };
        if (!$col) { echo json_encode(['error' => 'Unknown source']); exit; }
        $entries = Database::fetchAll(
            "SELECT * FROM entries WHERE $col = 1 AND " . match ($source) {
                'jira'     => "jira_issue_key IS NOT NULL AND jira_issue_key != ''",
                'zentao'   => 'zentao_bug_id IS NOT NULL',
                'livesync' => 'live_origin_id IS NOT NULL',
            }
        );

        // Bulk "accept" for Live-Sync has no per-entry checkbox selection to read -
        // apply every tracked field plus attachments, same as Jira/Zentao's
        // all-or-nothing accept.
        if ($source === 'livesync' && $action === 'accept') {
            $_POST['fields']             = array_keys(LiveSyncController::TRACKED_FIELDS);
            $_POST['import_attachments'] = '1';
        }

        $processed = 0; $errors = [];
        foreach ($entries as $entry) {
            ob_start();
            try {
                if ($source === 'jira') self::handleJiraAction($entry, $action);
                elseif ($source === 'zentao') self::handleZentaoAction($entry, $action);
                else self::handleLiveSyncAction($entry, $action);
                $res = json_decode(ob_get_clean(), true);
                // A successful "push" doesn't set 'success' (only 'accept'/'dismiss' do) — treat
                // "no error key" as success for all actions, matching what entryAction() does.
                if (!empty($res['error'])) {
                    $errors[] = '#' . $entry['id'] . ': ' . $res['error'];
                    continue;
                }
                $processed++;
                // On push, Jira may have rejected priority/status without that being a hard
                // error — surface it so a bulk push doesn't look fully successful when it isn't.
                if ($action === 'push') {
                    $notes = [];
                    if (!empty($res['transition']) && !str_starts_with($res['transition'], 'transitioned')) $notes[] = 'status: ' . $res['transition'];
                    if (!empty($res['priority'])   && !str_starts_with($res['priority'], 'set to'))         $notes[] = 'priority: ' . $res['priority'];
                    if ($notes) $errors[] = '#' . $entry['id'] . ' pushed, but ' . implode('; ', $notes);
                }
            } catch (\Throwable $e) {
                ob_end_clean();
                $errors[] = '#' . $entry['id'] . ': ' . $e->getMessage();
            }
        }

        echo json_encode(['success' => true, 'processed' => $processed, 'errors' => $errors]);
        exit;
    }
}
