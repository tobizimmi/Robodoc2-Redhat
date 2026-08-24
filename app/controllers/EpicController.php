<?php
declare(strict_types=1);

class EpicController {

    public static function index(): void {
        Auth::requireView('entries');
        $epics = Database::fetchAll(
            "SELECT ep.*, p.name project_name, p.color project_color,
                    COUNT(e.id) entry_count
             FROM epics ep
             LEFT JOIN projects p ON p.id = ep.project_id
             LEFT JOIN entries e ON e.epic_id = ep.id
             GROUP BY ep.id
             ORDER BY ep.project_id, ep.sort_order, ep.title"
        );
        $projects = Database::fetchAll('SELECT id, name, color FROM projects ORDER BY name');
        View::render('epics/index', compact('epics', 'projects') + ['title' => 'Epics']);
    }

    public static function store(): void {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $title     = trim($_POST['title'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 0) ?: null;
        $color     = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#8b5cf6';
        $desc      = trim($_POST['description'] ?? '');
        $jiraKey   = trim($_POST['jira_epic_key'] ?? '') ?: null;
        if (!$title) { redirect('/epics'); return; }
        Database::insert(
            'INSERT INTO epics (project_id, title, description, color, jira_epic_key, created_by, created_at) VALUES (?,?,?,?,?,?,?)',
            [$projectId, $title, $desc ?: null, $color, $jiraKey, Auth::id(), date('Y-m-d H:i:s')]
        );
        redirect('/epics');
    }

    public static function edit(string $id): void {
        Auth::requireEdit('entries');
        $epic = Database::fetchOne('SELECT * FROM epics WHERE id=?', [(int)$id]);
        if (!$epic) abort(404);
        $projects = Database::fetchAll('SELECT id, name, color FROM projects ORDER BY name');
        View::render('epics/edit', compact('epic', 'projects') + ['title' => 'Edit Epic']);
    }

    public static function update(string $id): void {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $epic = Database::fetchOne('SELECT id FROM epics WHERE id=?', [(int)$id]);
        if (!$epic) abort(404);
        $title     = trim($_POST['title'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 0) ?: null;
        $color     = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#8b5cf6';
        $desc      = trim($_POST['description'] ?? '');
        $jiraKey   = trim($_POST['jira_epic_key'] ?? '') ?: null;
        if (!$title) { redirect('/epics/'.$id.'/edit'); return; }
        Database::execute(
            'UPDATE epics SET project_id=?, title=?, description=?, color=?, jira_epic_key=? WHERE id=?',
            [$projectId, $title, $desc ?: null, $color, $jiraKey, (int)$id]
        );
        redirect('/epics');
    }

    public static function destroy(string $id): void {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        // Detach entries before deleting epic
        Database::execute('UPDATE entries SET epic_id=NULL WHERE epic_id=?', [(int)$id]);
        Database::execute('DELETE FROM epics WHERE id=?', [(int)$id]);
        redirect('/epics');
    }
}
