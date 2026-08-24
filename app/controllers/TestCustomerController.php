<?php
declare(strict_types=1);

class TestCustomerController
{
    // ── List all orders ───────────────────────────────────────────────────────
    public static function index(): void
    {
        Auth::requireView('entries');
        $projects = Database::fetchAll("SELECT id, name, color FROM projects WHERE status='active' ORDER BY name");
        $orders   = Database::fetchAll(
            "SELECT o.*, p.name project_name, p.color project_color, u.name creator_name,
                    (SELECT COUNT(*) FROM test_customer_feedback f WHERE f.order_id = o.id) feedback_count,
                    (SELECT COUNT(*) FROM test_customer_feedback f WHERE f.order_id = o.id AND f.status='pending') pending_count,
                    (SELECT COUNT(*) FROM questionnaires q WHERE q.order_id = o.id) questionnaire_count
             FROM test_customer_orders o
             LEFT JOIN projects p ON p.id = o.project_id
             LEFT JOIN users u    ON u.id = o.created_by
             ORDER BY o.created_at DESC"
        );
        View::render('test-customers/index', compact('orders', 'projects'), 'app');
    }

    // ── Show single order ─────────────────────────────────────────────────────
    public static function show(string $id): void
    {
        Auth::requireView('entries');
        $order = Database::fetchOne(
            "SELECT o.*, p.name project_name, p.color project_color
             FROM test_customer_orders o
             LEFT JOIN projects p ON p.id = o.project_id
             WHERE o.id = ?", [(int)$id]
        );
        if (!$order) { http_response_code(404); echo 'Not found'; return; }

        $feedback = Database::fetchAll(
            "SELECT f.*, u.name reviewer_name
             FROM test_customer_feedback f
             LEFT JOIN users u ON u.id = f.reviewed_by
             WHERE f.order_id = ? ORDER BY f.created_at DESC",
            [(int)$id]
        );
        $questionnaires = Database::fetchAll(
            "SELECT q.*,
                    (SELECT COUNT(*) FROM questionnaire_responses r WHERE r.questionnaire_id = q.id) response_count
             FROM questionnaires q WHERE q.order_id = ? ORDER BY q.created_at DESC",
            [(int)$id]
        );
        $templates   = Database::fetchAll("SELECT id, title FROM questionnaire_templates ORDER BY title");
        $respondents = Database::fetchAll(
            'SELECT * FROM test_customer_respondents WHERE order_id=? ORDER BY customer_number, label',
            [(int)$id]
        );
        $baseUrl   = rtrim(BASE_URL, '/');
        View::render('test-customers/show', compact('order', 'feedback', 'questionnaires', 'templates', 'respondents', 'baseUrl'), 'app');
    }

    // ── Create order ──────────────────────────────────────────────────────────
    public static function create(): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $title     = trim($_POST['title'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 0);
        $desc      = trim($_POST['description'] ?? '');
        if (!$title || !$projectId) { redirect('/test-customers'); }
        $token = bin2hex(random_bytes(16));
        $id = Database::insert(
            "INSERT INTO test_customer_orders (project_id, title, description, feedback_instructions, status, qr_token, created_by) VALUES (?,?,?,?,?,?,?)",
            [$projectId, $title, $desc, '', 'draft', $token, Auth::id()]
        );
        redirect('/test-customers/' . $id);
    }

    // ── Update order ──────────────────────────────────────────────────────────
    public static function update(string $id): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $title        = trim($_POST['title'] ?? '');
        $desc         = trim($_POST['description'] ?? '');
        $instructions = trim($_POST['feedback_instructions'] ?? '');
        $status       = $_POST['status'] ?? 'active';
        Database::execute(
            "UPDATE test_customer_orders SET title=?, description=?, feedback_instructions=?, status=? WHERE id=?",
            [$title, $desc, $instructions, $status, (int)$id]
        );
        redirect('/test-customers/' . $id);
    }

    // ── Respondents (individual test customers per order) ─────────────────────
    public static function createRespondent(string $orderId): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $label  = trim($_POST['label'] ?? '');
        $number = trim($_POST['customer_number'] ?? '');
        $email  = trim($_POST['email'] ?? '') ?: null;
        if (!$label) { redirect('/test-customers/' . $orderId); }
        $token = bin2hex(random_bytes(16));
        Database::insert(
            'INSERT INTO test_customer_respondents (order_id, label, customer_number, email, token) VALUES (?,?,?,?,?)',
            [(int)$orderId, $label, $number, $email, $token]
        );
        redirect('/test-customers/' . $orderId);
    }

    public static function deleteRespondent(string $orderId, string $rid): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        Database::execute(
            'DELETE FROM test_customer_respondents WHERE id=? AND order_id=?',
            [(int)$rid, (int)$orderId]
        );
        redirect('/test-customers/' . $orderId);
    }

    // Personal feedback form for a specific respondent
    public static function respondentFeedbackForm(string $token): void
    {
        $respondent = Database::fetchOne(
            'SELECT r.*, o.title order_title, o.qr_token order_token,
                    o.feedback_instructions, o.status order_status,
                    p.name project_name
             FROM test_customer_respondents r
             JOIN test_customer_orders o ON o.id = r.order_id
             JOIN projects p ON p.id = o.project_id
             WHERE r.token = ? AND o.status = ?',
            [$token, 'active']
        );
        if (!$respondent) { http_response_code(404); echo 'Dieser Link ist nicht aktiv.'; return; }
        // Build $order-compatible array for the view
        $order = [
            'id'                    => null,
            'qr_token'              => $respondent['order_token'],
            'title'                 => $respondent['order_title'],
            'project_name'          => $respondent['project_name'],
            'feedback_instructions' => $respondent['feedback_instructions'],
        ];
        View::render('test-customers/feedback-public', compact('order', 'respondent'), 'public');
    }

    public static function respondentFeedbackSubmit(string $token): void
    {
        $respondent = Database::fetchOne(
            'SELECT r.*, o.id order_id, o.qr_token order_token, o.status
             FROM test_customer_respondents r
             JOIN test_customer_orders o ON o.id = r.order_id
             WHERE r.token = ? AND o.status = ?',
            [$token, 'active']
        );
        if (!$respondent) { http_response_code(404); return; }
        if (trim($_POST['website'] ?? '') !== '') { redirect('/tc-respondent/' . $token . '/success'); }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!self::checkRateLimit('resp_' . hash('sha256', $ip), 10)) {
            http_response_code(429); echo '<p style="font-family:sans-serif;padding:2rem">Zu viele Anfragen.</p>'; exit;
        }

        $title   = trim($_POST['title'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $serial  = trim($_POST['mower_serial'] ?? '');
        $fw      = trim($_POST['firmware_version'] ?? '');
        if (!$title) { redirect('/tc-respondent/' . $token); }

        $fbId = Database::insert(
            'INSERT INTO test_customer_feedback
             (order_id, respondent_id, title, description, mower_serial, firmware_version,
              respondent_name, respondent_contact, ip_hash)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $respondent['order_id'], $respondent['id'],
                $title, $desc,
                $serial ?: null, $fw ?: null,
                null, null, // no personal data stored
                hash('sha256', (defined('APP_KEY') ? APP_KEY : 'robodoc2') . ':' . date('Y-m-d') . ':' . ($_SERVER['REMOTE_ADDR'] ?? ''))
            ]
        );

        // Handle file uploads — strict MIME whitelist
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'video/mp4', 'video/quicktime', 'video/x-msvideo',
        ];
        if ($fbId && !empty($_FILES['files']['name'][0])) {
            $uploadDir = __DIR__ . '/../../storage/tc-feedback/' . $fbId . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            foreach ($_FILES['files']['name'] as $i => $fname) {
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmp  = $_FILES['files']['tmp_name'][$i];
                $mime = mime_content_type($tmp);
                if (!in_array($mime, $allowedMimes, true)) continue; // reject invalid types
                // Strip dangerous extensions — force safe extension from MIME
                $ext  = match($mime) {
                    'image/jpeg'       => 'jpg',
                    'image/png'        => 'png',
                    'image/gif'        => 'gif',
                    'image/webp'       => 'webp',
                    'application/pdf'  => 'pdf',
                    'video/mp4'        => 'mp4',
                    'video/quicktime'  => 'mov',
                    'video/x-msvideo'  => 'avi',
                    default            => 'bin',
                };
                $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fname, PATHINFO_FILENAME))
                      . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($tmp, $uploadDir . $safe);
            }
        }
        redirect('/tc-respondent/' . $token . '/success');
    }

    public static function respondentFeedbackSuccess(string $token): void
    {
        View::render('test-customers/feedback-success',
            ['order' => null, 'respondentToken' => $token], 'public');
    }

    // ── Delete order ──────────────────────────────────────────────────────────
    public static function delete(string $id): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        Database::execute("DELETE FROM test_customer_feedback WHERE order_id=?", [(int)$id]);
        Database::execute("DELETE FROM questionnaire_responses WHERE questionnaire_id IN (SELECT id FROM questionnaires WHERE order_id=?)", [(int)$id]);
        Database::execute("DELETE FROM questionnaires WHERE order_id=?", [(int)$id]);
        Database::execute("DELETE FROM test_customer_orders WHERE id=?", [(int)$id]);
        redirect('/test-customers');
    }

    // ── Public feedback form ──────────────────────────────────────────────────
    public static function feedbackForm(string $token): void
    {
        $order = Database::fetchOne(
            "SELECT o.*, p.name project_name FROM test_customer_orders o
             LEFT JOIN projects p ON p.id = o.project_id
             WHERE o.qr_token = ? AND o.status = 'active'", [$token]
        );
        if (!$order) { http_response_code(404); echo 'Dieser Link ist nicht mehr aktiv.'; return; }
        View::render('test-customers/feedback-public', compact('order'), 'public');
    }

    // ── Submit public feedback ────────────────────────────────────────────────
    // ── Rate limiting helper ──────────────────────────────────────────────────
    private static function checkRateLimit(string $key, int $maxPerHour = 10): bool
    {
        $cacheFile = sys_get_temp_dir() . '/robodoc2_rl_' . md5($key) . '.json';
        $now    = time();
        $window = 3600; // 1 hour
        $hits   = [];
        if (file_exists($cacheFile)) {
            $hits = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        // Remove hits older than 1 hour
        $hits = array_filter($hits, fn($t) => ($now - $t) < $window);
        if (count($hits) >= $maxPerHour) return false; // rate limited
        $hits[] = $now;
        file_put_contents($cacheFile, json_encode(array_values($hits)));
        return true;
    }

    public static function feedbackSubmit(string $token): void
    {
        $order = Database::fetchOne(
            "SELECT * FROM test_customer_orders WHERE qr_token = ? AND status = 'active'", [$token]
        );
        if (!$order) { http_response_code(404); return; }
        // Honeypot
        if (trim($_POST['website'] ?? '') !== '') { redirect('/tc-feedback/' . $token . '/success'); }

        // Rate limit: max 10 submissions per IP per hour
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!self::checkRateLimit('feedback_' . hash('sha256', $ip), 10)) {
            http_response_code(429);
            echo '<p style="font-family:sans-serif;padding:2rem">Zu viele Anfragen. Bitte später erneut versuchen.</p>';
            exit;
        }

        $title   = trim($_POST['title'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $name    = trim($_POST['respondent_name'] ?? '');
        $contact = trim($_POST['respondent_contact'] ?? '');
        $serial  = trim($_POST['mower_serial'] ?? '');
        $fw      = trim($_POST['firmware_version'] ?? '');
        $rating  = isset($_POST['rating']) ? max(1, min(5, (int)$_POST['rating'])) : null;

        if (!$title) { redirect('/tc-feedback/' . $token); }

        $fbId = Database::insert(
            "INSERT INTO test_customer_feedback (order_id, title, description, respondent_name, respondent_contact, mower_serial, firmware_version, ip_hash)
             VALUES (?,?,?,?,?,?,?,?)",
            [$order['id'], $title, $desc, $name ?: null, $contact ?: null, $serial ?: null, $fw ?: null, hash('sha256', (defined('APP_KEY') ? APP_KEY : 'robodoc2') . ':' . date('Y-m-d') . ':' . ($_SERVER['REMOTE_ADDR'] ?? ''))]
        );
        // Handle file uploads — strict MIME whitelist
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'video/mp4', 'video/quicktime', 'video/x-msvideo',
        ];
        if ($fbId && !empty($_FILES['files']['name'][0])) {
            $uploadDir = __DIR__ . '/../../storage/tc-feedback/' . $fbId . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            foreach ($_FILES['files']['name'] as $i => $fname) {
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmp  = $_FILES['files']['tmp_name'][$i];
                $mime = mime_content_type($tmp);
                if (!in_array($mime, $allowedMimes, true)) continue; // reject invalid types
                // Strip dangerous extensions — force safe extension from MIME
                $ext  = match($mime) {
                    'image/jpeg'       => 'jpg',
                    'image/png'        => 'png',
                    'image/gif'        => 'gif',
                    'image/webp'       => 'webp',
                    'application/pdf'  => 'pdf',
                    'video/mp4'        => 'mp4',
                    'video/quicktime'  => 'mov',
                    'video/x-msvideo'  => 'avi',
                    default            => 'bin',
                };
                $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fname, PATHINFO_FILENAME))
                      . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($tmp, $uploadDir . $safe);
            }
        }
        redirect('/tc-feedback/' . $token . '/success');
    }

    public static function feedbackSuccess(string $token): void
    {
        $order = Database::fetchOne("SELECT * FROM test_customer_orders WHERE qr_token = ?", [$token]);
        View::render('test-customers/feedback-success',
            ['order' => $order, 'respondentToken' => null], 'public');
    }

    // ── Global feedback list (all orders) ────────────────────────────────────
    public static function allFeedback(): void
    {
        Auth::requireView('entries');

        // Filter params
        $fOrder    = (int)($_GET['order_id'] ?? 0);
        $fCustomer = (int)($_GET['customer_id'] ?? 0);
        $fStatus   = trim($_GET['status'] ?? '');
        $fSearch   = trim($_GET['search'] ?? '');
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 50;

        $where  = ['1=1'];
        $params = [];
        if ($fOrder)    { $where[] = 'f.order_id = ?';             $params[] = $fOrder; }
        if ($fCustomer) { $where[] = 'r.test_customer_id = ?';     $params[] = $fCustomer; }
        if ($fStatus)   { $where[] = 'f.status = ?';               $params[] = $fStatus; }
        if ($fSearch)   { $where[] = '(f.title LIKE ? OR f.description LIKE ?)'; $params[] = "%$fSearch%"; $params[] = "%$fSearch%"; }
        $wStr = implode(' AND ', $where);

        $total = (int)(Database::fetchOne(
            "SELECT COUNT(*) c FROM test_customer_feedback f
             LEFT JOIN test_customer_respondents r ON r.id = f.respondent_id
             WHERE $wStr", $params
        )['c'] ?? 0);

        $offset  = ($page - 1) * $perPage;
        $feedback = Database::fetchAll(
            "SELECT f.*,
                    o.title order_title, o.id order_id,
                    p.name project_name, p.color project_color,
                    r.label resp_label, r.customer_number resp_number,
                    tc.label tc_label, tc.customer_number tc_number, tc.email tc_email,
                    u.name reviewer_name
             FROM test_customer_feedback f
             LEFT JOIN test_customer_orders o ON o.id = f.order_id
             LEFT JOIN projects p ON p.id = o.project_id
             LEFT JOIN test_customer_respondents r ON r.id = f.respondent_id
             LEFT JOIN test_customers tc ON tc.id = r.test_customer_id
             LEFT JOIN users u ON u.id = f.reviewed_by
             WHERE $wStr
             ORDER BY f.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $orders    = Database::fetchAll('SELECT id, title FROM test_customer_orders ORDER BY title');
        $customers = Database::fetchAll('SELECT id, customer_number, label FROM test_customers ORDER BY customer_number');
        $pag       = paginate($total, $page, $perPage);

        View::render('test-customers/all-feedback',
            compact('feedback', 'orders', 'customers', 'pag',
                    'fOrder', 'fCustomer', 'fStatus', 'fSearch', 'total'),
            'app');
    }

    // ── Show single feedback ─────────────────────────────────────────────────
    public static function showFeedback(string $orderId, string $fbId): void
    {
        Auth::requireView('entries');
        $order = Database::fetchOne(
            'SELECT o.*, p.name project_name, p.color project_color
             FROM test_customer_orders o LEFT JOIN projects p ON p.id=o.project_id
             WHERE o.id=?', [(int)$orderId]
        );
        $fb = Database::fetchOne(
            'SELECT * FROM test_customer_feedback WHERE id=? AND order_id=?',
            [(int)$fbId, (int)$orderId]
        );
        if (!$order || !$fb) { http_response_code(404); echo 'Not found'; return; }
        $entryTypes = Database::fetchAll('SELECT id, name FROM entry_types ORDER BY sort_order, name');
        // Load respondent if feedback was submitted via personal link
        $respondent = null;
        if (!empty($fb['respondent_id'])) {
            $respondent = Database::fetchOne(
                'SELECT r.*, tc.label tc_label, tc.customer_number tc_number, tc.email tc_email
                 FROM test_customer_respondents r
                 LEFT JOIN test_customers tc ON tc.id = r.test_customer_id
                 WHERE r.id = ?',
                [$fb['respondent_id']]
            );
        }
        // Load attachments from storage
        $attachments = [];
        $dir = __DIR__ . '/../../storage/tc-feedback/' . $fb['id'] . '/';
        if (is_dir($dir)) {
            foreach (glob($dir . '*') as $file) {
                $mime = mime_content_type($file) ?: 'application/octet-stream';
                $name = basename($file);
                $attachments[] = [
                    'name' => $name,
                    'mime' => $mime,
                    'url'  => url('tc-feedback-file/' . $fb['id'] . '/' . urlencode($name)),
                ];
            }
        }
        View::render('test-customers/feedback-review', compact('order', 'fb', 'entryTypes', 'attachments', 'respondent'), 'app');
    }

    // ── Reopen feedback (set back to pending) ────────────────────────────────
    public static function reopenFeedback(string $orderId, string $fbId): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        Database::execute(
            'UPDATE test_customer_feedback SET status=\'pending\', reviewed_by=NULL, reviewed_at=NULL WHERE id=? AND order_id=?',
            [(int)$fbId, (int)$orderId]
        );
        redirect('/test-customers/' . $orderId . '/feedback/' . $fbId);
    }

    // ── Delete single feedback ────────────────────────────────────────────────
    public static function deleteFeedback(string $orderId, string $fbId): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        // Delete attachments from disk
        $dir = __DIR__ . '/../../storage/tc-feedback/' . (int)$fbId . '/';
        if (is_dir($dir)) {
            foreach (glob($dir . '*') as $f) { @unlink($f); }
            @rmdir($dir);
        }
        Database::execute(
            'DELETE FROM test_customer_feedback WHERE id=? AND order_id=?',
            [(int)$fbId, (int)$orderId]
        );
        redirect('/test-customers/' . $orderId);
    }

    // ── Review feedback ───────────────────────────────────────────────────────
    public static function reviewFeedback(string $orderId, string $feedbackId): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $action = $_POST['action'] ?? 'review';
        if ($action === 'import') {
            $fb = Database::fetchOne("SELECT * FROM test_customer_feedback WHERE id=? AND order_id=?", [(int)$feedbackId, (int)$orderId]);
            if ($fb) {
                $order      = Database::fetchOne("SELECT * FROM test_customer_orders WHERE id=?", [(int)$orderId]);
                $typeId     = (int)($_POST['entry_type_id'] ?? 0);
                $title      = trim($_POST['title'] ?? $fb['title']);
                $desc       = trim($_POST['description'] ?? $fb['description']);
                $serial     = trim($_POST['mower_serial'] ?? $fb['mower_serial'] ?? '');
                $fw         = trim($_POST['firmware_version'] ?? $fb['firmware_version'] ?? '');
                $entryId = Database::insert(
                    "INSERT INTO entries (project_id, title, description, entry_type_id, status, priority, mower_serial, firmware_version, created_by, entry_date, entry_time)
                     VALUES (?,?,?,?,'new','Medium',?,?,?,?,?)",
                    [$order['project_id'], $title, $desc, $typeId ?: null, $serial ?: null, $fw ?: null, Auth::id(), date('Y-m-d'), date('H:i:s')]
                );
                Database::execute(
                    "UPDATE test_customer_feedback SET status='imported', entry_id=?, reviewed_by=?, reviewed_at=? WHERE id=?",
                    [$entryId, Auth::id(), date('Y-m-d H:i:s'), (int)$feedbackId]
                );
            }
        } else {
            Database::execute(
                "UPDATE test_customer_feedback SET status='rejected', reviewed_by=?, reviewed_at=? WHERE id=?",
                [Auth::id(), date('Y-m-d H:i:s'), (int)$feedbackId]
            );
        }
        // After import: stay on detail; after reject: go back to feedback list
        if ($action === 'import') {
            redirect('/test-customers/' . $orderId . '/feedback/' . $feedbackId);
        } else {
            redirect('/feedback?tab=archive');
        }
    }

    // ── Central Test Customers (global directory) ─────────────────────────────
    public static function testCustomers(): void
    {
        Auth::requireView('entries');
        $customers = Database::fetchAll(
            'SELECT tc.*, u.name creator_name,
             (SELECT COUNT(*) FROM test_customer_respondents r WHERE r.test_customer_id=tc.id) order_count
             FROM test_customers tc LEFT JOIN users u ON u.id=tc.created_by
             ORDER BY tc.customer_number'
        );
        View::render('test-customers/customers', compact('customers'), 'app');
    }

    public static function saveTestCustomer(): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $id     = (int)($_POST['id'] ?? 0);
        $number = trim($_POST['customer_number'] ?? '');
        $label  = trim($_POST['label'] ?? '');
        $email  = trim($_POST['email'] ?? '') ?: null;
        $notes  = trim($_POST['notes'] ?? '') ?: null;
        if (!$number || !$label) { redirect('/test-customers/customers'); }
        if ($id) {
            Database::execute('UPDATE test_customers SET customer_number=?,label=?,email=?,notes=? WHERE id=?',
                [$number, $label, $email, $notes, $id]);
        } else {
            Database::insert('INSERT INTO test_customers (customer_number,label,email,notes,created_by) VALUES (?,?,?,?,?)',
                [$number, $label, $email, $notes, Auth::id()]);
        }
        redirect('/test-customers/customers');
    }

    public static function deleteTestCustomer(string $id): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_customers WHERE id=?', [(int)$id]);
        redirect('/test-customers/customers');
    }

    // Add existing test customer to order as respondent
    public static function addRespondentFromCustomer(string $orderId): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $tcId = (int)($_POST['test_customer_id'] ?? 0);
        if (!$tcId) { redirect('/test-customers/' . $orderId); }
        $tc = Database::fetchOne('SELECT * FROM test_customers WHERE id=?', [$tcId]);
        if (!$tc) { redirect('/test-customers/' . $orderId); }
        // Check not already added
        $existing = Database::fetchOne(
            'SELECT id FROM test_customer_respondents WHERE order_id=? AND test_customer_id=?',
            [(int)$orderId, $tcId]
        );
        if (!$existing) {
            $token = bin2hex(random_bytes(16));
            Database::insert(
                'INSERT INTO test_customer_respondents (order_id, test_customer_id, label, customer_number, email, token)
                 VALUES (?,?,?,?,?,?)',
                [(int)$orderId, $tcId, $tc['label'], $tc['customer_number'], $tc['email'], $token]
            );
        }
        redirect('/test-customers/' . $orderId);
    }

    // ── Questionnaire Templates ───────────────────────────────────────────────
    public static function templates(): void
    {
        Auth::requireView('entries');
        $templates = Database::fetchAll("SELECT t.*, u.name creator_name FROM questionnaire_templates t LEFT JOIN users u ON u.id=t.created_by ORDER BY t.title");
        View::render('test-customers/templates', compact('templates'), 'app');
    }

    public static function saveTemplate(): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $id       = (int)($_POST['template_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $questions = json_decode($_POST['questions'] ?? '[]', true) ?: [];
        if (!$title) { redirect('/test-customers/templates'); }
        if ($id) {
            Database::execute("UPDATE questionnaire_templates SET title=?,description=?,questions=? WHERE id=?",
                [$title, $desc, json_encode($questions), $id]);
        } else {
            Database::insert("INSERT INTO questionnaire_templates (title,description,questions,created_by) VALUES (?,?,?,?)",
                [$title, $desc, json_encode($questions), Auth::id()]);
        }
        redirect('/test-customers/templates');
    }

    public static function deleteTemplate(string $id): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        Database::execute("DELETE FROM questionnaire_templates WHERE id=?", [(int)$id]);
        redirect('/test-customers/templates');
    }

    // ── Create questionnaire for order ────────────────────────────────────────
    public static function createQuestionnaire(string $orderId): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        $title       = trim($_POST['title'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $templateId  = (int)($_POST['template_id'] ?? 0);
        $questionsRaw = $_POST['questions'] ?? '[]';

        if ($templateId) {
            $tpl = Database::fetchOne("SELECT * FROM questionnaire_templates WHERE id=?", [$templateId]);
            if ($tpl) {
                $title    = $title ?: $tpl['title'];
                $desc     = $desc ?: $tpl['description'];
                $questionsRaw = $tpl['questions'];
            }
        }
        $questions = json_decode($questionsRaw, true) ?: [];
        if (!$title) { redirect('/test-customers/' . $orderId); }

        $token = bin2hex(random_bytes(16));
        Database::insert(
            "INSERT INTO questionnaires (order_id, title, description, questions, qr_token, draft_mode, created_by) VALUES (?,?,?,?,?,1,?)",
            [(int)$orderId, $title, $desc, json_encode($questions), $token, Auth::id()]
        );
        redirect('/test-customers/' . $orderId);
    }

    // ── Update questionnaire ─────────────────────────────────────────────────
    public static function updateQuestionnaire(string $orderId, string $qId): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        // Only editable in draft mode
        $q = Database::fetchOne('SELECT draft_mode FROM questionnaires WHERE id=? AND order_id=?', [(int)$qId, (int)$orderId]);
        if (!$q || !$q['draft_mode']) { redirect('/test-customers/' . $orderId); }
        $title     = trim($_POST['title'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $questions = json_decode($_POST['questions'] ?? '[]', true) ?: [];
        if (!$title) { redirect('/test-customers/' . $orderId); }
        Database::execute(
            'UPDATE questionnaires SET title=?, description=?, questions=? WHERE id=? AND order_id=?',
            [$title, $desc, json_encode($questions), (int)$qId, (int)$orderId]
        );
        redirect('/test-customers/' . $orderId);
    }

    // ── Publish questionnaire (draft -> live, clears responses) ─────────────
    public static function publishQuestionnaire(string $orderId, string $qId): void
    {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();
        // Delete all existing draft responses
        Database::execute('DELETE FROM questionnaire_responses WHERE questionnaire_id=?', [(int)$qId]);
        // Set draft_mode=0 (live)
        Database::execute(
            'UPDATE questionnaires SET draft_mode=0 WHERE id=? AND order_id=?',
            [(int)$qId, (int)$orderId]
        );
        redirect('/test-customers/' . $orderId);
    }

    // ── Public questionnaire form ─────────────────────────────────────────────
    public static function questionnaireForm(string $token): void
    {
        $q = Database::fetchOne(
            "SELECT q.*, o.title order_title, o.qr_token order_token, p.name project_name
             FROM questionnaires q
             JOIN test_customer_orders o ON o.id = q.order_id
             JOIN projects p ON p.id = o.project_id
             WHERE q.qr_token = ? AND q.status = 'active'", [$token]
        );
        if (!$q) { http_response_code(404); echo 'Dieser Fragebogen ist nicht mehr aktiv.'; return; }
        $q['questions'] = json_decode($q['questions'] ?: '[]', true);
        View::render('test-customers/questionnaire-public', compact('q'), 'public');
    }

    public static function questionnaireSubmit(string $token): void
    {
        $q = Database::fetchOne("SELECT * FROM questionnaires WHERE qr_token=? AND status='active'", [$token]);
        if (!$q) { http_response_code(404); return; }
        if (trim($_POST['website'] ?? '') !== '') { redirect('/tc-questionnaire/' . $token . '/success'); }

        $answers = [];
        $questions = json_decode($q['questions'] ?: '[]', true);
        foreach ($questions as $i => $question) {
            $answers[$i] = $_POST['q_' . $i] ?? '';
        }
        $name    = trim($_POST['respondent_name'] ?? '');
        $contact = trim($_POST['respondent_contact'] ?? '');

        Database::insert(
            "INSERT INTO questionnaire_responses (questionnaire_id, respondent_name, respondent_contact, answers, ip_hash) VALUES (?,?,?,?,?)",
            [$q['id'], $name ?: null, $contact ?: null, json_encode($answers), hash('sha256', (defined('APP_KEY') ? APP_KEY : 'robodoc2') . ':' . date('Y-m-d') . ':' . ($_SERVER['REMOTE_ADDR'] ?? ''))]
        );
        redirect('/tc-questionnaire/' . $token . '/success');
    }

    public static function questionnaireSuccess(string $token): void
    {
        $q = Database::fetchOne("SELECT q.*, o.title order_title FROM questionnaires q JOIN test_customer_orders o ON o.id=q.order_id WHERE q.qr_token=?", [$token]);
        View::render('test-customers/questionnaire-success', compact('q'), 'public');
    }

    // ── View questionnaire responses ──────────────────────────────────────────
    public static function questionnaireResponses(string $orderId, string $questionnaireId): void
    {
        Auth::requireView('entries');
        $q = Database::fetchOne("SELECT * FROM questionnaires WHERE id=? AND order_id=?", [(int)$questionnaireId, (int)$orderId]);
        if (!$q) { redirect('/test-customers/' . $orderId); }
        $q['questions'] = json_decode($q['questions'] ?: '[]', true);
        $responses = Database::fetchAll(
            "SELECT * FROM questionnaire_responses WHERE questionnaire_id=? ORDER BY created_at DESC",
            [(int)$questionnaireId]
        );
        foreach ($responses as &$r) { $r['answers'] = json_decode($r['answers'] ?: '[]', true); }
        $order = Database::fetchOne("SELECT * FROM test_customer_orders WHERE id=?", [(int)$orderId]);
        View::render('test-customers/questionnaire-responses', compact('q', 'responses', 'order'), 'app');
    }

    // ── Serve feedback attachment ──────────────────────────────────────────────
    public static function serveFile(string $fbId, string $filename): void
    {
        Auth::requireView('entries');
        // Sanitize filename — no path traversal
        $filename = basename($filename);
        $path     = __DIR__ . '/../../storage/tc-feedback/' . (int)$fbId . '/' . $filename;
        if (!file_exists($path)) { http_response_code(404); echo 'Not found'; return; }
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    // ── Download QR code ──────────────────────────────────────────────────────
    public static function qrCode(string $type, string $token): void
    {
        Auth::requireView('entries');
        // Build absolute URL for QR code
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(BASE_URL, '/');
        if ($type === 'feedback') {
            $url = $scheme . '://' . $host . $basePath . '/tc-feedback/' . $token;
        } else {
            $url = $scheme . '://' . $host . $basePath . '/tc-questionnaire/' . $token;
        }
        $fname  = 'qr-' . $type . '-' . substr($token, 0, 8) . '.png';
        $qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data=' . urlencode($url);
        // Try curl first, fall back to file_get_contents
        $img = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($qrUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $img  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200) $img = false;
        }
        if (!$img) {
            $img = @file_get_contents($qrUrl);
        }
        if (!$img) {
            // Fallback: redirect to QR service directly
            header('Location: ' . $qrUrl);
            exit;
        }
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('Content-Length: ' . strlen($img));
        echo $img;
        exit;
    }
}
