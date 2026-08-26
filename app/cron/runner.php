<?php
declare(strict_types=1);
/**
 * RoboDoc Cron Runner
 * This is the ONLY file that needs to be registered on the server:
 *   * * * * * php /path/to/app/cron/runner.php
 *
 * It reads active jobs from the DB and executes them when due.
 */

require_once __DIR__ . '/../bootstrap.php';

// Ensure table exists
Database::execute("
    CREATE TABLE IF NOT EXISTS cron_jobs (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `key`         VARCHAR(100) NOT NULL UNIQUE,
        label         VARCHAR(200) NOT NULL,
        description   TEXT,
        script        VARCHAR(300) NOT NULL,
        interval_min  INT UNSIGNED NOT NULL DEFAULT 5,
        is_active     TINYINT(1)   NOT NULL DEFAULT 0,
        last_run_at   DATETIME,
        last_run_ok   TINYINT(1),
        last_run_msg  TEXT,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

// Seed built-in jobs if not present
$builtIn = [
    [
        'key'          => 'video_compression',
        'label'        => 'Video Compression',
        'description'  => 'Finalizes background ffmpeg video compression for uploaded videos.',
        'script'       => 'finalize_video_compression.php',
        'interval_min' => 1,
    ],
    [
        'key'          => 'jira_sync',
        'label'        => 'Jira Sync',
        'description'  => 'Detects Jira status/priority changes and flags them for review.',
        'script'       => 'jira_sync.php',
        'interval_min' => 15,
    ],
    [
        'key'          => 'zentao_sync',
        'label'        => 'Zentao Sync',
        'description'  => 'Detects Zentao status/priority changes and flags them for review.',
        'script'       => 'zentao_sync.php',
        'interval_min' => 15,
    ],
    [
        'key'          => 'report_schedules',
        'label'        => 'Report-Zeitpläne',
        'description'  => 'Verschickt geplante Report-Builder-Berichte per E-Mail, sobald sie fällig sind.',
        'script'       => 'report_schedules.php',
        'interval_min' => 15,
    ],
];

foreach ($builtIn as $job) {
    Database::execute(
        "INSERT IGNORE INTO cron_jobs (`key`, label, description, script, interval_min)
         VALUES (?, ?, ?, ?, ?)",
        [$job['key'], $job['label'], $job['description'], $job['script'], $job['interval_min']]
    );
}

// Fetch active jobs
$jobs = Database::fetchAll("SELECT * FROM cron_jobs WHERE is_active = 1");

foreach ($jobs as $job) {
    // Check if due
    $lastRun     = $job['last_run_at'] ? new DateTime($job['last_run_at']) : null;
    $intervalSec = (int)$job['interval_min'] * 60;
    $now         = new DateTime();

    if ($lastRun && ($now->getTimestamp() - $lastRun->getTimestamp()) < $intervalSec) {
        continue; // Not due yet
    }

    // Execute
    $script = __DIR__ . '/' . basename($job['script']);
    if (!file_exists($script)) {
        Database::execute(
            "UPDATE cron_jobs SET last_run_at=NOW(), last_run_ok=0, last_run_msg=? WHERE id=?",
            ["Script not found: {$job['script']}", $job['id']]
        );
        continue;
    }

    $output   = [];
    $exitCode = 0;
    exec('php ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);
    $msg = implode("\n", array_slice($output, -20)); // keep last 20 lines

    Database::execute(
        "UPDATE cron_jobs SET last_run_at=NOW(), last_run_ok=?, last_run_msg=? WHERE id=?",
        [$exitCode === 0 ? 1 : 0, $msg ?: 'OK', $job['id']]
    );
}
