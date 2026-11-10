<?php
// Azure App Service entry point — forwards to public/
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Strip leading slash
$uri = ltrim($uri, '/');

// If already accessing public/, let it through
if (str_starts_with($uri, 'public/')) {
    require __DIR__ . '/' . $uri;
    exit;
}

// Forward to public/index.php
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
$_SERVER['SCRIPT_NAME']     = '/public/index.php';

chdir(__DIR__ . '/public');
require __DIR__ . '/public/index.php';
