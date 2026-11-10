<?php
/**
 * RoboDoc Migration Tool — TEMPORARY, delete after migration!
 */
require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireAdmin();

set_time_limit(300);
ini_set('memory_limit', '512M');

// Detect upload dir
function detectUploadDir(): ?string {
    $dirs = [
        defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/') : null,
        dirname(__DIR__) . '/uploads',
        __DIR__ . '/uploads',
        '/var/www/html/uploads',
        '/var/www/uploads',
    ];
    foreach ($dirs as $d) {
        if ($d && is_dir($d)) return $d;
    }
    return null;
}

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
    $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4",
        $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "-- RoboDoc MySQL Dump\n-- Generated: " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            foreach (array_chunk($rows, 50) as $chunk) {
                echo "INSERT INTO `$table` ($cols) VALUES\n";
                $vals = [];
                foreach ($chunk as $row) {
                    $escaped = array_map(function($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote($v); }, array_values($row));
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

// ── Generate wget script ─────────────────────────────────────────────────────
if ($action === 'wget_script') {
    $uploadDir = detectUploadDir();
    if (!$uploadDir) die('Upload dir not found');
    
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] 
               . dirname($_SERVER['REQUEST_URI']);
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="download_uploads.sh"');
    
    echo "#!/bin/bash
";
    echo "# RoboDoc Upload Downloader
";
    echo "# Run this on your local machine to download all uploads

";
    echo "mkdir -p uploads
";
    echo "cd uploads

";
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile()) continue;
        $rel = str_replace('\', '/', substr($file->getRealPath(), strlen($uploadDir)+1));
        $dir = dirname($rel);
        $url = $baseUrl . '/migrate.php?action=download_file&file=' . rawurlencode($rel);
        echo "mkdir -p " . escapeshellarg($dir) . PHP_EOL;
        echo "curl -o " . escapeshellarg($rel) . " -L " . escapeshellarg($url) . PHP_EOL;
    }
    exit;
}

// ── Upload list (shows folders to download individually) ──────────────────────
if ($action === 'list_uploads') {
    header('Content-Type: application/json');
    $uploadDir = detectUploadDir();
    if (!$uploadDir) { echo json_encode(['error' => 'Upload dir not found']); exit; }
    
    // List top-level subdirectories and files
    $items = [];
    $totalSize = 0;
    $iterator = new DirectoryIterator($uploadDir);
    foreach ($iterator as $item) {
        if ($item->isDot()) continue;
        if ($item->isDir()) {
            $size = 0;
            $count = 0;
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($item->getPathname())) as $f) {
                if ($f->isFile()) { $size += $f->getSize(); $count++; }
            }
            $items[] = ['name' => $item->getFilename(), 'type' => 'dir', 'size' => $size, 'count' => $count];
            $totalSize += $size;
        } else {
            $items[] = ['name' => $item->getFilename(), 'type' => 'file', 'size' => $item->getSize(), 'count' => 1];
            $totalSize += $item->getSize();
        }
    }
    echo json_encode(['dir' => $uploadDir, 'items' => $items, 'total_size' => round($totalSize/1024/1024, 2)]);
    exit;
}

// ── Download single file ─────────────────────────────────────────────────────
if ($action === 'download_file') {
    $uploadDir = detectUploadDir();
    $rel = ltrim($_GET['file'] ?? '', '/');
    // Security: no path traversal
    if (strpos($rel, '..') !== false || !$rel) die('Invalid path');
    $fullPath = $uploadDir . '/' . $rel;
    if (!$uploadDir || !is_file($fullPath)) die('File not found: ' . $fullPath);
    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

// ── Download subfolder as chunked ZIPs (max 50MB each) ───────────────────────
// ── Download single subfolder as ZIP ─────────────────────────────────────────
if ($action === 'download_folder') {
    $folder = basename($_GET['folder'] ?? '');
    if (!$folder) die('No folder specified');
    
    $uploadDir = detectUploadDir();
    $targetDir = $uploadDir . '/' . $folder;
    
    if (!$uploadDir || !is_dir($targetDir)) die('Folder not found: ' . $targetDir);
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="uploads_' . $folder . '.zip"');
    
    $zip = new ZipArchive();
    $tmpFile = tempnam(sys_get_temp_dir(), 'robodoc_');
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if (is_file($targetDir)) {
        $zip->addFile($targetDir, $folder);
    } else {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getRealPath(), $folder . '/' . substr($file->getRealPath(), strlen($targetDir)+1));
            }
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
    $uploadDir = detectUploadDir();
    $cfg = [
        'host' => defined('DB_HOST') ? DB_HOST : getenv('DB_HOST'),
        'name' => defined('DB_NAME') ? DB_NAME : getenv('DB_NAME'),
        'user' => defined('DB_USER') ? DB_USER : getenv('DB_USER'),
        'pass' => defined('DB_PASS') ? DB_PASS : getenv('DB_PASS'),
    ];
    try {
        $pdo    = new PDO("mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4", $cfg['user'], $cfg['pass']);
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $dbOk   = true;
    } catch (Exception $e) { $tables = []; $dbOk = false; }
    echo json_encode([
        'db_host'      => $cfg['host'],
        'db_name'      => $cfg['name'],
        'db_ok'        => $dbOk,
        'tables'       => count($tables),
        'upload_dir'   => $uploadDir ?? 'NOT FOUND',
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#0f172a;color:#e2e8f0;min-height:100vh;padding:2rem}
.card{background:#1e293b;border-color:#334155!important}
pre{background:#0f172a;padding:1rem;border-radius:6px;font-size:12px}
</style>
</head>
<body>
<div class="container" style="max-width:800px">
  <div class="text-center mb-4">
    <h3><i class="bi bi-box-arrow-up-right me-2"></i>RoboDoc Migration Tool</h3>
    <p class="text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>Diese Seite nach der Migration löschen!</p>
  </div>

  <!-- Info -->
  <div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-info-circle me-2 text-info"></i>System Info</div>
    <div class="card-body">
      <button class="btn btn-info btn-sm" onclick="loadInfo()">Info laden</button>
      <pre id="infoOut" class="mt-2" style="display:none"></pre>
    </div>
  </div>

  <!-- DB Dump -->
  <div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-database me-2 text-primary"></i>Schritt 1 — Datenbank exportieren</div>
    <div class="card-body">
      <p class="small text-muted">Vollständiger SQL-Dump aller Tabellen.</p>
      <a href="?action=dump" class="btn btn-primary">
        <i class="bi bi-download me-1"></i>SQL Dump herunterladen
      </a>
    </div>
  </div>

  <!-- Uploads -->
  <div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-folder me-2 text-success"></i>Schritt 2 — Uploads exportieren (Ordner für Ordner)</div>
    <div class="card-body">
      <p class="small text-muted">Jeder Ordner wird einzeln als ZIP heruntergeladen um Timeouts zu vermeiden.</p>
      <div class="d-flex gap-2 mb-3">
      <button class="btn btn-success btn-sm" onclick="listUploads()">
        <i class="bi bi-folder2-open me-1"></i>Ordner anzeigen
      </button>
      <a href="?action=wget_script" class="btn btn-warning btn-sm">
        <i class="bi bi-terminal me-1"></i>Download-Script (curl) herunterladen
      </a>
    </div>
    <p class="small text-muted">💡 Tipp: Lade das curl-Script herunter und führe es lokal aus — lädt alle Dateien automatisch einzeln herunter.</p>
    <div>
        <i class="bi bi-folder2-open me-1"></i>Upload-Ordner anzeigen
      </button>
      <div id="uploadList"></div>
    </div>
  </div>

  <!-- Next steps -->
  <div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-check2-all me-2 text-warning"></i>Schritt 3 — Nach der Migration</div>
    <div class="card-body small text-muted">
      <ol class="mb-0">
        <li>SQL Dump in Azure MySQL importieren (Cloud Shell)</li>
        <li>Jeden ZIP entpacken und in App Service hochladen (Kudu)</li>
        <li>GitHub Deployment einrichten</li>
        <li>Environment Variables setzen</li>
        <li><strong>Diese Datei <code>migrate.php</code> löschen!</strong></li>
      </ol>
    </div>
  </div>
</div>

<script>
function loadInfo() {
  fetch('?action=info').then(r=>r.json()).then(d=>{
    const el = document.getElementById('infoOut');
    el.style.display='block';
    el.textContent = JSON.stringify(d,null,2);
  });
}

function listUploads() {
  document.getElementById('uploadList').innerHTML = '<p class="small text-muted">Wird geladen...</p>';
  fetch('?action=list_uploads').then(r=>r.json()).then(d=>{
    if (d.error) { document.getElementById('uploadList').innerHTML = '<div class="alert alert-danger">'+d.error+'</div>'; return; }
    let html = '<p class="small text-muted">Upload-Verzeichnis: <code>'+d.dir+'</code> — Gesamt: <strong>'+d.total_size+' MB</strong></p>';
    html += '<div class="list-group">';
    d.items.forEach(item => {
      const size = (item.size/1024/1024).toFixed(2);
      const icon = item.type==='dir' ? 'bi-folder-fill text-warning' : 'bi-file-earmark text-info';
      html += `<div class="list-group-item list-group-item-dark d-flex align-items-center justify-content-between">
        <span><i class="bi ${icon} me-2"></i>${item.name} <small class="text-muted">(${size} MB, ${item.count} Dateien)</small></span>
        <a href="?action=download_folder&folder=${encodeURIComponent(item.name)}" class="btn btn-sm btn-outline-success">
          <i class="bi bi-download me-1"></i>ZIP
        </a>
      </div>`;
    });
    html += '</div>';
    document.getElementById('uploadList').innerHTML = html;
  });
}
</script>
</body>
</html>
