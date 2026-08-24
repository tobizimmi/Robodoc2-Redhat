<?php
declare(strict_types=1);

class SearchController {
    public static function index(): void {
        Auth::requireView('search');
        $q       = trim($_GET['q'] ?? '');
        $results = [];

        if (strlen($q) >= 2) {
            $like = '%' . $q . '%';

            [$entrySql, $entryParams] = Auth::entryAccessClause('e');
            $results['entries'] = Database::fetchAll(
                "SELECT e.id, e.title, e.description, e.entry_date,
                        et.name type_name, et.color type_color, p.name project_name
                 FROM entries e
                 LEFT JOIN entry_types et ON et.id=e.entry_type_id
                 LEFT JOIN projects p ON p.id=e.project_id
                 WHERE (e.title LIKE ? OR e.description LIKE ? OR e.mower_serial LIKE ?) AND $entrySql
                 ORDER BY e.entry_date DESC LIMIT 20",
                array_merge([$like, $like, $like], $entryParams)
            );

            [$projSql, $projParams] = Auth::projectAccessClause('p');
            $results['projects'] = Database::fetchAll(
                "SELECT p.id, p.name, p.status FROM projects p WHERE ($projSql) AND (p.name LIKE ? OR p.project_number LIKE ?) LIMIT 10",
                array_merge($projParams, [$like, $like])
            );

            $invIds = Auth::groupProjectIds();
            if ($invIds === null) {
                $invSql = '1=1'; $invParams = [$like, $like];
            } elseif (empty($invIds)) {
                $invSql = 'project_id IS NULL'; $invParams = [$like, $like];
            } else {
                $ph = implode(',', array_fill(0, count($invIds), '?'));
                $invSql = "(project_id IS NULL OR project_id IN ($ph))";
                $invParams = array_merge($invIds, [$like, $like]);
            }
            $results['inventory'] = Database::fetchAll(
                "SELECT id, name, serial_number FROM inventory_items WHERE $invSql AND (name LIKE ? OR serial_number LIKE ?) LIMIT 10",
                $invParams
            );
        }

        View::render('search/index', ['q' => $q, 'results' => $results, 'title' => 'Suche']);
    }
}
