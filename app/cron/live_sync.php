#!/usr/bin/env php
<?php
/**
 * Live-Sync cron job.
 * Runs both directions - see LiveSyncController for details:
 *   - PUSH retry: retries any entry pushes that failed or timed out earlier.
 *   - PULL: if this instance is configured to pull from another one (used
 *     when this instance can't be reached directly, e.g. an OpenShift Route
 *     not reachable from outside), fetches and ingests whatever is pending
 *     on the source.
 * Both are no-ops (return 0 immediately) if not configured or if Live-Sync
 * is switched off (Admin > Live-Sync), so this script is safe to enable on
 * every instance regardless of which role, if any, it actually plays.
 *
 * Server crontab (alle 15 Minuten):
 *   5,20,35,50 * * * * php /var/www/vhosts/zimmimail.de/httpdocs/RoboDoc/cron/live_sync.php >> /tmp/robodoc_live_sync.log 2>&1
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

chdir(dirname(__DIR__, 1));
require_once __DIR__ . '/../bootstrap.php';

echo '[' . date('Y-m-d H:i:s') . '] Live-Sync: Prüfung gestartet' . PHP_EOL;

$sent = LiveSyncController::runQueueRetries();
$pulled = LiveSyncController::pullFromSource();

echo '[' . date('Y-m-d H:i:s') . "] Fertig — $sent nachgesendet, $pulled abgeholt" . PHP_EOL;
