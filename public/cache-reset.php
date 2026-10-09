<?php
// Temporary OPcache reset - delete after use
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully.";
} else {
    echo "OPcache not available.";
}
// Also show current file modification times
echo "<br>TestCycleController mtime: " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/../app/controllers/TestCycleController.php'));
echo "<br>Content check (executed_by): " . (strpos(file_get_contents(__DIR__ . '/../app/controllers/TestCycleController.php'), 'tr.executed_by') !== false ? 'FOUND (old)' : 'NOT FOUND (fixed)');
?>
