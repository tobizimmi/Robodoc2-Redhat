<?php
declare(strict_types=1);

class ZentaoController
{
    // -- Create Zentao bug from entry ------------------------------
    public static function create(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        session_write_close();
        header('Content-Type: application/json');

        $entry = self::fetchEntry((int)$id);
        if (!$entry) { http_response_code(404); echo json_encode(['error' => 'Entry not found']); return; }

        [$zentaoUrl, $token, $productId, $err] = self::resolveConfig();
        if ($err) { http_response_code(400); echo json_encode(['error' => $err]); return; }

        $settings  = self::settings();
        $titleTpl  = ($_POST['title_template'] ?? '')
            ?: (appSetting('zentao_title_template') ?: '{{title}}');
        $descTpl   = ($_POST['description_template'] ?? '')
            ?: (appSetting('zentao_desc_template') ?: "*Type:* {{type}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*Date:* {{date}}\n\n{{description}}");
        $issueType = trim($_POST['issue_type'] ?? '') ?: (appSetting('zentao_default_type') ?: 'codeerror');
        [$pri, $severity] = self::mapEntryPriAndSeverity($entry, $settings);
        $pri      = max(1, min(4, (int)($_POST['pri'] ?? $pri)));
        $prodId   = (int)($_POST['product_id'] ?? $productId);
        if (!$prodId) { http_response_code(400); echo json_encode(['error' => 'Zentao product ID not configured. Set it in Admin ? Zentao Settings.']); return; }

        $body = [
            'product'     => $prodId,
            'title'       => self::applyVars($titleTpl, $entry),
            'steps'       => self::textToHtml(self::applyVars($descTpl, $entry)),
            'pri'         => $pri,
            'severity'    => $severity,
            'type'        => $issueType,
            'status'      => 'active',
            'openedBuild' => 'trunk',
        ];

        [$code, $data] = self::apiRequest('POST', "$zentaoUrl/api.php/v1/bugs", $token, $body);
        $bug   = $data['data'] ?? $data;
        $bugId = (int)($bug['id'] ?? 0);

        if (!$bugId || $code >= 300) {
            echo json_encode(['error' => self::extractError($data, $code)]); return;
        }

        $bugUrl = "$zentaoUrl/bug-view-$bugId.html";
        $hash   = self::hashBug($bug);
        Database::execute(
            'UPDATE entries SET zentao_bug_id=?, zentao_bug_url=?, zentao_synced_at=NOW(), zentao_has_changes=0, zentao_status=?, zentao_bug_hash=? WHERE id=?',
            [$bugId, $bugUrl, $bug['status'] ?? 'active', $hash, (int)$id]
        );

        echo json_encode(['success' => true, 'bug_id' => $bugId, 'url' => $bugUrl]);
    }

    // -- Update existing linked Zentao bug -------------------------
    public static function update(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        session_write_close();
        header('Content-Type: application/json');

        $entry = self::fetchEntry((int)$id);
        if (!$entry) { http_response_code(404); echo json_encode(['error' => 'Entry not found']); return; }
        if (!$entry['zentao_bug_id']) { http_response_code(400); echo json_encode(['error' => 'No Zentao bug linked.']); return; }

        $titleTpl = ($_POST['title_template'] ?? '') ?: (appSetting('zentao_title_template') ?: '{{title}}');
        $descTpl  = ($_POST['description_template'] ?? '') ?: (appSetting('zentao_desc_template') ?: "*Type:* {{type}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*Date:* {{date}}\n\n{{description}}");

        $result = self::buildAndPush((int)$id, $titleTpl, $descTpl);
        if (isset($result['error'])) { echo json_encode(['error' => $result['error']]); return; }
        echo json_encode(['success' => true] + $result);
    }

    // -- Internal: push entry to linked bug -----------------------
    public static function buildAndPush(int $id, string $titleTpl, string $descTpl): array
    {
        $entry = self::fetchEntry($id);
        if (!$entry)               return ['error' => 'Entry not found.'];
        if (!$entry['zentao_bug_id']) return ['error' => 'No Zentao bug linked.'];

        [$zentaoUrl, $token, , $err] = self::resolveConfig();
        if ($err) return ['error' => $err];

        $bugId = (int)$entry['zentao_bug_id'];
        $s     = self::settings();

        [$pri, $severity] = self::mapEntryPriAndSeverity($entry, $s);
        $body = [
            'title'    => self::applyVars($titleTpl, $entry),
            'steps'    => self::textToHtml(self::applyVars($descTpl, $entry)),
            'pri'      => $pri,
            'severity' => $severity,
        ];

        [$code, $data] = self::apiRequest('PUT', "$zentaoUrl/api.php/v1/bugs/$bugId", $token, $body);
        // $code === 0 means curl never got a response at all (connection/DNS/TLS failure) —
        // must be treated as an error too, not just HTTP status codes >= 300, otherwise a total
        // connection failure silently falls through as if the push had succeeded.
        if ($code === 0 || $code >= 300) return ['error' => self::extractError($data, $code)];

        // Re-fetch bug to store accurate hash/status
        [, $bugData] = self::apiRequest('GET', "$zentaoUrl/api.php/v1/bugs/$bugId", $token, []);
        $bug  = $bugData['data'] ?? $bugData;
        $hash = self::hashBug(is_array($bug) ? $bug : []);

        Database::execute(
            'UPDATE entries SET zentao_synced_at=NOW(), zentao_has_changes=0, zentao_status=?, zentao_bug_hash=? WHERE id=?',
            [$bug['status'] ?? '', $hash, $id]
        );

        return ['bug_id' => $bugId, 'url' => "$zentaoUrl/bug-view-$bugId.html"];
    }

    // -- Fetch current Zentao bug state ----------------------------
    public static function fetchBugState(int $bugId): array
    {
        [$zentaoUrl, $token, , $err] = self::resolveConfig();
        if ($err) return ['error' => $err];

        [$code, $data] = self::apiRequest('GET', "$zentaoUrl/api.php/v1/bugs/$bugId", $token, []);
        if ($code !== 200) return ['error' => self::extractError($data, $code)];

        $bug = $data['data'] ?? $data;
        if (!is_array($bug) || !($bug['id'] ?? null)) return ['error' => "Bug #$bugId not found in Zentao."];

        $assignedTo = $bug['assignedTo'] ?? '';
        if (is_array($assignedTo)) $assignedTo = $assignedTo['account'] ?? $assignedTo['realname'] ?? '';

        // Fetch actions/comments - check main bug response first, then dedicated endpoints
        $actions = [];
        $parseActions = function(array $rawList) use (&$actions): bool {
            if (!$rawList) return false;
            foreach ($rawList as $a) {
                if (!is_array($a)) continue;
                $actions[] = [
                    'id'      => (string)($a['id'] ?? uniqid()),
                    'actor'   => is_array($a['actor'] ?? null) ? ($a['actor']['realname'] ?? $a['actor']['account'] ?? '') : (string)($a['actor'] ?? $a['account'] ?? ''),
                    'date'    => str_replace('T', ' ', substr($a['date'] ?? $a['createdDate'] ?? $a['created'] ?? '', 0, 19)),
                    'action'  => $a['action'] ?? $a['type'] ?? $a['verb'] ?? '',
                    'comment' => trim($a['comment'] ?? $a['text'] ?? $a['content'] ?? ''),
                ];
            }
            return true;
        };

        // Check if actions are embedded in the bug response
        $embeddedActions = $bug['actions'] ?? $bug['history'] ?? $bug['comments'] ?? [];
        if (!$parseActions(is_array($embeddedActions) ? $embeddedActions : [])) {
            // Try dedicated endpoints
            foreach (["$zentaoUrl/api.php/v1/bugs/{$bug['id']}/actions",
                      "$zentaoUrl/api.php/v1/actions?objectType=bug&objectID={$bug['id']}&limit=100",
                      "$zentaoUrl/api.php/v1/bugs/{$bug['id']}/comments"] as $actUrl) {
                [$actCode, $actData] = self::apiRequest('GET', $actUrl, $token, []);
                if ($actCode === 200) {
                    $raw = $actData['actions'] ?? $actData['comments'] ?? $actData['items'] ?? $actData ?? [];
                    if ($parseActions(is_array($raw) ? $raw : [])) break;
                }
            }
        }

        // Legacy loop kept for compatibility - will be unreachable if above succeeded
        foreach ([] as $actUrl) {
            [$actCode, $actData] = self::apiRequest('GET', $actUrl, $token, []);
            if ($actCode === 200) {
                $raw = $actData['actions'] ?? $actData['items'] ?? $actData ?? [];
                if (is_array($raw) && $raw) {
                    foreach ($raw as $a) {
                        $comment = trim($a['comment'] ?? $a['text'] ?? '');
                        $actions[] = [
                            'id'      => (string)($a['id'] ?? uniqid()),
                            'actor'   => is_array($a['actor'] ?? null) ? ($a['actor']['realname'] ?? $a['actor']['account'] ?? '') : (string)($a['actor'] ?? ''),
                            'date'    => str_replace('T', ' ', substr($a['date'] ?? $a['createdDate'] ?? '', 0, 19)),
                            'action'  => $a['action'] ?? $a['type'] ?? '',
                            'comment' => $comment,
                        ];
                    }
                    break;
                }
            }
        }

        return [
            'id'         => (int)$bug['id'],
            'title'      => $bug['title'] ?? '',
            'steps'      => self::htmlToText($bug['steps'] ?? $bug['desc'] ?? ''),
            'status'     => $bug['status'] ?? '',
            'pri'        => (int)($bug['pri'] ?? 3),
            'severity'   => (int)($bug['severity'] ?? 3),
            'assignedTo' => (string)$assignedTo,
            'hash'       => self::hashBug($bug),
            'zentao_url' => $zentaoUrl,
            'bug_url'    => "$zentaoUrl/bug-view-{$bug['id']}.html",
            'actions'    => $actions,
            'error'      => null,
        ];
    }

    // -- Sync Zentao status ? local entry status -------------------
    public static function syncStatus(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        session_write_close();

        $entry = Database::fetchOne('SELECT id, zentao_bug_id FROM entries WHERE id=?', [(int)$id]);
        if (!$entry || !$entry['zentao_bug_id']) { json(['error' => 'No Zentao bug linked.']); }

        $state = self::fetchBugState((int)$entry['zentao_bug_id']);
        if ($state['error'] ?? null) { json(['error' => $state['error']]); }

        $syncMode    = $_POST['sync_mode'] ?? 'quick';
        $checkFields = json_decode(appSetting($syncMode === 'full' ? 'zentao_full_sync_fields' : 'zentao_quick_sync_fields',
                           '["status","priority"]'), true) ?: ['status','priority'];

        $entry = Database::fetchOne('SELECT status, priority FROM entries WHERE id=?', [(int)$id]);
        $localStatus   = $entry['status']   ?? '';
        $localPriority = $entry['priority'] ?? 'Medium';
        $mappedStatus  = self::mapZentaoStatusToLocal($state['status']);

        $zentaoPri    = (int)($state['pri'] ?? 0);
        $s            = self::settings();
        [$expPri, ]   = self::mapEntryPriAndSeverity(['priority' => $localPriority], $s);

        $statusDiffers   = in_array('status',   $checkFields) && !self::zentaoStatusMatchesLocal($state['status'], $localStatus);
        $priorityDiffers = in_array('priority', $checkFields) && $zentaoPri > 0 && $zentaoPri !== $expPri;
        $anyDiffers      = $statusDiffers || $priorityDiffers;

        Database::execute(
            'UPDATE entries SET zentao_status=?, zentao_pri=?, zentao_synced_at=NOW(), zentao_has_changes=? WHERE id=?',
            [$state['status'], $zentaoPri ?: null, $anyDiffers ? 1 : 0, (int)$id]
        );

        json(['success'          => true,
              'zentao_status'    => $state['status'], 'mapped'         => $mappedStatus, 'local_status'   => $localStatus,
              'zentao_pri'       => $zentaoPri,        'local_priority' => $localPriority,
              'status_differs'   => $statusDiffers,    'priority_differs' => $priorityDiffers,
              'differs'          => $anyDiffers]);
    }

    // -- Bulk check linked entries for Zentao changes --------------
    public static function bulkCheckChanges(): int
    {
        [$zentaoUrl, $token, , $err] = self::resolveConfig();
        if ($err) return 0;

        // -- Pre-check: detect status mismatches using stored zentao_status --
        $changed = 0;
        $statusMismatches = Database::fetchAll(
            "SELECT id, zentao_status, status FROM entries
             WHERE zentao_bug_id IS NOT NULL AND zentao_has_changes=0
             AND zentao_status IS NOT NULL AND zentao_status != ''",
        );
        foreach ($statusMismatches as $sm) {
            if (!self::zentaoStatusMatchesLocal($sm['zentao_status'], $sm['status'] ?? '')) {
                Database::execute('UPDATE entries SET zentao_has_changes=1 WHERE id=?', [$sm['id']]);
                $changed++;
            }
        }

        $entries = Database::fetchAll(
            "SELECT id, zentao_bug_id, zentao_bug_hash, zentao_synced_at, status
             FROM entries
             WHERE zentao_bug_id IS NOT NULL AND zentao_has_changes=0
             ORDER BY zentao_synced_at ASC LIMIT 30"
        );
        foreach ($entries as $e) {
            try {
                [$code, $data] = self::apiRequest('GET', "$zentaoUrl/api.php/v1/bugs/{$e['zentao_bug_id']}", $token, []);
                if ($code !== 200) continue;
                $bug  = $data['data'] ?? $data;
                if (!is_array($bug)) continue;
                $hash = self::hashBug($bug);

                // Only flag for real content differences - status or priority.
                // The hash includes `steps` (description) which can differ due to minor
                // Zentao formatting changes even when content is the same. Using hash alone
                // causes false-positive "changes detected" messages.
                $zentaoStatus  = $bug['status'] ?? '';
                $mappedStatus  = self::mapZentaoStatusToLocal($zentaoStatus);
                $statusDiffers = $zentaoStatus !== '' && !self::zentaoStatusMatchesLocal($zentaoStatus, $e['status'] ?? '');
                $hasContentChange = $statusDiffers;  // extend with priority if needed

                // Always update hash + status + synced_at to keep the baseline current
                $sql = 'UPDATE entries SET zentao_synced_at=NOW(), zentao_bug_hash=?, zentao_status=?, '
                     . ($hasContentChange ? 'zentao_has_changes=1' : 'zentao_has_changes=0')
                     . ' WHERE id=?';
                Database::execute($sql, [$hash, $zentaoStatus, $e['id']]);
                if ($hasContentChange) $changed++;
            } catch (\Throwable) {
                continue;
            }
        }
        return $changed;
    }

    // -- Shared helpers --------------------------------------------

    public static function resolveConfig(): array
    {
        $zentaoUrl = rtrim(appSetting('zentao_url'), '/');
        $token     = trim(appSetting('zentao_token'));
        $productId = (int)appSetting('zentao_default_product');

        if (!$zentaoUrl || !$token) {
            $missing = array_filter(['Zentao URL' => !$zentaoUrl, 'API Token' => !$token]);
            return [null, null, null, 'Zentao not configured. Missing: ' . implode(', ', array_keys($missing))];
        }
        return [$zentaoUrl, $token, $productId, null];
    }

    public static function settings(): array
    {
        return array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
    }

    public static function fetchEntry(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT e.*, et.name type_name, p.name project_name,
                    ec.name cat_name, env.name env_name, ta.name test_area_name, u.name creator_name
             FROM entries e
             LEFT JOIN entry_types       et  ON et.id  = e.entry_type_id
             LEFT JOIN projects          p   ON p.id   = e.project_id
             LEFT JOIN error_categories  ec  ON ec.id  = e.error_category_id
             LEFT JOIN test_environments env ON env.id = e.environment_id
             LEFT JOIN test_areas        ta  ON ta.id  = e.test_area_id
             LEFT JOIN users             u   ON u.id   = e.created_by
             WHERE e.id = ?",
            [$id]
        ) ?: null;
    }

    // Wrap each line of plain text in its own <p> before sending to Zentao's
    // "steps" field. A flat run of bare <br> tags with no paragraph structure
    // tends to get mangled by Zentao's own rich-text editor the next time
    // someone opens and re-saves the bug directly in Zentao — proper <p>
    // boundaries survive that round trip far more reliably.
    private static function textToHtml(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        return implode('', array_map(
            fn($l) => '<p>' . ($l === '' ? '&nbsp;' : $l) . '</p>',
            $lines
        ));
    }

    // Zentao's "steps" field is a rich-text/HTML field in its UI. Reading it
    // back with a plain strip_tags() silently drops <br>/paragraph breaks
    // (bare \n means nothing in HTML), which is why line breaks entered or
    // edited in Zentao used to vanish once pulled back into RoboDoc2 (review
    // page, "accept description", change hashing). Convert line-break-ish
    // tags to \n BEFORE stripping the rest, so plain-text formatting survives
    // the round trip.
    private static function htmlToText(string $html): string
    {
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $html);
        return trim(strip_tags($html));
    }

    public static function applyVars(string $tpl, array $entry): string
    {
        $attachmentsBlock = '';
        if (str_contains($tpl, '{{attachments}}') && !empty($entry['id'])) {
            // Prefer SharePoint links (accessible to Zentao users); fall back to local URLs
            $spFiles = Database::fetchAll(
                'SELECT filename, web_url FROM entry_sharepoint_files WHERE entry_id=? ORDER BY id',
                [(int)$entry['id']]
            );
            if ($spFiles) {
                $lines = [];
                foreach ($spFiles as $f) {
                    $lines[] = $f['filename'] . ': ' . $f['web_url'];
                }
                $attachmentsBlock = implode("\n", $lines);
            } else {
                $atts = Database::fetchAll(
                    'SELECT display_name, original_name, file_path FROM entry_attachments WHERE entry_id=? ORDER BY id',
                    [(int)$entry['id']]
                );
                if ($atts) {
                    $baseUrl = rtrim(appSetting('app_base_url') ?: (
                        (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '')
                    ), '/');
                    $lines = [];
                    foreach ($atts as $a) {
                        $name = $a['display_name'] ?: $a['original_name'];
                        $url  = $baseUrl . '/' . ltrim($a['file_path'], '/');
                        $lines[] = $name . ': ' . $url . ' (local - SharePoint not uploaded)';
                    }
                    $attachmentsBlock = implode("\n", $lines);
                } else {
                    $attachmentsBlock = '(no attachments)';
                }
            }
        }
        return strtr($tpl, [
            '{{id}}'             => (string)($entry['id'] ?? ''),
            '{{type}}'           => $entry['type_name'] ?? '',
            '{{title}}'          => $entry['title'] ?? '',
            '{{description}}'    => strip_tags($entry['description'] ?? ''),
            '{{serial}}'         => $entry['mower_serial'] ?? '',
            '{{firmware}}'       => $entry['firmware_version'] ?? '',
            '{{app_version}}'    => $entry['app_version'] ?? '',
            '{{project}}'        => $entry['project_name'] ?? '',
            '{{project_status}}' => $entry['project_status_robot'] ?? '',
            '{{date}}'           => $entry['entry_date'] ?? '',
            '{{time}}'           => substr($entry['entry_time'] ?? '', 0, 5),
            '{{category}}'       => $entry['cat_name'] ?? '',
            '{{environment}}'    => $entry['env_name'] ?? '',
            '{{test_area}}'      => $entry['test_area_name'] ?? '',
            '{{status}}'         => $entry['status'] ?? '',
            '{{creator}}'        => $entry['creator_name'] ?? '',
            '{{temperature}}'    => (string)($entry['temperature'] ?? ''),
            '{{weather}}'        => $entry['weather_condition'] ?? '',
            '{{jira_key}}'       => $entry['jira_issue_key'] ?? '',
            '{{sharepoint}}'     => $entry['sharepoint_folder_url'] ?? '',
            '{{attachments}}'    => $attachmentsBlock,
        ]);
    }

    public static function hashBug(array $bug): string
    {
        return md5(json_encode([
            'title'  => $bug['title'] ?? '',
            'status' => $bug['status'] ?? '',
            'pri'    => $bug['pri'] ?? '',
            'steps'  => self::htmlToText($bug['steps'] ?? $bug['desc'] ?? ''),
        ]));
    }

    public static function apiRequest(string $method, string $url, string $token, array $body): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Token: ' . $token];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $body ? $url . '?' . http_build_query($body) : $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, $method === 'POST' ? CURLOPT_POST : CURLOPT_CUSTOMREQUEST, $method === 'POST' ? true : $method);
            $payload = json_encode($body);
            if ($payload === false) {
                array_walk_recursive($body, fn(&$v) => is_string($v) ? $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8') : null);
                $payload = json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response  = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlErr   = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response ?: '{}', true);
        if ($decoded === null && $response) {
            $decoded = ['message' => substr(strip_tags((string)$response), 0, 250)];
        }
        // curl_exec() failing outright (DNS/connection/TLS/timeout) yields HTTP code 0 and no
        // response body — without this, the caller only ever sees the unhelpful "HTTP 0",
        // with no indication of what actually went wrong (host unreachable, timeout, bad cert, ...).
        if ($response === false && $curlErr) {
            $decoded = ['message' => "Connection to Zentao failed: $curlErr" . ($curlErrno ? " (curl errno $curlErrno)" : '')];
        }
        return [$httpCode, $decoded ?? []];
    }

    public static function extractError(?array $data, int $httpCode): string
    {
        if (is_array($data)) {
            if (!empty($data['message']) && is_string($data['message'])) return $data['message'];
            if (!empty($data['error'])   && is_string($data['error']))   return $data['error'];
        }
        return "HTTP $httpCode";
    }

    // Returns the primary (first) RoboDoc status for a given Zentao status.
    // The mapping value may be a string (legacy) or array (new multi-mapping).
    public static function mapZentaoStatusToLocal(string $status): string
    {
        $map = json_decode(appSetting('zentao_status_to_local') ?: '{}', true);
        if ($map && isset($map[$status])) {
            $val = $map[$status];
            return is_array($val) ? ($val[0] ?? 'new') : $val;
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($status)));
        $slug = trim($slug, '_');
        if (array_key_exists($slug, entryStatuses())) return $slug;
        return match($status) {
            'active'   => 'internal',
            'resolved' => 'ready_for_test',
            'closed'   => 'finished',
            default    => 'new',
        };
    }

    // Returns the full list of admin-configured RoboDoc statuses allowed for a given
    // Zentao status, in configured order (first = import default). Falls back to the
    // single default mapping when nothing is configured.
    public static function allowedLocalStatuses(string $zentaoStatus): array
    {
        $map = json_decode(appSetting('zentao_status_to_local') ?: '{}', true);
        if ($map && isset($map[$zentaoStatus])) {
            $val  = $map[$zentaoStatus];
            $list = array_values(array_filter(is_array($val) ? $val : [$val], fn($v) => is_string($v) && $v !== ''));
            if ($list) return $list;
        }
        return [self::mapZentaoStatusToLocal($zentaoStatus)];
    }

    // Returns true if $localStatus is one of the allowed RoboDoc statuses for $zentaoStatus.
    // Used for change detection: if the entry is already in an accepted status, don't flag as changed.
    public static function zentaoStatusMatchesLocal(string $zentaoStatus, string $localStatus): bool
    {
        $map = json_decode(appSetting('zentao_status_to_local') ?: '{}', true);
        if ($map && isset($map[$zentaoStatus])) {
            $val     = $map[$zentaoStatus];
            $allowed = is_array($val) ? $val : [$val];
            return in_array($localStatus, $allowed, true);
        }
        return self::mapZentaoStatusToLocal($zentaoStatus) === $localStatus;
    }

    // Returns [pri, severity] based on entry priority field + admin mapping
    public static function mapEntryPriAndSeverity(array $entry, array $settings): array
    {
        $map      = json_decode($settings['zentao_priority_map'] ?? '{}', true) ?: [];
        $priority = $entry['priority'] ?? 'Medium';
        $defaults = ['Low'=>[4,4],'Medium'=>[3,3],'High'=>[2,2],'Highest'=>[1,2],'Blocker'=>[1,1]];
        if (isset($map[$priority]) && is_array($map[$priority])) {
            $pri = max(1, min(4, (int)($map[$priority]['pri']      ?? 3)));
            $sev = max(1, min(4, (int)($map[$priority]['severity'] ?? 3)));
            return [$pri, $sev];
        }
        return $defaults[$priority] ?? [max(1, min(4, (int)($settings['zentao_default_pri'] ?? 3))), 3];
    }

    // -- Search Zentao bugs ----------------------------------------
    public static function search(): void
    {
        Auth::require();
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        [$zentaoUrl, $token, $productId, $err] = self::resolveConfig();
        if ($err) { echo json_encode(['error' => $err]); exit; }

        // Try product-specific endpoint first, fall back to global
        $bugs = [];
        if ($productId) {
            $params = http_build_query(array_filter(['limit' => 20, 'page' => 1, 'title' => $q ?: null]));
            [$code, $data] = self::apiRequest('GET', "$zentaoUrl/api.php/v1/products/$productId/bugs?$params", $token, []);
            $raw = $data['bugs'] ?? $data['items'] ?? [];
            if (is_array($raw) && $raw) { $bugs = $raw; }
        }
        // If product endpoint returned nothing, try global search
        if (!$bugs) {
            $params = http_build_query(array_filter(['product' => $productId ?: null, 'limit' => 20, 'page' => 1, 'title' => $q ?: null]));
            [$code, $data] = self::apiRequest('GET', "$zentaoUrl/api.php/v1/bugs?$params", $token, []);
            $raw = $data['bugs'] ?? $data['items'] ?? $data['data'] ?? [];
            if (is_array($raw)) { $bugs = $raw; }
        }

        // Filter by search term client-side if API doesn't support title search
        if ($q && $bugs) {
            $qLower = strtolower($q);
            $bugs   = array_values(array_filter($bugs, fn($b) =>
                str_contains(strtolower($b['title'] ?? ''), $qLower) ||
                str_contains((string)($b['id'] ?? ''), $q)
            ));
        }

        echo json_encode(['bugs' => array_values(array_map(fn($b) => [
            'id'     => $b['id'] ?? 0,
            'title'  => $b['title'] ?? '',
            'status' => $b['status'] ?? '',
            'pri'    => $b['pri'] ?? '',
        ], array_slice($bugs, 0, 20)))]);
        exit;
    }

    // -- Link an existing Zentao bug to an entry -------------------
    public static function link(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');
        $bugId = (int)($_POST['bug_id'] ?? 0);
        if (!$bugId) { echo json_encode(['error' => 'Bug ID required']); exit; }
        [$zentaoUrl, $token, , $err] = self::resolveConfig();
        if ($err) { echo json_encode(['error' => $err]); exit; }
        [$code, $bug] = self::apiRequest('GET', "$zentaoUrl/api.php/v1/bugs/$bugId", $token, []);
        $bugData = $bug['data'] ?? $bug;
        if ($code !== 200 || empty($bugData['id'])) {
            echo json_encode(['error' => "Bug #$bugId not found (HTTP $code)"]); exit;
        }
        $bugUrl = rtrim($zentaoUrl, '/') . '/bug-view-' . $bugId . '.html';
        Database::execute(
            'UPDATE entries SET zentao_bug_id=?, zentao_bug_url=?, zentao_synced_at=NULL, zentao_has_changes=0 WHERE id=?',
            [$bugId, $bugUrl, (int)$id]
        );
        echo json_encode(['success' => true, 'bug_id' => $bugId, 'bug_url' => $bugUrl, 'title' => $bugData['title'] ?? '']);
        exit;
    }
}
