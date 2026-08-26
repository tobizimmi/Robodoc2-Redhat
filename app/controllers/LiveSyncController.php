<?php
declare(strict_types=1);

/**
 * Live-Sync: pushes newly created entries (incl. attachments) from this
 * RoboDoc2 instance to another one, e.g. classic Live (zimmimail.de) -> the
 * OpenShift/RedHat instance. One-way, creation-only (no updates, no back-sync).
 *
 * Symmetric, config-driven code shared by both instances (like the Zentao
 * relay): whichever side has "live_sync_target_url" configured pushes;
 * whichever side receives calls just needs "live_sync_secret" set. The same
 * secret is used both ways (Bearer token when pushing, verified when
 * receiving) so a single settings page works on either end.
 *
 * Reliability: every push attempt is logged in live_sync_queue. A failed/
 * unreachable attempt stays "pending" and is retried by the cron job
 * (app/cron/live_sync.php) so a briefly-down target doesn't lose data.
 *
 * Projects and entry types must already exist with matching names on both
 * systems — the ingest side resolves them by name and rejects unknown ones
 * rather than auto-creating (avoids silent drift between the two DBs). Tags
 * are low-risk and are auto-created on the receiving side if missing.
 */
class LiveSyncController
{
    private const MIME_EXT = [
        'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
        'image/svg+xml'=>'svg','image/heic'=>'heic','image/heif'=>'heif',
        'video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm',
        'video/x-msvideo'=>'avi','video/x-matroska'=>'mkv',
        'application/pdf'=>'pdf','application/zip'=>'zip',
        'text/plain'=>'txt','text/csv'=>'csv','application/json'=>'json',
        'application/octet-stream'=>'bin',
    ];

    // -- Push: called right after an entry (+ its attachments) is fully saved --
    public static function pushEntry(int $entryId): void
    {
        $targetUrl = trim(appSetting('live_sync_target_url'));
        $secret    = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$targetUrl || !$secret) return; // not configured on this instance

        $queueId = Database::insert('INSERT INTO live_sync_queue (entry_id, status) VALUES (?,?)', [$entryId, 'pending']);
        self::attemptSend($queueId, $entryId, $targetUrl, $secret);
    }

    // -- Cron entry point: retry everything still pending --
    public static function runQueueRetries(): int
    {
        $targetUrl = trim(appSetting('live_sync_target_url'));
        $secret    = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$targetUrl || !$secret) return 0;

        $rows = Database::fetchAll("SELECT * FROM live_sync_queue WHERE status='pending' AND attempts < 10 ORDER BY id");
        $sent = 0;
        foreach ($rows as $row) {
            if (self::attemptSend((int)$row['id'], (int)$row['entry_id'], $targetUrl, $secret)) $sent++;
        }
        return $sent;
    }

    private static function attemptSend(int $queueId, int $entryId, string $targetUrl, string $secret): bool
    {
        $payload = self::buildPayload($entryId);
        if (!$payload) {
            Database::execute("UPDATE live_sync_queue SET status='failed', last_error=? WHERE id=?", ['Entry not found', $queueId]);
            return false;
        }

        $ch = curl_init($targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $secret],
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code === 200) {
            Database::execute("UPDATE live_sync_queue SET status='sent', sent_at=NOW() WHERE id=?", [$queueId]);
            return true;
        }
        Database::execute(
            "UPDATE live_sync_queue SET attempts=attempts+1, last_error=? WHERE id=?",
            [$err ?: ('HTTP ' . $code . ': ' . substr((string)$resp, 0, 500)), $queueId]
        );
        return false;
    }

    private static function buildPayload(int $entryId): ?array
    {
        $e = Database::fetchOne("
            SELECT e.id, e.title, e.description, e.entry_date, e.entry_time, e.status, e.priority,
                   e.mower_serial, e.firmware_version, e.app_version, e.project_status_robot,
                   p.name AS project_name, et.name AS entry_type_name,
                   u.name AS creator_name, u.email AS creator_email
            FROM entries e
            LEFT JOIN projects p ON p.id = e.project_id
            LEFT JOIN entry_types et ON et.id = e.entry_type_id
            LEFT JOIN users u ON u.id = e.created_by
            WHERE e.id = ?
        ", [$entryId]);
        if (!$e) return null;

        $tags = array_column(Database::fetchAll(
            'SELECT t.name FROM entry_tags et JOIN tags t ON t.id = et.tag_id WHERE et.entry_id = ?', [$entryId]
        ), 'name');

        $attachments = Database::fetchAll(
            'SELECT id, original_name, mime_type, file_size FROM entry_attachments WHERE entry_id = ?', [$entryId]
        );
        $atts = array_map(fn($a) => [
            'original_name' => $a['original_name'],
            'mime_type'     => $a['mime_type'],
            'file_size'     => (int)$a['file_size'],
            'download_url'  => self::buildAttachmentUrl((int)$a['id']),
        ], $attachments);

        return [
            'origin_id'             => (int)$e['id'],
            'title'                 => $e['title'],
            'description'           => $e['description'],
            'entry_date'            => $e['entry_date'],
            'entry_time'            => $e['entry_time'],
            'status'                => $e['status'],
            'priority'              => $e['priority'],
            'mower_serial'          => $e['mower_serial'],
            'firmware_version'      => $e['firmware_version'],
            'app_version'           => $e['app_version'],
            'project_status_robot'  => $e['project_status_robot'],
            'project_name'          => $e['project_name'],
            'entry_type_name'       => $e['entry_type_name'],
            'creator_name'          => $e['creator_name'],
            'creator_email'         => $e['creator_email'],
            'tags'                  => $tags,
            'attachments'           => $atts,
        ];
    }

    // -- Signed, self-verifying attachment URL (HMAC over APP_SECRET, no DB row) --
    private static function buildAttachmentUrl(int $attachmentId): string
    {
        $payload = json_encode(['att' => $attachmentId, 'exp' => time() + 3 * 86400]);
        $sig     = hash_hmac('sha256', $payload, APP_SECRET);
        $token   = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . $sig;
        return rtrim(appSetting('app_url', ''), '/') . BASE_URL . '/api/sync/attachment/' . $token;
    }

    private static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;
        [$b64, $sig] = $parts;
        $payload = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($payload === false) return null;
        if (!hash_equals(hash_hmac('sha256', $payload, APP_SECRET), $sig)) return null;
        $data = json_decode($payload, true);
        if (!is_array($data) || ($data['exp'] ?? 0) < time()) return null;
        return $data;
    }

    // -- Serve one attachment's bytes for a valid signed token (called by the other instance) --
    public static function attachmentDownload(string $token): void
    {
        $data = self::verifyToken($token);
        if (!$data || empty($data['att'])) { abort(404); }
        $att = Database::fetchOne('SELECT * FROM entry_attachments WHERE id=?', [(int)$data['att']]);
        if (!$att || !is_file($att['file_path'])) { abort(404); }
        header('Content-Type: ' . $att['mime_type']);
        header('Content-Length: ' . filesize($att['file_path']));
        header('Content-Disposition: inline; filename="' . basename($att['original_name']) . '"');
        readfile($att['file_path']);
        exit;
    }

    // -- Ingest: receive a pushed entry from the other instance --
    public static function receiveEntry(): void
    {
        header('Content-Type: application/json');
        $secret = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$secret) { json(['error' => 'Live-Sync not configured on this instance.'], 500); }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($authHeader === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) { if (strtolower($k) === 'authorization') { $authHeader = $v; break; } }
        }
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m) || !hash_equals($secret, $m[1])) {
            json(['error' => 'Unauthorized'], 401);
        }

        $body = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($body) || empty($body['origin_id']) || empty($body['title'])) {
            json(['error' => 'Bad request'], 400);
        }

        // Idempotent: a retried/duplicate push for the same origin entry is a no-op.
        $existing = Database::fetchOne('SELECT id FROM entries WHERE live_origin_id=?', [(int)$body['origin_id']]);
        if ($existing) { json(['ok' => true, 'entry_id' => (int)$existing['id'], 'note' => 'already imported']); }

        $project = Database::fetchOne('SELECT id FROM projects WHERE name=?', [(string)($body['project_name'] ?? '')]);
        if (!$project) { json(['error' => 'Unknown project: ' . ($body['project_name'] ?? '(none)')], 422); }
        $entryType = Database::fetchOne('SELECT id FROM entry_types WHERE name=?', [(string)($body['entry_type_name'] ?? '')]);
        if (!$entryType) { json(['error' => 'Unknown entry type: ' . ($body['entry_type_name'] ?? '(none)')], 422); }

        $creator = !empty($body['creator_email'])
            ? Database::fetchOne('SELECT id FROM users WHERE email=?', [$body['creator_email']])
            : null;

        $entryId = Database::insert(
            'INSERT INTO entries (project_id, entry_type_id, entry_date, entry_time, title, description, status, priority,
                mower_serial, firmware_version, app_version, project_status_robot, created_by, live_origin_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $project['id'], $entryType['id'],
                $body['entry_date'] ?? date('Y-m-d'), $body['entry_time'] ?? '00:00:00',
                $body['title'], $body['description'] ?? '', $body['status'] ?? 'new', $body['priority'] ?? 'Medium',
                $body['mower_serial'] ?? null, $body['firmware_version'] ?? null, $body['app_version'] ?? null,
                $body['project_status_robot'] ?? null, $creator['id'] ?? null, (int)$body['origin_id'],
            ]
        );

        // Tags are low-risk (unlike projects/types) — auto-create if missing.
        foreach ((array)($body['tags'] ?? []) as $tagName) {
            $tagName = trim((string)$tagName);
            if ($tagName === '') continue;
            $tag   = Database::fetchOne('SELECT id FROM tags WHERE name=?', [$tagName]);
            $tagId = $tag['id'] ?? Database::insert('INSERT INTO tags (name) VALUES (?)', [$tagName]);
            Database::execute('INSERT IGNORE INTO entry_tags (entry_id, tag_id) VALUES (?,?)', [$entryId, $tagId]);
        }

        // Attachments: fetch each from its signed download URL (server-to-server), never
        // from a caller-supplied arbitrary host — the source host is allow-listed.
        $sourceHost = trim(appSetting('live_sync_source_host'));
        $fetched = 0;
        foreach ((array)($body['attachments'] ?? []) as $att) {
            $url = (string)($att['download_url'] ?? '');
            if (!$url) continue;
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $host   = parse_url($url, PHP_URL_HOST) ?: '';
            if (!in_array($scheme, ['http', 'https'], true)) continue;
            if ($sourceHost && strcasecmp($host, $sourceHost) !== 0) continue;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $bytes = curl_exec($ch);
            $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($bytes === false || $code !== 200) continue;

            $mime = (string)($att['mime_type'] ?? 'application/octet-stream');
            $ext  = self::MIME_EXT[$mime] ?? 'bin';
            $fn   = bin2hex(random_bytes(16)) . '.' . $ext;
            $dir  = UPLOAD_DIR . $entryId . '/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $dest = $dir . $fn;
            if (@file_put_contents($dest, $bytes) === false) continue;

            Database::insert(
                'INSERT INTO entry_attachments (entry_id, filename, original_name, mime_type, file_size, file_path) VALUES (?,?,?,?,?,?)',
                [$entryId, $fn, $att['original_name'] ?? $fn, $mime, strlen($bytes), $dest]
            );
            $fetched++;
        }
        if ($fetched) {
            try { Database::execute('UPDATE entries SET attachments_updated_at=NOW() WHERE id=?', [$entryId]); } catch (Throwable) {}
        }

        json(['ok' => true, 'entry_id' => $entryId, 'attachments_synced' => $fetched]);
    }
}
