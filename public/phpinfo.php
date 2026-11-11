<?php
// Diagnostics – admin only. Delete after checking!
require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

echo "PHP Version: " . PHP_VERSION . " (" . PHP_SAPI . ")\n";
echo "Loaded php.ini: " . (php_ini_loaded_file() ?: 'none') . "\n";
echo "Scanned ini files: " . (php_ini_scanned_files() ?: 'none') . "\n\n";

echo "== Upload limits ==\n";
foreach (['upload_max_filesize', 'post_max_size', 'max_execution_time', 'max_input_time', 'memory_limit', 'upload_tmp_dir'] as $k) {
    printf("%-22s %s\n", $k, ini_get($k));
}

echo "\n== nginx ==\n";
$ng = '/etc/nginx/conf.d/upload-limit.conf';
echo $ng . ': ' . (is_readable($ng) ? trim((string)file_get_contents($ng)) : 'MISSING') . "\n";
foreach (glob('/etc/nginx/conf.d/*.conf') ?: [] as $f) echo "  conf.d: " . basename($f) . "\n";
foreach (glob('/etc/nginx/sites-enabled/*') ?: [] as $f) {
    $c = (string)@file_get_contents($f);
    preg_match_all('/client_max_body_size\s+[^;]+;/', $c, $m);
    echo "  sites-enabled/" . basename($f) . ': ' . ($m[0] ? implode(' ', $m[0]) : 'no client_max_body_size') . "\n";
}

echo "\n== Storage ==\n";
$dir = rtrim(UPLOAD_DIR, '/');
echo "UPLOAD_DIR: $dir (" . (is_dir($dir) ? (is_writable($dir) ? 'writable' : 'NOT writable') : 'MISSING') . ")\n";
$free = @disk_free_space($dir); $total = @disk_total_space($dir);
if ($free !== false && $total) printf("Disk: %.1f GB free of %.1f GB (%.0f%% used)\n", $free / 1e9, $total / 1e9, 100 - $free / $total * 100);
echo "Startup script marker: " . (is_file('/tmp/robodoc_startup_ran') ? trim((string)file_get_contents('/tmp/robodoc_startup_ran')) : 'not found') . "\n";

echo "\n== Extensions ==\n";
foreach (['gd', 'zip', 'exif', 'fileinfo', 'pdo_mysql', 'curl', 'openssl', 'mbstring'] as $e) {
    printf("%-10s %s\n", $e, extension_loaded($e) ? 'yes' : 'MISSING');
}
echo "ffmpeg: " . (trim((string)@shell_exec('command -v ffmpeg')) ?: 'MISSING') . "\n";
