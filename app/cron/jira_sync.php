#!/usr/bin/env php
<?php
/**
 * Jira background change-detection cron job.
 * Flags entries where Jira status or priority differs from the local entry — does NOT
 * auto-update entries. Users review and accept changes manually via the Review page.
 *
 * Server crontab (run every 15 minutes):
 *   *\/15 * * * * php /var/www/vhosts/zimmimail.de/httpdocs/RoboDoc/cron/jira_sync.php >> /tmp/robodoc_jira_sync.log 2>&1
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

chdir(dirname(__DIR__, 1));
require_once __DIR__ . '/../bootstrap.php';

echo '[' . date('Y-m-d H:i:s') . '] Jira background check started' . PHP_EOL;

// Reset the throttle so the cron always runs (throttle is only for the web UI button)
try {
    Database::execute("DELETE FROM app_settings WHERE setting_key='jira_last_bulk_check'");
} catch (\Throwable) {}

$changed = JiraController::bulkCheckChanges();

echo '[' . date('Y-m-d H:i:s') . '] Done — ' . $changed . ' entr' . ($changed === 1 ? 'y' : 'ies') . ' flagged with changes' . PHP_EOL;
