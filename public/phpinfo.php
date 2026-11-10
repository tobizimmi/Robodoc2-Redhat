<?php
// Temporary - delete after checking!
echo "<h2>PHP Version: " . PHP_VERSION . "</h2>";
echo "<h3>Loaded Extensions:</h3><pre>";
$exts = get_loaded_extensions();
sort($exts);
foreach ($exts as $e) {
    echo $e . "\n";
}
echo "</pre>";
echo "<h3>PDO Drivers:</h3><pre>";
print_r(PDO::getAvailableDrivers());
echo "</pre>";
?>