<?php
declare(strict_types=1);

// Fixed Kanban lane slugs ? used in both controller and view
function kanbanLanes(): array {
    return [
        'new'              => ['label' => 'New',                         'color' => 'secondary'],
        'prioritized'      => ['label' => 'Prioritized - Info Required', 'color' => 'info'],
        'reviewed'         => ['label' => 'Review Done - At Supplier',   'color' => 'primary'],
        'at_supplier'      => ['label' => 'At Supplier',                 'color' => 'warning'],
        'supplier_tickets' => ['label' => 'Supplier Tickets',            'color' => 'teal', 'hideable' => true],
        'planned_retest'   => ['label' => 'Planned for Retesting',       'color' => 'purple'],
        'archive'          => ['label' => 'Archive',                     'color' => 'dark'],
    ];
}

class KanbanController
{
    public static function index(): void
    {
        Auth::requireView('kanban');

        $projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        $typeIds   = array_filter(array_map('intval', (array)($_GET['type_ids'] ?? [])));
        $priority  = $_GET['priority'] ?? '';
        $catId     = isset($_GET['cat_id']) && is_numeric($_GET['cat_id']) ? (int)$_GET['cat_id'] : null;
        $search    = trim($_GET['search'] ?? '');
        $viewMode  = in_array($_GET['view'] ?? '', ['status', 'lane'], true) ? $_GET['view'] : 'status';

        // Which status columns to show (defaults to all)
        $allSlugs    = array_keys(entryStatuses());
        $visStatuses = $_GET['vis_status'] ?? $allSlugs;
        if (!is_array($visStatuses)) $visStatuses = $allSlugs;
        $visStatuses = array_intersect($visStatuses, $allSlugs); // only valid slugs

        $where  = ['1=1', '(e.is_test_entry=0 OR e.is_test_entry IS NULL)'];
        $params = [];

        if (!Auth::isAdmin()) {
            [$accessClause, $accessParams] = Auth::entryAccessClause();
            $where[]  = $accessClause;
            $params   = array_merge($params, $accessParams);
        }
        // Apply global project filter
        [$gfClause, $gfParams] = Auth::globalFilterClause('e');
        if ($gfClause !== '1=1') {
            $where[]  = $gfClause;
            $params   = array_merge($params, $gfParams);
        }

        if ($projectId)   { $where[] = 'e.project_id=?';        $params[] = $projectId; }
        if ($typeIds)     {
            $ph = implode(',', array_fill(0, count($typeIds), '?'));
            $where[] = "e.entry_type_id IN ($ph)";
            $params  = array_merge($params, $typeIds);
        }
        if ($priority)    { $where[] = 'e.priority=?';           $params[] = $priority; }
        if ($catId)       { $where[] = 'e.error_category_id=?';  $params[] = $catId; }
        if ($search)      {
            $where[] = '(e.title LIKE ? OR e.mower_serial LIKE ?)';
            $params[] = "%$search%"; $params[] = "%$search%";
        }

        // For status view: also filter by visible statuses
        $statusWhere  = $where;
        $statusParams = $params;
        if ($visStatuses) {
            $ph = implode(',', array_fill(0, count($visStatuses), '?'));
            $statusWhere[] = "e.status IN ($ph)";
            $statusParams  = array_merge($statusParams, array_values($visStatuses));
        }

        $selectBase = "SELECT e.id, e.title, e.status, e.priority, e.entry_date,
                    e.kanban_lane, e.created_by, e.assigned_to,
                    e.parent_id, e.epic_id,
                    e.jira_issue_key, e.jira_has_changes, e.jira_issue_url,
                    e.zentao_bug_id, e.zentao_has_changes, e.zentao_bug_url,
                    et.name type_name, et.color type_color,
                    ec.name cat_name,
                    p.name project_name, p.color project_color,
                    u.name creator
             FROM entries e
             LEFT JOIN entry_types et      ON et.id  = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id  = e.error_category_id
             LEFT JOIN projects p          ON p.id   = e.project_id
             LEFT JOIN users u             ON u.id   = e.created_by";

        $orderBy = "ORDER BY FIELD(e.priority,'Blocker','Highest','High','Medium','Low'), e.entry_date DESC LIMIT 500";

        // ?? Status view entries ??????????????????????????????????
        $wStr    = implode(' AND ', $statusWhere);
        $entries = Database::fetchAll("$selectBase WHERE $wStr $orderBy", $statusParams);

        // Build childrenMap: parent_id -> [child entries]
        $childrenMap = [];
        foreach ($entries as $e) {
            if (!empty($e['parent_id'])) $childrenMap[$e['parent_id']][] = $e;
        }

        // Build epicGroups map for kanban
        $epicGroups = [];
        $epicIds = array_values(array_unique(array_filter(array_column($entries, 'epic_id'))));
        if ($epicIds) {
            $phE = implode(',', array_fill(0, count($epicIds), '?'));
            $epicsData = Database::fetchAll("SELECT * FROM epics WHERE id IN ($phE) ORDER BY sort_order, title", $epicIds);
            foreach ($epicsData as $epic) {
                $epicGroups[$epic['id']] = $epic;
            }
        }

        // Build status columns ? only visible statuses, in canonical order
        $cols = [];
        foreach (entryStatuses() as $slug => $label) {
            if (!in_array($slug, $visStatuses, true)) continue;
            $cols[$slug] = ['label' => $label, 'color' => entryStatusColor($slug), 'entries' => []];
        }
        foreach ($entries as $e) {
            if (!empty($e['parent_id'])) continue; // skip sub-tickets ? shown inside parent card
            $s = $e['status'] ?? 'new';
            if (isset($cols[$s])) $cols[$s]['entries'][] = $e;
        }

        // ?? Lane view entries ????????????????????????????????????
        $wStrLane     = implode(' AND ', $where);
        $laneEntries  = Database::fetchAll("$selectBase WHERE $wStrLane $orderBy", $params);

        $lanes = [];
        foreach (kanbanLanes() as $slug => $meta) {
            $lanes[$slug] = array_merge($meta, ['entries' => []]);
        }
        foreach ($laneEntries as $e) {
            if (!empty($e['parent_id'])) continue; // sub-tickets shown inside parent card
            $lane = $e['kanban_lane'] ?: 'new';
            if (!isset($lanes[$lane])) $lane = 'new';
            $lanes[$lane]['entries'][] = $e;
        }
        // Load private notes for current user
        $noteMap = [];
        if ($laneEntries) {
            $eids = array_column($laneEntries, 'id');
            $ph   = implode(',', array_fill(0, count($eids), '?'));
            $noteRows = Database::fetchAll(
                "SELECT entry_id, note FROM kanban_notes WHERE user_id=? AND entry_id IN ($ph)",
                array_merge([Auth::id()], $eids)
            );
            foreach ($noteRows as $nr) $noteMap[$nr['entry_id']] = $nr['note'];
        }
        // Attach note to each lane entry
        foreach ($lanes as $slug => &$laneCol) {
            foreach ($laneCol['entries'] as &$le) {
                $le['kanban_note'] = $noteMap[$le['id']] ?? '';
            }
        }
        unset($laneCol, $le);

        // Archive: only show last 5
        if (count($lanes['archive']['entries']) > 5) {
            $lanes['archive']['entries'] = array_slice($lanes['archive']['entries'], 0, 5);
        }

        [$projSql, $projParams] = Auth::projectAccessClause();
        $projects   = Database::fetchAll("SELECT id, name FROM projects WHERE $projSql ORDER BY name", $projParams);
        $entryTypes = Database::fetchAll('SELECT * FROM entry_types ORDER BY sort_order, name');
        $categories = Database::fetchAll('SELECT * FROM error_categories ORDER BY sort_order, name');

        View::render('kanban/index', compact(
            'cols', 'lanes', 'projects', 'entryTypes', 'categories',
            'projectId', 'typeIds', 'priority', 'catId', 'search', 'visStatuses', 'allSlugs', 'childrenMap', 'epicGroups', 'viewMode'
        ) + ['title' => 'Kanban Board']);
    }

    // POST /kanban/:id/note  ? save (upsert) private note
    public static function saveNote(string $id): void
    {
        Auth::requireView('kanban');
        if (!Auth::canEdit('kanban_notes')) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Keine Berechtigung fuer Kanban Notizen.']);
            exit;
        }
        header('Content-Type: application/json');
        Auth::verifyCsrf();

        $note = trim($_POST['note'] ?? '');
        $eid  = (int)$id;

        if ($note === '') {
            // Empty = delete
            Database::execute('DELETE FROM kanban_notes WHERE entry_id=? AND user_id=?', [$eid, Auth::id()]);
        } else {
            Database::execute(
                'INSERT INTO kanban_notes (entry_id, user_id, note) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE note=VALUES(note), updated_at=NOW()',
                [$eid, Auth::id(), $note]
            );
        }
        echo json_encode(['success' => true, 'note' => $note]);
        exit;
    }

    // POST /kanban/:id/note/promote  ? promote private note to real entry comment
    public static function promoteNote(string $id): void
    {
        Auth::requireView('kanban');
        if (!Auth::canEdit('entry_comments')) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Keine Berechtigung fuer Kommentare.']);
            exit;
        }
        $noteEntry = Database::fetchOne('SELECT id, created_by, assigned_to FROM entries WHERE id=?', [(int)$id]);
        if ($noteEntry) Auth::requireEditEntry($noteEntry);
        header('Content-Type: application/json');
        Auth::verifyCsrf();

        $eid  = (int)$id;
        $row  = Database::fetchOne('SELECT note FROM kanban_notes WHERE entry_id=? AND user_id=?', [$eid, Auth::id()]);
        if (!$row || $row['note'] === '') {
            http_response_code(404);
            echo json_encode(['error' => 'No note found']);
            exit;
        }

        Database::insert(
            'INSERT INTO entry_comments (entry_id, user_id, body) VALUES (?,?,?)',
            [$eid, Auth::id(), $row['note']]
        );
        // Delete the private note after promoting
        Database::execute('DELETE FROM kanban_notes WHERE entry_id=? AND user_id=?', [$eid, Auth::id()]);
        echo json_encode(['success' => true]);
        exit;
    }

    // POST /kanban/:id/lane  ? update kanban_lane for an entry
    public static function updateLane(string $id): void
    {
        Auth::requireView('kanban');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        // Block read-only users unless they own the entry
        $laneEntry = Database::fetchOne('SELECT id, created_by, assigned_to FROM entries WHERE id=?', [(int)$id]);
        if (!Auth::canEdit('kanban') && !($laneEntry && Auth::canEditEntry($laneEntry))) {
            http_response_code(403);
            echo json_encode(['error' => 'Keine Bearbeitungsrechte']);
            exit;
        }

        $lane = $_POST['lane'] ?? '';
        $validLanes = array_keys(kanbanLanes());
        if (!in_array($lane, $validLanes, true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid lane']);
            exit;
        }

        Database::execute('UPDATE entries SET kanban_lane=?, updated_at=NOW() WHERE id=?', [$lane, (int)$id]);
        $label = kanbanLanes()[$lane]['label'];
        echo json_encode(['success' => true, 'lane' => $lane, 'label' => $label]);
        exit;
    }
}
