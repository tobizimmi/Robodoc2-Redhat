#!/usr/bin/env php
<?php
/**
 * Zentao background change-detection cron job.
 * Flags entries where Zentao status or priority differs — does NOT auto-update entries.
 * Users review and accept changes manually via the Review page.
 *
 * Server crontab (run every 15 minutes, offset by 5 min from Jira cron):
 *   5,20,35,50 * * * * php /var/www/vhosts/zimmimail.de/httpdocs/RoboDoc/cron/zentao_sync.php >> /tmp/robodoc_zentao_sync.log 2>&1
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

chdir(dirname(__DIR__, 1));
require_once __DIR__ . '/../bootstrap.php';

echo '[' . date('Y-m-d H:i:s') . '] Zentao background check started' . PHP_EOL;

// Reset throttle so cron always runs
try {
    Database::execute("DELETE FROM app_settings WHERE setting_key='zentao_last_bulk_check'");
} catch (\Throwable) {}

$changed = ZentaoController::bulkCheckChanges();

echo '[' . date('Y-m-d H:i:s') . '] Done — ' . $changed . ' entr' . ($changed === 1 ? 'y' : 'ies') . ' flagged with changes' . PHP_EOL;
