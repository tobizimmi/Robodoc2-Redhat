<?php
declare(strict_types=1);

class TestSessionController
{
    // ── List ──────────────────────────────────────────────────────
    public static function index(): void
    {
        Auth::requireView('testing');
        [$projSql, $projParams] = self::sessionProjectClause('ts');
        $sessions = Database::fetchAll("
            SELECT ts.*, p.name project_name, ta.name area_name, u.name operator_name,
                   (SELECT COUNT(*) FROM entries WHERE session_id = ts.id) entry_count
            FROM test_sessions ts
            LEFT JOIN projects   p  ON p.id  = ts.project_id
            LEFT JOIN test_areas ta ON ta.id = ts.test_area_id
            LEFT JOIN users      u  ON u.id  = ts.operator_id
            WHERE $projSql
            ORDER BY ts.started_at DESC
        ", $projParams);
        $activeId = $_SESSION['active_session_id'] ?? null;
        View::render('test-sessions/index', compact('sessions', 'activeId') + ['title' => 'Test Sessions']);
    }

    // ── Show ──────────────────────────────────────────────────────
    public static function show(string $id): void
    {
        Auth::requireView('testing');
        $session = self::findOr404((int)$id);
        $entries = Database::fetchAll("
            SELECT e.id, e.title, e.description, e.entry_date, e.entry_time,
                   e.firmware_version, e.mower_serial, e.status, e.temperature, e.weather_condition,
                   et.name type_name, et.color type_color,
                   ec.name cat_name,  ec.color cat_color,
                   p.name project_name,
                   (SELECT COUNT(*) FROM entry_attachments WHERE entry_id = e.id) att_count,
                   (SELECT id FROM entry_attachments WHERE entry_id=e.id AND mime_type LIKE 'image/%' ORDER BY id LIMIT 1) thumb_id
            FROM entries e
            LEFT JOIN entry_types et      ON et.id = e.entry_type_id
            LEFT JOIN error_categories ec ON ec.id = e.error_category_id
            LEFT JOIN projects p          ON p.id  = e.project_id
            WHERE e.session_id = ?
            ORDER BY e.entry_date ASC, e.entry_time ASC, e.id ASC
        ", [(int)$id]);

        // Load test result sub-items for each entry
        $entryIds = array_column($entries, 'id');
        $testResults = [];
        if ($entryIds) {
            $ph = implode(',', array_fill(0, count($entryIds), '?'));
            foreach (Database::fetchAll("SELECT * FROM entry_test_results WHERE entry_id IN ($ph) ORDER BY entry_id, sort_order", $entryIds) as $tr) {
                $testResults[$tr['entry_id']][] = $tr;
            }
        }

        $byType = [];
        foreach ($entries as $e) {
            if ($e['type_name']) $byType[$e['type_name']] = ($byType[$e['type_name']] ?? 0) + 1;
        }
        arsort($byType);

        $project   = $session['project_id'] ? Database::fetchOne("SELECT * FROM projects WHERE id=?", [$session['project_id']]) : null;
        $area      = $session['test_area_id'] ? Database::fetchOne("SELECT * FROM test_areas WHERE id=?", [$session['test_area_id']]) : null;
        $settings  = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $activeId  = $_SESSION['active_session_id'] ?? null;

        $testOutcomes = array_filter(array_map('trim', explode(',', appSetting('test_result_outcomes', 'Passed,Failed,Blocked,Partial,Not Run'))));
        $testCycleLinked = $session['test_cycle_id'] ? Database::fetchOne('SELECT tc.*, tp.name plan_name FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id WHERE tc.id=?', [$session['test_cycle_id']]) : null;
        $testCaseLinked  = $session['test_plan_item_id'] ? Database::fetchOne('SELECT * FROM test_plan_items WHERE id=?', [$session['test_plan_item_id']]) : null;
        View::render('test-sessions/show', compact('session', 'entries', 'byType', 'project', 'area', 'settings', 'activeId', 'testOutcomes', 'testCycleLinked', 'testCaseLinked', 'testResults') + ['title' => $session['title']]);
    }

    // ── Create ────────────────────────────────────────────────────
    public static function create(): void
    {
        Auth::requireEdit('testing');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $title = trim($_POST['title'] ?? '');
            if (!$title) { flash('error', 'Title is required.'); redirect('/test-sessions/create'); }

            $id = Database::insert(
                "INSERT INTO test_sessions
                 (title, description, project_id, test_area_id, firmware_version, app_version,
                  operator_id, temperature, weather_condition, terrain_notes, created_by,
                  test_cycle_id, test_plan_item_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $title,
                    trim($_POST['description'] ?? '') ?: null,
                    (int)($_POST['project_id'] ?? 0) ?: null,
                    (int)($_POST['test_area_id'] ?? 0) ?: null,
                    trim($_POST['firmware_version'] ?? '') ?: null,
                    trim($_POST['app_version'] ?? '') ?: null,
                    (int)($_POST['operator_id'] ?? 0) ?: null,
                    ($_POST['temperature'] ?? '') !== '' ? (float)$_POST['temperature'] : null,
                    trim($_POST['weather_condition'] ?? '') ?: null,
                    trim($_POST['terrain_notes'] ?? '') ?: null,
                    Auth::id(),
                    (int)($_POST['test_cycle_id'] ?? 0) ?: null,
                    (int)($_POST['test_plan_item_id'] ?? 0) ?: null,
                ]
            );

            if (!empty($_POST['start_now'])) {
                $_SESSION['active_session_id'] = $id;
            }

            // Save mower links
            $mowerIds = array_filter(array_map('intval', (array)($_POST['mower_ids'] ?? [])));
            foreach ($mowerIds as $mid) {
                try { Database::execute('INSERT IGNORE INTO session_mowers (session_id, mower_id) VALUES (?,?)', [$id, $mid]); } catch (\Throwable) {}
            }

            flash('success', 'Session created.');
            redirect('/test-sessions/' . $id);
        }

        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects  = Database::fetchAll("SELECT p.id, p.name FROM projects p WHERE p.status='active' AND $pSql ORDER BY p.name", $pParams);
        $areas     = Database::fetchAll("SELECT id, name FROM test_areas ORDER BY name");
        $users     = Database::fetchAll("SELECT id, name FROM users ORDER BY name");
        $mowers    = Database::fetchAll("SELECT * FROM test_mowers ORDER BY label");
        $testCycles   = Database::fetchAll('SELECT tc.id, tc.name, tp.name plan_name FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id ORDER BY tc.created_at DESC LIMIT 100');
        $testOutcomes = array_filter(array_map('trim', explode(',', appSetting('test_result_outcomes', 'Passed,Failed,Blocked,Partial,Not Run'))));
        View::render('test-sessions/create', compact('projects', 'areas', 'users', 'mowers', 'testCycles', 'testOutcomes') + ['data' => [], 'selectedMowerIds' => [], 'title' => 'New Session']);
    }

    // ── Edit ──────────────────────────────────────────────────────
    public static function edit(string $id): void
    {
        Auth::requireEdit('testing');
        $session = self::findOr404((int)$id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            // Update mower links
            Database::execute('DELETE FROM session_mowers WHERE session_id=?', [(int)$id]);
            $mowerIds = array_filter(array_map('intval', (array)($_POST['mower_ids'] ?? [])));
            foreach ($mowerIds as $mid) {
                try { Database::execute('INSERT IGNORE INTO session_mowers (session_id, mower_id) VALUES (?,?)', [(int)$id, $mid]); } catch (\Throwable) {}
            }
            Database::execute(
                "UPDATE test_sessions SET
                 title=?, description=?, project_id=?, test_area_id=?, firmware_version=?, app_version=?,
                 operator_id=?, temperature=?, weather_condition=?, terrain_notes=?,
                 test_cycle_id=?, test_plan_item_id=?
                 WHERE id=?",
                [
                    trim($_POST['title'] ?? ''),
                    trim($_POST['description'] ?? '') ?: null,
                    (int)($_POST['project_id'] ?? 0) ?: null,
                    (int)($_POST['test_area_id'] ?? 0) ?: null,
                    trim($_POST['firmware_version'] ?? '') ?: null,
                    trim($_POST['app_version'] ?? '') ?: null,
                    (int)($_POST['operator_id'] ?? 0) ?: null,
                    ($_POST['temperature'] ?? '') !== '' ? (float)$_POST['temperature'] : null,
                    trim($_POST['weather_condition'] ?? '') ?: null,
                    trim($_POST['terrain_notes'] ?? '') ?: null,
                    (int)($_POST['test_cycle_id'] ?? 0) ?: null,
                    (int)($_POST['test_plan_item_id'] ?? 0) ?: null,
                    (int)$id,
                ]
            );
            flash('success', 'Session updated.');
            redirect('/test-sessions/' . $id);
        }
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects  = Database::fetchAll("SELECT p.id, p.name FROM projects p WHERE p.status='active' AND $pSql ORDER BY p.name", $pParams);
        $areas     = Database::fetchAll("SELECT id, name FROM test_areas ORDER BY name");
        $users     = Database::fetchAll("SELECT id, name FROM users ORDER BY name");
        $mowers    = Database::fetchAll("SELECT * FROM test_mowers ORDER BY label");
        $selectedMowerIds = array_column(Database::fetchAll('SELECT mower_id FROM session_mowers WHERE session_id=?', [(int)$id]), 'mower_id');
        $data      = $session;
        $testCycles   = Database::fetchAll('SELECT tc.id, tc.name, tp.name plan_name FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id ORDER BY tc.created_at DESC LIMIT 100');
        $testOutcomes = array_filter(array_map('trim', explode(',', appSetting('test_result_outcomes', 'Passed,Failed,Blocked,Partial,Not Run'))));
        View::render('test-sessions/edit', compact('session', 'data', 'projects', 'areas', 'users', 'mowers', 'selectedMowerIds', 'testCycles', 'testOutcomes') + ['title' => 'Edit: ' . $session['title']]);
    }

    // ── Delete ────────────────────────────────────────────────────
    public static function delete(string $id): void
    {
        Auth::requireEdit('testing');
        Auth::verifyCsrf();
        // Unlink entries but keep them
        Database::execute("UPDATE entries SET session_id=NULL WHERE session_id=?", [(int)$id]);
        Database::execute('DELETE FROM test_sessions WHERE id=?', [(int)$id]);
        if (($_SESSION['active_session_id'] ?? null) == $id) {
            unset($_SESSION['active_session_id']);
        }
        flash('success', 'Session deleted.');
        redirect('/test-sessions');
    }

    // ── Activate / Deactivate ─────────────────────────────────────
    public static function activate(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $session = self::findOr404((int)$id);
        if ($session['status'] !== 'active') {
            Database::execute("UPDATE test_sessions SET status='active', ended_at=NULL WHERE id=?", [(int)$id]);
        }
        $_SESSION['active_session_id'] = (int)$id;
        flash('success', 'Session is now active — new entries will be linked to it.');
        redirect('/test-sessions/' . $id);
    }

    public static function deactivate(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        if (($_SESSION['active_session_id'] ?? null) == $id) {
            unset($_SESSION['active_session_id']);
        }
        flash('success', 'Session deactivated.');
        redirect('/test-sessions/' . $id);
    }

    public static function complete(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        Database::execute(
            "UPDATE test_sessions SET status='completed', ended_at=NOW() WHERE id=?",
            [(int)$id]
        );
        if (($_SESSION['active_session_id'] ?? null) == $id) {
            unset($_SESSION['active_session_id']);
        }
        flash('success', 'Session marked as completed.');
        redirect('/test-sessions/' . $id);
    }

    // ── Export ────────────────────────────────────────────────────
    public static function export(string $id): void
    {
        Auth::require();
        $session = self::findOr404((int)$id);
        $format  = $_GET['format'] ?? 'pdf';

        $entries = Database::fetchAll("
            SELECT e.*, et.name type_name, et.color type_color,
                   ec.name cat_name, p.name project_name, u.name creator
            FROM entries e
            LEFT JOIN entry_types et      ON et.id = e.entry_type_id
            LEFT JOIN error_categories ec ON ec.id = e.error_category_id
            LEFT JOIN projects p          ON p.id  = e.project_id
            LEFT JOIN users u             ON u.id  = e.created_by
            WHERE e.session_id = ?
            ORDER BY e.entry_date ASC, e.entry_time ASC
        ", [(int)$id]);

        $area = $session['test_area_id'] ? Database::fetchOne("SELECT * FROM test_areas WHERE id=?", [$session['test_area_id']]) : null;

        if ($format === 'word') {
            self::exportWord($session, $entries, $area);
        } elseif ($format === 'confluence') {
            self::exportConfluence($session, $entries, $area);
        } else {
            // PDF: render printable HTML, browser handles Save as PDF
            View::render('test-sessions/print', compact('session', 'entries', 'area') + ['title' => $session['title']], 'print');
        }
    }

    private static function exportWord(array $session, array $entries, ?array $area): void
    {
        $html = self::buildReportHtml($session, $entries, $area);
        $filename = 'session-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($session['title'])) . '.doc';
        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
body{font-family:Arial,sans-serif;font-size:11pt}
h1{font-size:16pt}h2{font-size:13pt}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #ccc;padding:4px 8px;font-size:10pt}
th{background:#eee;font-weight:bold}
.badge{padding:2px 6px;border-radius:3px;color:#fff;font-size:9pt}
</style></head><body>' . $html . '</body></html>';
        exit;
    }

    private static function exportConfluence(array $session, array $entries, ?array $area): void
    {
        $settings = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $user     = Database::fetchOne('SELECT confluence_email, confluence_token FROM users WHERE id=?', [Auth::id()]);
        $baseUrl  = rtrim($settings['confluence_url'] ?? '', '/');
        $email    = trim($user['confluence_email'] ?? '');
        $token    = trim($user['confluence_token'] ?? '');

        if (!$baseUrl || !$token) {
            json(['error' => 'Confluence not configured. Check Admin → Settings and your Profile.']);
        }

        $spaceKey  = strtoupper(trim($_POST['space_key'] ?? $settings['confluence_default_space'] ?? ''));
        $parentId  = trim($_POST['parent_id'] ?? '');
        if (!$spaceKey) { json(['error' => 'Space key required.']); }

        $html       = self::buildReportHtml($session, $entries, $area);
        $body       = '<p>' . date('Y-m-d H:i') . ' – Exported from RoboDoc</p>' . $html;
        $authHeader = $email ? 'Basic ' . base64_encode("$email:$token") : 'Bearer ' . $token;
        $headers    = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $authHeader];

        $payload = [
            'type'  => 'page',
            'title' => 'Session: ' . $session['title'],
            'space' => ['key' => $spaceKey],
            'body'  => ['storage' => ['value' => $body, 'representation' => 'storage']],
        ];
        if ($parentId) $payload['ancestors'] = [['id' => $parentId]];

        $ch = curl_init($baseUrl . '/rest/api/content');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 20]);
        $r    = curl_exec($ch); curl_close($ch);
        $resp = json_decode($r ?: '{}', true) ?? [];

        if (isset($resp['id'])) {
            $url = $baseUrl . (!empty($resp['_links']['webui']) ? $resp['_links']['webui'] : "/wiki/spaces/$spaceKey/pages/{$resp['id']}");
            json(['success' => true, 'url' => $url, 'title' => $resp['title'] ?? '']);
        }
        json(['error' => $resp['message'] ?? 'Unknown error']);
    }

    private static function buildReportHtml(array $s, array $entries, ?array $area): string
    {
        $dur = '';
        if ($s['started_at'] && $s['ended_at']) {
            $diff = (strtotime($s['ended_at']) - strtotime($s['started_at'])) / 60;
            $dur  = round($diff) . ' min';
        }
        $h  = '<h1>' . htmlspecialchars($s['title']) . '</h1>';
        $h .= '<table><tbody>';
        if ($s['firmware_version'])   $h .= '<tr><th>Firmware</th><td>' . htmlspecialchars($s['firmware_version']) . '</td></tr>';
        if ($s['app_version'])        $h .= '<tr><th>App Version</th><td>' . htmlspecialchars($s['app_version']) . '</td></tr>';
        if ($area)                    $h .= '<tr><th>Test Area</th><td>' . htmlspecialchars($area['name']) . '</td></tr>';
        if ($s['weather_condition'])  $h .= '<tr><th>Weather</th><td>' . htmlspecialchars($s['weather_condition']) . '</td></tr>';
        if ($s['temperature'] !== null) $h .= '<tr><th>Temperature</th><td>' . $s['temperature'] . ' °C</td></tr>';
        if ($s['started_at'])         $h .= '<tr><th>Started</th><td>' . htmlspecialchars($s['started_at']) . ($dur ? " (duration: $dur)" : '') . '</td></tr>';
        if ($s['description'])        $h .= '<tr><th>Notes</th><td>' . nl2br(htmlspecialchars($s['description'])) . '</td></tr>';
        $h .= '</tbody></table>';

        $h .= '<h2>Entries (' . count($entries) . ')</h2>';
        if ($entries) {
            $h .= '<table><tbody><tr><th>Date</th><th>Type</th><th>Serial</th><th>Firmware</th><th>Title / Description</th></tr>';
            foreach ($entries as $e) {
                $h .= sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($e['entry_date']),
                    htmlspecialchars($e['type_name'] ?? ''),
                    htmlspecialchars($e['mower_serial'] ?? ''),
                    htmlspecialchars($e['firmware_version'] ?? ''),
                    htmlspecialchars($e['title'] ?: substr($e['description'] ?? '', 0, 100))
                );
            }
            $h .= '</tbody></table>';
        }
        return $h;
    }

    // ── Helper ────────────────────────────────────────────────────
    private static function findOr404(int $id): array
    {
        $s = Database::fetchOne("SELECT * FROM test_sessions WHERE id=?", [$id]);
        if (!$s) { http_response_code(404); exit('Session not found'); }
        return $s;
    }

    private static function sessionProjectClause(string $alias): array {
        $ids = Auth::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ["$alias.project_id IS NULL", []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.project_id IS NULL OR $alias.project_id IN ($ph))", $ids];
    }

    // Called by EntryController to pre-fill session metadata
    public static function getActive(): ?array
    {
        $sid = $_SESSION['active_session_id'] ?? null;
        if (!$sid) return null;
        $s = Database::fetchOne("SELECT * FROM test_sessions WHERE id=? AND status='active'", [(int)$sid]);
        if (!$s) { unset($_SESSION['active_session_id']); return null; }
        return $s;
    }
}
