<?php
declare(strict_types=1);

/**
 * Duplicate detection and resolution for entries.
 * Finds duplicates by: same title+project, same live_origin_id, or same title globally.
 */
class DuplicateController
{
    public static function index(): void
    {
        Auth::requireAdmin();

        // 1. Duplikate per live_origin_id (importierte Einträge)
        $byOrigin = Database::fetchAll(
            'SELECT e.live_origin_id,
                    COUNT(*) cnt,
                    GROUP_CONCAT(e.id ORDER BY e.id ASC SEPARATOR ",") ids,
                    GROUP_CONCAT(IFNULL(e.title,"(kein Titel)") ORDER BY e.id ASC SEPARATOR "||") titles,
                    GROUP_CONCAT(e.created_at ORDER BY e.id ASC SEPARATOR "||") dates
             FROM entries e
             WHERE e.live_origin_id IS NOT NULL AND e.live_origin_id > 0
             GROUP BY e.live_origin_id
             HAVING COUNT(*) > 1
             ORDER BY cnt DESC
             LIMIT 200'
        );

        // 2. Duplikate per Titel + Projekt
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

        // 3. Duplikate per Titel global (projektübergreifend, falls Projekt fehlt)
        $byTitleGlobal = Database::fetchAll(
            'SELECT e.title,
                    COUNT(*) cnt,
                    COUNT(DISTINCT IFNULL(e.project_id,0)) projects,
                    GROUP_CONCAT(e.id ORDER BY e.id ASC SEPARATOR ",") ids,
                    GROUP_CONCAT(IFNULL(p.name,"kein Projekt") ORDER BY e.id ASC SEPARATOR "||") project_names,
                    GROUP_CONCAT(e.created_at ORDER BY e.id ASC SEPARATOR "||") dates
             FROM entries e
             LEFT JOIN projects p ON p.id = e.project_id
             WHERE e.title IS NOT NULL AND e.title != ""
             GROUP BY e.title
             HAVING COUNT(*) > 1 AND COUNT(DISTINCT IFNULL(e.project_id,0)) > 1
             ORDER BY cnt DESC
             LIMIT 100'
        );

        // Debug info
        $debug = [
            'total_entries' => (int)(Database::fetchOne('SELECT COUNT(*) c FROM entries')['c'] ?? 0),
            'with_origin'   => (int)(Database::fetchOne('SELECT COUNT(*) c FROM entries WHERE live_origin_id IS NOT NULL AND live_origin_id > 0')['c'] ?? 0),
            'by_origin_groups' => count($byOrigin),
            'by_title_groups'  => count($byTitle),
            'by_title_global'  => count($byTitleGlobal),
        ];

        View::render('admin/duplicates', [
            'title'         => 'Duplikate',
            'byTitle'       => $byTitle,
            'byOrigin'      => $byOrigin,
            'byTitleGlobal' => $byTitleGlobal,
            'debug'         => $debug,
        ]);
    }

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
        try { Database::execute('UPDATE entry_attachments SET entry_id=? WHERE entry_id=?', [$keepId, $id]); } catch (Throwable) {}
        try { Database::execute('UPDATE entry_comments SET entry_id=? WHERE entry_id=?', [$keepId, $id]); } catch (Throwable) {}
        try { Database::execute('UPDATE entry_history SET entry_id=? WHERE entry_id=?', [$keepId, $id]); } catch (Throwable) {}
        try { Database::execute('UPDATE test_results SET entry_id=? WHERE entry_id=?', [$keepId, $id]); } catch (Throwable) {}
        Database::execute('DELETE FROM entries WHERE id=?', [$id]);
        Audit::log('duplicate_deleted', 'entry', $id, "Kept entry #$keepId");
        flash('success', "Eintrag #$id gelöscht. Eintrag #$keepId behalten.");
        redirect('/admin/duplicates');
    }

    public static function deleteAll(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $mode    = $_POST['mode'] ?? 'title';
        $removed = 0;

        if ($mode === 'origin') {
            $dupes = Database::fetchAll(
                'SELECT MIN(id) keep_id, GROUP_CONCAT(id ORDER BY id ASC) ids
                 FROM entries
                 WHERE live_origin_id IS NOT NULL AND live_origin_id > 0
                 GROUP BY live_origin_id HAVING COUNT(*) > 1'
            );
        } elseif ($mode === 'title_global') {
            $dupes = Database::fetchAll(
                'SELECT MIN(id) keep_id, GROUP_CONCAT(id ORDER BY id ASC) ids
                 FROM entries
                 WHERE title IS NOT NULL AND title != ""
                 GROUP BY title HAVING COUNT(*) > 1'
            );
        } else {
            // by title + project
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
