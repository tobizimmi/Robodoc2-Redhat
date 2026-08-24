<?php
declare(strict_types=1);

/**
 * 8D problem-solving reports (D1-D8), including 5-Why and Ishikawa
 * (fishbone) root-cause tools. Each report gets a unique, human-readable
 * reference (8D-YYYY-NNNN).
 */
class EightDController
{
    // ── List ─────────────────────────────────────────────────────────────────
    public static function index(): void
    {
        Auth::requireView('eight_d');
        $status  = in_array($_GET['status'] ?? '', ['open', 'closed'], true) ? $_GET['status'] : '';
        $search  = trim($_GET['search'] ?? '');
        $where   = ['1=1'];
        $params  = [];
        if ($status)  { $where[] = 'r.status = ?'; $params[] = $status; }
        if ($search)  { $where[] = '(r.reference LIKE ? OR r.title LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
        $wStr = implode(' AND ', $where);

        $reports = Database::fetchAll(
            "SELECT r.*, p.name project_name, p.color project_color, u.name creator_name,
                    (SELECT COUNT(*) FROM eight_d_actions a WHERE a.report_id = r.id AND a.status NOT IN ('done','verified')) open_actions
             FROM eight_d_reports r
             LEFT JOIN projects p ON p.id = r.project_id
             LEFT JOIN users u    ON u.id = r.created_by
             WHERE $wStr
             ORDER BY r.created_at DESC",
            $params
        );
        $projects = Database::fetchAll("SELECT id, name FROM projects WHERE status='active' ORDER BY name");
        View::render('eight_d/index', compact('reports', 'projects', 'status', 'search') + ['title' => '8D-Berichte']);
    }

    // ── Create ───────────────────────────────────────────────────────────────
    public static function create(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        if (!Auth::canOwn('eight_d') && !Auth::canEdit('eight_d') && !Auth::isAdmin()) abort(403);

        $title     = trim($_POST['title'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 0) ?: null;
        $entryId   = (int)($_POST['entry_id'] ?? 0) ?: null;
        if (!$title) { flash('error', 'Titel ist erforderlich.'); redirect('/8d'); }

        $reference = self::nextReference();
        $id = Database::insert(
            'INSERT INTO eight_d_reports (reference, title, project_id, entry_id, created_by) VALUES (?,?,?,?,?)',
            [$reference, $title, $projectId, $entryId, Auth::id()]
        );
        flash('success', "8D-Bericht $reference angelegt.");
        redirect('/8d/' . $id);
    }

    // ── Show / editor ────────────────────────────────────────────────────────
    public static function show(string $id): void
    {
        Auth::requireView('eight_d');
        $report = self::loadOr404((int)$id);

        $team    = Database::fetchAll('SELECT * FROM eight_d_team_members WHERE report_id=? ORDER BY sort_order, id', [(int)$id]);
        $actions = Database::fetchAll(
            'SELECT a.*, u.name responsible_user_name
             FROM eight_d_actions a LEFT JOIN users u ON u.id = a.responsible_user_id
             WHERE a.report_id=? ORDER BY a.discipline, a.sort_order, a.id',
            [(int)$id]
        );
        $actionsByDiscipline = ['d3' => [], 'd5' => [], 'd6' => [], 'd7' => []];
        foreach ($actions as $a) { $actionsByDiscipline[$a['discipline']][] = $a; }

        $attachments = Database::fetchAll('SELECT * FROM eight_d_attachments WHERE report_id=? ORDER BY discipline, id', [(int)$id]);
        $attachmentsByDiscipline = ['d2' => [], 'd3' => [], 'd4' => [], 'd6' => []];
        foreach ($attachments as $att) { $attachmentsByDiscipline[$att['discipline']][] = $att; }

        $fiveWhy  = json_decode($report['d4_five_why']  ?? '[]', true) ?: [];
        $ishikawa = json_decode($report['d4_ishikawa']  ?? '{}', true) ?: [];
        $isIsNot  = json_decode($report['d2_is_is_not'] ?? '{}', true) ?: [];

        $projects    = Database::fetchAll("SELECT id, name FROM projects WHERE status='active' ORDER BY name");
        $users       = Database::fetchAll("SELECT id, name FROM users WHERE status='active' ORDER BY name");
        $linkedEntry = $report['entry_id'] ? Database::fetchOne('SELECT id, title FROM entries WHERE id=?', [$report['entry_id']]) : null;
        $canEdit     = self::canEditReport($report);

        View::render('eight_d/show', compact(
            'report', 'team', 'actionsByDiscipline', 'attachmentsByDiscipline', 'fiveWhy', 'ishikawa', 'isIsNot',
            'projects', 'users', 'linkedEntry', 'canEdit'
        ) + ['title' => $report['reference'] . ' — ' . $report['title']]);
    }

    // ── Save all D0-D8 text/JSON fields in one go ──────────────────────────────
    public static function update(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403, 'Keine Bearbeitungsrechte für diesen 8D-Bericht.');

        $title       = trim($_POST['title'] ?? $report['title']);
        $projectId   = (int)($_POST['project_id'] ?? 0) ?: null;

        $isIsNot = [];
        foreach (['what_is','what_isnot','where_is','where_isnot','when_is','when_isnot','extent_is','extent_isnot'] as $k) {
            $isIsNot[$k] = trim($_POST['isisnot_' . $k] ?? '');
        }

        $fiveWhy = json_decode($_POST['five_why_json'] ?? '[]', true);
        if (!is_array($fiveWhy)) $fiveWhy = [];
        $fiveWhy = array_values(array_filter(array_map(function ($chain) {
            return [
                'problem'    => trim((string)($chain['problem'] ?? '')),
                'whys'       => array_values(array_map(fn($w) => trim((string)$w), (array)($chain['whys'] ?? []))),
                'root_cause' => trim((string)($chain['root_cause'] ?? '')),
            ];
        }, $fiveWhy), fn($c) => $c['problem'] !== '' || array_filter($c['whys']) || $c['root_cause'] !== ''));

        $ishikawa = json_decode($_POST['ishikawa_json'] ?? '{}', true);
        if (!is_array($ishikawa)) $ishikawa = [];
        foreach ($ishikawa as $cat => $causes) {
            $ishikawa[$cat] = array_values(array_filter(array_map(fn($c) => trim((string)$c), (array)$causes), fn($c) => $c !== ''));
        }

        Database::execute(
            "UPDATE eight_d_reports SET
                title=?, project_id=?,
                d0_symptom=?, d0_emergency_response=?,
                d1_champion=?,
                d2_problem_description=?, d2_is_is_not=?,
                d4_five_why=?, d4_ishikawa=?, d4_root_cause=?, d4_escape_point=?,
                d5_selected_solution=?,
                d6_validation=?,
                d7_systemic_actions=?,
                d8_team_recognition=?
             WHERE id=?",
            [
                $title, $projectId,
                trim($_POST['d0_symptom'] ?? ''), trim($_POST['d0_emergency_response'] ?? ''),
                trim($_POST['d1_champion'] ?? ''),
                trim($_POST['d2_problem_description'] ?? ''), json_encode($isIsNot),
                json_encode($fiveWhy), json_encode($ishikawa),
                trim($_POST['d4_root_cause'] ?? ''), trim($_POST['d4_escape_point'] ?? ''),
                trim($_POST['d5_selected_solution'] ?? ''),
                trim($_POST['d6_validation'] ?? ''),
                trim($_POST['d7_systemic_actions'] ?? ''),
                trim($_POST['d8_team_recognition'] ?? ''),
                (int)$id,
            ]
        );
        flash('success', '8D-Bericht gespeichert.');
        redirect('/8d/' . $id . (!empty($_POST['tab']) ? '?tab=' . urlencode($_POST['tab']) : ''));
    }

    public static function close(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);
        Database::execute("UPDATE eight_d_reports SET status='closed', d8_closed_at=NOW() WHERE id=?", [(int)$id]);
        flash('success', '8D-Bericht abgeschlossen.');
        redirect('/8d/' . $id);
    }

    public static function reopen(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);
        Database::execute("UPDATE eight_d_reports SET status='open', d8_closed_at=NULL WHERE id=?", [(int)$id]);
        flash('success', '8D-Bericht wieder geöffnet.');
        redirect('/8d/' . $id);
    }

    public static function delete(string $id): void
    {
        Auth::require();
        $report = self::loadOr404((int)$id);
        if (!Auth::isAdmin() && !Auth::canEdit('eight_d')) abort(403);
        Auth::verifyCsrf();
        Database::execute('DELETE FROM eight_d_reports WHERE id=?', [(int)$id]);
        flash('success', '8D-Bericht gelöscht.');
        redirect('/8d');
    }

    // ── Team members (D1) ───────────────────────────────────────────────────
    public static function addTeamMember(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);
        $name = trim($_POST['name'] ?? '');
        if (!$name) { redirect('/8d/' . $id); }
        Database::insert(
            'INSERT INTO eight_d_team_members (report_id, name, role, department, sort_order) VALUES (?,?,?,?,?)',
            [(int)$id, $name, trim($_POST['role'] ?? '') ?: null, trim($_POST['department'] ?? '') ?: null,
             (int)(Database::fetchOne('SELECT COUNT(*) c FROM eight_d_team_members WHERE report_id=?', [(int)$id])['c'] ?? 0)]
        );
        redirect('/8d/' . $id . '?tab=d1');
    }

    public static function deleteTeamMember(string $id, string $memberId): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);
        Database::execute('DELETE FROM eight_d_team_members WHERE id=? AND report_id=?', [(int)$memberId, (int)$id]);
        redirect('/8d/' . $id . '?tab=d1');
    }

    // ── Actions (D3 / D5 / D6 / D7) ─────────────────────────────────────────
    public static function addAction(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);

        $discipline = $_POST['discipline'] ?? '';
        if (!in_array($discipline, ['d3', 'd5', 'd6', 'd7'], true)) abort(400);
        $description = trim($_POST['description'] ?? '');
        if (!$description) { redirect('/8d/' . $id . '?tab=' . $discipline); }

        Database::insert(
            'INSERT INTO eight_d_actions (report_id, discipline, description, responsible, responsible_user_id, due_date, sort_order)
             VALUES (?,?,?,?,?,?,?)',
            [
                (int)$id, $discipline, $description,
                trim($_POST['responsible'] ?? '') ?: null,
                (int)($_POST['responsible_user_id'] ?? 0) ?: null,
                trim($_POST['due_date'] ?? '') ?: null,
                (int)(Database::fetchOne('SELECT COUNT(*) c FROM eight_d_actions WHERE report_id=? AND discipline=?', [(int)$id, $discipline])['c'] ?? 0),
            ]
        );
        redirect('/8d/' . $id . '?tab=' . $discipline);
    }

    public static function updateAction(string $id, string $actionId): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);

        $action = Database::fetchOne('SELECT * FROM eight_d_actions WHERE id=? AND report_id=?', [(int)$actionId, (int)$id]);
        if (!$action) abort(404);

        $status = $_POST['status'] ?? $action['status'];
        if (!in_array($status, ['open', 'in_progress', 'done', 'verified'], true)) $status = $action['status'];
        $wasOpen = !in_array($action['status'], ['done', 'verified'], true);
        $nowDone = in_array($status, ['done', 'verified'], true);

        Database::execute(
            'UPDATE eight_d_actions SET description=?, responsible=?, responsible_user_id=?, due_date=?, status=?, verification=?, completed_at=?
             WHERE id=?',
            [
                trim($_POST['description'] ?? $action['description']),
                trim($_POST['responsible'] ?? '') ?: null,
                (int)($_POST['responsible_user_id'] ?? 0) ?: null,
                trim($_POST['due_date'] ?? '') ?: null,
                $status,
                trim($_POST['verification'] ?? '') ?: null,
                $nowDone ? ($wasOpen ? date('Y-m-d H:i:s') : $action['completed_at']) : null,
                (int)$actionId,
            ]
        );
        redirect('/8d/' . $id . '?tab=' . $action['discipline']);
    }

    public static function deleteAction(string $id, string $actionId): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);
        $action = Database::fetchOne('SELECT discipline FROM eight_d_actions WHERE id=? AND report_id=?', [(int)$actionId, (int)$id]);
        Database::execute('DELETE FROM eight_d_actions WHERE id=? AND report_id=?', [(int)$actionId, (int)$id]);
        redirect('/8d/' . $id . '?tab=' . ($action['discipline'] ?? 'd3'));
    }

    // ── Attachments (D2 / D3 / D4 / D6) ─────────────────────────────────────
    private const ATTACHMENT_MIME_EXT = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'image/heic' => 'heic', 'image/heif' => 'heif',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt', 'text/csv' => 'csv',
    ];

    public static function uploadAttachment(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);

        $discipline = $_POST['discipline'] ?? '';
        if (!in_array($discipline, ['d2', 'd3', 'd4', 'd6'], true)) abort(400);

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) { redirect('/8d/' . $id . '?tab=' . $discipline); }
        if ($file['size'] > MAX_FILE_SIZE) { flash('error', 'Datei zu groß.'); redirect('/8d/' . $id . '?tab=' . $discipline); }

        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $ext  = self::ATTACHMENT_MIME_EXT[$mime] ?? null;
        if (!$ext) { flash('error', 'Dateityp nicht erlaubt: ' . $mime); redirect('/8d/' . $id . '?tab=' . $discipline); }

        $dir = rtrim(UPLOAD_DIR, '/') . '/8d/' . $id . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { abort(500, 'Upload-Verzeichnis konnte nicht erstellt werden.'); }
        $fn   = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . $fn;
        if (!move_uploaded_file($file['tmp_name'], $dest)) { abort(500, 'Datei konnte nicht gespeichert werden.'); }

        Database::insert(
            'INSERT INTO eight_d_attachments (report_id, discipline, filename, original_name, mime_type, file_size, file_path, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [(int)$id, $discipline, $fn, $file['name'], $mime, $file['size'], $dest, Auth::id()]
        );
        redirect('/8d/' . $id . '?tab=' . $discipline);
    }

    public static function deleteAttachment(string $id, string $attId): void
    {
        Auth::require();
        Auth::verifyCsrf();
        $report = self::loadOr404((int)$id);
        if (!self::canEditReport($report)) abort(403);

        $att = Database::fetchOne('SELECT * FROM eight_d_attachments WHERE id=? AND report_id=?', [(int)$attId, (int)$id]);
        if ($att) {
            if (is_file($att['file_path'])) @unlink($att['file_path']);
            Database::execute('DELETE FROM eight_d_attachments WHERE id=?', [(int)$attId]);
        }
        redirect('/8d/' . $id . '?tab=' . ($att['discipline'] ?? 'd2'));
    }

    public static function downloadAttachment(string $attId): void
    {
        Auth::require();
        $att = Database::fetchOne('SELECT * FROM eight_d_attachments WHERE id=?', [(int)$attId]);
        if (!$att) abort(404);
        Auth::requireView('eight_d');
        if (!is_file($att['file_path'])) abort(404, 'Datei nicht gefunden.');

        $mime = $att['mime_type'] ?: 'application/octet-stream';
        $inline = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($att['file_path']));
        $safeName = preg_replace('/[\x00-\x1f\x7f"\\\\]/', '_', $att['original_name']);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"');
        readfile($att['file_path']);
        exit;
    }

    // ── Export (print/PDF-friendly, full D1-D8 document) ────────────────────
    public static function export(string $id): void
    {
        Auth::requireView('eight_d');
        $report = self::loadOr404((int)$id);

        $team    = Database::fetchAll('SELECT * FROM eight_d_team_members WHERE report_id=? ORDER BY sort_order, id', [(int)$id]);
        $actions = Database::fetchAll(
            'SELECT a.*, u.name responsible_user_name
             FROM eight_d_actions a LEFT JOIN users u ON u.id = a.responsible_user_id
             WHERE a.report_id=? ORDER BY a.discipline, a.sort_order, a.id',
            [(int)$id]
        );
        $actionsByDiscipline = ['d3' => [], 'd5' => [], 'd6' => [], 'd7' => []];
        foreach ($actions as $a) { $actionsByDiscipline[$a['discipline']][] = $a; }

        $attachments = Database::fetchAll('SELECT * FROM eight_d_attachments WHERE report_id=? ORDER BY discipline, id', [(int)$id]);
        $attachmentsByDiscipline = ['d2' => [], 'd3' => [], 'd4' => [], 'd6' => []];
        foreach ($attachments as $att) { $attachmentsByDiscipline[$att['discipline']][] = $att; }

        $fiveWhy  = json_decode($report['d4_five_why']  ?? '[]', true) ?: [];
        $ishikawa = json_decode($report['d4_ishikawa']  ?? '{}', true) ?: [];
        $isIsNot  = json_decode($report['d2_is_is_not'] ?? '{}', true) ?: [];
        $project  = $report['project_id'] ? Database::fetchOne('SELECT name, color FROM projects WHERE id=?', [$report['project_id']]) : null;
        $linkedEntry = $report['entry_id'] ? Database::fetchOne('SELECT id, title FROM entries WHERE id=?', [$report['entry_id']]) : null;

        View::render('eight_d/export', compact(
            'report', 'team', 'actionsByDiscipline', 'attachmentsByDiscipline', 'fiveWhy', 'ishikawa', 'isIsNot', 'project', 'linkedEntry'
        ), 'export');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    private static function loadOr404(int $id): array
    {
        $report = Database::fetchOne('SELECT * FROM eight_d_reports WHERE id=?', [$id]);
        if (!$report) abort(404, '8D-Bericht nicht gefunden.');
        return $report;
    }

    private static function canEditReport(array $report): bool
    {
        if (Auth::isAdmin()) return true;
        if (Auth::canEdit('eight_d')) return true;
        if (Auth::canOwn('eight_d') && (int)$report['created_by'] === Auth::id()) return true;
        return false;
    }

    private static function nextReference(): string
    {
        $year = date('Y');
        $row  = Database::fetchOne(
            "SELECT reference FROM eight_d_reports WHERE reference LIKE ? ORDER BY id DESC LIMIT 1",
            ["8D-$year-%"]
        );
        $seq = 1;
        if ($row && preg_match('/^8D-\d{4}-(\d+)$/', $row['reference'], $m)) {
            $seq = (int)$m[1] + 1;
        }
        return sprintf('8D-%s-%04d', $year, $seq);
    }
}
