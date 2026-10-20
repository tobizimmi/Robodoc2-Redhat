<?php
declare(strict_types=1);

class EntryTemplateController
{
    public static function index(): void
    {
        Auth::require();
        [$accessSql, $accessParams] = self::projectClause('t');
        json(Database::fetchAll(
            "SELECT t.*, et.name type_name, et.color type_color, p.name project_name
             FROM entry_templates t
             LEFT JOIN entry_types et ON et.id = t.entry_type_id
             LEFT JOIN projects p ON p.id = t.project_id
             WHERE $accessSql
             ORDER BY t.name",
            $accessParams
        ));
    }

    public static function create(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $name = trim($_POST['name'] ?? '');
        if (!$name) { json(['error' => 'Name required'], 422); }
        $projectId = $_POST['project_id'] ? (int)$_POST['project_id'] : null;
        self::checkAccess(['project_id' => $projectId]);
        $id = Database::insert(
            "INSERT INTO entry_templates (name, entry_type_id, project_id, description, firmware_version, app_version, error_category_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $name,
                $_POST['entry_type_id'] ? (int)$_POST['entry_type_id'] : null,
                $projectId,
                trim($_POST['description'] ?? '') ?: null,
                trim($_POST['firmware_version'] ?? '') ?: null,
                trim($_POST['app_version'] ?? '') ?: null,
                $_POST['error_category_id'] ? (int)$_POST['error_category_id'] : null,
                Auth::id(),
            ]
        );
        json(['id' => $id, 'name' => $name]);
    }

    public static function delete(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $tpl = Database::fetchOne('SELECT project_id FROM entry_templates WHERE id=?', [(int)$id]);
        if (!$tpl) { json(['error' => 'Not found'], 404); }
        self::checkAccess($tpl);
        Database::execute('DELETE FROM entry_templates WHERE id=?', [(int)$id]);
        json(['ok' => true]);
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
