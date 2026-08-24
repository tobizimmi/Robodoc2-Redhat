<?php
declare(strict_types=1);

class TagController
{
    // GET /tags — list all tags (JSON for autocomplete)
    public static function index(): void
    {
        Auth::require();
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        $where = $q ? 'WHERE name LIKE ?' : '';
        $params = $q ? ['%'.$q.'%'] : [];
        $tags = Database::fetchAll("SELECT id, name, color FROM tags $where ORDER BY name", $params);
        echo json_encode($tags);
        exit;
    }

    // GET /tags/manage — manage tags page
    public static function manage(): void
    {
        Auth::requireAdmin();
        $tags = Database::fetchAll(
            'SELECT t.*, COUNT(et.entry_id) entry_count FROM tags t
             LEFT JOIN entry_tags et ON et.tag_id=t.id
             GROUP BY t.id ORDER BY t.name'
        );
        View::render('tags/manage', ['tags' => $tags, 'title' => 'Tags verwalten']);
    }

    // POST /tags — create tag
    public static function create(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');
        if (!$name) { http_response_code(422); echo json_encode(['error' => 'Name erforderlich']); exit; }
        if (Database::fetchOne('SELECT id FROM tags WHERE name=?', [$name])) {
            http_response_code(409); echo json_encode(['error' => 'Tag existiert bereits']); exit;
        }
        $id = Database::insert('INSERT INTO tags (name, color, created_by) VALUES (?,?,?)', [$name, $color, Auth::id()]);
        echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'color' => $color]);
        exit;
    }

    // POST /tags/{id}/update
    public static function update(string $id): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');
        if (!$name) { http_response_code(422); echo json_encode(['error' => 'Name erforderlich']); exit; }
        Database::execute('UPDATE tags SET name=?, color=? WHERE id=?', [$name, $color, (int)$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // POST /tags/{id}/delete
    public static function delete(string $id): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM tags WHERE id=?', [(int)$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // POST /entries/{id}/tags — set tags on an entry
    public static function setEntryTags(string $entryId): void
    {
        Auth::require();
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        try {
        $tagIds = array_filter(array_map('intval', (array)($_POST['tag_ids'] ?? [])));
        $eid = (int)$entryId;
        Database::execute('DELETE FROM entry_tags WHERE entry_id=?', [$eid]);
        foreach ($tagIds as $tid) {
            Database::execute('INSERT IGNORE INTO entry_tags (entry_id, tag_id) VALUES (?,?)', [$eid, $tid]);
        }
        $tags = Database::fetchAll(
            'SELECT t.id, t.name, t.color FROM tags t JOIN entry_tags et ON et.tag_id=t.id WHERE et.entry_id=? ORDER BY t.name',
            [$eid]
        );
        echo json_encode(['success' => true, 'tags' => $tags]);
        } catch (\Throwable $ex) {
            http_response_code(500);
            echo json_encode(['error' => $ex->getMessage()]);
        }
        exit;
    }

    // GET /entries/{id}/tags — get tags for an entry
    public static function getEntryTags(string $entryId): void
    {
        Auth::require();
        header('Content-Type: application/json');
        $tags = Database::fetchAll(
            'SELECT t.id, t.name, t.color FROM tags t JOIN entry_tags et ON et.tag_id=t.id WHERE et.entry_id=? ORDER BY t.name',
            [(int)$entryId]
        );
        echo json_encode($tags);
        exit;
    }
}
