<?php
declare(strict_types=1);

class EpicController {

    public static function index(): void {
        Auth::requireView('entries');
        [$accessSql, $accessParams] = self::projectClause('ep');
        $epics = Database::fetchAll(
            "SELECT ep.*, p.name project_name, p.color project_color,
                    COUNT(e.id) entry_count
             FROM epics ep
             LEFT JOIN projects p ON p.id = ep.project_id
             LEFT JOIN entries e ON e.epic_id = ep.id
             WHERE $accessSql
             GROUP BY ep.id
             ORDER BY ep.project_id, ep.sort_order, ep.title",
            $accessParams
        );
        [$pSql, $pParams] = Auth::projectAccessClause();
        $projects = Database::fetchAll("SELECT id, name, color FROM projects WHERE $pSql ORDER BY name", $pParams);
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
        self::checkAccess(['project_id' => $projectId]);
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
        self::checkAccess($epic);
        [$pSql, $pParams] = Auth::projectAccessClause();
        $projects = Database::fetchAll("SELECT id, name, color FROM projects WHERE $pSql ORDER BY name", $pParams);
        View::render('epics/edit', compact('epic', 'projects') + ['title' => 'Edit Epic']);
    }

    public static function update(string $id): void {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $epic = Database::fetchOne('SELECT id, project_id FROM epics WHERE id=?', [(int)$id]);
        if (!$epic) abort(404);
        self::checkAccess($epic);
        $title     = trim($_POST['title'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 0) ?: null;
        $color     = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#8b5cf6';
        $desc      = trim($_POST['description'] ?? '');
        $jiraKey   = trim($_POST['jira_epic_key'] ?? '') ?: null;
        if (!$title) { redirect('/epics/'.$id.'/edit'); return; }
        self::checkAccess(['project_id' => $projectId]);
        Database::execute(
            'UPDATE epics SET project_id=?, title=?, description=?, color=?, jira_epic_key=? WHERE id=?',
            [$projectId, $title, $desc ?: null, $color, $jiraKey, (int)$id]
        );
        redirect('/epics');
    }

    public static function destroy(string $id): void {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $epic = Database::fetchOne('SELECT id, project_id FROM epics WHERE id=?', [(int)$id]);
        if (!$epic) abort(404);
        self::checkAccess($epic);
        // Detach entries before deleting epic
        Database::execute('UPDATE entries SET epic_id=NULL WHERE epic_id=?', [(int)$id]);
        Database::execute('DELETE FROM epics WHERE id=?', [(int)$id]);
        redirect('/epics');
    }

    private static function checkAccess(array $row): void {
        if (Auth::isAdmin()) return;
        $pid = $row['project_id'] ?? null;
        if (!$pid) return;
        $ids = Auth::groupProjectIds();
        if ($ids !== null && !in_array((int)$pid, $ids, true)) abort(403, 'No access to this project');
    }

    private static function projectClause(string $alias): array {
        $ids = Auth::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ["$alias.project_id IS NULL", []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.project_id IS NULL OR $alias.project_id IN ($ph))", $ids];
    }
}
