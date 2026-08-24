<?php
declare(strict_types=1);

class EntryExportController
{
    // ── Wizard modal data (AJAX) ───────────────────────────────────────────────
    public static function wizard(string $id): void
    {
        Auth::require();
        $entry = self::loadEntry((int)$id);
        if (!$entry) abort(404);
        $templates = Database::fetchAll('SELECT id, name, description, is_default FROM entry_export_templates ORDER BY is_default DESC, name');
        json(['templates' => $templates, 'entry_id' => $id, 'title' => $entry['title']]);
    }

    // ── Generate export (HTML for print/PDF) ──────────────────────────────────
    public static function export(string $id): void
    {
        Auth::require();
        $entry = self::loadEntry((int)$id);
        if (!$entry) abort(404);

        $templateId = (int)($_GET['template'] ?? 1);
        $tpl = Database::fetchOne('SELECT * FROM entry_export_templates WHERE id=?', [$templateId])
            ?? Database::fetchOne('SELECT * FROM entry_export_templates WHERE is_default=1')
            ?? ['name'=>'Default','primary_color'=>'#1e3a5f','accent_color'=>'#3b82f6','font_family'=>'Arial, sans-serif','header_html'=>'','footer_html'=>''];

        // Fields to include (from GET params, fallback to template defaults)
        $defaults = json_decode($tpl['default_fields'] ?? '{}', true) ?? [];
        $fields = [
            'description'  => (bool)(($_GET['f_description']  ?? $defaults['description']  ?? 1)),
            'metadata'     => (bool)(($_GET['f_metadata']      ?? $defaults['metadata']      ?? 1)),
            'attachments'  => (bool)(($_GET['f_attachments']   ?? $defaults['attachments']   ?? 1)),
            'images'       => (bool)(($_GET['f_images']        ?? $defaults['images']        ?? 1)),
            'comments'     => (bool)(($_GET['f_comments']      ?? $defaults['comments']      ?? 1)),
            'history'      => (bool)(($_GET['f_history']       ?? $defaults['history']       ?? 0)),
            'test_results' => (bool)(($_GET['f_test_results']  ?? $defaults['test_results']  ?? 1)),
            'jira_info'    => (bool)(($_GET['f_jira_info']     ?? $defaults['jira_info']     ?? 1)),
            'sub_entries'  => (bool)(($_GET['f_sub_entries']   ?? $defaults['sub_entries']   ?? 0)),
        ];

        $imageSize = in_array($_GET['img_size'] ?? '', ['small', 'medium', 'large', 'full'], true)
            ? $_GET['img_size'] : 'medium';

        // Load related data
        $comments    = $fields['comments']  ? Database::fetchAll(
            'SELECT c.*, u.name user_name FROM entry_comments c LEFT JOIN users u ON u.id=c.user_id WHERE c.entry_id=? ORDER BY c.created_at',
            [(int)$id]
        ) : [];
        $attachments = ($fields['attachments'] || $fields['images']) ? Database::fetchAll(
            'SELECT * FROM entry_attachments WHERE entry_id=? AND (test_result_id IS NULL) ORDER BY created_at',
            [(int)$id]
        ) : [];
        // Always try to load test results — don't rely on is_test_entry flag
        $testResults = $fields['test_results'] ? Database::fetchAll(
            'SELECT * FROM entry_test_results WHERE entry_id=? ORDER BY sort_order',
            [(int)$id]
        ) : [];
        foreach ($testResults as &$tr) {
            $tr['attachments'] = Database::fetchAll(
                'SELECT * FROM entry_attachments WHERE entry_id=? AND test_result_id=? ORDER BY created_at',
                [(int)$id, $tr['id']]
            );
        }
        $history = $fields['history'] ? Database::fetchAll(
            'SELECT h.*, u.name user_name FROM entry_history h LEFT JOIN users u ON u.id=h.user_id WHERE h.entry_id=? ORDER BY h.changed_at DESC LIMIT 50',
            [(int)$id]
        ) : [];
        $subEntries = $fields['sub_entries'] ? Database::fetchAll(
            'SELECT e.id, e.title, e.status, e.entry_date FROM entries e WHERE e.parent_id=? ORDER BY e.entry_date DESC',
            [(int)$id]
        ) : [];
        $project = Database::fetchOne('SELECT name, color FROM projects WHERE id=?', [$entry['project_id']]);

        View::render('entries/export', compact(
            'entry', 'tpl', 'fields', 'comments', 'attachments',
            'testResults', 'history', 'subEntries', 'project', 'imageSize'
        ), 'export');
    }

    // ── Admin: manage templates ────────────────────────────────────────────────
    public static function templates(): void
    {
        Auth::requireAdmin();
        $templates = Database::fetchAll('SELECT * FROM entry_export_templates ORDER BY is_default DESC, name');
        View::render('admin/export_templates', compact('templates') + ['title' => 'Export Templates']);
    }

    public static function templateSave(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $id      = (int)($_POST['id'] ?? 0);
        $data = [
            'name'          => trim($_POST['name'] ?? 'Template'),
            'description'   => trim($_POST['description'] ?? ''),
            'primary_color' => $_POST['primary_color'] ?? '#1e3a5f',
            'accent_color'  => $_POST['accent_color']  ?? '#3b82f6',
            'font_family'   => $_POST['font_family']   ?? 'Arial, sans-serif',
            'header_html'   => $_POST['header_html']   ?? '',
            'footer_html'   => $_POST['footer_html']   ?? '',
            'is_default'    => isset($_POST['is_default']) ? 1 : 0,
            'default_fields'=> json_encode([
                'description' => isset($_POST['df_description']) ? 1 : 0,
                'metadata'    => isset($_POST['df_metadata'])    ? 1 : 0,
                'attachments' => isset($_POST['df_attachments']) ? 1 : 0,
                'images'      => isset($_POST['df_images'])      ? 1 : 0,
                'comments'    => isset($_POST['df_comments'])    ? 1 : 0,
                'history'     => isset($_POST['df_history'])     ? 1 : 0,
                'test_results'=> isset($_POST['df_test_results'])? 1 : 0,
                'jira_info'   => isset($_POST['df_jira_info'])   ? 1 : 0,
                'sub_entries' => isset($_POST['df_sub_entries']) ? 1 : 0,
            ]),
        ];
        // Handle logo upload
        if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $dir  = UPLOAD_DIR . 'export_templates/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext  = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $dest = $dir . 'logo_' . ($id ?: 'new') . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                $data['logo_path'] = $dest;
            }
        }
        if ($data['is_default']) {
            Database::execute('UPDATE entry_export_templates SET is_default=0');
        }
        if ($id) {
            $sets = implode(',', array_map(fn($k) => "$k=?", array_keys($data)));
            Database::execute("UPDATE entry_export_templates SET $sets WHERE id=?",
                [...array_values($data), $id]);
        } else {
            $cols = implode(',', array_keys($data));
            $phs  = implode(',', array_fill(0, count($data), '?'));
            Database::execute("INSERT INTO entry_export_templates ($cols) VALUES ($phs)",
                array_values($data));
        }
        flash('success', 'Template saved.');
        redirect('/admin/export-templates');
    }

    public static function templateDelete(string $id): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM entry_export_templates WHERE id=? AND is_default=0', [(int)$id]);
        flash('success', 'Template deleted.');
        redirect('/admin/export-templates');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function loadEntry(int $id): ?array
    {
        $entry = Database::fetchOne(
            "SELECT e.*, u.name created_by_name, p.name project_name,
                    et.name entry_type_name, et.color entry_type_color,
                    ec.name error_category_name,
                    ass.name assigned_to_name,
                    ep.title epic_title
             FROM entries e
             LEFT JOIN users u        ON u.id  = e.created_by
             LEFT JOIN projects p     ON p.id  = e.project_id
             LEFT JOIN entry_types et ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             LEFT JOIN users ass      ON ass.id = e.assigned_to
             LEFT JOIN epics ep       ON ep.id = e.epic_id
             WHERE e.id = ?",
            [$id]
        );
        if (!$entry) return null;
        // Check access
        $access = Auth::groupAccess();
        if ($access !== null && !in_array($entry['project_id'], $access)) return null;
        // Detect test entry
        $testTypeIds = array_filter(array_map('trim', explode(',', appSetting('test_result_entry_type_ids',''))));
        $entry['is_test_entry'] = in_array($entry['entry_type_id'], $testTypeIds);
        return $entry;
    }
}
