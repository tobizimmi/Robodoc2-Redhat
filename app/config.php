<?php
declare(strict_types=1);

// Application secret key — used for HMAC, IP hashing, etc.
define('APP_KEY', getenv('APP_KEY') ?: 'robodoc2-change-me-in-production-x9k2p');

// Auto-detect BASE_URL for direct-domain deployments
$_rdHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
if (preg_match('/^(www\.|live\.)?roboticsdocs?\.de$/i', $_rdHost)) {
    define('BASE_URL', '');
}
unset($_rdHost);

// Load local overrides — config.local.php takes full priority
$localConfig = file_exists(__DIR__ . '/config.local.php')
    ? __DIR__ . '/config.local.php'
    : __DIR__ . '/../config.local.php';
if (file_exists($localConfig) && is_readable($localConfig)) {
    set_error_handler(static fn() => true, E_WARNING);
    try { require_once $localConfig; } catch (Throwable) {}
    restore_error_handler();
}

// Fallback defaults if config.local.php not present
if (!defined('DB_HOST'))     define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
if (!defined('DB_PORT'))     define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
if (!defined('DB_NAME'))     define('DB_NAME',     getenv('DB_NAME')     ?: 'robodoc2');
if (!defined('DB_USER'))     define('DB_USER',     getenv('DB_USER')     ?: 'root');
if (!defined('DB_PASS'))     define('DB_PASS',     getenv('DB_PASS')     ?: '');
if (!defined('APP_SECRET'))  define('APP_SECRET',  getenv('APP_SECRET')  ?: 'change-me-' . sha1(__FILE__));
if (!defined('APP_NAME'))    define('APP_NAME',    'RoboDoc');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.0');
if (!defined('UPLOAD_DIR'))  define('UPLOAD_DIR',  dirname(__DIR__) . '/uploads/');
if (!defined('BASE_URL'))    define('BASE_URL',    '');

define('MAX_FILE_SIZE', 500 * 1024 * 1024);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'image/heic', 'image/heif',
    'video/mp4', 'video/quicktime', 'video/webm', 'video/avi', 'video/x-msvideo',
    'application/pdf', 'application/zip',
    'text/plain', 'text/csv', 'application/json', 'application/octet-stream',
]);

date_default_timezone_set('Europe/Berlin');
