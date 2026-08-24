<?php
declare(strict_types=1);

class JiraController
{
    // -- Auto-create called programmatically (no HTTP response) ---
    public static function createForEntry(int $id): void
    {
        $entry = self::fetchEntry($id);
        if (!$entry) return;
        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) return;
        $settings   = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $projectKey = trim($settings['jira_default_project'] ?? '');
        if (!$projectKey) return;
        $isCloud  = str_contains($jiraUrl, 'atlassian.net');
        $titleTpl = appSetting('jira_default_title_template') ?: '[{{type}}] {{title}}';
        $descTpl  = appSetting('jira_default_desc_template')  ?: self::defaultDescTemplate();
        $body = ['fields' => [
            'project'     => ['key' => strtoupper($projectKey)],
            'summary'     => self::applyVars($titleTpl, $entry),
            'issuetype'   => ['name' => 'Bug'],
            'description' => self::descField(self::applyVars($descTpl, $entry), $isCloud),
        ]];
        self::applyFieldMapping($body, $entry);
        [$httpCode, $data] = self::jsonRequest('POST', "$apiBase/issue", $authHeader, $body);
        if (($httpCode === 201 || $httpCode === 200) && isset($data['key'])) {
            Database::execute(
                'UPDATE entries SET jira_issue_key=?, jira_issue_url=?, jira_synced_at=NOW(), jira_has_changes=0 WHERE id=?',
                [$data['key'], "$jiraUrl/browse/{$data['key']}", $id]
            );
        }
    }

    // -- Create new Jira issue ------------------------------------
    public static function create(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');

        $entry = self::fetchEntry((int)$id);
        if (!$entry) { http_response_code(404); echo json_encode(['error' => 'Entry not found']); return; }

        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) { http_response_code(400); echo json_encode(['error' => $err]); return; }

        $settings   = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $projectKey = trim($_POST['project_key'] ?? $settings['jira_default_project'] ?? '');
        $issueType  = trim($_POST['issue_type'] ?? 'Bug');
        $mappedPri  = self::mapEntryPriority($entry);
        $priority   = trim($_POST['priority'] ?? $mappedPri ?: 'Medium');
        $labels     = array_filter(array_map('trim', explode(',', $_POST['labels'] ?? '')));
        $titleTpl   = $_POST['title_template'] ?? '[{{type}}] {{title}}';
        $descTpl    = $_POST['description_template'] ?? "*Type:* {{type}}\n*Project:* {{project}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*Date:* {{date}}\n\n{{description}}";

        if (!$projectKey) { http_response_code(400); echo json_encode(['error' => 'Project key is required.']); return; }

        $summary  = self::applyVars($titleTpl, $entry);
        $descText = self::applyVars($descTpl, $entry);
        $isCloud  = str_contains($jiraUrl, 'atlassian.net');

        $body = ['fields' => [
            'project'     => ['key' => strtoupper($projectKey)],
            'summary'     => $summary,
            'issuetype'   => ['name' => $issueType],
            'priority'    => ['name' => $priority],
            'description' => self::descField($descText, $isCloud),
        ]];
        if ($labels) $body['fields']['labels'] = array_values($labels);
        self::applyFieldMapping($body, $entry);

        [$httpCode, $data] = self::jsonRequest('POST', "$apiBase/issue", $authHeader, $body);

        // On 400 retry without priority + field mapping (field may not be on screen for this issue type)
        if ($httpCode === 400) {
            $minBody = ['fields' => [
                'project'     => ['key' => strtoupper($projectKey)],
                'summary'     => $summary,
                'issuetype'   => ['name' => $issueType],
                'description' => self::descField($descText, $isCloud),
            ]];
            if ($labels) $minBody['fields']['labels'] = array_values($labels);
            [$httpCode, $data] = self::jsonRequest('POST', "$apiBase/issue", $authHeader, $minBody);
        }

        if (($httpCode === 201 || $httpCode === 200) && isset($data['key'])) {
            $key      = $data['key'];
            $issueUrl = "$jiraUrl/browse/$key";

            // Save link to entry
            Database::execute('UPDATE entries SET jira_issue_key=?, jira_issue_url=?, jira_synced_at=NOW(), jira_has_changes=0 WHERE id=?', [$key, $issueUrl, (int)$id]);

            // Upload all attachments
            $attachments = Database::fetchAll('SELECT * FROM entry_attachments WHERE entry_id=? ORDER BY created_at', [(int)$id]);
            $uploaded    = self::uploadAttachments($apiBase, $authHeader, $key, $attachments);

            echo json_encode(['success' => true, 'key' => $key, 'url' => $issueUrl, 'attachments' => $uploaded]);
        } else {
            echo json_encode(['error' => self::extractError($data, $httpCode)]);
        }
    }

    // -- Update existing linked Jira issue ------------------------
    public static function update(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');

        $entry = self::fetchEntry((int)$id);
        if (!$entry) { http_response_code(404); echo json_encode(['error' => 'Entry not found']); return; }
        if (!($entry['jira_issue_key'] ?? '')) { http_response_code(400); echo json_encode(['error' => 'No Jira issue linked to this entry.']); return; }

        $user     = Database::fetchOne('SELECT jira_title_template, jira_desc_template FROM users WHERE id=?', [Auth::id()]);
        $titleTpl = ($_POST['title_template'] ?? '') ?: ($user['jira_title_template'] ?? '') ?: (appSetting('jira_default_title_template') ?: '[{{type}}] {{title}}');
        $descTpl  = ($_POST['description_template'] ?? '') ?: ($user['jira_desc_template']  ?? '') ?: (appSetting('jira_default_desc_template')  ?: self::defaultDescTemplate());

        $result = self::buildAndPushEntry((int)$id, $titleTpl, $descTpl);
        if (isset($result['error'])) {
            echo json_encode(['error' => $result['error']]);
            return;
        }
        echo json_encode(['success' => true] + $result);
    }

    // -- Shared: push an entry to its linked Jira issue (PUT) -----
    // Returns ['key'=>?,'url'=>?,'attachments'=>int] or ['error'=>string]
    public static function buildAndPushEntry(int $id, string $titleTpl, string $descTpl): array
    {
        $entry = self::fetchEntry($id);
        if (!$entry)                    return ['error' => 'Entry not found.'];
        if (!$entry['jira_issue_key'])  return ['error' => 'No Jira issue linked.'];

        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) return ['error' => $err];

        $isCloud  = str_contains($jiraUrl, 'atlassian.net');
        $issueKey = $entry['jira_issue_key'];

        $baseBody = ['fields' => [
            'summary'     => self::applyVars($titleTpl, $entry),
            'description' => self::descField(self::applyVars($descTpl, $entry), $isCloud),
        ]];

        // Try full body first (summary + description + priority + field mapping)
        $fullBody  = $baseBody;
        $mappedPri = self::mapEntryPriority($entry);
        if ($mappedPri) $fullBody['fields']['priority'] = ['name' => $mappedPri];
        self::applyFieldMapping($fullBody, $entry);

        [$httpCode, $errData] = self::jsonRequest('PUT', "$apiBase/issue/$issueKey", $authHeader, $fullBody);

        // On 400 (field rejected by Jira), retry with base body only (summary + description).
        // This commonly happens when the local priority name (Low/Medium/High/Highest/Blocker)
        // doesn't exist in this Jira project's priority scheme, or a mapped custom field isn't
        // on the edit screen for that issue type. Surface that to the caller instead of
        // silently dropping the field — otherwise the push looks successful even though
        // priority was never applied.
        $priorityResult = '';
        if ($httpCode === 400) {
            $origError = self::extractError($errData, $httpCode);
            [$httpCode2, $errData2] = self::jsonRequest('PUT', "$apiBase/issue/$issueKey", $authHeader, $baseBody);
            if ($httpCode2 < 300 || $httpCode2 === 204) {
                $httpCode = $httpCode2;
                $errData  = $errData2;
                if ($mappedPri) $priorityResult = "'$mappedPri' rejected by Jira: $origError";
            } else {
                // Both attempts failed - return detailed error from first attempt
                return ['error' => $origError . ' (retry also failed: ' . self::extractError($errData2, $httpCode2) . ')'];
            }
        } elseif ($httpCode >= 300 && $httpCode !== 204) {
            return ['error' => self::extractError($errData, $httpCode)];
        } elseif ($mappedPri) {
            $priorityResult = "set to '$mappedPri'";
        }

        // Attempt to transition Jira status to match the local entry status
        $localStatus      = $entry['status'] ?? '';
        $transitionResult = '';
        if ($localStatus) {
            $transitionResult = self::applyStatusTransition($apiBase, $authHeader, $issueKey, $localStatus);
        }

        $newAtts  = Database::fetchAll(
            'SELECT * FROM entry_attachments WHERE entry_id=? AND (jira_synced IS NULL OR jira_synced=0) ORDER BY created_at',
            [$id]
        );
        $uploaded = self::uploadAttachments($apiBase, $authHeader, $issueKey, $newAtts);

        // Re-fetch Jira's actual current state after the push attempt. If priority or status
        // still don't match locally (Jira rejected the field, or no workflow transition existed),
        // keep jira_has_changes=1 instead of clearing it — otherwise the mismatch silently
        // "disappears" from the entry / Sync Review list even though Jira was never corrected.
        $state = self::fetchIssueState($issueKey);
        $stillMismatched = false;
        if (!($state['error'] ?? null)) {
            if ($localStatus && self::mapJiraStatusToLocal($state['jira_status_name'] ?? '') !== $localStatus) {
                $stillMismatched = true;
            }
            if ($mappedPri && strcasecmp($state['jira_priority_name'] ?? '', $mappedPri) !== 0) {
                $stillMismatched = true;
            }
        }
        Database::execute(
            'UPDATE entries SET jira_synced_at=?, jira_has_changes=?, jira_status=?, jira_priority=? WHERE id=?',
            [$state['updated_at'] ?? date('Y-m-d H:i:s'), $stillMismatched ? 1 : 0, $state['jira_status_name'] ?? '', $state['jira_priority_name'] ?? null, $id]
        );

        return ['key' => $issueKey, 'url' => "$jiraUrl/browse/$issueKey", 'attachments' => $uploaded, 'transition' => $transitionResult, 'priority' => $priorityResult, 'still_mismatched' => $stillMismatched];
    }

    // -- Shared helpers -------------------------------------------

    public static function mappableFields(): array
    {
        return [
            'firmware_version'     => 'Firmware Version',
            'mower_serial'         => 'Serial Number',
            'app_version'          => 'App Version',
            'type_name'            => 'Entry Type',
            'cat_name'             => 'Category',
            'project_name'         => 'Project',
            'project_status_robot' => 'Project Status',
            'env_name'             => 'Environment',
            'test_area_name'       => 'Test Area',
            'temperature'          => 'Temperature (?C)',
            'weather_condition'    => 'Weather Condition',
        ];
    }

    private static function applyFieldMapping(array &$body, array $entry): void
    {
        $mapping = json_decode(appSetting('jira_field_mapping'), true);
        if (!is_array($mapping) || empty($mapping)) return;
        foreach ($mapping as $localField => $conf) {
            // Support old format (plain string ID) and new format (['id'=>..., 'type'=>...])
            $jiraId   = is_array($conf) ? ($conf['id']   ?? '')     : (string)$conf;
            $jiraType = is_array($conf) ? ($conf['type'] ?? 'text') : 'text';
            if (!$jiraId) continue;
            $value = (string)($entry[$localField] ?? '');
            if ($value === '') continue;
            $body['fields'][$jiraId] = match($jiraType) {
                'select'      => ['value' => $value],
                'multiselect' => [['value' => $value]],
                'version'     => [['name'  => $value]],   // versions / fixVersions
                'labels'      => [$value],                 // plain string array
                default       => $value,
            };
        }
    }

    private static function mapEntryPriority(array $entry): string
    {
        $local = $entry['priority'] ?? '';
        return $local !== '' ? self::mapLocalPriorityToJira($local) : '';
    }

    // Local priority (Low/Medium/High/Highest/Blocker) → Jira priority name to push.
    // Uses the admin-configured mapping (Admin > Jira > Priority Mapping) when set;
    // otherwise falls back to the local name unchanged (only correct if this Jira
    // project's priority scheme happens to use the same names).
    public static function mapLocalPriorityToJira(string $localPriority): string
    {
        $map = json_decode(appSetting('jira_priority_map') ?: '{}', true) ?: [];
        return trim((string)($map[$localPriority] ?? '')) !== '' ? $map[$localPriority] : $localPriority;
    }

    // Reverse of mapLocalPriorityToJira(): given a Jira priority name, returns the
    // matching local priority level, or 'Medium' if nothing matches.
    public static function mapJiraPriorityToLocal(string $jiraPriorityName): string
    {
        $map = json_decode(appSetting('jira_priority_map') ?: '{}', true) ?: [];
        foreach ($map as $local => $jiraName) {
            if (strcasecmp((string)$jiraName, $jiraPriorityName) === 0) return $local;
        }
        $valid = ['Low', 'Medium', 'High', 'Highest', 'Blocker'];
        foreach ($valid as $v) {
            if (strcasecmp($v, $jiraPriorityName) === 0) return $v;
        }
        return 'Medium';
    }

    // Returns true if $jiraPriorityName is the priority Jira should have for
    // $localPriority, per the admin mapping (or literal match when unmapped).
    public static function jiraPriorityMatchesLocal(string $jiraPriorityName, string $localPriority): bool
    {
        if ($localPriority === '') return true;
        return strcasecmp(self::mapLocalPriorityToJira($localPriority), $jiraPriorityName) === 0;
    }

    private static function defaultDescTemplate(): string
    {
        return "*Type:* {{type}}\n*Category:* {{category}}\n*Project:* {{project}}\n*Project Status:* {{project_status}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*App Version:* {{app_version}}\n*Environment:* {{environment}}\n*Test Area:* {{test_area}}\n*Date:* {{date}} {{time}}\n*Creator:* {{creator}}\n\n{{description}}";
    }

    // -- Fetch all Jira fields (for admin field mapping UI) -------
    public static function getAvailableFields(): array
    {
        [, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) return ['error' => $err, 'fields' => []];
        $ch = curl_init("$apiBase/field");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $authHeader, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) return ['error' => "HTTP $httpCode", 'fields' => []];
        $raw    = json_decode($resp ?: '[]', true) ?: [];
        $fields = array_values(array_map(fn($f) => ['id' => $f['id'], 'name' => $f['name'] ?? ''], $raw));
        usort($fields, fn($a, $b) => strcmp($a['name'], $b['name']));
        return ['fields' => $fields];
    }

    public static function fetchEntry(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT e.*,
                    et.name  AS type_name,
                    p.name   AS project_name,
                    ec.name  AS cat_name,
                    env.name AS env_name,
                    ta.name  AS test_area_name,
                    u.name   AS creator_name
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

    private static function isPrivateHost(string $host): bool
    {
        if (in_array($host, ['localhost', '::1'], true)) return true;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : @gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public static function resolveAuth(): array
    {
        $creds    = Database::fetchOne('SELECT jira_email, jira_api_key FROM users WHERE id=?', [Auth::id()]);
        $settings = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $jiraUrl  = rtrim($settings['jira_url'] ?? '', '/');
        $email    = trim($creds['jira_email'] ?? '');
        $token    = trim(Encryption::decrypt($creds['jira_api_key'] ?? ''));

        if (!$jiraUrl || !$token) {
            $missing = [];
            if (!$jiraUrl) $missing[] = 'Jira URL (Admin ? Settings)';
            if (!$token)   $missing[] = 'API Token / PAT (Profile)';
            return [null, null, null, 'Jira not configured. Missing: ' . implode(', ', $missing)];
        }

        // SSRF guard: reject non-HTTPS and private/loopback IP ranges
        $parsed = parse_url($jiraUrl);
        if (($parsed['scheme'] ?? '') !== 'https') {
            return [null, null, null, 'Jira URL must use HTTPS.'];
        }
        $host = $parsed['host'] ?? '';
        if (self::isPrivateHost($host)) {
            return [null, null, null, 'Jira URL must not point to a private or internal address.'];
        }

        $authHeader = $email ? 'Basic ' . base64_encode("$email:$token") : 'Bearer ' . $token;
        $isCloud    = str_contains($jiraUrl, 'atlassian.net');
        $apiBase    = $isCloud ? "$jiraUrl/rest/api/3" : "$jiraUrl/rest/api/2";

        return [$jiraUrl, $apiBase, $authHeader, null];
    }

    private static function applyVars(string $tpl, array $entry): string
    {
        // Build attachment links list (only if {{attachments}} is actually used, to avoid extra query)
        $attachmentsBlock = '';
        if (str_contains($tpl, '{{attachments}}') && !empty($entry['id'])) {
            // Prefer SharePoint links (accessible to Jira/Zentao users); fall back to local URLs
            $spFiles = Database::fetchAll(
                'SELECT filename, web_url FROM entry_sharepoint_files WHERE entry_id=? ORDER BY id',
                [(int)$entry['id']]
            );
            if ($spFiles) {
                $lines = [];
                foreach ($spFiles as $f) {
                    $lines[] = '- [' . $f['filename'] . '](' . $f['web_url'] . ')';
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
                        $lines[] = '- [' . $name . '](' . $url . ') (local - SharePoint not uploaded)';
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
            '{{temperature}}'    => $entry['temperature'] ?? '',
            '{{weather}}'        => $entry['weather_condition'] ?? '',
            '{{sharepoint}}'     => $entry['sharepoint_folder_url'] ?? '',
            '{{attachments}}'    => $attachmentsBlock,
        ]);
    }

    private static function descField(string $text, bool $isCloud): mixed
    {
        if (!$isCloud) return $text;
        return ['type' => 'doc', 'version' => 1, 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ]];
    }

    private static function jsonRequest(string $method, string $url, string $authHeader, array $body): array
    {
        // Encode to JSON - sanitize invalid UTF-8 chars that would make json_encode return false
        $payload = json_encode($body);
        if ($payload === false) {
            array_walk_recursive($body, function (&$v): void {
                if (is_string($v)) $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
            });
            $payload = json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $authHeader,
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response ?: '{}', true);
        // When Jira returns non-JSON (e.g. HTML error page), surface a readable snippet
        if ($decoded === null && $response) {
            $snippet = substr(strip_tags((string)$response), 0, 250);
            $decoded = ['message' => trim($snippet) ?: "HTTP $httpCode (non-JSON response)"];
        }
        return [$httpCode, $decoded ?? []];
    }

    private static function uploadAttachments(string $apiBase, string $authHeader, string $issueKey, array $attachments): int
    {
        $count = 0;
        foreach ($attachments as $att) {
            if (!file_exists($att['file_path'])) continue;
            $name = $att['display_name'] ?: ($att['original_name'] ?? basename($att['file_path']));
            $cf   = new CURLFile($att['file_path'], $att['mime_type'] ?: 'application/octet-stream', $name);

            $ch = curl_init("$apiBase/issue/$issueKey/attachments");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: ' . $authHeader,
                    'X-Atlassian-Token: no-check',
                ],
                CURLOPT_POSTFIELDS     => ['file' => $cf],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 || $httpCode === 201) {
                Database::execute('UPDATE entry_attachments SET jira_synced=1 WHERE id=?', [$att['id']]);
                $count++;
            }
        }
        return $count;
    }

    private static function extractError(?array $data, int $httpCode): string
    {
        if (is_array($data)) {
            // Field-specific errors: {"errors": {"field_id": "msg"}}  - non-empty takes priority
            $errors = $data['errors'] ?? [];
            if (is_array($errors) && $errors) {
                return implode(', ', array_map(
                    fn($f, $m) => is_string($m) ? "$f: $m" : $f,
                    array_keys($errors), array_values($errors)
                ));
            }
            // Global messages: {"errorMessages": ["msg1", "msg2"]}
            $msgs = $data['errorMessages'] ?? [];
            if (is_array($msgs) && $msgs) return implode(', ', $msgs);
            if (isset($data['message']) && is_string($data['message'])) return $data['message'];
        }
        return match($httpCode) {
            401 => 'Jira authentication failed - check your PAT/credentials in Settings',
            403 => 'Permission denied - your Jira account may not have access to this issue',
            404 => 'Jira issue not found',
            429 => 'Jira rate limit reached - try again in a few minutes',
            503 => 'Jira is temporarily unavailable - try again in a few minutes',
            0   => 'Could not connect to Jira - check the server URL and network',
            default => "HTTP $httpCode",
        };
    }

    // -- Create Jira issue for Test Request -----------------------
    public static function createForTestRequest(int $id): void
    {
        $request = Database::fetchOne('SELECT * FROM test_requests WHERE id=?', [$id]);
        if (!$request) throw new \RuntimeException('Test Request not found.');

        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) throw new \RuntimeException($err);

        $projectKey = trim(appSetting('jira_test_request_project'));
        if (!$projectKey) throw new \RuntimeException('Jira Test Request project key not configured (Admin ? Settings).');

        $isCloud = str_contains($jiraUrl, 'atlassian.net');

        // Resolve custom field IDs by name
        $customFieldMap = self::resolveCustomFields($apiBase, $authHeader, [
            'Project Name', 'Project Number', 'Order Number', 'Product', 'Initiator', 'Development Type',
        ]);

        $body = ['fields' => [
            'project'     => ['key' => strtoupper($projectKey)],
            'summary'     => $request['summary'],
            'issuetype'   => ['name' => 'Request'],
            'description' => self::descField($request['description'] ?? '', $isCloud),
        ]];

        if ($request['labels']) {
            $body['fields']['labels'] = array_values(array_filter(array_map('trim', explode(',', $request['labels']))));
        }

        $fieldValues = [
            'Project Name'    => $request['project_name'],
            'Project Number'  => $request['project_number'],
            'Order Number'    => $request['order_number'],
            'Product'         => $request['product'],
            'Initiator'       => $request['initiator'],
            'Development Type'=> $request['development_type'],
        ];
        foreach ($fieldValues as $name => $value) {
            if ($value && isset($customFieldMap[$name])) {
                $body['fields'][$customFieldMap[$name]] = $value;
            }
        }

        [$httpCode, $data] = self::jsonRequest('POST', "$apiBase/issue", $authHeader, $body);
        if (($httpCode !== 201 && $httpCode !== 200) || !isset($data['key'])) {
            throw new \RuntimeException('Jira error: ' . self::extractError($data, $httpCode));
        }

        $key      = $data['key'];
        $issueUrl = "$jiraUrl/browse/$key";

        Database::execute(
            'UPDATE test_requests SET jira_issue_key=?, jira_issue_url=?, jira_synced_at=NOW(), jira_has_changes=0 WHERE id=?',
            [$key, $issueUrl, $id]
        );

        // Upload attachments
        $atts = Database::fetchAll(
            'SELECT id, file_path, original_name AS original_name, display_name, mime_type FROM test_request_attachments WHERE request_id=?',
            [$id]
        );
        foreach ($atts as &$att) {
            $att['display_name'] = $att['display_name'] ?: $att['original_name'];
        }
        unset($att);

        $ch = null;
        foreach ($atts as $att) {
            if (!file_exists($att['file_path'])) continue;
            $name = $att['display_name'] ?: basename($att['file_path']);
            $cf   = new CURLFile($att['file_path'], $att['mime_type'] ?: 'application/octet-stream', $name);
            $ch   = curl_init("$apiBase/issue/$key/attachments");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: ' . $authHeader, 'X-Atlassian-Token: no-check'],
                CURLOPT_POSTFIELDS     => ['file' => $cf],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    // -- Parse structured *Field:* Value lines from a Jira description --
    // Returns ['fields' => ['Type' => 'Bug', ...], 'free_text' => '...']
    public static function parseDescriptionFields(string $desc): array
    {
        $fields   = [];
        $freeLines = [];
        $inHeader  = true;
        foreach (preg_split('/\r?\n/', $desc) as $raw) {
            $line = trim($raw);
            if ($inHeader && preg_match('/^\*([^*:]+):\*\s*(.*)$/', $line, $m)) {
                $fields[trim($m[1])] = trim($m[2]);
            } else {
                if ($line !== '' || !$inHeader) {
                    $inHeader = false;
                }
                if (!$inHeader) {
                    $freeLines[] = $raw;
                }
            }
        }
        return [
            'fields'    => $fields,
            'free_text' => rtrim(ltrim(implode("\n", $freeLines), "\n")),
        ];
    }

    // Canonical map: Jira description label ? [entry column, display label, lookup table or null]
    public static function entryFieldMap(): array
    {
        return [
            'Type'           => ['col' => 'entry_type_id',        'label' => 'Type',           'lookup' => 'entry_types'],
            'Category'       => ['col' => 'error_category_id',    'label' => 'Category',       'lookup' => 'error_categories'],
            'Project'        => ['col' => 'project_id',           'label' => 'Project',        'lookup' => 'projects'],
            'Project Status' => ['col' => 'project_status_robot', 'label' => 'Project Status', 'lookup' => null],
            'Serial'         => ['col' => 'mower_serial',         'label' => 'Serial',         'lookup' => null],
            'Firmware'       => ['col' => 'firmware_version',     'label' => 'Firmware',       'lookup' => null],
            'App Version'    => ['col' => 'app_version',          'label' => 'App Version',    'lookup' => null],
            'Environment'    => ['col' => 'environment_id',       'label' => 'Environment',    'lookup' => 'test_environments'],
            'Test Area'      => ['col' => 'test_area_id',         'label' => 'Test Area',      'lookup' => 'test_areas'],
        ];
    }

    // -- Bulk-check all linked records for Jira changes -----------
    // Compares Jira's `updated` timestamp against our jira_synced_at.
    // First-time check (jira_synced_at NULL) establishes a baseline without flagging.
    // Returns count of newly flagged records.
    public static function bulkCheckChanges(): int
    {
        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) return 0;

        // -- Pre-check: detect mismatches using stored jira_status / jira_priority (no API call) --
        $changed = 0;
        // Pre-check ALL entries (including already-flagged ones) so stale flags get cleared.
        // Previously filtering jira_has_changes=0 meant wrongly-flagged entries were never re-evaluated.
        $preCheck = Database::fetchAll(
            "SELECT id, jira_status, jira_priority, jira_has_changes, status, priority FROM entries
             WHERE jira_issue_key IS NOT NULL
             AND (jira_status IS NOT NULL OR jira_priority IS NOT NULL)",
        );
        foreach ($preCheck as $sm) {
            $statusDiffers   = $sm['jira_status']   && self::mapJiraStatusToLocal($sm['jira_status']) !== ($sm['status'] ?? '');
            $priorityDiffers = $sm['jira_priority'] && !self::jiraPriorityMatchesLocal($sm['jira_priority'], $sm['priority'] ?? '');
            if ($statusDiffers || $priorityDiffers) {
                if (!$sm['jira_has_changes']) {
                    Database::execute('UPDATE entries SET jira_has_changes=1 WHERE id=?', [$sm['id']]);
                    $changed++;
                }
            } else {
                // No real difference - clear any stale flag
                if ($sm['jira_has_changes']) {
                    Database::execute('UPDATE entries SET jira_has_changes=0 WHERE id=?', [$sm['id']]);
                }
            }
        }

        // API check: process all entries (flagged or not) - up to limit per run
        $entries = Database::fetchAll(
            "SELECT 'entry' AS src, id, jira_issue_key, jira_synced_at, jira_has_changes, status, priority FROM entries
             WHERE jira_issue_key IS NOT NULL AND jira_issue_key != ''
             ORDER BY jira_synced_at ASC LIMIT 40"
        );
        $testReqs = Database::fetchAll(
            "SELECT 'test_request' AS src, id, jira_issue_key, jira_synced_at, jira_has_changes, '' AS status, '' AS priority FROM test_requests
             WHERE jira_issue_key IS NOT NULL AND jira_issue_key != ''
             ORDER BY jira_synced_at ASC LIMIT 20"
        );
        $records = array_merge($entries, $testReqs);
        if (!$records) return $changed;  // return pre-check $changed, not 0

        $keys = array_values(array_unique(array_column($records, 'jira_issue_key')));
        $jql  = 'key in (' . implode(',', array_map(fn($k) => '"' . addslashes($k) . '"', $keys)) . ')';

        $ch = curl_init("$apiBase/search?" . http_build_query([
            'jql'        => $jql,
            'fields'     => 'updated,status,priority',
            'maxResults' => count($keys),
        ]));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $authHeader, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data       = json_decode($resp ?: '{}', true);
        $jiraFields = [];
        foreach (($data['issues'] ?? []) as $issue) {
            $jiraFields[$issue['key']] = [
                'updated'  => $issue['fields']['updated'] ?? null,
                'status'   => $issue['fields']['status']['name'] ?? null,
                'priority' => $issue['fields']['priority']['name'] ?? null,
            ];
        }

        foreach ($records as $rec) {
            $issueData = $jiraFields[$rec['jira_issue_key']] ?? null;
            if (!$issueData) continue;
            $jiraTime     = $issueData['updated'];
            $jiraStatus   = $issueData['status'];
            $jiraPriority = $issueData['priority'];
            if (!$jiraTime) continue;
            $jiraTs = strtotime($jiraTime);
            $table  = $rec['src'] === 'entry' ? 'entries' : 'test_requests';

            // Only flag for real content differences (status/priority) - NOT pure timestamp changes.
            // Jira advances its `updated` timestamp for comments, assignee changes, sprint moves etc.
            // Flagging on timestamp alone causes constant false-positive "changes detected" messages.
            $statusDiffers = false;
            if ($jiraStatus && $table === 'entries' && ($rec['status'] ?? '') !== '') {
                $mappedStatus  = self::mapJiraStatusToLocal($jiraStatus);
                $statusDiffers = $mappedStatus !== ($rec['status'] ?? '');
            }
            $priorityDiffers = $jiraPriority && $table === 'entries' && ($rec['priority'] ?? '') !== ''
                && !self::jiraPriorityMatchesLocal($jiraPriority, $rec['priority']);
            $hasContentChange = $statusDiffers || $priorityDiffers;

            // Always build the set of values to persist (status, priority, synced_at)
            // so the pre-check and next run use the latest fetched values.
            $syncedAtStr = date('Y-m-d H:i:s', $jiraTs);
            $extraSets   = ["jira_synced_at='$syncedAtStr'"]; // always advance the baseline
            $extraBinds  = [];
            if ($jiraStatus   && $table === 'entries') { $extraSets[] = 'jira_status=?';   $extraBinds[] = $jiraStatus; }
            if ($jiraPriority && $table === 'entries') { $extraSets[] = 'jira_priority=?'; $extraBinds[] = $jiraPriority; }

            if ($hasContentChange) {
                $sets = array_merge(['jira_has_changes=1'], $extraSets);
                Database::execute("UPDATE $table SET " . implode(',', $sets) . " WHERE id=?", array_merge($extraBinds, [$rec['id']]));
                $changed++;
            } else {
                // No real change - update stored values + clear any stale flag
                $sets = array_merge(['jira_has_changes=0'], $extraSets);
                Database::execute("UPDATE $table SET " . implode(',', $sets) . " WHERE id=?", array_merge($extraBinds, [$rec['id']]));
            }
        }
        return $changed;
    }

    // -- Fetch current state of a Jira issue (description + comments) --
    public static function fetchIssueState(string $issueKey): array
    {
        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) return ['error' => $err];

        $ch = curl_init("$apiBase/issue/$issueKey?fields=summary,description,comment,attachment,updated,status,priority");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $authHeader, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp ?: '{}', true);
        if ($httpCode !== 200) return ['error' => self::extractError($data, $httpCode)];

        $f = $data['fields'];
        $comments = [];
        foreach (($f['comment']['comments'] ?? []) as $c) {
            $comments[] = [
                'id'         => $c['id'],
                'author'     => $c['author']['displayName'] ?? ($c['author']['name'] ?? 'Unknown'),
                'body'       => self::flattenDescription($c['body'] ?? null),
                'created_at' => str_replace('T', ' ', substr($c['created'] ?? '', 0, 19)),
                'updated_at' => str_replace('T', ' ', substr($c['updated'] ?? '', 0, 19)),
            ];
        }

        $isCloud     = str_contains($jiraUrl, 'atlassian.net');
        $attachments = [];
        foreach (($f['attachment'] ?? []) as $a) {
            // Always ensure a usable download URL
            $contentUrl = $a['content'] ?? '';
            if (!$contentUrl) {
                $contentUrl = $isCloud
                    ? "$apiBase/attachment/content/{$a['id']}"
                    : "$jiraUrl/secure/attachment/{$a['id']}/" . rawurlencode($a['filename'] ?? '');
            }
            $attachments[] = [
                'id'         => $a['id'],
                'filename'   => $a['filename'],
                'size'       => $a['size'] ?? 0,
                'mimeType'   => $a['mimeType'] ?? '',
                'created'    => str_replace('T', ' ', substr($a['created'] ?? '', 0, 19)),
                'author'     => $a['author']['displayName'] ?? ($a['author']['name'] ?? ''),
                'contentUrl' => $contentUrl,
            ];
        }

        $description = self::flattenDescription($f['description'] ?? null);
        $parsed      = self::parseDescriptionFields($description);

        return [
            'key'             => $issueKey,
            'description'     => $description,
            'parsed_fields'   => $parsed['fields'],
            'parsed_free_text'=> $parsed['free_text'],
            'comments'        => $comments,
            'attachments'     => $attachments,
            'updated_at'      => str_replace('T', ' ', substr($f['updated'] ?? '', 0, 19)),
            'jira_status_name'   => $f['status']['name'] ?? null,
            'jira_priority_name' => $f['priority']['name'] ?? null,
            'jira_url'           => $jiraUrl,
            'error'           => null,
        ];
    }

    // -- Search Jira issues for import into Test Requests ---------
    // Returns ['issues' => [...], 'error' => string|null]
    public static function searchForImport(string $jql, int $maxResults = 50): array
    {
        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) return ['issues' => [], 'error' => $err];

        $customNames = ['Project Name', 'Project Number', 'Order Number', 'Product', 'Initiator', 'Development Type'];
        $fieldMap    = self::resolveCustomFields($apiBase, $authHeader, $customNames);
        $fieldIds    = array_values($fieldMap);
        $fieldList   = implode(',', array_merge(['summary', 'description', 'labels', 'status', 'issuetype', 'created'], $fieldIds));

        $url = "$apiBase/search?" . http_build_query([
            'jql'        => $jql,
            'maxResults' => $maxResults,
            'fields'     => $fieldList,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $authHeader, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp ?: '{}', true);
        if ($httpCode !== 200) {
            return ['issues' => [], 'error' => self::extractError($data, $httpCode)];
        }

        $issues = [];
        foreach (($data['issues'] ?? []) as $issue) {
            $f = $issue['fields'];
            $issues[] = [
                'key'              => $issue['key'],
                'summary'          => $f['summary'] ?? '',
                'description'      => self::flattenDescription($f['description'] ?? null),
                'labels'           => implode(', ', $f['labels'] ?? []),
                'status'           => $f['status']['name'] ?? '',
                'created'          => substr($f['created'] ?? '', 0, 10),
                'project_name'     => self::cfVal($f, $fieldMap, 'Project Name'),
                'project_number'   => self::cfVal($f, $fieldMap, 'Project Number'),
                'order_number'     => self::cfVal($f, $fieldMap, 'Order Number'),
                'product'          => self::cfVal($f, $fieldMap, 'Product'),
                'initiator'        => self::cfVal($f, $fieldMap, 'Initiator'),
                'development_type' => self::cfVal($f, $fieldMap, 'Development Type'),
            ];
        }
        return ['issues' => $issues, 'error' => null, 'jiraUrl' => $jiraUrl];
    }

    private static function cfVal(array $fields, array $fieldMap, string $name): string
    {
        $id  = $fieldMap[$name] ?? null;
        $val = $id ? ($fields[$id] ?? null) : null;
        if ($val === null || $val === '') return '';
        if (is_array($val)) return $val['value'] ?? $val['name'] ?? '';
        return (string)$val;
    }

    // Flatten Jira description: plain text (Server) or ADF (Cloud)
    private static function flattenDescription(mixed $desc): string
    {
        if ($desc === null) return '';
        if (is_string($desc)) return $desc;
        if (is_array($desc)) return trim(self::flattenAdf($desc));
        return '';
    }

    private static function flattenAdf(array $node): string
    {
        $out = '';
        $type = $node['type'] ?? '';
        if ($type === 'text') return $node['text'] ?? '';
        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $child) {
                $out .= self::flattenAdf($child);
            }
        }
        if (in_array($type, ['paragraph', 'heading', 'bulletList', 'listItem', 'rule'], true)) {
            $out .= "\n";
        }
        return $out;
    }

    private static function resolveCustomFields(string $apiBase, string $authHeader, array $names): array
    {
        $ch = curl_init("$apiBase/field");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $authHeader, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp   = curl_exec($ch);
        curl_close($ch);
        $fields = json_decode($resp ?: '[]', true);
        $map    = [];
        if (!is_array($fields)) return $map;
        $nameLower = array_map('strtolower', $names);
        foreach ($fields as $f) {
            $idx = array_search(strtolower($f['name'] ?? ''), $nameLower, true);
            if ($idx !== false) {
                $map[$names[$idx]] = $f['id'];
            }
        }
        return $map;
    }

    // -- Sync Jira status ? entry status -------------------------
    public static function syncStatus(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();

        $entry = self::fetchEntry((int)$id);
        if (!$entry || !$entry['jira_issue_key']) {
            json(['error' => 'No Jira issue linked to this entry.']);
        }

        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) { json(['error' => $err]); }

        $ch = curl_init("$apiBase/issue/{$entry['jira_issue_key']}?fields=status,priority");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $authHeader],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch); curl_close($ch);
        $data = json_decode($raw ?: '{}', true) ?? [];

        $jiraStatus   = $data['fields']['status']['name'] ?? null;
        $jiraPriority = $data['fields']['priority']['name'] ?? null;
        if (!$jiraStatus) { json(['error' => 'Could not fetch Jira status.']); }

        $syncMode    = $_POST['sync_mode'] ?? 'quick';
        $checkFields = json_decode(appSetting($syncMode === 'full' ? 'jira_full_sync_fields' : 'jira_quick_sync_fields',
                           $syncMode === 'full' ? '["status","priority","description","comments"]' : '["status","priority"]'), true)
                       ?: ['status','priority'];

        $localStatus   = $entry['status'] ?? '';
        $localPriority = $entry['priority'] ?? 'Medium';
        $mappedStatus  = self::mapJiraStatusToLocal($jiraStatus);

        $statusDiffers   = in_array('status',   $checkFields) && $mappedStatus !== $localStatus;
        $priorityDiffers = in_array('priority', $checkFields) && $jiraPriority && !self::jiraPriorityMatchesLocal($jiraPriority, $localPriority);
        $anyDiffers      = $statusDiffers || $priorityDiffers;

        Database::execute(
            "UPDATE entries SET jira_status=?, jira_priority=?, jira_status_synced_at=NOW(), jira_has_changes=? WHERE id=?",
            [$jiraStatus, $jiraPriority, $anyDiffers ? 1 : 0, (int)$id]
        );

        json(['success'          => true,
              'jira_status'      => $jiraStatus,   'mapped_status'   => $mappedStatus,  'local_status'   => $localStatus,
              'jira_priority'    => $jiraPriority,  'local_priority'  => $localPriority,
              'status_differs'   => $statusDiffers, 'priority_differs' => $priorityDiffers,
              'differs'          => $anyDiffers]);
    }

    // -- Batch sync (called by cron script) -----------------------
    public static function syncAllStatuses(): void
    {
        [$jiraUrl, $apiBase, $authHeader, $err] = self::resolveAuth();
        if ($err) { return; }

        $entries = Database::fetchAll(
            "SELECT id, jira_issue_key FROM entries
             WHERE jira_issue_key IS NOT NULL AND jira_issue_key != ''
               AND (jira_status_synced_at IS NULL OR jira_status_synced_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE))
               AND status != 'finalized'
             LIMIT 50"
        );

        foreach ($entries as $e) {
            $ch = curl_init("$apiBase/issue/{$e['jira_issue_key']}?fields=status");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $authHeader],
                CURLOPT_TIMEOUT        => 10,
            ]);
            $raw  = curl_exec($ch); curl_close($ch);
            $data = json_decode($raw ?: '{}', true) ?? [];
            $js   = $data['fields']['status']['name'] ?? null;
            if (!$js) continue;
            $mapped = self::mapJiraStatusToLocal($js);
            // Store Jira status label only - do NOT overwrite local entry status.
            // Flag as changed if the mapped status differs from the current entry status.
            $current = Database::fetchOne('SELECT status FROM entries WHERE id=?', [$e['id']]);
            $differs = $current && $mapped !== ($current['status'] ?? '');
            $sql    = "UPDATE entries SET jira_status=?, jira_status_synced_at=NOW()" . ($differs ? ", jira_has_changes=1" : "") . " WHERE id=?";
            Database::execute($sql, [$js, $e['id']]);
        }
    }

    // Transition a Jira issue to the status that corresponds to the given local status.
    // Jira status cannot be set via PUT - it requires finding the right workflow transition.
    // Returns a human-readable result string (empty = no transition needed/possible)
    private static function applyStatusTransition(string $apiBase, string $authHeader, string $issueKey, string $localStatus): string
    {
        // Fetch available transitions for this issue
        $ch = curl_init("$apiBase/issue/$issueKey/transitions");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $authHeader, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data        = json_decode($resp ?: '{}', true);
        $transitions = $data['transitions'] ?? [];
        if (!$transitions) return 'no transitions available';

        // Build a reverse map from the admin status map:
        // local_slug ? [jira status names that map to it]
        $adminMap   = json_decode(appSetting('jira_status_map'), true) ?: [];
        $reverseMap = [];
        foreach ($adminMap as $jiraName => $lSlug) {
            $reverseMap[$lSlug][] = strtolower(trim($jiraName));
        }
        $wantedNames = $reverseMap[$localStatus] ?? [];

        $targetId   = null;
        $targetName = '';
        foreach ($transitions as $t) {
            $toName   = trim($t['to']['name'] ?? '');
            $transName = trim($t['name'] ?? '');
            if (!$toName) continue;

            // 1. Reverse-map: does this transition lead to a Jira status that the admin mapped to localStatus?
            if ($wantedNames && in_array(strtolower($toName), $wantedNames, true)) {
                $targetId = $t['id']; $targetName = $toName; break;
            }
            // 2. mapJiraStatusToLocal on the target name (catches slug normalization)
            if (self::mapJiraStatusToLocal($toName) === $localStatus) {
                $targetId = $t['id']; $targetName = $toName; break;
            }
            // 3. Same checks on the transition name itself (some workflows name it differently)
            if ($wantedNames && in_array(strtolower($transName), $wantedNames, true)) {
                $targetId = $t['id']; $targetName = $transName; break;
            }
            if ($transName && self::mapJiraStatusToLocal($transName) === $localStatus) {
                $targetId = $t['id']; $targetName = $transName; break;
            }
        }

        if (!$targetId) {
            $available = implode(', ', array_map(fn($t) => '"' . ($t['to']['name'] ?? $t['name'] ?? '?') . '"', $transitions));
            return "no matching transition for '$localStatus' (available targets: $available)";
        }

        [$httpCode] = self::jsonRequest('POST', "$apiBase/issue/$issueKey/transitions", $authHeader, [
            'transition' => ['id' => $targetId],
        ]);

        if ($httpCode === 204 || $httpCode === 200) {
            return "transitioned to '$targetName'";
        }
        return "transition to '$targetName' failed (HTTP $httpCode)";
    }

    // Map a Jira status name to a local entry status using admin settings
    public static function mapJiraStatusToLocal(string $jiraStatus): string
    {
        $map = json_decode(appSetting('jira_status_map'), true) ?: [];
        // 1. Exact match from admin mapping
        if (isset($map[$jiraStatus])) return $map[$jiraStatus];
        // 2. Case-insensitive match from admin mapping
        foreach ($map as $k => $v) {
            if (strcasecmp($k, $jiraStatus) === 0) return $v;
        }
        // 3. Normalize Jira status to slug and match against local status slugs
        //    e.g. "Ready for Test" ? "ready_for_test", "Pending at Supplier" ? "pending_at_supplier"
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($jiraStatus)));
        $slug = trim($slug, '_');
        if (array_key_exists($slug, entryStatuses())) return $slug;
        // 4. Hardcoded sensible fallbacks
        return match (strtolower($jiraStatus)) {
            'done', 'closed', 'resolved', 'won\'t do', 'duplicate' => 'finished',
            'in progress'                                            => 'internal',
            'in review'                                             => 'reviewed',
            'blocked'                                               => 'pending_at_supplier',
            'won\'t fix', 'wont fix', 'rejected'                   => 'rejected',
            default                                                 => 'new',
        };
    }
}
