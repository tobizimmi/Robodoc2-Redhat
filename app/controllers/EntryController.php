<?php
declare(strict_types=1);

class EntryController {

    // ?? List ?????????????????????????????????????????????????????
    public static function index(string $viewMode = 'entries'): void {
        Auth::requireView('entries');
        $userId  = Auth::id();
        $isAdmin = Auth::isAdmin();

        $settingKey   = match($viewMode) {
            'test-results'   => 'test_results_type_ids',
            'other-entries'  => 'other_entries_type_ids',
            default          => 'entries_type_ids',
        };
        $preFilterRaw = appSetting($settingKey, '');
        $preFilterIds = array_filter(array_map('intval', explode(',', $preFilterRaw)));


        $page     = max(1, (int)($_GET['page'] ?? 1));
        $perPage  = 30;
        $offset   = ($page - 1) * $perPage;
        $search   = trim($_GET['search'] ?? '');
        $projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        $catId    = isset($_GET['cat_id']) && is_numeric($_GET['cat_id']) ? (int)$_GET['cat_id'] : null;
        $typeIds  = array_filter(array_map('intval', (array)($_GET['type_ids'] ?? [])));
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';
        // Expanded sort: maps URL param ? safe SQL expression (all whitelisted, never interpolated from user input)
        $sortMap = [
            'entry_date'       => 'e.entry_date',
            'entry_time'       => 'e.entry_time',
            'id'               => 'e.id',
            'created_at'       => 'e.created_at',
            'status'           => 'e.status',
            'priority'         => "FIELD(e.priority,'Blocker','Highest','High','Medium','Low')",
            'type_name'        => 'et.name',
            'cat_name'         => 'ec.name',
            'project_name'     => 'p.name',
            'mower_serial'     => 'e.mower_serial',
            'firmware_version' => 'e.firmware_version',
            'app_version'      => 'e.app_version',
            'env_name'         => 'env.name',
            'test_area_name'   => 'ta.name',
            'creator'          => 'u.name',
            'temperature'      => 'e.temperature',
        ];
        $sortParam = $_GET['sort'] ?? 'entry_date';
        $sortBy    = array_key_exists($sortParam, $sortMap) ? $sortParam : 'entry_date';
        $sortExpr  = $sortMap[$sortBy];
        $sortDir   = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $showTestEntries    = !empty($_GET['show_test_entries']);
        $showKeyQuestions   = !empty($_GET['show_key_questions']);

        $where  = ['1=1'];
        $params = [];

        if (!$isAdmin) {
            [$accessClause, $accessParams] = Auth::entryAccessClause();
            $where[]  = $accessClause;
            $params   = array_merge($params, $accessParams);
        }
        // Apply global project filter (affects all users incl. admin)
        [$gfClause, $gfParams] = Auth::globalFilterClause('e');
        if ($gfClause !== '1=1') {
            $where[]  = $gfClause;
            $params   = array_merge($params, $gfParams);
        }
        if (!$showTestEntries) {
            $where[] = '(e.is_test_entry = 0 OR e.is_test_entry IS NULL)';
        }
        if (!$showKeyQuestions) {
            $where[] = '(e.is_key_question = 0 OR e.is_key_question IS NULL)';
        }
        if ($projectId) { $where[] = 'e.project_id = ?';        $params[] = $projectId; }
        if ($catId)     { $where[] = 'e.error_category_id = ?'; $params[] = $catId; }
        if (!empty($typeIds)) {
            $ph = implode(',', array_fill(0, count($typeIds), '?'));
            $where[] = "e.entry_type_id IN ($ph)";
            $params  = array_merge($params, $typeIds);
        } elseif (!empty($preFilterIds)) {
            $ph = implode(',', array_fill(0, count($preFilterIds), '?'));
            $where[] = "e.entry_type_id IN ($ph)";
            $params  = array_merge($params, $preFilterIds);
        }
        if ($search) {
            $where[] = '(e.title LIKE ? OR e.description LIKE ? OR e.mower_serial LIKE ?)';
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($dateFrom) { $where[] = 'e.entry_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = 'e.entry_date <= ?'; $params[] = $dateTo; }

        // Epic filter
        $epicFilter = isset($_GET['epic_id']) && (int)$_GET['epic_id'] > 0 ? (int)$_GET['epic_id'] : 0;
        if ($epicFilter) { $where[] = 'e.epic_id = ?'; $params[] = $epicFilter; }

        // Snapshot before col filters for totalUnfiltered count
        $whereBeforeColFilters  = $where;
        $paramsBeforeColFilters = $params;

        // Column filters (_f_* params) ? server-side filtering for all pages
        // Maps col key -> [sql_expr, type] where type: 'like'=LIKE, 'in'=exact match IN
        $colFilterMap = [
            'title'       => ['e.title',                    'like'],
            'description' => ['e.description',              'like'],
            'status'      => ['e.status',                   'in'],
            'priority'    => ['e.priority',                 'in'],
            'type'        => ['et.name',                    'in'],
            'category'    => ['ec.name',                    'in'],
            'project'     => ['p.name',                     'in'],
            'creator'     => ['u.name',                     'in'],
            'serial'      => ['e.mower_serial',             'like'],
            'firmware'    => ['e.firmware_version',         'like'],
            'app_version' => ['e.app_version',              'like'],
            'jira'        => ['e.jira_issue_key',           'like'],
            'zentao'      => ['CAST(e.zentao_bug_id AS CHAR)', 'like'],
        ];
        $colFiltersActive = [];
        foreach ($colFilterMap as $colKey => [$sqlExpr, $filterType]) {
            $rawVal = trim($_GET['_f_'.$colKey] ?? '');
            if ($rawVal === '') continue;
            // Support comma/semicolon separated values (OR logic)
            $terms = array_filter(array_map('trim', preg_split('/[,;]/', $rawVal)));
            if (empty($terms)) continue;
            $colFiltersActive[$colKey] = $rawVal;
            if ($filterType === 'like') {
                // LIKE OR across terms
                $likeClauses = array_map(fn($t) => "$sqlExpr LIKE ?", $terms);
                $where[] = '(' . implode(' OR ', $likeClauses) . ')';
                foreach ($terms as $t) $params[] = "%$t%";
            } else {
                // Exact match IN (case-insensitive via LOWER)
                $inClauses = array_map(fn($t) => "LOWER($sqlExpr) LIKE LOWER(?)", $terms);
                $where[] = '(' . implode(' OR ', $inClauses) . ')';
                foreach ($terms as $t) $params[] = "%$t%";
            }
        }

        // Archive vs normal list — entries are archived either by being merged/a
        // sub-ticket, or by having a status the admin flagged as "archived"
        // (Admin > Settings > Automatische Archivierung nach Status).
        $archivedStatuses = array_filter(array_map('trim', explode(',', appSetting('archived_statuses', ''))));
        $entryListView = $_GET['list'] ?? 'normal';
        if ($entryListView === 'archived') {
            $archCond = '(e.is_merged = 1 OR e.parent_id IS NOT NULL)';
            if ($archivedStatuses) {
                $ph = implode(',', array_fill(0, count($archivedStatuses), '?'));
                $archCond = "($archCond OR e.status IN ($ph))";
                $params = array_merge($params, $archivedStatuses);
            }
            $where[] = $archCond;
        } else {
            $where[] = '(e.is_merged = 0 OR e.is_merged IS NULL)';
            $where[] = 'e.parent_id IS NULL';
            if ($archivedStatuses) {
                $ph = implode(',', array_fill(0, count($archivedStatuses), '?'));
                $where[] = "e.status NOT IN ($ph)";
                $params = array_merge($params, $archivedStatuses);
            }
        }

        $wStr  = implode(' AND ', $where);
        $total = (int)Database::fetchOne(
            "SELECT COUNT(*) c FROM entries e
             LEFT JOIN entry_types      et ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             LEFT JOIN projects         p  ON p.id  = e.project_id
             LEFT JOIN users            u  ON u.id  = e.created_by
             WHERE $wStr", $params
        )['c'];
        // Count without col filters for 'X of Y' display
        if (!empty($colFiltersActive)) {
            $wStrBase = implode(' AND ', $whereBeforeColFilters);
            $totalUnfiltered = (int)Database::fetchOne(
                "SELECT COUNT(*) c FROM entries e
                 LEFT JOIN entry_types      et ON et.id = e.entry_type_id
                 LEFT JOIN error_categories ec ON ec.id = e.error_category_id
                 LEFT JOIN projects         p  ON p.id  = e.project_id
                 LEFT JOIN users            u  ON u.id  = e.created_by
                 WHERE $wStrBase", $paramsBeforeColFilters
            )['c'];
        } else {
            $totalUnfiltered = $total;
        }
        $pag   = paginate($total, $page, $perPage);

        $allEpicEntries = [];
        $epicIdsAll     = [];

        // Standard paginated query (unchanged)
        $entries = Database::fetchAll(
            "SELECT e.id, e.title, SUBSTRING(e.description, 1, 200) description, e.entry_date, e.entry_time,
                    e.firmware_version, e.mower_serial, e.app_version, e.is_private,
                    e.project_status_robot, e.temperature, e.weather_condition,
                    e.gps_lat, e.gps_lon,
                    e.jira_issue_key, e.jira_issue_url, e.jira_has_changes, e.jira_status,
                    e.zentao_bug_id, e.zentao_bug_url, e.zentao_has_changes, e.zentao_status,
                    e.zip_downloaded_at, e.attachments_updated_at, e.priority, e.status,
                    e.parent_id, e.is_merged, e.epic_id,
                    et.name type_name, et.color type_color,
                    ec.name cat_name,  ec.color cat_color,
                    p.name  project_name, p.color project_color,
                    env.name env_name,
                    ta.name  test_area_name,
                    u.name  creator
             FROM entries e
             LEFT JOIN entry_types et        ON et.id  = e.entry_type_id
             LEFT JOIN error_categories ec   ON ec.id  = e.error_category_id
             LEFT JOIN projects p            ON p.id   = e.project_id
             LEFT JOIN test_environments env ON env.id = e.environment_id
             LEFT JOIN test_areas ta         ON ta.id  = e.test_area_id
             LEFT JOIN users u               ON u.id   = e.created_by
             WHERE $wStr
             ORDER BY $sortExpr $sortDir, e.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        // On page 1: load ALL epic entries from DB and prepend
        if ($entryListView === 'normal' && !$epicFilter && $page === 1) {
            $epicIdRows = Database::fetchAll(
                "SELECT DISTINCT e.epic_id FROM entries e
                 LEFT JOIN entry_types et        ON et.id  = e.entry_type_id
                 LEFT JOIN error_categories ec   ON ec.id  = e.error_category_id
                 LEFT JOIN projects p            ON p.id   = e.project_id
                 LEFT JOIN users u               ON u.id   = e.created_by
                 WHERE $wStr AND e.epic_id IS NOT NULL",
                $params
            );
            $epicIdsAll = array_values(array_filter(array_column($epicIdRows, 'epic_id')));
        }
        if (!empty($epicIdsAll) && $page === 1) {
            $phE = implode(',', array_fill(0, count($epicIdsAll), '?'));
            $allEpicEntries = Database::fetchAll(
                "SELECT e.id, e.title, SUBSTRING(e.description,1,200) description,
                        e.entry_date, e.entry_time, e.firmware_version, e.mower_serial,
                        e.app_version, e.is_private, e.project_status_robot,
                        e.temperature, e.weather_condition, e.gps_lat, e.gps_lon,
                        e.jira_issue_key, e.jira_issue_url, e.jira_has_changes, e.jira_status,
                        e.zentao_bug_id, e.zentao_bug_url, e.zentao_has_changes, e.zentao_status,
                        e.zip_downloaded_at, e.attachments_updated_at, e.priority, e.status,
                        e.parent_id, e.is_merged, e.epic_id,
                        et.name type_name, et.color type_color,
                        ec.name cat_name, ec.color cat_color,
                        p.name project_name, p.color project_color,
                        env.name env_name, ta.name test_area_name, u.name creator
                 FROM entries e
                 LEFT JOIN entry_types et        ON et.id  = e.entry_type_id
                 LEFT JOIN error_categories ec   ON ec.id  = e.error_category_id
                 LEFT JOIN projects p            ON p.id   = e.project_id
                 LEFT JOIN test_environments env ON env.id = e.environment_id
                 LEFT JOIN test_areas ta         ON ta.id  = e.test_area_id
                 LEFT JOIN users u               ON u.id   = e.created_by
                 WHERE e.epic_id IN ($phE) AND (e.is_merged=0 OR e.is_merged IS NULL)
                 " . (!empty($preFilterIds) ? 'AND e.entry_type_id IN (' . implode(',', array_fill(0, count($preFilterIds), '?')) . ')' : '') . "
                 ORDER BY e.entry_date DESC, e.id DESC",
                array_merge($epicIdsAll, !empty($preFilterIds) ? $preFilterIds : [])
            );
            // Remove epic entries from paginated results (they are fully loaded above)
            $epicIdSet = array_flip(array_column($allEpicEntries, 'id'));
            $entries   = array_values(array_filter($entries, fn($e) => !isset($epicIdSet[$e['id']])));
            // Prepend complete epic entries before non-epic paginated entries
            $entries = array_values(array_merge($allEpicEntries, $entries));
        }

        // On page 2+: filter out epic entries (shown on page 1 only)
        if ($entryListView === 'normal' && !$epicFilter && $page > 1) {
            $epicIdRowsP = Database::fetchAll(
                "SELECT DISTINCT e.epic_id FROM entries e
                 LEFT JOIN entry_types et        ON et.id  = e.entry_type_id
                 LEFT JOIN error_categories ec   ON ec.id  = e.error_category_id
                 LEFT JOIN projects p            ON p.id   = e.project_id
                 LEFT JOIN users u               ON u.id   = e.created_by
                 WHERE $wStr AND e.epic_id IS NOT NULL",
                $params
            );
            $epicIdsPage = array_values(array_filter(array_column($epicIdRowsP, 'epic_id')));
            if (!empty($epicIdsPage)) {
                $entries = array_values(array_filter($entries, fn($e) => empty($e['epic_id'])));
            }
        }

        // In normal view: load sub-tickets for entries on this page and append them
        if ($entryListView === 'normal' && $entries) {
            $entryIds = array_column($entries, 'id');
            $phSub = implode(',', array_fill(0, count($entryIds), '?'));
            $subEntries = Database::fetchAll(
                "SELECT e.id, e.title, SUBSTRING(e.description,1,200) description,
                        e.entry_date, e.entry_time, e.firmware_version, e.mower_serial,
                        e.app_version, e.is_private, e.project_status_robot,
                        e.temperature, e.weather_condition, e.gps_lat, e.gps_lon,
                        e.jira_issue_key, e.jira_issue_url, e.jira_has_changes, e.jira_status,
                        e.zentao_bug_id, e.zentao_bug_url, e.zentao_has_changes, e.zentao_status,
                        e.zip_downloaded_at, e.attachments_updated_at, e.priority, e.status,
                        e.parent_id, e.is_merged, e.epic_id,
                        et.name type_name, et.color type_color,
                        ec.name cat_name, ec.color cat_color,
                        p.name project_name, p.color project_color,
                        env.name env_name, ta.name test_area_name, u.name creator
                 FROM entries e
                 LEFT JOIN entry_types et        ON et.id  = e.entry_type_id
                 LEFT JOIN error_categories ec   ON ec.id  = e.error_category_id
                 LEFT JOIN projects p            ON p.id   = e.project_id
                 LEFT JOIN test_environments env ON env.id = e.environment_id
                 LEFT JOIN test_areas ta         ON ta.id  = e.test_area_id
                 LEFT JOIN users u               ON u.id   = e.created_by
                 WHERE e.parent_id IN ($phSub) AND (e.is_merged=0 OR e.is_merged IS NULL)
                 ORDER BY e.parent_id, e.id",
                $entryIds
            );
            // Inject sub-entries into the main array right after their parent
            if ($subEntries) {
                $subByParent = [];
                foreach ($subEntries as $sub) $subByParent[$sub['parent_id']][] = $sub;
                $expanded = [];
                foreach ($entries as $e) {
                    $e['_depth'] = 0;
                    $expanded[] = $e;
                    foreach ($subByParent[$e['id']] ?? [] as $sub) {
                        $sub['_depth'] = 1; // mark as sub-ticket for view indentation
                        $expanded[] = $sub;
                    }
                }
                $entries = $expanded;
            }
        }

        // Build childrenMap for sub-ticket count badges shown on parent rows
        $childrenMap = [];
        foreach ($entries as $e) {
            if (!empty($e['parent_id'])) $childrenMap[$e['parent_id']][] = $e['id'];
        }

        // Build epicGroups
        $epicGroups = [];
        if ($epicFilter) {
            $activeEpic = Database::fetchOne('SELECT * FROM epics WHERE id=?', [$epicFilter]);
            if ($activeEpic) $epicGroups[$epicFilter] = ['epic' => $activeEpic, 'count' => count($entries)];
        }
          if ($page === 1 && !empty($epicIdsAll) && !empty($allEpicEntries)) {
            $phE       = implode(',', array_fill(0, count($epicIdsAll), '?'));
            $epicsData = Database::fetchAll("SELECT * FROM epics WHERE id IN ($phE) ORDER BY sort_order, title", $epicIdsAll);
            $byEpicCount = array_count_values(array_filter(array_column($allEpicEntries, 'epic_id')));
            foreach ($epicsData as $epic) {
                $epicGroups[$epic['id']] = ['epic' => $epic, 'count' => $byEpicCount[$epic['id']] ?? 0];
            }
        }

        // Batch-load counts and thumbnails for the current page
        if ($entries) {
            $ids = array_column($entries, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));

            $attRows = Database::fetchAll(
                "SELECT entry_id, COUNT(*) att_count,
                        MIN(CASE WHEN mime_type LIKE 'image/%' THEN id END) thumb_att_id
                 FROM entry_attachments WHERE entry_id IN ($ph) GROUP BY entry_id",
                $ids
            );
            $attMap = array_column($attRows, null, 'entry_id');

            $cmtRows = Database::fetchAll(
                "SELECT entry_id, COUNT(*) comment_count FROM entry_comments WHERE entry_id IN ($ph) GROUP BY entry_id",
                $ids
            );
            $cmtMap = array_column($cmtRows, null, 'entry_id');

            $todoRows = Database::fetchAll(
                "SELECT entry_id FROM entry_todos WHERE entry_id IN ($ph) AND user_id = ?",
                array_merge($ids, [$userId])
            );
            $todoSet = array_flip(array_column($todoRows, 'entry_id'));

            foreach ($entries as &$e) {
                if (!empty($e['_is_epic_header'])) continue; // skip pseudo-rows
                $a = $attMap[$e['id']] ?? null;
                $e['att_count']     = (int)($a['att_count'] ?? 0);
                $e['thumb_att_id']  = $a['thumb_att_id'] ?? null;
                $e['comment_count'] = (int)($cmtMap[$e['id']]['comment_count'] ?? 0);
                $e['is_todo']       = isset($todoSet[$e['id']]) ? 1 : 0;
            }
            unset($e);
        }

        [$projSql, $projParams] = Auth::projectAccessClause();
        $projects    = Database::fetchAll("SELECT id, name, color FROM projects WHERE $projSql ORDER BY name", $projParams);
        $entryTypes  = Database::fetchAll("SELECT * FROM entry_types ORDER BY sort_order, name");
        $categories  = Database::fetchAll("SELECT * FROM error_categories ORDER BY sort_order, name");

        $settings = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        View::render('entries/index', compact(
            'entries', 'pag', 'projects', 'entryTypes', 'categories',
            'search', 'projectId', 'catId', 'typeIds', 'dateFrom', 'dateTo', 'sortBy', 'sortDir',
            'showTestEntries', 'showKeyQuestions', 'settings',
            'childrenMap', 'entryListView', 'epicFilter', 'epicGroups', 'colFiltersActive', 'totalUnfiltered'
        ) + ['title' => match($viewMode) {
            'test-results'  => 'Test Results',
            'other-entries' => 'Sonstige Einträge',
            default         => 'Entries',
        }, 'vmForView' => $viewMode, 'preFilterIds' => $preFilterIds]);
    }

    public static function testResults(): void { self::index('test-results'); }
    public static function otherEntries(): void { self::index('other-entries'); }

    // ?? Show ????????????????????????????????????????????????????
    public static function show(string $id): void {
        Auth::requireView('entries');
        $entry = self::findOr404((int)$id);
        self::checkPrivacy($entry);

        // Check if this is a Test Result Entry
        $trTypeIds   = array_filter(array_map('intval', explode(',', appSetting('test_result_entry_type_ids',''))));
        $isTestEntry = !empty($trTypeIds) && in_array((int)($entry['entry_type_id']??0), $trTypeIds);
        // Also treat as test entry if entry_type name is 'Test Result'
        if (!$isTestEntry) {
            $etName = Database::fetchOne('SELECT name FROM entry_types WHERE id=?', [(int)($entry['entry_type_id']??0)]);
            if ($etName && strtolower($etName['name']) === 'test result') $isTestEntry = true;
        }
        // Load test result sub-items
        $testResults  = [];
        if ($isTestEntry) {
            $rows = Database::fetchAll('SELECT * FROM entry_test_results WHERE entry_id=? ORDER BY sort_order', [(int)$id]);
            foreach ($rows as &$row) {
                $row['attachments'] = Database::fetchAll('SELECT * FROM entry_attachments WHERE entry_id=? AND test_result_id=? ORDER BY created_at', [(int)$id, $row['id']]);
            }
            $testResults = $rows;
        }
        $testOutcomes = $isTestEntry ? array_filter(array_map('trim', explode(',', appSetting('test_result_outcomes', 'Passed,Failed,Blocked,Partial,Not Run')))) : [];
        $testCycles   = $isTestEntry ? Database::fetchAll('SELECT tc.id, tc.name, tp.name plan_name FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id ORDER BY tc.created_at DESC LIMIT 100') : [];
        $testMowers   = $isTestEntry ? Database::fetchAll('SELECT id, label, serial_number FROM test_mowers ORDER BY label') : [];
        $testCycleLinked = ($isTestEntry && !empty($entry['test_cycle_id'])) ? Database::fetchOne('SELECT tc.*, tp.name plan_name FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id WHERE tc.id=?', [$entry['test_cycle_id']]) : null;
        $testCaseLinked  = ($isTestEntry && !empty($entry['test_plan_item_id_ref'])) ? Database::fetchOne('SELECT * FROM test_plan_items WHERE id=?', [$entry['test_plan_item_id_ref']]) : null;
        // Load all attachments; TR-linked ones also shown per partial result
        $allAttachments = Database::fetchAll(
            'SELECT * FROM entry_attachments WHERE entry_id = ? ORDER BY created_at',
            [(int)$id]
        );
        // Split: general attachments (no TR link) vs TR-linked
        $attachments = array_values(array_filter($allAttachments, fn($a) => empty($a['test_result_id'])));
        $trAttachments = array_values(array_filter($allAttachments, fn($a) => !empty($a['test_result_id'])));
        $comments    = Database::fetchAll(
            'SELECT c.*, u.name user_name FROM entry_comments c JOIN users u ON u.id = c.user_id WHERE c.entry_id = ? ORDER BY c.created_at',
            [(int)$id]
        );
        $history     = Database::fetchAll(
            'SELECT h.*, u.name user_name FROM entry_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.entry_id = ? ORDER BY h.changed_at DESC',
            [(int)$id]
        );
        $customFields  = Database::fetchAll('SELECT * FROM custom_fields ORDER BY sort_order, name');
        $customValues  = Database::fetchAll('SELECT * FROM entry_custom_values WHERE entry_id = ?', [(int)$id]);
        $customMap     = array_column($customValues, 'value', 'field_id');

        $todoData = Database::fetchOne(
            'SELECT due_date, priority, notes FROM entry_todos WHERE entry_id = ? AND user_id = ?',
            [(int)$id, Auth::id()]
        );
        $isTodo = (bool)$todoData;

        $testItems = Database::fetchAll(
            "SELECT tpi.*, tp.name plan_name, p.name project_name
             FROM test_plan_item_entries tpie
             JOIN test_plan_items tpi ON tpi.id = tpie.test_plan_item_id
             JOIN test_plans tp ON tp.id = tpi.test_plan_id
             JOIN projects p ON p.id = tp.project_id
             WHERE tpie.entry_id = ?",
            [(int)$id]
        );

        try {
            $linkedEntries = Database::fetchAll(
                "SELECT e.id, e.title, e.entry_date, et.name type_name, et.color type_color, el.id link_id
                 FROM entry_links el
                 JOIN entries e ON e.id = IF(el.from_entry_id=?, el.to_entry_id, el.from_entry_id)
                 JOIN entry_types et ON et.id = e.entry_type_id
                 WHERE el.from_entry_id=? OR el.to_entry_id=?",
                [(int)$id, (int)$id, (int)$id]
            );
        } catch (Throwable) {
            $linkedEntries = [];
        }

        $settings    = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $currentUser = Database::fetchOne('SELECT jira_title_template, jira_desc_template FROM users WHERE id=?', [Auth::id()]);
        $jiraComments = Database::fetchAll(
            'SELECT * FROM jira_comments WHERE source_type=? AND source_id=? ORDER BY jira_created_at',
            ['entry', (int)$id]
        );
        $zentaoActions = Database::fetchAll(
            "SELECT * FROM jira_comments WHERE source_type='zentao_bug' AND source_id=? ORDER BY jira_created_at",
            [(int)$id]
        );

                // Load epic if assigned
        $entryEpic = !empty($entry['epic_id'])
            ? Database::fetchOne('SELECT id, title, color FROM epics WHERE id=?', [$entry['epic_id']])
            : null;
        // Load parent entry and sub-tickets
        $parentEntry = !empty($entry['parent_id'])
            ? Database::fetchOne('SELECT id, title, status, jira_issue_key FROM entries WHERE id=?', [$entry['parent_id']])
            : null;
        $subTickets = Database::fetchAll(
            'SELECT id, title, status, priority, jira_issue_key, zentao_bug_id FROM entries WHERE parent_id=? AND is_merged=0 ORDER BY id',
            [(int)$id]
        );
        // Load project-level Jira destinations
        $jiraConfigs = Database::fetchAll(
            'SELECT jira_project_key, label, issue_type FROM project_jira_configs WHERE project_id=? ORDER BY sort_order, id',
            [(int)($entry['project_id'] ?? 0)]
        );
        // Load merge info
        $mergedEntries = Database::fetchAll(
            'SELECT id, title, jira_issue_key, merged_at FROM entries WHERE merged_into_id=? AND is_merged=1 ORDER BY merged_at DESC',
            [(int)$id]
        );
        $mergedInto = null;
        if (!empty($entry['merged_into_id'])) {
            $mergedInto = Database::fetchOne('SELECT id, title FROM entries WHERE id=?', [$entry['merged_into_id']]);
        }
        // Load tags for this entry (safe fallback if table not yet migrated)
        try {
            $entryTags = Database::fetchAll(
                'SELECT t.id, t.name, t.color FROM tags t JOIN entry_tags et ON et.tag_id=t.id WHERE et.entry_id=? ORDER BY t.name',
                [(int)$id]
            );
            $allTags = Database::fetchAll('SELECT id, name, color FROM tags ORDER BY name');
        } catch (Throwable $e) {
            $entryTags = [];
            $allTags   = [];
        }
        $linkedEightD = Database::fetchAll(
            "SELECT id, reference, title, status FROM eight_d_reports WHERE entry_id=? ORDER BY created_at DESC",
            [(int)$id]
        );

        Audit::access('entry', (int)$id, 'view');
        View::render('entries/show', array_merge(
            compact('entry', 'attachments', 'comments', 'history', 'customFields', 'customMap',
            'isTodo', 'todoData', 'testItems', 'linkedEntries', 'settings', 'currentUser', 'jiraComments', 'zentaoActions',
            'entryTags', 'allTags', 'mergedEntries', 'mergedInto', 'jiraConfigs', 'parentEntry', 'subTickets', 'entryEpic', 'linkedEightD'),
            [
                'title'           => $entry['title'] ?: substr($entry['description'], 0, 50),
                'isTestEntry'     => $isTestEntry,
                'trAttachments'   => $trAttachments ?? [],
                'testResults'     => $testResults,
                'testOutcomes'    => $testOutcomes,
                'testCycles'      => $testCycles,
                'testMowers'      => $testMowers,
                'testCycleLinked' => $testCycleLinked,
                'testCaseLinked'  => $testCaseLinked,
            ]
        ));
    }

    // ?? Create ???????????????????????????????????????????????????
    public static function integrations(): void {
        Auth::requireView('entries');
        $filter = $_GET['filter'] ?? 'all';
        $baseWhere = 'e.deleted_at IS NULL';
        [$gfClause, $gfParams] = Auth::globalFilterClause('e');
        if ($gfClause !== '1=1') $baseWhere .= ' AND ' . $gfClause;
        if (!Auth::isAdmin()) {
            [$ac, $ap] = Auth::entryAccessClause();
            $baseWhere .= ' AND ' . $ac;
            $gfParams = array_merge($gfParams, $ap);
        }
        $conditions = [
            'robodoc_only'   => 'e.jira_issue_key IS NULL AND (e.zentao_bug_id IS NULL OR e.zentao_bug_id=0)',
            'jira_only'      => 'e.jira_issue_key IS NOT NULL AND (e.zentao_bug_id IS NULL OR e.zentao_bug_id=0)',
            'zentao_only'    => 'e.jira_issue_key IS NULL AND e.zentao_bug_id IS NOT NULL AND e.zentao_bug_id>0',
            'both'           => 'e.jira_issue_key IS NOT NULL AND e.zentao_bug_id IS NOT NULL AND e.zentao_bug_id>0',
            'all'            => '1=1',
        ];
        $filterCond = $conditions[$filter] ?? '1=1';
        $entries = Database::fetchAll(
            "SELECT e.id, e.title, e.status, e.priority, e.created_at,
                    e.jira_issue_key, e.jira_issue_url, e.jira_has_changes,
                    e.zentao_bug_id, e.zentao_bug_url, e.zentao_status,
                    p.name project_name, p.color project_color
             FROM entries e LEFT JOIN projects p ON p.id=e.project_id
             WHERE $baseWhere AND $filterCond
             ORDER BY e.created_at DESC LIMIT 500",
            $gfParams
        );
        // Counts per filter
        $counts = [];
        foreach ($conditions as $key => $cond) {
            $row = Database::fetchOne("SELECT COUNT(*) c FROM entries e WHERE $baseWhere AND $cond", $gfParams);
            $counts[$key] = (int)($row['c'] ?? 0);
        }
        View::render('entries/integrations', compact('entries', 'filter', 'counts') + ['title' => 'Integration Overview']);
    }

    public static function create(): void {
        Auth::require();
        if (!Auth::canEdit('entries') && !Auth::canOwn('entries')) {
            abort(403, 'Keine Berechtigung zum Erstellen von Einträgen.');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $data = self::extractFields();
            if (empty($data['project_id']) || empty($data['entry_type_id'])) {
                Auth::flash('error', 'Project and type are required.');
                redirect('/entries/create' . (isset($_GET['project_id']) ? '?project_id=' . (int)$_GET['project_id'] : ''));
            }
            try {
                $id = Database::insert(
                    'INSERT INTO entries (project_id,entry_type_id,error_category_id,entry_date,entry_time,title,description,
                        firmware_version,app_version,mower_serial,project_status_robot,gps_lat,gps_lon,environment_id,is_private,is_key_question,status,priority,assigned_to,created_by,epic_id,parent_id,is_report_relevant)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$data['project_id'], $data['entry_type_id'], $data['error_category_id'] ?: null,
                     $data['entry_date'], $data['entry_time'], $data['title'],
                     $data['description'], $data['firmware_version'], $data['app_version'],
                     $data['mower_serial'], $data['project_status_robot'],
                     $data['gps_lat'] ?: null, $data['gps_lon'] ?: null,
                     $data['environment_id'] ?: null, $data['is_private'] ? 1 : 0,
                     $data['is_key_question'] ? 1 : 0,
                     $data['status'], $data['priority'], $data['assigned_to'] ?: null,
                     Auth::id()]
                );
            } catch (Throwable) {
                $id = Database::insert(
                    'INSERT INTO entries (project_id,entry_type_id,error_category_id,entry_date,entry_time,title,description,
                        firmware_version,app_version,mower_serial,project_status_robot,gps_lat,gps_lon,environment_id,is_private,status,priority,created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$data['project_id'], $data['entry_type_id'], $data['error_category_id'] ?: null,
                     $data['entry_date'], $data['entry_time'], $data['title'],
                     $data['description'], $data['firmware_version'], $data['app_version'],
                     $data['mower_serial'], $data['project_status_robot'],
                     $data['gps_lat'] ?: null, $data['gps_lon'] ?: null,
                     $data['environment_id'] ?: null, $data['is_private'] ? 1 : 0,
                     $data['status'], $data['priority'], Auth::id()]
                );
            }
            self::saveCustomValues($id, $_POST['custom'] ?? []);
            self::saveNewFields($id, $data);

            if ($_POST['mark_todo'] ?? '') {
                Database::execute(
                    'INSERT IGNORE INTO entry_todos (entry_id, user_id) VALUES (?, ?)',
                    [$id, Auth::id()]
                );
            }

            if (!empty($_FILES['files']['name'][0])) {
                self::handleUploads($id, $_FILES['files']);
            }

            // Email notification
            try {
                $fullEntry = self::findOr404($id);
                Mailer::notifyNewEntry($fullEntry);
            } catch (Throwable) {}

            // Auto-create Jira issue if checked ? or link an existing key
            // Never auto-push test entries to Jira
            $isTestEntry = !empty($_POST['is_test_entry']);
            $autoJira = !$isTestEntry && !empty($_POST['jira_auto_create']);
            $jiraKeyRaw = strtoupper(trim($_POST['jira_issue_key'] ?? ''));
            if ($autoJira && appSetting('jira_url')) {
                try { JiraController::createForEntry($id); } catch (Throwable) {}
            } elseif ($jiraKeyRaw) {
                $base    = rtrim(appSetting('jira_url', ''), '/');
                $jiraUrl = $base ? $base . '/browse/' . $jiraKeyRaw : '';
                Database::execute(
                    'UPDATE entries SET jira_issue_key=?, jira_issue_url=? WHERE id=?',
                    [$jiraKeyRaw, $jiraUrl, $id]
                );
            }

            // Save partial results FIRST (use UPSERT to preserve IDs for attachment links)
            if (isset($_POST['tr_setup']) && is_array($_POST['tr_setup'])) {
                // Get existing TR rows to reuse their IDs
                $existingTrs = Database::fetchAll(
                    'SELECT id, sort_order FROM entry_test_results WHERE entry_id=? ORDER BY sort_order',
                    [(int)$id]
                );
                $existingIds = array_column($existingTrs, 'id', 'sort_order');
                $submittedIdxs = [];
                foreach ($_POST['tr_setup'] as $i => $setup) {
                    if (empty($setup) && empty($_POST['tr_doc'][$i] ?? '') && empty($_POST['tr_result'][$i] ?? '')) continue;
                    $submittedIdxs[] = $i;
                    if (isset($existingIds[$i])) {
                        // Update existing row — ID stays the same, attachments stay linked
                        Database::execute(
                            'UPDATE entry_test_results SET test_setup=?,test_doc=?,test_result=?,mower_serial=?,notes=?,sort_order=? WHERE id=?',
                            [trim($setup), trim($_POST['tr_doc'][$i]??''), trim($_POST['tr_result'][$i]??''), trim($_POST['tr_serial'][$i]??''), trim($_POST['tr_notes'][$i]??''), (int)$i, $existingIds[$i]]
                        );
                    } else {
                        // New row
                        Database::execute(
                            'INSERT INTO entry_test_results (entry_id,sort_order,test_setup,test_doc,test_result,mower_serial,notes) VALUES (?,?,?,?,?,?,?)',
                            [(int)$id, (int)$i, trim($setup), trim($_POST['tr_doc'][$i]??''), trim($_POST['tr_result'][$i]??''), trim($_POST['tr_serial'][$i]??''), trim($_POST['tr_notes'][$i]??'')]
                        );
                    }
                }
                // Delete removed rows (but keep attachments orphaned rather than deleted)
                foreach ($existingTrs as $exTr) {
                    if (!in_array((int)$exTr['sort_order'], $submittedIdxs)) {
                        Database::execute('DELETE FROM entry_test_results WHERE id=?', [$exTr['id']]);
                    }
                }
            }
            // Save test cycle link
            if (array_key_exists('test_cycle_id', $_POST)) {
                Database::execute('UPDATE entries SET test_cycle_id=?, test_plan_item_id_ref=? WHERE id=?',
                    [(int)($_POST['test_cycle_id']??0)?:null, (int)($_POST['test_plan_item_id_ref']??0)?:null, (int)$id]);
            }

            // Upload attachments for partial results (AFTER saving tr rows so IDs exist)
            if (!empty($_FILES['tr_file_new'])) {
                $trRows = Database::fetchAll(
                    'SELECT id, sort_order FROM entry_test_results WHERE entry_id=? ORDER BY sort_order',
                    [(int)$id]
                );
                $trIdxMap = [];
                foreach ($trRows as $trRow) {
                    $trIdxMap[(int)$trRow['sort_order']] = (int)$trRow['id'];
                }

                $trNames = $_FILES['tr_file_new']['name']     ?? [];
                $trTmps  = $_FILES['tr_file_new']['tmp_name'] ?? [];
                $trTypes = $_FILES['tr_file_new']['type']     ?? [];
                $trErrs  = $_FILES['tr_file_new']['error']    ?? [];
                $trSizes = $_FILES['tr_file_new']['size']     ?? [];
                foreach ($trNames as $trIdx => $fileGroup) {
                    if (!is_array($fileGroup)) continue;
                    $trResultId = $trIdxMap[(int)$trIdx] ?? null;
                    if (!$trResultId) continue;
                    foreach ($fileGroup as $fi => $fname) {
                        if (empty($fname) || ($trErrs[$trIdx][$fi] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                        $safe = preg_replace('/[^a-z0-9._-]/', '_', strtolower(basename($fname)));
                        if (!is_dir(rtrim(UPLOAD_DIR,'/'))) mkdir(rtrim(UPLOAD_DIR,'/'), 0755, true);
                        $dest = UPLOAD_DIR . $id . '_tr' . $trResultId . '_' . uniqid() . '_' . $safe;
                        if (move_uploaded_file($trTmps[$trIdx][$fi] ?? '', $dest)) {
                            Database::execute(
                                'INSERT INTO entry_attachments (entry_id, test_result_id, filename, original_name, file_path, mime_type, file_size) VALUES (?,?,?,?,?,?,?)',
                                [(int)$id, $trResultId, basename($dest), $fname, $dest,
                                 $trTypes[$trIdx][$fi] ?? 'application/octet-stream',
                                 $trSizes[$trIdx][$fi] ?? 0]
                            );
                        }
                    }
                }
            }

            Audit::log('entry_created', 'entry', (int)$id, $data['title'] ?? '');
            flash('success', 'Eintrag erstellt.');
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                json(['redirect' => url('entries/' . $id)]);
            }
            redirect('/entries/' . $id);
        }

        [$projects, $entryTypes, $categories, $environments, $customFields, $statuses, $checklists, $users, $testAreas, $activeSession] = self::formData();
        $currentUser = Database::fetchOne('SELECT jira_auto_create FROM users WHERE id=?', [Auth::id()]);
        $settings = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $data = [
            'entry_date'          => date('Y-m-d'),
            'entry_time'          => date('H:i'),
            'project_id'          => (int)($_GET['project_id'] ?? 0),
            'epic_id'             => (int)($_GET['epic_id']    ?? 0),
            'parent_id'           => (int)($_GET['parent_id']  ?? 0),
            'is_report_relevant'  => 1,
            'jira_auto_create'    => (bool)($currentUser['jira_auto_create'] ?? false),
        ];
        // Load epics and top-level entries for epic/parent selectors
        $epics   = Database::fetchAll('SELECT id, title, color FROM epics ORDER BY title');
        $parents = Database::fetchAll(
            'SELECT e.id, e.title, p.name project_name FROM entries e
             LEFT JOIN projects p ON p.id=e.project_id
             WHERE e.parent_id IS NULL AND e.is_merged=0
             ORDER BY e.entry_date DESC, e.id DESC LIMIT 200'
        );
        // Test Result extras
        $testCycles     = Database::fetchAll('SELECT tc.id, tc.name, tp.name plan_name FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id ORDER BY tc.created_at DESC LIMIT 100');
        $testOutcomes   = array_filter(array_map('trim', explode(',', appSetting('test_result_outcomes', 'Passed,Failed,Blocked,Partial,Not Run'))));
        $testMowers     = Database::fetchAll('SELECT id, label, serial_number FROM test_mowers ORDER BY label');
        View::render('entries/create', compact('projects','entryTypes','categories','environments','customFields','statuses','checklists','users','testAreas','activeSession','data','settings','epics','parents','testCycles','testOutcomes','testMowers') + ['title' => 'Neuer Eintrag']);
    }

    // ?? Edit ????????????????????????????????????????????????????
    public static function edit(string $id): void {
        Auth::requireView('entries');
        $entry = self::findOr404((int)$id);
        self::checkPrivacy($entry);
        Auth::requireEditEntry($entry);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $data = self::extractFields();
            self::logHistory((int)$id, $entry, $data);
            try {
                Database::execute(
                    'UPDATE entries SET project_id=?,entry_type_id=?,error_category_id=?,entry_date=?,entry_time=?,title=?,description=?,
                        firmware_version=?,app_version=?,mower_serial=?,project_status_robot=?,gps_lat=?,gps_lon=?,environment_id=?,is_private=?,is_key_question=?,status=?,priority=?,assigned_to=?
                     WHERE id=?',
                    [$data['project_id'], $data['entry_type_id'], $data['error_category_id'] ?: null,
                     $data['entry_date'], $data['entry_time'], $data['title'],
                     $data['description'], $data['firmware_version'], $data['app_version'],
                     $data['mower_serial'], $data['project_status_robot'],
                     $data['gps_lat'] ?: null, $data['gps_lon'] ?: null,
                     $data['environment_id'] ?: null, $data['is_private'] ? 1 : 0,
                     $data['is_key_question'] ? 1 : 0,
                     $data['status'], $data['priority'], $data['assigned_to'] ?: null,
                     (int)$id]
                );
            } catch (Throwable) {
                Database::execute(
                    'UPDATE entries SET project_id=?,entry_type_id=?,error_category_id=?,entry_date=?,entry_time=?,title=?,description=?,
                        firmware_version=?,app_version=?,mower_serial=?,project_status_robot=?,gps_lat=?,gps_lon=?,environment_id=?,is_private=?,status=?,priority=?
                     WHERE id=?',
                    [$data['project_id'], $data['entry_type_id'], $data['error_category_id'] ?: null,
                     $data['entry_date'], $data['entry_time'], $data['title'],
                     $data['description'], $data['firmware_version'], $data['app_version'],
                     $data['mower_serial'], $data['project_status_robot'],
                     $data['gps_lat'] ?: null, $data['gps_lon'] ?: null,
                     $data['environment_id'] ?: null, $data['is_private'] ? 1 : 0,
                     $data['status'], $data['priority'],
                     (int)$id]
                );
            }
            self::saveCustomValues((int)$id, $_POST['custom'] ?? []);
            self::saveNewFields((int)$id, $data);

            // Manual Jira link
            $jiraKey = strtoupper(trim($_POST['jira_issue_key'] ?? ''));
            if (isset($_POST['jira_issue_key'])) {
                $jiraUrl = '';
                if ($jiraKey) {
                    $base    = rtrim(appSetting('jira_url', ''), '/');
                    $jiraUrl = $base ? $base . '/browse/' . $jiraKey : ($entry['jira_issue_url'] ?? '');
                }
                Database::execute(
                    'UPDATE entries SET jira_issue_key=?, jira_issue_url=? WHERE id=?',
                    [$jiraKey ?: null, $jiraUrl ?: null, (int)$id]
                );
            }

            if (!empty($_FILES['files']['name'][0])) {
                self::handleUploads((int)$id, $_FILES['files']);
            }

            // Save partial results FIRST (use UPSERT to preserve IDs for attachment links)
            if (isset($_POST['tr_setup']) && is_array($_POST['tr_setup'])) {
                // Get existing TR rows to reuse their IDs
                $existingTrs = Database::fetchAll(
                    'SELECT id, sort_order FROM entry_test_results WHERE entry_id=? ORDER BY sort_order',
                    [(int)$id]
                );
                $existingIds = array_column($existingTrs, 'id', 'sort_order');
                $submittedIdxs = [];
                foreach ($_POST['tr_setup'] as $i => $setup) {
                    if (empty($setup) && empty($_POST['tr_doc'][$i] ?? '') && empty($_POST['tr_result'][$i] ?? '')) continue;
                    $submittedIdxs[] = $i;
                    if (isset($existingIds[$i])) {
                        // Update existing row — ID stays the same, attachments stay linked
                        Database::execute(
                            'UPDATE entry_test_results SET test_setup=?,test_doc=?,test_result=?,mower_serial=?,notes=?,sort_order=? WHERE id=?',
                            [trim($setup), trim($_POST['tr_doc'][$i]??''), trim($_POST['tr_result'][$i]??''), trim($_POST['tr_serial'][$i]??''), trim($_POST['tr_notes'][$i]??''), (int)$i, $existingIds[$i]]
                        );
                    } else {
                        // New row
                        Database::execute(
                            'INSERT INTO entry_test_results (entry_id,sort_order,test_setup,test_doc,test_result,mower_serial,notes) VALUES (?,?,?,?,?,?,?)',
                            [(int)$id, (int)$i, trim($setup), trim($_POST['tr_doc'][$i]??''), trim($_POST['tr_result'][$i]??''), trim($_POST['tr_serial'][$i]??''), trim($_POST['tr_notes'][$i]??'')]
                        );
                    }
                }
                // Delete removed rows (but keep attachments orphaned rather than deleted)
                foreach ($existingTrs as $exTr) {
                    if (!in_array((int)$exTr['sort_order'], $submittedIdxs)) {
                        Database::execute('DELETE FROM entry_test_results WHERE id=?', [$exTr['id']]);
                    }
                }
            }
            // Save test cycle link
            if (array_key_exists('test_cycle_id', $_POST)) {
                Database::execute('UPDATE entries SET test_cycle_id=?, test_plan_item_id_ref=? WHERE id=?',
                    [(int)($_POST['test_cycle_id']??0)?:null, (int)($_POST['test_plan_item_id_ref']??0)?:null, (int)$id]);
            }

            // Upload attachments for partial results (AFTER saving tr rows so IDs exist)
            if (!empty($_FILES['tr_file_new'])) {
                $trRows = Database::fetchAll(
                    'SELECT id, sort_order FROM entry_test_results WHERE entry_id=? ORDER BY sort_order',
                    [(int)$id]
                );
                $trIdxMap = [];
                foreach ($trRows as $trRow) {
                    $trIdxMap[(int)$trRow['sort_order']] = (int)$trRow['id'];
                }

                $trNames = $_FILES['tr_file_new']['name']     ?? [];
                $trTmps  = $_FILES['tr_file_new']['tmp_name'] ?? [];
                $trTypes = $_FILES['tr_file_new']['type']     ?? [];
                $trErrs  = $_FILES['tr_file_new']['error']    ?? [];
                $trSizes = $_FILES['tr_file_new']['size']     ?? [];
                foreach ($trNames as $trIdx => $fileGroup) {
                    if (!is_array($fileGroup)) continue;
                    $trResultId = $trIdxMap[(int)$trIdx] ?? null;
                    if (!$trResultId) continue;
                    foreach ($fileGroup as $fi => $fname) {
                        if (empty($fname) || ($trErrs[$trIdx][$fi] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                        $safe = preg_replace('/[^a-z0-9._-]/', '_', strtolower(basename($fname)));
                        if (!is_dir(rtrim(UPLOAD_DIR,'/'))) mkdir(rtrim(UPLOAD_DIR,'/'), 0755, true);
                        $dest = UPLOAD_DIR . $id . '_tr' . $trResultId . '_' . uniqid() . '_' . $safe;
                        if (move_uploaded_file($trTmps[$trIdx][$fi] ?? '', $dest)) {
                            Database::execute(
                                'INSERT INTO entry_attachments (entry_id, test_result_id, filename, original_name, file_path, mime_type, file_size) VALUES (?,?,?,?,?,?,?)',
                                [(int)$id, $trResultId, basename($dest), $fname, $dest,
                                 $trTypes[$trIdx][$fi] ?? 'application/octet-stream',
                                 $trSizes[$trIdx][$fi] ?? 0]
                            );
                        }
                    }
                }
            }

            Audit::log('entry_updated', 'entry', (int)$id);
            flash('success', 'Eintrag aktualisiert.');
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                json(['redirect' => url('entries/' . $id)]);
            }
            redirect('/entries/' . $id);
        }

        [$projects, $entryTypes, $categories, $environments, $customFields, $statuses, $checklists, $users, $testAreas, $activeSession] = self::formData();
        $customValues = Database::fetchAll('SELECT * FROM entry_custom_values WHERE entry_id = ?', [(int)$id]);
        $customMap    = array_column($customValues, 'value', 'field_id');
        $settings     = array_column(Database::fetchAll('SELECT setting_key, setting_value FROM app_settings'), 'setting_value', 'setting_key');
        $data         = $entry;
        
        // Detect Test Result Entry for edit form
        $trTypeIds   = array_filter(array_map('intval', explode(',', appSetting('test_result_entry_type_ids',''))));
        $isTestEntry = !empty($trTypeIds) && in_array((int)($data['entry_type_id']??0), $trTypeIds);
        if (!$isTestEntry) {
            $etName2 = Database::fetchOne('SELECT name FROM entry_types WHERE id=?', [(int)($data['entry_type_id']??0)]);
            if ($etName2 && strtolower($etName2['name']) === 'test result') $isTestEntry = true;
        }
        $testResults  = [];
        if ($isTestEntry) {
            $rows = Database::fetchAll('SELECT * FROM entry_test_results WHERE entry_id=? ORDER BY sort_order', [(int)$id]);
            foreach ($rows as &$row) {
                $row['attachments'] = Database::fetchAll('SELECT * FROM entry_attachments WHERE entry_id=? AND test_result_id=? ORDER BY created_at', [(int)$id, $row['id']]);
            }
            $testResults = $rows;
        }
        $testOutcomes = array_filter(array_map('trim', explode(',', appSetting('test_result_outcomes', 'Passed,Failed,Blocked,Partial,Not Run'))));
        $testCycles   = Database::fetchAll('SELECT tc.id, tc.name, tp.name plan_name FROM test_cycles tc LEFT JOIN test_plans tp ON tp.id=tc.test_plan_id ORDER BY tc.created_at DESC LIMIT 100');
        $testMowers   = Database::fetchAll('SELECT id, label, serial_number FROM test_mowers ORDER BY label');
View::render('entries/edit', compact('entry','data','projects','entryTypes','categories','environments',
            'customFields','customMap','statuses','checklists','users','testAreas','activeSession','settings') + [
            'title'        => 'Eintrag bearbeiten',
            'errors'       => [],
            'isTestEntry'  => $isTestEntry,
            'testResults'  => $testResults,
            'testOutcomes' => $testOutcomes,
            'testCycles'   => $testCycles,
            'testMowers'   => $testMowers,
        ]);
    }

    // ?? Delete ???????????????????????????????????????????????????
    // ?? AJAX status update (used by Kanban drag & drop) ?????????
    public static function updateStatus(string $id): void {
        Auth::require();
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $status = $_POST['status'] ?? '';
        if (!array_key_exists($status, entryStatuses())) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status']);
            exit;
        }
        $entry = Database::fetchOne('SELECT id, created_by, assigned_to FROM entries WHERE id=?', [(int)$id]);
        if (!$entry) {
            http_response_code(404);
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }
        // Allow if user has kanban edit OR entries edit OR owns the entry
        $canEdit = Auth::canEdit('kanban') || Auth::canEdit('entries') || Auth::canEditEntry($entry);
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['error' => 'Keine Bearbeitungsrechte']);
            exit;
        }
        Database::execute('UPDATE entries SET status=? WHERE id=?', [$status, (int)$id]);
        echo json_encode(['success' => true, 'status' => $status, 'label' => entryStatuses()[$status]]);
        exit;
    }

    public static function toggleReportRelevant(string $id): void {
        Auth::require();
        Auth::verifyCsrf();
        $entry = self::findOr404((int)$id);
        $new = $entry['is_report_relevant'] ? 0 : 1;
        Database::execute('UPDATE entries SET is_report_relevant=? WHERE id=?', [$new, (int)$id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            json(['is_report_relevant' => $new]);
        }
        redirect('/entries/' . $id);
    }

    public static function delete(string $id): void {
        Auth::requireView('entries');
        $entry = self::findOr404((int)$id);
        Auth::requireEditEntry($entry);
        Auth::verifyCsrf();
        Audit::log('entry_deleted', 'entry', (int)$id, $entry['title'] ?? '');
        Database::execute('DELETE FROM entries WHERE id = ?', [(int)$id]);
        flash('success', 'Entry deleted.');
        redirect('/entries');
    }

    // ?? Duplicate ????????????????????????????????????????????????
    // POST /entries/{id}/set-epic
    public static function setEpic(string $id): void {
        Auth::requireEdit('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $epicId = (int)($_POST['epic_id'] ?? 0);
        $epic   = $epicId ? Database::fetchOne('SELECT id, title, color FROM epics WHERE id=?', [$epicId]) : null;
        if ($epicId && !$epic) { http_response_code(404); echo json_encode(['error'=>'Epic not found']); exit; }
        Database::execute('UPDATE entries SET epic_id=? WHERE id=?', [$epicId ?: null, (int)$id]);
        echo json_encode(['success' => true, 'epic' => $epic]);
        exit;
    }

    // POST /entries/{id}/unset-epic
    public static function unsetEpic(string $id): void {
        Auth::requireEdit('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        Database::execute('UPDATE entries SET epic_id=NULL WHERE id=?', [(int)$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // POST /entries/{id}/set-parent   attach this entry as sub-ticket of another
    public static function setParent(string $id): void {
        Auth::requireEdit('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $childId  = (int)$id;
        $parentId = (int)($_POST['parent_id'] ?? 0);
        if (!$parentId || $parentId === $childId) {
            http_response_code(422); echo json_encode(['error' => 'Invalid parent']); exit;
        }
        // Prevent circular references: parent must not be a descendant of child
        $visited = [$childId];
        $check = $parentId;
        while ($check) {
            if (in_array($check, $visited)) {
                http_response_code(422); echo json_encode(['error' => 'Circular reference detected']); exit;
            }
            $visited[] = $check;
            $row = Database::fetchOne('SELECT parent_id FROM entries WHERE id=?', [$check]);
            $check = (int)($row['parent_id'] ?? 0);
        }
        $parent = Database::fetchOne('SELECT id, title FROM entries WHERE id=? AND is_merged=0', [$parentId]);
        if (!$parent) { http_response_code(404); echo json_encode(['error' => 'Parent not found']); exit; }
        Database::execute('UPDATE entries SET parent_id=? WHERE id=?', [$parentId, $childId]);
        echo json_encode(['success' => true, 'parent' => $parent]);
        exit;
    }

    // POST /entries/{id}/unset-parent   detach sub-ticket from parent
    public static function unsetParent(string $id): void {
        Auth::requireEdit('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        Database::execute('UPDATE entries SET parent_id=NULL WHERE id=?', [(int)$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // GET /entries/{id}/merge-preview ? search + preview merge
    public static function mergePreview(string $id): void {
        Auth::requireEdit('entries');
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        if (!$q) { echo json_encode([]); exit; }
        $entries = Database::fetchAll(
            "SELECT e.id, e.title, e.status, e.priority, e.jira_issue_key, e.parent_id,
                    (SELECT COUNT(*) FROM entries s WHERE s.parent_id = e.id) AS sub_count
             FROM entries e
             WHERE (e.title LIKE ? OR e.jira_issue_key LIKE ? OR e.id=?)
             AND e.id != ? AND e.is_merged=0
             ORDER BY e.created_at DESC LIMIT 15",
            ['%'.$q.'%', '%'.$q.'%', (int)$q, (int)$id]
        );
        echo json_encode($entries);
        exit;
    }

    // POST /entries/{id}/merge ? execute merge
    public static function merge(string $id): void {
        Auth::requireEdit('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $targetId   = (int)($_POST['target_id'] ?? 0);
        $primaryId  = (int)($_POST['primary_id'] ?? $id); // which becomes the main ticket
        $fields     = (array)($_POST['fields'] ?? []);    // fields to copy from secondary
        if (!$targetId || $targetId === (int)$id) {
            http_response_code(422); echo json_encode(['error' => 'Invalid target']); exit;
        }
        $sourceId   = $primaryId === (int)$id ? $targetId : (int)$id;
        $primary    = Database::fetchOne('SELECT * FROM entries WHERE id=? AND is_merged=0', [$primaryId]);
        $secondary  = Database::fetchOne('SELECT * FROM entries WHERE id=? AND is_merged=0', [$sourceId]);
        if (!$primary || !$secondary) {
            http_response_code(404); echo json_encode(['error' => 'Entry not found']); exit;
        }
        // Mergeable fields: text fields support replace or append mode
        $textFields    = ['title','description','summary','steps_to_reproduce','expected_result','actual_result',
                          'firmware_version','app_version','mower_serial','weather_condition','temperature'];
        $replaceFields = ['priority','assigned_to','environment_id','test_area_id',
                          'entry_type_id','error_category_id','status',
                          'gps_lat','gps_lon','entry_date','entry_time'];
        $mergeableFields = array_merge($textFields, $replaceFields);
        // Attachments: always merged (they are moved, not replaced)
        $mergeAttachments = in_array('attachments', $fields);
        // $fields = array of field keys, $_POST['field_mode'][field] = 'replace'|'append'
        $fieldModes = (array)($_POST['field_mode'] ?? []);
        $updates = [];
        $appends = []; // fields to CONCAT instead of replace
        foreach ($fields as $field) {
            if (!in_array($field, $mergeableFields) || !isset($secondary[$field])) continue;
            $mode = $fieldModes[$field] ?? 'replace';
            if (in_array($field, $textFields) && $mode === 'append') {
                $appends[$field] = $secondary[$field];
            } else {
                $updates[$field] = $secondary[$field];
            }
        }
        if ($updates) {
            $setClauses = implode(',', array_map(fn($f) => "$f=?", array_keys($updates)));
            Database::execute("UPDATE entries SET $setClauses WHERE id=?",
                array_merge(array_values($updates), [$primaryId]));
        }
        if ($appends) {
            foreach ($appends as $field => $value) {
                $current = Database::fetchOne("SELECT $field FROM entries WHERE id=?", [$primaryId]);
                $merged  = ($current[$field] ?? '') . "\n\n--- Merged from #$sourceId ---\n" . $value;
                Database::execute("UPDATE entries SET $field=? WHERE id=?", [$merged, $primaryId]);
            }
        }
        try {
            // Merge comments
            Database::execute('UPDATE entry_comments SET entry_id=? WHERE entry_id=?', [$primaryId, $sourceId]);
        } catch (Throwable) {}
        if (!empty($mergeAttachments)) {
            try {
                // Load attachment IDs before moving so we can clear their thumb caches
                $movedAtts = Database::fetchAll('SELECT id FROM entry_attachments WHERE entry_id=?', [$sourceId]);
                Database::execute('UPDATE entry_attachments SET entry_id=? WHERE entry_id=?', [$primaryId, $sourceId]);
                // Clear thumb cache for moved attachments - the thumb path uses att ID
                // and the source file may live in a different directory than expected
                foreach ($movedAtts as $ma) {
                    $thumbFile = UPLOAD_DIR . 'thumbs/' . $ma['id'] . '.jpg';
                    if (file_exists($thumbFile)) @unlink($thumbFile);
                }
            } catch (Throwable) {}
        }
        try {
            // Merge tags
            $secTags = Database::fetchAll('SELECT tag_id FROM entry_tags WHERE entry_id=?', [$sourceId]);
            foreach ($secTags as $t) {
                Database::execute('INSERT IGNORE INTO entry_tags (entry_id, tag_id) VALUES (?,?)', [$primaryId, $t['tag_id']]);
            }
        } catch (Throwable) {}
        // Add merge comment to primary
        $mergeNote = 'Merged with entry #'.$sourceId.' "'.addslashes($secondary['title']).'"';
        if (!empty($secondary['jira_issue_key'])) $mergeNote .= ' (Jira: '.$secondary['jira_issue_key'].')';
        try {
            Database::execute('INSERT INTO entry_comments (entry_id, user_id, content, created_at) VALUES (?,?,?,NOW())',
                [$primaryId, Auth::id(), $mergeNote]);
        } catch (Throwable) {}
        // Mark secondary as merged
        Database::execute('UPDATE entries SET is_merged=1, merged_into_id=?, merged_at=NOW(), merged_by=? WHERE id=?',
            [$primaryId, Auth::id(), $sourceId]);
        echo json_encode(['success' => true, 'primary_id' => $primaryId, 'merged_id' => $sourceId]);
        exit;
    }

    public static function duplicate(string $id): void {
        Auth::requireView('entries');
        Auth::verifyCsrf();
        $entry = self::findOr404((int)$id);
        Auth::requireEditEntry($entry);
        $newId = Database::insert(
            'INSERT INTO entries (project_id,entry_type_id,error_category_id,entry_date,entry_time,title,description,
                firmware_version,app_version,mower_serial,project_status_robot,environment_id,is_private,created_by)
             SELECT project_id,entry_type_id,error_category_id,?,?,CONCAT("[Kopie] ",COALESCE(title,"")),description,
                firmware_version,app_version,mower_serial,project_status_robot,environment_id,is_private,?
             FROM entries WHERE id = ?',
            [date('Y-m-d'), date('H:i:s'), Auth::id(), (int)$id]
        );
        flash('success', 'Eintrag dupliziert.');
        redirect('/entries/' . $newId);
    }

    // ?? Comments ?????????????????????????????????????????????????
    public static function addComment(string $id): void {
        Auth::requireView('entries');
        Auth::verifyCsrf();
        $entry = self::findOr404((int)$id);
        $body  = trim($_POST['body'] ?? '');
        if (!$body) { flash('error', 'Kommentar darf nicht leer sein.'); redirect('/entries/' . $id); }
        $cid = Database::insert(
            'INSERT INTO entry_comments (entry_id, user_id, body) VALUES (?, ?, ?)',
            [(int)$id, Auth::id(), $body]
        );

        // AJAX response
        if ($_SERVER['HTTP_ACCEPT'] === 'application/json') {
            $comment = Database::fetchOne('SELECT c.*, u.name user_name FROM entry_comments c JOIN users u ON u.id=c.user_id WHERE c.id=?', [$cid]);
            ob_start();
            include __DIR__ . '/../views/entries/_comment.php';
            $html = ob_get_clean();
            json(['html' => $html]);
        }

        flash('success', 'Comment added.');
        redirect('/entries/' . $id . '#comments');
    }

    public static function deleteComment(string $id, string $cid): void {
        Auth::requireView('entries');
        Auth::verifyCsrf();
        $comment = Database::fetchOne('SELECT * FROM entry_comments WHERE id = ?', [(int)$cid]);
        if (!$comment) abort(404);
        if (!Auth::isAdmin() && $comment['user_id'] != Auth::id()) abort(403);
        Database::execute('DELETE FROM entry_comments WHERE id = ?', [(int)$cid]);
        flash('success', 'Comment deleted.');
        redirect('/entries/' . $id . '#comments');
    }

    // ?? Download all attachments as ZIP ?????????????????????????
    public static function downloadZip(string $id): void {
        Auth::requireView('entries');
        $entry = self::findOr404((int)$id);

        $attachments = Database::fetchAll(
            'SELECT * FROM entry_attachments WHERE entry_id=? ORDER BY created_at',
            [(int)$id]
        );
        if (!$attachments) { flash('info', 'This entry has no attachments.'); redirect('/entries/' . $id); }

        // Build ZIP filename: JIRAKEY_Title.zip  or  Title.zip
        $jiraKey    = trim($entry['jira_issue_key'] ?? '');
        $rawTitle   = $entry['title'] ?: ('entry-' . $id);
        $titleClean = preg_replace('/[^\w\s\-???????]/', '', $rawTitle);
        $titleClean = trim(preg_replace('/\s+/', '_', $titleClean), '_');
        $zipName    = ($jiraKey ? $jiraKey . '_' : '') . $titleClean . '.zip';

        if (!class_exists('ZipArchive')) {
            flash('error', 'ZIP export is not available (ZipArchive extension missing).');
            redirect('/entries/' . $id);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'entry_zip_');
        $zip     = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::OVERWRITE) !== true) {
            flash('error', 'Could not create ZIP file.');
            redirect('/entries/' . $id);
        }

        $added = 0;
        $seen  = [];
        foreach ($attachments as $att) {
            if (!file_exists($att['file_path'])) continue;
            $name = $att['display_name'] ?: $att['original_name'] ?: basename($att['file_path']);
            // Deduplicate names inside the ZIP
            $base = pathinfo($name, PATHINFO_FILENAME);
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $candidate = $name;
            $n = 1;
            while (isset($seen[$candidate])) {
                $candidate = $base . '_' . $n . ($ext ? '.' . $ext : '');
                $n++;
            }
            $seen[$candidate] = true;
            $zip->addFile($att['file_path'], $candidate);
            $added++;
        }
        $zip->close();

        if (!$added) {
            @unlink($tmpFile);
            flash('info', 'No attachment files found on disk.');
            redirect('/entries/' . $id);
        }

        Database::execute('UPDATE entries SET zip_downloaded_at=NOW() WHERE id=?', [(int)$id]);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . rawurlencode($zipName) . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-cache, no-store');
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    // ?? File Upload (AJAX, supports multiple files) ??????????????
    public static function upload(string $id): void {
        Auth::requireView('entries');
        // Detect PHP silently dropping POST body when post_max_size is exceeded
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxBytes  = self::iniBytes(ini_get('post_max_size'));
        if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Total upload size too large (server limit: ' . ini_get('post_max_size') . ')']);
            exit;
        }
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $entry = self::findOr404((int)$id);

        // Ensure base upload directory exists
        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
            echo json_encode(['error' => 'Upload directory could not be created: ' . UPLOAD_DIR]);
            exit;
        }

        // Accept name="files[]" (multiple) or name="file" (single)
        $raw = $_FILES['files'] ?? $_FILES['file'] ?? null;
        if (!$raw || empty($raw['tmp_name'])) {
            echo json_encode(['error' => 'No file received']); exit;
        }

        $results = [];
        $errors  = [];

        $processFile = function(array $file) use ($id, &$results, &$errors): void {
            $name = $file['name'];
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $errors[] = "$name (exceeds server upload limit ? check .user.ini)"; return;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "$name (PHP upload error {$file['error']})"; return;
            }
            if ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = "$name (file too large: " . round($file['size']/1048576, 1) . " MB)"; return;
            }
            $mime = mime_content_type($file['tmp_name']) ?: 'unknown';
            $allowed = str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/') || in_array($mime, [
                'application/pdf', 'application/zip', 'text/plain', 'text/csv',
                'application/json', 'application/octet-stream',
            ]);
            if (!$allowed) {
                $errors[] = "$name (type not allowed: $mime)"; return;
            }
            $result = self::saveFile((int)$id, $file);
            if (is_array($result)) {
                $results[] = $result;
            } else {
                $errors[] = "$name (save failed: " . ($result ?? 'unknown error') . ")";
            }
        };

        if (is_array($raw['tmp_name'])) {
            $count = count($raw['tmp_name']);
            for ($i = 0; $i < $count; $i++) {
                $processFile(['name' => $raw['name'][$i], 'type' => $raw['type'][$i],
                              'tmp_name' => $raw['tmp_name'][$i], 'error' => $raw['error'][$i], 'size' => $raw['size'][$i]]);
            }
        } else {
            $processFile($raw);
        }

        echo json_encode(['success' => count($results), 'attachments' => $results, 'errors' => $errors]);
        exit;
    }

    // ?? Entry Links ??????????????????????????????????????????????
    public static function addLink(string $id): void {
        Auth::requireView('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $linkEntry = Database::fetchOne('SELECT id, created_by, assigned_to FROM entries WHERE id=?', [(int)$id]);
        if ($linkEntry && !Auth::canEditEntry($linkEntry)) { http_response_code(403); echo json_encode(['error' => 'Keine Berechtigung']); exit; }
        $toId = (int)($_POST['to_entry_id'] ?? 0);
        if (!$toId || $toId == (int)$id) { echo json_encode(['error' => 'Invalid entry']); exit; }
        if (!Database::fetchOne('SELECT id FROM entries WHERE id=?', [$toId])) {
            echo json_encode(['error' => 'Entry not found']); exit;
        }
        // Avoid duplicates in both directions
        $exists = Database::fetchOne(
            'SELECT id FROM entry_links WHERE (from_entry_id=? AND to_entry_id=?) OR (from_entry_id=? AND to_entry_id=?)',
            [(int)$id, $toId, $toId, (int)$id]
        );
        if ($exists) { echo json_encode(['error' => 'Already linked']); exit; }
        $linkId = Database::insert(
            'INSERT INTO entry_links (from_entry_id, to_entry_id, created_by) VALUES (?,?,?)',
            [(int)$id, $toId, Auth::id()]
        );
        $linked = Database::fetchOne(
            "SELECT e.id, e.title, e.entry_date, et.name type_name, et.color type_color
             FROM entries e JOIN entry_types et ON et.id=e.entry_type_id WHERE e.id=?",
            [$toId]
        );
        $linked['link_id'] = $linkId;
        echo json_encode(['success' => true, 'entry' => $linked]);
        exit;
    }

    public static function deleteLink(string $id, string $lid): void {
        Auth::requireView('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $linkEntry = Database::fetchOne('SELECT id, created_by, assigned_to FROM entries WHERE id=?', [(int)$id]);
        if ($linkEntry && !Auth::canEditEntry($linkEntry)) abort(403);
        Database::execute('DELETE FROM entry_links WHERE id=?', [(int)$lid]);
        json(['success' => true]);
    }

    // ?? Todo toggle ??????????????????????????????????????????????
    public static function toggleTodo(string $id): void {
        Auth::requireView('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $exists = Database::fetchOne('SELECT id FROM entry_todos WHERE entry_id=? AND user_id=?', [(int)$id, Auth::id()]);
        if ($exists) {
            Database::execute('DELETE FROM entry_todos WHERE entry_id=? AND user_id=?', [(int)$id, Auth::id()]);
            $done = false;
        } else {
            Database::insert('INSERT IGNORE INTO entry_todos (entry_id, user_id) VALUES (?,?)', [(int)$id, Auth::id()]);
            $done = true;
        }
        $todo = Database::fetchOne('SELECT due_date, priority, notes FROM entry_todos WHERE entry_id=? AND user_id=?', [(int)$id, Auth::id()]);
        json(['done' => $done, 'todo' => $todo ?: null]);
    }

    // ?? Task details (todo extra fields) ????????????????????????
    public static function saveTodoDetails(string $id): void {
        Auth::requireView('entries');
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        $todo = Database::fetchOne('SELECT id FROM entry_todos WHERE entry_id=? AND user_id=?', [(int)$id, Auth::id()]);
        if (!$todo) { json(['error' => 'Not bookmarked'], 404); }
        $due      = trim($_POST['due_date'] ?? '') ?: null;
        $priority = in_array($_POST['priority'] ?? '', ['low','medium','high']) ? $_POST['priority'] : null;
        $notes    = trim($_POST['notes'] ?? '') ?: null;
        Database::execute(
            'UPDATE entry_todos SET due_date=?, priority=?, notes=? WHERE entry_id=? AND user_id=?',
            [$due, $priority, $notes, (int)$id, Auth::id()]
        );
        json(['ok' => true]);
    }

    // ?? Helpers ??????????????????????????????????????????????????
    private static function findOr404(int $id): array {
        try {
            $e = Database::fetchOne(
                "SELECT e.*, et.name type_name, et.color type_color, et.icon type_icon,
                        ec.name cat_name, ec.color cat_color,
                        p.name project_name, p.color project_color,
                        u.name creator, env.name env_name,
                        ua.name assigned_name
                 FROM entries e
                 LEFT JOIN entry_types et      ON et.id = e.entry_type_id
                 LEFT JOIN error_categories ec ON ec.id = e.error_category_id
                 LEFT JOIN projects p          ON p.id  = e.project_id
                 LEFT JOIN users u             ON u.id  = e.created_by
                 LEFT JOIN users ua            ON ua.id = e.assigned_to
                 LEFT JOIN test_environments env ON env.id = e.environment_id
                 WHERE e.id = ?",
                [$id]
            );
        } catch (Throwable) {
            // assigned_to column not yet migrated ? fall back to query without it
            $e = Database::fetchOne(
                "SELECT e.*, et.name type_name, et.color type_color, et.icon type_icon,
                        ec.name cat_name, ec.color cat_color,
                        p.name project_name, p.color project_color,
                        u.name creator, env.name env_name,
                        NULL AS assigned_name
                 FROM entries e
                 LEFT JOIN entry_types et      ON et.id = e.entry_type_id
                 LEFT JOIN error_categories ec ON ec.id = e.error_category_id
                 LEFT JOIN projects p          ON p.id  = e.project_id
                 LEFT JOIN users u             ON u.id  = e.created_by
                 LEFT JOIN test_environments env ON env.id = e.environment_id
                 WHERE e.id = ?",
                [$id]
            );
        }
        if (!$e) abort(404, 'Eintrag nicht gefunden');
        return $e;
    }

    private static function checkPrivacy(array $entry): void {
        if (Auth::isAdmin()) return;

        if ($entry['is_private'] && $entry['created_by'] != Auth::id()) {
            abort(403, 'Privater Eintrag');
        }

        $access = Auth::groupAccess();
        if ($access === null) return;

        $pid    = (int)$entry['project_id'];
        $allIds = $access['all'];
        $ownIds = $access['own'];

        if (!in_array($pid, $allIds, true) && !in_array($pid, $ownIds, true)) {
            abort(403, 'Kein Zugriff auf dieses Projekt');
        }

        if (!in_array($pid, $allIds, true) && (int)$entry['created_by'] !== Auth::id()) {
            abort(403, 'Nur eigene Einträge sichtbar');
        }
    }

    private static function extractFields(): array {
        return [
            'epic_id'             => (int)($_POST['epic_id']      ?? 0),
            'parent_id'           => (int)($_POST['parent_id']    ?? 0),
            'project_id'          => (int)($_POST['project_id']  ?? 0),
            'entry_type_id'       => (int)($_POST['entry_type_id'] ?? 0),
            'is_report_relevant' => isset($_POST['is_report_relevant']) ? 1 : 0,
            'error_category_id'  => (int)($_POST['error_category_id'] ?? 0) ?: null,
            'entry_date'          => $_POST['entry_date']          ?? date('Y-m-d'),
            'entry_time'          => $_POST['entry_time']          ?? '00:00',
            'title'               => trim($_POST['title']          ?? ''),
            'description'         => trim($_POST['description']    ?? ''),
            'firmware_version'    => trim($_POST['firmware_version'] ?? ''),
            'app_version'         => trim($_POST['app_version']    ?? ''),
            'mower_serial'        => trim($_POST['mower_serial']   ?? ''),
            'project_status_robot'=> trim($_POST['project_status_robot'] ?? ''),
            'gps_lat'             => trim($_POST['gps_lat']        ?? ''),
            'gps_lon'             => trim($_POST['gps_lon']        ?? ''),
            'environment_id'      => (int)($_POST['environment_id'] ?? 0),
            'is_private'          => !empty($_POST['is_private']),
            'is_key_question'     => !empty($_POST['is_key_question']),
            'status'              => array_key_exists($_POST['status'] ?? '', entryStatuses()) ? $_POST['status'] : 'new',
            'priority'            => in_array($_POST['priority'] ?? '', ['Low','Medium','High','Highest','Blocker']) ? $_POST['priority'] : 'Medium',
            'assigned_to'         => (int)($_POST['assigned_to'] ?? 0) ?: null,
            // new fields
            'session_id'          => (int)($_POST['session_id'] ?? 0) ?: null,
            'test_area_id'        => (int)($_POST['test_area_id'] ?? 0) ?: null,
            'temperature'         => ($_POST['temperature'] ?? '') !== '' ? (float)$_POST['temperature'] : null,
            'weather_condition'   => trim($_POST['weather_condition'] ?? '') ?: null,
        ];
    }

    private static function saveNewFields(int $entryId, array $data): void {
        // Session auto-assign: if no explicit session_id, use active session
        $sessionId = $data['session_id'];
        if (!$sessionId && class_exists('TestSessionController')) {
            $active = TestSessionController::getActive();
            if ($active) $sessionId = (int)$active['id'];
        }
        try {
            Database::execute(
                "UPDATE entries SET session_id=?, test_area_id=?, temperature=?, weather_condition=? WHERE id=?",
                [$sessionId, $data['test_area_id'], $data['temperature'], $data['weather_condition'], $entryId]
            );
        } catch (Throwable) {}
    }

    private static function saveCustomValues(int $entryId, array $values): void {
        foreach ($values as $fieldId => $value) {
            $fieldId = (int)$fieldId;
            if (!$fieldId) continue;
            Database::execute(
                'INSERT INTO entry_custom_values (entry_id, field_id, value) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [$entryId, $fieldId, $value]
            );
        }
    }

    private static function formData(): array {
        [$projSql, $projParams] = Auth::projectAccessClause();
        $projects    = Database::fetchAll("SELECT id, name, color FROM projects WHERE status='active' AND $projSql ORDER BY name", $projParams);
        $entryTypes  = Database::fetchAll("SELECT * FROM entry_types ORDER BY sort_order, name");
        $categories  = Database::fetchAll("SELECT * FROM error_categories ORDER BY sort_order, name");
        $environments = Database::fetchAll("SELECT * FROM test_environments ORDER BY name");
        $customFields = Database::fetchAll("SELECT * FROM custom_fields ORDER BY sort_order, name");
        $statuses     = json_decode(appSetting('project_statuses', '["Prototyp","EP0","EP1","EP2","MP","SOP"]'), true) ?: [];
        $checklists   = Database::fetchAll("SELECT tc.*, GROUP_CONCAT(ci.text ORDER BY ci.sort_order SEPARATOR '||') items FROM test_checklists tc LEFT JOIN checklist_items ci ON ci.checklist_id = tc.id GROUP BY tc.id");
        $users        = Database::fetchAll("SELECT id, name FROM users ORDER BY name");
        $testAreas    = Database::fetchAll("SELECT id, name FROM test_areas ORDER BY name");
        $activeSession = class_exists('TestSessionController') ? TestSessionController::getActive() : null;
        return [$projects, $entryTypes, $categories, $environments, $customFields, $statuses, $checklists, $users, $testAreas, $activeSession];
    }

    private static function logHistory(int $entryId, array $old, array $new): void {
        $fields = ['project_id', 'entry_type_id', 'error_category_id', 'title', 'description', 'firmware_version', 'app_version', 'mower_serial', 'is_private'];
        foreach ($fields as $f) {
            if (($old[$f] ?? '') != ($new[$f] ?? '')) {
                Database::insert(
                    'INSERT INTO entry_history (entry_id, user_id, field_name, old_value, new_value) VALUES (?,?,?,?,?)',
                    [$entryId, Auth::id(), $f, $old[$f] ?? '', $new[$f] ?? '']
                );
            }
        }
    }

    // ?? Quick Capture ?????????????????????????????????????????????
    public static function quickCapture(): void {
        Auth::require();
        Auth::verifyCsrf();

        if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'No file received.');
            redirect('/entries/create');
        }

        [$projSql, $projParams] = Auth::projectAccessClause();
        $project = Database::fetchOne("SELECT id FROM projects WHERE status='active' AND $projSql ORDER BY id LIMIT 1", $projParams);
        $type    = Database::fetchOne('SELECT id FROM entry_types ORDER BY sort_order, id LIMIT 1');

        if (!$project || !$type) {
            flash('error', 'Please create at least one project and one entry type first.');
            redirect('/entries/create');
        }

        $id = Database::insert(
            'INSERT INTO entries (project_id,entry_type_id,entry_date,entry_time,title,description,created_by)
             VALUES (?,?,?,?,?,?,?)',
            [$project['id'], $type['id'], date('Y-m-d'), date('H:i:s'), '', 'Quick capture', Auth::id()]
        );

        // Convert single-file array to multi-file array format for handleUploads
        $files = [
            'name'     => [$_FILES['file']['name']],
            'type'     => [$_FILES['file']['type']],
            'tmp_name' => [$_FILES['file']['tmp_name']],
            'error'    => [$_FILES['file']['error']],
            'size'     => [$_FILES['file']['size']],
        ];
        self::handleUploads($id, $files);

        redirect('/entries/' . $id . '/edit');
    }

    public static function bulkUpdate(): void {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();

        $ids = array_values(array_filter(array_map('intval', (array)($_POST['entry_ids'] ?? []))));
        if (empty($ids)) {
            flash('error', 'No entries selected.');
            redirect('/entries');
        }

        $sets   = [];
        $params = [];

        if (isset($_POST['project_id']) && $_POST['project_id'] !== '' && is_numeric($_POST['project_id'])) {
            $sets[] = 'project_id = ?'; $params[] = (int)$_POST['project_id'];
        }
        if (isset($_POST['entry_type_id']) && $_POST['entry_type_id'] !== '' && is_numeric($_POST['entry_type_id'])) {
            $sets[] = 'entry_type_id = ?'; $params[] = (int)$_POST['entry_type_id'];
        }
        if (isset($_POST['error_category_id']) && $_POST['error_category_id'] !== '' && is_numeric($_POST['error_category_id'])) {
            $sets[] = 'error_category_id = ?'; $params[] = (int)$_POST['error_category_id'];
        }
        if (!empty($_POST['entry_date'])) {
            $sets[] = 'entry_date = ?'; $params[] = $_POST['entry_date'];
        }

        if ($sets) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            Database::execute('UPDATE entries SET ' . implode(', ', $sets) . " WHERE id IN ($ph)", array_merge($params, $ids));
            flash('success', count($ids) . ' entries updated.');
        } else {
            flash('error', 'No fields selected for update.');
        }
        redirect('/entries');
    }

    public static function bulkDelete(): void {
        Auth::requireEdit('entries');
        Auth::verifyCsrf();

        $ids = array_values(array_filter(array_map('intval', (array)($_POST['entry_ids'] ?? []))));
        if (empty($ids)) {
            flash('error', 'No entries selected.');
            redirect('/entries');
        }

        $isAdmin = Auth::isAdmin();
        $deleted = 0;
        foreach ($ids as $id) {
            $entry = Database::fetchOne('SELECT id, created_by, title FROM entries WHERE id = ?', [$id]);
            if (!$entry) continue;
            if (!$isAdmin && (int)$entry['created_by'] !== Auth::id()) continue;
            Audit::log('entry_deleted', 'entry', $id, $entry['title'] ?? '');
            Database::execute('DELETE FROM entries WHERE id = ?', [$id]);
            $deleted++;
        }

        flash('success', $deleted . ' ' . ($deleted === 1 ? 'entry' : 'entries') . ' deleted.');
        redirect('/entries');
    }

    public static function handleUploads(int $entryId, array $files): void {
        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < $count; $i++) {
            $file = is_array($files['name']) ? [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ] : $files;
            if ($file['error'] !== UPLOAD_ERR_OK) continue;
            self::saveFile($entryId, $file);
        }
    }

    private static function iniBytes(string $val): int {
        $val  = trim($val);
        $last = strtolower($val[-1] ?? '');
        $num  = (int)$val;
        return match($last) { 'g' => $num * 1024 ** 3, 'm' => $num * 1024 ** 2, 'k' => $num * 1024, default => $num };
    }

    public static function saveFile(int $entryId, array $file): array|null|string {
        if ($file['size'] > MAX_FILE_SIZE) return null;
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        // Allow any image/* or video/* detected from file bytes (safe ? not extension-based).
        // Keep explicit allowlist for document types only.
        $allowed = str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/') || in_array($mime, [
            'application/pdf', 'application/zip', 'text/plain', 'text/csv',
            'application/json', 'application/octet-stream',
        ]);
        if (!$allowed) return null;

        // Derive extension from validated MIME type ? never trust user-supplied filename extension
        $mimeExtMap = [
            'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
            'image/svg+xml'=>'svg','image/heic'=>'heic','image/heif'=>'heif',
            'video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm',
            'video/x-msvideo'=>'avi','video/x-matroska'=>'mkv',
            'application/pdf'=>'pdf','application/zip'=>'zip',
            'text/plain'=>'txt','text/csv'=>'csv','application/json'=>'json',
            'application/octet-stream'=>'bin',
        ];
        $ext = $mimeExtMap[$mime] ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin');
        $fn  = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir  = UPLOAD_DIR . $entryId . '/';

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                $parent = UPLOAD_DIR;
                if (!is_dir($parent)) return "upload dir missing: $parent";
                if (!is_writable($parent)) return "upload dir not writable: $parent";
                return "cannot create subdir: $dir";
            }
        }
        if (!is_writable($dir)) return "subdir not writable: $dir";

        $dest = $dir . $fn;
        if (!move_uploaded_file($file['tmp_name'], $dest)) return "move_uploaded_file failed to: $dest";

        // Save the attachment row immediately with the original (uncompressed) file.
        // Video compression runs in the BACKGROUND (non-blocking) so large uploads on
        // mobile connections don't time out waiting for ffmpeg before the response returns.
        $id = Database::insert(
            'INSERT INTO entry_attachments (entry_id, filename, original_name, mime_type, file_size, file_path) VALUES (?,?,?,?,?,?)',
            [$entryId, $fn, $file['name'], $mime, $file['size'], $dest]
        );
        try { Database::execute('UPDATE entries SET attachments_updated_at=NOW() WHERE id=?', [$entryId]); } catch (Throwable) {}

        // Kick off background video compression (H.264+AAC MP4, ~80-90% size reduction).
        // The leading '&' + redirected I/O detaches the process so PHP does not wait for it.
        if (str_starts_with($mime, 'video/') && function_exists('exec')) {
            $compFn   = bin2hex(random_bytes(16)) . '.mp4';
            $compDest = $dir . $compFn;
            $cmd = sprintf(
                'nohup /usr/bin/ffmpeg -i %s -c:v libx264 -crf 28 -preset fast -c:a aac -b:a 128k -movflags +faststart -y %s > /dev/null 2>&1 & echo $!',
                escapeshellarg($dest),
                escapeshellarg($compDest)
            );
            exec($cmd);
            // Register a pending-compression marker so a cron/cleanup job can swap the file
            // in once ffmpeg finishes, without blocking this request.
            try {
                Database::execute(
                    'UPDATE entry_attachments SET compress_pending=1, compress_target_path=? WHERE id=?',
                    [$compDest, $id]
                );
            } catch (Throwable) {}
        }

        return Database::fetchOne('SELECT * FROM entry_attachments WHERE id = ?', [$id]);
    }
}
