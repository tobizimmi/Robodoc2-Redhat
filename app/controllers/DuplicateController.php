<?php
declare(strict_types=1);

/**
 * Duplicate detection and resolution for entries.
 * Finds duplicates by: same title, same live_origin_id, or similar content.
 */
class DuplicateController
{
    /** Show all duplicate groups */
    public static function index(): void
    {
        Auth::requireAdmin();

        // Find duplicates by exact title match within same project
        $byTitle = Database::fetchAll(
            'SELECT e.title, e.project_id, p.name project_name,
                    COUNT(*) cnt,
                    GROUP_CONCAT(e.id ORDER BY e.id ASC SEPARATOR ",") ids,
                    GROUP_CONCAT(e.created_at ORDER BY e.id ASC SEPARATOR "||") dates,
                    GROUP_CONCAT(IFNULL(u.name,"?") ORDER BY e.id ASC SEPARATOR "||") authors
             FROM entries e
             LEFT JOIN projects p ON p.id = e.project_id
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.title IS NOT NULL AND e.title != ""
             GROUP BY e.title, e.project_id
             HAVING COUNT(*) > 1
             ORDER BY cnt DESC, p.name, e.title
             LIMIT 200'
        );

        // Find duplicates by live_origin_id
        $byOrigin = Database::fetchAll(
            'SELECT e.live_origin_id,
                    COUNT(*) cnt,
                    GROUP_CONCAT(e.id ORDER BY e.id ASC SEPARATOR ",") ids,
                    GROUP_CONCAT(e.title ORDER BY e.id ASC SEPARATOR "||") titles,
                    GROUP_CONCAT(e.created_at ORDER BY e.id ASC SEPARATOR "||") dates
             FROM entries e
             WHERE e.live_origin_id IS NOT NULL AND e.live_origin_id > 0
             GROUP BY e.live_origin_id
             HAVING COUNT(*) > 1
             ORDER BY cnt DESC
             LIMIT 200'
        );

        View::render('admin/duplicates', [
            'title'    => 'Duplikate',
            'byTitle'  => $byTitle,
            'byOrigin' => $byOrigin,
        ]);
    }

    /** Delete a specific entry (keep another) */
    public static function delete(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $id     = (int)($_POST['id'] ?? 0);
        $keepId = (int)($_POST['keep_id'] ?? 0);
        if (!$id || $id === $keepId) {
            flash('error', 'Ungültige IDs.');
            redirect('/admin/duplicates');
        }

        // Move attachments to kept entry
        try { Database::execute('UPDATE entry_attachments SET entry_id=? WHERE entry_id=?', [$keepId, $id]); } catch (Throwable) {}
        // Move comments
        try { Database::execute('UPDATE entry_comments SET entry_id=? WHERE entry_id=?', [$keepId, $id]); } catch (Throwable) {}
        // Move history
        try { Database::execute('UPDATE entry_history SET entry_id=? WHERE entry_id=?', [$keepId, $id]); } catch (Throwable) {}
        // Move test results
        try { Database::execute('UPDATE test_results SET entry_id=? WHERE entry_id=?', [$keepId, $id]); } catch (Throwable) {}
        // Delete duplicate
        Database::execute('DELETE FROM entries WHERE id=?', [$id]);

        Audit::log('duplicate_deleted', 'entry', $id, "Kept entry #$keepId");
        flash('success', "Eintrag #$id gelöscht. Eintrag #$keepId behalten.");
        redirect('/admin/duplicates');
    }

    /** Delete all duplicates automatically (keep oldest) */
    public static function deleteAll(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $mode = $_POST['mode'] ?? 'origin'; // 'origin' or 'title'
        $removed = 0;

        if ($mode === 'origin') {
            $dupes = Database::fetchAll(
                'SELECT MIN(id) keep_id, GROUP_CONCAT(id ORDER BY id ASC) ids
                 FROM entries
                 WHERE live_origin_id IS NOT NULL AND live_origin_id > 0
                 GROUP BY live_origin_id HAVING COUNT(*) > 1'
            );
        } else {
            $dupes = Database::fetchAll(
                'SELECT MIN(id) keep_id, GROUP_CONCAT(id ORDER BY id ASC) ids
                 FROM entries
                 WHERE title IS NOT NULL AND title != ""
                 GROUP BY title, project_id HAVING COUNT(*) > 1'
            );
        }

        foreach ($dupes as $dupe) {
            $ids    = explode(',', $dupe['ids']);
            $keepId = (int)array_shift($ids);
            foreach ($ids as $deleteId) {
                $deleteId = (int)$deleteId;
                try {
                    Database::execute('UPDATE entry_attachments SET entry_id=? WHERE entry_id=?', [$keepId, $deleteId]);
                    Database::execute('UPDATE entry_comments SET entry_id=? WHERE entry_id=?', [$keepId, $deleteId]);
                    Database::execute('UPDATE entry_history SET entry_id=? WHERE entry_id=?', [$keepId, $deleteId]);
                    Database::execute('UPDATE test_results SET entry_id=? WHERE entry_id=?', [$keepId, $deleteId]);
                    Database::execute('DELETE FROM entries WHERE id=?', [$deleteId]);
                    $removed++;
                } catch (Throwable) {}
            }
        }

        Audit::log('duplicates_bulk_deleted', 'admin', 0, "mode=$mode removed=$removed");
        flash('success', "$removed Duplikate gelöscht.");
        redirect('/admin/duplicates');
    }
}
