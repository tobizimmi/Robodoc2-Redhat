<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/View.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Audit.php';
require_once __DIR__ . '/Totp.php';
require_once __DIR__ . '/Encryption.php';
require_once __DIR__ . '/QrCode.php';

// Auto-load controllers
foreach (glob(__DIR__ . '/controllers/*.php') as $f) {
    require_once $f;
}

Auth::start();

// ── Security headers ─────────────────────────────────────────
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    // HSTS: enforce HTTPS for 1 year
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }        // DENY was too strict for any embedded use
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Content Security Policy — enforce (not report-only)
    header("Content-Security-Policy: "
         . "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; "
         . "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; "
         . "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net data:; "
         . "img-src 'self' data: blob: api.qrserver.com; "
         . "media-src 'self' blob:; "
         . "connect-src 'self' cdn.jsdelivr.net; "
         . "frame-ancestors 'self'; "
         . "object-src 'none'");
}

// Run migrations whenever the migration list changes (lock file keyed by content hash + DB name).
// Lock file prefix includes DB_NAME so staging and live on the same server don't clear each other's locks.
$_rdDbSlug  = preg_replace('/[^a-z0-9]/i', '_', DB_NAME);
$_rdMigHash = substr(md5(implode('|', getMigrations()) . DB_NAME), 0, 12);
$_rdLock    = sys_get_temp_dir() . '/robodoc2_schema_' . $_rdDbSlug . '_' . $_rdMigHash . '.lock';
if (!file_exists($_rdLock)) {
    // Remove stale lock files for THIS environment only (same DB prefix)
    foreach (glob(sys_get_temp_dir() . '/robodoc2_schema_' . $_rdDbSlug . '_*.lock') as $_old) {
        @unlink($_old);
    }
    runMigrations();
    touch($_rdLock);
}
unset($_rdLock, $_rdMigHash, $_rdDbSlug);
