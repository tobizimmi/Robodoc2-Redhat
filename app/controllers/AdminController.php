<?php
declare(strict_types=1);

class AdminController {
    public static function index(): void {
        Auth::requireAdmin();
        $stats = [
            'users'      => (int)Database::fetchOne('SELECT COUNT(*) c FROM users')['c'],
            'entries'    => (int)Database::fetchOne('SELECT COUNT(*) c FROM entries')['c'],
            'projects'   => (int)Database::fetchOne('SELECT COUNT(*) c FROM projects')['c'],
            'test_plans' => (int)Database::fetchOne('SELECT COUNT(*) c FROM test_plans')['c'],
        ];
        View::render('admin/index', ['stats' => $stats, 'title' => 'Administration']);
    }

    // ── Users ─────────────────────────────────────────────────
    public static function users(): void {
        Auth::requireAdmin();
        $users   = Database::fetchAll('SELECT * FROM users WHERE status != ? ORDER BY name', ['pending']);
        $pending = Database::fetchAll('SELECT * FROM users WHERE status = ? ORDER BY created_at DESC', ['pending']);
        View::render('admin/users/index', compact('users', 'pending') + ['title' => 'User Management']);
    }

    public static function approveUser(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('UPDATE users SET status=? WHERE id=?', ['active', (int)$id]);
        flash('success', 'User approved.');
        redirect('/admin/users');
    }

    public static function rejectUser(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM users WHERE id=? AND status=?', [(int)$id, 'pending']);
        flash('success', 'Registration rejected and removed.');
        redirect('/admin/users');
    }

    public static function createUser(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $name   = trim($_POST['name'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $pw     = $_POST['password'] ?? '';
            $role   = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';
            if (!$name || !$email || !$pw) { flash('error', 'All fields are required.'); redirect('/admin/users/create'); }
            if (Database::fetchOne('SELECT id FROM users WHERE email=?', [$email])) {
                flash('error', 'Email already registered.'); redirect('/admin/users/create');
            }
            Database::insert(
                'INSERT INTO users (name, email, password_hash, role, status) VALUES (?,?,?,?,?)',
                [$name, $email, password_hash($pw, PASSWORD_BCRYPT), $role, $status]
            );
            flash('success', 'User created.');
            redirect('/admin/users');
        }
        View::render('admin/users/create', ['title' => 'New User']);
    }

    public static function editUser(string $id): void {
        Auth::requireAdmin();
        $user = Database::fetchOne('SELECT * FROM users WHERE id=?', [(int)$id]);
        if (!$user) abort(404);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $name      = trim($_POST['name'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $role      = $_POST['role']   ?? 'user';
            $newStatus = $_POST['status'] ?? 'active';
            $wasActive = $user['status'] === 'active';
            $canTR = !empty($_POST['can_test_requests']) ? 1 : 0;
            Database::execute('UPDATE users SET name=?, email=?, role=?, status=?, can_test_requests=? WHERE id=?', [$name, $email, $role, $newStatus, $canTR, (int)$id]);
            if (!empty($_POST['password'])) {
                Database::execute('UPDATE users SET password_hash=? WHERE id=?', [password_hash($_POST['password'], PASSWORD_BCRYPT), (int)$id]);
            }
            // Email notification when a pending/disabled user is approved
            if (!$wasActive && $newStatus === 'active') {
                $updated = Database::fetchOne('SELECT id, name, email FROM users WHERE id=?', [(int)$id]);
                if ($updated) Mailer::notifyAccountApproved($updated);
            }
            Audit::log('user_updated', 'user', (int)$id, "status=$newStatus role=$role");
            // If "clear individual perms" was requested, wipe them and don't re-save
            if (!empty($_POST['clear_user_perms']) && $_POST['clear_user_perms'] === '1') {
                Database::execute('DELETE FROM user_permissions WHERE user_id=?', [(int)$id]);
            } else {
                self::syncUserPerms((int)$id);
            }
            flash('success', 'User updated.');
            redirect('/admin/users');
        }
        $modules   = Auth::allModules();
        $permRows  = Database::fetchAll('SELECT module, can_view, can_own, can_edit FROM user_permissions WHERE user_id=?', [(int)$id]);
        $userPerms = [];
        foreach ($permRows as $pr) $userPerms[$pr['module']] = ['view' => (bool)$pr['can_view'], 'own' => (bool)$pr['can_own'], 'edit' => (bool)$pr['can_edit']];
        // Load which groups this user belongs to (for info display)
        $userGroups = Database::fetchAll(
            'SELECT g.name FROM user_groups g JOIN user_group_members ugm ON ugm.group_id=g.id WHERE ugm.user_id=?',
            [(int)$id]
        );
        View::render('admin/users/edit', compact('user', 'modules', 'userPerms', 'userGroups') + ['title' => 'Edit User']);
    }

    // POST /admin/users/:id/clear-perms — delete all individual module permissions
    public static function clearUserPerms(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM user_permissions WHERE user_id=?', [(int)$id]);
        flash('success', 'Individuelle Rechte wurden entfernt.');
        redirect('/admin/users/' . (int)$id . '/edit');
    }

    public static function deleteUser(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        if ((int)$id === Auth::id()) { flash('error', 'Cannot delete your own account.'); redirect('/admin/users'); }
        $user = Database::fetchOne('SELECT email FROM users WHERE id=?', [(int)$id]);
        Database::execute('DELETE FROM users WHERE id=?', [(int)$id]);
        Audit::log('user_deleted', 'user', (int)$id, $user['email'] ?? '');
        flash('success', 'User deleted.');
        redirect('/admin/users');
    }

    // ── Groups ────────────────────────────────────────────────
    public static function groups(): void {
        Auth::requireAdmin();
        $groups = Database::fetchAll('
            SELECT g.*,
                   COUNT(DISTINCT ugm.user_id)  member_count,
                   COUNT(DISTINCT ugp.project_id) project_count
            FROM user_groups g
            LEFT JOIN user_group_members  ugm ON ugm.group_id = g.id
            LEFT JOIN user_group_projects ugp ON ugp.group_id = g.id
            GROUP BY g.id ORDER BY g.name
        ');
        View::render('admin/groups/index', compact('groups') + ['title' => 'Access Groups']);
    }

    public static function createGroup(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $name = trim($_POST['name'] ?? '');
            if (!$name) { flash('error', 'Name is required.'); redirect('/admin/groups/create'); }
            $gid = Database::insert(
                'INSERT INTO user_groups (name, description, can_test_requests) VALUES (?,?,?)',
                [$name, trim($_POST['description'] ?? ''), !empty($_POST['can_test_requests']) ? 1 : 0]
            );
            self::syncGroupMembers((int)$gid);
            self::syncGroupPerms((int)$gid);
            flash('success', 'Group created.');
            redirect('/admin/groups');
        }
        $users    = Database::fetchAll('SELECT id, name FROM users WHERE status=? ORDER BY name', ['active']);
        $projects = Database::fetchAll('SELECT id, name FROM projects ORDER BY name');
        $modules  = Auth::allModules();
        $groupPerms = [];
        View::render('admin/groups/form', compact('users', 'projects', 'modules', 'groupPerms') + ['title' => 'New Group', 'group' => null, 'memberIds' => [], 'projectIds' => [], 'projectVis' => []]);
    }

    public static function editGroup(string $id): void {
        Auth::requireAdmin();
        $group = Database::fetchOne('SELECT * FROM user_groups WHERE id=?', [(int)$id]);
        if (!$group) abort(404);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $name = trim($_POST['name'] ?? '');
            if (!$name) { flash('error', 'Name is required.'); redirect('/admin/groups/' . $id . '/edit'); }
            Database::execute('UPDATE user_groups SET name=?, description=?, can_test_requests=? WHERE id=?', [$name, trim($_POST['description'] ?? ''), !empty($_POST['can_test_requests']) ? 1 : 0, (int)$id]);
            self::syncGroupMembers((int)$id);
            self::syncGroupPerms((int)$id);
            flash('success', 'Group updated.');
            redirect('/admin/groups');
        }
        $users      = Database::fetchAll('SELECT id, name FROM users WHERE status=? ORDER BY name', ['active']);
        $projects   = Database::fetchAll('SELECT id, name FROM projects ORDER BY name');
        $memberIds   = array_column(Database::fetchAll('SELECT user_id FROM user_group_members WHERE group_id=?', [(int)$id]), 'user_id');
        $projectRows = Database::fetchAll('SELECT project_id, entry_visibility FROM user_group_projects WHERE group_id=?', [(int)$id]);
        $projectIds  = array_column($projectRows, 'project_id');
        $projectVis  = array_column($projectRows, 'entry_visibility', 'project_id');
        $modules    = Auth::allModules();
        $permRows   = Database::fetchAll('SELECT module, can_view, can_own, can_edit FROM user_group_permissions WHERE group_id=?', [(int)$id]);
        $groupPerms = [];
        foreach ($permRows as $pr) $groupPerms[$pr['module']] = ['view' => (bool)$pr['can_view'], 'own' => (bool)$pr['can_own'], 'edit' => (bool)$pr['can_edit']];
        View::render('admin/groups/form', compact('group', 'users', 'projects', 'memberIds', 'projectIds', 'projectVis', 'modules', 'groupPerms') + ['title' => 'Edit Group']);
    }

    public static function deleteGroup(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM user_groups WHERE id=?', [(int)$id]);
        flash('success', 'Group deleted.');
        redirect('/admin/groups');
    }

    private static function syncUserPerms(int $userId): void {
        $modules = array_keys(Auth::allModules());
        Database::execute('DELETE FROM user_permissions WHERE user_id=?', [$userId]);
        $hasAny = !empty($_POST['perm_view']) || !empty($_POST['perm_own']) || !empty($_POST['perm_edit']);
        if (!$hasAny) return;
        foreach ($modules as $module) {
            $canView = !empty($_POST['perm_view'][$module]) ? 1 : 0;
            $canOwn  = !empty($_POST['perm_own'][$module])  ? 1 : 0;
            $canEdit = !empty($_POST['perm_edit'][$module]) ? 1 : 0;
            if ($canEdit) { $canOwn = 1; $canView = 1; }
            if ($canOwn)  { $canView = 1; }
            Database::execute(
                'INSERT INTO user_permissions (user_id, module, can_view, can_own, can_edit) VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_own=VALUES(can_own), can_edit=VALUES(can_edit)',
                [$userId, $module, $canView, $canOwn, $canEdit]
            );
        }
    }

    private static function syncGroupPerms(int $groupId): void {
        $modules = array_keys(Auth::allModules());
        Database::execute('DELETE FROM user_group_permissions WHERE group_id=?', [$groupId]);
        foreach ($modules as $module) {
            $canView = !empty($_POST['perm_view'][$module]) ? 1 : 0;
            $canOwn  = !empty($_POST['perm_own'][$module])  ? 1 : 0;
            $canEdit = !empty($_POST['perm_edit'][$module]) ? 1 : 0;
            // edit implies own implies view
            if ($canEdit) { $canOwn = 1; $canView = 1; }
            if ($canOwn)  { $canView = 1; }
            Database::execute(
                'INSERT INTO user_group_permissions (group_id, module, can_view, can_own, can_edit) VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_own=VALUES(can_own), can_edit=VALUES(can_edit)',
                [$groupId, $module, $canView, $canOwn, $canEdit]
            );
        }
    }

    private static function syncGroupMembers(int $groupId): void {
        Database::execute('DELETE FROM user_group_members  WHERE group_id=?', [$groupId]);
        Database::execute('DELETE FROM user_group_projects WHERE group_id=?', [$groupId]);
        foreach ((array)($_POST['user_ids'] ?? []) as $uid) {
            if ((int)$uid > 0) Database::insert('INSERT IGNORE INTO user_group_members (user_id, group_id) VALUES (?,?)', [(int)$uid, $groupId]);
        }
        foreach ((array)($_POST['project_ids'] ?? []) as $pid) {
            if ((int)$pid > 0) {
                $vis = ($_POST['project_vis'][(int)$pid] ?? '') === 'own' ? 'own' : 'all';
                Database::insert('INSERT IGNORE INTO user_group_projects (group_id, project_id, entry_visibility) VALUES (?,?,?)', [$groupId, (int)$pid, $vis]);
            }
        }
    }

    // ── Settings ──────────────────────────────────────────────
    public static function settings(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $keys = ['app_name', 'app_url', 'jira_url', 'jira_default_project', 'jira_test_request_project',
                     'confluence_url', 'confluence_default_space',
                     'sharepoint_tenant_id', 'sharepoint_client_id', 'sharepoint_client_secret', 'sharepoint_site_url',
                     'allow_registration', 'timezone', 'project_statuses',
                     'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
                     'test_result_outcomes'];
            foreach (['entries_type_ids','test_results_type_ids','other_entries_type_ids','test_result_entry_type_ids'] as $akey) {
                $aval = implode(',', array_filter(array_map('intval', (array)($_POST[$akey] ?? []))));
                Database::execute('INSERT INTO app_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$akey, $aval]);
            }
            // Statuses that hide an entry from the normal list and move it under "Archiviert"
            $archivedStatuses = array_values(array_intersect((array)($_POST['archived_statuses'] ?? []), array_keys(entryStatuses())));
            Database::execute('INSERT INTO app_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['archived_statuses', implode(',', $archivedStatuses)]);
            foreach ($keys as $key) {
                $val = $_POST[$key] ?? '';
                Database::execute(
                    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    [$key, $val]
                );
            }
            flash('success', 'Settings saved.');
            redirect('/admin/settings');
        }
        $settings = Database::fetchAll('SELECT * FROM app_settings');
        $s = array_column($settings, 'setting_value', 'setting_key');
        View::render('admin/settings', ['s' => $s, 'title' => 'Settings']);
    }

    // ── Zentao Settings ───────────────────────────────────────
    public static function zentaoSettings(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $scalar = ['zentao_url','zentao_token','zentao_default_product','zentao_default_type','zentao_default_pri','zentao_title_template','zentao_desc_template'];
            foreach ($scalar as $key) {
                Database::execute(
                    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    [$key, $_POST[$key] ?? '']
                );
            }
            // Status mapping JSON — values are arrays (multi-mapping: one Zentao status → multiple allowed local statuses)
            $rawStatusMap = $_POST['zentao_status_to_local'] ?? [];
            $statusMap = [];
            foreach ($rawStatusMap as $zentaoStatus => $localStatuses) {
                $statusMap[$zentaoStatus] = array_values((array)$localStatuses);
            }
            Database::execute(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['zentao_status_to_local', json_encode($statusMap)]
            );
            // Priority+severity mapping JSON: {Level: {pri: N, severity: N}}
            $rawPri = $_POST['zentao_priority_map'] ?? [];
            $priMap = [];
            foreach ($rawPri as $level => $vals) {
                if (is_array($vals) && isset($vals['pri'])) {
                    $priMap[$level] = ['pri' => (int)$vals['pri'], 'severity' => (int)($vals['severity'] ?? $vals['pri'])];
                }
            }
            Database::execute(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['zentao_priority_map', json_encode($priMap)]
            );
            foreach (['zentao_quick_sync_fields', 'zentao_full_sync_fields'] as $sfKey) {
                $val = $_POST[$sfKey] ?? [];
                Database::execute('INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    [$sfKey, json_encode(array_values($val))]);
            }
            // Relay: for environments that can't reach Zentao directly (e.g. a network-
            // restricted cluster), route all Zentao API calls through a small proxy script
            // hosted somewhere that can. Leave empty to call Zentao directly (default).
            Database::execute(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['zentao_relay_url', trim($_POST['zentao_relay_url'] ?? '')]
            );
            $newRelaySecret = trim($_POST['zentao_relay_secret'] ?? '');
            if ($newRelaySecret !== '') {
                Database::execute(
                    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    ['zentao_relay_secret', Encryption::encryptIfNeeded($newRelaySecret)]
                );
            }
            flash('success', 'Zentao settings saved.');
            redirect('/admin/zentao');
        }
        $s          = array_column(Database::fetchAll('SELECT * FROM app_settings'), 'setting_value', 'setting_key');
        $entryTypes = Database::fetchAll('SELECT name FROM entry_types ORDER BY sort_order, name');
        View::render('admin/zentao', [
            'title'            => 'Zentao Settings',
            's'                => $s,
            'entryTypes'       => $entryTypes,
            'hasRelaySecret'   => !empty($s['zentao_relay_secret']),
        ]);
    }

    // ── Jira Settings (templates + field mapping) ─────────────
    public static function jiraSettings(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            foreach (['jira_default_title_template', 'jira_default_desc_template'] as $key) {
                Database::execute(
                    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    [$key, $_POST[$key] ?? '']
                );
            }
            $mapping = [];
            foreach ($_POST as $k => $v) {
                if (str_starts_with($k, 'fm_id_') && trim($v) !== '') {
                    $localField = substr($k, 6);
                    $mapping[$localField] = [
                        'id'   => trim($v),
                        'type' => trim($_POST['fm_type_' . $localField] ?? 'text'),
                    ];
                }
            }
            Database::execute(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['jira_field_mapping', json_encode($mapping)]
            );
            // Jira priority map: entry type name → Jira priority string
            $jiraPriMap = array_filter($_POST['jira_priority_map'] ?? [], fn($v) => trim($v) !== '');
            Database::execute(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['jira_priority_map', json_encode($jiraPriMap)]
            );
            // Jira status map — built from parallel keys[]/vals[] arrays (supports custom rows)
            $smKeys = $_POST['jira_status_map_keys'] ?? [];
            $smVals = $_POST['jira_status_map_vals'] ?? [];
            $jiraStatusMap = [];
            foreach ($smKeys as $i => $key) {
                $key = trim($key);
                $val = trim($smVals[$i] ?? '');
                if ($key !== '' && $val !== '') $jiraStatusMap[$key] = $val;
            }
            Database::execute(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['jira_status_map', json_encode($jiraStatusMap)]
            );
            // Sync field configs
            foreach (['jira_quick_sync_fields', 'jira_full_sync_fields'] as $sfKey) {
                $val = $_POST[$sfKey] ?? [];
                Database::execute('INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    [$sfKey, json_encode(array_values($val))]);
            }
            flash('success', 'Jira settings saved.');
            redirect('/admin/jira');
        }
        $s          = array_column(Database::fetchAll('SELECT * FROM app_settings'), 'setting_value', 'setting_key');
        $mapping    = json_decode($s['jira_field_mapping'] ?? '{}', true) ?: [];
        $jiraPriMap = json_decode($s['jira_priority_map'] ?? '{}', true) ?: [];
        $entryTypes = Database::fetchAll('SELECT name FROM entry_types ORDER BY sort_order, name');
        View::render('admin/jira', [
            'title'          => 'Jira Settings',
            's'              => $s,
            'mapping'        => $mapping,
            'mappableFields' => JiraController::mappableFields(),
            'jiraPriMap'     => $jiraPriMap,
            'entryTypes'     => $entryTypes,
        ]);
    }

    // ── Microsoft SSO Settings ─────────────────────────────────
    public static function microsoftSsoSettings(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                ['ms_sso_enabled', !empty($_POST['ms_sso_enabled']) ? '1' : '0']
            );
            foreach (['ms_tenant_id', 'ms_client_id'] as $key) {
                Database::execute(
                    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    [$key, trim($_POST[$key] ?? '')]
                );
            }
            // Client secret: leave the stored value untouched if the field was left empty
            // (so re-saving the form doesn't require re-entering it every time).
            $newSecret = trim($_POST['ms_client_secret'] ?? '');
            if ($newSecret !== '') {
                Database::execute(
                    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                    ['ms_client_secret', Encryption::encryptIfNeeded($newSecret)]
                );
            }
            flash('success', 'Microsoft SSO settings saved.');
            redirect('/admin/microsoft-sso');
        }
        $s = array_column(Database::fetchAll('SELECT * FROM app_settings'), 'setting_value', 'setting_key');
        View::render('admin/microsoft-sso', [
            'title'          => 'Microsoft SSO Settings',
            's'              => $s,
            'hasClientSecret'=> !empty($s['ms_client_secret']),
        ]);
    }

    // ── Entry Types ───────────────────────────────────────────
    public static function entryTypes(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                Database::insert('INSERT INTO entry_types (name, color, icon, sort_order) VALUES (?,?,?,?)',
                    [trim($_POST['name']), $_POST['color'] ?? '#6366f1', trim($_POST['icon'] ?? 'tag'), (int)($_POST['sort_order'] ?? 0)]);
                flash('success', 'Type created.');
            } elseif ($action === 'edit') {
                Database::execute('UPDATE entry_types SET name=?, color=?, icon=?, sort_order=? WHERE id=?',
                    [trim($_POST['name']), $_POST['color'], trim($_POST['icon']), (int)$_POST['sort_order'], (int)$_POST['id']]);
                flash('success', 'Type updated.');
            }
            redirect('/admin/entry-types');
        }
        $types = Database::fetchAll('SELECT * FROM entry_types ORDER BY sort_order, name');
        View::render('admin/entry-types', ['types' => $types, 'title' => 'Entry Types']);
    }

    public static function deleteEntryType(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM entry_types WHERE id=?', [(int)$id]);
        flash('success', 'Type deleted.');
        redirect('/admin/entry-types');
    }

    // ── Categories ────────────────────────────────────────────
    public static function categories(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                Database::insert('INSERT INTO error_categories (name, description, color, sort_order) VALUES (?,?,?,?)',
                    [trim($_POST['name']), trim($_POST['description'] ?? ''), $_POST['color'] ?? '#6366f1', (int)$_POST['sort_order']]);
                flash('success', 'Category created.');
            } elseif ($action === 'edit') {
                Database::execute('UPDATE error_categories SET name=?, description=?, color=?, sort_order=? WHERE id=?',
                    [trim($_POST['name']), trim($_POST['description'] ?? ''), $_POST['color'], (int)$_POST['sort_order'], (int)$_POST['id']]);
                flash('success', 'Category updated.');
            }
            redirect('/admin/categories');
        }
        $cats = Database::fetchAll('SELECT * FROM error_categories ORDER BY sort_order, name');
        View::render('admin/categories', ['cats' => $cats, 'title' => 'Error Categories']);
    }

    public static function deleteCategory(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM error_categories WHERE id=?', [(int)$id]);
        flash('success', 'Category deleted.');
        redirect('/admin/categories');
    }

    // ── Custom Fields ─────────────────────────────────────────
    public static function customFields(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                $varName = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($_POST['variable_name'] ?? trim($_POST['name'] ?? ''))));
                Database::insert('INSERT INTO custom_fields (name, variable_name, field_type, options, placeholder, sort_order) VALUES (?,?,?,?,?,?)',
                    [trim($_POST['name']), $varName, $_POST['field_type'] ?? 'text', trim($_POST['options'] ?? ''), trim($_POST['placeholder'] ?? ''), (int)$_POST['sort_order']]);
                flash('success', 'Field created.');
            } elseif ($action === 'edit') {
                Database::execute('UPDATE custom_fields SET name=?, field_type=?, options=?, placeholder=?, sort_order=? WHERE id=?',
                    [trim($_POST['name']), $_POST['field_type'], trim($_POST['options'] ?? ''), trim($_POST['placeholder'] ?? ''), (int)$_POST['sort_order'], (int)$_POST['id']]);
                flash('success', 'Field updated.');
            }
            redirect('/admin/custom-fields');
        }
        $fields = Database::fetchAll('SELECT * FROM custom_fields ORDER BY sort_order, name');
        View::render('admin/custom-fields', ['fields' => $fields, 'title' => 'Custom Fields']);
    }

    public static function deleteCustomField(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM custom_fields WHERE id=?', [(int)$id]);
        flash('success', 'Field deleted.');
        redirect('/admin/custom-fields');
    }

    // ── Test Case Custom Fields ───────────────────────────────
    public static function testCaseFields(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                $varName = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($_POST['variable_name'] ?? trim($_POST['name'] ?? ''))));
                Database::insert(
                    'INSERT INTO test_case_fields (name, variable_name, field_type, options, placeholder, sort_order) VALUES (?,?,?,?,?,?)',
                    [trim($_POST['name']), $varName, $_POST['field_type'] ?? 'text', trim($_POST['options'] ?? ''), trim($_POST['placeholder'] ?? ''), (int)($_POST['sort_order'] ?? 0)]
                );
                flash('success', 'Field created.');
            } elseif ($action === 'edit') {
                Database::execute(
                    'UPDATE test_case_fields SET name=?, field_type=?, options=?, placeholder=?, sort_order=? WHERE id=?',
                    [trim($_POST['name']), $_POST['field_type'], trim($_POST['options'] ?? ''), trim($_POST['placeholder'] ?? ''), (int)($_POST['sort_order'] ?? 0), (int)$_POST['id']]
                );
                flash('success', 'Field updated.');
            }
            redirect('/admin/test-case-fields');
        }
        $fields = Database::fetchAll('SELECT * FROM test_case_fields ORDER BY sort_order, name');
        View::render('admin/test-case-fields', ['fields' => $fields, 'title' => 'Test Case Fields']);
    }

    public static function deleteTestCaseField(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_case_fields WHERE id=?', [(int)$id]);
        flash('success', 'Field deleted.');
        redirect('/admin/test-case-fields');
    }

    // ── Test Mowers ───────────────────────────────────────────
    public static function testMowers(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                Database::insert(
                    'INSERT INTO test_mowers (label, serial_number, model, firmware_version, notes) VALUES (?,?,?,?,?)',
                    [trim($_POST['label']), trim($_POST['serial_number'] ?? ''), trim($_POST['model'] ?? ''), trim($_POST['firmware_version'] ?? ''), trim($_POST['notes'] ?? '')]
                );
                flash('success', 'Mower added.');
            } elseif ($action === 'edit') {
                Database::execute(
                    'UPDATE test_mowers SET label=?, serial_number=?, model=?, firmware_version=?, notes=? WHERE id=?',
                    [trim($_POST['label']), trim($_POST['serial_number'] ?? ''), trim($_POST['model'] ?? ''), trim($_POST['firmware_version'] ?? ''), trim($_POST['notes'] ?? ''), (int)$_POST['id']]
                );
                flash('success', 'Mower updated.');
            }
            redirect('/admin/test-mowers');
        }
        $mowers = Database::fetchAll('SELECT * FROM test_mowers ORDER BY label');
        View::render('admin/test-mowers', ['mowers' => $mowers, 'title' => 'Test Mowers']);
    }

    public static function deleteTestMower(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_mowers WHERE id=?', [(int)$id]);
        flash('success', 'Mower deleted.');
        redirect('/admin/test-mowers');
    }

    // ── Environments ──────────────────────────────────────────
    public static function environments(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                Database::insert('INSERT INTO test_environments (name, os, device, firmware, description) VALUES (?,?,?,?,?)',
                    [trim($_POST['name']), trim($_POST['os'] ?? ''), trim($_POST['device'] ?? ''), trim($_POST['firmware'] ?? ''), trim($_POST['description'] ?? '')]);
                flash('success', 'Environment created.');
            } elseif ($action === 'edit') {
                Database::execute('UPDATE test_environments SET name=?, os=?, device=?, firmware=?, description=? WHERE id=?',
                    [trim($_POST['name']), trim($_POST['os'] ?? ''), trim($_POST['device'] ?? ''), trim($_POST['firmware'] ?? ''), trim($_POST['description'] ?? ''), (int)$_POST['id']]);
                flash('success', 'Environment updated.');
            }
            redirect('/admin/environments');
        }
        $envs = Database::fetchAll('SELECT * FROM test_environments ORDER BY name');
        View::render('admin/environments', ['envs' => $envs, 'title' => 'Test Environments']);
    }

    public static function deleteEnvironment(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_environments WHERE id=?', [(int)$id]);
        flash('success', 'Environment deleted.');
        redirect('/admin/environments');
    }

    // ── Checklists ────────────────────────────────────────────
    public static function checklists(): void {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                $id = Database::insert('INSERT INTO test_checklists (name, description) VALUES (?,?)',
                    [trim($_POST['name']), trim($_POST['description'] ?? '')]);
                // Parse items (one per line)
                $lines = array_filter(array_map('trim', explode("\n", $_POST['items'] ?? '')));
                foreach (array_values($lines) as $i => $line) {
                    Database::insert('INSERT INTO checklist_items (checklist_id, text, sort_order) VALUES (?,?,?)', [$id, $line, $i]);
                }
                flash('success', 'Checklist created.');
            }
            redirect('/admin/checklists');
        }
        $cls = Database::fetchAll(
            "SELECT tc.*, GROUP_CONCAT(ci.text ORDER BY ci.sort_order SEPARATOR '\n') items
             FROM test_checklists tc LEFT JOIN checklist_items ci ON ci.checklist_id=tc.id
             GROUP BY tc.id ORDER BY tc.name"
        );
        View::render('admin/checklists', ['cls' => $cls, 'title' => 'Checklists']);
    }

    public static function deleteChecklist(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM test_checklists WHERE id=?', [(int)$id]);
        flash('success', 'Checklist deleted.');
        redirect('/admin/checklists');
    }

    // ── Audit Log ─────────────────────────────────────────────
    public static function audit(): void {
        Auth::requireAdmin();
        $logs = Database::fetchAll(
            "SELECT al.*, u.name user_name FROM audit_log al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT 200"
        );
        View::render('admin/audit', ['logs' => $logs, 'title' => 'Audit Log']);
    }

    // ── Cron Job Management ───────────────────────────────────────────────────
    public static function cronJobs(): void {
        Auth::requireAdmin();
        // Ensure table + seed
        require_once APP_ROOT . '/app/cron/runner.php';
        $jobs = Database::fetchAll("SELECT * FROM cron_jobs ORDER BY label");
        View::render('admin/cron', compact('jobs'), 'app', ['title' => 'Cron Jobs']);
    }

    public static function cronToggle(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $job = Database::fetchOne("SELECT * FROM cron_jobs WHERE id=?", [(int)$id]);
        if (!$job) abort(404);
        Database::execute(
            "UPDATE cron_jobs SET is_active=? WHERE id=?",
            [$job['is_active'] ? 0 : 1, (int)$id]
        );
        redirect('admin/cron');
    }

    public static function cronInterval(string $id): void {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $min = max(1, min(1440, (int)($_POST['interval_min'] ?? 5)));
        Database::execute(
            "UPDATE cron_jobs SET interval_min=? WHERE id=?",
            [$min, (int)$id]
        );
        redirect('admin/cron');
    }
}