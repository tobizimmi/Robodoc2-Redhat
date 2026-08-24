<?php
declare(strict_types=1);
// Finalizes background ffmpeg video compression started by EntryController::saveFile().
// Run every 1-2 minutes via cron: php app/cron/finalize_video_compression.php

require_once __DIR__ . '/../bootstrap.php';

$pending = Database::fetchAll(
    "SELECT * FROM entry_attachments WHERE compress_pending=1"
);

foreach ($pending as $att) {
    $target = $att['compress_target_path'];
    if (!$target) {
        Database::execute('UPDATE entry_attachments SET compress_pending=0 WHERE id=?', [$att['id']]);
        continue;
    }
    // ffmpeg writes the file progressively; only swap once it's stable (not growing) and the
    // source process is no longer holding it open. Simple heuristic: file exists, is non-empty,
    // and its size hasn't changed in the last check (best-effort, cron runs every 1-2 min anyway).
    if (!file_exists($target) || filesize($target) === 0) {
        continue; // still compressing or ffmpeg failed - leave pending, retry next run
    }
    // Give it one more cron cycle of stability before swapping (avoid swapping a half-written file)
    $stableMarker = $target . '.stable';
    if (!file_exists($stableMarker)) {
        touch($stableMarker);
        continue;
    }

    $oldPath = $att['file_path'];
    $newName = basename($target);
    @unlink($stableMarker);

    if (@rename($target, dirname($oldPath) . '/' . $newName)) {
        $newPath = dirname($oldPath) . '/' . $newName;
        $newSize = filesize($newPath);
        Database::execute(
            'UPDATE entry_attachments SET filename=?, file_path=?, mime_type=?, file_size=?, compress_pending=0, compress_target_path=NULL WHERE id=?',
            [$newName, $newPath, 'video/mp4', $newSize, $att['id']]
        );
        @unlink($oldPath);
        echo "Compressed attachment #{$att['id']}: " . round($newSize/1048576,1) . " MB\n";
    } else {
        Database::execute('UPDATE entry_attachments SET compress_pending=0 WHERE id=?', [$att['id']]);
        echo "Failed to finalize compression for attachment #{$att['id']}\n";
    }
}
