<?php
declare(strict_types=1);

class ProjectController {
    public static function index(): void {
        Auth::requireView('projects');
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? '';
        $where  = ['1=1'];
        $params = [];
        if ($search) { $where[] = '(p.name LIKE ? OR p.project_number LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
        [$accessSql, $accessParams] = Auth::projectAccessClause('p');
        $where[]  = $accessSql;
        $params   = array_merge($params, $accessParams);
        $wStr = implode(' AND ', $where);

        $projects = Database::fetchAll(
            "SELECT p.*, u.name creator, COUNT(e.id) entry_count,
                    MAX(e.entry_date) last_entry
             FROM projects p
             LEFT JOIN users u ON u.id = p.created_by
             LEFT JOIN entries e ON e.project_id = p.id
             WHERE $wStr
             GROUP BY p.id ORDER BY p.status, p.name",
            $params
        );
        View::render('projects/index', compact('projects', 'search', 'status') + ['title' => 'Projekte']);
    }

    public static function show(string $id): void {
        Auth::requireView('projects');
        $project = self::findOr404((int)$id);
        $allowedIds = Auth::groupProjectIds();
        if ($allowedIds !== null && !in_array((int)$id, $allowedIds, true)) {
            abort(403, 'Kein Zugriff auf dieses Projekt');
        }
        $entries = Database::fetchAll(
            "SELECT e.id, e.title, e.description, e.entry_date, et.name type_name, et.color type_color, ec.name cat_name, ec.color cat_color
             FROM entries e
             LEFT JOIN entry_types et ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             WHERE e.project_id = ? ORDER BY e.entry_date DESC LIMIT 20",
            [(int)$id]
        );
        $testPlans = Database::fetchAll(
            'SELECT tp.*, COUNT(tpi.id) item_count FROM test_plans tp LEFT JOIN test_plan_items tpi ON tpi.test_plan_id = tp.id WHERE tp.project_id = ? GROUP BY tp.id',
            [(int)$id]
        );
        $statuses = json_decode(appSetting('project_statuses', '[]'), true) ?: [];
        View::render('projects/show', compact('project', 'entries', 'testPlans', 'statuses') + ['title' => $project['name']]);
    }

    public static function create(): void {
        Auth::requireEdit('projects');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $data = self::extractFields();
            $errors = self::validate($data);
            if ($errors) {
                View::render('projects/create', compact('data', 'errors') + ['title' => 'Neues Projekt']);
                return;
            }
            $id = Database::insert(
                'INSERT INTO projects (name, project_number, description, status, color, prototype_date, ep0_date, ep1_date, ep3_date, sharepoint_folder, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$data['name'], $data['project_number'], $data['description'], $data['status'], $data['color'],
                 $data['prototype_date'] ?: null, $data['ep0_date'] ?: null, $data['ep1_date'] ?: null, $data['ep3_date'] ?: null,
                 $data['sharepoint_folder'] ?: null, Auth::id()]
            );
            flash('success', 'Project created.');
            redirect('/projects/' . $id);
        }
        View::render('projects/create', ['data' => [], 'errors' => [], 'title' => 'Neues Projekt']);
    }

    public static function edit(string $id): void {
        Auth::requireEdit('projects');
        $project = self::findOr404((int)$id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $data   = self::extractFields();
            $errors = self::validate($data);
            if ($errors) {
                View::render('projects/edit', compact('project', 'data', 'errors') + ['title' => 'Projekt bearbeiten']);
                return;
            }
            Database::execute(
                'UPDATE projects SET name=?,project_number=?,description=?,status=?,color=?,prototype_date=?,ep0_date=?,ep1_date=?,ep3_date=?,sharepoint_folder=? WHERE id=?',
                [$data['name'], $data['project_number'], $data['description'], $data['status'], $data['color'],
                 $data['prototype_date'] ?: null, $data['ep0_date'] ?: null, $data['ep1_date'] ?: null, $data['ep3_date'] ?: null,
                 $data['sharepoint_folder'] ?: null, (int)$id]
            );
            flash('success', 'Project updated.');
            redirect('/projects/' . $id);
        }
        $data = $project;
        View::render('projects/edit', compact('project', 'data', 'errors') + ['errors' => [], 'title' => 'Projekt bearbeiten']);
    }

    public static function delete(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $pid = (int)$id;
        $project = Database::fetchOne('SELECT name FROM projects WHERE id=?', [$pid]);

        // Nullify project references so related data is not lost
        Database::execute('UPDATE entries           SET project_id = NULL WHERE project_id = ?', [$pid]);
        Database::execute('UPDATE inventory_items   SET project_id = NULL WHERE project_id = ?', [$pid]);
        Database::execute('UPDATE requirements      SET project_id = NULL WHERE project_id = ?', [$pid]);
        Database::execute('UPDATE test_plans        SET project_id = NULL WHERE project_id = ?', [$pid]);
        Database::execute('UPDATE test_sessions     SET project_id = NULL WHERE project_id = ?', [$pid]);
        Database::execute('UPDATE entry_templates   SET project_id = NULL WHERE project_id = ?', [$pid]);
        Database::execute('DELETE FROM user_group_projects WHERE project_id = ?', [$pid]);

        Database::execute('DELETE FROM projects WHERE id = ?', [$pid]);
        Audit::log('project_deleted', 'project', $pid, $project['name'] ?? '');
        flash('success', 'Project deleted. Related entries/items have been unlinked (not deleted).');
        redirect('/projects');
    }

    private static function findOr404(int $id): array {
        $p = Database::fetchOne('SELECT * FROM projects WHERE id = ?', [$id]);
        if (!$p) abort(404, 'Projekt nicht gefunden');
        return $p;
    }

    private static function extractFields(): array {
        return [
            'name'              => trim($_POST['name'] ?? ''),
            'project_number'    => trim($_POST['project_number'] ?? ''),
            'description'       => trim($_POST['description'] ?? ''),
            'status'            => $_POST['status'] ?? 'active',
            'color'             => $_POST['color'] ?? '#4f46e5',
            'prototype_date'    => $_POST['prototype_date'] ?? '',
            'ep0_date'          => $_POST['ep0_date'] ?? '',
            'ep1_date'          => $_POST['ep1_date'] ?? '',
            'ep3_date'          => $_POST['ep3_date'] ?? '',
            'sharepoint_folder' => trim($_POST['sharepoint_folder'] ?? ''),
        ];
    }

    // GET /projects/{id}/jira-configs
    public static function jiraConfigs(string $id): void {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        $configs = Database::fetchAll(
            'SELECT id, jira_project_key, label, issue_type, sort_order FROM project_jira_configs WHERE project_id=? ORDER BY sort_order, id',
            [(int)$id]
        );
        echo json_encode($configs);
        exit;
    }

    // POST /projects/{id}/jira-configs
    public static function saveJiraConfigs(string $id): void {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $pid     = (int)$id;
        $configs = json_decode($_POST['configs'] ?? '[]', true);
        if (!is_array($configs)) { http_response_code(422); echo json_encode(['error'=>'Invalid']); exit; }
        Database::execute('DELETE FROM project_jira_configs WHERE project_id=?', [$pid]);
        foreach ($configs as $i => $cfg) {
            $key   = strtoupper(trim($cfg['jira_project_key'] ?? ''));
            $label = trim($cfg['label'] ?? $key);
            $type  = trim($cfg['issue_type'] ?? 'Bug') ?: 'Bug';
            if (!$key) continue;
            Database::execute(
                'INSERT INTO project_jira_configs (project_id, jira_project_key, label, issue_type, sort_order) VALUES (?,?,?,?,?)',
                [$pid, $key, $label ?: $key, $type, $i]
            );
        }
        echo json_encode(['success' => true]);
        exit;
    }

    private static function validate(array $data): array {
        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Name is required.';
        if (!in_array($data['status'], ['active', 'archived', 'completed'])) $errors['status'] = 'Invalid status.';
        return $errors;
    }
}
