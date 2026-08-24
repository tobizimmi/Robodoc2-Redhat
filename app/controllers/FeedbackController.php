<?php
declare(strict_types=1);

class FeedbackController
{
    // ── Combined Quick Capture + Testkunden feedback inbox (moderation) ────────
    public static function index(): void
    {
        Auth::require(); // requires login only — both QC and TC feedback visible to all logged-in users

        $fType     = $_GET['type']        ?? '';   // 'qc' | 'tc' | ''
        $fTab      = $_GET['tab']          ?? 'active'; // 'active' | 'archive'
        $fStatus   = $_GET['status']      ?? '';
        $fOrder    = (int)($_GET['order_id']    ?? 0);
        $fCustomer = (int)($_GET['customer_id'] ?? 0);
        $fSearch   = trim($_GET['search'] ?? '');
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 50;

        // ── Build unified list ────────────────────────────────────────────────
        $items = [];

        // Quick Captures
        if ($fType === '' || $fType === 'qc') {
            $where  = ['1=1'];
            $params = [];
            // Tab filter
            if ($fTab === 'archive') {
                $where[] = "(qc.status='approved' OR qc.status='rejected')";
            } else {
                $where[] = "qc.status='pending'";
            }
            if ($fStatus === 'imported') { $where[] = 'qc.entry_id IS NOT NULL'; }
            if ($fStatus === 'rejected') { $where[] = 'qc.entry_id IS NULL AND qc.status!=\'pending\''; }
            if ($fSearch) {
                $where[] = '(qc.title LIKE ? OR qc.description LIKE ?)';
                $params[] = "%$fSearch%"; $params[] = "%$fSearch%";
            }
            // skip if order/customer filter active (QC not linked to TC orders)
            if (!$fOrder && !$fCustomer) {
                $wStr = implode(' AND ', $where);
                $qcs = Database::fetchAll(
                    "SELECT qc.id, qc.title, qc.description, qc.status AS qc_status,
                            qc.created_at, qc.reporter_name, qc.mower_serial, qc.firmware_version,
                            e.id AS entry_id,
                            (SELECT COUNT(*) FROM quick_capture_files f WHERE f.capture_id=qc.id) AS file_count
                     FROM quick_captures qc
                     LEFT JOIN entries e ON e.id=qc.entry_id
                     WHERE $wStr ORDER BY qc.created_at DESC",
                    $params
                );
                foreach ($qcs as $qc) {
                    $status = match($qc['qc_status']) {
                        'pending'  => 'pending',
                        'approved' => $qc['entry_id'] ? 'imported' : 'rejected',
                        'rejected' => 'rejected',
                        default    => 'rejected'
                    };
                    $items[] = [
                        'type'          => 'qc',
                        'id'            => $qc['id'],
                        'title'         => $qc['title'],
                        'description'   => $qc['description'],
                        'status'        => $status,
                        'created_at'    => $qc['created_at'],
                        'sender'        => $qc['reporter_name'] ?? null,
                        'mower_serial'  => $qc['mower_serial'] ?? null,
                        'firmware'      => $qc['firmware_version'] ?? null,
                        'file_count'    => $qc['file_count'],
                        'entry_id'      => $qc['entry_id'],
                        'order_title'   => null,
                        'order_id'      => null,
                        'project_name'  => null,
                        'project_color' => null,
                        'customer_num'  => null,
                        'customer_name' => null,
                        'detail_url'    => url("quick-captures/{$qc['id']}"),
                    ];
                }
            }
        }

        // Testkunden Feedback
        if ($fType === '' || $fType === 'tc') {
            $where  = ['1=1'];
            $params = [];
            // Tab filter
            if ($fTab === 'archive') {
                $where[] = "f.status IN ('rejected','reviewed','imported')";
            } else {
                $where[] = "f.status='pending'";
            }
            if ($fStatus && in_array($fStatus, ['rejected','reviewed','imported','pending'])) {
                array_pop($where); // remove tab filter
                $where[] = 'f.status=?'; $params[] = $fStatus;
            }
            if ($fOrder)    { $where[] = 'f.order_id=?';           $params[] = $fOrder; }
            if ($fCustomer) { $where[] = 'r.test_customer_id=?';   $params[] = $fCustomer; }
            if ($fSearch)   {
                $where[] = '(f.title LIKE ? OR f.description LIKE ?)';
                $params[] = "%$fSearch%"; $params[] = "%$fSearch%";
            }
            $wStr = implode(' AND ', $where);
            $tcs = Database::fetchAll(
                "SELECT f.id, f.title, f.description, f.status, f.created_at,
                        f.respondent_name, f.mower_serial, f.firmware_version,
                        f.order_id, f.entry_id,
                        o.title order_title,
                        p.name project_name, p.color project_color,
                        r.label resp_label, r.customer_number resp_number,
                        tc.label tc_label, tc.customer_number tc_number
                 FROM test_customer_feedback f
                 LEFT JOIN test_customer_orders o ON o.id=f.order_id
                 LEFT JOIN projects p ON p.id=o.project_id
                 LEFT JOIN test_customer_respondents r ON r.id=f.respondent_id
                 LEFT JOIN test_customers tc ON tc.id=r.test_customer_id
                 WHERE $wStr ORDER BY f.created_at DESC",
                $params
            );
            foreach ($tcs as $fb) {
                $items[] = [
                    'type'          => 'tc',
                    'id'            => $fb['id'],
                    'title'         => $fb['title'],
                    'description'   => $fb['description'],
                    'status'        => $fb['status'] === 'reviewed' ? 'rejected' : $fb['status'],
                    'created_at'    => $fb['created_at'],
                    'sender'        => $fb['respondent_name'] ?? null,
                    'mower_serial'  => $fb['mower_serial'] ?? null,
                    'firmware'      => $fb['firmware_version'] ?? null,
                    'file_count'    => 0,
                    'entry_id'      => $fb['entry_id'] ?? null,
                    'order_id'      => $fb['order_id'],
                    'order_title'   => $fb['order_title'],
                    'project_name'  => $fb['project_name'],
                    'project_color' => $fb['project_color'],
                    'customer_num'  => $fb['tc_number'] ?? $fb['resp_number'] ?? null,
                    'customer_name' => $fb['tc_label']  ?? $fb['resp_label']  ?? null,
                    'detail_url'    => url("test-customers/{$fb['order_id']}/feedback/{$fb['id']}"),
                ];
            }
        }

        // Sort combined list by date desc
        usort($items, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));

        // Paginate
        $total  = count($items);
        $pag    = paginate($total, $page, $perPage);
        $items  = array_slice($items, ($page-1)*$perPage, $perPage);

        $orders    = Database::fetchAll("SELECT id, title FROM test_customer_orders ORDER BY title");
        $customers = Database::fetchAll("SELECT id, customer_number, label FROM test_customers ORDER BY customer_number");

        View::render("feedback/index", compact(
            "items", "pag", "total",
            "fType", "fTab", "fStatus", "fOrder", "fCustomer", "fSearch",
            "orders", "customers"
        ), "app");
    }

    // ── Personal "Benutzer Tool Feedback" (bug reports / ideas about the tool) ─
    public static function create(): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $type    = $_POST['type']    ?? 'bug';
            $title   = trim($_POST['title']   ?? '');
            $message = trim($_POST['message'] ?? '');
            if (!$title || !$message) {
                flash('error', 'Title and message are required.');
                redirect('/tool-feedback/new');
            }
            $fbId = Database::insert(
                "INSERT INTO user_feedback (user_id, type, title, message, status, created_at)
                 VALUES (?,?,?,?,'open',NOW())",
                [Auth::id(), $type, $title, $message]
            );
            if (!empty($_FILES['attachments']['name'][0])) {
                $dir = UPLOAD_DIR . 'feedback/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $count = count($_FILES['attachments']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    $orig = $_FILES['attachments']['name'][$i];
                    $safe = preg_replace('/[^a-z0-9._-]/', '_', strtolower(basename($orig)));
                    $dest = $dir . $fbId . '_' . uniqid() . '_' . $safe;
                    if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $dest)) {
                        Database::execute(
                            'INSERT INTO user_feedback_attachments (feedback_id, filename, original_name, file_path, mime_type, file_size) VALUES (?,?,?,?,?,?)',
                            [$fbId, basename($dest), $orig, $dest,
                             $_FILES['attachments']['type'][$i] ?? '', $_FILES['attachments']['size'][$i] ?? 0]
                        );
                    }
                }
            }
            flash('success', 'Thank you! Your feedback has been submitted.');
            redirect('/tool-feedback');
        }
        View::render('feedback/create', ['title' => 'Submit Feedback']);
    }

    public static function myFeedback(): void
    {
        Auth::require();
        $items = Database::fetchAll(
            'SELECT f.*, u.name user_name FROM user_feedback f
             LEFT JOIN users u ON u.id=f.user_id
             WHERE f.user_id=? ORDER BY f.created_at DESC',
            [Auth::id()]
        );
        $attCounts = [];
        foreach ($items as $item) {
            $cnt = Database::fetchOne('SELECT COUNT(*) cnt FROM user_feedback_attachments WHERE feedback_id=?', [$item['id']]);
            $attCounts[$item['id']] = (int)($cnt['cnt'] ?? 0);
        }
        View::render('feedback/my-feedback', compact('items', 'attCounts') + ['title' => 'My Feedback']);
    }

    public static function adminIndex(): void
    {
        Auth::requireAdmin();
        $status = $_GET['status'] ?? 'open';
        $items  = Database::fetchAll(
            "SELECT f.*, u.name user_name FROM user_feedback f
             LEFT JOIN users u ON u.id=f.user_id
             " . ($status !== 'all' ? "WHERE f.status=?" : "") . "
             ORDER BY f.created_at DESC",
            $status !== 'all' ? [$status] : []
        );
        View::render('feedback/admin', compact('items', 'status') + ['title' => 'Feedback Inbox']);
    }

    public static function adminShow(string $id): void
    {
        Auth::requireAdmin();
        $item = Database::fetchOne(
            'SELECT f.*, u.name user_name FROM user_feedback f
             LEFT JOIN users u ON u.id=f.user_id WHERE f.id=?',
            [(int)$id]
        );
        if (!$item) abort(404);
        $comments = Database::fetchAll(
            'SELECT c.*, u.name user_name FROM user_feedback_comments c
             LEFT JOIN users u ON u.id=c.user_id WHERE c.feedback_id=? ORDER BY c.created_at',
            [(int)$id]
        );
        $attachments = Database::fetchAll(
            'SELECT * FROM user_feedback_attachments WHERE feedback_id=? ORDER BY created_at',
            [(int)$id]
        );
        View::render('feedback/admin_show', compact('item', 'comments', 'attachments') + ['title' => 'Feedback #' . $id]);
    }

    public static function adminUpdateStatus(string $id): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $status = $_POST['status'] ?? 'open';
        if (!in_array($status, ['open','todo','done','rejected'])) abort(400);
        Database::execute('UPDATE user_feedback SET status=?, updated_at=NOW() WHERE id=?', [$status, (int)$id]);
        redirect('/admin/feedback/' . $id);
    }

    public static function adminComment(string $id): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $comment = trim($_POST['comment'] ?? '');
        if (!$comment) {
            redirect('/admin/feedback/' . $id);
        }
        Database::execute(
            'INSERT INTO user_feedback_comments (feedback_id, user_id, comment, created_at) VALUES (?,?,?,NOW())',
            [(int)$id, Auth::id(), $comment]
        );
        $item = Database::fetchOne('SELECT * FROM user_feedback WHERE id=?', [(int)$id]);
        if ($item && $item['user_id'] !== Auth::id()) {
            try {
                $user = Database::fetchOne('SELECT * FROM users WHERE id=?', [$item['user_id']]);
                if ($user) Mailer::sendSimple(
                    $user['email'],
                    'New comment on your feedback: ' . $item['title'],
                    'An admin has commented on your feedback "' . $item['title'] . '".<br><br>' .
                    '<strong>Comment:</strong> ' . htmlspecialchars($comment) . '<br><br>' .
                    'View: ' . appSetting('app_url') . '/tool-feedback'
                );
            } catch (Throwable) {}
        }
        redirect('/admin/feedback/' . $id);
    }

    public static function downloadAttachment(string $id): void
    {
        Auth::require();
        $att = Database::fetchOne('SELECT * FROM user_feedback_attachments WHERE id=?', [(int)$id]);
        if (!$att) abort(404);
        $fb = Database::fetchOne('SELECT user_id FROM user_feedback WHERE id=?', [$att['feedback_id']]);
        if (!$fb) abort(404);
        if ($fb['user_id'] !== Auth::id() && !Auth::isAdmin()) abort(403);
        $path = $att['file_path'];
        if (!file_exists($path)) abort(404, 'File not found');
        header('Content-Type: ' . ($att['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . addslashes($att['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
