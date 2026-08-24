<?php
declare(strict_types=1);

class ConfluenceController
{
    public static function index(): void
    {
        Auth::requireView('confluence');

        $settings   = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $projects   = Database::fetchAll("SELECT id, name, color FROM projects WHERE status='active' ORDER BY name");
        $types      = Database::fetchAll('SELECT id, name, color FROM entry_types ORDER BY sort_order, name');
        $categories = Database::fetchAll('SELECT id, name FROM error_categories ORDER BY sort_order, name');
        $inventory  = Database::fetchAll('SELECT * FROM inventory_items ORDER BY name');
        $presets    = Database::fetchAll("SELECT * FROM user_presets WHERE user_id=? AND preset_type='entry_table' ORDER BY name", [Auth::id()]);

        // Server-side entry filtering for Confluence export
        $cfWhere  = ['e.is_merged = 0'];
        $cfParams = [];
        $cfFiltersActive = [];
        $colFilterMap = [
            'status'   => ['e.status',           'like'],
            'priority' => ['e.priority',          'like'],
            'type'     => ['et.name',             'like'],
            'category' => ['ec.name',             'like'],
            'project'  => ['p.name',              'like'],
            'creator'  => ['u.name',              'like'],
            'serial'   => ['e.mower_serial',      'like'],
            'firmware' => ['e.firmware_version',  'like'],
            'title'    => ['e.title',             'like'],
        ];
        foreach ($colFilterMap as $colKey => [$sqlExpr, $_]) {
            $rawVal = trim($_GET['_cf_'.$colKey] ?? '');
            if ($rawVal === '') continue;
            $terms = array_filter(array_map('trim', preg_split('/[,;]/', $rawVal)));
            if (empty($terms)) continue;
            $cfFiltersActive[$colKey] = $rawVal;
            $clauses = array_map(fn($t) => "$sqlExpr LIKE ?", $terms);
            $cfWhere[] = '(' . implode(' OR ', $clauses) . ')';
            foreach ($terms as $t) $cfParams[] = "%$t%";
        }
        if (isset($_GET['cf_project_id']) && (int)$_GET['cf_project_id'] > 0) {
            $cfWhere[] = 'e.project_id = ?'; $cfParams[] = (int)$_GET['cf_project_id'];
            $cfFiltersActive['project_id'] = (int)$_GET['cf_project_id'];
        }
        $cfWStr = implode(' AND ', $cfWhere);
        $allEntries = Database::fetchAll(
            "SELECT e.id, e.title, e.description, e.entry_date, e.entry_type_id, e.project_id, e.status, e.priority,
                    et.name type_name, et.color type_color, p.name project_name, ec.name cat_name, u.name creator
             FROM entries e
             LEFT JOIN entry_types et      ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             LEFT JOIN projects p          ON p.id  = e.project_id
             LEFT JOIN users u             ON u.id  = e.created_by
             WHERE $cfWStr
             ORDER BY e.entry_date DESC, e.id DESC",
            $cfParams
        );

        $result = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $result = self::export($settings);
            if (!empty($result['success'])) {
                $spaceKey   = strtoupper(trim($_POST['space_key'] ?? $settings['confluence_default_space'] ?? ''));
                $appendMode = ($_POST['append_mode'] ?? 'new') === 'append' ? 1 : 0;
                $mode       = $_POST['mode'] ?? 'entries';
                $pageIdStr  = preg_match('/\/pages\/(\d+)/', $result['url'], $m) ? $m[1] : null;
                Database::insert(
                    'INSERT INTO confluence_exports (page_id, page_title, page_url, space_key, export_mode, append_mode, exported_by) VALUES (?,?,?,?,?,?,?)',
                    [$pageIdStr, $result['title'], $result['url'], $spaceKey, $mode, $appendMode, Auth::id()]
                );
            }
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
            if ($isAjax) {
                json($result);
            }
        }

        $exports = Database::fetchAll(
            'SELECT ce.*, u.name exported_by_name FROM confluence_exports ce
             LEFT JOIN users u ON u.id = ce.exported_by
             ORDER BY ce.exported_at DESC LIMIT 50'
        );

        View::render('confluence/index', compact('projects', 'types', 'categories', 'inventory', 'settings', 'result', 'exports', 'presets', 'allEntries', 'cfFiltersActive'), 'app');
    }

    public static function searchPages(): void
    {
        Auth::requireEdit('confluence');
        $settings  = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $user      = Database::fetchOne('SELECT confluence_email, confluence_token FROM users WHERE id=?', [Auth::id()]);
        $baseUrl   = rtrim($settings['confluence_url'] ?? '', '/');
        $email     = trim($user['confluence_email'] ?? '');
        $token     = trim(Encryption::decrypt($user['confluence_token'] ?? '') ?? '');
        $q         = trim($_GET['q'] ?? '');
        $spaceKey  = strtoupper(trim($_GET['space'] ?? ''));

        if (!$baseUrl || !$token || strlen($q) < 2) {
            json([]);
        }

        $authHeader = $email
            ? 'Basic ' . base64_encode("$email:$token")
            : 'Bearer ' . $token;
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $authHeader];

        $cql = 'type=page AND title~"' . addslashes($q) . '"';
        if ($spaceKey) $cql .= ' AND space="' . addslashes($spaceKey) . '"';
        $url = $baseUrl . '/rest/api/content/search?cql=' . urlencode($cql) . '&limit=10&expand=ancestors';

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 8]);
        $r = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($r ?: '{}', true) ?? [];
        $pages = array_map(fn($p) => ['id' => $p['id'], 'title' => $p['title']], $data['results'] ?? []);
        json($pages);
    }

    private static function export(array $settings): array
    {
        $user      = Database::fetchOne('SELECT confluence_email, confluence_token FROM users WHERE id=?', [Auth::id()]);
        $baseUrl   = rtrim($settings['confluence_url'] ?? '', '/');
        $email     = trim($user['confluence_email'] ?? '');
        $token     = trim(Encryption::decrypt($user['confluence_token'] ?? '') ?? '');

        if (!$baseUrl || !$token) {
            $missing = [];
            if (!$baseUrl) $missing[] = 'Confluence URL (Admin → Settings)';
            if (!$token)   $missing[] = 'API Token / PAT (Profile)';
            return ['error' => 'Confluence not configured. Missing: ' . implode(', ', $missing)];
        }

        // PAT = Bearer auth (no email); Cloud API token = Basic auth (email:token)
        $authHeader = $email
            ? 'Basic ' . base64_encode("$email:$token")
            : 'Bearer ' . $token;

        $mode        = $_POST['mode'] ?? 'entries'; // entries | inventory
        $spaceKey    = strtoupper(trim($_POST['space_key'] ?? $settings['confluence_default_space'] ?? ''));
        $pageTitle   = trim($_POST['page_title'] ?? 'RoboDoc Export');
        $parentId    = trim($_POST['parent_id'] ?? '');
        $appendMode  = ($_POST['append_mode'] ?? 'new') === 'append';
        $existingId  = trim($_POST['existing_page_id'] ?? '');

        if (!$spaceKey) {
            return ['error' => 'Space key is required.'];
        }

        // Build page body
        if ($mode === 'inventory') {
            $itemIds    = array_map('intval', (array)($_POST['item_ids'] ?? []));
            $invColumns = (array)($_POST['inv_columns'] ?? ['name', 'serial', 'project', 'firmware', 'status', 'location', 'notes']);
            $html       = self::buildInventoryHtml($itemIds, $invColumns);
        } elseif ($mode === 'mower_history') {
            $serials      = array_filter(array_map('trim', (array)($_POST['serials'] ?? [])));
            $mowerColumns = (array)($_POST['mower_columns'] ?? ['date', 'type', 'title', 'firmware', 'app_version']);
            $html         = self::buildMowerHistoryHtml($serials, $mowerColumns);
        } else {
            $entryIds = array_map('intval', (array)($_POST['entry_ids'] ?? []));
            $columns  = (array)($_POST['columns'] ?? ['date', 'type', 'project', 'title', 'description', 'serial', 'firmware']);
            $entryImageMap = [];
            $html     = self::buildEntriesHtml($entryIds, $columns, $entryImageMap);
        }

        // Collect entry IDs that need image upload (if images column selected)
        $needImageUpload = isset($columns) && in_array('images', $columns);
        $storageBody = self::wrapStorageFormat($html);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $authHeader,
        ];

        if ($appendMode && $existingId) {
            // Fetch existing page version
            $existing = self::confluenceGet("$baseUrl/rest/api/content/$existingId?expand=version,body.storage", $headers);
            if (isset($existing['statusCode']) && $existing['statusCode'] >= 400) {
                return ['error' => 'Could not find page ' . $existingId];
            }
            $version = (int)($existing['version']['number'] ?? 0) + 1;
            $currentBody = $existing['body']['storage']['value'] ?? '';
            $storageBody = $currentBody . "\n" . $storageBody;
            $payload = [
                'version' => ['number' => $version],
                'title'   => $existing['title'],
                'type'    => 'page',
                'body'    => ['storage' => ['value' => $storageBody, 'representation' => 'storage']],
            ];
            $resp = self::confluencePut("$baseUrl/rest/api/content/$existingId", $headers, $payload);
        } else {
            $payload = [
                'type'  => 'page',
                'title' => $pageTitle,
                'space' => ['key' => $spaceKey],
                'body'  => ['storage' => ['value' => $storageBody, 'representation' => 'storage']],
            ];
            if ($parentId) {
                $payload['ancestors'] = [['id' => $parentId]];
            }
            $resp = self::confluencePost("$baseUrl/rest/api/content", $headers, $payload);
        }

        if (isset($resp['id'])) {
            $createdPageId = $resp['id'];
            $pageUrl = "$baseUrl/wiki/spaces/$spaceKey/pages/$createdPageId";
            if (!empty($resp['_links']['webui'])) {
                $pageUrl = rtrim($baseUrl, '/') . $resp['_links']['webui'];
            }
            // Upload images and update page with real ac:image tags if needed
            if (!empty($needImageUpload) && !empty($entryImageMap)) {
                $imgUploadHeaders = [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $authHeader,
                ];
                $uploadedMap = self::uploadEntryImages($baseUrl, $imgUploadHeaders, $createdPageId, array_keys($entryImageMap));
                if (!empty($uploadedMap)) {
                    // Replace placeholders with real ac:image tags
                    $newHtml = $html;
                    foreach ($uploadedMap as $eid => $fnames) {
                        $acTags = implode('', array_map(
                            fn($f) => '<ac:image ac:thumbnail="true"><ri:attachment ri:filename="'.htmlspecialchars($f).'"/></ac:image>',
                            $fnames
                        ));
                        $newHtml = str_replace('__IMAGES_'.$eid.'__', $acTags, $newHtml);
                    }
                    // Update page with real image tags
                    $existing2  = self::confluenceGet("$baseUrl/rest/api/content/$createdPageId?expand=version", $imgUploadHeaders);
                    $newVersion = (int)($existing2['version']['number'] ?? 1) + 1;
                    $updatePayload = [
                        'version' => ['number' => $newVersion],
                        'title'   => $pageTitle,
                        'type'    => 'page',
                        'body'    => ['storage' => ['value' => self::wrapStorageFormat($newHtml), 'representation' => 'storage']],
                    ];
                    self::confluencePut("$baseUrl/rest/api/content/$createdPageId", $imgUploadHeaders, $updatePayload);
                }
            }
            return ['success' => true, 'url' => $pageUrl, 'title' => $resp['title'] ?? $pageTitle];
        }

        $msg = $resp['message'] ?? ($resp['reason'] ?? 'Unknown error');
        return ['error' => (string)$msg];
    }

    private static function buildEntriesHtml(array $entryIds, array $columns, array &$entryImageMap = []): string
    {
        $joins = "
            LEFT JOIN entry_types et       ON et.id = e.entry_type_id
            LEFT JOIN projects p           ON p.id  = e.project_id
            LEFT JOIN error_categories ec  ON ec.id = e.error_category_id
            LEFT JOIN test_environments te ON te.id = e.environment_id
            LEFT JOIN test_areas ta        ON ta.id = e.test_area_id
            LEFT JOIN users ua             ON ua.id = e.assigned_to
            LEFT JOIN users uc             ON uc.id = e.created_by
        ";
        $needImages      = in_array('images',      $columns);
        $needAttachments = in_array('attachments', $columns);
        $select = "e.*, et.name type_name, p.name project_name,
                   ec.name cat_name, te.name env_name, ta.name area_name,
                   ua.name assigned_name, uc.name creator_name,
                   ep.title epic_title, pe.title parent_title,
                   (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') FROM entry_tags etg
                    JOIN tags t ON t.id = etg.tag_id WHERE etg.entry_id = e.id) AS tag_names";
        $joins .= "
            LEFT JOIN epics ep ON ep.id = e.epic_id
            LEFT JOIN entries pe ON pe.id = e.parent_id
        ";

        if ($entryIds) {
            $in      = implode(',', array_fill(0, count($entryIds), '?'));
            $entries = Database::fetchAll("
                SELECT $select FROM entries e $joins
                WHERE e.id IN ($in)
                ORDER BY e.entry_date DESC, e.id DESC
            ", $entryIds);
        } else {
            $where  = ['e.is_merged = 0'];
            $params = [];
            // Apply _cf_* server-side filters from GET
            $cfMap = ['status'=>'e.status','priority'=>'e.priority','type'=>'et.name',
                      'category'=>'ec.name','project'=>'p.name','creator'=>'uc.name',
                      'serial'=>'e.mower_serial','firmware'=>'e.firmware_version','title'=>'e.title'];
            foreach ($cfMap as $cfKey => $cfExpr) {
                $cfVal = trim($_GET['_cf_'.$cfKey] ?? '');
                if ($cfVal === '') continue;
                $cfTerms = array_filter(array_map('trim', preg_split('/[,;]/', $cfVal)));
                if (!$cfTerms) continue;
                $cfClauses = array_map(fn($t) => "$cfExpr LIKE ?", $cfTerms);
                $where[] = '(' . implode(' OR ', $cfClauses) . ')';
                foreach ($cfTerms as $t) $params[] = "%$t%";
            }
            if (isset($_GET['cf_project_id']) && (int)$_GET['cf_project_id'] > 0) {
                $where[] = 'e.project_id=?'; $params[] = (int)$_GET['cf_project_id'];
            }
            $entries = Database::fetchAll("
                SELECT $select FROM entries e $joins
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.entry_date DESC, e.id DESC
            ", $params);
        }

        $colLabels = [
            'date'        => 'Datum',
            'time'        => 'Zeit',
            'type'        => 'Typ',
            'project'     => 'Projekt',
            'epic'        => 'Epic',
            'parent'      => 'Parent Ticket',
            'title'       => 'Titel',
            'description' => 'Beschreibung',
            'status'      => 'Status',
            'priority'    => 'Priorität',
            'serial'      => 'Seriennummer',
            'firmware'    => 'Firmware',
            'app_version' => 'App Version',
            'category'    => 'Kategorie',
            'environment' => 'Environment',
            'test_area'   => 'Test Area',
            'assigned_to' => 'Zugewiesen an',
            'creator'     => 'Ersteller',
            'tags'        => 'Tags',
            'temperature' => 'Temperatur',
            'weather'     => 'Wetter',
            'jira'        => 'Jira Issue',
            'zentao'      => 'Zentao Bug',
            'sharepoint'  => 'SharePoint',
            'images'      => 'Bilder',
            'attachments' => 'Anhänge',
        ];

        // Group by epic for better structure
        $entriesByEpic = [];
        foreach ($entries as $e) {
            $epicKey = $e['epic_id'] ? ($e['epic_title'] ?? 'Epic #'.$e['epic_id']) : '';
            $entriesByEpic[$epicKey][] = $e;
        }
        // Flatten back: epic header rows + entries, only if epic col selected
        $showEpicGroups = in_array('epic', $columns) && count(array_filter(array_keys($entriesByEpic))) > 1;

        $html  = '<h2>Entries</h2>';
        $html .= '<table><tbody>';
        $html .= '<tr>' . implode('', array_map(fn($c) => '<th><strong>' . ($colLabels[$c] ?? $c) . '</strong></th>', $columns)) . '</tr>';

        $lastEpicKey = null;
        foreach ($entries as $e) {
            // Insert epic group header row
            $epicKey = $e['epic_id'] ? ($e['epic_title'] ?? 'Epic #'.$e['epic_id']) : '';
            if ($showEpicGroups && $epicKey !== $lastEpicKey) {
                $lastEpicKey = $epicKey;
                if ($epicKey) {
                    $colSpan = count($columns);
                    $html .= '<tr><td colspan="'.$colSpan.'" style="background:#E6F0FF;padding:6px 8px">';
                    $html .= '<strong style="color:#0052CC">⚡ Epic: ' . htmlspecialchars($epicKey) . '</strong>';
                    $html .= '</td></tr>';
                }
            }
            $isSubTicket = !empty($e['parent_id']);
            $rowStyle    = $isSubTicket ? ' style="background:#F4F5F7"' : '';
            $html .= '<tr' . $rowStyle . '>';
            foreach ($columns as $col) {
                $val = match ($col) {
                    'date'        => htmlspecialchars($e['entry_date'] ?? ''),
                    'time'        => htmlspecialchars($e['entry_time'] ?? ''),
                    'type'        => htmlspecialchars($e['type_name'] ?? ''),
                    'project'     => htmlspecialchars($e['project_name'] ?? ''),
                    'title'       => (function() use ($e) {
                        $t = htmlspecialchars($e['title'] ?? '');
                        // Sub-ticket: indent with arrow
                        if (!empty($e['parent_id']) && !empty($e['parent_title'])) {
                            $parent = htmlspecialchars(mb_substr($e['parent_title'], 0, 40));
                            $t = '<span style="color:#666;font-size:.85em">&rarr; Sub von: ' . $parent . '</span><br/>' . $t;
                        }
                        return $t;
                    })(),
                    'description' => htmlspecialchars($e['description'] ?? ''),
                    'serial'      => htmlspecialchars($e['mower_serial'] ?? ''),
                    'firmware'    => htmlspecialchars($e['firmware_version'] ?? ''),
                    'app_version' => htmlspecialchars($e['app_version'] ?? ''),
                    'status'      => htmlspecialchars($e['status'] ?? ''),
                    'category'    => htmlspecialchars($e['cat_name'] ?? ''),
                    'environment' => htmlspecialchars($e['env_name'] ?? ''),
                    'test_area'   => htmlspecialchars($e['area_name'] ?? ''),
                    'assigned_to' => htmlspecialchars($e['assigned_name'] ?? ''),
                    'creator'     => htmlspecialchars($e['creator_name'] ?? ''),
                    'temperature' => $e['temperature'] !== null ? htmlspecialchars($e['temperature']) . ' °C' : '',
                    'weather'     => htmlspecialchars($e['weather_condition'] ?? ''),
                    'priority'    => htmlspecialchars($e['priority'] ?? ''),
                    'epic'        => (function() use ($e) {
                        if (empty($e['epic_title'])) return '';
                        return '<strong style="color:#0052CC">⚡ ' . htmlspecialchars($e['epic_title']) . '</strong>';
                    })(),
                    'parent'      => htmlspecialchars($e['parent_title'] ?? ''),
                    'tags'        => htmlspecialchars($e['tag_names'] ?? ''),
                    'jira'        => $e['jira_issue_key']
                        ? '<a href="' . htmlspecialchars($e['jira_issue_url'] ?? '') . '">' . htmlspecialchars($e['jira_issue_key']) . '</a>'
                        : '',
                    'zentao'      => $e['zentao_bug_id']
                        ? '<a href="' . htmlspecialchars($e['zentao_bug_url'] ?? '') . '">Bug #' . htmlspecialchars((string)$e['zentao_bug_id']) . '</a>'
                        : '',
                    'sharepoint'  => $e['sharepoint_folder_url']
                        ? '<a href="' . htmlspecialchars($e['sharepoint_folder_url']) . '">SharePoint</a>'
                        : '',
                    'images'      => (function() use ($e, &$entryImageMap) {
                        $atts = Database::fetchAll(
                            'SELECT original_name, mime_type FROM entry_attachments
                             WHERE entry_id=? AND mime_type LIKE "image/%" ORDER BY created_at',
                            [$e['id']]
                        );
                        if (empty($atts)) return '';
                        // Mark this entry for image upload after page creation
                        $entryImageMap[$e['id']] = count($atts);
                        // Use placeholder — replaced after upload
                        return '__IMAGES_' . $e['id'] . '__';
                    })(),
                    'attachments' => (function() use ($e) {
                        $atts = Database::fetchAll(
                            'SELECT original_name, mime_type FROM entry_attachments
                             WHERE entry_id=? ORDER BY created_at',
                            [$e['id']]
                        );
                        if (empty($atts)) return '';
                        $names = array_map(fn($a) => htmlspecialchars($a['original_name'] ?? 'Datei'), $atts);
                        return count($atts) . ' Datei(en): ' . implode(', ', $names);
                    })(),
                    default       => htmlspecialchars($e[$col] ?? ''),
                };
                $html .= "<td>$val</td>";
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Upload a single file as attachment to a Confluence page.
     * Returns the attachment filename on success, null on failure.
     */
    private static function uploadAttachment(string $baseUrl, array $authHeaders, string $pageId, string $filePath, string $fileName, string $mimeType): ?string
    {
        if (!file_exists($filePath)) return null;
        $fileData = file_get_contents($filePath);
        if ($fileData === false) return null;

        $boundary = '----ConfluenceBoundary' . uniqid();
        $crlf     = "\r\n";

        $body  = '--' . $boundary . $crlf;
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . $crlf;
        $body .= 'Content-Type: ' . $mimeType . $crlf . $crlf;
        $body .= $fileData . $crlf;
        $body .= '--' . $boundary . $crlf;
        $body .= 'Content-Disposition: form-data; name="minorEdit"' . $crlf . $crlf;
        $body .= 'true' . $crlf;
        $body .= '--' . $boundary . '--' . $crlf;

        $uploadHeaders = array_filter($authHeaders, fn($h) => !str_starts_with($h, 'Content-Type'));
        $uploadHeaders[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
        $uploadHeaders[] = 'X-Atlassian-Token: no-check';

        $ch = curl_init($baseUrl . '/rest/api/content/' . $pageId . '/child/attachment');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => array_values($uploadHeaders),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 300) ? $fileName : null;
    }


    /**
     * Upload all image attachments for given entry IDs to a Confluence page.
     * Returns map of entryId -> [filename, ...]
     */
    private static function uploadEntryImages(string $baseUrl, array $authHeaders, string $pageId, array $entryIds): array
    {
        if (empty($entryIds)) return [];
        $ph   = implode(',', array_fill(0, count($entryIds), '?'));
        $atts = Database::fetchAll(
            "SELECT entry_id, file_path, original_name, mime_type FROM entry_attachments
             WHERE entry_id IN ($ph) AND mime_type LIKE 'image/%'
             ORDER BY entry_id, created_at",
            $entryIds
        );
        $result = [];
        $uploaded = []; // track already-uploaded filenames to avoid duplicates
        foreach ($atts as $att) {
            $eid      = $att['entry_id'];
            $origName = $att['original_name'] ?: 'image.jpg';
            // Prefix with entry ID to avoid filename collisions
            $cfName   = 'entry' . $eid . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
            if (!isset($uploaded[$cfName])) {
                $ok = self::uploadAttachment($baseUrl, $authHeaders, $pageId, $att['file_path'], $cfName, $att['mime_type']);
                $uploaded[$cfName] = $ok !== null;
            }
            if ($uploaded[$cfName]) {
                $result[$eid][] = $cfName;
            }
        }
        return $result;
    }

    private static function buildInventoryHtml(array $itemIds, array $columns): string
    {
        if ($itemIds) {
            $in    = implode(',', array_fill(0, count($itemIds), '?'));
            $items = Database::fetchAll(
                "SELECT i.*, p.name project_name FROM inventory_items i
                 LEFT JOIN projects p ON p.id = i.project_id
                 WHERE i.id IN ($in) ORDER BY i.name",
                $itemIds
            );
        } else {
            $items = Database::fetchAll(
                'SELECT i.*, p.name project_name FROM inventory_items i
                 LEFT JOIN projects p ON p.id = i.project_id ORDER BY i.name'
            );
        }

        $colLabels = [
            'name'      => 'Name',
            'serial'    => 'Serial No.',
            'project'   => 'Project',
            'firmware'  => 'Firmware',
            'status'    => 'Status',
            'location'  => 'Location',
            'notes'     => 'Notes',
            'comment'   => 'Comment',
            'purchased' => 'Purchased',
        ];

        $html  = '<h2>Inventory</h2>';
        $html .= '<table><tbody>';
        $html .= '<tr>' . implode('', array_map(fn($c) => '<th>' . ($colLabels[$c] ?? $c) . '</th>', $columns)) . '</tr>';
        foreach ($items as $item) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $val = match ($col) {
                    'name'      => htmlspecialchars($item['name'] ?? ''),
                    'serial'    => htmlspecialchars($item['serial_number'] ?? ''),
                    'project'   => htmlspecialchars($item['project_name'] ?? ''),
                    'firmware'  => htmlspecialchars($item['firmware_version'] ?? ''),
                    'status'    => htmlspecialchars($item['status'] ?? ''),
                    'location'  => htmlspecialchars($item['location'] ?? ''),
                    'notes'     => htmlspecialchars($item['notes'] ?? ''),
                    'comment'   => htmlspecialchars($item['comment'] ?? ''),
                    'purchased' => htmlspecialchars($item['purchased_at'] ?? ''),
                    default     => '',
                };
                $html .= "<td>$val</td>";
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    private static function buildMowerHistoryHtml(array $serials, array $entryColumns): string
    {
        if (empty($serials)) {
            $rows    = Database::fetchAll("SELECT serial_number FROM inventory_items WHERE serial_number IS NOT NULL AND serial_number != '' ORDER BY name");
            $serials = array_column($rows, 'serial_number');
        }

        if (empty($serials)) {
            return '<p>No mowers with serial numbers found.</p>';
        }

        $colLabels = [
            'date'        => 'Date',
            'time'        => 'Time',
            'type'        => 'Type',
            'title'       => 'Title',
            'description' => 'Description',
            'firmware'    => 'Firmware',
            'app_version' => 'App Version',
            'status'      => 'Status',
            'jira'        => 'Jira Issue',
        ];

        $html = '<h2>Mower History</h2>';

        foreach ($serials as $serial) {
            $item    = Database::fetchOne(
                'SELECT i.*, p.name project_name FROM inventory_items i LEFT JOIN projects p ON p.id = i.project_id WHERE i.serial_number = ?',
                [$serial]
            );
            $entries = Database::fetchAll(
                "SELECT e.*, et.name type_name FROM entries e
                 LEFT JOIN entry_types et ON et.id = e.entry_type_id
                 WHERE e.mower_serial = ?
                 ORDER BY e.entry_date DESC, e.id DESC",
                [$serial]
            );

            $html .= '<h3>' . htmlspecialchars($serial);
            if ($item && !empty($item['name'])) {
                $html .= ' – ' . htmlspecialchars($item['name']);
            }
            $html .= '</h3>';

            if ($item) {
                $html .= '<p>'
                    . '<strong>Project:</strong> ' . htmlspecialchars($item['project_name'] ?? '–') . ' &nbsp;|&nbsp; '
                    . '<strong>Firmware:</strong> ' . htmlspecialchars($item['firmware_version'] ?? '–') . ' &nbsp;|&nbsp; '
                    . '<strong>Status:</strong> '   . htmlspecialchars($item['status'] ?? '–')
                    . '</p>';
            }

            if ($entries) {
                $html .= '<table><tbody>';
                $html .= '<tr>' . implode('', array_map(fn($c) => '<th>' . ($colLabels[$c] ?? $c) . '</th>', $entryColumns)) . '</tr>';
                foreach ($entries as $e) {
                    $html .= '<tr>';
                    foreach ($entryColumns as $col) {
                        $val = match ($col) {
                            'date'        => htmlspecialchars($e['entry_date'] ?? ''),
                            'time'        => htmlspecialchars($e['entry_time'] ?? ''),
                            'type'        => htmlspecialchars($e['type_name'] ?? ''),
                            'title'       => (function() use ($e) {
                        $t = htmlspecialchars($e['title'] ?? '');
                        // Sub-ticket: indent with arrow
                        if (!empty($e['parent_id']) && !empty($e['parent_title'])) {
                            $parent = htmlspecialchars(mb_substr($e['parent_title'], 0, 40));
                            $t = '<span style="color:#666;font-size:.85em">&rarr; Sub von: ' . $parent . '</span><br/>' . $t;
                        }
                        return $t;
                    })(),
                            'description' => htmlspecialchars($e['description'] ?? ''),
                            'firmware'    => htmlspecialchars($e['firmware_version'] ?? ''),
                            'app_version' => htmlspecialchars($e['app_version'] ?? ''),
                            'status'      => htmlspecialchars($e['status'] ?? ''),
                            'jira'        => htmlspecialchars($e['jira_issue_key'] ?? ''),
                            default       => '',
                        };
                        $html .= "<td>$val</td>";
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
            } else {
                $html .= '<p><em>No logbook entries for this mower.</em></p>';
            }
        }

        return $html;
    }

    private static function wrapStorageFormat(string $html): string
    {
        return '<p>' . date('Y-m-d H:i') . ' – Exported from RoboDoc</p>' . $html;
    }

    private static function confluenceGet(string $url, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 15]);
        $r = curl_exec($ch); curl_close($ch);
        return json_decode($r ?: '{}', true) ?? [];
    }

    private static function confluencePost(string $url, array $headers, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 20]);
        $r = curl_exec($ch); curl_close($ch);
        return json_decode($r ?: '{}', true) ?? [];
    }

    private static function confluencePut(string $url, array $headers, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 20]);
        $r = curl_exec($ch); curl_close($ch);
        return json_decode($r ?: '{}', true) ?? [];
    }
}
