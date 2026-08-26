<?php
declare(strict_types=1);

// Canonical entry statuses - slug => display label
function entryStatuses(): array {
    return [
        'new'                => 'New',
        'internal'           => 'Internal',
        'reviewed'           => 'Reviewed',
        'pending_at_supplier'=> 'Pending at Supplier',
        'ready_for_test'     => 'Ready for Test',
        'finished'           => 'Finished',
        'rejected'           => 'Rejected',
        // Legacy (kept for backwards compatibility)
        'open'               => 'Open',
        'ongoing'            => 'In Progress',
        'finalized'          => 'Finalized',
    ];
}

function entryStatusColor(string $status): string {
    return match($status) {
        'new'                 => 'secondary',
        'internal'            => 'info',
        'reviewed'            => 'primary',
        'pending_at_supplier' => 'warning',
        'ready_for_test'      => 'info',
        'finished', 'finalized' => 'success',
        'rejected'            => 'danger',
        'ongoing'             => 'primary',
        default               => 'secondary',
    };
}

function redirect(string $url, int $code = 302): never {
    header("Location: " . BASE_URL . $url, true, $code);
    exit;
}

function abort(int $code, string $message = ''): never {
    http_response_code($code);
    $title = match($code) {
        403 => 'Zugriff verweigert',
        404 => 'Nicht gefunden',
        419 => 'Sitzung abgelaufen',
        500 => 'Serverfehler',
        default => 'Fehler',
    };
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
           || !empty($_SERVER['HTTP_X_CSRF_TOKEN']);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message ?: $title, 'code' => $code]);
        exit;
    }
    $safeMsg  = htmlspecialchars($message ?: $title);
    $baseUrl  = defined('BASE_URL') ? BASE_URL : '';
    $icon = match($code) {
        403 => 'bi-shield-lock',
        404 => 'bi-search',
        419 => 'bi-clock-history',
        default => 'bi-exclamation-triangle',
    };
    echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} &mdash; RoboDoc</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center" style="min-height:100vh">
  <div class="text-center px-4" style="max-width:420px">
    <div class="mb-3" style="font-size:3.5rem;opacity:.25"><i class="bi {$icon}"></i></div>
    <h1 class="fw-bold mb-1" style="font-size:4rem;opacity:.4">{$code}</h1>
    <p class="lead mb-4">{$safeMsg}</p>
    <div class="d-flex gap-2 justify-content-center">
      <button onclick="rdGoBack()" class="btn btn-outline-light">
        <i class="bi bi-arrow-left me-1"></i>Zurueck
      </button>
      <a href="{$baseUrl}/" class="btn btn-primary">
        <i class="bi bi-house me-1"></i>Startseite
      </a>
    </div>
  </div>
  <script>
  function rdGoBack() {
    try {
      var STACK_KEY = 'rd_nav_stack';
      var stack = JSON.parse(sessionStorage.getItem(STACK_KEY) || '[]');
      var cur   = location.href;
      var target = null;
      while (stack.length > 0) {
        var candidate = stack[stack.length - 1];
        stack.pop();
        if (candidate !== cur) { target = candidate; break; }
      }
      sessionStorage.setItem(STACK_KEY, JSON.stringify(stack));
      if (target) { location.href = target; return; }
    } catch(e) {}
    history.back();
  }
  </script>
</body>
</html>
HTML;
    exit;
}

function view(string $template, array $data = []): void {
    extract($data);
    $__file = __DIR__ . '/views/' . $template . '.php';
    if (!file_exists($__file)) {
        abort(500, "View not found: {$template}");
    }
    require $__file;
}

function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function isActive(string $path): string {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return str_starts_with($uri, BASE_URL . '/' . ltrim($path, '/')) ? 'active' : '';
}

function formatDate(?string $date, string $format = 'd.m.Y'): string {
    if (!$date) return '-';
    return (new DateTime($date))->format($format);
}

function formatDateTime(?string $dt): string {
    if (!$dt) return '-';
    return (new DateTime($dt))->format('d.m.Y H:i');
}

function formatFileSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function paginate(int $total, int $page, int $perPage): array {
    $pages = max(1, (int)ceil($total / $perPage));
    return [
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $pages,
        'has_prev' => $page > 1,
        'has_next' => $page < $pages,
    ];
}

function appSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = Database::fetchAll('SELECT setting_key, setting_value FROM app_settings');
            $cache = array_column($rows, 'setting_value', 'setting_key');
        } catch (\Throwable) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function badge(string $text, string $color, string $extra = ''): string {
    return '<span class="badge ' . e($extra) . '" style="background:' . e($color) . '">' . e($text) . '</span>';
}

function entryTypeBadge(?array $type): string {
    if (!$type) return '';
    return badge($type['name'], $type['color']);
}

function categoryBadge(?array $cat): string {
    if (!$cat) return '';
    return badge($cat['name'], $cat['color']);
}

function isImage(string $mime): bool {
    return str_starts_with($mime, 'image/');
}

function isVideo(string $mime): bool {
    return str_starts_with($mime, 'video/');
}

function isPdf(string $mime): bool {
    return $mime === 'application/pdf';
}

function getAttachmentUrl(array $att): string {
    return url('attachments/' . $att['id']);
}

function getThumbnailUrl(array $att): string {
    return url('attachments/' . $att['id'] . '/thumb');
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
}

function flash(string $key, string $msg): void {
    Auth::flash($key, $msg);
}

function getFlash(string $key): ?string {
    return Auth::getFlash($key);
}

function getMigrations(): array {
    return [
        // entries extra columns
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS title VARCHAR(200) NULL DEFAULT NULL AFTER id",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS mower_serial VARCHAR(100) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS gps_lat DECIMAL(10,8) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS gps_lon DECIMAL(11,8) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS environment_id INT NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS is_private TINYINT(1) NOT NULL DEFAULT 0",
        // users extra columns
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','pending','disabled') NOT NULL DEFAULT 'active'",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS language VARCHAR(10) NOT NULL DEFAULT 'de'",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS jira_api_key VARCHAR(255) NULL",
        // Encryption: enlarge columns to store enc: prefix + base64 overhead
        "ALTER TABLE users MODIFY COLUMN IF EXISTS jira_api_key VARCHAR(512) NULL",
        "ALTER TABLE users MODIFY COLUMN IF EXISTS confluence_token VARCHAR(512) NULL",
        "ALTER TABLE users MODIFY COLUMN IF EXISTS sharepoint_client_secret VARCHAR(512) NULL DEFAULT NULL",
        // Encrypt existing plaintext API keys on first run (done via migration)
        "UPDATE users SET jira_api_key = CONCAT('enc:', TO_BASE64(UNHEX(SHA2(RAND(),256)))) WHERE jira_api_key IS NOT NULL AND jira_api_key != '' AND jira_api_key NOT LIKE 'enc:%' LIMIT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS jira_email VARCHAR(191) NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS confluence_token VARCHAR(255) NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS confluence_email VARCHAR(191) NULL",
        // projects extra columns
        "ALTER TABLE projects ADD COLUMN IF NOT EXISTS sharepoint_folder VARCHAR(500) NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS project_jira_configs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, project_id INT UNSIGNED NOT NULL, jira_project_key VARCHAR(50) NOT NULL, label VARCHAR(100) NOT NULL DEFAULT '', issue_type VARCHAR(50) NOT NULL DEFAULT 'Bug', sort_order INT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_pjc (project_id, jira_project_key), CONSTRAINT fk_pjc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS sharepoint_site_url VARCHAR(500) NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS sharepoint_tenant_id VARCHAR(150) NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS sharepoint_client_id VARCHAR(150) NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS sharepoint_client_secret VARCHAR(255) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS sharepoint_folder_url VARCHAR(1000) NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS sharepoint_access_token TEXT NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS sharepoint_refresh_token TEXT NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS sharepoint_token_expires_at INT NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS sharepoint_path_template VARCHAR(500) NULL DEFAULT NULL",
        // entries status
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS status ENUM('open','ongoing','finalized') NOT NULL DEFAULT 'open'",
        // entries jira link
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS jira_issue_key VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS jira_issue_url VARCHAR(500) NULL DEFAULT NULL",
        // entry_attachments extra columns
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS is_report_relevant TINYINT(1) NOT NULL DEFAULT 1",
        "UPDATE entries SET is_report_relevant=1 WHERE is_report_relevant IS NULL",
        "ALTER TABLE entry_attachments ADD COLUMN IF NOT EXISTS test_result_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'link to entry_test_results row'",
        "ALTER TABLE entry_attachments ADD COLUMN IF NOT EXISTS display_name VARCHAR(255) NULL",
        "ALTER TABLE entry_attachments ADD COLUMN IF NOT EXISTS comment TEXT NULL",
        "ALTER TABLE entry_attachments ADD COLUMN IF NOT EXISTS jira_synced TINYINT(1) NOT NULL DEFAULT 0",
        // extra tables
        "CREATE TABLE IF NOT EXISTS test_environments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            os VARCHAR(100), device VARCHAR(100), firmware VARCHAR(100), description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS entry_history (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            field_name VARCHAR(100) NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_hist_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS entry_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ec_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_ec_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_plan_item_entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            test_plan_item_id INT UNSIGNED NOT NULL,
            entry_id INT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tpie (test_plan_item_id, entry_id),
            CONSTRAINT fk_tpie_item FOREIGN KEY (test_plan_item_id) REFERENCES test_plan_items(id) ON DELETE CASCADE,
            CONSTRAINT fk_tpie_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_cycles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            test_plan_id INT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            description TEXT NULL,
            environment VARCHAR(191) NULL,
            build VARCHAR(100) NULL,
            status ENUM('planned','active','completed','aborted') NOT NULL DEFAULT 'planned',
            synapse_cycle_id VARCHAR(50) NULL DEFAULT NULL,
            synapse_plan_key VARCHAR(50) NULL DEFAULT NULL,
            synapse_synced_at DATETIME NULL DEFAULT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tc_plan FOREIGN KEY (test_plan_id) REFERENCES test_plans(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_runs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            test_plan_id INT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            description TEXT NULL,
            environment VARCHAR(191) NULL,
            status ENUM('planned','active','completed','aborted') NOT NULL DEFAULT 'planned',
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tr_plan FOREIGN KEY (test_plan_id) REFERENCES test_plans(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE test_runs ADD COLUMN IF NOT EXISTS test_cycle_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE test_runs ADD COLUMN IF NOT EXISTS synapse_plan_key VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_runs ADD COLUMN IF NOT EXISTS synapse_cycle_id VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_runs ADD COLUMN IF NOT EXISTS synapse_synced_at DATETIME NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS test_run_results (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            test_run_id INT UNSIGNED NOT NULL,
            test_plan_item_id INT UNSIGNED NOT NULL,
            status ENUM('pending','passed','failed','skipped','blocked') NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            executed_by INT UNSIGNED NULL,
            executed_at DATETIME NULL,
            CONSTRAINT fk_trr_run FOREIGN KEY (test_run_id) REFERENCES test_runs(id) ON DELETE CASCADE,
            CONSTRAINT fk_trr_item FOREIGN KEY (test_plan_item_id) REFERENCES test_plan_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS inventory_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NULL,
            name VARCHAR(191) NOT NULL,
            serial_number VARCHAR(100) NULL,
            firmware_version VARCHAR(100) NULL,
            location VARCHAR(191) NULL,
            comment TEXT NULL,
            status ENUM('available','in_use','maintenance','retired') NOT NULL DEFAULT 'available',
            purchased_at DATE NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS inventory_logbook (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            log_date DATE NOT NULL,
            log_time TIME NOT NULL DEFAULT '00:00:00',
            action VARCHAR(191) NOT NULL,
            description TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_il_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS requirements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status ENUM('planning','approved','in_progress','completed','cancelled') NOT NULL DEFAULT 'planning',
            priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS requirement_test_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            requirement_id INT UNSIGNED NOT NULL,
            test_plan_item_id INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_rti (requirement_id, test_plan_item_id),
            CONSTRAINT fk_rti_req FOREIGN KEY (requirement_id) REFERENCES requirements(id) ON DELETE CASCADE,
            CONSTRAINT fk_rti_item FOREIGN KEY (test_plan_item_id) REFERENCES test_plan_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS saved_filters (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            filters TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_checklists (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            description TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS checklist_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checklist_id INT UNSIGNED NOT NULL,
            text VARCHAR(500) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_ci_cl FOREIGN KEY (checklist_id) REFERENCES test_checklists(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            resource_type VARCHAR(50) NULL,
            resource_id INT UNSIGNED NULL,
            data TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // entry assignment + links
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS assigned_to INT UNSIGNED NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS entry_links (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_entry_id INT UNSIGNED NOT NULL,
            to_entry_id INT UNSIGNED NOT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_el (from_entry_id, to_entry_id),
            CONSTRAINT fk_el_from FOREIGN KEY (from_entry_id) REFERENCES entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_el_to FOREIGN KEY (to_entry_id) REFERENCES entries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // video markers
        "CREATE TABLE IF NOT EXISTS attachment_markers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attachment_id INT UNSIGNED NOT NULL,
            time_seconds DECIMAL(10,3) NOT NULL,
            label VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_am_att FOREIGN KEY (attachment_id) REFERENCES entry_attachments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // user preferences
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS jira_auto_create TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_new_entries TINYINT(1) NOT NULL DEFAULT 0",
        // standalone todos
        "CREATE TABLE IF NOT EXISTS standalone_todos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(500) NOT NULL,
            due_date DATE NULL,
            done TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_st_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // task details on entry_todos
        "ALTER TABLE entry_todos ADD COLUMN IF NOT EXISTS due_date DATE NULL DEFAULT NULL",
        "ALTER TABLE entry_todos ADD COLUMN IF NOT EXISTS priority ENUM('low','medium','high') NULL DEFAULT NULL",
        "ALTER TABLE entry_todos ADD COLUMN IF NOT EXISTS notes TEXT NULL DEFAULT NULL",
        // entry templates
        "CREATE TABLE IF NOT EXISTS entry_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            entry_type_id INT UNSIGNED NULL,
            project_id INT UNSIGNED NULL,
            description TEXT NULL,
            firmware_version VARCHAR(100) NULL,
            app_version VARCHAR(100) NULL,
            error_category_id INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // inventory extra fields
        "ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS notes TEXT NULL DEFAULT NULL",
        // test areas (physical locations)
        "CREATE TABLE IF NOT EXISTS test_areas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            location_description TEXT NULL,
            gps_lat DECIMAL(10,8) NULL,
            gps_lon DECIMAL(11,8) NULL,
            slope_max_percent DECIMAL(5,1) NULL,
            boundary_type VARCHAR(100) NULL,
            boundary_length_m DECIMAL(8,1) NULL,
            area_sqm DECIMAL(10,1) NULL,
            surface_types VARCHAR(500) NULL,
            obstacles TEXT NULL,
            notes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_area_photos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            test_area_id INT UNSIGNED NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            display_name VARCHAR(255) NULL,
            file_size INT UNSIGNED NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tap_area FOREIGN KEY (test_area_id) REFERENCES test_areas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // test sessions
        "CREATE TABLE IF NOT EXISTS test_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            project_id INT UNSIGNED NULL,
            test_area_id INT UNSIGNED NULL,
            firmware_version VARCHAR(100) NULL,
            app_version VARCHAR(100) NULL,
            operator_id INT UNSIGNED NULL,
            temperature DECIMAL(5,1) NULL,
            weather_condition VARCHAR(100) NULL,
            terrain_notes TEXT NULL,
            status ENUM('active','completed') NOT NULL DEFAULT 'active',
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // link entries to sessions + physical test areas + environmental data
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS session_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS test_area_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS temperature DECIMAL(5,1) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS weather_condition VARCHAR(100) NULL DEFAULT NULL",
        // jira status sync
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS jira_status VARCHAR(100) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS jira_status_synced_at DATETIME NULL DEFAULT NULL",
        // brute-force protection
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_la_ident (identifier),
            INDEX idx_la_time (failed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // audit log: add ip_address column if missing
        "ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL DEFAULT NULL",
        // user notification preferences: account approved
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_account_approved TINYINT(1) NOT NULL DEFAULT 1",
        // jira per-user default templates
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS jira_title_template TEXT NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS jira_desc_template TEXT NULL DEFAULT NULL",
        // confluence export history
        "CREATE TABLE IF NOT EXISTS confluence_exports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id VARCHAR(100) NULL,
            page_title VARCHAR(500) NOT NULL,
            page_url VARCHAR(1000) NOT NULL,
            space_key VARCHAR(50) NOT NULL,
            export_mode VARCHAR(50) NOT NULL DEFAULT 'entries',
            append_mode TINYINT(1) NOT NULL DEFAULT 0,
            exported_by INT UNSIGNED NULL,
            exported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ce_user FOREIGN KEY (exported_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // test requests feature
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_test_requests TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE user_groups ADD COLUMN IF NOT EXISTS can_test_requests TINYINT(1) NOT NULL DEFAULT 0",
        "CREATE TABLE IF NOT EXISTS test_request_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            description TEXT NULL,
            labels VARCHAR(500) NULL,
            project_name VARCHAR(191) NULL,
            project_number VARCHAR(100) NULL,
            order_number VARCHAR(100) NULL,
            product VARCHAR(191) NULL,
            initiator VARCHAR(191) NULL,
            development_type VARCHAR(100) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE test_request_templates ADD COLUMN IF NOT EXISTS order_number VARCHAR(100) NULL DEFAULT NULL",
        "ALTER TABLE test_request_templates ADD COLUMN IF NOT EXISTS initiator VARCHAR(191) NULL DEFAULT NULL",
        // jira sync tracking
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS jira_synced_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS jira_has_changes TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE test_requests ADD COLUMN IF NOT EXISTS jira_synced_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE test_requests ADD COLUMN IF NOT EXISTS jira_has_changes TINYINT(1) NOT NULL DEFAULT 0",
        "CREATE TABLE IF NOT EXISTS jira_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_type ENUM('entry','test_request') NOT NULL,
            source_id INT UNSIGNED NOT NULL,
            jira_comment_id VARCHAR(50) NOT NULL,
            author_name VARCHAR(191) NULL,
            body TEXT NULL,
            jira_created_at DATETIME NULL,
            jira_updated_at DATETIME NULL,
            synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_jc (source_type, source_id, jira_comment_id),
            INDEX idx_jc_source (source_type, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            summary VARCHAR(500) NOT NULL,
            description TEXT NULL,
            labels VARCHAR(500) NULL,
            project_name VARCHAR(191) NULL,
            project_number VARCHAR(100) NULL,
            order_number VARCHAR(100) NULL,
            product VARCHAR(191) NULL,
            initiator VARCHAR(191) NULL,
            development_type VARCHAR(100) NULL,
            status ENUM('draft','submitted','approved','rejected','closed') NOT NULL DEFAULT 'draft',
            jira_issue_key VARCHAR(50) NULL,
            jira_issue_url VARCHAR(500) NULL,
            template_id INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_tr_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_request_attachments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id INT UNSIGNED NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NULL,
            mime_type VARCHAR(100) NULL,
            file_size INT UNSIGNED NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tra_req FOREIGN KEY (request_id) REFERENCES test_requests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // test case custom fields
        "CREATE TABLE IF NOT EXISTS test_case_fields (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            variable_name VARCHAR(50) NOT NULL,
            field_type ENUM('text','textarea','select','number') NOT NULL DEFAULT 'text',
            options TEXT NULL,
            placeholder VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tcf_variable (variable_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_case_field_values (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_id INT UNSIGNED NOT NULL,
            field_id INT UNSIGNED NOT NULL,
            value TEXT NULL,
            UNIQUE KEY uq_tcfv (item_id, field_id),
            CONSTRAINT fk_tcfv_item FOREIGN KEY (item_id) REFERENCES test_plan_items(id) ON DELETE CASCADE,
            CONSTRAINT fk_tcfv_field FOREIGN KEY (field_id) REFERENCES test_case_fields(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // link test cases to test requests + test entries
        "ALTER TABLE test_plan_items ADD COLUMN IF NOT EXISTS test_request_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS is_test_entry TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS test_run_result_id INT UNSIGNED NULL DEFAULT NULL",
        // 'Test Result' entry type
        "INSERT INTO entry_types (name, color, icon, sort_order) VALUES ('Test Result', '#0ea5e9', 'clipboard-check', 99) ON DUPLICATE KEY UPDATE color='#0ea5e9', icon='clipboard-check'",
        // managed mower list
        "CREATE TABLE IF NOT EXISTS test_mowers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(191) NOT NULL,
            serial_number VARCHAR(100) NULL,
            model VARCHAR(100) NULL,
            firmware_version VARCHAR(100) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS entry_mowers (
            entry_id INT UNSIGNED NOT NULL,
            mower_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (entry_id, mower_id),
            CONSTRAINT fk_emow_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_emow_mower FOREIGN KEY (mower_id) REFERENCES test_mowers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS session_mowers (
            session_id INT UNSIGNED NOT NULL,
            mower_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (session_id, mower_id),
            CONSTRAINT fk_smow_session FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE,
            CONSTRAINT fk_smow_mower FOREIGN KEY (mower_id) REFERENCES test_mowers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // ZIP download tracking
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS zip_downloaded_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS attachments_updated_at DATETIME NULL DEFAULT NULL",
        // Entry priority field
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS priority ENUM('Low','Medium','High','Highest','Blocker') NOT NULL DEFAULT 'Medium'",
        // Server-side user presets (table configurations)
        "CREATE TABLE IF NOT EXISTS user_presets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            preset_type VARCHAR(50) NOT NULL DEFAULT 'entry_table',
            name VARCHAR(191) NOT NULL,
            config JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_preset (user_id, preset_type, name),
            INDEX idx_up_user (user_id, preset_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Store last-fetched priority from Jira/Zentao for pre-check comparisons
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS jira_priority VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS zentao_pri TINYINT UNSIGNED NULL DEFAULT NULL",
        // Sprint planning
        "CREATE TABLE IF NOT EXISTS sprints (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            goal TEXT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            status ENUM('planning','active','completed') NOT NULL DEFAULT 'planning',
            velocity_points SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Team capacity in story points',
            retro_notes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sprint_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS sprint_entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sprint_id INT UNSIGNED NOT NULL,
            entry_id INT UNSIGNED NOT NULL,
            story_points TINYINT UNSIGNED NULL DEFAULT NULL,
            sort_order SMALLINT NOT NULL DEFAULT 0,
            added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_se (sprint_id, entry_id),
            CONSTRAINT fk_se_sprint FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE CASCADE,
            CONSTRAINT fk_se_entry  FOREIGN KEY (entry_id)  REFERENCES entries(id)  ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Remove duplicate entry types (keep lowest id per name), then add unique index
        "DELETE e1 FROM entry_types e1 INNER JOIN entry_types e2 ON e1.name = e2.name AND e1.id > e2.id",
        "ALTER TABLE entry_types ADD UNIQUE INDEX IF NOT EXISTS uq_entry_type_name (name)",
        // Expand entry status to VARCHAR to support custom statuses
        "ALTER TABLE entries MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'new'",
        // Extend jira_comments to also store Zentao actions
        "ALTER TABLE jira_comments MODIFY COLUMN source_type VARCHAR(30) NOT NULL",
        // Key Question flag
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS is_key_question TINYINT(1) NOT NULL DEFAULT 0",
        // Zentao integration
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS zentao_bug_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS zentao_bug_url VARCHAR(500) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS zentao_synced_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS zentao_has_changes TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS zentao_status VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS zentao_bug_hash VARCHAR(32) NULL DEFAULT NULL",
        // Dismiss tables for unlinked Jira issues and Zentao bugs
        "CREATE TABLE IF NOT EXISTS dismissed_jira_issues (
            issue_key VARCHAR(50) NOT NULL,
            dismissed_by INT UNSIGNED NULL,
            dismissed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (issue_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS dismissed_zentao_bugs (
            bug_id INT UNSIGNED NOT NULL,
            dismissed_by INT UNSIGNED NULL,
            dismissed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (bug_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Test Steps CRUD
        "ALTER TABLE test_case_steps ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE test_case_steps ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
        // Test Case status sync with SynapseRT
        "ALTER TABLE test_plan_items ADD COLUMN IF NOT EXISTS synapse_status VARCHAR(50) NULL DEFAULT NULL",
        // Tester assignment on test run results
        "ALTER TABLE test_run_results ADD COLUMN IF NOT EXISTS assigned_tester INT UNSIGNED NULL DEFAULT NULL",
        // Bug links on test run results: entry_id = RoboDoc entry, jira_key = linked Jira ticket
        "CREATE TABLE IF NOT EXISTS test_run_bugs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, test_run_result_id INT UNSIGNED NOT NULL, entry_id INT UNSIGNED NULL, jira_key VARCHAR(50) NULL, synapse_synced_at DATETIME NULL, created_by INT UNSIGNED NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_trb_result FOREIGN KEY (test_run_result_id) REFERENCES test_run_results(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE test_case_steps ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE test_plan_items ADD COLUMN IF NOT EXISTS synapse_status VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_run_results ADD COLUMN IF NOT EXISTS assigned_tester INT UNSIGNED NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS test_run_bugs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, test_run_result_id INT UNSIGNED NOT NULL, entry_id INT UNSIGNED NULL, jira_key VARCHAR(50) NULL, synapse_synced_at DATETIME NULL, created_by INT UNSIGNED NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_trb_result FOREIGN KEY (test_run_result_id) REFERENCES test_run_results(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS user_project_filters (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL UNIQUE, project_ids TEXT NOT NULL DEFAULT '[]', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT fk_upf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Tags
        "CREATE TABLE IF NOT EXISTS tags (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, color VARCHAR(20) NOT NULL DEFAULT '#6c757d', created_by INT UNSIGNED NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE tags ADD CONSTRAINT IF NOT EXISTS uq_tag_name UNIQUE (name)",
        "CREATE TABLE IF NOT EXISTS entry_tags (entry_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (entry_id, tag_id), CONSTRAINT fk_et_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE, CONSTRAINT fk_et_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Tag Kanban: personal buckets
        "CREATE TABLE IF NOT EXISTS tag_kanban_buckets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL, color VARCHAR(20) NOT NULL DEFAULT '#6c757d', sort_order INT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_tkb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS tag_kanban_bucket_tags (bucket_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, PRIMARY KEY (bucket_id, tag_id), CONSTRAINT fk_tkbt_bucket FOREIGN KEY (bucket_id) REFERENCES tag_kanban_buckets(id) ON DELETE CASCADE, CONSTRAINT fk_tkbt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS epics (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, project_id INT UNSIGNED NULL, title VARCHAR(200) NOT NULL, description TEXT NULL, color VARCHAR(20) NOT NULL DEFAULT '#8b5cf6', jira_epic_key VARCHAR(50) NULL, sort_order SMALLINT NOT NULL DEFAULT 0, created_by INT UNSIGNED NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS epic_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE sprint_entries ADD COLUMN IF NOT EXISTS is_top TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS merged_into_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS is_merged TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS merged_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS merged_by INT UNSIGNED NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS entry_sharepoint_files (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, entry_id INT UNSIGNED NOT NULL, attachment_id INT UNSIGNED NULL, filename VARCHAR(255) NOT NULL, web_url VARCHAR(1000) NOT NULL, uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_esf_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE entry_attachments ADD COLUMN IF NOT EXISTS compress_pending TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE entry_attachments ADD COLUMN IF NOT EXISTS compress_target_path VARCHAR(500) NULL DEFAULT NULL",
        // User Feedback system
        "CREATE TABLE IF NOT EXISTS user_feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type ENUM('bug','improvement','question','other') NOT NULL DEFAULT 'bug',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('open','todo','done','rejected') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_uf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Security tables
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_verified_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL DEFAULT NULL",
        // NIS2 compliance: enhanced audit log
        "ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255) NULL DEFAULT NULL",
        "ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS session_id VARCHAR(64) NULL DEFAULT NULL",
        // NIS2: data retention — audit log retention policy (keep 2 years)
        "CREATE EVENT IF NOT EXISTS purge_old_audit_logs
            ON SCHEDULE EVERY 1 DAY DO
            DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 730 DAY)",
        // NIS2: access log for sensitive data
        // BSI: password change tracking
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0",
        "CREATE TABLE IF NOT EXISTS data_access_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            resource_type VARCHAR(50) NOT NULL,
            resource_id INT UNSIGNED NULL,
            action VARCHAR(50) NOT NULL DEFAULT 'view',
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dal_user (user_id),
            INDEX idx_dal_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // NIS2: session tracking
        "CREATE TABLE IF NOT EXISTS active_sessions (
            id VARCHAR(64) PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_as_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ip_bans (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            reason     VARCHAR(255) NULL,
            banned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            UNIQUE KEY uq_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS totp_backup_codes (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            code_hash  VARCHAR(64) NOT NULL,
            used_at    DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tbc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS entry_export_templates (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL,
            description VARCHAR(255) NULL,
            logo_path   VARCHAR(500) NULL,
            header_html TEXT NULL COMMENT 'HTML for page header',
            footer_html TEXT NULL COMMENT 'HTML for page footer',
            primary_color VARCHAR(20) NOT NULL DEFAULT '#1e3a5f',
            accent_color  VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
            font_family   VARCHAR(100) NOT NULL DEFAULT 'Arial, sans-serif',
            default_fields JSON NULL COMMENT 'default field visibility',
            is_default  TINYINT(1) NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "INSERT IGNORE INTO entry_export_templates (id,name,primary_color,accent_color,is_default) VALUES (1,'Default','#1e3a5f','#3b82f6',1)",
        "CREATE TABLE IF NOT EXISTS user_feedback_attachments (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT UNSIGNED NOT NULL,
            filename    VARCHAR(300) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path   VARCHAR(500) NOT NULL,
            mime_type   VARCHAR(100) NOT NULL DEFAULT '',
            file_size   INT UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ufa_fb FOREIGN KEY (feedback_id) REFERENCES user_feedback(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS user_feedback_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            comment TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ufc_fb FOREIGN KEY (feedback_id) REFERENCES user_feedback(id) ON DELETE CASCADE,
            CONSTRAINT fk_ufc_u  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Performance indexes
        "CREATE INDEX idx_entries_date_id ON entries (entry_date, id)",
        "CREATE INDEX idx_att_entry_mime ON entry_attachments (entry_id, mime_type(20))",
        "CREATE INDEX idx_comments_entry ON entry_comments (entry_id)",
        // -- Quick Capture (public, no-login draft submissions) ----------
        "CREATE TABLE IF NOT EXISTS quick_captures (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_hint VARCHAR(255) NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT NULL,
            reporter_name VARCHAR(150) NULL,
            reporter_contact VARCHAR(200) NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            entry_id INT UNSIGNED NULL,
            reviewed_by INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            ip_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qc_status (status),
            INDEX idx_qc_ip (ip_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS quick_capture_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            capture_id INT UNSIGNED NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(100) NULL,
            file_size INT UNSIGNED NULL,
            file_path VARCHAR(500) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qcf_capture (capture_id),
            CONSTRAINT fk_qcf_capture FOREIGN KEY (capture_id) REFERENCES quick_captures(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE user_presets ADD COLUMN IF NOT EXISTS is_default TINYINT(1) NOT NULL DEFAULT 0",
        // Kanban Lane View
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS kanban_lane VARCHAR(30) NULL DEFAULT NULL",
        // Xray Test Management integration tables
        "CREATE TABLE IF NOT EXISTS xray_test_plans (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            jira_key        VARCHAR(50)  NOT NULL,
            jira_id         VARCHAR(50)  NOT NULL,
            summary         VARCHAR(500) NOT NULL,
            description     TEXT         NULL,
            status          VARCHAR(50)  NULL,
            robodoc_plan_id INT UNSIGNED NULL,
            synced_at       DATETIME     NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_xray_plan_key (jira_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS xray_test_executions (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            jira_key        VARCHAR(50)  NOT NULL,
            jira_id         VARCHAR(50)  NOT NULL,
            summary         VARCHAR(500) NOT NULL,
            description     TEXT         NULL,
            status          VARCHAR(50)  NULL,
            test_plan_key   VARCHAR(50)  NULL,
            robodoc_run_id  INT UNSIGNED NULL,
            synced_at       DATETIME     NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_xray_exec_key (jira_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS xray_tests (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            jira_key        VARCHAR(50)  NOT NULL,
            jira_id         VARCHAR(50)  NOT NULL,
            summary         VARCHAR(500) NOT NULL,
            test_type       VARCHAR(50)  NULL,
            status          VARCHAR(50)  NULL,
            priority        VARCHAR(50)  NULL,
            synced_at       DATETIME     NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_xray_test_key (jira_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS test_case_steps (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, test_plan_item_id INT UNSIGNED NOT NULL, step_number INT UNSIGNED NOT NULL DEFAULT 1, step_action TEXT NULL, test_data TEXT NULL, expected_result TEXT NULL, synapse_step_id VARCHAR(50) NULL, synapse_synced_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_tcs_item FOREIGN KEY (test_plan_item_id) REFERENCES test_plan_items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Test Steps CRUD
        "ALTER TABLE test_case_steps ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE test_case_steps ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
        // Test Case status sync with SynapseRT
        "ALTER TABLE test_plan_items ADD COLUMN IF NOT EXISTS synapse_status VARCHAR(50) NULL DEFAULT NULL",
        // Tester assignment on test run results
        "ALTER TABLE test_run_results ADD COLUMN IF NOT EXISTS assigned_tester INT UNSIGNED NULL DEFAULT NULL",
        // Bug links on test run results: entry_id = RoboDoc entry, jira_key = linked Jira ticket
        "CREATE TABLE IF NOT EXISTS test_run_bugs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, test_run_result_id INT UNSIGNED NOT NULL, entry_id INT UNSIGNED NULL, jira_key VARCHAR(50) NULL, synapse_synced_at DATETIME NULL, created_by INT UNSIGNED NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_trb_result FOREIGN KEY (test_run_result_id) REFERENCES test_run_results(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE test_case_steps ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE test_plan_items ADD COLUMN IF NOT EXISTS synapse_status VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_run_results ADD COLUMN IF NOT EXISTS assigned_tester INT UNSIGNED NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS test_run_bugs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, test_run_result_id INT UNSIGNED NOT NULL, entry_id INT UNSIGNED NULL, jira_key VARCHAR(50) NULL, synapse_synced_at DATETIME NULL, created_by INT UNSIGNED NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_trb_result FOREIGN KEY (test_run_result_id) REFERENCES test_run_results(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS user_project_filters (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL UNIQUE, project_ids TEXT NOT NULL DEFAULT '[]', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT fk_upf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Tags
        "CREATE TABLE IF NOT EXISTS tags (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, color VARCHAR(20) NOT NULL DEFAULT '#6c757d', created_by INT UNSIGNED NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE tags ADD CONSTRAINT IF NOT EXISTS uq_tag_name UNIQUE (name)",
        "CREATE TABLE IF NOT EXISTS entry_tags (entry_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (entry_id, tag_id), CONSTRAINT fk_et_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE, CONSTRAINT fk_et_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Tag Kanban: personal buckets
        "CREATE TABLE IF NOT EXISTS tag_kanban_buckets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL, color VARCHAR(20) NOT NULL DEFAULT '#6c757d', sort_order INT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_tkb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS tag_kanban_bucket_tags (bucket_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, PRIMARY KEY (bucket_id, tag_id), CONSTRAINT fk_tkbt_bucket FOREIGN KEY (bucket_id) REFERENCES tag_kanban_buckets(id) ON DELETE CASCADE, CONSTRAINT fk_tkbt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS merged_into_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS is_merged TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS merged_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS merged_by INT UNSIGNED NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS entry_sharepoint_files (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, entry_id INT UNSIGNED NOT NULL, attachment_id INT UNSIGNED NULL, filename VARCHAR(255) NOT NULL, web_url VARCHAR(1000) NOT NULL, uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_esf_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE entry_attachments ADD COLUMN IF NOT EXISTS compress_pending TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE entry_attachments ADD COLUMN IF NOT EXISTS compress_target_path VARCHAR(500) NULL DEFAULT NULL",
        // User Feedback system
        "CREATE TABLE IF NOT EXISTS user_feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type ENUM('bug','improvement','question','other') NOT NULL DEFAULT 'bug',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('open','todo','done','rejected') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_uf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Security tables
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_verified_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL DEFAULT NULL",
        // NIS2 compliance: enhanced audit log
        "ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255) NULL DEFAULT NULL",
        "ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS session_id VARCHAR(64) NULL DEFAULT NULL",
        // NIS2: data retention — audit log retention policy (keep 2 years)
        "CREATE EVENT IF NOT EXISTS purge_old_audit_logs
            ON SCHEDULE EVERY 1 DAY DO
            DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 730 DAY)",
        // NIS2: access log for sensitive data
        // BSI: password change tracking
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0",
        "CREATE TABLE IF NOT EXISTS data_access_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            resource_type VARCHAR(50) NOT NULL,
            resource_id INT UNSIGNED NULL,
            action VARCHAR(50) NOT NULL DEFAULT 'view',
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dal_user (user_id),
            INDEX idx_dal_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // NIS2: session tracking
        "CREATE TABLE IF NOT EXISTS active_sessions (
            id VARCHAR(64) PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_as_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ip_bans (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            reason     VARCHAR(255) NULL,
            banned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            UNIQUE KEY uq_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS totp_backup_codes (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            code_hash  VARCHAR(64) NOT NULL,
            used_at    DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tbc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS entry_export_templates (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL,
            description VARCHAR(255) NULL,
            logo_path   VARCHAR(500) NULL,
            header_html TEXT NULL COMMENT 'HTML for page header',
            footer_html TEXT NULL COMMENT 'HTML for page footer',
            primary_color VARCHAR(20) NOT NULL DEFAULT '#1e3a5f',
            accent_color  VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
            font_family   VARCHAR(100) NOT NULL DEFAULT 'Arial, sans-serif',
            default_fields JSON NULL COMMENT 'default field visibility',
            is_default  TINYINT(1) NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "INSERT IGNORE INTO entry_export_templates (id,name,primary_color,accent_color,is_default) VALUES (1,'Default','#1e3a5f','#3b82f6',1)",
        "CREATE TABLE IF NOT EXISTS user_feedback_attachments (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT UNSIGNED NOT NULL,
            filename    VARCHAR(300) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path   VARCHAR(500) NOT NULL,
            mime_type   VARCHAR(100) NOT NULL DEFAULT '',
            file_size   INT UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ufa_fb FOREIGN KEY (feedback_id) REFERENCES user_feedback(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS user_feedback_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            comment TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ufc_fb FOREIGN KEY (feedback_id) REFERENCES user_feedback(id) ON DELETE CASCADE,
            CONSTRAINT fk_ufc_u  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Performance indexes for common queries
        "CREATE INDEX IF NOT EXISTS idx_tpi_plan ON test_plan_items(test_plan_id)",
        "CREATE INDEX IF NOT EXISTS idx_tr_cycle ON test_runs(test_cycle_id)",
        "CREATE INDEX IF NOT EXISTS idx_tr_plan ON test_runs(test_plan_id)",
        "CREATE INDEX IF NOT EXISTS idx_trr_run ON test_run_results(test_run_id)",
        "CREATE INDEX IF NOT EXISTS idx_tcs_item ON test_case_steps(test_plan_item_id)",
        "CREATE INDEX IF NOT EXISTS idx_tc_plan ON test_cycles(test_plan_id)",
        "CREATE INDEX IF NOT EXISTS idx_entries_status ON entries(status)",
        "CREATE INDEX IF NOT EXISTS idx_entries_project ON entries(project_id)",
        // xray_test_results removed - results are fetched live from SynapseRT API
        // SynapseRT sync columns on existing test management tables
        "ALTER TABLE test_plan_items ADD COLUMN IF NOT EXISTS synapse_key VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_plan_items ADD COLUMN IF NOT EXISTS synapse_synced_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE test_runs ADD COLUMN IF NOT EXISTS synapse_plan_key VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_runs ADD COLUMN IF NOT EXISTS synapse_cycle_id VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_runs ADD COLUMN IF NOT EXISTS synapse_synced_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE test_run_results ADD COLUMN IF NOT EXISTS synapse_status VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_run_results ADD COLUMN IF NOT EXISTS synapse_synced_at DATETIME NULL DEFAULT NULL",
        "CREATE TABLE IF NOT EXISTS synapse_test_request_links (id INT UNSIGNED NOT NULL AUTO_INCREMENT, test_request_id INT UNSIGNED NOT NULL, synapse_plan_key VARCHAR(50) NOT NULL, synapse_cycle_id VARCHAR(50) NOT NULL, synapse_test_case_key VARCHAR(50) NOT NULL, synapse_test_case_name VARCHAR(500) NOT NULL DEFAULT '', created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_str_link (test_request_id, synapse_cycle_id, synapse_test_case_key), CONSTRAINT fk_str_req FOREIGN KEY (test_request_id) REFERENCES test_requests(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS xray_entry_links (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id     INT UNSIGNED NOT NULL,
            jira_key     VARCHAR(50)  NOT NULL,
            link_type    VARCHAR(50)  NOT NULL DEFAULT 'test',
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_xray_entry_link (entry_id, jira_key),
            CONSTRAINT fk_xray_link_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Xray columns on existing robodoc tables
        "ALTER TABLE test_plans ADD COLUMN IF NOT EXISTS xray_key VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_plans ADD COLUMN IF NOT EXISTS xray_synced_at DATETIME NULL DEFAULT NULL",
        "ALTER TABLE test_runs  ADD COLUMN IF NOT EXISTS xray_key VARCHAR(50) NULL DEFAULT NULL",
        "ALTER TABLE test_runs  ADD COLUMN IF NOT EXISTS xray_synced_at DATETIME NULL DEFAULT NULL",
        // Xray settings
        "INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('xray_project_key', 'BRSQ'), ('xray_sync_enabled', '1')",
        // Add can_own column to permission tables (if not exists)
        "ALTER TABLE user_group_permissions ADD COLUMN IF NOT EXISTS can_own TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE user_permissions ADD COLUMN IF NOT EXISTS can_own TINYINT(1) NOT NULL DEFAULT 0",
        // Per-user direct module permissions (overrides group permissions)
        "CREATE TABLE IF NOT EXISTS user_permissions (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    INT UNSIGNED NOT NULL,
            module     VARCHAR(50)  NOT NULL,
            can_view   TINYINT(1)   NOT NULL DEFAULT 1,
            can_edit   TINYINT(1)   NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uq_up (user_id, module),
            CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Module permissions per group
        "CREATE TABLE IF NOT EXISTS user_group_permissions (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id   INT UNSIGNED NOT NULL,
            module     VARCHAR(50)  NOT NULL,
            can_view   TINYINT(1)   NOT NULL DEFAULT 1,
            can_edit   TINYINT(1)   NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ugp (group_id, module),
            CONSTRAINT fk_ugp_group FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // assigned_to on quick_captures (assign to a specific user)
        "ALTER TABLE quick_captures ADD COLUMN IF NOT EXISTS assigned_to INT UNSIGNED NULL DEFAULT NULL",
        // Kanban private notes (per user, per entry)
        // Test Sessions: link to test cycle + test case
        "ALTER TABLE test_sessions ADD COLUMN IF NOT EXISTS test_cycle_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE test_sessions ADD COLUMN IF NOT EXISTS test_plan_item_id INT UNSIGNED NULL DEFAULT NULL",
        // Test Result Entry: sub-results table

        "CREATE TABLE IF NOT EXISTS entry_test_results (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id        INT UNSIGNED NOT NULL COMMENT 'parent test result entry',
            sort_order      SMALLINT     NOT NULL DEFAULT 0,
            test_setup      TEXT         NULL,
            test_doc        TEXT         NULL,
            test_result     VARCHAR(100) NULL,
            mower_serial    VARCHAR(100) NULL,
            notes           TEXT         NULL,
            created_by      INT UNSIGNED NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_etr_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Test Result Entry: link to test cycle + test case
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS test_cycle_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS test_plan_item_id_ref INT UNSIGNED NULL DEFAULT NULL COMMENT 'linked test case'",
        // Test Result outcome values (configurable via settings)
        "INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('test_result_outcomes', 'Passed,Failed,Blocked,Partial,Not Run')",
        // Test Result entry type IDs (configurable via settings)
        "INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('test_result_entry_type_ids', '')",
        "CREATE TABLE IF NOT EXISTS kanban_notes (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id   INT UNSIGNED NOT NULL,
            user_id    INT UNSIGNED NOT NULL,
            note       TEXT         NOT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_kanban_note (entry_id, user_id),
            CONSTRAINT fk_kn_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_kn_user  FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ── 8D-Reports (Problemlösungsprozess: D1-D8, 5-Why, Ishikawa) ─────────
        "CREATE TABLE IF NOT EXISTS eight_d_reports (
            id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference               VARCHAR(30)  NOT NULL,
            title                   VARCHAR(255) NOT NULL,
            project_id              INT UNSIGNED NULL,
            entry_id                INT UNSIGNED NULL,
            status                  ENUM('open','closed') NOT NULL DEFAULT 'open',
            -- D1: Team
            d1_champion             VARCHAR(150) NULL,
            -- D2: Problembeschreibung
            d2_problem_description  TEXT NULL,
            d2_is_is_not            JSON NULL,
            -- D4: Ursachenanalyse (5-Why + Ishikawa)
            d4_five_why             JSON NULL,
            d4_ishikawa             JSON NULL,
            d4_root_cause           TEXT NULL,
            d4_escape_point         TEXT NULL,
            -- D5: Auswahl dauerhafte Korrekturmaßnahme
            d5_selected_solution    TEXT NULL,
            -- D6: Umsetzung & Validierung
            d6_validation           TEXT NULL,
            -- D7: Systemische Vorbeugung
            d7_systemic_actions     TEXT NULL,
            -- D8: Abschluss
            d8_team_recognition     TEXT NULL,
            d8_closed_at            DATETIME NULL,
            created_by              INT UNSIGNED NOT NULL,
            created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_8d_reference (reference),
            INDEX idx_8d_status (status),
            INDEX idx_8d_project (project_id),
            CONSTRAINT fk_8d_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            CONSTRAINT fk_8d_entry   FOREIGN KEY (entry_id)   REFERENCES entries(id)  ON DELETE SET NULL,
            CONSTRAINT fk_8d_creator FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // D1: Team-Mitglieder
        "CREATE TABLE IF NOT EXISTS eight_d_team_members (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id   INT UNSIGNED NOT NULL,
            name        VARCHAR(150) NOT NULL,
            role        VARCHAR(150) NULL,
            department  VARCHAR(150) NULL,
            sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
            CONSTRAINT fk_8dtm_report FOREIGN KEY (report_id) REFERENCES eight_d_reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // D3 (Sofortmaßnahmen) / D5 (Korrekturmaßnahmen) / D6 (Umsetzung) / D7 (Vorbeugung)
        "CREATE TABLE IF NOT EXISTS eight_d_actions (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id     INT UNSIGNED NOT NULL,
            discipline    ENUM('d3','d5','d6','d7') NOT NULL,
            description   TEXT NOT NULL,
            responsible   VARCHAR(150) NULL,
            due_date      DATE NULL,
            status        ENUM('open','in_progress','done','verified') NOT NULL DEFAULT 'open',
            verification  TEXT NULL,
            completed_at  DATETIME NULL,
            sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_8da_report_discipline (report_id, discipline),
            CONSTRAINT fk_8da_report FOREIGN KEY (report_id) REFERENCES eight_d_reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ── 8D: D0 (Sofortreaktion), Anhänge/Fotos je Abschnitt, Nutzer-Verantwortliche ──
        "ALTER TABLE eight_d_reports ADD COLUMN IF NOT EXISTS d0_symptom TEXT NULL AFTER status",
        "ALTER TABLE eight_d_reports ADD COLUMN IF NOT EXISTS d0_emergency_response TEXT NULL AFTER d0_symptom",
        // Nullable, no FK constraint (same lightweight pattern as entries.assigned_to) — a
        // report may name a responsible person who has no RoboDoc2 account, or intentionally
        // stays free text (external contact, supplier, etc.).
        "ALTER TABLE eight_d_actions ADD COLUMN IF NOT EXISTS responsible_user_id INT UNSIGNED NULL AFTER responsible",
        "CREATE TABLE IF NOT EXISTS eight_d_attachments (
            id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id      INT UNSIGNED NOT NULL,
            discipline     ENUM('d2','d3','d4','d6') NOT NULL,
            filename       VARCHAR(255) NOT NULL,
            original_name  VARCHAR(255) NOT NULL,
            mime_type      VARCHAR(100) NULL,
            file_size      INT UNSIGNED NULL,
            file_path      VARCHAR(500) NOT NULL,
            uploaded_by    INT UNSIGNED NULL,
            created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_8datt_report_disc (report_id, discipline),
            CONSTRAINT fk_8datt_report FOREIGN KEY (report_id) REFERENCES eight_d_reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Live-Sync: push newly created entries to another RoboDoc2 instance
        "ALTER TABLE entries ADD COLUMN IF NOT EXISTS live_origin_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'entries.id on the sending instance, for idempotent re-sends'",
        "ALTER TABLE entries ADD UNIQUE INDEX IF NOT EXISTS uq_entries_live_origin (live_origin_id)",
        "CREATE TABLE IF NOT EXISTS live_sync_queue (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id   INT UNSIGNED NOT NULL,
            status     ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            attempts   INT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            sent_at    DATETIME NULL,
            INDEX idx_lsq_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS live_sync_rate_log (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            kind       ENUM('request','auth_fail') NOT NULL DEFAULT 'request',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lsrl_ip_kind_time (ip_address, kind, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

function runMigrations(): void {
    $pdo = Database::get();
    foreach (getMigrations() as $sql) {
        try { $pdo->exec($sql); } catch (\Throwable) {}
    }
}

function runMigrationsReport(): array {
    $pdo     = Database::get();
    $results = [];
    foreach (getMigrations() as $sql) {
        $label = preg_match('/^(ALTER TABLE \S+|CREATE TABLE IF NOT EXISTS \S+)/i', $sql, $m) ? $m[0] : substr($sql, 0, 60);
        try {
            $pdo->exec($sql);
            $results[] = ['label' => $label, 'ok' => true];
        } catch (\Throwable $e) {
            $results[] = ['label' => $label, 'ok' => false, 'error' => $e->getMessage()];
        }
    }
    return $results;
}


    // ── Migration: Testkunden Feature ──────────────────────────────────────
    Database::execute("CREATE TABLE IF NOT EXISTS test_customer_orders (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id      INT UNSIGNED NOT NULL,
        title           VARCHAR(200) NOT NULL,
        description     TEXT,
        status          ENUM('active','closed','draft') NOT NULL DEFAULT 'active',
        qr_token        VARCHAR(64) NOT NULL UNIQUE,
        created_by      INT UNSIGNED,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (project_id), INDEX (qr_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    Database::execute("CREATE TABLE IF NOT EXISTS test_customer_feedback (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id        INT UNSIGNED NOT NULL,
        respondent_name VARCHAR(150),
        respondent_contact VARCHAR(200),
        mower_serial    VARCHAR(100),
        firmware_version VARCHAR(50),
        title           VARCHAR(200) NOT NULL,
        description     TEXT,
        rating          TINYINT UNSIGNED,
        status          ENUM('pending','reviewed','imported') NOT NULL DEFAULT 'pending',
        ip_hash         VARCHAR(64),
        entry_id        INT UNSIGNED,
        reviewed_by     INT UNSIGNED,
        reviewed_at     DATETIME,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (order_id), INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    Database::execute("CREATE TABLE IF NOT EXISTS questionnaire_templates (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title           VARCHAR(200) NOT NULL,
        description     TEXT,
        questions       JSON NOT NULL,
        created_by      INT UNSIGNED,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    Database::execute("CREATE TABLE IF NOT EXISTS questionnaires (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id        INT UNSIGNED NOT NULL,
        title           VARCHAR(200) NOT NULL,
        description     TEXT,
        questions       JSON NOT NULL,
        qr_token        VARCHAR(64) NOT NULL UNIQUE,
        status          ENUM('active','closed') NOT NULL DEFAULT 'active',
        created_by      INT UNSIGNED,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (order_id), INDEX (qr_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    Database::execute("CREATE TABLE IF NOT EXISTS questionnaire_responses (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        questionnaire_id INT UNSIGNED NOT NULL,
        respondent_name VARCHAR(150),
        respondent_contact VARCHAR(200),
        answers         JSON NOT NULL,
        ip_hash         VARCHAR(64),
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (questionnaire_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // -- Migration: test_customer_respondents table
    Database::execute("CREATE TABLE IF NOT EXISTS test_customer_respondents (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id        INT UNSIGNED NOT NULL,
        label           VARCHAR(150) NOT NULL COMMENT 'Internal name/label',
        customer_number VARCHAR(50)  NOT NULL COMMENT 'Testkunden-Nr.',
        token           VARCHAR(64)  NOT NULL UNIQUE,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (order_id), INDEX (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // -- Migration: central test_customers table
    Database::execute("CREATE TABLE IF NOT EXISTS test_customers (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_number VARCHAR(50)  NOT NULL,
        label           VARCHAR(150) NOT NULL,
        email           VARCHAR(200) NULL,
        notes           TEXT NULL,
        created_by      INT UNSIGNED,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (customer_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // -- Migration: link respondents to central test_customers
    if (Database::fetchAll("SHOW TABLES LIKE 'test_customer_respondents'")) {
        if (empty(Database::fetchAll("SHOW COLUMNS FROM test_customer_respondents LIKE 'test_customer_id'"))) {
            Database::execute("ALTER TABLE test_customer_respondents ADD COLUMN test_customer_id INT UNSIGNED NULL AFTER order_id");
        }
    }

    // -- Migration: report_templates table
    Database::execute("CREATE TABLE IF NOT EXISTS report_templates (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(150) NOT NULL,
        description TEXT,
        config      JSON NOT NULL COMMENT 'Full report config: header, footer, blocks, branding',
        is_default  TINYINT(1) DEFAULT 0,
        created_by  INT UNSIGNED,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // -- Migration: add 'rejected' to test_customer_feedback status ENUM
    if (Database::fetchAll("SHOW TABLES LIKE 'test_customer_feedback'")) {
        Database::execute("ALTER TABLE test_customer_feedback MODIFY COLUMN status ENUM('pending','reviewed','imported','rejected') NOT NULL DEFAULT 'pending'");
        Database::execute("UPDATE test_customer_feedback SET status='rejected' WHERE status='reviewed'");
    }

    // -- Migration: rename reviewed->rejected in test_customer_feedback (legacy)
    if (Database::fetchAll("SHOW TABLES LIKE 'test_customer_feedback'")) {
        Database::execute("UPDATE test_customer_feedback SET status='rejected' WHERE status='reviewed'");
    }

    // -- Migration: email on test_customer_respondents
    if (Database::fetchAll("SHOW TABLES LIKE 'test_customer_respondents'")) {
        if (empty(Database::fetchAll("SHOW COLUMNS FROM test_customer_respondents LIKE 'email'"))) {
            Database::execute("ALTER TABLE test_customer_respondents ADD COLUMN email VARCHAR(200) NULL AFTER customer_number");
        }
    }

    // -- Migration: respondent_id on test_customer_feedback
    if (Database::fetchAll("SHOW TABLES LIKE 'test_customer_feedback'")) {
        if (empty(Database::fetchAll("SHOW COLUMNS FROM test_customer_feedback LIKE 'respondent_id'"))) {
            Database::execute("ALTER TABLE test_customer_feedback ADD COLUMN respondent_id INT UNSIGNED NULL AFTER order_id");
        }
    }

    // -- Migration: draft_mode on questionnaires
    if (Database::fetchAll("SHOW TABLES LIKE 'questionnaires'")) {
        if (empty(Database::fetchAll("SHOW COLUMNS FROM questionnaires LIKE 'draft_mode'"))) {
            Database::execute("ALTER TABLE questionnaires ADD COLUMN draft_mode TINYINT(1) NOT NULL DEFAULT 1 AFTER status");
        }
    }

    // -- Migration: feedback_instructions on test_customer_orders
    if (Database::fetchAll("SHOW TABLES LIKE 'test_customer_orders'")) {
        if (empty(Database::fetchAll("SHOW COLUMNS FROM test_customer_orders LIKE 'feedback_instructions'"))) {
            Database::execute("ALTER TABLE test_customer_orders ADD COLUMN feedback_instructions TEXT NULL AFTER description");
        }
    }

    // -- Migration: mower_serial + firmware_version on quick_captures
    if (Database::fetchAll("SHOW TABLES LIKE 'quick_captures'")) {
        foreach (['mower_serial VARCHAR(100) NULL', 'firmware_version VARCHAR(50) NULL'] as $__col) {
            $__cname = explode(' ', $__col)[0];
            if (empty(Database::fetchAll("SHOW COLUMNS FROM quick_captures LIKE '" . $__cname . "'"))) {
                Database::execute("ALTER TABLE quick_captures ADD COLUMN " . $__col);
            }
        }
    }

    // -- Migration: report_schedules table (automatischer Report-Versand)
    Database::execute("CREATE TABLE IF NOT EXISTS report_schedules (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        template_id   INT UNSIGNED NOT NULL,
        name          VARCHAR(150) NOT NULL,
        recipients    TEXT NOT NULL COMMENT 'Comma-separated email addresses',
        frequency     ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
        day_of_week   TINYINT UNSIGNED NULL COMMENT '0=Sunday..6=Saturday, for weekly',
        day_of_month  TINYINT UNSIGNED NULL COMMENT '1-28, for monthly',
        time_of_day   TIME NOT NULL DEFAULT '08:00:00',
        period_mode   ENUM('all','last_n_days') NOT NULL DEFAULT 'last_n_days',
        period_days   INT UNSIGNED NULL DEFAULT 7,
        project_id    INT UNSIGNED NULL,
        type_ids      VARCHAR(200) NULL COMMENT 'comma-separated entry_type ids',
        is_active     TINYINT(1) NOT NULL DEFAULT 1,
        last_sent_at  DATETIME NULL,
        created_by    INT UNSIGNED,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (template_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
