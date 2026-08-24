<?php
declare(strict_types=1);

class BackupController
{
    // ── Admin settings page ───────────────────────────────────
    public static function index(): void
    {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'save';

            $path  = rtrim(trim($_POST['backup_path']  ?? ''), '/');
            $keep  = max(1, min(99, (int)($_POST['backup_keep']  ?? 7)));
            $sched = trim($_POST['backup_schedule'] ?? '0 2 * * *');

            // Auto-generate secret once
            $secret = appSetting('backup_secret') ?: bin2hex(random_bytes(24));

            foreach ([
                'backup_path'     => $path,
                'backup_keep'     => (string)$keep,
                'backup_schedule' => $sched,
                'backup_secret'   => $secret,
            ] as $k => $v) {
                Database::execute(
                    'INSERT INTO app_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    [$k, $v]
                );
            }

            if ($action === 'run') {
                $result = self::perform($path, $keep);
                if ($result['success'] ?? false) {
                    flash('success', 'Backup created: ' . $result['file'] . ' (' . formatFileSize((int)$result['size']) . ')');
                } else {
                    flash('error', 'Backup failed: ' . ($result['error'] ?? 'Unknown error'));
                }
            } else {
                flash('success', 'Backup settings saved.');
            }
            redirect('/admin/backup');
        }

        $s = array_column(
            Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'),
            'setting_value', 'setting_key'
        );

        // Auto-generate secret on first visit
        if (empty($s['backup_secret'])) {
            $secret = bin2hex(random_bytes(24));
            Database::execute(
                'INSERT INTO app_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['backup_secret', $secret]
            );
            $s['backup_secret'] = $secret;
        }

        // List existing backups newest-first
        $backupPath = $s['backup_path'] ?? '';
        $backups    = [];
        if ($backupPath && is_dir($backupPath)) {
            $files = glob(rtrim($backupPath, '/') . '/robodoc_backup_*.zip') ?: [];
            rsort($files);
            foreach ($files as $f) {
                $backups[] = ['name' => basename($f), 'size' => filesize($f), 'mtime' => filemtime($f)];
            }
        }

        View::render('admin/backup', compact('s', 'backups') + ['title' => 'Automatic Backup']);
    }

    // ── Cron endpoint (no session, secured by token) ──────────
    public static function runCron(): void
    {
        $secret = appSetting('backup_secret');
        $token  = $_GET['token'] ?? '';
        if (!$secret || !hash_equals($secret, $token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        ignore_user_abort(true);
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $path = appSetting('backup_path');
        $keep = max(1, (int)(appSetting('backup_keep') ?: 7));
        $result = self::perform($path, $keep);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    // ── Download a backup file (admin only) ───────────────────
    public static function download(): void
    {
        Auth::requireAdmin();
        $file     = basename($_GET['file'] ?? '');
        $basePath = rtrim(appSetting('backup_path') ?: '', '/');
        if (!$file || !$basePath) abort(400);

        $fullPath = $basePath . '/' . $file;
        // Validate: must be inside the configured backup dir and match our naming pattern
        if (!preg_match('/^robodoc_backup_[\d_\-]+\.zip$/', $file) || !file_exists($fullPath)) {
            abort(404, 'Backup file not found');
        }

        session_write_close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: no-cache, no-store');
        readfile($fullPath);
        exit;
    }

    // ── Delete a backup file (admin only) ────────────────────
    public static function deleteBackup(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $file     = basename($_POST['file'] ?? '');
        $basePath = rtrim(appSetting('backup_path') ?: '', '/');
        if (!$file || !$basePath) abort(400);

        $fullPath = $basePath . '/' . $file;
        if (!preg_match('/^robodoc_backup_[\d_\-]+\.zip$/', $file) || !file_exists($fullPath)) {
            abort(404);
        }
        @unlink($fullPath);
        flash('success', 'Backup deleted: ' . $file);
        redirect('/admin/backup');
    }

    // ── Core backup logic ─────────────────────────────────────
    public static function perform(string $path, int $keep): array
    {
        if (!$path) return ['error' => 'No backup path configured. Set it in Admin → Backup.'];
        if (!class_exists('ZipArchive')) return ['error' => 'ZipArchive PHP extension is not available.'];

        if (!is_dir($path) && !@mkdir($path, 0750, true)) {
            return ['error' => "Cannot create backup directory: $path"];
        }
        if (!is_writable($path)) {
            return ['error' => "Backup directory is not writable: $path"];
        }

        $ts      = date('Y-m-d_H-i-s');
        $zipFile = rtrim($path, '/') . '/robodoc_backup_' . $ts . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return ['error' => "Cannot create ZIP file: $zipFile"];
        }

        // 1. Database dump
        try {
            $dbSql = self::dumpDatabase();
            $zip->addFromString('database.sql', $dbSql);
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($zipFile);
            return ['error' => 'Database dump failed: ' . $e->getMessage()];
        }

        // 2. Uploads directory
        $uploadDir = rtrim(UPLOAD_DIR, '/');
        if (is_dir($uploadDir)) {
            self::addDirToZip($zip, $uploadDir, 'uploads');
        }

        // 3. Restore guide
        $zip->addFromString('RESTORE_GUIDE.txt', self::restoreGuide($ts));

        $zip->close();

        if (!file_exists($zipFile) || filesize($zipFile) === 0) {
            return ['error' => 'Backup ZIP was not created correctly.'];
        }

        // Rotate old backups
        $deleted = self::rotate($path, $keep);

        // Log to app_settings
        Database::execute(
            'INSERT INTO app_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
            ['backup_last_run', date('Y-m-d H:i:s')]
        );
        Database::execute(
            'INSERT INTO app_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
            ['backup_last_file', basename($zipFile)]
        );

        return [
            'success' => true,
            'file'    => basename($zipFile),
            'size'    => filesize($zipFile),
            'deleted' => $deleted,
        ];
    }

    // ── Pure-PHP database dump ────────────────────────────────
    private static function dumpDatabase(): string
    {
        $pdo = Database::get();
        $out = [];
        $out[] = '-- RoboDoc2 Database Backup';
        $out[] = '-- Created: ' . date('Y-m-d H:i:s');
        $out[] = '-- Database: ' . DB_NAME;
        $out[] = '';
        $out[] = 'SET NAMES utf8mb4;';
        $out[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $out[] = 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";';
        $out[] = '';

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $out[] = "-- Table: `$table`";
            $out[] = "DROP TABLE IF EXISTS `$table`;";
            $row   = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_NUM);
            $out[] = $row[1] . ';';
            $out[] = '';

            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = '`' . implode('`,`', array_keys($rows[0])) . '`';
                foreach (array_chunk($rows, 200) as $chunk) {
                    $vals = array_map(function (array $row) use ($pdo): string {
                        $parts = array_map(function ($v) use ($pdo): string {
                            return $v === null ? 'NULL' : $pdo->quote((string)$v);
                        }, array_values($row));
                        return '(' . implode(',', $parts) . ')';
                    }, $chunk);
                    $out[] = "INSERT INTO `$table` ($cols) VALUES";
                    $out[] = implode(",\n", $vals) . ';';
                }
                $out[] = '';
            }
        }

        $out[] = 'SET FOREIGN_KEY_CHECKS=1;';
        return implode("\n", $out);
    }

    // ── Recursively add directory to ZIP ─────────────────────
    private static function addDirToZip(\ZipArchive $zip, string $dir, string $prefix): void
    {
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                $rel  = substr($file->getPathname(), strlen($dir) + 1);
                $zip->addFile($file->getPathname(), $prefix . '/' . $rel);
            }
        } catch (\Throwable) {
            // Skip unreadable files silently
        }
    }

    // ── Delete oldest backups beyond retention limit ──────────
    private static function rotate(string $path, int $keep): int
    {
        $files = glob(rtrim($path, '/') . '/robodoc_backup_*.zip') ?: [];
        sort($files); // oldest first
        $deleted = 0;
        while (count($files) > $keep) {
            if (@unlink(array_shift($files))) $deleted++;
        }
        return $deleted;
    }

    private static function restoreGuide(string $ts): string
    {
        return <<<TXT
RoboDoc2 Backup — $ts
=====================

This archive contains:
  database.sql   — Full MySQL database dump (all tables + data)
  uploads/       — All entry attachments, thumbnails, and media files

How to restore from scratch
----------------------------
1. Deploy RoboDoc2 app files to server (git clone or unzip)
2. Create a new MySQL database
3. Import the database:
     mysql -u USER -p DBNAME < database.sql
4. Copy the uploads/ folder to the app root:
     cp -r uploads/ /path/to/robodoc/uploads/
5. Configure config.local.php with DB credentials
6. Visit the app — migrations run automatically

Notes
-----
- config.local.php is NOT included in this backup (contains secrets).
  You will need to recreate it with your DB credentials.
- The app auto-runs all migrations on first visit.

TXT;
    }
}
