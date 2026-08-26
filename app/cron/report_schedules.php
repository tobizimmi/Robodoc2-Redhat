#!/usr/bin/env php
<?php
/**
 * Report-Zeitplan cron job.
 * Prüft alle aktiven Zeitpläne (Admin/Report Builder > Zeitplan) und verschickt
 * fällige Berichte per E-Mail (signierter Link, kein Login für den Empfänger nötig).
 *
 * Server crontab (alle 15 Minuten, versetzt zu jira_sync/zentao_sync):
 *   10,25,40,55 * * * * php /var/www/vhosts/zimmimail.de/httpdocs/RoboDoc/cron/report_schedules.php >> /tmp/robodoc_report_schedules.log 2>&1
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

chdir(dirname(__DIR__, 1));
require_once __DIR__ . '/../bootstrap.php';

echo '[' . date('Y-m-d H:i:s') . '] Report-Zeitpläne: Prüfung gestartet' . PHP_EOL;

$sent = ReportController::runScheduledSends();

echo '[' . date('Y-m-d H:i:s') . "] Fertig — $sent Bericht(e) versendet" . PHP_EOL;
