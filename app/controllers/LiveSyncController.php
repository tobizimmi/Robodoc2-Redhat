<?php
declare(strict_types=1);

/**
 * Live-Sync: transfers newly created entries (incl. attachments) from this
 * RoboDoc2 instance to another one, e.g. classic Live (zimmimail.de) -> the
 * OpenShift/RedHat instance. One-way, creation-only (no updates, no back-sync).
 *
 * Two transport modes, both symmetric/config-driven (same code on both
 * instances, like the Zentao relay) so either side can play either role:
 *
 *  - PUSH: the source calls the target's ingest endpoint directly
 *    (live_sync_target_url configured on the source). Only works if the
 *    target is reachable from the source.
 *  - PULL: the target periodically (cron) asks the source "what's new" and
 *    fetches it (live_sync_pull_source_url configured on the puller). Use
 *    this when the target (e.g. an OpenShift Route) cannot be reached from
 *    outside at all — exactly the situation that made the Zentao relay
 *    necessary: only the direction "target reaches out" works, so the
 *    target must be the one initiating every call.
 *
 * Every entry is queued in live_sync_queue on creation regardless of mode;
 * PUSH marks a row 'sent' on a successful direct call, PULL marks it 'sent'
 * via an explicit ack call from the puller once ingested. A cron job
 * (app/cron/live_sync.php) retries PUSH failures and runs the PULL check.
 *
 * Projects and entry types must already exist with matching names on both
 * systems — the ingest side resolves them by name and rejects unknown ones
 * rather than auto-creating (avoids silent drift between the two DBs). Tags
 * are low-risk and are auto-created on the receiving side if missing.
 */
class LiveSyncController
{
    // Hard cap on how many entries a single pending-list / pull cycle can return,
    // so neither a runaway queue nor a malicious caller can force this instance
    // to process an unbounded batch in one go.
    private const MAX_BATCH = 50;

    private const MIME_EXT = [
        'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
        'image/svg+xml'=>'svg','image/heic'=>'heic','image/heif'=>'heif',
        'video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm',
        'video/x-msvideo'=>'avi','video/x-matroska'=>'mkv',
        'application/pdf'=>'pdf','application/zip'=>'zip',
        'text/plain'=>'txt','text/csv'=>'csv','application/json'=>'json',
        'application/octet-stream'=>'bin',
    ];

    // ============================== PUSH (source -> target, direct) =========

    // -- Called right after an entry (+ its attachments) is fully saved. Always
    // queues the entry (so PULL can find it later); additionally attempts a
    // direct PUSH if a target URL is configured.
    public static function pushEntry(int $entryId): void
    {
        if (!self::liveSyncEnabled()) return; // master switch off - don't even queue

        $queueId = Database::insert('INSERT INTO live_sync_queue (entry_id, status) VALUES (?,?)', [$entryId, 'pending']);

        $targetUrl = trim(appSetting('live_sync_target_url'));
        $secret    = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$targetUrl || !$secret) return; // PUSH not configured - PULL (if set up on the other side) will pick it up

        self::attemptSend($queueId, $entryId, $targetUrl, $secret);
    }

    // -- Cron entry point: retry every PUSH that's still pending --
    public static function runQueueRetries(): int
    {
        if (!self::liveSyncEnabled()) return 0;
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

    // -- Ingest: receive a directly-pushed entry from the source (PUSH mode) --
    public static function receiveEntry(): void
    {
        header('Content-Type: application/json');
        self::guardApi();
        $secret = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$secret) { json(['error' => 'Live-Sync not configured on this instance.'], 500); }
        self::checkSecretAuth($secret);

        $body = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($body)) { json(['error' => 'Bad request'], 400); }

        $result = self::ingestPayload($body);
        $code = $result['code'] ?? ($result['ok'] ? 200 : 500);
        unset($result['code']);
        json($result, $code);
    }

    // ============================== PULL (target asks source) ===============

    // -- Source side: list entries still waiting to be picked up --
    public static function listPending(): void
    {
        header('Content-Type: application/json');
        self::guardApi();
        $secret = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$secret) { json(['error' => 'Live-Sync not configured on this instance.'], 500); }
        self::checkSecretAuth($secret);

        // Capped so a single pull cycle (and thus a single downstream ingest burst)
        // can never process more than this many entries at once.
        $rows = Database::fetchAll("SELECT id, entry_id FROM live_sync_queue WHERE status='pending' ORDER BY id LIMIT " . self::MAX_BATCH);
        json(['queue' => $rows]);
    }

    // -- Source side: full payload for one entry, same shape as the PUSH body --
    public static function entryDetail(string $id): void
    {
        header('Content-Type: application/json');
        self::guardApi();
        $secret = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$secret) { json(['error' => 'Live-Sync not configured on this instance.'], 500); }
        self::checkSecretAuth($secret);

        $payload = self::buildPayload((int)$id);
        if (!$payload) { json(['error' => 'Entry not found'], 404); }
        json(['entry' => $payload]);
    }

    // -- Source side: puller confirms it has ingested these queue rows --
    public static function ackSent(): void
    {
        header('Content-Type: application/json');
        self::guardApi();
        $secret = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$secret) { json(['error' => 'Live-Sync not configured on this instance.'], 500); }
        self::checkSecretAuth($secret);

        $body = json_decode(file_get_contents('php://input') ?: '', true);
        $ids  = array_slice(array_values(array_filter(array_map('intval', (array)($body['queue_ids'] ?? [])))), 0, self::MAX_BATCH);
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            Database::execute("UPDATE live_sync_queue SET status='sent', sent_at=NOW() WHERE id IN ($ph)", $ids);
        }
        json(['ok' => true, 'acked' => count($ids)]);
    }

    // -- Puller side: cron entry point. Asks the source what's pending, fetches
    // and ingests each one, then acks the ones that succeeded. Use this when
    // the source cannot reach this instance directly (e.g. this instance's
    // ingress isn't publicly reachable) - this instance reaches OUT instead,
    // the same reasoning as the Zentao relay.
    public static function pullFromSource(): int
    {
        if (!self::liveSyncEnabled()) return 0;
        $sourceUrl = rtrim(trim(appSetting('live_sync_pull_source_url')), '/');
        $secret    = trim(Encryption::decrypt((string)appSetting('live_sync_secret')));
        if (!$sourceUrl || !$secret) return 0;

        $pending = self::httpGetJson($sourceUrl . '/api/sync/pending', $secret);
        if (!$pending || empty($pending['queue'])) return 0;

        $doneIds  = [];
        $imported = 0;
        foreach ($pending['queue'] as $row) {
            $entryId = (int)($row['entry_id'] ?? 0);
            $queueId = (int)($row['id'] ?? 0);
            if (!$entryId || !$queueId) continue;

            $detail = self::httpGetJson($sourceUrl . '/api/sync/entry/' . $entryId, $secret);
            if (!$detail || empty($detail['entry'])) continue;

            $result = self::ingestPayload($detail['entry']);
            if ($result['ok']) {
                $imported++;
                $doneIds[] = $queueId;
            }
        }
        if ($doneIds) {
            self::httpPostJson($sourceUrl . '/api/sync/ack', $secret, ['queue_ids' => $doneIds]);
        }
        return $imported;
    }

    private static function httpGetJson(string $url, string $secret): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secret],
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $code !== 200) return null;
        $data = json_decode($resp, true);
        return is_array($data) ? $data : null;
    }

    private static function httpPostJson(string $url, string $secret, array $body): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $secret],
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $code !== 200) return null;
        $data = json_decode($resp, true);
        return is_array($data) ? $data : null;
    }

    // ============================== shared =====================================

    private static function liveSyncEnabled(): bool
    {
        return appSetting('live_sync_enabled') === '1';
    }

    // Master switch + rate-limit gate, called first thing by every JSON API
    // endpoint below (before touching the database for anything else).
    private static function guardApi(): void
    {
        if (!self::liveSyncEnabled()) { json(['error' => 'Live-Sync is disabled on this instance.'], 503); }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (self::tooManyFailedAuth($ip) || self::tooManyRequests($ip)) {
            json(['error' => 'Too many requests.'], 429);
        }
        self::logRateEvent($ip, 'request');
    }

    // Same gate for attachmentDownload(), which isn't a JSON endpoint (aborts
    // instead of returning a JSON error body).
    private static function guardAttachmentApi(): void
    {
        if (!self::liveSyncEnabled()) { abort(503, 'Live-Sync is disabled on this instance.'); }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (self::tooManyRequests($ip)) { abort(429, 'Too many requests.'); }
        self::logRateEvent($ip, 'request');
    }

    // Failed-auth attempts (wrong/missing secret) are tracked separately and at
    // a much lower threshold than plain request volume — this is what actually
    // stops someone from brute-forcing the secret, mirroring the login_attempts
    // pattern already used for the password-login brute-force guard.
    private static function tooManyFailedAuth(string $ip): bool
    {
        $count = (int)(Database::fetchOne(
            "SELECT COUNT(*) c FROM live_sync_rate_log WHERE ip_address=? AND kind='auth_fail' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            [$ip]
        )['c'] ?? 0);
        return $count >= 10;
    }

    // Blunt flood/DoS guard, generous enough not to throttle a legitimate pull
    // cycle (up to MAX_BATCH entries + their attachments) but low enough to stop
    // raw request flooding even with a valid (e.g. leaked) secret.
    private static function tooManyRequests(string $ip): bool
    {
        $count = (int)(Database::fetchOne(
            "SELECT COUNT(*) c FROM live_sync_rate_log WHERE ip_address=? AND kind='request' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$ip]
        )['c'] ?? 0);
        return $count >= 300;
    }

    private static function logRateEvent(string $ip, string $kind): void
    {
        try {
            Database::execute("DELETE FROM live_sync_rate_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            Database::execute('INSERT INTO live_sync_rate_log (ip_address, kind) VALUES (?,?)', [$ip, $kind]);
        } catch (Throwable) {}
    }

    private static function checkSecretAuth(string $secret): void
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($authHeader === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) { if (strtolower($k) === 'authorization') { $authHeader = $v; break; } }
        }
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m) || !hash_equals($secret, $m[1])) {
            self::logRateEvent($_SERVER['REMOTE_ADDR'] ?? 'unknown', 'auth_fail');
            json(['error' => 'Unauthorized'], 401);
        }
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
        self::guardAttachmentApi();
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

    // -- Shared ingest logic (project/type/tag resolution, attachment fetch,
    // idempotent insert) used by both PUSH (receiveEntry) and PULL (pullFromSource).
    private static function ingestPayload(array $body): array
    {
        if (empty($body['origin_id']) || empty($body['title'])) {
            return ['ok' => false, 'error' => 'Bad request', 'code' => 400];
        }

        // Idempotent: a retried/duplicate push or a re-listed pull for the same
        // origin entry is a no-op.
        $existing = Database::fetchOne('SELECT id FROM entries WHERE live_origin_id=?', [(int)$body['origin_id']]);
        if ($existing) {
            return ['ok' => true, 'entry_id' => (int)$existing['id'], 'attachments_synced' => 0, 'note' => 'already imported'];
        }

        $project = Database::fetchOne('SELECT id FROM projects WHERE name=?', [(string)($body['project_name'] ?? '')]);
        if (!$project) return ['ok' => false, 'error' => 'Unknown project: ' . ($body['project_name'] ?? '(none)'), 'code' => 422];
        $entryType = Database::fetchOne('SELECT id FROM entry_types WHERE name=?', [(string)($body['entry_type_name'] ?? '')]);
        if (!$entryType) return ['ok' => false, 'error' => 'Unknown entry type: ' . ($body['entry_type_name'] ?? '(none)'), 'code' => 422];

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

        return ['ok' => true, 'entry_id' => $entryId, 'attachments_synced' => $fetched];
    }
}
