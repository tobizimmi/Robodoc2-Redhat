<?php
declare(strict_types=1);

class DashboardController {
    public static function index(): void {
        Auth::require();

        $today   = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $monthAgo = date('Y-m-d', strtotime('-30 days'));

        $userId  = Auth::id();

        [$entrySql, $entryParams] = Auth::entryAccessClause('e');
        [$projSql, $projParams]   = Auth::projectAccessClause('p');

        $stats = [
            'today'  => (int)Database::fetchOne("SELECT COUNT(*) c FROM entries e WHERE e.entry_date = ? AND $entrySql", array_merge([$today], $entryParams))['c'],
            'week'   => (int)Database::fetchOne("SELECT COUNT(*) c FROM entries e WHERE e.entry_date >= ? AND $entrySql", array_merge([$weekAgo], $entryParams))['c'],
            'month'  => (int)Database::fetchOne("SELECT COUNT(*) c FROM entries e WHERE e.entry_date >= ? AND $entrySql", array_merge([$monthAgo], $entryParams))['c'],
            'total'  => (int)Database::fetchOne("SELECT COUNT(*) c FROM entries e WHERE $entrySql", $entryParams)['c'],
            'projects' => (int)Database::fetchOne("SELECT COUNT(*) c FROM projects p WHERE p.status='active' AND $projSql", $projParams)['c'],
            'open_todos' => (int)Database::fetchOne("SELECT COUNT(*) c FROM entry_todos WHERE user_id = ?", [$userId])['c'] +
                           (int)Database::fetchOne("SELECT COUNT(*) c FROM standalone_todos WHERE user_id = ? AND done = 0", [$userId])['c'],
        ];

        // Entries by type (all time)
        $byType = Database::fetchAll(
            "SELECT et.name, et.color, COUNT(e.id) cnt
             FROM entry_types et
             LEFT JOIN entries e ON e.entry_type_id = et.id AND $entrySql
             GROUP BY et.id ORDER BY cnt DESC",
            $entryParams
        );

        // Entries by category (all time)
        $byCategory = Database::fetchAll(
            "SELECT ec.name, ec.color, COUNT(e.id) cnt
             FROM error_categories ec
             LEFT JOIN entries e ON e.error_category_id = ec.id AND $entrySql
             GROUP BY ec.id ORDER BY cnt DESC",
            $entryParams
        );

        // Recent entries
        $recentEntries = Database::fetchAll(
            "SELECT e.id, e.title, e.description, e.entry_date, e.entry_time,
                    et.name type_name, et.color type_color,
                    ec.name cat_name, ec.color cat_color,
                    p.name project_name, p.color project_color,
                    u.name creator
             FROM entries e
             LEFT JOIN entry_types et ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             LEFT JOIN projects p ON p.id = e.project_id
             LEFT JOIN users u ON u.id = e.created_by
             WHERE $entrySql
             ORDER BY e.created_at DESC LIMIT 10",
            $entryParams
        );

        // Active projects (restricted to accessible ones)
        $projects = Database::fetchAll(
            "SELECT p.*, COUNT(e.id) entry_count
             FROM projects p
             LEFT JOIN entries e ON e.project_id = p.id
             WHERE p.status = 'active' AND $projSql
             GROUP BY p.id ORDER BY p.name LIMIT 8",
            $projParams
        );

        // Entries trend (last 6 months by month)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $cnt   = (int)Database::fetchOne(
                "SELECT COUNT(*) c FROM entries e WHERE DATE_FORMAT(e.entry_date,'%Y-%m') = ? AND $entrySql",
                array_merge([$month], $entryParams)
            )['c'];
            $trend[] = ['date' => $month, 'label' => date('M y', strtotime($month . '-01')), 'count' => $cnt];
        }

        View::render('dashboard/index', compact('stats', 'byType', 'byCategory', 'recentEntries', 'projects', 'trend') + ['title' => 'Dashboard']);
    }

    public static function manifest(): void {
        $base = rtrim(BASE_URL, '/');
        $manifest = [
            'name'             => appSetting('app_name', 'RoboDoc'),
            'short_name'       => 'RoboDoc',
            'description'      => 'Field testing documentation tool',
            'start_url'        => $base . '/',
            'scope'            => $base . '/',
            'display'          => 'standalone',
            'background_color' => '#0f172a',
            'theme_color'      => '#1e293b',
            'orientation'      => 'any',
            'icons'            => [
                ['src' => $base . '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => $base . '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'categories' => ['productivity', 'utilities'],
        ];
        header('Content-Type: application/manifest+json');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
