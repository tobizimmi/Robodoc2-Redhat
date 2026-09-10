<?php
declare(strict_types=1);

class DuplicateController
{
    public static function index(): void
    {
        Auth::requireAdmin();

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
             ORDER BY cnt DESC LIMIT 200'
        );

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
             ORDER BY cnt DESC, p.name, e.title LIMIT 200'
        );

        $byTitleGlobal = Database::fetchAll(
            'SELECT e.title,
                    COUNT(*) cnt,
                    GROUP_CONCAT(e.id ORDER BY e.id ASC SEPARATOR ",") ids,
                    GROUP_CONCAT(IFNULL(p.name,"kein Projekt") ORDER BY e.id ASC SEPARATOR "||") project_names,
                    GROUP_CONCAT(e.created_at ORDER BY e.id ASC SEPARATOR "||") dates
             FROM entries e
             LEFT JOIN projects p ON p.id = e.project_id
             WHERE e.title IS NOT NULL AND e.title != ""
             GROUP BY e.title
             HAVING COUNT(*) > 1 AND COUNT(DISTINCT IFNULL(e.project_id,0)) > 1
             ORDER BY cnt DESC LIMIT 100'
        );

        $debug = [
            'total_entries'    => (int)(Database::fetchOne('SELECT COUNT(*) c FROM entries')['c'] ?? 0),
            'with_origin'      => (int)(Database::fetchOne('SELECT COUNT(*) c FROM entries WHERE live_origin_id IS NOT NULL AND live_origin_id > 0')['c'] ?? 0),
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

    /** Move all related data to keepId, then delete the duplicate */
    private static function deleteEntry(int $deleteId, int $keepId): bool
    {
        if (!$deleteId || $deleteId === $keepId) return false;
        // All tables that may reference entry_id
        $tables = [
            'entry_attachments', 'entry_comments', 'entry_history',
            'entry_mowers', 'entry_tags', 'entry_sharepoint_files',
            'entry_test_results', 'sprint_entries', 'kanban_notes',
            'live_sync_queue', 'quick_captures', 'eight_d_reports',
            'test_plan_item_entries', 'xray_entry_links',
            'dismissed_zentao_bugs', 'test_customer_feedback',
        ];
        foreach ($tables as $table) {
            try {
                // Check if table has entry_id column
                $cols = Database::fetchAll("SHOW COLUMNS FROM `$table` LIKE 'entry_id'");
                if ($cols) {
                    Database::execute(
                        "UPDATE `$table` SET entry_id=? WHERE entry_id=?",
                        [$keepId, $deleteId]
                    );
                }
            } catch (Throwable) {}
        }
        // entry_links has from_entry_id and to_entry_id
        try {
            Database::execute('UPDATE entry_links SET from_entry_id=? WHERE from_entry_id=?', [$keepId, $deleteId]);
            Database::execute('UPDATE entry_links SET to_entry_id=? WHERE to_entry_id=?', [$keepId, $deleteId]);
        } catch (Throwable) {}
        // Now delete — use FOREIGN KEY checks disabled as fallback
        try {
            Database::execute('DELETE FROM entries WHERE id=?', [$deleteId]);
            return true;
        } catch (Throwable $e) {
            // If FK still blocks, force delete with checks off
            try {
                Database::execute('SET FOREIGN_KEY_CHECKS=0');
                Database::execute('DELETE FROM entries WHERE id=?', [$deleteId]);
                Database::execute('SET FOREIGN_KEY_CHECKS=1');
                return true;
            } catch (Throwable) {
                Database::execute('SET FOREIGN_KEY_CHECKS=1');
                return false;
            }
        }
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
        $ok = self::deleteEntry($id, $keepId);
        Audit::log('duplicate_deleted', 'entry', $id, "Kept #$keepId ok=$ok");
        flash($ok ? 'success' : 'error', $ok ? "Eintrag #$id gelöscht." : "Fehler beim Löschen von #$id.");
        redirect('/admin/duplicates');
    }

    public static function deleteAll(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $mode    = $_POST['mode'] ?? 'title';
        $removed = 0;
        $errors  = 0;

        if ($mode === 'origin') {
            $dupes = Database::fetchAll(
                'SELECT MIN(id) keep_id, GROUP_CONCAT(id ORDER BY id ASC) ids
                 FROM entries WHERE live_origin_id IS NOT NULL AND live_origin_id > 0
                 GROUP BY live_origin_id HAVING COUNT(*) > 1'
            );
        } elseif ($mode === 'title_global') {
            $dupes = Database::fetchAll(
                'SELECT MIN(id) keep_id, GROUP_CONCAT(id ORDER BY id ASC) ids
                 FROM entries WHERE title IS NOT NULL AND title != ""
                 GROUP BY title HAVING COUNT(*) > 1'
            );
        } else {
            $dupes = Database::fetchAll(
                'SELECT MIN(id) keep_id, GROUP_CONCAT(id ORDER BY id ASC) ids
                 FROM entries WHERE title IS NOT NULL AND title != ""
                 GROUP BY title, project_id HAVING COUNT(*) > 1'
            );
        }

        foreach ($dupes as $dupe) {
            $ids    = explode(',', $dupe['ids']);
            $keepId = (int)array_shift($ids);
            foreach ($ids as $deleteId) {
                $ok = self::deleteEntry((int)$deleteId, $keepId);
                $ok ? $removed++ : $errors++;
            }
        }

        Audit::log('duplicates_bulk_deleted', 'admin', 0, "mode=$mode removed=$removed errors=$errors");
        $msg = "$removed Duplikate gelöscht.";
        if ($errors > 0) $msg .= " $errors konnten nicht gelöscht werden.";
        flash($removed > 0 ? 'success' : 'warning', $msg);
        redirect('/admin/duplicates');
    }
}
