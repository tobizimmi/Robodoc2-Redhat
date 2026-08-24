<?php
declare(strict_types=1);

class EntryTemplateController
{
    public static function index(): void
    {
        Auth::require();
        json(Database::fetchAll(
            "SELECT t.*, et.name type_name, et.color type_color, p.name project_name
             FROM entry_templates t
             LEFT JOIN entry_types et ON et.id = t.entry_type_id
             LEFT JOIN projects p ON p.id = t.project_id
             ORDER BY t.name"
        ));
    }

    public static function create(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $name = trim($_POST['name'] ?? '');
        if (!$name) { json(['error' => 'Name required'], 422); }
        $id = Database::insert(
            "INSERT INTO entry_templates (name, entry_type_id, project_id, description, firmware_version, app_version, error_category_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $name,
                $_POST['entry_type_id'] ? (int)$_POST['entry_type_id'] : null,
                $_POST['project_id']    ? (int)$_POST['project_id']    : null,
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
        Database::execute('DELETE FROM entry_templates WHERE id=?', [(int)$id]);
        json(['ok' => true]);
    }
}
