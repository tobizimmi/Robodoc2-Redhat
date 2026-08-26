<?php
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL);

chdir(__DIR__);
require_once __DIR__ . '/../app/bootstrap.php';

$router = new Router();

// -- Auth ------------------------------------------------
$router->any('/login',                            [AuthController::class, 'login']);
$router->any('/login/2fa',                        [AuthController::class, 'login2fa']);
$router->get('/auth/microsoft',                   [MicrosoftAuthController::class, 'redirect']);
$router->get('/auth/microsoft/callback',          [MicrosoftAuthController::class, 'callback']);
$router->get('/profile/gdpr-export',                    [GdprController::class, 'export']);
$router->any('/profile/2fa/setup',                [AuthController::class, 'setup2fa']);
$router->get('/profile/2fa/qr',                          [AuthController::class, 'qrCode']);
$router->get('/profile/2fa/backup-codes',         [AuthController::class, 'backupCodes']);
$router->post('/profile/2fa/disable',             [AuthController::class, 'disable2fa']);
$router->get('/admin/nis2',                         [SecurityController::class, 'nis2']);
$router->get('/admin/security',                   [SecurityController::class, 'index']);
$router->post('/admin/security/ban',              [SecurityController::class, 'ban']);
$router->post('/admin/security/kill-session/{id}', [SecurityController::class, 'killSession']);
$router->post('/admin/security/unban/{id}',       [SecurityController::class, 'unban']);
$router->any('/logout',              [AuthController::class, 'logout']);
$router->any('/register',            [AuthController::class, 'register']);
$router->any('/forgot-password',     [AuthController::class, 'forgotPassword']);
$router->any('/reset-password',      [AuthController::class, 'resetPassword']);

// -- Dashboard --------------------------------------------
$router->get('/',                    [DashboardController::class, 'index']);
$router->post('/global-filter/set',   [GlobalFilterController::class, 'set']);
$router->post('/global-filter/clear', [GlobalFilterController::class, 'clear']);
$router->get('/dashboard',           [DashboardController::class, 'index']);

// -- Projects ---------------------------------------------
$router->get('/projects',            [ProjectController::class, 'index']);
$router->any('/projects/create',     [ProjectController::class, 'create']);
$router->get('/projects/{id}',       [ProjectController::class, 'show']);
$router->any('/projects/{id}/edit',  [ProjectController::class, 'edit']);
$router->post('/projects/{id}/delete', [ProjectController::class, 'delete']);

// -- Entries ----------------------------------------------
$router->get('/entries/integrations', [EntryController::class, 'integrations']);
$router->get('/epics',               [EpicController::class, 'index']);
$router->post('/epics',              [EpicController::class, 'store']);
$router->get('/epics/{id}/edit',     [EpicController::class, 'edit']);
$router->post('/epics/{id}',         [EpicController::class, 'update']);
$router->post('/epics/{id}/delete',  [EpicController::class, 'destroy']);
$router->post('/entries/{id}/set-epic',   [EntryController::class, 'setEpic']);
$router->post('/entries/{id}/unset-epic', [EntryController::class, 'unsetEpic']);

// -- 8D-Berichte --------------------------------------------
$router->get('/8d',                          [EightDController::class, 'index']);
$router->post('/8d/create',                  [EightDController::class, 'create']);
$router->get('/8d/{id}',                     [EightDController::class, 'show']);
$router->get('/8d/{id}/export',              [EightDController::class, 'export']);
$router->post('/8d/{id}/update',             [EightDController::class, 'update']);
$router->post('/8d/{id}/close',              [EightDController::class, 'close']);
$router->post('/8d/{id}/reopen',             [EightDController::class, 'reopen']);
$router->post('/8d/{id}/delete',             [EightDController::class, 'delete']);
$router->post('/8d/{id}/team',               [EightDController::class, 'addTeamMember']);
$router->post('/8d/{id}/team/{mid}/delete',  [EightDController::class, 'deleteTeamMember']);
$router->post('/8d/{id}/action',             [EightDController::class, 'addAction']);
$router->post('/8d/{id}/action/{aid}',       [EightDController::class, 'updateAction']);
$router->post('/8d/{id}/action/{aid}/delete',[EightDController::class, 'deleteAction']);
$router->post('/8d/{id}/attachment',         [EightDController::class, 'uploadAttachment']);
$router->post('/8d/{id}/attachment/{aid}/delete', [EightDController::class, 'deleteAttachment']);
$router->get('/8d/attachment/{aid}',         [EightDController::class, 'downloadAttachment']);
$router->get('/entries',             [EntryController::class, 'index']);
$router->get('/test-results',        [EntryController::class, 'testResults']);
$router->get('/other-entries',       [EntryController::class, 'otherEntries']);
$router->any('/entries/create',      [EntryController::class, 'create']);
$router->post('/entries/quick-capture', [EntryController::class, 'quickCapture']);

// -- Quick Capture (public, no login) ---------------------
$router->get('/quick-capture',         [QuickCaptureController::class, 'publicForm']);
$router->post('/quick-capture',        [QuickCaptureController::class, 'publicSubmit']);
$router->get('/quick-capture/thanks',  [QuickCaptureController::class, 'thanks']);
// Quick Capture moderation queue (login required)
$router->get('/feedback',  [FeedbackController::class, 'index']);
// Redirect old quick-captures list to new combined feedback page
$router->get('/quick-captures', function() { header('Location: ' . url('feedback')); exit; });
$router->get('/quick-captures/{id}',          [QuickCaptureController::class, 'review']);
$router->post('/quick-captures/{id}/approve', [QuickCaptureController::class, 'approve']);
$router->post('/quick-captures/{id}/reject',  [QuickCaptureController::class, 'reject']);
$router->post('/entries/bulk-update',   [EntryController::class, 'bulkUpdate']);
$router->post('/entries/{id}/toggle-report-relevant', [EntryController::class, 'toggleReportRelevant']);
$router->post('/entries/{id}/delete',      [EntryController::class, 'delete']);
$router->post('/entries/bulk-delete',   [EntryController::class, 'bulkDelete']);
$router->get('/entries/{id}',        [EntryController::class, 'show']);
$router->any('/entries/{id}/edit',   [EntryController::class, 'edit']);
$router->post('/entries/{id}/status', [EntryController::class, 'updateStatus']);
// Tags
$router->get('/tags',                          [TagController::class, 'index']);
$router->get('/tags/manage',                   [TagController::class, 'manage']);
$router->post('/tags',                         [TagController::class, 'create']);
$router->post('/tags/{id}/update',             [TagController::class, 'update']);
$router->post('/tags/{id}/delete',             [TagController::class, 'delete']);
$router->get('/entries/{id}/tags',             [TagController::class, 'getEntryTags']);
$router->post('/entries/{id}/tags',            [TagController::class, 'setEntryTags']);
// Tag Kanban
$router->get('/kanban/tag-view',               [TagKanbanController::class, 'index']);
$router->post('/kanban/tag-buckets',           [TagKanbanController::class, 'createBucket']);
$router->post('/kanban/tag-buckets/{id}/update', [TagKanbanController::class, 'updateBucket']);
$router->post('/kanban/tag-buckets/{id}/delete', [TagKanbanController::class, 'deleteBucket']);
$router->get('/kanban',              [KanbanController::class, 'index']);
$router->post('/entries/{id}/lane',         [KanbanController::class, 'updateLane']);
$router->post('/entries/{id}/note',         [KanbanController::class, 'saveNote']);
$router->post('/entries/{id}/note/promote', [KanbanController::class, 'promoteNote']);
$router->post('/kanban/{id}/lane',          [KanbanController::class, 'updateLane']);
$router->post('/kanban/{id}/note',          [KanbanController::class, 'saveNote']);
$router->post('/kanban/{id}/note/promote',  [KanbanController::class, 'promoteNote']);

// -- Jira -------------------------------------------------
$router->post('/entries/{id}/jira/update', [JiraController::class, 'update']);
$router->post('/entries/{id}/jira',        [JiraController::class, 'create']);
$router->post('/entries/{id}/zentao',             [ZentaoController::class, 'create']);
$router->post('/entries/{id}/zentao/update',      [ZentaoController::class, 'update']);
$router->post('/entries/{id}/zentao/sync-status',   [ZentaoController::class,     'syncStatus']);
$router->post('/entries/{id}/jira/sync-comments',   [JiraSyncController::class,   'syncCommentsForEntry']);
$router->post('/entries/{id}/zentao/sync-comments', [ZentaoSyncController::class, 'syncCommentsForEntry']);
$router->post('/entries/{id}/zentao/link',        [ZentaoController::class, 'link']);
$router->get('/api/zentao/bugs/search',           [ZentaoController::class, 'search']);
$router->get('/projects/{id}/jira-configs',          [ProjectController::class, 'jiraConfigs']);
$router->post('/projects/{id}/jira-configs',         [ProjectController::class, 'saveJiraConfigs']);
$router->post('/entries/{id}/set-parent',     [EntryController::class, 'setParent']);
$router->post('/entries/{id}/unset-parent',   [EntryController::class, 'unsetParent']);
$router->get('/entries/{id}/merge-preview',  [EntryController::class, 'mergePreview']);
$router->post('/entries/{id}/merge',          [EntryController::class, 'merge']);
$router->post('/entries/{id}/comments',              [EntryController::class, 'addComment']);
$router->post('/entries/{id}/comments/{cid}/delete', [EntryController::class, 'deleteComment']);
$router->post('/entries/{id}/upload',     [EntryController::class, 'upload']);
$router->post('/entries/{id}/sharepoint', [SharepointController::class, 'upload']);
$router->get('/sharepoint/connect',       [SharepointController::class, 'connect']);
$router->get('/sharepoint/callback',      [SharepointController::class, 'callback']);
$router->post('/sharepoint/disconnect',   [SharepointController::class, 'disconnect']);

// -- Attachments ------------------------------------------
$router->get('/attachments/{id}',    [AttachmentController::class, 'download']);
$router->get('/attachments/{id}/thumb', [AttachmentController::class, 'thumb']);
$router->post('/attachments/{id}/delete', [AttachmentController::class, 'delete']);
$router->post('/attachments/{id}/update', [AttachmentController::class, 'update']);
$router->post('/attachments/{id}/annotate', [AttachmentController::class, 'annotate']);
$router->get('/attachments/{id}/markers',   [AttachmentController::class, 'markers']);
$router->post('/attachments/{id}/markers',  [AttachmentController::class, 'addMarker']);
$router->post('/attachments/{id}/markers/{mid}/delete', [AttachmentController::class, 'deleteMarker']);
$router->get('/entries/{id}/download-zip', [AttachmentController::class, 'downloadZip']);
$router->post('/attachments/zip',    [AttachmentController::class, 'zip']);

// -- Test Plans -------------------------------------------
// SynapseRT Sync Dashboard
$router->get('/synapse',                        [SynapseController::class, 'index']);
$router->post('/synapse/import-all',            [SynapseController::class, 'importAll']);
$router->post('/synapse/sync-plan',             [SynapseController::class, 'syncPlan']);
$router->post('/synapse/sync-run',              [SynapseController::class, 'syncRun']);
$router->post('/synapse/link-plan',             [SynapseController::class, 'linkPlan']);
$router->post('/synapse/create-test-plan',      [SynapseController::class, 'createTestPlan']);
$router->get('/synapse/search-plans',           [SynapseController::class, 'searchPlans']);
$router->get('/synapse/search-requests',        [SynapseController::class, 'searchTestRequests']);
$router->post('/synapse/link-request',          [SynapseController::class, 'linkTestRequest']);
$router->post('/synapse/unlink-request',        [SynapseController::class, 'unlinkTestRequest']);
$router->post('/synapse/refresh-cache',          [SynapseController::class, 'refreshCache']);
$router->get('/synapse/list-all',              [SynapseController::class, 'listAll']);
$router->post('/synapse/import-single',         [SynapseController::class, 'importSinglePlan']);
$router->get('/test-plans/{id}/cycles-json',         [TestPlanController::class, 'cyclesJson']);
$router->post('/test-plans/{id}/cycles',              [TestCycleController::class, 'create']);
$router->post('/test-plans/{id}/cycles/{cid}/delete', [TestCycleController::class, 'delete']);
$router->post('/test-cycles/{id}/status',             [TestCycleController::class, 'updateStatus']);
$router->get('/test-plans',          [TestPlanController::class, 'index']);
$router->any('/test-plans/create',   [TestPlanController::class, 'create']);
$router->get('/test-plans/{id}',     [TestPlanController::class, 'show']);
$router->any('/test-plans/{id}/edit', [TestPlanController::class, 'edit']);
$router->post('/test-plans/{id}/delete', [TestPlanController::class, 'delete']);
$router->post('/test-plans/{id}/items', [TestPlanController::class, 'addItem']);
$router->post('/test-plans/{id}/items/{iid}/steps',              [TestPlanController::class, 'addStep']);
$router->post('/test-plans/{id}/items/{iid}/steps/{sid}/update', [TestPlanController::class, 'updateStep']);
$router->post('/test-plans/{id}/items/{iid}/steps/{sid}/delete', [TestPlanController::class, 'deleteStep']);
$router->any('/test-plans/{id}/items/{iid}/edit',                [TestPlanController::class, 'editItem']);
$router->post('/test-plans/{id}/items/{iid}/delete',             [TestPlanController::class, 'deleteItem']);
$router->post('/test-plans/{id}/items/{iid}/update',             [TestPlanController::class, 'updateItem']);
$router->get('/test-cycles',                                [TestCycleController::class, 'index']);
$router->post('/test-runs/{id}/assign-cycle',              [TestCycleController::class, 'assignCycle']);
$router->get('/test-cycles/{id}',                                [TestCycleController::class, 'show']);
$router->any('/test-cycles/{id}/edit',                           [TestCycleController::class, 'editCycle']);
$router->post('/test-plans/{id}/import', [TestPlanController::class, 'import']);
$router->post('/test-plans/{id}/items/{iid}/set-request', [TestPlanController::class, 'setTestRequest']);

// -- Test Runs --------------------------------------------
$router->get('/test-runs',           [TestRunController::class, 'index']);
$router->any('/test-runs/create',    [TestRunController::class, 'create']);
$router->get('/test-runs/{id}',      [TestRunController::class, 'show']);
$router->any('/test-runs/{id}/edit', [TestRunController::class, 'edit']);
$router->post('/test-runs/{id}/delete', [TestRunController::class, 'delete']);
$router->post('/test-runs/{id}/results/{rid}', [TestRunController::class, 'updateResult']);
$router->post('/test-runs/{id}/results/{rid}/entry', [TestRunController::class, 'createEntry']);
$router->post('/test-runs/{id}/results/{rid}/assign-tester', [TestRunController::class, 'assignTester']);
$router->post('/test-runs/{id}/results/{rid}/bugs',          [TestRunController::class, 'addBug']);
$router->post('/test-runs/{id}/results/{rid}/bugs/{bid}/delete', [TestRunController::class, 'removeBug']);
$router->post('/test-runs/{id}/results/{rid}/assign-tester',      [TestRunController::class, 'assignTester']);
$router->post('/test-runs/{id}/results/{rid}/bugs',               [TestRunController::class, 'addBug']);
$router->post('/test-runs/{id}/results/{rid}/bugs/{bid}/delete',  [TestRunController::class, 'removeBug']);
$router->post('/test-runs/{id}/add-items', [TestRunController::class, 'addItems']);

// -- Test Results -----------------------------------------
$router->get('/test-results', [TestResultController::class, 'index']);

// -- Inventory ---------------------------------------------
$router->get('/inventory',           [InventoryController::class, 'index']);
$router->any('/inventory/import',    [InventoryController::class, 'importCsv']);
$router->any('/inventory/create',    [InventoryController::class, 'create']);
$router->get('/inventory/{id}',      [InventoryController::class, 'show']);
$router->any('/inventory/{id}/edit', [InventoryController::class, 'edit']);
$router->post('/inventory/{id}/delete', [InventoryController::class, 'delete']);
$router->post('/inventory/{id}/logbook', [InventoryController::class, 'addLog']);
$router->post('/inventory/{id}/logbook/{lid}/delete', [InventoryController::class, 'deleteLog']);

// -- Requirements -----------------------------------------
// -- Sprints -----------------------------------------------
$router->get('/sprints',                                [SprintController::class, 'index']);
$router->any('/sprints/create',                         [SprintController::class, 'create']);
$router->get('/sprints/{id}',                           [SprintController::class, 'show']);
$router->any('/sprints/{id}/edit',                      [SprintController::class, 'edit']);
$router->post('/sprints/{id}/delete',                   [SprintController::class, 'delete']);
$router->post('/sprints/{id}/start',                    [SprintController::class, 'start']);
$router->post('/sprints/{id}/complete',                 [SprintController::class, 'complete']);
$router->post('/sprints/{id}/reopen',                   [SprintController::class, 'reopen']);
$router->post('/sprints/{id}/entries',                  [SprintController::class, 'addEntries']);
$router->post('/sprints/{id}/entries/{eid}/top',    [SprintController::class, 'toggleTop']);
$router->post('/sprints/{id}/entries/{eid}/remove',     [SprintController::class, 'removeEntry']);
$router->post('/sprints/{id}/entries/{eid}/points',     [SprintController::class, 'updatePoints']);
$router->post('/sprints/{id}/copy-incomplete',          [SprintController::class, 'copyIncomplete']);
$router->post('/sprints/{id}/retro',                    [SprintController::class, 'saveRetro']);
$router->get('/api/sprints',                            [SprintController::class, 'apiList']);

$router->get('/requirements',        [RequirementController::class, 'index']);
$router->any('/requirements/create', [RequirementController::class, 'create']);
$router->any('/requirements/{id}/edit', [RequirementController::class, 'edit']);
$router->post('/requirements/{id}/delete', [RequirementController::class, 'delete']);

// -- Todos ------------------------------------------------
$router->get('/todos',               [TodoController::class, 'index']);
$router->post('/todos/create',       [TodoController::class, 'create']);
$router->post('/todos/{id}/toggle',  [TodoController::class, 'toggle']);
$router->post('/todos/{id}/delete',  [TodoController::class, 'delete']);

// -- Profile ----------------------------------------------
$router->any('/profile',             [ProfileController::class, 'index']);

// -- Search -----------------------------------------------
$router->get('/search',              [SearchController::class, 'index']);

// -- Export -----------------------------------------------
$router->get('/export/entries',      [ExportController::class, 'entries']);

// -- Admin -------------------------------------------------
$router->get('/admin',               [AdminController::class, 'index']);
$router->get('/admin/users',                      [AdminController::class, 'users']);
$router->any('/admin/users/create',              [AdminController::class, 'createUser']);
$router->any('/admin/users/{id}/edit',           [AdminController::class, 'editUser']);
$router->post('/admin/users/{id}/clear-perms',   [AdminController::class, 'clearUserPerms']);
$router->post('/admin/users/{id}/delete',        [AdminController::class, 'deleteUser']);
$router->post('/admin/users/{id}/approve',       [AdminController::class, 'approveUser']);
$router->post('/admin/users/{id}/reject',        [AdminController::class, 'rejectUser']);
$router->get('/admin/groups',                    [AdminController::class, 'groups']);
$router->any('/admin/groups/create',             [AdminController::class, 'createGroup']);
$router->any('/admin/groups/{id}/edit',          [AdminController::class, 'editGroup']);
$router->post('/admin/groups/{id}/delete',       [AdminController::class, 'deleteGroup']);
$router->any('/admin/settings',      [AdminController::class, 'settings']);
$router->any('/admin/jira',          [AdminController::class, 'jiraSettings']);
$router->get('/admin/sync-review',                                        [SyncReviewController::class, 'index']);
$router->post('/admin/sync-review/{source}/{id}/{action}',                [SyncReviewController::class, 'entryAction']);
$router->post('/admin/sync-review/{source}/bulk/{action}',                [SyncReviewController::class, 'bulkAction']);
$router->any('/admin/zentao',        [AdminController::class, 'zentaoSettings']);
$router->any('/admin/microsoft-sso', [AdminController::class, 'microsoftSsoSettings']);
$router->any('/admin/entry-types',   [AdminController::class, 'entryTypes']);
$router->post('/admin/entry-types/{id}/delete', [AdminController::class, 'deleteEntryType']);
$router->any('/admin/categories',    [AdminController::class, 'categories']);
$router->post('/admin/categories/{id}/delete', [AdminController::class, 'deleteCategory']);
$router->any('/admin/custom-fields', [AdminController::class, 'customFields']);
$router->post('/admin/custom-fields/{id}/delete', [AdminController::class, 'deleteCustomField']);
$router->any('/admin/test-case-fields', [AdminController::class, 'testCaseFields']);
$router->post('/admin/test-case-fields/{id}/delete', [AdminController::class, 'deleteTestCaseField']);
// test-mowers routes removed   use Inventory instead
$router->any('/admin/environments',  [AdminController::class, 'environments']);
$router->post('/admin/environments/{id}/delete', [AdminController::class, 'deleteEnvironment']);
$router->any('/admin/checklists',    [AdminController::class, 'checklists']);
$router->post('/admin/checklists/{id}/delete', [AdminController::class, 'deleteChecklist']);
$router->get('/admin/audit',         [AdminController::class, 'audit']);
$router->any('/admin/backup',                [BackupController::class, 'index']);
$router->get('/admin/backup/download',       [BackupController::class, 'download']);
$router->post('/admin/backup/delete',        [BackupController::class, 'deleteBackup']);
$router->get('/api/backup/run',              [BackupController::class, 'runCron']);

// -- AJAX / JSON endpoints ---------------------------------
$router->get('/api/entry-types',     function() { Auth::require(); json(Database::fetchAll('SELECT * FROM entry_types ORDER BY sort_order, name')); });
$router->get('/api/projects',        function() { Auth::require(); json(Database::fetchAll("SELECT id, name FROM projects WHERE status='active' ORDER BY name")); });
$router->get('/api/categories',      function() { Auth::require(); json(Database::fetchAll('SELECT * FROM error_categories ORDER BY sort_order, name')); });
$router->get('/api/environments',    function() { Auth::require(); json(Database::fetchAll('SELECT * FROM test_environments ORDER BY name')); });
$router->get('/api/custom-fields',   function() { Auth::require(); json(Database::fetchAll('SELECT * FROM custom_fields ORDER BY sort_order, name')); });

// -- User presets (server-side table configuration storage) ----
$router->get('/api/presets', function() {
    Auth::require();
    $type = trim($_GET['type'] ?? 'entry_table');
    json(Database::fetchAll(
        'SELECT id, name, config, updated_at FROM user_presets WHERE user_id=? AND preset_type=? ORDER BY name',
        [Auth::id(), $type]
    ));
});
$router->post('/api/presets', function() {
    Auth::require();
    Auth::verifyCsrf();
    $name   = trim($_POST['name'] ?? '');
    $type   = trim($_POST['type'] ?? 'entry_table');
    $config = trim($_POST['config'] ?? '{}');
    if (!$name) { json(['error' => 'Name required']); }
    if (json_decode($config) === null) { json(['error' => 'Invalid config']); }
    Database::execute(
        'INSERT INTO user_presets (user_id, preset_type, name, config) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE config=VALUES(config), updated_at=NOW()',
        [Auth::id(), $type, $name, $config]
    );
    $row = Database::fetchOne(
        'SELECT id, name, config, updated_at FROM user_presets WHERE user_id=? AND preset_type=? AND name=?',
        [Auth::id(), $type, $name]
    );
    json(['success' => true, 'preset' => $row]);
});
// Update preset config
$router->post('/api/presets/{id}', function(string $presetId) {
    Auth::require();
    Auth::verifyCsrf();
    $config = trim($_POST['config'] ?? '');
    if (!$config) { echo json_encode(['ok' => false, 'error' => 'No config']); return; }
    $row = Database::fetchOne('SELECT * FROM user_presets WHERE id=? AND user_id=?',
        [(int)$presetId, Auth::id()]);
    if (!$row) { echo json_encode(['ok' => false, 'error' => 'Not found']); return; }
    Database::execute('UPDATE user_presets SET config=?, updated_at=? WHERE id=?',
        [$config, date('Y-m-d H:i:s'), (int)$presetId]);
    echo json_encode(['ok' => true]);
});

$router->post('/api/presets/{id}/delete', function(string $presetId) {
    Auth::require();
    Auth::verifyCsrf();
    Database::execute('DELETE FROM user_presets WHERE id=? AND user_id=?', [(int)$presetId, Auth::id()]);
    json(['success' => true]);
});
$router->get('/api/preset-default', function() {
    Auth::require();
    $type = trim($_GET['type'] ?? 'entry_table');
    $row = Database::fetchOne(
        'SELECT id FROM user_presets WHERE user_id=? AND preset_type=? AND is_default=1 LIMIT 1',
        [Auth::id(), $type]
    );
    json(['default_id' => $row ? (int)$row['id'] : null]);
});
$router->post('/api/presets/{id}/default', function(string $presetId) {
    Auth::require();
    Auth::verifyCsrf();
    $id  = (int)$presetId;
    $row = Database::fetchOne('SELECT preset_type FROM user_presets WHERE id=? AND user_id=?', [$id, Auth::id()]);
    if (!$row) { json(['error' => 'Preset nicht gefunden.']); }
    $type        = $row['preset_type'];
    $makeDefault = (($_POST['value'] ?? '1') !== '0');
    Database::execute('UPDATE user_presets SET is_default=0 WHERE user_id=? AND preset_type=?', [Auth::id(), $type]);
    if ($makeDefault) {
        Database::execute('UPDATE user_presets SET is_default=1 WHERE id=? AND user_id=?', [$id, Auth::id()]);
    }
    json(['success' => true]);
});

$router->get('/api/entries/search', function() {
    Auth::require();
    $q  = trim($_GET['q'] ?? '');
    $ex = (int)($_GET['exclude'] ?? 0);
    if (strlen($q) < 2) { json([]); }
    $like = '%' . $q . '%';
    json(Database::fetchAll(
        "SELECT id, title, entry_date FROM entries
         WHERE (title LIKE ? OR mower_serial LIKE ?) AND id != ?
         ORDER BY entry_date DESC LIMIT 10",
        [$like, $like, $ex]
    ));
});
$router->get('/api/users', function() {
    Auth::require();
    json(Database::fetchAll('SELECT id, name FROM users ORDER BY name'));
});
$router->get('/api/inventory/by-serial', function() {
    Auth::require();
    $serial = trim($_GET['serial'] ?? '');
    if ($serial === '') { json(null); }
    $item = Database::fetchOne(
        'SELECT id, name, serial_number, firmware_version, status, location, project_id FROM inventory_items WHERE serial_number = ? LIMIT 1',
        [$serial]
    );
    json($item ?: null);
});

$router->get('/api/inventory/search', function() {
    Auth::require();
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { json([]); }
    $like = '%' . $q . '%';
    $items = Database::fetchAll(
        "SELECT id, name, serial_number, firmware_version, status, location
         FROM inventory_items
         WHERE serial_number LIKE ? OR name LIKE ?
         ORDER BY
           CASE WHEN serial_number LIKE ? THEN 0 ELSE 1 END,
           name
         LIMIT 15",
        [$like, $like, $q . '%']
    );
    json($items);
});

// -- Reports ----------------------------------------------
// Report Builder
$router->get('/reports/builder',                  [ReportController::class, 'builder']);
$router->post('/reports/templates/save',           [ReportController::class, 'saveTemplate']);
$router->post('/reports/templates/{id}/delete',    [ReportController::class, 'deleteTemplate']);
$router->get('/reports/templates/{id}/preview',    [ReportController::class, 'generateFromTemplate']);
$router->get('/reports/templates/{id}/report',   [ReportController::class, 'generateReport']);
$router->post('/reports/templates/{id}/report',  [ReportController::class, 'generateReport']);
$router->get('/reports/templates/{id}/pdf',        [ReportController::class, 'exportPdf']);
$router->get('/reports/public/{token}',            [ReportController::class, 'publicView']);
$router->get('/reports/templates/{id}/schedules',        [ReportController::class, 'listSchedules']);
$router->post('/reports/templates/{id}/schedules',       [ReportController::class, 'saveSchedule']);
$router->post('/reports/schedules/{id}/delete',           [ReportController::class, 'deleteSchedule']);
$router->any('/reports',             [ReportController::class, 'index']);
$router->get('/api/reports/firmware',[ReportController::class, 'firmwareComparison']);

// -- Robots (per-robot history) ----------------------------
$router->get('/robots',                                    [RobotController::class, 'index']);
$router->get('/robots/{serial}',                           [RobotController::class, 'show']);
$router->get('/robots/{serial}/export',                    [RobotController::class, 'export']);
$router->post('/robots/{serial}/logbook',                  [RobotController::class, 'addLogbook']);
$router->post('/robots/{serial}/logbook/{lid}/delete',     [RobotController::class, 'deleteLogbook']);

// -- Test Areas --------------------------------------------
$router->get('/test-areas',              [TestAreaController::class, 'index']);
$router->any('/test-areas/create',       [TestAreaController::class, 'create']);
$router->get('/test-areas/{id}',         [TestAreaController::class, 'show']);
$router->any('/test-areas/{id}/edit',    [TestAreaController::class, 'edit']);
$router->post('/test-areas/{id}/delete', [TestAreaController::class, 'delete']);
$router->post('/test-areas/{id}/photos/{pid}/delete', [TestAreaController::class, 'deletePhoto']);
$router->get('/test-areas/{id}/photos/{pid}',        [TestAreaController::class, 'servePhoto']);
$router->get('/test-areas/{id}/photos/{pid}/thumb',  [TestAreaController::class, 'serveThumb']);

// -- Test Sessions -----------------------------------------
$router->get('/test-sessions',               [TestSessionController::class, 'index']);
$router->any('/test-sessions/create',        [TestSessionController::class, 'create']);
$router->get('/test-sessions/{id}',          [TestSessionController::class, 'show']);
$router->any('/test-sessions/{id}/edit',     [TestSessionController::class, 'edit']);
$router->post('/test-sessions/{id}/delete',  [TestSessionController::class, 'delete']);
$router->post('/test-sessions/{id}/activate',[TestSessionController::class, 'activate']);
$router->post('/test-sessions/{id}/deactivate', [TestSessionController::class, 'deactivate']);
$router->post('/test-sessions/{id}/complete',[TestSessionController::class, 'complete']);
$router->any('/test-sessions/{id}/export',   [TestSessionController::class, 'export']);

// -- Jira status sync --------------------------------------
$router->post('/entries/{id}/jira/sync-status', [JiraController::class, 'syncStatus']);

// -- Entry Templates ---------------------------------------
$router->get('/api/entry-templates',              [EntryTemplateController::class, 'index']);
$router->post('/api/entry-templates',             [EntryTemplateController::class, 'create']);
$router->post('/api/entry-templates/{id}/delete', [EntryTemplateController::class, 'delete']);

// -- Confluence --------------------------------------------
$router->any('/confluence',                    [ConfluenceController::class, 'index']);
$router->get('/api/confluence/search-pages',   [ConfluenceController::class, 'searchPages']);

// -- Jira Sync ---------------------------------------------
$router->post('/api/jira-sync/bulk-check',              [JiraSyncController::class, 'bulkCheck']);
$router->post('/api/jira-sync/check-record',            [JiraSyncController::class, 'checkRecord']);
$router->post('/api/zentao-sync/bulk-check',            [ZentaoSyncController::class, 'bulkCheck']);
$router->post('/api/zentao-sync/check-record',          [ZentaoSyncController::class, 'checkRecord']);
$router->get('/zentao-sync/entry/{id}',                 [ZentaoSyncController::class, 'reviewEntry']);
$router->post('/zentao-sync/entry/{id}/accept',         [ZentaoSyncController::class, 'acceptEntry']);
$router->post('/zentao-sync/entry/{id}/push',           [ZentaoSyncController::class, 'pushEntry']);
$router->post('/zentao-sync/entry/{id}/dismiss',        [ZentaoSyncController::class, 'dismissEntry']);
$router->get('/jira-unlinked',                          [JiraSyncController::class,   'unlinkedIssues']);
$router->post('/jira-unlinked/link',                    [JiraSyncController::class,   'linkIssueToEntry']);
$router->post('/jira-unlinked/create-entry',            [JiraSyncController::class,   'createEntryFromIssue']);
$router->post('/jira-unlinked/dismiss',                 [JiraSyncController::class,   'dismissUnlinked']);
$router->post('/jira-unlinked/undismiss',               [JiraSyncController::class,   'undismissUnlinked']);
$router->post('/jira-unlinked/bulk-create',             [JiraSyncController::class,   'bulkCreateFromIssues']);
$router->get('/zentao-unlinked',                        [ZentaoSyncController::class, 'unlinkedBugs']);
$router->post('/zentao-unlinked/link',                  [ZentaoSyncController::class, 'linkBugToEntry']);
$router->post('/zentao-unlinked/create-entry',          [ZentaoSyncController::class, 'createEntryFromBug']);
$router->post('/zentao-unlinked/dismiss',               [ZentaoSyncController::class, 'dismissUnlinked']);
$router->post('/zentao-unlinked/undismiss',             [ZentaoSyncController::class, 'undismissUnlinked']);
$router->post('/zentao-unlinked/bulk-create',           [ZentaoSyncController::class, 'bulkCreateFromBugs']);
$router->get('/api/entries/search', function() {
    Auth::require();
    $q    = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { json([]); }
    $like = '%' . $q . '%';
    $all  = !empty($_GET['include_all']);
    $extra = $all ? '' : " AND (e.jira_issue_key IS NULL OR e.jira_issue_key = '')";
    json(Database::fetchAll(
        "SELECT e.id, e.title, e.entry_date, e.status, e.priority, et.name type_name, p.name project_name
         FROM entries e
         LEFT JOIN entry_types et ON et.id = e.entry_type_id
         LEFT JOIN projects p ON p.id = e.project_id
         WHERE (e.title LIKE ? OR e.mower_serial LIKE ? OR e.description LIKE ?)$extra
         ORDER BY e.entry_date DESC LIMIT 30",
        [$like, $like, $like]
    ));
});
$router->get('/api/entries/search-for-zentao', function() {
    Auth::require();
    $q    = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { json([]); }
    $like = '%' . $q . '%';
    json(Database::fetchAll(
        "SELECT e.id, e.title, e.entry_date, et.name type_name, p.name project_name
         FROM entries e
         LEFT JOIN entry_types et ON et.id = e.entry_type_id
         LEFT JOIN projects p ON p.id = e.project_id
         WHERE (e.title LIKE ? OR e.mower_serial LIKE ?)
           AND e.zentao_bug_id IS NULL
         ORDER BY e.entry_date DESC LIMIT 20",
        [$like, $like]
    ));
});
$router->get('/api/jira/fields', function() {
    Auth::requireAdmin();
    header('Content-Type: application/json');
    $result = JiraController::getAvailableFields();
    echo json_encode($result);
    exit;
});
$router->get('/jira-sync/entry/{id}',                   [JiraSyncController::class, 'reviewEntry']);
$router->post('/jira-sync/entry/{id}/accept',           [JiraSyncController::class, 'acceptEntry']);
$router->post('/jira-sync/entry/{id}/push',             [JiraSyncController::class, 'pushEntry']);
$router->post('/jira-sync/entry/{id}/download-attachment', [JiraSyncController::class, 'downloadAttachment']);
$router->post('/jira-sync/entry/{id}/dismiss',          [JiraSyncController::class, 'dismissEntry']);
$router->get('/jira-sync/test-request/{id}',            [JiraSyncController::class, 'reviewTestRequest']);
$router->post('/jira-sync/test-request/{id}/accept',    [JiraSyncController::class, 'acceptTestRequest']);
$router->post('/jira-sync/test-request/{id}/dismiss',   [JiraSyncController::class, 'dismissTestRequest']);

// -- Test Requests -----------------------------------------
$router->get('/test-requests',                                       [TestRequestController::class, 'index']);
$router->any('/test-requests/import-jira',                           [TestRequestController::class, 'importJira']);
$router->get('/api/test-requests/jira-search',                       [TestRequestController::class, 'jiraSearch']);
$router->any('/test-requests/create',                                [TestRequestController::class, 'create']);
$router->any('/test-requests/from-test-case/{itemId}',               [TestRequestController::class, 'fromTestCase']);
$router->get('/test-requests/templates',                             [TestRequestController::class, 'templates']);
$router->post('/test-requests/templates/create',                     [TestRequestController::class, 'templateCreate']);
$router->any('/test-requests/templates/{id}/edit',                   [TestRequestController::class, 'templateEdit']);
$router->post('/test-requests/templates/{id}/delete',                [TestRequestController::class, 'templateDelete']);
$router->get('/test-requests/templates/{id}/load',                   [TestRequestController::class, 'templateLoad']);
$router->get('/test-requests/{id}',                                  [TestRequestController::class, 'show']);
$router->any('/test-requests/{id}/edit',                             [TestRequestController::class, 'edit']);
$router->post('/test-requests/{id}/delete',                          [TestRequestController::class, 'delete']);
$router->post('/test-requests/{id}/jira',                            [TestRequestController::class, 'pushJira']);
$router->post('/test-requests/{id}/attachments/{attId}/delete',      [TestRequestController::class, 'deleteAttachment']);

// -- Migration (admin only) --------------------------------
$router->any('/migrate',             [MigrateController::class, 'index']);

// -- Installer (only accessible if not installed) ----------
$router->any('/install',             [InstallController::class, 'index']);

// -- PWA Manifest (dynamic, so start_url reflects correct BASE_URL) ------------
$router->get('/manifest.json',       [DashboardController::class, 'manifest']);


// API: test cycle items for Test Result form
$router->get('/api/test-cycle-items', function() {
    Auth::require();
    $cid   = (int)($_GET['cycle_id'] ?? 0);
    if (!$cid) { json([]); return; }
    // test_cycles.test_plan_id → test_plan_items.test_plan_id
    $cycle = Database::fetchOne('SELECT test_plan_id FROM test_cycles WHERE id=?', [$cid]);
    if (!$cycle) { json([]); return; }
    $items = Database::fetchAll(
        'SELECT tpi.id,
                COALESCE(tpi.title, CONCAT("Test Case #", tpi.id)) AS name,
                tpi.sort_order
         FROM test_plan_items tpi
         WHERE tpi.test_plan_id = ?
         ORDER BY tpi.sort_order, tpi.id',
        [$cycle['test_plan_id']]
    );
    json($items);
});
// ─── Entry Export ────
$router->get('/entries/{id}/export/wizard',      [EntryExportController::class, 'wizard']);
$router->get('/entries/{id}/export',             [EntryExportController::class, 'export']);
$router->get('/admin/export-templates',          [EntryExportController::class, 'templates']);
$router->post('/admin/export-templates/save',    [EntryExportController::class, 'templateSave']);
$router->post('/admin/export-templates/{id}/delete', [EntryExportController::class, 'templateDelete']);
// ─── Personal "Tool Feedback" (bug reports / ideas about the tool itself) ────
// NOTE: lives under /tool-feedback, NOT /feedback — /feedback is the combined
// Quick Capture + Testkunden moderation inbox registered above.
$router->get('/tool-feedback',                     [FeedbackController::class, 'myFeedback']);
$router->get('/tool-feedback/new',                 [FeedbackController::class, 'create']);
$router->post('/tool-feedback/new',                [FeedbackController::class, 'create']);
$router->get('/admin/feedback',                    [FeedbackController::class, 'adminIndex']);
$router->get('/admin/feedback/{id}',               [FeedbackController::class, 'adminShow']);
$router->post('/admin/feedback/{id}/status',       [FeedbackController::class, 'adminUpdateStatus']);
$router->post('/admin/feedback/{id}/comment',      [FeedbackController::class, 'adminComment']);
$router->get('/tool-feedback/attachments/{id}', [FeedbackController::class, 'downloadAttachment']);
// ─── Test Customers ────────────────────────────────────────────────────
$router->get('/test-customers',                                   [TestCustomerController::class, 'index']);
$router->post('/test-customers/create',                           [TestCustomerController::class, 'create']);
$router->get('/test-customers/templates',                         [TestCustomerController::class, 'templates']);
$router->post('/test-customers/templates/save',                   [TestCustomerController::class, 'saveTemplate']);
$router->post('/test-customers/templates/{id}/delete',            [TestCustomerController::class, 'deleteTemplate']);
// Specific routes before {id} catch-all
$router->get('/test-customers/feedback',                           [TestCustomerController::class, 'allFeedback']);
$router->get('/test-customers/customers',                          [TestCustomerController::class, 'testCustomers']);
$router->post('/test-customers/customers/save',                    [TestCustomerController::class, 'saveTestCustomer']);
$router->post('/test-customers/customers/{id}/delete',             [TestCustomerController::class, 'deleteTestCustomer']);
$router->post('/test-customers/{id}/respondents/add-from-customer',[TestCustomerController::class, 'addRespondentFromCustomer']);
// QR must be before {id} to avoid collision
$router->get('/test-customers/qr/{type}/{token}',                [TestCustomerController::class, 'qrCode']);
$router->get('/test-customers/{id}',                             [TestCustomerController::class, 'show']);
$router->post('/test-customers/{id}/update',                      [TestCustomerController::class, 'update']);
$router->post('/test-customers/{id}/delete',                      [TestCustomerController::class, 'delete']);
$router->post('/test-customers/{id}/feedback/{fid}/reopen',       [TestCustomerController::class, 'reopenFeedback']);
$router->post('/test-customers/{id}/feedback/{fid}/delete',       [TestCustomerController::class, 'deleteFeedback']);
$router->get('/test-customers/{id}/feedback/{fid}',              [TestCustomerController::class, 'showFeedback']);
$router->post('/test-customers/{id}/feedback/{fid}/review',       [TestCustomerController::class, 'reviewFeedback']);
$router->post('/test-customers/{id}/questionnaires/create',       [TestCustomerController::class, 'createQuestionnaire']);
$router->post('/test-customers/{id}/questionnaires/{qid}/publish', [TestCustomerController::class, 'publishQuestionnaire']);
$router->post('/test-customers/{id}/questionnaires/{qid}/update',  [TestCustomerController::class, 'updateQuestionnaire']);
$router->get('/test-customers/{id}/questionnaires/{qid}',        [TestCustomerController::class, 'questionnaireResponses']);
// Success pages first (more specific), then catch-all token routes
$router->get('/tc-feedback-file/{id}/{filename}',                [TestCustomerController::class, 'serveFile']);
// Individual respondent feedback
$router->get('/tc-respondent/{token}/success',  [TestCustomerController::class, 'respondentFeedbackSuccess']);
$router->get('/tc-respondent/{token}',          [TestCustomerController::class, 'respondentFeedbackForm']);
$router->post('/tc-respondent/{token}',         [TestCustomerController::class, 'respondentFeedbackSubmit']);
$router->post('/test-customers/{id}/respondents/create',       [TestCustomerController::class, 'createRespondent']);
$router->post('/test-customers/{id}/respondents/{rid}/delete', [TestCustomerController::class, 'deleteRespondent']);
$router->get('/tc-feedback/{token}/success',                     [TestCustomerController::class, 'feedbackSuccess']);
$router->get('/tc-feedback/{token}',                             [TestCustomerController::class, 'feedbackForm']);
$router->post('/tc-feedback/{token}',                            [TestCustomerController::class, 'feedbackSubmit']);
$router->get('/tc-questionnaire/{token}/success',                [TestCustomerController::class, 'questionnaireSuccess']);
$router->get('/tc-questionnaire/{token}',                        [TestCustomerController::class, 'questionnaireForm']);
$router->post('/tc-questionnaire/{token}',                       [TestCustomerController::class, 'questionnaireSubmit']);

$router->dispatch();
