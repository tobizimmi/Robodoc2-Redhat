<?php
declare(strict_types=1);

class TodoController {
    public static function index(): void {
        Auth::requireView('todos');
        $userId = Auth::id();

        $entryTodos = Database::fetchAll(
            "SELECT et.id, et.entry_id, et.created_at, e.title, e.description, e.entry_date,
                    t.name type_name, t.color type_color, p.name project_name
             FROM entry_todos et
             JOIN entries e ON e.id = et.entry_id
             LEFT JOIN entry_types t ON t.id = e.entry_type_id
             LEFT JOIN projects p ON p.id = e.project_id
             WHERE et.user_id = ? ORDER BY et.created_at DESC",
            [$userId]
        );

        $standaloneTodos = Database::fetchAll(
            'SELECT * FROM standalone_todos WHERE user_id=? ORDER BY done, due_date, created_at',
            [$userId]
        );

        View::render('todos/index', compact('entryTodos', 'standaloneTodos') + ['title' => 'To-dos']);
    }

    public static function create(): void {
        Auth::requireEdit('todos');
        Auth::verifyCsrf();
        $title = trim($_POST['title'] ?? '');
        if (!$title) { flash('error', 'Titel erforderlich.'); redirect('/todos'); }
        Database::insert(
            'INSERT INTO standalone_todos (user_id, title, due_date) VALUES (?,?,?)',
            [Auth::id(), $title, $_POST['due_date'] ?: null]
        );
        flash('success', 'To-do erstellt.');
        redirect('/todos');
    }

    public static function toggle(string $id): void {
        Auth::requireEdit('todos');
        Auth::verifyCsrf();
        $todo = Database::fetchOne('SELECT * FROM standalone_todos WHERE id=? AND user_id=?', [(int)$id, Auth::id()]);
        if (!$todo) abort(404);
        $done = $todo['done'] ? 0 : 1;
        Database::execute('UPDATE standalone_todos SET done=? WHERE id=?', [$done, (int)$id]);
        json(['done' => (bool)$done]);
    }

    public static function delete(string $id): void {
        Auth::requireEdit('todos');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM standalone_todos WHERE id=? AND user_id=?', [(int)$id, Auth::id()]);
        flash('success', 'Todo deleted.');
        redirect('/todos');
    }
}
