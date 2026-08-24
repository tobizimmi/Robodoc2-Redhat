<?php
declare(strict_types=1);

class TagKanbanController
{
    public static function index(): void
    {
        Auth::requireView('kanban');
        $userId = Auth::id();

        // Load user's personal buckets with their tags
        $buckets = Database::fetchAll(
            'SELECT b.*, GROUP_CONCAT(t.id) tag_ids, GROUP_CONCAT(t.name) tag_names, GROUP_CONCAT(t.color) tag_colors
             FROM tag_kanban_buckets b
             LEFT JOIN tag_kanban_bucket_tags bt ON bt.bucket_id=b.id
             LEFT JOIN tags t ON t.id=bt.tag_id
             WHERE b.user_id=?
             GROUP BY b.id ORDER BY b.sort_order, b.name',
            [$userId]
        );

        // Parse tag info per bucket
        foreach ($buckets as &$b) {
            $ids    = $b['tag_ids']    ? explode(',', $b['tag_ids'])    : [];
            $names  = $b['tag_names']  ? explode(',', $b['tag_names'])  : [];
            $colors = $b['tag_colors'] ? explode(',', $b['tag_colors']) : [];
            $b['tags'] = array_map(fn($i) => [
                'id'    => $ids[$i],
                'name'  => $names[$i],
                'color' => $colors[$i],
            ], array_keys($ids));
            unset($b['tag_ids'], $b['tag_names'], $b['tag_colors']);
        }
        unset($b);

        // Get all tag IDs used by this user
        $allTagIds = array_unique(array_merge(
            ...array_map(fn($b) => array_column($b['tags'], 'id'), $buckets)
        ));

        // Load entries that have any of those tags
        $entriesByTag = [];
        if ($allTagIds) {
            $ph = implode(',', array_fill(0, count($allTagIds), '?'));
            $entries = Database::fetchAll(
                "SELECT e.id, e.title, e.status, e.priority, e.jira_issue_key, e.jira_issue_url,
                        e.zentao_bug_id, e.zentao_bug_url, e.jira_has_changes, e.zentao_has_changes,
                        e.entry_date, e.parent_id, e.epic_id,
                        et.tag_id, p.name project_name, p.color project_color,
                        et2.name type_name, et2.color type_color, ec.name cat_name
                 FROM entries e
                 JOIN entry_tags et ON et.entry_id=e.id
                 JOIN projects p ON p.id=e.project_id
                 LEFT JOIN entry_types et2 ON et2.id=e.entry_type_id
                 LEFT JOIN error_categories ec ON ec.id=e.error_category_id
                 WHERE et.tag_id IN ($ph)
                 ORDER BY e.created_at DESC",
                $allTagIds
            );
            // Build childrenMap: parent_id -> [child entries]
            $allEntries = $entries;
            $childrenMap = [];
            $parentIdsNeeded = [];
            foreach ($entries as $entry) {
                if (!empty($entry['parent_id'])) {
                    $childrenMap[$entry['parent_id']][] = $entry;
                    $parentIdsNeeded[] = (int)$entry['parent_id'];
                }
            }
            // Load parent entries for sub-tickets that have the tag
            if ($parentIdsNeeded) {
                $phP = implode(',', array_fill(0, count($parentIdsNeeded), '?'));
                $parentEntries = Database::fetchAll(
                    "SELECT e.id, e.title, e.status, e.priority, e.parent_id, e.entry_date,
                            et.name type_name, et.color type_color, ec.name cat_name,
                            p.name project_name
                     FROM entries e
                     LEFT JOIN entry_types et ON et.id=e.entry_type_id
                     LEFT JOIN error_categories ec ON ec.id=e.error_category_id
                     LEFT JOIN projects p ON p.id=e.project_id
                     WHERE e.id IN ($phP)", $parentIdsNeeded
                );
                foreach ($parentEntries as $pe) {
                    $pe['_is_parent_of_tagged_sub'] = true;
                    // Add to each tag bucket that has a sub-ticket of this parent
                    foreach ($entries as $entry) {
                        if ($entry['parent_id'] == $pe['id']) {
                            // Check if parent not already in this tag bucket
                            $already = array_filter($entriesByTag[$entry['tag_id']] ?? [],
                                fn($x) => $x['id'] == $pe['id']);
                            if (empty($already)) {
                                $entriesByTag[$entry['tag_id']][] = $pe;
                            }
                        }
                    }
                }
            }
            foreach ($entries as $entry) {
                if (empty($entry['parent_id'])) { // only add parent entries directly
                    $entriesByTag[$entry['tag_id']][] = $entry;
                }
            }
        }

        // All tags for bucket editor
        $allTags = Database::fetchAll('SELECT id, name, color FROM tags ORDER BY name');

                // Build epicGroups for tag view
        $tagEpicIds = [];
        foreach ($entriesByTag as $tagEntries) {
            foreach ($tagEntries as $te) {
                if (!empty($te['epic_id'])) $tagEpicIds[] = $te['epic_id'];
            }
        }
        $epicGroups = [];
        $tagEpicIds = array_unique($tagEpicIds);
        if ($tagEpicIds) {
            $phE = implode(',', array_fill(0, count($tagEpicIds), '?'));
            foreach (Database::fetchAll("SELECT * FROM epics WHERE id IN ($phE)", array_values($tagEpicIds)) as $ep) {
                $epicGroups[$ep['id']] = $ep;
            }
        }

        View::render('kanban/tag_view', [
            'buckets'      => $buckets,
            'entriesByTag' => $entriesByTag,
            'childrenMap'  => $childrenMap,
            'epicGroups'   => $epicGroups,
            'allTags'      => $allTags,
            'title'        => 'Tag View',
        ]);
    }

    // POST /kanban/tag-buckets ? create bucket
    public static function createBucket(): void
    {
        Auth::requireView('kanban');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');
        if (!$name) { http_response_code(422); echo json_encode(['error' => 'Name erforderlich']); exit; }
        $maxOrder = (int)(Database::fetchOne('SELECT MAX(sort_order) m FROM tag_kanban_buckets WHERE user_id=?', [Auth::id()])['m'] ?? 0);
        $id = Database::insert('INSERT INTO tag_kanban_buckets (user_id, name, color, sort_order) VALUES (?,?,?,?)',
            [Auth::id(), $name, $color, $maxOrder + 1]);
        echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'color' => $color]);
        exit;
    }

    // POST /kanban/tag-buckets/{id}/update
    public static function updateBucket(string $id): void
    {
        Auth::requireView('kanban');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $name   = trim($_POST['name'] ?? '');
        $color  = trim($_POST['color'] ?? '#6c757d');
        $tagIds = array_filter(array_map('intval', (array)($_POST['tag_ids'] ?? [])));
        Database::execute('UPDATE tag_kanban_buckets SET name=?, color=? WHERE id=? AND user_id=?',
            [$name, $color, (int)$id, Auth::id()]);
        Database::execute('DELETE FROM tag_kanban_bucket_tags WHERE bucket_id=?', [(int)$id]);
        foreach ($tagIds as $tid) {
            Database::execute('INSERT IGNORE INTO tag_kanban_bucket_tags (bucket_id, tag_id) VALUES (?,?)', [(int)$id, $tid]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // POST /kanban/tag-buckets/{id}/delete
    public static function deleteBucket(string $id): void
    {
        Auth::requireView('kanban');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM tag_kanban_buckets WHERE id=? AND user_id=?', [(int)$id, Auth::id()]);
        echo json_encode(['success' => true]);
        exit;
    }
}
