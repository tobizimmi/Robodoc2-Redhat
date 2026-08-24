<?php
declare(strict_types=1);

class AttachmentController {
    public static function download(string $id): void {
        Auth::require();
        $att = self::findOr404((int)$id);
        self::checkAccess($att);

        // Support both file_path (absolute) and filename (relative to UPLOAD_DIR)
        if (!empty($att['file_path']) && file_exists($att['file_path'])) {
            $path = $att['file_path'];
        } elseif (!empty($att['filename']) && file_exists(UPLOAD_DIR . $att['filename'])) {
            $path = UPLOAD_DIR . $att['filename'];
            // Fix the DB record so next access is faster
            Database::execute('UPDATE entry_attachments SET file_path=? WHERE id=?', [$path, $att['id']]);
        } else {
            abort(404, 'Datei nicht gefunden: ' . basename($att['filename'] ?? $att['file_path'] ?? '?'));
        }

        $size = filesize($path);
        $mime = $att['mime_type'];

        // Re-detect MIME from actual file bytes if stored type is generic or unknown.
        // This is necessary because X-Content-Type-Options: nosniff prevents browsers
        // from sniffing the real type - we must send the correct Content-Type ourselves.
        if (!$mime || in_array($mime, ['application/octet-stream', 'unknown', 'binary/octet-stream'])) {
            $detected = mime_content_type($path);
            if ($detected && $detected !== 'application/octet-stream') {
                $mime = $detected;
                // Persist the corrected MIME type so future requests are fast
                try { Database::execute('UPDATE entry_attachments SET mime_type=? WHERE id=?', [$mime, $att['id']]); } catch (\Throwable) {}
            }
        }

        // Release session lock and clear any buffered PHP warnings before streaming binary data
        session_write_close();
        while (ob_get_level() > 0) { @ob_end_clean(); }
        error_reporting(0);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Accept-Ranges: bytes');

        // Inline display for images/videos/pdf, download for others
        $inline  = str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/') || $mime === 'application/pdf';
        $disp    = $inline ? 'inline' : 'attachment';
        $name    = $att['display_name'] ?: $att['original_name'];
        // Safe filename: strip control chars, fall back to simple quoted format for broad compatibility
        $safeName = preg_replace('/[\x00-\x1f\x7f"\\\\]/', '_', $name);
        header('Content-Disposition: ' . $disp . '; filename="' . $safeName . '"');

        // Range support for video streaming
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
            $start = $m[1] !== '' ? (int)$m[1] : 0;
            $end   = $m[2] !== '' ? (int)$m[2] : $size - 1;
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . ($end - $start + 1));
            $fp = fopen($path, 'rb');
            fseek($fp, $start);
            $remaining = $end - $start + 1;
            while ($remaining > 0 && !feof($fp)) {
                $chunk = fread($fp, min(8192, $remaining));
                echo $chunk;
                $remaining -= strlen($chunk);
            }
            fclose($fp);
        } else {
            readfile($path);
        }
        exit;
    }

    public static function thumb(string $id): void {
        Auth::require();
        $att = self::findOr404((int)$id);
        self::checkAccess($att);

        $path = $att['file_path'];

        // -- File not found: serve 1?1 transparent GIF placeholder --
        if (!file_exists($path)) {
            self::servePlaceholder();
            return;
        }

        // Always re-detect MIME from actual bytes - stored type may be wrong
        // (e.g., HEIC stored as image/jpeg, octet-stream, etc.)
        $detectedMime = @mime_content_type($path) ?: '';
        $mime = ($detectedMime && $detectedMime !== 'application/octet-stream')
            ? $detectedMime
            : ($att['mime_type'] ?: 'application/octet-stream');

        // Persist corrected MIME so future serving is fast
        if ($mime !== ($att['mime_type'] ?? '')) {
            try { Database::execute('UPDATE entry_attachments SET mime_type=? WHERE id=?', [$mime, $att['id']]); } catch (\Throwable) {}
        }
        // Invalidate cached thumbnail so stale cached images don't appear after file changes
        $staleThumb = UPLOAD_DIR . 'thumbs/' . $att['id'] . '.jpg';
        if (file_exists($staleThumb)) @unlink($staleThumb);


        if (!str_starts_with($mime, 'image/')) {
            // Non-image file: serve inline directly
            session_write_close();
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . preg_replace('/[\x00-\x1f\x7f"\\\\]/', '_', $att['display_name'] ?: $att['original_name']) . '"');
            readfile($path);
            exit;
        }

        // -- Try cached thumbnail ----------------------------------
        $thumbsDir = UPLOAD_DIR . 'thumbs/';
        $thumbPath = $thumbsDir . $att['id'] . '.jpg';
        if (!is_dir($thumbsDir)) @mkdir($thumbsDir, 0755, true);
        // Invalidate stale thumb if source file was modified after thumb was cached
        if (file_exists($thumbPath) && file_exists($path) && filemtime($path) > filemtime($thumbPath)) {
            @unlink($thumbPath);
        }
        if (!file_exists($thumbPath) && is_writable($thumbsDir)) {
            self::createThumb($path, $thumbPath, $mime);
        }

        // Discard any PHP warnings/notices that might have been buffered
        // (they would corrupt binary image data if prepended to the response)
        session_write_close();
        while (ob_get_level() > 0) { @ob_end_clean(); }
        error_reporting(0); // suppress any further warnings during streaming

        if (file_exists($thumbPath) && filesize($thumbPath) > 0) {
            header('Content-Type: image/jpeg');
            header('Cache-Control: no-cache, must-revalidate');
            readfile($thumbPath);
        } else {
            // Thumbnail unavailable - serve original file directly
            header('Content-Type: ' . $mime);
            header('Cache-Control: no-cache, must-revalidate');
            readfile($path);
        }
        exit;
    }

    private static function servePlaceholder(): void
    {
        // 1?1 transparent GIF - safe fallback when file not found on disk
        session_write_close();
        header('Content-Type: image/gif');
        header('Cache-Control: no-store');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    public static function delete(string $id): void {
        Auth::require();
        Auth::verifyCsrf();
        $att   = self::findOr404((int)$id);
        $entry = Database::fetchOne('SELECT created_by FROM entries WHERE id = ?', [$att['entry_id']]);
        if (!Auth::isAdmin() && ($entry['created_by'] ?? null) != Auth::id()) abort(403);

        $entryId = $att['entry_id'];
        if (file_exists($att['file_path'])) @unlink($att['file_path']);
        self::clearThumbCache((int)$id); // remove stale thumb so no orphaned cache remains
        Database::execute('DELETE FROM entry_attachments WHERE id = ?', [(int)$id]);
        Database::execute('UPDATE entries SET attachments_updated_at=NOW() WHERE id=?', [$entryId]);
        flash('success', 'Attachment deleted.');
        redirect('/entries/' . $entryId);
    }

    public static function update(string $id): void {
        Auth::require();
        Auth::verifyCsrf();
        $att = self::findOr404((int)$id);
        Database::execute(
            'UPDATE entry_attachments SET display_name=?, comment=? WHERE id=?',
            [trim($_POST['display_name'] ?? ''), trim($_POST['comment'] ?? ''), (int)$id]
        );
        Database::execute('UPDATE entries SET attachments_updated_at=NOW() WHERE id=?', [$att['entry_id']]);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json(['success' => true]);
        }
        flash('success', 'Anhang aktualisiert.');
        redirect('/entries/' . $att['entry_id']);
    }

    // Save annotated photo - receives base64 PNG canvas data, writes as new file
    public static function annotate(string $id): void {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');
        $att = self::findOr404((int)$id);
        $dataUrl = $_POST['image_data'] ?? '';
        if (!preg_match('/^data:image\/(png|jpeg|webp);base64,/', $dataUrl, $m)) {
            echo json_encode(['error' => 'Invalid image data']); exit;
        }
        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($base64);
        if (!$binary) { echo json_encode(['error' => 'Decode failed']); exit; }

        $dir = UPLOAD_DIR . $att['entry_id'] . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fn   = bin2hex(random_bytes(16)) . '_annotated.' . $m[1];
        $dest = $dir . $fn;
        file_put_contents($dest, $binary);

        $label = trim($_POST['label'] ?? '') ?: ($att['display_name'] ?: $att['original_name']);
        $label = preg_replace('/(\.\w+)?$/', '_annotated.png', $label, 1);

        $newId = Database::insert(
            'INSERT INTO entry_attachments (entry_id, filename, original_name, display_name, mime_type, file_size, file_path) VALUES (?,?,?,?,?,?,?)',
            [$att['entry_id'], $fn, basename($dest), $label, 'image/' . $m[1], strlen($binary), $dest]
        );
        try { Database::execute('UPDATE entries SET attachments_updated_at=NOW() WHERE id=?', [$att['entry_id']]); } catch (\Throwable) {}
        echo json_encode(['success' => true, 'id' => $newId, 'label' => $label]);
        exit;
    }

    // Video markers
    public static function markers(string $id): void {
        Auth::require();
        session_write_close(); // don't hold session lock during DB fetch
        $att     = self::findOr404((int)$id);
        $markers = Database::fetchAll(
            'SELECT m.*, u.name user_name FROM attachment_markers m LEFT JOIN users u ON u.id=m.created_by WHERE m.attachment_id=? ORDER BY m.time_seconds',
            [(int)$id]
        );
        json($markers);
    }

    public static function addMarker(string $id): void {
        Auth::require();
        Auth::verifyCsrf();
        $att     = self::findOr404((int)$id);
        $time    = (float)($_POST['time_seconds'] ?? 0);
        $label   = trim($_POST['label'] ?? '');
        $markerId = Database::insert(
            'INSERT INTO attachment_markers (attachment_id, time_seconds, label, created_by) VALUES (?,?,?,?)',
            [(int)$id, $time, $label, Auth::id()]
        );
        json(['success' => true, 'id' => $markerId, 'time_seconds' => $time, 'label' => $label]);
    }

    public static function deleteMarker(string $id, string $mid): void {
        Auth::require();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM attachment_markers WHERE id=? AND attachment_id=?', [(int)$mid, (int)$id]);
        json(['success' => true]);
    }

    // Single-entry ZIP download via GET /entries/{id}/download-zip
    public static function downloadZip(string $entryId): void {
        Auth::requireView('entries');
        $eid  = (int)$entryId;
        $atts = Database::fetchAll(
            'SELECT * FROM entry_attachments WHERE entry_id = ? ORDER BY created_at',
            [$eid]
        );
        if (empty($atts)) { abort(404, 'Keine Anhaenge gefunden'); }
        if (!class_exists('ZipArchive')) { abort(500, 'ZipArchive not available'); }
        $entry   = Database::fetchOne('SELECT title FROM entries WHERE id=?', [$eid]);
        $slug    = preg_replace('/[^a-z0-9]+/', '-', strtolower($entry['title'] ?? 'entry'));
        $slug    = trim($slug, '-') ?: 'entry';
        $tmpFile = tempnam(sys_get_temp_dir(), 'rd2_') . '.zip';
        $zip     = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'ZIP konnte nicht erstellt werden');
        }
        $added = 0;
        foreach ($atts as $att) {
            if (!file_exists($att['file_path'])) continue;
            try { self::checkAccess($att); } catch (\Throwable) { continue; }
            $localName = $added . '_' . basename($att['file_path']);
            if ($zip->addFile($att['file_path'], $localName)) $added++;
        }
        $zip->close();
        if ($added === 0) { @unlink($tmpFile); abort(404, 'Keine Dateien gefunden'); }
        // Discard any output buffers to avoid corrupting binary output
        while (ob_get_level()) ob_end_clean();
        $filename = 'entry-' . $eid . '-' . $slug . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-cache');
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    public static function zip(string ...$_): void {
        Auth::require();
        Auth::verifyCsrf();
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if (!$ids) abort(400, 'Keine IDs');

        if (!class_exists('ZipArchive')) abort(500, 'ZipArchive not available');

        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $atts = Database::fetchAll("SELECT * FROM entry_attachments WHERE id IN ($ph)", $ids);

        $tmpFile = tempnam(sys_get_temp_dir(), 'rd2_');
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($atts as $att) {
            // Enforce per-attachment access control (prevents IDOR)
            try { self::checkAccess($att); } catch (\Throwable) { continue; }
            if (file_exists($att['file_path'])) {
                $zip->addFile($att['file_path'], $att['display_name'] ?: $att['original_name']);
            }
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="robodoc-export.zip"');
        header('Content-Length: ' . filesize($tmpFile));
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    // Delete cached thumbnail for an attachment - call whenever the source file changes or is deleted
    private static function clearThumbCache(int $attId): void {
        $thumb = UPLOAD_DIR . 'thumbs/' . $attId . '.jpg';
        if (file_exists($thumb)) @unlink($thumb);
    }

    private static function findOr404(int $id): array {
        $att = Database::fetchOne('SELECT * FROM entry_attachments WHERE id = ?', [$id]);
        if (!$att) abort(404);
        return $att;
    }

    private static function checkAccess(array $att): void {
        $entry = Database::fetchOne('SELECT is_private, created_by, project_id FROM entries WHERE id = ?', [$att['entry_id']]);
        if (!$entry) return;
        if (Auth::isAdmin()) return;

        if ($entry['is_private'] && $entry['created_by'] != Auth::id()) abort(403);

        $access = Auth::groupAccess();
        if ($access === null) return;

        $pid    = (int)$entry['project_id'];
        $allIds = $access['all'];
        $ownIds = $access['own'];
        if (!in_array($pid, $allIds, true) && !in_array($pid, $ownIds, true)) abort(403);
        if (!in_array($pid, $allIds, true) && (int)$entry['created_by'] !== Auth::id()) abort(403);
    }

    public static function makeThumb(string $src, string $dest, string $mime, int $tw = 200, int $th = 0): void {
        self::createThumb($src, $dest, $mime);
    }

    private static function createThumb(string $src, string $dest, string $mime): void {
        if (!extension_loaded('gd')) return;
        // Raise memory limit for large iPhone photos (10MB+ JPEG = ~50MB in RAM)
        ini_set('memory_limit', '256M');
        $img = match(true) {
            in_array($mime, ['image/jpeg', 'image/heic', 'image/heif']) => imagecreatefromjpeg($src),
            $mime === 'image/png'  => imagecreatefrompng($src),
            $mime === 'image/gif'  => imagecreatefromgif($src),
            $mime === 'image/webp' => imagecreatefromwebp($src),
            default => false,
        };
        if (!$img) return;
        $w = imagesx($img); $h = imagesy($img);
        // Square crop from center at 200?200
        $size = min($w, $h);
        $sx = (int)(($w - $size) / 2);
        $sy = (int)(($h - $size) / 2);
        $thumb = imagecreatetruecolor(200, 200);
        imagecopyresampled($thumb, $img, 0, 0, $sx, $sy, 200, 200, $size, $size);
        @imagejpeg($thumb, $dest, 85);
        imagedestroy($img);
        imagedestroy($thumb);
    }
}
