<?php
declare(strict_types=1);

class Auth {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure cookie flags
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || ($_SERVER['SERVER_PORT'] ?? 80) == 443
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            // Sessions valid for 8 hours of inactivity — NIS2 requirement.
            // Set gc_maxlifetime BEFORE session_start so the GC never collects
            // active session files. ini_set here affects the current process;
            // we also store sessions in a private directory so the server-wide
            // GC (often only 24 min on shared hosts) cannot touch our files.
            $lifetime = 60 * 60 * 8; // 8 hours — shorter for security
            ini_set('session.gc_maxlifetime', (string)$lifetime);

            // Use a private session directory so the system GC cannot interfere.
            $sessionDir = sys_get_temp_dir() . '/robodoc2_sessions';
            if (!is_dir($sessionDir)) {
                @mkdir($sessionDir, 0700, true);
            }
            if (is_dir($sessionDir) && is_writable($sessionDir)) {
                session_save_path($sessionDir);
            }

            session_name('robodoc2_session');
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();

            // Inactivity timeout: 30 days
            $idleTimeout = 60 * 60 * 24 * 30;
            if (isset($_SESSION['_last_active']) && (time() - $_SESSION['_last_active']) > $idleTimeout) {
                session_unset();
                session_destroy();
                session_start();
            }
            $_SESSION['_last_active'] = time();
        }
    }

    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    public static function isAdmin(): bool {
        return ($_SESSION['user']['role'] ?? '') === 'admin';
    }

    public static function check(): bool {
        return isset($_SESSION['user']['id']);
    }

    public static function require(): void {
        // NIS2: check session inactivity timeout (8 hours)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 28800) {
            try { self::untrackSession(); } catch (Throwable) {}
            self::logout();
            flash('error', 'Your session has expired due to inactivity. Please log in again.');
            redirect('/login');
        }
        if (self::check()) $_SESSION['last_activity'] = time();
        if (!self::check()) {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
            // Strip BASE_URL prefix so redirect() doesn't double-prepend it
            if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($uri, BASE_URL)) {
                $uri = substr($uri, strlen(BASE_URL));
            }
            $uri = '/' . ltrim($uri, '/');
            redirect('/login?next=' . urlencode($uri));
        }
    }

    public static function requireAdmin(): void {
        self::require();
        if (!self::isAdmin()) {
            abort(403, 'Zugriff verweigert');
        }
    }

    public static function login(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
    }

    // Returns null = no restriction (admin or ungrouped user).
    // Returns ['all' => int[], 'own' => int[]] where 'all' projects show every non-private
    // entry and 'own' projects show only the user's own entries. A project in both buckets
    // (via different groups) is promoted to 'all'.
    public static function groupAccess(): ?array {
        if (self::isAdmin()) return null;
        static $cache = [];
        $userId = self::id();
        if (array_key_exists($userId, $cache)) return $cache[$userId];

        $memberCount = (int)(Database::fetchOne(
            'SELECT COUNT(*) c FROM user_group_members WHERE user_id = ?', [$userId]
        )['c'] ?? 0);

        if ($memberCount === 0) return $cache[$userId] = null;

        $rows = Database::fetchAll(
            'SELECT ugp.project_id, ugp.entry_visibility
             FROM user_group_projects ugp
             JOIN user_group_members ugm ON ugm.group_id = ugp.group_id
             WHERE ugm.user_id = ?',
            [$userId]
        );

        $allIds = [];
        $ownIds = [];
        foreach ($rows as $row) {
            if ($row['entry_visibility'] === 'own') {
                $ownIds[] = (int)$row['project_id'];
            } else {
                $allIds[] = (int)$row['project_id'];
            }
        }
        $allIds  = array_values(array_unique($allIds));
        $ownOnly = array_values(array_diff(array_unique($ownIds), $allIds));

        return $cache[$userId] = ['all' => $allIds, 'own' => $ownOnly];
    }

    // Convenience: flat list of all project IDs the user may access (any visibility).
    public static function groupProjectIds(): ?array {
        $access = self::groupAccess();
        if ($access === null) return null;
        return array_values(array_unique(array_merge($access['all'], $access['own'])));
    }

    // ── Module permissions ───────────────────────────────────────

    // Complete list of all controllable modules.
    // slug => display label (used in admin UI and permission checks).
    public static function allModules(): array {
        return [
            'entries'       => 'Einträge',
            'quick_capture' => 'Quick Capture',
            'kanban'        => 'Kanban Board',
            'kanban_notes'  => 'Kanban Notizen',
            'tags'          => 'Tags',
            'entry_comments'=> 'Eintrag Kommentare',
            'sprint'        => 'Sprint Planung',
            'projects'      => 'Projekte',
            'reports'       => 'Berichte & Statistiken',
            'export'        => 'Export (Excel/PDF/CSV)',
            'testing'       => 'Test Pläne, Runs & Sessions',
            'synapse'       => 'SynapseRT Integration',
            'test_requests' => 'Test Requests',
            'test_areas'    => 'Test Areas',
            'requirements'  => 'Requirements',
            'inventory'     => 'Inventar & Mäher',
            'confluence'    => 'Confluence Export',
            'todos'         => 'Todos',
            'search'        => 'Suche',
            'eight_d'       => '8D-Berichte',
        ];
    }

    // Resolve effective module permissions for the current user.
    // Priority: user-direct overrides > group permissions > default (full access).
    // Returns: null = no restrictions (admin), or array module => ['view'=>bool,'edit'=>bool]
    private static function modulePerms(): ?array {
        if (self::isAdmin()) return null;
        static $cache = [];
        $userId = self::id();
        if (!$userId) return [];
        if (array_key_exists($userId, $cache)) return $cache[$userId];

        // ── User-direct permissions (highest priority) ────────────────
        $directRows = Database::fetchAll(
            'SELECT module, can_view, can_own, can_edit FROM user_permissions WHERE user_id=?',
            [$userId]
        );
        $directPerms = [];
        foreach ($directRows as $row) {
            $directPerms[$row['module']] = ['view' => (bool)$row['can_view'], 'own' => (bool)$row['can_own'], 'edit' => (bool)$row['can_edit']];
        }

        // ── Group permissions ─────────────────────────────────────────
        $groupIds = array_column(
            Database::fetchAll('SELECT group_id FROM user_group_members WHERE user_id=?', [$userId]),
            'group_id'
        );
        $groupPerms = [];
        if (!empty($groupIds)) {
            $ph   = implode(',', array_fill(0, count($groupIds), '?'));
            $rows = Database::fetchAll(
                "SELECT module, MAX(can_view) can_view, MAX(can_own) can_own, MAX(can_edit) can_edit
                 FROM user_group_permissions WHERE group_id IN ($ph) GROUP BY module",
                $groupIds
            );
            foreach ($rows as $row) {
                $groupPerms[$row['module']] = ['view' => (bool)$row['can_view'], 'own' => (bool)$row['can_own'], 'edit' => (bool)$row['can_edit']];
            }
        }

        // ── Determine effective permissions ───────────────────────────
        // If neither direct nor group perms exist → all modules denied (show warning in UI)
        if (empty($directPerms) && empty($groupPerms)) {
            $denied = [];
            foreach (array_keys(self::allModules()) as $m) {
                $denied[$m] = ['view' => false, 'own' => false, 'edit' => false];
            }
            return $cache[$userId] = $denied;
        }

        // Merge: start from group perms, user-direct overrides per module
        $modules = array_keys(self::allModules());
        $perms   = [];
        foreach ($modules as $module) {
            if (isset($directPerms[$module])) {
                $perms[$module] = $directPerms[$module];
            } elseif (isset($groupPerms[$module])) {
                $perms[$module] = $groupPerms[$module];
            } else {
                $perms[$module] = ['view' => false, 'own' => false, 'edit' => false];
            }
        }
        return $cache[$userId] = $perms;
    }

    // True if the user has view access to at least one module (used to detect "no rights" state)
    public static function hasAnyAccess(): bool {
        if (self::isAdmin()) return true;
        $perms = self::modulePerms();
        if ($perms === null) return true;
        foreach ($perms as $p) {
            if ($p['view']) return true;
        }
        return false;
    }

    public static function canView(string $module): bool {
        $perms = self::modulePerms();
        if ($perms === null) return true;
        return (bool)($perms[$module]['view'] ?? false);
    }

    // canOwn: user can create new entries and edit/delete their own + assigned entries
    // Implies canView. Does NOT grant access to other users' entries.
    public static function canOwn(string $module): bool {
        $perms = self::modulePerms();
        if ($perms === null) return true;
        return (bool)($perms[$module]['own'] ?? false);
    }

    public static function canEdit(string $module): bool {
        $perms = self::modulePerms();
        if ($perms === null) return true;
        return (bool)($perms[$module]['edit'] ?? false);
    }

    public static function requireView(string $module): void {
        self::require();
        if (!self::canView($module)) abort(403, 'Kein Zugriff auf Modul: ' . $module);
    }

    // Can the current user edit a specific entry?
    // Full edit rights if: admin, OR has requireEdit('entries') AND (created_by OR assigned_to).
    // If user only has canView('entries'), they can still edit their own / assigned entries.
    public static function canEditEntry(array $entry): bool {
        if (self::isAdmin()) return true;
        $userId = self::id();
        $isOwner = ((int)($entry['created_by'] ?? 0) === $userId)
                || ((int)($entry['assigned_to'] ?? 0) === $userId);
        // Full edit: can edit any entry
        if (self::canEdit('entries')) return true;
        // Own mode OR read-only: can only edit own/assigned entries
        if ((self::canOwn('entries') || self::canView('entries')) && $isOwner) return true;
        return false;
    }

    public static function requireEditEntry(array $entry): void {
        self::require();
        if (!self::canEditEntry($entry)) abort(403, 'Keine Bearbeitungsrechte für diesen Eintrag.');
    }

    public static function requireEdit(string $module): void {
        self::require();
        if (!self::canEdit($module)) abort(403, 'Keine Bearbeitungsrechte für Modul: ' . $module);
    }

    // Returns [sqlFragment, params] to restrict a projects query to allowed projects.
    // $alias: the table alias to prefix 'id' with, e.g. 'p' → 'p.id IN (...)'.
    public static function projectAccessClause(string $alias = ''): array {
        $ids = self::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ['0=1', []];
        $col = $alias ? "$alias.id" : 'id';
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        return ["$col IN ($ph)", $ids];
    }

    // Returns [sqlFragment, params] safe to AND into a WHERE clause for entry listings.
    // $alias is the entries table alias.
    public static function entryAccessClause(string $alias = 'e'): array {
        if (self::isAdmin()) return ['1=1', []];

        $userId = self::id();
        $access = self::groupAccess();

        if ($access === null) {
            return ["({$alias}.is_private = 0 OR {$alias}.created_by = ? OR {$alias}.assigned_to = ?)", [$userId, $userId]];
        }

        $allIds = $access['all'];
        $ownIds = $access['own'];

        if (empty($allIds) && empty($ownIds)) {
            return ['0=1', []];
        }

        $parts  = [];
        $params = [];

        if (!empty($allIds)) {
            $ph      = implode(',', array_fill(0, count($allIds), '?'));
            $parts[] = "({$alias}.project_id IN ($ph) AND ({$alias}.is_private = 0 OR {$alias}.created_by = ?))";
            $params  = array_merge($params, $allIds, [$userId]);
        }

        if (!empty($ownIds)) {
            $ph      = implode(',', array_fill(0, count($ownIds), '?'));
            $parts[] = "({$alias}.project_id IN ($ph) AND {$alias}.created_by = ?)";
            $params  = array_merge($params, $ownIds, [$userId]);
        }

        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    public static function canTestRequests(): bool {
        if (self::isAdmin()) return true;
        $userId = self::id();
        if (!$userId) return false;
        $u = Database::fetchOne('SELECT can_test_requests FROM users WHERE id=?', [$userId]);
        if (!empty($u['can_test_requests'])) return true;
        $row = Database::fetchOne(
            'SELECT 1 FROM user_group_members ugm
             JOIN user_groups ug ON ug.id = ugm.group_id
             WHERE ugm.user_id = ? AND ug.can_test_requests = 1 LIMIT 1',
            [$userId]
        );
        return (bool)$row;
    }

    public static function requireTestRequests(): void {
        self::require();
        if (!self::canTestRequests()) {
            abort(403, 'Access to Test Requests not granted.');
        }
    }

    // Global project filter — persisted in DB + cached in session
    public static function globalProjectFilter(): ?array
    {
        // null = show all projects (no filter)
        // [] = empty (show nothing — shouldn't happen normally)
        // [1,2,3] = only these project IDs
        if (isset($_SESSION['global_project_filter'])) {
            return $_SESSION['global_project_filter']; // null stored as 'all'
        }
        $userId = self::id();
        if (!$userId) return null;
        $row = Database::fetchOne('SELECT project_ids FROM user_project_filters WHERE user_id=?', [$userId]);
        if (!$row) { $_SESSION['global_project_filter'] = null; return null; }
        $ids = json_decode($row['project_ids'], true);
        $filter = is_array($ids) && count($ids) > 0 ? array_map('intval', $ids) : null;
        $_SESSION['global_project_filter'] = $filter;
        return $filter;
    }

    public static function setGlobalProjectFilter(?array $projectIds): void
    {
        $userId = self::id();
        if (!$userId) return;
        if ($projectIds === null || count($projectIds) === 0) {
            // Clear filter
            Database::execute('DELETE FROM user_project_filters WHERE user_id=?', [$userId]);
            $_SESSION['global_project_filter'] = null;
        } else {
            $ids = json_encode(array_values(array_unique(array_map('intval', $projectIds))));
            Database::execute('INSERT INTO user_project_filters (user_id, project_ids) VALUES (?,?) ON DUPLICATE KEY UPDATE project_ids=VALUES(project_ids), updated_at=NOW()', [$userId, $ids]);
            $_SESSION['global_project_filter'] = array_values(array_unique(array_map('intval', $projectIds)));
        }
    }

    // Returns SQL WHERE clause fragment + params for global project filter.
    // Returns ['1=1', []] if no filter is active.
    public static function globalFilterClause(string $alias = 'e'): array
    {
        $filter = self::globalProjectFilter();
        if ($filter === null) return ['1=1', []];
        if (empty($filter)) return ['0=1', []];
        $ph = implode(',', array_fill(0, count($filter), '?'));
        return ["{$alias}.project_id IN ($ph)", $filter];
    }

    public static function logout(): void {
        try { self::untrackSession(); } catch (Throwable) {}
        session_destroy();
    }

    public static function refreshUser(): void {
        $id = self::id();
        if ($id) {
            $user = Database::fetchOne('SELECT id, name, email, role FROM users WHERE id = ?', [$id]);
            if ($user) {
                $_SESSION['user'] = $user;
            }
        }
    }

    public static function flash(string $key, string $message): void {
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            abort(419, 'CSRF token mismatch');
        }
    }
}
