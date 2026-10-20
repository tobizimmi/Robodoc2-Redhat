<?php
declare(strict_types=1);

// Freely-creatable categories to group test cases (test_plan_items) within a
// project - used by the edit/add forms on a Test Plan and by the per-project
// Test Overview table (collapsible sections).
class TestCategoryController
{
    public static function create(): void
    {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();

        $projectId = (int)($_POST['project_id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        if (!$projectId || $name === '') {
            echo json_encode(['error' => 'Projekt und Name sind erforderlich.']);
            exit;
        }
        if (!Auth::isAdmin()) {
            $ids = Auth::groupProjectIds();
            if ($ids !== null && !in_array($projectId, $ids, true)) {
                http_response_code(403); echo json_encode(['error' => 'No access to this project']); exit;
            }
        }

        $existing = Database::fetchOne(
            'SELECT id, name FROM test_categories WHERE project_id=? AND name=?',
            [$projectId, $name]
        );
        if ($existing) {
            echo json_encode(['success' => true, 'id' => (int)$existing['id'], 'name' => $existing['name']]);
            exit;
        }

        $sortOrder = (int)(Database::fetchOne('SELECT COUNT(*) c FROM test_categories WHERE project_id=?', [$projectId])['c'] ?? 0);
        $id = Database::insert(
            'INSERT INTO test_categories (project_id, name, sort_order) VALUES (?,?,?)',
            [$projectId, $name, $sortOrder]
        );
        echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
        exit;
    }

    public static function delete(string $id): void
    {
        Auth::requireEdit('testing');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $cat = Database::fetchOne('SELECT project_id FROM test_categories WHERE id=?', [(int)$id]);
        if (!$cat) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
        if (!Auth::isAdmin()) {
            $ids = Auth::groupProjectIds();
            $pid = $cat['project_id'] ?? null;
            if ($pid && $ids !== null && !in_array((int)$pid, $ids, true)) {
                http_response_code(403); echo json_encode(['error' => 'No access to this project']); exit;
            }
        }
        Database::execute('UPDATE test_plan_items SET category_id=NULL WHERE category_id=?', [(int)$id]);
        Database::execute('DELETE FROM test_categories WHERE id=?', [(int)$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}
