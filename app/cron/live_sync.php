#!/usr/bin/env php
<?php
/**
 * Live-Sync retry cron job.
 * Retries any entry pushes that failed or timed out on their first attempt
 * (network hiccup, target briefly unreachable, etc.) — see LiveSyncController.
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

echo '[' . date('Y-m-d H:i:s') . '] Live-Sync: Retry-Prüfung gestartet' . PHP_EOL;

$sent = LiveSyncController::runQueueRetries();

echo '[' . date('Y-m-d H:i:s') . "] Fertig — $sent Eintrag/Einträge nachgesendet" . PHP_EOL;
