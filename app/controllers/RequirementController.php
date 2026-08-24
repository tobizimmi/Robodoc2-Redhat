<?php
declare(strict_types=1);

class RequirementController {
    public static function index(): void {
        Auth::requireView('requirements');
        $projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        [$accessSql, $accessParams] = self::projectClause('r');
        $where = [$accessSql]; $params = $accessParams;
        if ($projectId) { $where[] = 'r.project_id=?'; $params[] = $projectId; }

        $reqs = Database::fetchAll(
            "SELECT r.*, p.name project_name FROM requirements r LEFT JOIN projects p ON p.id=r.project_id WHERE " . implode(' AND ', $where) . " ORDER BY r.priority DESC, r.name",
            $params
        );
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE $pSql ORDER BY name", $pParams);
        View::render('requirements/index', compact('reqs', 'projects', 'projectId') + ['title' => 'Anforderungen']);
    }

    public static function create(): void {
        Auth::requireEdit('requirements');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $id = Database::insert(
                'INSERT INTO requirements (project_id, name, description, status, priority, created_by) VALUES (?,?,?,?,?,?)',
                [(int)$_POST['project_id'] ?: null, trim($_POST['name']), trim($_POST['description'] ?? ''), $_POST['status'] ?? 'planning', $_POST['priority'] ?? 'medium', Auth::id()]
            );
            flash('success', 'Anforderung erstellt.');
            redirect('/requirements');
        }
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE $pSql ORDER BY name", $pParams);
        View::render('requirements/create', ['projects' => $projects, 'data' => [], 'title' => 'Neue Anforderung']);
    }

    public static function edit(string $id): void {
        Auth::requireEdit('requirements');
        $req = Database::fetchOne('SELECT * FROM requirements WHERE id=?', [(int)$id]);
        if (!$req) abort(404);
        self::checkAccess($req);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute(
                'UPDATE requirements SET project_id=?, name=?, description=?, status=?, priority=? WHERE id=?',
                [(int)$_POST['project_id'] ?: null, trim($_POST['name']), trim($_POST['description'] ?? ''), $_POST['status'] ?? 'planning', $_POST['priority'] ?? 'medium', (int)$id]
            );
            flash('success', 'Anforderung aktualisiert.');
            redirect('/requirements');
        }
        [$pSql, $pParams] = Auth::projectAccessClause('p');
        $projects = Database::fetchAll("SELECT id, name FROM projects p WHERE $pSql ORDER BY name", $pParams);
        $data = $req;
        View::render('requirements/edit', compact('req', 'data', 'projects') + ['title' => 'Anforderung bearbeiten']);
    }

    public static function delete(string $id): void {
        Auth::requireEdit('requirements');
        Auth::verifyCsrf();
        $req = Database::fetchOne('SELECT * FROM requirements WHERE id=?', [(int)$id]);
        if ($req) self::checkAccess($req);
        Database::execute('DELETE FROM requirements WHERE id=?', [(int)$id]);
        flash('success', 'Requirement deleted.');
        redirect('/requirements');
    }

    private static function checkAccess(array $req): void {
        if (Auth::isAdmin()) return;
        $pid = $req['project_id'] ?? null;
        if (!$pid) return;
        $ids = Auth::groupProjectIds();
        if ($ids !== null && !in_array((int)$pid, $ids, true)) abort(403, 'Kein Zugriff auf dieses Projekt');
    }

    private static function projectClause(string $alias): array {
        $ids = Auth::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ["$alias.project_id IS NULL", []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.project_id IS NULL OR $alias.project_id IN ($ph))", $ids];
    }
}
