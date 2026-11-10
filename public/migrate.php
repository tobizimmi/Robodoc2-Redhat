<?php
/**
 * RoboDoc Migration Tool — TEMPORARY, delete after migration!
 * Access only as Admin.
 */
require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireAdmin();

$action = $_GET['action'] ?? 'menu';

// ── SQL Dump ──────────────────────────────────────────────────────────────────
if ($action === 'dump') {
    $cfg = [
        'host' => defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost'),
        'name' => defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: ''),
        'user' => defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: ''),
        'pass' => defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: ''),
    ];

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="robodoc_dump_' . date('Ymd_His') . '.sql"');

    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "-- RoboDoc MySQL Dump\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: {$cfg['name']}\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        // CREATE TABLE
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $create['Create Table'] . ";\n\n";

        // INSERT DATA
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $chunks = array_chunk($rows, 100);
            foreach ($chunks as $chunk) {
                echo "INSERT INTO `$table` ($cols) VALUES\n";
                $vals = [];
                foreach ($chunk as $row) {
                    $escaped = array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote($v);
                    }, array_values($row));
                    $vals[] = '(' . implode(', ', $escaped) . ')';
                }
                echo implode(",\n", $vals) . ";\n";
            }
            echo "\n";
        }
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

// ── Uploads ZIP ───────────────────────────────────────────────────────────────
if ($action === 'uploads') {
    // Try multiple possible upload paths
    $possibleDirs = [
        defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/') : null,
        dirname(__DIR__) . '/uploads',
        __DIR__ . '/uploads',
        '/var/www/html/uploads',
        '/var/www/uploads',
    ];
    $uploadDir = null;
    foreach ($possibleDirs as $d) {
        if ($d && is_dir($d)) { $uploadDir = $d; break; }
    }
    if (!$uploadDir) {
        $checked = implode('<br>', array_filter($possibleDirs));
        die('Upload directory not found. Checked:<br>' . $checked);
    }
    if (!is_dir($uploadDir)) {
        die('Upload directory not found: ' . $uploadDir);
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="robodoc_uploads_' . date('Ymd_His') . '.zip"');

    $zip = new ZipArchive();
    $tmpFile = tempnam(sys_get_temp_dir(), 'robodoc_uploads_');
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath     = $file->getRealPath();
            $relativePath = substr($filePath, strlen($uploadDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();

    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// ── Info ──────────────────────────────────────────────────────────────────────
if ($action === 'info') {
    header('Content-Type: application/json');
    $possibleDirs2 = [
        defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/') : null,
        dirname(__DIR__) . '/uploads',
        __DIR__ . '/uploads',
    ];
    $uploadDir = null;
    foreach ($possibleDirs2 as $d) {
        if ($d && is_dir($d)) { $uploadDir = $d; break; }
    }
    $uploadSize = 0;
    $uploadCount = 0;
    if (is_dir($uploadDir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir)) as $f) {
            if ($f->isFile()) { $uploadSize += $f->getSize(); $uploadCount++; }
        }
    }
    $cfg = [
        'host' => defined('DB_HOST') ? DB_HOST : getenv('DB_HOST'),
        'name' => defined('DB_NAME') ? DB_NAME : getenv('DB_NAME'),
    ];
    $pdo    = new PDO("mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4",
        defined('DB_USER') ? DB_USER : getenv('DB_USER'),
        defined('DB_PASS') ? DB_PASS : getenv('DB_PASS'));
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $rows   = [];
    foreach ($tables as $t) {
        $rows[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    }
    echo json_encode([
        'upload_dir_detected' => $uploadDir,
        'db_host'      => $cfg['host'],
        'db_name'      => $cfg['name'],
        'tables'       => count($tables),
        'table_rows'   => $rows,
        'upload_files' => $uploadCount,
        'upload_size'  => round($uploadSize / 1024 / 1024, 2) . ' MB',
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>RoboDoc Migration Tool</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#0f172a;color:#e2e8f0;min-height:100vh;padding:2rem}</style>
</head>
<body>
<div class="container" style="max-width:700px">
  <div class="text-center mb-4">
    <h3><i class="bi bi-box-arrow-up-right me-2"></i>RoboDoc Migration Tool</h3>
    <p class="text-warning small">⚠️ Diese Seite nach der Migration löschen!</p>
  </div>

  <div class="card border-info mb-4">
    <div class="card-header border-info">Schritt 1 — Datenbankinfo prüfen</div>
    <div class="card-body">
      <button class="btn btn-info btn-sm" onclick="loadInfo()">
        <i class="bi bi-info-circle me-1"></i>DB-Info laden
      </button>
      <pre id="infoOutput" class="mt-3 small" style="background:#1e293b;padding:1rem;border-radius:6px;display:none"></pre>
    </div>
  </div>

  <div class="card border-primary mb-4">
    <div class="card-header border-primary">Schritt 2 — Datenbank exportieren</div>
    <div class="card-body">
      <p class="small text-muted">Erstellt einen vollständigen SQL-Dump aller Tabellen und Daten.</p>
      <a href="?action=dump" class="btn btn-primary">
        <i class="bi bi-download me-1"></i>SQL Dump herunterladen
      </a>
    </div>
  </div>

  <div class="card border-success mb-4">
    <div class="card-header border-success">Schritt 3 — Uploads exportieren</div>
    <div class="card-body">
      <p class="small text-muted">Erstellt ein ZIP mit allen hochgeladenen Dateien.</p>
      <a href="?action=uploads" class="btn btn-success">
        <i class="bi bi-file-zip me-1"></i>Uploads ZIP herunterladen
      </a>
    </div>
  </div>

  <div class="card border-warning mb-4">
    <div class="card-header border-warning">Schritt 4 — Nach der Migration</div>
    <div class="card-body small text-muted">
      <ol>
        <li>SQL Dump in Azure MySQL importieren</li>
        <li>ZIP entpacken und in Azure App Service <code>/home/site/wwwroot/public/uploads/</code> hochladen</li>
        <li>Environment Variables in Azure App Service setzen</li>
        <li>Diese Datei <code>public/migrate.php</code> löschen!</li>
      </ol>
    </div>
  </div>
</div>

<script>
function loadInfo() {
  fetch('?action=info')
    .then(r => r.json())
    .then(d => {
      document.getElementById('infoOutput').style.display = 'block';
      document.getElementById('infoOutput').textContent = JSON.stringify(d, null, 2);
    });
}
</script>
</body>
</html>
