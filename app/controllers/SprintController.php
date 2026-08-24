<?php
declare(strict_types=1);

class SprintController
{
    // ?? Sprint list ???????????????????????????????????????????
    public static function index(): void
    {
        Auth::requireView('sprint');
        $sprints = Database::fetchAll(
            "SELECT s.*,
                    u.name creator_name,
                    COUNT(se.entry_id) total_entries,
                    SUM(CASE WHEN e.status IN ('finished','finalized') THEN 1 ELSE 0 END) done_entries,
                    SUM(CASE WHEN e.status = 'rejected' THEN 1 ELSE 0 END) rejected_entries,
                    SUM(se.story_points) total_points,
                    SUM(CASE WHEN e.status IN ('finished','finalized','rejected') THEN se.story_points ELSE 0 END) done_points
             FROM sprints s
             LEFT JOIN users u ON u.id = s.created_by
             LEFT JOIN sprint_entries se ON se.sprint_id = s.id
             LEFT JOIN entries e ON e.id = se.entry_id
             GROUP BY s.id ORDER BY s.status='planning' DESC, s.status='active' DESC, s.start_date DESC, s.id DESC"
        );
        View::render('sprints/index', ['sprints' => $sprints, 'title' => 'Sprints']);
    }

    // ?? API: list sprints for dropdowns ??????????????????????
    public static function apiList(): void
    {
        Auth::requireView('sprint');
        json(Database::fetchAll(
            "SELECT id, name, status, start_date, end_date FROM sprints ORDER BY status='active' DESC, start_date DESC LIMIT 30"
        ));
    }

    // ?? Create sprint ?????????????????????????????????????????
    public static function create(): void
    {
        Auth::requireEdit('sprint');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $id = Database::insert(
                'INSERT INTO sprints (name, goal, start_date, end_date, velocity_points, created_by) VALUES (?,?,?,?,?,?)',
                [
                    trim($_POST['name'] ?? ''),
                    trim($_POST['goal'] ?? '') ?: null,
                    $_POST['start_date'] ?: null,
                    $_POST['end_date']   ?: null,
                    (int)($_POST['velocity_points'] ?? 0) ?: null,
                    Auth::id(),
                ]
            );
            flash('success', 'Sprint created.');
            redirect('/sprints/' . $id);
        }
        View::render('sprints/form', ['sprint' => null, 'title' => 'New Sprint']);
    }

    // ?? Sprint detail ?????????????????????????????????????????
    public static function show(string $id): void
    {
        Auth::requireView('sprint');
        $sprint  = self::findOr404((int)$id);
        $entries = self::sprintEntries((int)$id);

        // Group by status for board view
        $board = [];
        foreach (entryStatuses() as $slug => $label) {
            $board[$slug] = ['label' => $label, 'color' => entryStatusColor($slug), 'entries' => []];
        }
        foreach ($entries as $e) {
            $s = $e['status'] ?? 'new';
            if (!isset($board[$s])) $board[$s] = ['label' => $s, 'color' => 'secondary', 'entries' => []];
            $board[$s]['entries'][] = $e;
        }
        $board = array_filter($board, fn($b) => !empty($b['entries']) || true); // keep all

        // Stats
        $stats = self::sprintStats($entries, $sprint);

        // Other sprints for "copy to" dropdown
        $otherSprints = Database::fetchAll(
            "SELECT id, name FROM sprints WHERE id != ? AND status != 'completed' ORDER BY status='active' DESC, start_date",
            [(int)$id]
        );

        // Entries NOT yet in this sprint ? for the "Add Tickets" tab
        $alreadyIn   = array_column($entries, 'id');
        $excludeSql  = $alreadyIn ? 'AND e.id NOT IN (' . implode(',', array_fill(0, count($alreadyIn), '?')) . ')' : '';
        $available   = Database::fetchAll(
            "SELECT e.id, e.title, e.status, e.priority, e.entry_date,
                    e.jira_issue_key,
                    et.name type_name, et.color type_color,
                    ec.name cat_name,
                    p.name project_name
             FROM entries e
             LEFT JOIN entry_types et ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             LEFT JOIN projects p ON p.id = e.project_id
             WHERE (e.is_test_entry = 0 OR e.is_test_entry IS NULL) $excludeSql
             ORDER BY e.entry_date DESC, e.id DESC
             LIMIT 300",
            $alreadyIn ?: []
        );

        // All projects for filter
        $projects = Database::fetchAll('SELECT id, name FROM projects ORDER BY name');

        View::render('sprints/show', compact('sprint', 'entries', 'board', 'stats', 'otherSprints', 'available', 'projects') + ['title' => $sprint['name']]);
    }

    // ?? Edit sprint ???????????????????????????????????????????
    public static function edit(string $id): void
    {
        Auth::requireEdit('sprint');
        $sprint = self::findOr404((int)$id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute(
                'UPDATE sprints SET name=?, goal=?, start_date=?, end_date=?, velocity_points=? WHERE id=?',
                [
                    trim($_POST['name'] ?? ''),
                    trim($_POST['goal'] ?? '') ?: null,
                    $_POST['start_date'] ?: null,
                    $_POST['end_date']   ?: null,
                    (int)($_POST['velocity_points'] ?? 0) ?: null,
                    (int)$id,
                ]
            );
            flash('success', 'Sprint updated.');
            redirect('/sprints/' . $id);
        }
        View::render('sprints/form', ['sprint' => $sprint, 'title' => 'Edit Sprint']);
    }

    // ?? Status transitions ????????????????????????????????????
    public static function start(string $id): void
    {
        Auth::requireEdit('sprint'); Auth::verifyCsrf();
        Database::execute("UPDATE sprints SET status='active' WHERE id=? AND status='planning'", [(int)$id]);
        flash('success', 'Sprint started.');
        redirect('/sprints/' . $id);
    }

    public static function complete(string $id): void
    {
        Auth::requireEdit('sprint'); Auth::verifyCsrf();
        Database::execute("UPDATE sprints SET status='completed' WHERE id=? AND status='active'", [(int)$id]);
        flash('success', 'Sprint completed.');
        redirect('/sprints/' . $id);
    }

    public static function reopen(string $id): void
    {
        Auth::requireEdit('sprint'); Auth::verifyCsrf();
        Database::execute("UPDATE sprints SET status='active' WHERE id=? AND status='completed'", [(int)$id]);
        flash('info', 'Sprint re-opened.');
        redirect('/sprints/' . $id);
    }

    // ?? Delete sprint ?????????????????????????????????????????
    public static function delete(string $id): void
    {
        Auth::requireEdit('sprint'); Auth::verifyCsrf();
        Database::execute('DELETE FROM sprints WHERE id=?', [(int)$id]);
        flash('success', 'Sprint deleted.');
        redirect('/sprints');
    }

    // ?? Add entries to sprint ?????????????????????????????????
    public static function addEntries(string $id): void
    {
        Auth::requireEdit('sprint'); Auth::verifyCsrf();
        self::findOr404((int)$id);
        $entryIds = array_filter(array_map('intval', (array)($_POST['entry_ids'] ?? [])));
        $added    = 0;
        foreach ($entryIds as $eid) {
            try {
                Database::insert(
                    'INSERT IGNORE INTO sprint_entries (sprint_id, entry_id) VALUES (?,?)',
                    [(int)$id, $eid]
                );
                $added++;
            } catch (\Throwable) {}
        }
        flash('success', $added . ' ticket(s) added to sprint.');
        redirect('/sprints/' . $id);
    }

    // ?? Remove entry from sprint ??????????????????????????????
    public static function removeEntry(string $id, string $eid): void
    {
        Auth::requireEdit('sprint'); Auth::verifyCsrf();
        Database::execute('DELETE FROM sprint_entries WHERE sprint_id=? AND entry_id=?', [(int)$id, (int)$eid]);
        flash('info', 'Ticket removed from sprint.');
        redirect('/sprints/' . $id);
    }

    // ?? Update story points ???????????????????????????????????
    public static function updatePoints(string $id, string $eid): void
    {
        Auth::requireEdit('sprint');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $points = ($_POST['story_points'] ?? '') !== '' ? (int)$_POST['story_points'] : null;
        Database::execute(
            'UPDATE sprint_entries SET story_points=? WHERE sprint_id=? AND entry_id=?',
            [$points, (int)$id, (int)$eid]
        );
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    public static function toggleTop(string $id, string $eid): void
    {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');
        $row = Database::fetchOne(
            'SELECT is_top FROM sprint_entries WHERE sprint_id=? AND entry_id=?',
            [(int)$id, (int)$eid]
        );
        if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }
        $newTop = $row['is_top'] ? 0 : 1;
        Database::execute(
            'UPDATE sprint_entries SET is_top=? WHERE sprint_id=? AND entry_id=?',
            [$newTop, (int)$id, (int)$eid]
        );
        echo json_encode(['success' => true, 'is_top' => (bool)$newTop]);
        exit;
    }


    // ?? Copy incomplete entries to another sprint ?????????????
    public static function copyIncomplete(string $id): void
    {
        Auth::requireEdit('sprint'); Auth::verifyCsrf();
        $targetId = (int)($_POST['target_sprint_id'] ?? 0);
        if (!$targetId) { flash('error', 'Please select a target sprint.'); redirect('/sprints/' . $id); }

        $incomplete = Database::fetchAll(
            "SELECT se.entry_id, se.story_points FROM sprint_entries se
             JOIN entries e ON e.id = se.entry_id
             WHERE se.sprint_id = ? AND e.status NOT IN ('finished','finalized','rejected')",
            [(int)$id]
        );
        $count = 0;
        foreach ($incomplete as $row) {
            try {
                Database::insert(
                    'INSERT IGNORE INTO sprint_entries (sprint_id, entry_id, story_points) VALUES (?,?,?)',
                    [$targetId, $row['entry_id'], $row['story_points']]
                );
                $count++;
            } catch (\Throwable) {}
        }
        flash('success', "$count incomplete ticket(s) copied to the target sprint.");
        redirect('/sprints/' . $id);
    }

    // ?? Save retrospective ????????????????????????????????????
    public static function saveRetro(string $id): void
    {
        Auth::requireEdit('sprint'); Auth::verifyCsrf();
        Database::execute(
            'UPDATE sprints SET retro_notes=? WHERE id=?',
            [trim($_POST['retro_notes'] ?? ''), (int)$id]
        );
        flash('success', 'Retrospective saved.');
        redirect('/sprints/' . $id . '#retro');
    }

    // ?? Shared helpers ????????????????????????????????????????
    private static function findOr404(int $id): array
    {
        $s = Database::fetchOne('SELECT * FROM sprints WHERE id=?', [$id]);
        if (!$s) abort(404, 'Sprint not found');
        return $s;
    }

    private static function sprintEntries(int $sprintId): array
    {
        return Database::fetchAll(
            "SELECT e.id, e.title, e.status, e.priority, e.entry_date,
                    e.jira_issue_key, e.jira_status,
                    e.zentao_bug_id, e.zentao_status,
                    et.name type_name, et.color type_color,
                    ec.name cat_name,
                    p.name project_name,
                    u.name creator,
                    se.story_points, se.sort_order, se.added_at, se.is_top
             FROM sprint_entries se
             JOIN entries e ON e.id = se.entry_id
             LEFT JOIN entry_types et ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             LEFT JOIN projects p ON p.id = e.project_id
             LEFT JOIN users u ON u.id = e.created_by
             WHERE se.sprint_id = ?
             ORDER BY se.is_top DESC, se.sort_order, e.priority DESC, se.added_at",
            [$sprintId]
        );
    }

    public static function sprintStats(array $entries, array $sprint): array
    {
        $total   = count($entries);
        $done    = count(array_filter($entries, fn($e) => in_array($e['status'], ['finished','finalized'])));
        $rejected= count(array_filter($entries, fn($e) => $e['status'] === 'rejected'));
        $inprog  = count(array_filter($entries, fn($e) => in_array($e['status'], ['internal','reviewed','pending_at_supplier','ready_for_test'])));
        $notStarted = $total - $done - $rejected - $inprog;
        $totalPts= array_sum(array_column($entries, 'story_points'));
        $donePts = array_sum(array_map(
            fn($e) => in_array($e['status'], ['finished','finalized','rejected']) ? (int)$e['story_points'] : 0,
            $entries
        ));
        $pct     = $total > 0 ? round(($done + $rejected) / $total * 100) : 0;
        $capacity= (int)($sprint['velocity_points'] ?? 0);

        // Days remaining
        $daysLeft = null;
        if ($sprint['end_date'] && $sprint['status'] === 'active') {
            $daysLeft = max(0, (int)((new \DateTime($sprint['end_date']))->diff(new \DateTime())->days * -1 + (new \DateTime($sprint['end_date']))->diff(new \DateTime())->invert));
            $daysLeft = (new \DateTime())->diff(new \DateTime($sprint['end_date']))->invert ? 0 : (new \DateTime())->diff(new \DateTime($sprint['end_date']))->days;
        }

        return compact('total','done','rejected','inprog','notStarted','totalPts','donePts','pct','capacity','daysLeft');
    }
}
