<?php
declare(strict_types=1);

/**
 * QuickCaptureController
 *
 * Public (no-login) quick capture of draft entries + a moderation queue.
 * Anonymous submissions land in the `quick_captures` table (status=pending) and
 * NEVER touch the real `entries` table until a logged-in user with project access
 * approves them. Uploaded files are quarantined in a SEPARATE directory until then.
 */
class QuickCaptureController {

    // Tighter limits for the anonymous endpoint than the authenticated app.
    private const MAX_FILES        = 5;
    private const MAX_FILE_BYTES   = 500 * 1024 * 1024; // 500 MB per file (same as entry attachments)
    private const RATE_WINDOW_MIN  = 10;               // minutes
    private const RATE_MAX         = 5;                 // submissions per IP per window
    private const QUARANTINE_DIR   = 'quick_captures/'; // under UPLOAD_DIR

    // -- Public form (no login) -----------------------------------
    public static function publicForm(): void {
        // A session token lets us verify the POST came from our own form.
        $csrf = Auth::csrfToken();
        View::render('quick_capture/public', [
            'title' => 'Quick Capture',
            'csrf'  => $csrf,
        ], 'auth');
    }

    // -- Public submit (no login) ---------------------------------
    public static function publicSubmit(): void {
        Auth::verifyCsrf();

        // Honeypot: bots fill hidden fields. Pretend success, save nothing.
        if (trim($_POST['website'] ?? '') !== '') {
            redirect('/quick-capture/thanks');
        }

        $ipHash = self::ipHash();

        // Simple per-IP rate limit using the table itself (no extra table needed).
        $window = (int)self::RATE_WINDOW_MIN; // safe: class constant, not user input
        $windowStart = date('Y-m-d H:i:s', time() - $window * 60);
        $recent = (int)(Database::fetchOne(
            "SELECT COUNT(*) c FROM quick_captures WHERE ip_hash = ? AND created_at > ?",
            [$ipHash, $windowStart]
        )['c'] ?? 0);
        if ($recent >= self::RATE_MAX) {
            flash('error', 'Zu viele Einsendungen in kurzer Zeit. Bitte versuche es spaeter erneut.');
            redirect('/quick-capture');
        }

        $projectHint     = trim($_POST['project_hint'] ?? '');
        $title           = trim($_POST['title'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $reporter        = trim($_POST['reporter_name'] ?? '');
        $contact         = trim($_POST['reporter_contact'] ?? '');
        $mowerSerial     = trim($_POST['mower_serial'] ?? '');
        $firmwareVersion = trim($_POST['firmware_version'] ?? '');

        // project_hint is the required "which project / context" field.
        if ($projectHint === '' || $title === '') {
            flash('error', 'Bitte Projekt-/Bezugsangabe und einen Titel ausfuellen.');
            redirect('/quick-capture');
        }

        // Length caps (defensive).
        $projectHint = mb_substr($projectHint, 0, 255);
        $title       = mb_substr($title, 0, 200);
        $description = mb_substr($description, 0, 5000);
        $reporter    = mb_substr($reporter, 0, 150);
        $contact     = mb_substr($contact, 0, 200);

        $captureId = Database::insert(
            'INSERT INTO quick_captures
                (project_hint, title, description, reporter_name, reporter_contact, mower_serial, firmware_version, status, ip_hash, created_at)
             VALUES (?,?,?,?,?,?,?,\'pending\',?,?)',
            [$projectHint, $title, $description, $reporter ?: null, $contact ?: null, $mowerSerial ?: null, $firmwareVersion ?: null, $ipHash, date('Y-m-d H:i:s')]
        );

        // Handle optional uploads into the quarantine directory.
        $uploadErrors = [];
        if (!empty($_FILES['files']['name'][0])) {
            $uploadErrors = self::saveCaptureFiles($captureId, $_FILES['files']);
        }

        if ($uploadErrors) {
            // Entry/capture itself was still saved successfully - only the file(s) had issues.
            // Show the thank-you page but surface exactly which attachment(s) failed and why.
            $_SESSION['qc_upload_warnings'] = $uploadErrors;
        }

        redirect('/quick-capture/thanks');
    }

    public static function thanks(): void {
        $uploadWarnings = $_SESSION['qc_upload_warnings'] ?? [];
        unset($_SESSION['qc_upload_warnings']);
        View::render('quick_capture/success', ['title' => 'Danke', 'uploadWarnings' => $uploadWarnings], 'auth');
    }

    // -- Moderation queue (login required) ------------------------
    public static function index(): void {
        Auth::requireView('quick_capture');
        $status = in_array($_GET['status'] ?? '', ['pending','approved','rejected'], true)
            ? $_GET['status'] : 'pending';

        $captures = Database::fetchAll(
            "SELECT qc.*, e.id AS entry_id_real, u.name AS reviewer_name,
                    (SELECT COUNT(*) FROM quick_capture_files f WHERE f.capture_id = qc.id) AS file_count
             FROM quick_captures qc
             LEFT JOIN entries e ON e.id = qc.entry_id
             LEFT JOIN users u   ON u.id = qc.reviewed_by
             WHERE qc.status = ?
             ORDER BY qc.created_at DESC",
            [$status]
        );

        $counts = [];
        foreach (Database::fetchAll("SELECT status, COUNT(*) c FROM quick_captures GROUP BY status") as $r) {
            $counts[$r['status']] = (int)$r['c'];
        }

        View::render('quick_capture/index', compact('captures','status','counts') + [
            'title' => 'Quick Captures',
        ]);
    }

    // -- Review single capture (login required) -------------------
    public static function review(string $id): void {
        Auth::requireEdit('quick_capture');
        $capture = self::findOr404((int)$id);
        $files   = Database::fetchAll('SELECT * FROM quick_capture_files WHERE capture_id = ? ORDER BY id', [(int)$id]);

        // Only projects the user may write into appear in the approval dropdown.
        [$projSql, $projParams] = Auth::projectAccessClause('p');
        $projects   = Database::fetchAll(
            "SELECT p.id, p.name FROM projects p WHERE p.status='active' AND $projSql ORDER BY p.name",
            $projParams
        );
        $entryTypes = Database::fetchAll('SELECT id, name FROM entry_types ORDER BY sort_order, name');

        $activeUsers = Database::fetchAll('SELECT id, name FROM users WHERE status=? ORDER BY name', ['active']);
        View::render('quick_capture/review', compact('capture','files','projects','entryTypes','activeUsers') + [
            'title' => 'Quick Capture pruefen',
        ]);
    }

    // -- Approve ? create a real entry (login required) -----------
    public static function approve(string $id): void {
        Auth::requireEdit('quick_capture');
        Auth::verifyCsrf();
        $capture = self::findOr404((int)$id);
        if ($capture['status'] !== 'pending') {
            flash('error', 'Dieser Eintrag wurde bereits bearbeitet.');
            redirect('/quick-captures');
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $typeId    = (int)($_POST['entry_type_id'] ?? 0);
        $title           = trim($_POST['title'] ?? $capture['title']);
        $desc            = trim($_POST['description'] ?? (string)$capture['description']);
        $mowerSerial     = trim($_POST['mower_serial'] ?? (string)($capture['mower_serial'] ?? ''));
        $firmwareVersion = trim($_POST['firmware_version'] ?? (string)($capture['firmware_version'] ?? ''));

        if (!$projectId || !$typeId) {
            flash('error', 'Bitte Projekt und Typ auswaehlen.');
            redirect('/quick-captures/' . (int)$id);
        }

        // Enforce project access: the chosen project must be one the user may write into.
        $allowed = Auth::groupProjectIds(); // null = unrestricted (admin / ungrouped)
        if ($allowed !== null && !in_array($projectId, $allowed, true)) {
            abort(403, 'Kein Zugriff auf das gewaehlte Projekt.');
        }
        $projOk = Database::fetchOne("SELECT id FROM projects WHERE id=? AND status='active'", [$projectId]);
        if (!$projOk) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/quick-captures/' . (int)$id);
        }

        $assignedTo = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $entryId = Database::insert(
            'INSERT INTO entries
                (project_id, entry_type_id, entry_date, entry_time, title, description, status, priority, is_private, created_by, assigned_to, mower_serial, firmware_version)
             VALUES (?,?,?,?,?,?,\'new\',\'Medium\',0,?,?,?,?)',
            [$projectId, $typeId, date('Y-m-d'), date('H:i:s'),
             mb_substr($title, 0, 200), $desc, Auth::id(), $assignedTo,
             $mowerSerial ?: null, $firmwareVersion ?: null]
        );

        // Move quarantined files into the real entry's attachment storage.
        self::promoteFiles((int)$id, $entryId);

        Database::execute(
            'UPDATE quick_captures SET status=\'approved\', entry_id=?, reviewed_by=?, reviewed_at=?, assigned_to=? WHERE id=?',
            [$entryId, Auth::id(), date('Y-m-d H:i:s'), $assignedTo, (int)$id]
        );

        flash('success', 'Eintrag freigegeben und uebernommen.');
        redirect('/entries/' . $entryId);
    }

    // -- Reject (login required) ----------------------------------
    public static function reject(string $id): void {
        Auth::requireEdit('quick_capture');
        Auth::verifyCsrf();
        $capture = self::findOr404((int)$id);
        if ($capture['status'] === 'pending') {
            Database::execute(
                'UPDATE quick_captures SET status=\'rejected\', reviewed_by=?, reviewed_at=? WHERE id=?',
                [Auth::id(), date('Y-m-d H:i:s'), (int)$id]
            );
            self::deleteQuarantine((int)$id); // free disk; metadata rows kept for audit
        }
        flash('success', 'Eintrag abgelehnt.');
        redirect('/quick-captures');
    }

    // -- Helpers --------------------------------------------------
    private static function findOr404(int $id): array {
        $row = Database::fetchOne('SELECT * FROM quick_captures WHERE id = ?', [$id]);
        if (!$row) abort(404, 'Quick Capture nicht gefunden.');
        return $row;
    }

    private static function ipHash(): string {
        return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . APP_SECRET);
    }

    private static function quarantineDir(int $captureId): string {
        return rtrim(UPLOAD_DIR, '/') . '/' . self::QUARANTINE_DIR . $captureId . '/';
    }

    /** Validate + store anonymous uploads in the quarantine directory. */
    // Returns an array of human-readable error strings for any files that could not be
    // saved, so the caller can show them to the user instead of silently dropping files.
    private static function saveCaptureFiles(int $captureId, array $files): array {
        $count  = min(count($files['name']), self::MAX_FILES);
        $dir    = self::quarantineDir($captureId);
        $errors = [];
        if (count($files['name']) > self::MAX_FILES) {
            $errors[] = 'Nur die ersten ' . self::MAX_FILES . ' Dateien wurden beruecksichtigt (Limit: ' . self::MAX_FILES . ').';
        }
        for ($i = 0; $i < $count; $i++) {
            $origName = (string)($files['name'][$i] ?? 'Datei ' . ($i + 1));
            $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) continue; // empty file input slot, not a real error
            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = "$origName: Upload-Fehler (Code $err)";
                continue;
            }
            $tmp  = $files['tmp_name'][$i];
            $size = (int)$files['size'][$i];
            if ($size <= 0) {
                $errors[] = "$origName: Datei ist leer oder konnte nicht gelesen werden.";
                continue;
            }
            if ($size > self::MAX_FILE_BYTES) {
                $errors[] = "$origName: Datei zu gross (" . round($size / 1048576, 1) . ' MB, Limit: ' . round(self::MAX_FILE_BYTES / 1048576) . ' MB).';
                continue;
            }

            // MIME is detected from the file bytes, never trusted from the client.
            $mime = mime_content_type($tmp) ?: 'application/octet-stream';
            $allowed = str_starts_with($mime, 'image/')
                    || str_starts_with($mime, 'video/')
                    || $mime === 'application/pdf';
            if (!$allowed) {
                $errors[] = "$origName: Dateityp nicht erlaubt ($mime).";
                continue;
            }

            $extMap = [
                'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
                'image/heic'=>'heic','image/heif'=>'heif',
                'video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm',
                'application/pdf'=>'pdf',
            ];
            $ext = $extMap[$mime] ?? 'bin';
            $fn  = bin2hex(random_bytes(16)) . '.' . $ext;

            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                $errors[] = "$origName: Upload-Verzeichnis konnte nicht erstellt werden.";
                continue;
            }
            if (!is_writable($dir)) {
                $errors[] = "$origName: Upload-Verzeichnis nicht beschreibbar.";
                continue;
            }

            $dest = $dir . $fn;
            if (!move_uploaded_file($tmp, $dest)) {
                $errors[] = "$origName: Datei konnte nicht gespeichert werden.";
                continue;
            }

            $orig = mb_substr($origName, 0, 255);
            Database::insert(
                'INSERT INTO quick_capture_files
                    (capture_id, filename, original_name, mime_type, file_size, file_path, created_at)
                 VALUES (?,?,?,?,?,?,?)',
                [$captureId, $fn, $orig, $mime, $size, $dest, date('Y-m-d H:i:s')]
            );
        }
        return $errors;
    }

    /** Move quarantined files into the entry's attachment store + register them. */
    private static function promoteFiles(int $captureId, int $entryId): void {
        $files = Database::fetchAll('SELECT * FROM quick_capture_files WHERE capture_id = ?', [$captureId]);
        if (!$files) return;

        $entryDir = rtrim(UPLOAD_DIR, '/') . '/' . $entryId . '/';
        if (!is_dir($entryDir)) @mkdir($entryDir, 0755, true);

        foreach ($files as $f) {
            $src  = $f['file_path'];
            $dest = $entryDir . $f['filename'];
            if (is_file($src) && @rename($src, $dest)) {
                Database::insert(
                    'INSERT INTO entry_attachments (entry_id, filename, original_name, mime_type, file_size, file_path)
                     VALUES (?,?,?,?,?,?)',
                    [$entryId, $f['filename'], $f['original_name'], $f['mime_type'], $f['file_size'], $dest]
                );
            }
        }
        try { Database::execute('UPDATE entries SET attachments_updated_at=NOW() WHERE id=?', [$entryId]); } catch (\Throwable) {}
        self::deleteQuarantine($captureId);
    }

    private static function deleteQuarantine(int $captureId): void {
        $dir = self::quarantineDir($captureId);
        if (is_dir($dir)) {
            foreach (glob($dir . '*') as $file) { @unlink($file); }
            @rmdir($dir);
        }
    }
}
