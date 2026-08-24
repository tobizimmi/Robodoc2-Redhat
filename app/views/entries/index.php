<?php
$hasChildren  = $hasChildren  ?? false;
$rowDepth     = $rowDepth     ?? 0;
$vmForView    = $vmForView    ?? 'entries';
$preFilterIds = $preFilterIds ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <p class="text-muted mb-0 small">
    <?php $totalUnfiltered = $totalUnfiltered ?? $pag['total']; ?>
    <?php if (!empty($colFiltersActive)): ?>
      <span class="text-warning fw-semibold"><?= $pag['total'] ?></span>
      <span class="text-muted">von <?= $totalUnfiltered ?> Eintr&auml;gen</span>
    <?php else: ?>
      <?= $pag['total'] ?> entries
    <?php endif; ?>
  </p>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <?php
    $exportParams = http_build_query(array_filter([
      'project_id' => $projectId ?? '',
      'date_from'  => $dateFrom ?? '',
      'date_to'    => $dateTo ?? '',
    ]));
    ?>
        <button class="btn btn-outline-secondary btn-sm" onclick="openExportWizard()" title="Export">
      <i class="bi bi-download me-1"></i>Export
    </button>
    <!-- View toggle -->
    <div class="btn-group btn-group-sm" role="group">
      <button type="button" class="btn btn-outline-secondary view-btn" data-view="card" title="Card View">
        <i class="bi bi-grid-3x3"></i>
      </button>
      <button type="button" class="btn btn-outline-secondary view-btn" data-view="list" title="List View">
        <i class="bi bi-list-ul"></i>
      </button>
      <button type="button" class="btn btn-outline-secondary view-btn" data-view="table" title="Table View">
        <i class="bi bi-table"></i>
      </button>
    </div>
    <?php if (!empty($settings['jira_url'])): ?>
    <button class="btn btn-outline-secondary btn-sm" id="jiraBulkCheckBtn" onclick="bulkCheckSync('jira', this)" title="Check all Jira-linked entries for changes">
      <i class="bi bi-bug-fill text-warning"></i><span class="d-none d-lg-inline ms-1">Check Jira</span>
    </button>
    <a href="<?= url('jira-unlinked') ?>" class="btn btn-outline-secondary btn-sm" title="Jira issues not yet linked to an entry">
      <i class="bi bi-bug-fill text-warning"></i><span class="d-none d-lg-inline ms-1">Unlinked</span>
    </a>
    <?php endif; ?>
    <?php if (!empty($settings['zentao_url'])): ?>
    <button class="btn btn-outline-secondary btn-sm" id="zentaoBulkCheckBtn" onclick="bulkCheckSync('zentao', this)" title="Check all Zentao-linked entries for changes">
      <i class="bi bi-bug text-info"></i><span class="d-none d-lg-inline ms-1">Check Zentao</span>
    </button>
    <a href="<?= url('zentao-unlinked') ?>" class="btn btn-outline-secondary btn-sm" title="Zentao bugs not yet linked to an entry">
      <i class="bi bi-bug text-info"></i><span class="d-none d-lg-inline ms-1">Unlinked</span>
    </a>
    <?php endif; ?>
    <a href="<?= url('entries/create') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>New Entry
    </a>
  </div>
</div>

<?php
$entryListView = $entryListView ?? 'normal';
$hasChildren   = false;
$rowDepth      = 0;
$vmForView    = $vmForView ?? 'entries';
$preFilterIds = $preFilterIds ?? [];
$baseUrl = match($vmForView) {
    'test-results'  => url('test-results'),
    'other-entries' => url('other-entries'),
    default         => url('entries'),
};
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <ul class="nav nav-tabs mb-0 flex-grow-1">
    <li class="nav-item">
      <a class="nav-link <?= $entryListView === 'normal' ? 'active' : '' ?>"
         href="<?= $baseUrl ?>">
        <i class="bi bi-list-ul me-1"></i><?= match($vmForView) { 'test-results' => 'Test Results', 'other-entries' => 'Other Entries', default => 'Entries' } ?>
        <?php if ($entryListView === 'normal'): ?><span class="badge bg-secondary ms-1"><?= number_format($pag['total']) ?></span><?php endif; ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $entryListView === 'archived' ? 'active' : '' ?>"
         href="<?= $baseUrl ?>?<?= http_build_query(array_merge(array_diff_key($_GET, ['list'=>'']), ['list'=>'archived'])) ?>"
         title="Zusammengeführte, Sub-Tickets und Einträge mit einem als &quot;archiviert&quot; markierten Status (Admin &gt; Settings)">
        <i class="bi bi-archive me-1"></i>Archiviert
        <?php if ($entryListView === 'archived'): ?><span class="badge bg-secondary ms-1"><?= number_format($pag['total']) ?></span><?php endif; ?>
      </a>
    </li>
  </ul>
  <?php if (!empty($childrenMap) && $entryListView === 'normal'): ?>
  <button id="globalSubToggleBtn" class="btn btn-outline-info btn-sm ms-3 flex-shrink-0"
          onclick="toggleAllSubTickets()" title="Alle Sub-Tickets ein-/ausblenden">
    <i class="bi bi-diagram-2 me-1"></i>
    <span id="globalSubToggleLabel">Sub-Tickets ausblenden</span>
  </button>
  <?php endif; ?>
</div>
<script>
var _LS_KEY = 'rd_sub_hidden';
var _LS_KEY_P = 'rd_sub_collapsed'; // per-parent collapse state
var _allSubHidden = localStorage.getItem(_LS_KEY) === '1';

function _applyGlobalState() {
  document.querySelectorAll('tr.sub-ticket-row').forEach(function(r){
    r.style.display = _allSubHidden ? 'none' : '';
  });
  document.querySelectorAll('.sub-toggle-btn').forEach(function(b){
    var pid = b.dataset.parent;
    b.querySelector('i').className = _allSubHidden ? 'bi bi-diagram-2-fill' : 'bi bi-diagram-2';
    if (_allSubHidden) { b.classList.replace('btn-outline-info','btn-outline-secondary'); }
    else { b.classList.replace('btn-outline-secondary','btn-outline-info'); }
    if (pid) _subCollapsed[pid] = _allSubHidden;
  });
  var lbl  = document.getElementById('globalSubToggleLabel');
  var gbtn = document.getElementById('globalSubToggleBtn');
  if (lbl)  lbl.textContent = _allSubHidden ? 'Sub-Tickets einblenden' : 'Sub-Tickets ausblenden';
  if (gbtn) {
    gbtn.classList.toggle('btn-outline-info',      !_allSubHidden);
    gbtn.classList.toggle('btn-outline-secondary',  _allSubHidden);
  }
}

function toggleAllSubTickets() {
  _allSubHidden = !_allSubHidden;
  localStorage.setItem(_LS_KEY, _allSubHidden ? '1' : '0');
  _applyGlobalState();
}

// Apply saved state on page load
// Epic collapse persistence key
var _LS_EPIC = 'rd_epic_collapsed';

document.addEventListener('DOMContentLoaded', function() {
  // Show filter row if server-side filters are active
  var hasServerFilter = document.querySelectorAll('#tblFilterRow .tbl-filter-input')
    [Symbol.iterator] && [...document.querySelectorAll('#tblFilterRow .tbl-filter-input')].some(i => i.value);
  if (hasServerFilter) {
    var fr = document.getElementById('tblFilterRow');
    if (fr) fr.style.display = '';
    var btn = document.getElementById('filterToggleBtn');
    if (btn) btn.classList.add('btn-warning');
  }
  if (_allSubHidden) _applyGlobalState();

  // Restore per-parent sub-ticket collapsed state
  try {
    var saved = JSON.parse(localStorage.getItem(_LS_KEY_P) || '{}');
    Object.keys(saved).forEach(function(pid) {
      if (saved[pid]) {
        _subCollapsed[pid] = true;
        // Hide sub-ticket rows
        document.querySelectorAll('tr[data-parent-id="' + pid + '"]').forEach(function(r) {
          r.style.display = 'none';
        });
        // Update toggle button
        var btn = document.querySelector('.sub-toggle-btn[data-parent="' + pid + '"]');
        if (btn) {
          btn.querySelector('i').className = 'bi bi-diagram-2-fill';
          btn.classList.replace('btn-outline-info','btn-outline-secondary');
        }
        // Update chevron in checkbox column
        var chev = document.getElementById('sub-chev-' + pid);
        if (chev) chev.className = 'bi bi-chevron-right text-info sub-row-chevron';
      }
    });
  } catch(e) {}

  // Restore epic collapsed state
  try {
    var savedEpics = JSON.parse(localStorage.getItem(_LS_EPIC) || '{}');
    Object.keys(savedEpics).forEach(function(epicId) {
      if (savedEpics[epicId]) {
        _epicCollapsed[epicId] = true;
        // Hide epic entry rows
        document.querySelectorAll('#tblBody tr.tbl-main-row[data-epic-id="' + epicId + '"]').forEach(function(r) {
          r.style.display = 'none';
        });
        // Update chevron
        var chev = document.getElementById('epic-chev-' + epicId);
        if (chev) chev.className = 'bi bi-chevron-right me-1';
      }
    });
  } catch(e) {}
});
</script>

<!-- Filters -->
<?php $hasActiveFilter = $search || $projectId || $catId || !empty($typeIds) || $dateFrom || $dateTo; ?>
<form method="GET" class="card mb-4">
  <button type="button"
          class="d-flex d-md-none align-items-center justify-content-between w-100 px-3 py-2 bg-transparent border-0 text-start"
          data-bs-toggle="collapse" data-bs-target="#filterBody"
          aria-expanded="<?= $hasActiveFilter ? 'true' : 'false' ?>" aria-controls="filterBody">
    <span class="small fw-semibold text-muted">
      <i class="bi bi-funnel me-1"></i>Filter
      <?php if ($hasActiveFilter): ?>
      <span class="badge bg-primary rounded-pill ms-1" style="font-size:.65rem">Aktiv</span>
      <?php endif; ?>
    </span>
    <i class="bi bi-chevron-<?= $hasActiveFilter ? 'up' : 'down' ?>" id="filterChevron"></i>
  </button>
  <div class="collapse d-md-block<?= $hasActiveFilter ? ' show' : '' ?>" id="filterBody">
  <div class="card-body p-3">
    <div class="row g-2">
      <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search?" value="<?= e($search) ?>">
      </div>
      <div class="col-md-2">
        <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Projects</option>
          <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $projectId == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="cat_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
      </div>
      <div class="col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100">OK</button>
      </div>
    </div>
    <!-- Type filter chips -->
    <?php if ($entryTypes): ?>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <?php foreach ($entryTypes as $et): ?>
      <label class="chip" style="cursor:pointer">
        <input type="checkbox" name="type_ids[]" value="<?= $et['id'] ?>" class="d-none"
               <?= in_array($et['id'], $typeIds) ? 'checked' : '' ?>
               onchange="this.closest('form').submit()">
        <span class="rounded-circle d-inline-block me-1" style="width:8px;height:8px;background:<?= e($et['color']) ?>"></span>
        <?= e($et['name']) ?>
      </label>
      <?php endforeach; ?>
      <?php if ($search || $projectId || $catId || $typeIds || $dateFrom || $dateTo): ?>
      <a href="<?= url('entries') ?>" class="chip text-danger">? Reset</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="mt-2">
      <?php
      $teParams = array_merge($_GET, ['show_test_entries' => $showTestEntries ? '' : '1']);
      if (!$showTestEntries) unset($teParams['show_test_entries']);
      else $teParams['show_test_entries'] = '1';
      $teUrl = url('entries?' . http_build_query(array_filter($teParams, fn($v) => $v !== '')));
      ?>
      <a href="<?= $showTestEntries ? url('entries?' . http_build_query(array_diff_key($_GET, ['show_test_entries'=>'']))) : url('entries?' . http_build_query(array_merge($_GET, ['show_test_entries'=>'1']))) ?>" class="btn btn-sm <?= $showTestEntries ? 'btn-info' : 'btn-outline-secondary' ?>">
        <i class="bi bi-clipboard-check me-1"></i><?= $showTestEntries ? 'Hide Test Entries' : 'Show Test Entries' ?>
      </a>
      <a href="<?= $showKeyQuestions ? url('entries?' . http_build_query(array_diff_key($_GET, ['show_key_questions'=>'']))) : url('entries?' . http_build_query(array_merge($_GET, ['show_key_questions'=>'1']))) ?>" class="btn btn-sm <?= $showKeyQuestions ? 'btn-warning' : 'btn-outline-secondary' ?>">
        <i class="bi bi-question-circle me-1"></i><?= $showKeyQuestions ? 'Hide Key Questions' : 'Show Key Questions' ?>
      </a>
    </div>
  </div>
  </div><!-- /#filterBody -->
</form>

<!-- Bulk action bar -->
<div id="bulkBar" class="card mb-3 border-primary" style="display:none">
  <div class="card-body py-2 d-flex align-items-center gap-3">
    <span id="bulkCount" class="fw-semibold text-primary small">0 selected</span>
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="bi bi-download me-1"></i>Export Selected
      </button>
      <ul class="dropdown-menu dropdown-menu-dark">
        <li><a class="dropdown-item bulk-export" data-format="pdf" href="#"><i class="bi bi-file-pdf me-2 text-danger"></i>PDF</a></li>
        <li><a class="dropdown-item bulk-export" data-format="xlsx" href="#"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Excel (.xlsx)</a></li>
        <li><a class="dropdown-item bulk-export" data-format="csv" href="#"><i class="bi bi-filetype-csv me-2 text-info"></i>CSV</a></li>
      </ul>
    </div>
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkEditModal">
      <i class="bi bi-pencil-square me-1"></i>Bulk Edit
    </button>
    <div class="dropdown">
      <button class="btn btn-outline-warning btn-sm dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-lightning-charge me-1"></i>Add to Sprint
      </button>
      <ul class="dropdown-menu dropdown-menu-dark" id="sprintDropdown">
        <li><div class="dropdown-item text-muted small">Loading sprints?</div></li>
      </ul>
    </div>
    <button class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn" onclick="bulkDelete()">
      <i class="bi bi-trash me-1"></i>Delete Selected
    </button>
    <button class="btn btn-link btn-sm text-muted ms-auto p-0" onclick="clearSelection()">? Clear</button>
    <form id="bulkDeleteForm" method="POST" action="<?= url('entries/bulk-delete') ?>" style="display:none">
      <?= csrfField() ?>
      <div id="bulkDeleteIdsContainer"></div>
    </form>
  </div>
</div>

<?php if (!$entries): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-journal-x display-4 mb-3 d-block"></i>
  <p>No entries found.</p>
  <a href="<?= url('entries/create') ?>" class="btn btn-primary">Create first entry</a>
</div>
<?php else: ?>

<?php
// ZIP button color: green = downloaded & nothing changed since; red = never downloaded or changed since last download
$zipColor = fn(array $e): string =>
    !($e['zip_downloaded_at'] ?? null) ? 'danger'
    : ((!($e['attachments_updated_at'] ?? null) || $e['zip_downloaded_at'] >= $e['attachments_updated_at']) ? 'success' : 'danger');
?>

<!-- ?? CARD VIEW ???????????????????????????????????????????? -->
<div id="viewCard" class="view-panel" style="display:none">
  <div class="row g-3">
    <?php foreach ($entries as $e): ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
      <?php $cdepth = (int)($e['_depth'] ?? 0); ?>
      <div class="card h-100 entry-card position-relative <?= $cdepth>0?'border-info border-opacity-25':'' ?>" style="cursor:pointer<?= $cdepth>0?';background:rgba(99,179,237,.05)':'' ?>" onclick="location.href='<?= url('entries/' . $e['id']) ?>'">
      <?php if ($cdepth > 0): ?>
      <div class="position-absolute top-0 start-0 m-1">
        <span class="badge bg-info bg-opacity-75" style="font-size:.55rem"><i class="bi bi-diagram-3"></i> Sub</span>
      </div>
      <?php endif; ?>
        <input type="checkbox" class="form-check-input entry-check position-absolute"
               value="<?= $e['id'] ?>" style="top:8px;left:8px;z-index:5"
               onclick="event.stopPropagation()">
        <?php if ($e['thumb_att_id']): ?>
        <img src="<?= url('attachments/' . $e['thumb_att_id'] . '/thumb') ?>"
             class="card-img-top" style="height:150px;object-fit:cover" alt="">
        <?php else: ?>
        <div class="d-flex align-items-center justify-content-center bg-secondary" style="height:150px">
          <i class="bi bi-journal-text text-muted" style="font-size:2rem"></i>
        </div>
        <?php endif; ?>
        <div class="card-body p-2">
          <div class="d-flex align-items-start gap-1 mb-1 flex-wrap">
            <span class="badge" style="background:<?= e($e['type_color']) ?>;font-size:.65rem"><?= e($e['type_name']) ?></span>
            <?php if ($e['is_private']): ?>
            <span class="badge bg-warning text-dark" style="font-size:.65rem"><i class="bi bi-lock-fill"></i></span>
            <?php endif; ?>
            <div class="ms-auto d-flex gap-1 align-items-center">
              <?php if ($e['jira_issue_key']): ?>
              <a href="<?= e($e['jira_issue_url']) ?>" target="_blank" onclick="event.stopPropagation()"
                 title="Jira: <?= e($e['jira_issue_key']) . ($e['jira_has_changes'] ? ' (changes!)' : ' (synced)') ?>"
                 class="<?= $e['jira_has_changes'] ? 'text-warning' : 'text-success' ?> text-decoration-none" style="font-size:.7rem;line-height:1">
                <i class="bi bi-bug-fill"></i><sup style="font-size:.55rem;font-weight:700">J</sup>
              </a>
              <?php endif; ?>
              <?php if (!empty($e['zentao_bug_id'])): ?>
              <a href="<?= e($e['zentao_bug_url'] ?? '#') ?>" target="_blank" onclick="event.stopPropagation()"
                 title="Zentao: Bug #<?= e($e['zentao_bug_id']) . ($e['zentao_has_changes'] ? ' (changes!)' : ' (synced)') ?>"
                 class="<?= $e['zentao_has_changes'] ? 'text-warning' : 'text-success' ?> text-decoration-none" style="font-size:.7rem;line-height:1">
                <i class="bi bi-bug"></i><sup style="font-size:.55rem;font-weight:700">Z</sup>
              </a>
              <?php endif; ?>
              <?php if ($e['is_todo']): ?>
              <i class="bi bi-bookmark-fill text-warning" style="font-size:.75rem"></i>
              <?php endif; ?>
            </div>
          </div>
          <div class="fw-semibold text-white" style="font-size:.82rem;line-height:1.3">
            <?php if (!empty($e['is_report_relevant'])): ?><i class="bi bi-bar-chart-fill text-success me-1" title="Relevant for Reporting" style="font-size:.7rem"></i><?php endif; ?>
            <?= e($e['title'] ?: '?') ?>
          </div>
          <?php if ($e['description']): ?>
          <div class="text-muted mt-1" style="font-size:.72rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
            <?= e(strip_tags($e['description'])) ?>
          </div>
          <?php endif; ?>
          <div class="d-flex align-items-center justify-content-between mt-2">
            <span class="text-muted" style="font-size:.7rem">
              <span class="color-dot" style="background:<?= e($e['project_color']) ?>"></span>
              <?= e($e['project_name']) ?>
            </span>
            <span class="text-muted" style="font-size:.7rem"><?= formatDate($e['entry_date'], 'd.m.Y') ?></span>
          </div>
          <div class="d-flex gap-1 mt-1 flex-wrap">
            <span class="badge bg-<?= entryStatusColor($e['status'] ?? 'new') ?>" style="font-size:.6rem"><?= entryStatuses()[$e['status'] ?? 'new'] ?? $e['status'] ?></span>
            <?php $priC=['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger']; $pc=$priC[$e['priority']??'Medium']??'secondary'; ?>
            <span class="badge" style="font-size:.6rem;<?= $pc==='orange'?'background:#f97316':'background:var(--bs-'.$pc.')'?>"><?= e($e['priority'] ?? 'Medium') ?></span>
          </div>
          <?php if ($e['att_count'] || $e['comment_count']): ?>
          <div class="d-flex align-items-center justify-content-between mt-1" style="font-size:.7rem">
            <span class="text-muted">
              <?php if ($e['att_count']): ?><i class="bi bi-paperclip"></i> <?= $e['att_count'] ?><?php endif; ?>
              <?php if ($e['comment_count']): ?><i class="bi bi-chat ms-1"></i> <?= $e['comment_count'] ?><?php endif; ?>
            </span>
            <?php if ($e['att_count']): ?>
            <a href="<?= url('entries/' . $e['id'] . '/download-zip') ?>"
               onclick="event.stopPropagation()"
               title="Download all attachments as ZIP"
               class="text-<?= $zipColor($e) ?> text-decoration-none">
              <i class="bi bi-file-earmark-zip"></i>
            </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ?? LIST VIEW ???????????????????????????????????????????? -->
<div id="viewList" class="view-panel" style="display:none">
  <div class="card">
    <div class="table-responsive">
      <table class="table table-dark table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th style="width:36px" class="ps-3"><input type="checkbox" id="selectAll" class="form-check-input" title="Select all"></th>
            <th style="width:110px">Date</th>
            <th>Title / Description</th>
            <th>Project</th>
            <th>Type</th>
            <th>Category</th>
            <th style="width:90px" class="text-center">Files</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entries as $e): ?>
          <?php
          $depth = (int)($e['_depth'] ?? 0);
          $rowStyle = $depth > 0 ? 'background:rgba(255,255,255,.03);border-left:3px solid rgba(99,179,237,.3)' : '';
          ?>
          <tr class="entry-row <?= $depth>0?'sub-ticket-row':'' ?>" data-id="<?= $e['id'] ?>" style="<?= $rowStyle ?>">
            <td class="ps-1 text-center" style="width:20px"><?php if ($hasChildren && $rowDepth===0): ?>
            <i class="bi bi-chevron-down text-info sub-row-chevron" id="sub-chev-<?= $e['id'] ?>"
               style="font-size:.7rem;cursor:pointer"
               onclick="toggleSubTicketsById(<?= $e['id'] ?>)"></i>
          <?php elseif ($rowDepth > 0): ?>
            <i class="bi bi-arrow-return-right text-info" style="font-size:.65rem;opacity:.6"></i>
          <?php endif; ?></td>
          <td class="text-center p-0" style="width:20px;vertical-align:middle"><?php if ($hasChildren && $rowDepth===0): ?><i class="bi bi-chevron-down text-info sub-row-chevron" id="sub-chev-<?= $e['id'] ?>" style="font-size:.65rem;cursor:pointer;display:block;padding:2px" onclick="toggleSubTicketsById(<?= $e['id'] ?>)"></i><?php elseif ($rowDepth>0): ?><i class="bi bi-arrow-return-right text-info" style="font-size:.6rem;opacity:.5;display:block;padding:2px"></i><?php endif; ?></td>
          <td class="ps-2" style="width:56px;vertical-align:middle">
            <div class="d-flex align-items-center gap-1">
              <?php if ($hasChildren && $rowDepth===0): ?>
              <i class="bi bi-chevron-down text-info sub-row-chevron flex-shrink-0"
                 id="sub-chev-<?= $e['id'] ?>"
                 style="font-size:.65rem;cursor:pointer;min-width:10px"
                 onclick="toggleSubTicketsById(<?= $e['id'] ?>)"></i>
              <?php elseif ($rowDepth>0): ?>
              <i class="bi bi-arrow-return-right text-info flex-shrink-0" style="font-size:.6rem;opacity:.6;min-width:10px"></i>
              <?php else: ?>
              <span style="min-width:10px"></span>
              <?php endif; ?>
              <input type="checkbox" class="form-check-input entry-check flex-shrink-0" value="<?= $e['id'] ?>">
            </div>
          </td>
            <td class="text-muted small"><?= formatDate($e['entry_date']) ?><br><?= substr($e['entry_time'], 0, 5) ?></td>
            <td style="<?= $depth>0 ? 'padding-left:'.(16+$depth*20).'px' : '' ?>">
              <?php if ($depth > 0): ?>
              <div class="text-muted mb-1" style="font-size:.65rem">
                <i class="bi bi-diagram-3 me-1"></i>Sub-Ticket
                <?php if (!empty($e['_extra_parent'])): ?>
                von: <span class="text-info"><?= e(mb_substr($e['_extra_parent'],0,40)) ?></span>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <a href="<?= url('entries/' . $e['id']) ?>" class="text-white text-decoration-none d-block">
                <?php if ($e['is_private']): ?><i class="bi bi-lock-fill text-warning me-1" title="Private"></i><?php endif; ?>
                <?php if ($depth > 0): ?><i class="bi bi-arrow-return-right text-muted me-1" style="font-size:.7rem"></i><?php endif; ?>
                <div class="fw-semibold" style="font-size:.875rem"><?= e($e['title'] ?: '?') ?></div>
                <?php if ($depth === 0 && !empty($childrenMap[$e['id']])): ?>
                <div class="text-muted mt-1" style="font-size:.7rem">
                  <i class="bi bi-diagram-3 me-1"></i><?= count($childrenMap[$e['id']] ?? []) ?> Sub-Ticket(s)
                </div>
                <?php endif; ?>
                <?php
                  $desc = trim(strip_tags($e['description'] ?? ''));
                  if ($desc):
                    $preview = mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '?' : $desc;
                ?>
                <div class="text-muted" style="font-size:.78rem;line-height:1.3"><?= e($preview) ?></div>
                <?php endif; ?>
              </a>
              <?php if ($e['mower_serial']): ?>
              <div class="text-muted" style="font-size:.75rem"><i class="bi bi-upc me-1"></i><?= e($e['mower_serial']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="color-dot" style="background:<?= e($e['project_color']) ?>"></span>
              <span class="small"><?= e($e['project_name']) ?></span>
            </td>
            <td><span class="badge" style="background:<?= e($e['type_color']) ?>"><?= e($e['type_name']) ?></span></td>
            <td>
              <?php if ($e['cat_name']): ?>
              <span class="badge" style="background:<?= e($e['cat_color']) ?>"><?= e($e['cat_name']) ?></span>
              <?php else: ?><span class="text-muted small">?</span><?php endif; ?>
            </td>
            <td class="text-center small text-muted">
              <?php if ($e['att_count']): ?><i class="bi bi-paperclip"></i> <?= $e['att_count'] ?><?php endif; ?>
              <?php if ($e['comment_count']): ?> <i class="bi bi-chat ms-1"></i> <?= $e['comment_count'] ?><?php endif; ?>
              <?php if ($e['is_todo']): ?><i class="bi bi-bookmark-fill text-warning ms-1"></i><?php endif; ?>
              <?php if ($e['jira_issue_key']): ?>
              <a href="<?= e($e['jira_issue_url']) ?>" target="_blank"
                 title="Jira: <?= e($e['jira_issue_key']) . ($e['jira_has_changes'] ? ' (changes!)' : '') ?>"
                 class="<?= $e['jira_has_changes'] ? 'text-warning' : 'text-success' ?> text-decoration-none ms-1">
                <i class="bi bi-bug-fill"></i><sup style="font-size:.55rem;font-weight:700">J</sup></a>
              <?php endif; ?>
              <?php if (!empty($e['zentao_bug_id'])): ?>
              <a href="<?= e($e['zentao_bug_url'] ?? '#') ?>" target="_blank"
                 title="Zentao: Bug #<?= e($e['zentao_bug_id']) . ($e['zentao_has_changes'] ? ' (changes!)' : '') ?>"
                 class="<?= $e['zentao_has_changes'] ? 'text-warning' : 'text-success' ?> text-decoration-none ms-1">
                <i class="bi bi-bug"></i><sup style="font-size:.55rem;font-weight:700">Z</sup></a>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1 justify-content-end">
                <?php if ($e['att_count']): ?>
                <a href="<?= url('entries/' . $e['id'] . '/download-zip') ?>"
                   class="btn btn-outline-<?= $zipColor($e) ?> btn-sm py-0 px-2"
                   title="Download all attachments as ZIP">
                  <i class="bi bi-file-earmark-zip"></i>
                </a>
                <?php endif; ?>
                <a href="<?= url('entries/' . $e['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">
                  <i class="bi bi-pencil"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ?? TABLE VIEW ??????????????????????????????????????????? -->
<div id="viewTable" class="view-panel" style="display:none">
<?php
// ?? All available columns ?????????????????????????????????
// label, sortKey (server-side), defaultRow (1 or 2), defaultVisible
$tblAllCols = [
  'date'         => ['label'=>'Date',          'sort'=>'entry_date',       'row'=>1,'vis'=>true],
  'time'         => ['label'=>'Time',          'sort'=>'entry_time',       'row'=>2,'vis'=>false],
  'title'        => ['label'=>'Title',         'sort'=>'id',               'row'=>1,'vis'=>true],
  'description'  => ['label'=>'Description',  'sort'=>null,               'row'=>2,'vis'=>false],
  'status'       => ['label'=>'Status',        'sort'=>'status',           'row'=>1,'vis'=>true],
  'priority'     => ['label'=>'Priority',      'sort'=>'priority',         'row'=>1,'vis'=>true],
  'project'      => ['label'=>'Project',       'sort'=>'project_name',     'row'=>1,'vis'=>true],
  'proj_status'  => ['label'=>'Proj. Status', 'sort'=>null,               'row'=>2,'vis'=>false],
  'type'         => ['label'=>'Type',          'sort'=>'type_name',        'row'=>1,'vis'=>true],
  'category'     => ['label'=>'Category',      'sort'=>'cat_name',         'row'=>1,'vis'=>true],
  'serial'       => ['label'=>'Serial No.',    'sort'=>'mower_serial',     'row'=>2,'vis'=>true],
  'firmware'     => ['label'=>'Firmware',      'sort'=>'firmware_version', 'row'=>2,'vis'=>true],
  'app_version'  => ['label'=>'App Version',  'sort'=>'app_version',      'row'=>2,'vis'=>false],
  'environment'  => ['label'=>'Environment',   'sort'=>'env_name',         'row'=>2,'vis'=>false],
  'test_area'    => ['label'=>'Test Area',     'sort'=>'test_area_name',   'row'=>2,'vis'=>false],
  'temperature'  => ['label'=>'Temp (?C)',     'sort'=>'temperature',      'row'=>2,'vis'=>false],
  'weather'      => ['label'=>'Weather',       'sort'=>null,               'row'=>2,'vis'=>false],
  'gps'          => ['label'=>'GPS',           'sort'=>null,               'row'=>2,'vis'=>false],
  'creator'      => ['label'=>'Creator',       'sort'=>'creator',          'row'=>1,'vis'=>true],
  'jira'         => ['label'=>'Jira',          'sort'=>null,               'row'=>1,'vis'=>true],
  'zentao'       => ['label'=>'Zentao',        'sort'=>null,               'row'=>1,'vis'=>true],
  'files'        => ['label'=>'Files',         'sort'=>null,               'row'=>1,'vis'=>true],
];
$priColorsT=['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];

// Cell renderer ? returns the HTML content of a cell for a given column key
$renderCell = function(string $col, array $e, bool $forDetail=false) use ($priColorsT, $childrenMap): string {
  switch ($col) {
    case 'date':        return '<span class="text-muted small text-nowrap">'.e(formatDate($e['entry_date'])).'</span>';
    case 'time':        return '<span class="text-muted small">'.e(substr($e['entry_time']??'',0,5)).'</span>';
    case 'title':
      $lock  = $e['is_private'] ? '<i class="bi bi-lock-fill text-warning me-1"></i>' : '';
      $depth = (int)($e['_depth'] ?? 0);
      $hasChildrenR = !empty($childrenMap[$e['id']]);
      $indent = $depth > 0
        ? '<span style="display:inline-block;width:'.($depth*20).'px"></span><i class="bi bi-arrow-return-right text-muted me-1" style="font-size:.7rem"></i>'
        : '';
      $subLabel = $depth > 0 ? '<div class="text-info" style="font-size:.65rem"><i class="bi bi-diagram-3 me-1"></i>Sub-Ticket</div>' : '';
      return $subLabel.'<a href="'.url('entries/'.$e['id']).'" class="text-white text-decoration-none fw-semibold" style="font-size:.85rem;word-break:break-word;white-space:normal">'.$indent.$lock.e($e['title']?:'?').'</a>';
    case 'description': return '<span class="text-muted small" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">'.e(strip_tags($e['description']??'')).'</span>';
    case 'status':
      $st=$e['status']??'new'; $sl=entryStatuses()[$st]??$st;
      return '<span class="badge bg-'.entryStatusColor($st).'" style="font-size:.65rem;cursor:pointer" onclick="quickFilter(\'status\',\''.e(addslashes($sl)).'\')">'.e($sl).'</span>';
    case 'priority':
      $pv=$e['priority']??'Medium'; $pc=$priColorsT[$pv]??'secondary';
      $pstyle=$pc==='orange'?'background:#f97316':'background:var(--bs-'.$pc.')';
      return '<span class="badge" style="font-size:.65rem;cursor:pointer;'.$pstyle.'" onclick="quickFilter(\'priority\',\''.e(addslashes($pv)).'\')">'.e($pv).'</span>';
    case 'project':
      return '<span class="color-dot" style="background:'.e($e['project_color']).'"></span><span class="small ms-1" style="cursor:pointer" onclick="quickFilter(\'project\',\''.e(addslashes($e['project_name']??'')).'\')">'.e($e['project_name']).'</span>';
    case 'proj_status': return '<span class="small text-muted">'.e($e['project_status_robot']?:'?').'</span>';
    case 'type':        return '<span class="badge" style="background:'.e($e['type_color']).';cursor:pointer" onclick="quickFilter(\'type\',\''.e(addslashes($e['type_name']??'')).'\')">'.e($e['type_name']).'</span>';
    case 'category':    return $e['cat_name'] ? '<span class="badge" style="background:'.e($e['cat_color']).';cursor:pointer" onclick="quickFilter(\'category\',\''.e(addslashes($e['cat_name']??'')).'\')">'.e($e['cat_name']).'</span>' : '<span class="text-muted small">?</span>';
    case 'serial':      return '<span class="small text-muted">'.e($e['mower_serial']?:'?').'</span>';
    case 'firmware':    return '<span class="small text-muted">'.e($e['firmware_version']?:'?').'</span>';
    case 'app_version': return '<span class="small text-muted">'.e($e['app_version']??'?').'</span>';
    case 'environment': return '<span class="small text-muted">'.e($e['env_name']??'?').'</span>';
    case 'test_area':   return '<span class="small text-muted">'.e($e['test_area_name']??'?').'</span>';
    case 'temperature': return '<span class="small text-muted">'.($e['temperature']!==null ? $e['temperature'].' ?C' : '?').'</span>';
    case 'weather':     return '<span class="small text-muted">'.e($e['weather_condition']??'?').'</span>';
    case 'gps':         return '<span class="small text-muted">'.($e['gps_lat'] ? round($e['gps_lat'],4).', '.round($e['gps_lon'],4) : '?').'</span>';
    case 'creator':     return '<span class="small text-muted">'.e($e['creator']?:'?').'</span>';
    case 'jira':
      if (!$e['jira_issue_key']) return '<span class="text-muted">?</span>';
      $jc=$e['jira_has_changes']?'text-warning':'text-success';
      $js=$e['jira_status']?'<span class="text-muted ms-1" style="font-size:.7rem">'.e($e['jira_status']).'</span>':'';
      return '<a href="'.e($e['jira_issue_url']).'" target="_blank" class="'.$jc.' text-decoration-none small"><i class="bi bi-bug-fill"></i><sup style="font-size:.55rem;font-weight:700">J</sup> '.e($e['jira_issue_key']).'</a>'.$js;
    case 'zentao':
      if (empty($e['zentao_bug_id'])) return '<span class="text-muted">?</span>';
      $zc=$e['zentao_has_changes']?'text-warning':'text-success';
      $zs=($e['zentao_status']??'')?'<span class="text-muted ms-1" style="font-size:.7rem">'.e($e['zentao_status']).'</span>':'';
      return '<a href="'.e($e['zentao_bug_url']??'#').'" target="_blank" class="'.$zc.' text-decoration-none small"><i class="bi bi-bug"></i><sup style="font-size:.55rem;font-weight:700">Z</sup> #'.e($e['zentao_bug_id']).'</a>'.$zs;
    case 'files':
      $out='';
      if ($e['att_count']) $out.='<i class="bi bi-paperclip"></i> '.$e['att_count'].' ';
      if ($e['comment_count']) $out.='<i class="bi bi-chat"></i> '.$e['comment_count'].' ';
      if ($e['is_todo']) $out.='<i class="bi bi-bookmark-fill text-warning"></i>';
      return '<span class="text-muted small">'.$out.'</span>';
    default: return '?';
  }
};
// Plain-text value for client-side filtering
$filterVal = function(string $col, array $e): string {
  return match($col) {
    'date'        => $e['entry_date']??'',
    'time'        => substr($e['entry_time']??'',0,5),
    'title'       => $e['title']??'',
    'description' => strip_tags($e['description']??''),
    'status'      => entryStatuses()[$e['status']??'new']??($e['status']??''),
    'priority'    => $e['priority']??'',
    'project'     => $e['project_name']??'',
    'proj_status' => $e['project_status_robot']??'',
    'type'        => $e['type_name']??'',
    'category'    => $e['cat_name']??'',
    'serial'      => $e['mower_serial']??'',
    'firmware'    => $e['firmware_version']??'',
    'app_version' => $e['app_version']??'',
    'environment' => $e['env_name']??'',
    'test_area'   => $e['test_area_name']??'',
    'temperature' => $e['temperature']!==null ? $e['temperature'].' ?C' : '',
    'weather'     => $e['weather_condition']??'',
    'gps'         => $e['gps_lat'] ? round($e['gps_lat'],4).', '.round($e['gps_lon'],4) : '',
    'creator'     => $e['creator']??'',
    'jira'        => ($e['jira_issue_key']??'').' '.($e['jira_status']??''),
    'zentao'      => ($e['zentao_bug_id']??'').' '.($e['zentao_status']??''),
    'files'       => ($e['att_count']?'files':'').' '.($e['is_todo']?'todo':''),
    default       => '',
  };
};
?>

<?php /* ?? Toolbar ???????????????????????????????????????????? */ ?>
<div class="mb-2 d-flex align-items-center gap-2 flex-wrap" id="tblToolbar">

  <!-- 1. Global search -->
  <div class="input-group input-group-sm" style="max-width:220px">
    <span class="input-group-text bg-dark border-secondary"><i class="bi bi-search text-muted"></i></span>
    <input type="text" id="tblGlobalSearch" class="form-control form-control-sm bg-dark text-white border-secondary"
           placeholder="Search all columns?" oninput="applyGlobalSearch(this.value)">
    <button class="btn btn-outline-secondary btn-sm" id="tblGlobalClearBtn" style="display:none"
            onclick="document.getElementById('tblGlobalSearch').value='';applyGlobalSearch('');this.style.display='none'">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <!-- 2. Column configurator with search -->
  <div class="dropdown">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
      <i class="bi bi-layout-three-columns me-1"></i>Columns
    </button>
    <div class="dropdown-menu dropdown-menu-dark p-2" style="min-width:300px">
      <input type="text" id="colSearch" class="form-control form-control-sm bg-dark text-white border-secondary mb-2"
             placeholder="Search columns?" oninput="filterColList(this.value)">
      <div class="text-muted small fw-semibold mb-1 px-1" style="font-size:.7rem">
        VISIBLE ? Drag headers to reorder ? R1=main row ? R2=detail row
      </div>
      <div id="colList" style="max-height:340px;overflow-y:auto">
        <?php foreach ($tblAllCols as $cKey => $cDef): ?>
        <div class="d-flex align-items-center gap-2 py-1 border-bottom border-secondary col-list-item" data-label="<?= strtolower(e($cDef['label'])) ?>" style="font-size:.82rem">
          <input type="checkbox" class="form-check-input flex-shrink-0 tbl-vis-cb" id="vis_<?= $cKey ?>" data-col="<?= $cKey ?>" checked>
          <label class="flex-grow-1 mb-0" for="vis_<?= $cKey ?>"><?= e($cDef['label']) ?></label>
          <div class="btn-group btn-group-sm flex-shrink-0" style="font-size:.7rem">
            <button type="button" class="btn btn-outline-secondary py-0 px-1 tbl-row-btn" data-col="<?= $cKey ?>" data-row="1" title="Row 1">R1</button>
            <button type="button" class="btn btn-outline-secondary py-0 px-1 tbl-row-btn" data-col="<?= $cKey ?>" data-row="2" title="Row 2">R2</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Presets -->
  <div class="dropdown">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
      <i class="bi bi-bookmark me-1"></i>Presets
    </button>
    <div class="dropdown-menu dropdown-menu-dark p-2" style="min-width:200px">
      <div class="input-group input-group-sm mb-2">
        <input type="text" id="presetNameInput" class="form-control" placeholder="Preset name?">
        <button class="btn btn-outline-success" onclick="savePreset()" title="Save preset"><i class="bi bi-check-lg"></i></button>
      </div>
      <div id="presetList" class="list-group list-group-flush"></div>
      <div class="border-top border-secondary mt-2 pt-2">
        <button class="btn btn-outline-danger btn-sm w-100" onclick="resetTblConfig()" title="Reset to default view">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default
        </button>
      </div>
    </div>
  </div>

  <!-- 3. Export filtered rows -->
  <div class="dropdown">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
      <i class="bi bi-download me-1"></i>Export
    </button>
    <ul class="dropdown-menu dropdown-menu-dark">
      <li><a class="dropdown-item small" href="#" onclick="exportFiltered('csv');return false"><i class="bi bi-filetype-csv me-2 text-info"></i>CSV (filtered rows)</a></li>
      <li><a class="dropdown-item small" href="#" onclick="exportFiltered('tsv');return false"><i class="bi bi-table me-2 text-success"></i>TSV (filtered rows)</a></li>
    </ul>
  </div>

  <!-- 4. Compact/Comfortable toggle -->
  <button class="btn btn-outline-secondary btn-sm" id="tblDensityBtn" onclick="toggleDensity()" title="Toggle compact/comfortable">
    <i class="bi bi-text-paragraph"></i>
  </button>

  <!-- 5. Column filters -->
  <button class="btn btn-outline-secondary btn-sm" id="filterToggleBtn" onclick="toggleFilterRow()" title="Per-column filters">
    <i class="bi bi-funnel me-1"></i>Filters
  </button>

  <!-- 6. Group by (multi-level) -->
  <div class="dropdown">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" id="groupByBtn">
      <i class="bi bi-collection me-1"></i><span id="groupByLabel">Group by</span>
    </button>
    <div class="dropdown-menu dropdown-menu-dark p-3" style="min-width:300px">
      <div class="text-muted fw-semibold mb-2" style="font-size:.7rem;letter-spacing:.06em;text-transform:uppercase">
        Grouping (up to 4 levels)
      </div>
      <?php for ($__lvl = 0; $__lvl < 4; $__lvl++): ?>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="text-muted flex-shrink-0" style="font-size:.75rem;min-width:50px">Level <?= $__lvl+1 ?></span>
        <select class="form-select form-select-sm group-level-sel" id="groupLevel<?= $__lvl ?>"
                data-level="<?= $__lvl ?>" onchange="onGroupLevelChange()">
          <option value="">? None ?</option>
          <?php foreach ($tblAllCols as $__cKey => $__cDef): ?>
          <option value="<?= $__cKey ?>"><?= e($__cDef['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endfor; ?>
      <div class="mt-2 pt-2 border-top border-secondary">
        <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="clearGrouping()">
          <i class="bi bi-x-lg me-1"></i>Clear All
        </button>
      </div>
    </div>
  </div>

  <button class="btn btn-outline-secondary btn-sm" onclick="resetTblConfig()" title="Reset all to defaults">
    <i class="bi bi-arrow-counterclockwise"></i>
  </button>

  <span class="text-muted small ms-auto d-none d-lg-inline"><i class="bi bi-grip-vertical"></i> Drag to reorder</span>
  <?php if (!empty($colFiltersActive)): ?>
  <span class="badge bg-warning text-dark" style="font-size:.8rem" title="Eintr&auml;ge auf dieser Seite / Gefilterte Eintr&auml;ge gesamt">
    <?= count($entries) ?> / <?= $pag['total'] ?>
  </span>
  <?php endif; ?>
</div>

<style>
/* Sticky title column ? matches Bootstrap table-dark row color exactly */
#tblMain th[data-col="title"] {
  position: sticky; left: 0; z-index: 3;
  background: #212529; /* Bootstrap thead-dark */
  box-shadow: 3px 0 6px rgba(0,0,0,.45);
}
#tblMain td[data-col="title"] {
  position: sticky; left: 0; z-index: 2;
  background: #1e2125; /* Bootstrap table-dark row */
  box-shadow: 3px 0 6px rgba(0,0,0,.45);
}
#tblMain tr:hover td[data-col="title"] {
  background: #2c3034; /* Bootstrap table-dark hover */
}
/* Compact mode */
#tblMain.tbl-compact td, #tblMain.tbl-compact th { padding: 2px 6px !important; font-size:.78rem; }
#tblMain.tbl-compact .badge { font-size:.58rem !important; }
/* Column resize handle */
.col-resize-handle {
  position:absolute; right:0; top:0; bottom:0; width:5px;
  cursor:col-resize; z-index:10; user-select:none;
  transition: background .15s;
}
.col-resize-handle:hover, .col-resize-handle.dragging {
  background: rgba(255,255,255,.25);
}
#tblMain { table-layout:auto; width:100%; }
#tblSortRow th[data-col] { position:relative; overflow:hidden; text-overflow:ellipsis; }
/* Body cells: clip content so text doesn't overflow the column */
#tblMain tbody td[data-col] { overflow:hidden; white-space:nowrap; }
#tblMain tbody td[data-col] a { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* Detail row: normal wrapping, don't influence column widths */
#tblMain tr.tbl-detail-row td { white-space:normal; overflow:hidden; }
/* Group header rows ? level-specific styling applied inline via JS */
#tblMain tr.tbl-group-header td { white-space: nowrap; user-select: none; }
#tblMain tr.tbl-group-header td:hover { filter: brightness(1.18); }
</style>
<?php if ($epicFilter && !empty($epicGroups[$epicFilter])): ?>
<?php $activeEpic = $epicGroups[$epicFilter]['epic']; ?>
<div class="d-flex align-items-center gap-3 mb-2 py-2 px-3 border rounded" style="background:<?= e($activeEpic['color']) ?>18;border-color:<?= e($activeEpic['color']) ?> !important">
  <i class="bi bi-lightning-fill" style="color:<?= e($activeEpic['color']) ?>;font-size:1rem"></i>
  <div class="flex-grow-1">
    <span class="fw-semibold" style="color:<?= e($activeEpic['color']) ?>"><?= e($activeEpic['title']) ?></span>
    <span class="text-muted small ms-2">Epic-Filter aktiv &mdash; es werden nur Eintr&auml;ge dieses Epics angezeigt</span>
  </div>
  <a href="<?= url('entries') ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">
    <i class="bi bi-x me-1"></i>Filter aufheben
  </a>
</div>
<?php endif; ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-dark table-hover table-sm mb-0 align-middle w-100" id="tblMain">
      <thead>
        <!-- Sort row -->
        <tr id="tblSortRow">
          <th style="width:44px;min-width:44px;max-width:44px;padding:0 4px"><div style="display:flex;align-items:center;gap:3px;width:44px"><span style="display:inline-flex;width:12px;flex-shrink:0"></span><input type="checkbox" id="selectAllTable" class="form-check-input flex-shrink-0"></div></th>
          <?php
          $tblSortLink = function(string $sortKey, string $label) use ($sortBy, $sortDir): string {
            $active  = $sortBy === $sortKey;
            $nextDir = $active && $sortDir === 'ASC' ? 'DESC' : 'ASC';
            $icon    = $active ? ($sortDir==='ASC'?' <i class="bi bi-caret-up-fill"></i>':' <i class="bi bi-caret-down-fill"></i>') : ' <i class="bi bi-caret-down text-muted" style="opacity:.35"></i>';
            $params  = array_merge($_GET, ['sort'=>$sortKey,'dir'=>$nextDir,'page'=>1]);
            return '<a href="?'.http_build_query($params).'" class="text-white text-decoration-none d-block w-100">'.$label.$icon.'</a>';
          };
          foreach ($tblAllCols as $cKey => $cDef): ?>
          <th data-col="<?= $cKey ?>" draggable="true" class="tbl-th" style="cursor:grab;user-select:none">
            <?php if ($cDef['sort']): echo $tblSortLink($cDef['sort'], e($cDef['label'])); else: ?>
            <span><?= e($cDef['label']) ?></span>
            <?php endif; ?>
          </th>
          <?php endforeach; ?>
          <th style="width:70px"></th>
        </tr>
        <!-- Filter row (hidden by default) -->
        <tr id="tblFilterRow" style="display:none">
          <th></th>
          <?php foreach ($tblAllCols as $cKey => $cDef): ?>
          <th data-col="<?= $cKey ?>">
            <input type="text" class="form-control form-control-sm tbl-filter-input bg-dark text-white border-secondary"
                   data-col="<?= $cKey ?>" placeholder="Filter? (mehrere: A,B)" style="min-width:80px"
                   title="Mehrere Werte mit Komma trennen, z.B. Bug,Finding"
                   value="<?= e(($colFiltersActive ?? [])[$cKey] ?? '') ?>">
          </th>
          <?php endforeach; ?>
          <th class="d-flex gap-1">
            <button type="button" class="btn btn-primary btn-sm py-0 px-2"
                    onclick="_applyServerFilters()" title="Filter anwenden (Server-seitig)">
              <i class="bi bi-search"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1"
                    onclick="clearAllFilters()" title="Alle Filter löschen">
              <i class="bi bi-x-lg"></i>
            </button>
          </th>
        </tr>
      </thead>
      <tbody id="tblBody">
        <?php
        $priColorsT2=['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];
        // Only show epic group headers on page 1
        $epicGroups = (($pag['page'] ?? 1) === 1) ? ($epicGroups ?? []) : [];
        $lastEpicId = null;
        foreach ($entries as $e):
          if (($e['epic_id'] ?? null) && ($e['epic_id'] ?? null) !== $lastEpicId && !empty($epicGroups[$e['epic_id']])):
            $lastEpicId = $e['epic_id'];
            $epG = $epicGroups[$e['epic_id']]['epic'];
        ?><tr class="epic-header-row" data-epic-id="<?= $epG['id'] ?>" style="background:<?= e($epG['color']) ?>18;border-left:4px solid <?= e($epG['color']) ?>;cursor:pointer" onclick="toggleEpicGroup(<?= $epG['id'] ?>)">
            <td colspan="<?= count($tblAllCols)+2 ?>" class="ps-2 py-1" style="user-select:none">
              <i class="bi bi-chevron-down me-1" id="epic-chev-<?= $epG['id'] ?>" style="font-size:.7rem;color:<?= e($epG['color']) ?>"></i>
              <span class="fw-semibold" style="color:<?= e($epG['color']) ?>"><i class="bi bi-lightning-fill me-1"></i><?= e($epG['title']) ?></span>
              <span class="badge ms-1" style="background:<?= e($epG['color']) ?>;font-size:.6rem"><?= $epicGroups[$e['epic_id']]['count'] ?></span>
              <a href="<?= url('entries?epic_id='.$epG['id']) ?>" class="ms-2 text-muted" onclick="event.stopPropagation()" style="font-size:.7rem">filter</a>
              <a href="<?= url('epics/'.$epG['id'].'/edit') ?>" class="ms-1 text-muted" onclick="event.stopPropagation()" style="font-size:.7rem"><i class="bi bi-pencil"></i></a>
            </td></tr>
        <?php endif; ?>
        <?php
        // Build filter-value map for all columns
        $fvals = [];
        foreach ($tblAllCols as $cKey => $_) { $fvals[$cKey] = $filterVal($cKey, $e); }
        $fvalsJson = htmlspecialchars(json_encode($fvals), ENT_QUOTES);
        ?>
        <?php
        $rowPriBorder=['Low'=>'transparent','Medium'=>'transparent','High'=>'#f59e0b','Highest'=>'#f97316','Blocker'=>'#ef4444'];
        $rowBorder=$rowPriBorder[$e['priority']??'Medium']??'transparent';
        $_epicColor = (!empty($e['epic_id']) && !empty($epicGroups[$e['epic_id']])) ? $epicGroups[$e['epic_id']]['epic']['color'] : null;
        if ($_epicColor) $rowBorder = $_epicColor;
        $rowDepth = (int)($e['_depth'] ?? 0);
        if ($rowDepth > 0) $rowBorder = 'rgba(99,179,237,.6)';
        ?>
        <?php $hasChildren = !empty($childrenMap[$e['id']]); ?>
        <tr class="entry-row tbl-main-row<?= $rowDepth>0?' sub-ticket-row':'' ?>"
            data-id="<?= $e['id'] ?>"
            data-fvals="<?= $fvalsJson ?>"
            <?= $rowDepth>0 ? 'data-parent-id="'.$e['parent_id'].'"' : '' ?>
            <?= !empty($e['epic_id']) ? 'data-epic-id="'.$e['epic_id'].'"' : '' ?>
            style="border-left:3px solid <?= ($hasChildren && $rowDepth===0) ? 'rgba(99,179,237,.8)' : $rowBorder ?><?= $rowDepth>0?';background:rgba(99,179,237,.04)':'' ?><?= isset($_epicColor)&&$_epicColor?';background:'.e($_epicColor).'0d':'' ?>">
          <td style="width:44px;min-width:44px;max-width:44px;vertical-align:middle;padding:0 4px">
            <div style="display:flex;align-items:center;gap:3px;width:44px">
              <span style="display:inline-flex;align-items:center;justify-content:center;width:12px;flex-shrink:0"><?php if ($hasChildren && $rowDepth===0): ?><i class="bi bi-chevron-down text-info sub-row-chevron" id="sub-chev-<?= $e['id'] ?>" style="font-size:.6rem;cursor:pointer" onclick="toggleSubTicketsById(<?= $e['id'] ?>)"></i><?php elseif ($rowDepth>0): ?><i class="bi bi-arrow-return-right text-info" style="font-size:.6rem;opacity:.6"></i><?php endif; ?></span>
              <input type="checkbox" class="form-check-input entry-check flex-shrink-0" value="<?= $e['id'] ?>">
            </div>
          </td>
          <?php foreach ($tblAllCols as $cKey => $cDef): ?>
          <td data-col="<?= $cKey ?>"><?= $renderCell($cKey, $e) ?></td>
          <?php endforeach; ?>
          <td>
            <div class="d-flex gap-1 justify-content-end">
              <?php if ($hasChildren): ?>
              <button class="btn btn-outline-info btn-sm py-0 px-1 sub-toggle-btn"
                      data-parent="<?= $e['id'] ?>"
                      title="Sub-Tickets ein-/ausblenden"
                      onclick="toggleSubTickets(this, <?= $e['id'] ?>)">
                <i class="bi bi-diagram-2" style="font-size:.7rem"></i>
                <span class="sub-toggle-count" style="font-size:.65rem"><?= count($childrenMap[$e['id']]) ?></span>
              </button>
              <?php endif; ?>
              <button class="btn btn-outline-secondary btn-sm py-0 px-1 tbl-detail-btn" data-id="<?= $e['id'] ?>" title="Show detail row">
                <i class="bi bi-chevron-down" style="font-size:.7rem"></i>
              </button>
              <?php if ($e['att_count']): ?>
              <a href="<?= url('entries/'.$e['id'].'/download-zip') ?>"
                 class="btn btn-outline-<?= $zipColor($e) ?> btn-sm py-0 px-1" title="ZIP">
                <i class="bi bi-file-earmark-zip" style="font-size:.7rem"></i>
              </a>
              <?php endif; ?>
              <a href="<?= url('entries/'.$e['id'].'/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-1">
                <i class="bi bi-pencil" style="font-size:.7rem"></i>
              </a>
            </div>
          </td>
        </tr>
        <!-- Detail row (row 2 columns + extra info) -->
        <tr class="tbl-detail-row" data-id="<?= $e['id'] ?>" style="display:none">
          <td></td>
          <td colspan="<?= count($tblAllCols)+1 ?>" class="py-2 px-3 bg-dark border-top-0">
            <div class="d-flex flex-wrap gap-3 small" id="detail-<?= $e['id'] ?>">
              <?php foreach ($tblAllCols as $cKey => $cDef): ?>
              <span class="tbl-detail-cell" data-col="<?= $cKey ?>" style="display:none">
                <span class="text-muted me-1"><?= e($cDef['label']) ?>:</span><?= $renderCell($cKey, $e, true) ?>
              </span>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>


<!-- Pagination -->
<?php if ($pag['pages'] > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm justify-content-center">
    <?php if ($pag['has_prev']): ?>
    <li class="page-item">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] - 1])) ?>">&lsaquo;</a>
    </li>
    <?php endif; ?>
    <?php for ($i = max(1, $pag['page'] - 3); $i <= min($pag['pages'], $pag['page'] + 3); $i++): ?>
    <li class="page-item <?= $i === $pag['page'] ? 'active' : '' ?>">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
    <?php if ($pag['has_next']): ?>
    <li class="page-item">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] + 1])) ?>">&rsaquo;</a>
    </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<!-- Bulk Edit Modal -->
<div class="modal fade" id="bulkEditModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Bulk Edit Entries</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= url('entries/bulk-update') ?>" id="bulkEditForm">
        <?= csrfField() ?>
        <div id="bulkIdsContainer"></div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Leave fields empty to keep existing values for the <span class="bulk-count-label fw-semibold text-primary">0</span> selected entries.</p>
          <div class="mb-3">
            <label class="form-label small">Project</label>
            <select name="project_id" class="form-select form-select-sm bg-dark text-white border-secondary">
              <option value="">? keep unchanged ?</option>
              <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small">Entry Type</label>
            <select name="entry_type_id" class="form-select form-select-sm bg-dark text-white border-secondary">
              <option value="">? keep unchanged ?</option>
              <?php foreach ($entryTypes as $et): ?>
              <option value="<?= $et['id'] ?>"><?= e($et['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small">Category</label>
            <select name="error_category_id" class="form-select form-select-sm bg-dark text-white border-secondary">
              <option value="">? keep unchanged ?</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small">Date</label>
            <input type="date" name="entry_date" class="form-control form-control-sm bg-dark text-white border-secondary">
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Apply to <span class="bulk-count-label">0</span> entries</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const exportBase = '<?= url('export/entries') ?>';
let selectedIds = new Set();

// ?? View switching ????????????????????????????????????????
const VIEWS = ['card', 'list', 'table'];
function setView(v) {
  if (!VIEWS.includes(v)) v = 'list';
  localStorage.setItem('entriesView', v);
  VIEWS.forEach(name => {
    const panel = document.getElementById('viewPanel_' + name) || document.getElementById('view' + name.charAt(0).toUpperCase() + name.slice(1));
    if (panel) panel.style.display = name === v ? '' : 'none';
  });
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.view === v);
    btn.classList.toggle('btn-secondary', btn.dataset.view === v);
    btn.classList.toggle('btn-outline-secondary', btn.dataset.view !== v);
  });
}

document.querySelectorAll('.view-btn').forEach(btn => {
  btn.addEventListener('click', () => setView(btn.dataset.view));
});

// Init view from localStorage
setView(localStorage.getItem('entriesView') || 'list');

// ?? Checkbox selection ????????????????????????????????????
function syncSelectAll() {
  const all = document.querySelectorAll('.entry-check');
  const allChecked = [...all].every(cb => selectedIds.has(cb.value));
  document.querySelectorAll('#selectAll, #selectAllTable').forEach(sa => sa.checked = allChecked);
}

document.querySelectorAll('#selectAll, #selectAllTable').forEach(sa => {
  sa.addEventListener('change', function() {
    document.querySelectorAll('.entry-check').forEach(cb => {
      cb.checked = this.checked;
      if (this.checked) selectedIds.add(cb.value); else selectedIds.delete(cb.value);
    });
    document.querySelectorAll('#selectAll, #selectAllTable').forEach(o => o.checked = this.checked);
    updateBulkBar();
  });
});

document.querySelectorAll('.entry-check').forEach(cb => {
  cb.addEventListener('change', function() {
    if (this.checked) selectedIds.add(this.value); else selectedIds.delete(this.value);
    // Sync all checkboxes with same value
    document.querySelectorAll('.entry-check[value="' + this.value + '"]').forEach(o => o.checked = this.checked);
    syncSelectAll();
    updateBulkBar();
  });
});

function updateBulkBar() {
  const n = selectedIds.size;
  document.getElementById('bulkBar').style.display = n > 0 ? '' : 'none';
  document.getElementById('bulkCount').textContent = n + ' selected';
  document.querySelectorAll('.bulk-count-label').forEach(el => el.textContent = n);
  const ids = [...selectedIds].join(',');
  document.querySelectorAll('.bulk-export').forEach(a => {
    const fmt = a.dataset.format;
    a.href = exportBase + '?ids=' + ids + '&format=' + fmt;
    if (fmt === 'pdf') a.setAttribute('target', '_blank'); else a.removeAttribute('target');
  });
  const container = document.getElementById('bulkIdsContainer');
  container.innerHTML = '';
  selectedIds.forEach(id => {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'entry_ids[]'; inp.value = id;
    container.appendChild(inp);
  });
}

function clearSelection() {
  selectedIds.clear();
  document.querySelectorAll('.entry-check').forEach(cb => cb.checked = false);
  document.querySelectorAll('#selectAll, #selectAllTable').forEach(sa => sa.checked = false);
  updateBulkBar();
}

function bulkDelete() {
  const n = selectedIds.size;
  if (!n) return;
  if (!confirm('Delete ' + n + ' selected ' + (n === 1 ? 'entry' : 'entries') + '? This cannot be undone.')) return;
  const container = document.getElementById('bulkDeleteIdsContainer');
  container.innerHTML = '';
  selectedIds.forEach(id => {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'entry_ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulkDeleteForm').submit();
}


// ??????????????????????????????????????????????????????????????
// FULLY CONFIGURABLE TABLE ENGINE
// ??????????????????????????????????????????????????????????????
const TBL_STORE = 'entriesTbl_v4'; // bump to clear stale configs with old sort keys

// Default config from PHP
const TBL_DEFAULTS = <?= isset($tblAllCols) ? json_encode(array_map(fn($k,$d)=>['col'=>$k,'visible'=>$d['vis'],'row'=>$d['row']], array_keys($tblAllCols), array_values($tblAllCols))) : '[]' ?>;

function tblLoad() {
  try { return JSON.parse(localStorage.getItem(TBL_STORE)) || {}; } catch { return {}; }
}
function tblSave(cfg) { localStorage.setItem(TBL_STORE, JSON.stringify(cfg)); }
function tblGet() {
  const saved = tblLoad();
  // Always MERGE with defaults so newly added columns always have sane defaults
  const defVis  = Object.fromEntries(TBL_DEFAULTS.map(d => [d.col, d.visible]));
  const defRows = Object.fromEntries(TBL_DEFAULTS.map(d => [d.col, d.row]));
  return {
    order:   saved.order   || TBL_DEFAULTS.map(d => d.col),
    visible: { ...defVis,  ...(saved.visible  || {}) },
    rows:    { ...defRows, ...(saved.rows     || {}) },
    presets: saved.presets || {},
  };
}

// ?? Apply everything ??????????????????????????????????????????
function tblApply() {
  const cfg = tblGet();
  const sortRow    = document.getElementById('tblSortRow');
  const filterRow  = document.getElementById('tblFilterRow');
  if (!sortRow) return;

  // 1. Reorder columns
  const fixedFirst = sortRow.children[0]; // checkbox
  const fixedLast  = sortRow.children[sortRow.children.length - 1]; // actions
  cfg.order.forEach(col => {
    const th = sortRow.querySelector(`th[data-col="${col}"]`);
    if (th) sortRow.insertBefore(th, fixedLast);
    const fth = filterRow?.querySelector(`th[data-col="${col}"]`);
    if (fth) filterRow.insertBefore(fth, filterRow.lastElementChild);
  });

  // 2. Apply visibility + row assignment
  Object.entries(cfg.visible).forEach(([col, vis]) => {
    const row = cfg.rows[col] ?? 1;
    const showR1 = vis && row === 1;
    const showR2 = vis && row === 2;
    // Header sort row th ? show only for R1
    document.querySelectorAll(`#tblSortRow th[data-col="${col}"]`).forEach(el => {
      el.style.display = showR1 ? '' : 'none';
    });
    // Header filter row th ? show only for R1
    document.querySelectorAll(`#tblFilterRow th[data-col="${col}"]`).forEach(el => {
      el.style.display = showR1 ? '' : 'none';
    });
    // Body main row td ? show for R1
    document.querySelectorAll(`#tblBody .tbl-main-row td[data-col="${col}"]`).forEach(el => {
      el.style.display = showR1 ? '' : 'none';
    });
    // Detail row span ? show for R2 (detail row itself is toggled separately)
    document.querySelectorAll(`#tblBody .tbl-detail-row span.tbl-detail-cell[data-col="${col}"]`).forEach(el => {
      el.style.display = showR2 ? '' : 'none';
    });
  });

  // 3. Sync body column order to header order.
  // IMPORTANT: use the LAST non-data td (actions column), NOT the first (checkbox).
  const order1 = Array.from(sortRow.querySelectorAll('th[data-col]')).map(th => th.dataset.col);
  document.querySelectorAll('#tblBody .tbl-main-row').forEach(tr => {
    const nonData = tr.querySelectorAll('td:not([data-col])');
    const actionsTd = nonData[nonData.length - 1]; // last = actions, first = checkbox
    order1.forEach(col => {
      const td = tr.querySelector(`td[data-col="${col}"]`);
      if (td) tr.insertBefore(td, actionsTd || null);
    });
  });

  // 4. Sync toolbar checkboxes + row buttons
  document.querySelectorAll('.tbl-vis-cb').forEach(cb => {
    const col = cb.dataset.col;
    cb.checked = cfg.visible[col] !== false;
  });
  document.querySelectorAll('.tbl-row-btn').forEach(btn => {
    const col = btn.dataset.col, row = parseInt(btn.dataset.row);
    btn.classList.toggle('active', cfg.rows[col] === row);
    btn.classList.toggle('btn-primary', cfg.rows[col] === row);
    btn.classList.toggle('btn-outline-secondary', cfg.rows[col] !== row);
  });

  renderPresetList();
  applyFilters();
}

// ?? Column visibility toggle ??????????????????????????????????
document.querySelectorAll('.tbl-vis-cb').forEach(cb => {
  cb.addEventListener('change', function() {
    const cfg = tblGet();
    cfg.visible[this.dataset.col] = this.checked;
    tblSave(cfg);
    tblApply(); lockTblColWidths();
  });
});

// ?? Row assignment buttons ????????????????????????????????????
document.querySelectorAll('.tbl-row-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const cfg = tblGet();
    cfg.rows[this.dataset.col] = parseInt(this.dataset.row);
    tblSave(cfg);
    tblApply(); lockTblColWidths();
  });
});

// ?? Drag to reorder ???????????????????????????????????????????
let _dragSrc = null;
document.querySelectorAll('#tblSortRow th[data-col]').forEach(th => {
  th.addEventListener('dragstart', e => {
    _dragSrc = th;
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(() => th.classList.add('opacity-50'), 0);
  });
  th.addEventListener('dragend', () => {
    th.classList.remove('opacity-50');
    document.querySelectorAll('#tblSortRow th').forEach(t => t.classList.remove('table-active'));
  });
  th.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
  th.addEventListener('dragenter', () => { if (th !== _dragSrc && th.dataset.col) th.classList.add('table-active'); });
  th.addEventListener('dragleave', () => th.classList.remove('table-active'));
  th.addEventListener('drop', e => {
    e.preventDefault();
    th.classList.remove('table-active');
    if (!_dragSrc || _dragSrc === th || !th.dataset.col) return;
    const sr = document.getElementById('tblSortRow');
    const ths = Array.from(sr.children);
    if (ths.indexOf(_dragSrc) < ths.indexOf(th)) sr.insertBefore(_dragSrc, th.nextSibling);
    else sr.insertBefore(_dragSrc, th);
    // Save new order
    const cfg = tblGet();
    cfg.order = Array.from(sr.querySelectorAll('th[data-col]')).map(t => t.dataset.col);
    tblSave(cfg);
    tblApply(); lockTblColWidths();
  });
});

// ?? Inline column filters ?????????????????????????????????????
const _filterTimers = {};
document.querySelectorAll('.tbl-filter-input').forEach(inp => {
  inp.addEventListener('input', function() {
    clearTimeout(_filterTimers[this.dataset.col]);
    _filterTimers[this.dataset.col] = setTimeout(applyFilters, 200);
  });
});

// Server-side column filter: update URL and reload
function _getFilterUrl() {
  const params = new URLSearchParams(location.search);
  // Remove old _f_* params
  [...params.keys()].filter(k => k.startsWith('_f_')).forEach(k => params.delete(k));
  // Remove page param so we go back to page 1
  params.delete('page');
  // Add current filter values
  document.querySelectorAll('#tblFilterRow .tbl-filter-input').forEach(inp => {
    if (inp.value.trim()) params.set('_f_' + inp.dataset.col, inp.value.trim());
  });
  return location.pathname + '?' + params.toString();
}

function _applyServerFilters() {
  location.href = _getFilterUrl();
}

function applyFilters() {
  const filters = {};
  document.querySelectorAll('.tbl-filter-input').forEach(inp => {
    const val = inp.value.trim();
    inp.classList.toggle('border-warning', !!val);
    inp.classList.toggle('text-warning', !!val);
    if (val) filters[inp.dataset.col] = val.toLowerCase();
  });

  const hasFilters = Object.keys(filters).length > 0;
  const filterBtn = document.getElementById('filterToggleBtn');
  if (filterBtn) filterBtn.classList.toggle('btn-warning', hasFilters);

  let shown = 0, total = 0;
  document.querySelectorAll('#tblBody .tbl-main-row').forEach(row => {
    total++;
    let fvals = {};
    try { fvals = JSON.parse(row.dataset.fvals || '{}'); } catch {}
    const match = !hasFilters || Object.entries(filters).every(([col, q]) => {
      const cellVal = (fvals[col] || '').toLowerCase();
      // Support multiple values separated by comma or semicolon (OR logic)
      const terms = q.split(/[,;]/).map(t => t.trim()).filter(t => t.length > 0);
      return terms.some(term => cellVal.includes(term));
    });
    row.style.display = match ? '' : 'none';
    const detRow = document.querySelector(`.tbl-detail-row[data-id="${row.dataset.id}"]`);
    if (detRow && !match) detRow.style.display = 'none';
    if (match) shown++;
  });

  // tblFilterInfo removed ? server-side filtering used
}

// Quick-filter by clicking a value in the table (e.g., a status badge)
function quickFilter(col, value) {
  // Show filter row
  const fr = document.getElementById('tblFilterRow');
  if (fr) fr.style.display = '';
  const btn = document.getElementById('filterToggleBtn');
  if (btn) { btn.classList.add('btn-warning'); btn.classList.remove('btn-outline-secondary','btn-info'); }
  // Set the input for that column
  const inp = document.querySelector(`.tbl-filter-input[data-col="${col}"]`);
  if (inp) { inp.value = value; inp.dispatchEvent(new Event('input')); }
}

function clearAllFilters() {
  // Remove ALL _f_* params and _preset, always reload from server
  const params = new URLSearchParams(location.search);
  [...params.keys()].filter(k => k.startsWith('_f_') || k === '_preset').forEach(k => params.delete(k));
  params.delete('page');
  const q = params.toString();
  location.href = location.pathname + (q ? '?' + q : '');
}

function toggleFilterRow() {
  const fr = document.getElementById('tblFilterRow');
  if (!fr) return;
  const visible = fr.style.display !== 'none';
  fr.style.display = visible ? 'none' : '';
  const btn = document.getElementById('filterToggleBtn');
  if (btn) {
    btn.classList.toggle('btn-info', !visible);
    btn.classList.toggle('btn-outline-secondary', visible);
  }
  // Filter bleiben beim Ausblenden erhalten ? nur sichtbare Filter-Zeile toggling
  if (!visible) document.querySelector('.tbl-filter-input')?.focus();
}

// ?? 1. Sticky title: handled via CSS ?????????????????????????

// ?? 2. Column list search ?????????????????????????????????????
function filterColList(q) {
  const lq = q.toLowerCase().trim();
  document.querySelectorAll('.col-list-item').forEach(item => {
    item.style.display = !lq || item.dataset.label.includes(lq) ? '' : 'none';
  });
}

// ?? 3. Export filtered rows ???????????????????????????????????
function exportFiltered(fmt) {
  const cfg = tblGet();
  // Collect visible columns (row 1, visible)
  const visibleCols = cfg.order.filter(col => cfg.visible[col] && cfg.rows[col] !== 2);
  const labels = <?= isset($tblAllCols) ? json_encode(array_map(fn($d)=>$d['label'], array_values($tblAllCols))) : '[]' ?>;
  const colKeys = <?= isset($tblAllCols) ? json_encode(array_keys($tblAllCols)) : '[]' ?>;
  const colLabels = Object.fromEntries(colKeys.map((k,i) => [k, labels[i]]));

  const rows = [];
  // Header
  rows.push(visibleCols.map(col => colLabels[col] || col));
  // Data rows (only visible)
  document.querySelectorAll('#tblBody .tbl-main-row').forEach(tr => {
    if (tr.style.display === 'none') return;
    let fvals = {};
    try { fvals = JSON.parse(tr.dataset.fvals || '{}'); } catch {}
    rows.push(visibleCols.map(col => fvals[col] || ''));
  });

  const sep = fmt === 'tsv' ? '\t' : ',';
  const content = rows.map(r => r.map(v => {
    const s = String(v).replace(/"/g, '""');
    return fmt === 'csv' ? `"${s}"` : s;
  }).join(sep)).join('\n');

  const blob = new Blob(['?' + content], { type: fmt === 'csv' ? 'text/csv;charset=utf-8' : 'text/tab-separated-values' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'entries.' + (fmt === 'tsv' ? 'tsv' : 'csv');
  a.click();
  URL.revokeObjectURL(a.href);
}

// ?? 4. Compact/Comfortable toggle ????????????????????????????
const DENSITY_KEY = 'entriesTblDensity';
function toggleDensity() {
  const tbl = document.getElementById('tblMain');
  const compact = !tbl.classList.contains('tbl-compact');
  tbl.classList.toggle('tbl-compact', compact);
  localStorage.setItem(DENSITY_KEY, compact ? 'compact' : 'comfortable');
  const btn = document.getElementById('tblDensityBtn');
  if (btn) btn.title = compact ? 'Comfortable view' : 'Compact view';
}
(function() {
  if (localStorage.getItem(DENSITY_KEY) === 'compact') {
    document.getElementById('tblMain')?.classList.add('tbl-compact');
  }
})();

// ?? 5. Global search across all columns ??????????????????????
let _globalSearchTimer;
function applyGlobalSearch(q) {
  const clearBtn = document.getElementById('tblGlobalClearBtn');
  if (clearBtn) clearBtn.style.display = q.trim() ? '' : 'none';
  const lq = q.trim().toLowerCase();
  let shown = 0, total = 0;
  document.querySelectorAll('#tblBody .tbl-main-row').forEach(row => {
    total++;
    let fvals = {};
    try { fvals = JSON.parse(row.dataset.fvals || '{}'); } catch {}
    const allText = Object.values(fvals).join(' ').toLowerCase();
    const match = !lq || allText.includes(lq);
    row.style.display = match ? '' : 'none';
    const detRow = document.querySelector(`.tbl-detail-row[data-id="${row.dataset.id}"]`);
    if (detRow && !match) detRow.style.display = 'none';
    if (match) shown++;
  });
  // tblFilterInfo removed ? server-side filtering used
  // Highlight matching text in title cells
  document.querySelectorAll('#tblBody .tbl-main-row td[data-col="title"] a').forEach(a => {
    if (!lq) { a.innerHTML = a.textContent; return; }
    const escaped = lq.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    a.innerHTML = a.textContent.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-warning text-dark">$1</mark>');
  });
}

// ?? Detail row toggle ?????????????????????????????????????????
document.querySelectorAll('.tbl-detail-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const id   = this.dataset.id;
    const det  = document.querySelector(`.tbl-detail-row[data-id="${id}"]`);
    const icon = this.querySelector('i');
    if (!det) return;
    const open = det.style.display !== 'none';
    det.style.display = open ? 'none' : '';
    icon?.classList.toggle('bi-chevron-down', open);
    icon?.classList.toggle('bi-chevron-up', !open);
  });
});

// ?? Presets ? saved to server (persist across devices & browser clears) ??
const _presetCsrf = '<?= e(Auth::csrfToken()) ?>';
let   _serverPresets = []; // [{id, name, config}]

// Load presets from server on init
fetch('<?= url('api/presets') ?>?type=entry_table')
  .then(r => r.json())
  .then(rows => { _serverPresets = rows || []; renderPresetList(); })
  .catch(() => {});

function _collectColWidths() {
  const widths = {};
  document.querySelectorAll('#tblSortRow th[data-col]').forEach(function(th) {
    const w = parseInt(th.style.width);
    if (w > 0) widths[th.dataset.col] = w;
  });
  return widths;
}

function _applyColWidths(widths) {
  if (!widths || !Object.keys(widths).length) return;
  Object.entries(widths).forEach(([col, px]) => {
    document.querySelectorAll(`#tblMain th[data-col="${col}"], #tblMain td[data-col="${col}"]`).forEach(el => {
      el.style.width = px + 'px';
      el.style.minWidth = px + 'px';
    });
  });
}

function _collectFilters() {
  // Collect active column filters
  const colFilters = {};
  document.querySelectorAll('#tblFilterRow input[data-col]').forEach(inp => {
    if (inp.value.trim()) colFilters[inp.dataset.col] = inp.value.trim();
  });
  // Global search
  const globalSearch = document.getElementById('tblGlobalSearch')?.value.trim() || '';
  // Group by
  const groupCols = Array.from(document.querySelectorAll('.grp-check:checked')).map(cb => cb.dataset.col);
  // URL params (project, type, priority filters from sidebar)
  const urlParams = {};
  new URLSearchParams(location.search).forEach((v,k) => { if (k !== 'page') urlParams[k] = v; });
  return { colFilters, globalSearch, groupCols, urlParams };
}

function savePreset() {
  const name = document.getElementById('presetNameInput')?.value.trim();
  if (!name) return;
  const cfg     = tblGet();
  const filters = _collectFilters();
  const config  = JSON.stringify({
    order: cfg.order, visible: cfg.visible, rows: cfg.rows,
    colFilters: filters.colFilters,
    globalSearch: filters.globalSearch,
    groupCols: filters.groupCols,
    urlParams: filters.urlParams,
    colWidths: _collectColWidths(),
  });
  const btn    = document.querySelector('#presetNameInput + button');
  if (btn) { btn.disabled = true; }
  fetch('<?= url('api/presets') ?>', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _presetCsrf, type: 'entry_table', name, config })
  })
  .then(r => r.json())
  .then(d => {
    if (btn) btn.disabled = false;
    if (d.error) { if (typeof showToast==='function') showToast(d.error, 'danger'); return; }
    // Upsert in local array
    const idx = _serverPresets.findIndex(p => p.name === name);
    if (idx >= 0) _serverPresets[idx] = d.preset;
    else _serverPresets.push(d.preset);
    _serverPresets.sort((a,b) => a.name.localeCompare(b.name));
    renderPresetList();
    document.getElementById('presetNameInput').value = '';
    if (typeof showToast==='function') showToast('Preset "' + name + '" saved.', 'success');
  })
  .catch(() => { if (btn) btn.disabled = false; });
}

function loadPreset(id) {
  const p = _serverPresets.find(x => x.id == id);
  if (!p) return;
  let data;
  try { data = typeof p.config === 'string' ? JSON.parse(p.config) : p.config; } catch { return; }
  // 1. Restore columns
  const cfg   = tblGet();
  cfg.order   = data.order   || cfg.order;
  cfg.visible = { ...Object.fromEntries(TBL_DEFAULTS.map(d=>[d.col,d.visible])), ...(data.visible||{}) };
  cfg.rows    = { ...Object.fromEntries(TBL_DEFAULTS.map(d=>[d.col,d.row])),    ...(data.rows||{}) };
  cfg.activePreset = p.name;
  tblSave(cfg);
  tblApply();
  if (data.colWidths) {
    // Apply saved widths ? skip lockTblColWidths so they aren't overwritten
    _applyColWidths(data.colWidths);
    _skipNextLock = true; // prevent rAF lockTblColWidths from overwriting
  } else {
    lockTblColWidths();
  }
  // 2. Restore global search
  if (data.globalSearch) {
    const gs = document.getElementById('tblGlobalSearch');
    if (gs) { gs.value = data.globalSearch; applyGlobalSearch(data.globalSearch); }
  }
  // 3. Restore column filters ? always server-side reload
  {
    const params = new URLSearchParams(location.search);
    [...params.keys()].filter(k => k.startsWith('_f_')).forEach(k => params.delete(k));
    params.delete('page');
    if (data.colFilters && Object.keys(data.colFilters).length) {
      Object.entries(data.colFilters).forEach(([col, val]) => {
        if (val) params.set('_f_' + col, val);
      });
    }
    params.set('_preset', p.name);
    location.href = location.pathname + '?' + params.toString();
    return;
  }
  // 4. Restore URL params (project/type filters) ? reload with params
  if (data.urlParams && Object.keys(data.urlParams).length) {
    const params = new URLSearchParams(data.urlParams);
    const newUrl = location.pathname + '?' + params.toString();
    // Show active preset indicator then navigate
    if (typeof showToast==='function') showToast('Preset "' + p.name + '" loaded ? applying filters...', 'success');
    setTimeout(() => { location.href = newUrl + '&_preset=' + encodeURIComponent(p.name); }, 600);
    return;
  }
  // 5. Show active preset badge
  updateActivePresetBadge(p.name);
  if (typeof showToast==='function') showToast('Preset "' + p.name + '" loaded.', 'success');
}

function updateActivePresetBadge(name) {
  let badge = document.getElementById('activePresetBadge');
  if (!badge) {
    badge = document.createElement('span');
    badge.id = 'activePresetBadge';
    badge.className = 'badge bg-warning text-dark d-flex align-items-center gap-1';
    badge.style.cssText = 'font-size:.75rem;cursor:pointer';
    badge.title = 'Click to clear preset';
    badge.onclick = resetTblConfig;
    const toolbar = document.getElementById('tblToolbar');
    if (toolbar) toolbar.appendChild(badge);
  }
  badge.innerHTML = '<i class="bi bi-bookmark-fill me-1"></i>' + name + ' <i class="bi bi-x-lg ms-1" style="font-size:.65rem"></i>';
  badge.style.display = '';
}

function updatePreset(id, name) {
  if (!confirm('Preset "' + name + '" mit aktueller Konfiguration ?berschreiben?')) return;
  const cfg     = tblGet();
  const filters = _collectFilters();
  const config  = JSON.stringify({
    order: cfg.order, visible: cfg.visible, rows: cfg.rows,
    colFilters: filters.colFilters,
    globalSearch: filters.globalSearch,
    groupCols: filters.groupCols,
    urlParams: filters.urlParams,
    colWidths: _collectColWidths(),
  });
  fetch('<?= url('api/presets') ?>/' + id, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ _csrf: _presetCsrf, config })
  }).then(r => r.json()).then(d => {
    if (d.ok) {
      const p = _serverPresets.find(x => x.id == id);
      if (p) p.config = config;
      renderPresetList();
      updateActivePresetBadge(name);
    } else { alert('Fehler: ' + (d.error || 'Unbekannt')); }
  }).catch(() => alert('Netzwerkfehler'));
}

function deletePreset(id, name) {
  if (!confirm('Delete preset "' + name + '"?')) return;
  fetch('<?= url('api/presets/') ?>' + id + '/delete', {
    method: 'POST', body: new URLSearchParams({ _csrf: _presetCsrf })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      _serverPresets = _serverPresets.filter(p => p.id != id);
      renderPresetList();
    }
  });
}

function renderPresetList() {
  const list = document.getElementById('presetList');
  if (!list) return;
  list.innerHTML = _serverPresets.length
    ? _serverPresets.map(p => {
        const safeName = p.name.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
        return `<div class="d-flex align-items-center gap-1 py-1 border-bottom border-secondary" style="font-size:.82rem">
          <button class="btn btn-link p-0 text-white text-decoration-none flex-grow-1 text-start" onclick="loadPreset(${p.id})">${p.name}</button>
          <button class="btn btn-link p-0 text-info" onclick="updatePreset(${p.id},'${safeName}')" title="Preset aktualisieren"><i class="bi bi-pencil" style="font-size:.7rem"></i></button>
          <button class="btn btn-link p-0 text-danger" onclick="deletePreset(${p.id},'${safeName}')"><i class="bi bi-trash" style="font-size:.7rem"></i></button>
        </div>`;
      }).join('')
    : '<div class="text-muted small p-1">No saved presets</div>';
}

function resetTblConfig() {
  const cfg = tblGet();
  cfg.order   = TBL_DEFAULTS.map(d => d.col);
  cfg.visible  = Object.fromEntries(TBL_DEFAULTS.map(d => [d.col, d.visible]));
  cfg.rows     = Object.fromEntries(TBL_DEFAULTS.map(d => [d.col, d.row]));
  tblSave(cfg);
  localStorage.removeItem(COL_W_KEY);
  // Also clear all _f_* filters and _preset from URL
  const params = new URLSearchParams(location.search);
  [...params.keys()].filter(k => k.startsWith('_f_') || k === '_preset').forEach(k => params.delete(k));
  params.delete('page');
  const q = params.toString();
  location.href = location.pathname + (q ? '?' + q : '');
}

// ?? Select all in table view ??????????????????????????????????
document.getElementById('selectAllTable')?.addEventListener('change', function() {
  document.querySelectorAll('#tblBody .tbl-main-row:not([style*="none"]) .entry-check').forEach(cb => {
    cb.checked = this.checked;
    const id = parseInt(cb.value);
    if (this.checked) selectedIds.add(id); else selectedIds.delete(id);
  });
  updateBulkBar();
});

// Init
tblApply();
initColResize();
applyColWidths();
// Lock column widths after 2 animation frames so table is fully laid out at w-100
requestAnimationFrame(function(){
  requestAnimationFrame(function(){
    lockTblColWidths();
  });
});

// Auto-restore active preset badge if URL has _preset param
(function() {
  const urlPreset = new URLSearchParams(location.search).get('_preset');
  if (urlPreset) {
    // Wait for presets to load then apply column config from matching preset
    const waitForPresets = setInterval(() => {
      if (_serverPresets.length > 0 || document.readyState === 'complete') {
        clearInterval(waitForPresets);
        const p = _serverPresets.find(x => x.name === urlPreset);
        if (p) {
          let data;
          try { data = typeof p.config === 'string' ? JSON.parse(p.config) : p.config; } catch { return; }
          const cfg = tblGet();
          cfg.order   = data.order   || cfg.order;
          cfg.visible = { ...Object.fromEntries(TBL_DEFAULTS.map(d=>[d.col,d.visible])), ...(data.visible||{}) };
          cfg.rows    = { ...Object.fromEntries(TBL_DEFAULTS.map(d=>[d.col,d.row])),    ...(data.rows||{}) };
          tblSave(cfg);
          tblApply();
          if (data.colWidths && Object.keys(data.colWidths).length) {
            // Use rAF so table is fully rendered before applying widths
            requestAnimationFrame(function() {
              requestAnimationFrame(function() {
                _applyColWidths(data.colWidths);
                _skipNextLock = true;
                lockTblColWidths();
              });
            });
          } else {
            requestAnimationFrame(function() {
              requestAnimationFrame(function() {
                lockTblColWidths();
              });
            });
          }
          updateActivePresetBadge(urlPreset);
          // Restore column filters
          if (data.colFilters) {
            const fr = document.getElementById('tblFilterRow');
            if (fr && fr.style.display === 'none') toggleFilterRow();
            Object.entries(data.colFilters).forEach(([col, val]) => {
              const inp = document.querySelector(`#tblFilterRow input[data-col="${col}"]`);
              if (inp) inp.value = val;
            });
            applyFilters();
          }
          if (data.globalSearch) {
            const gs = document.getElementById('tblGlobalSearch');
            if (gs) { gs.value = data.globalSearch; applyGlobalSearch(data.globalSearch); }
          }
        }
      }
    }, 100);
    setTimeout(() => clearInterval(waitForPresets), 3000);
  }
})();

var _skipNextLock = false;
function lockTblColWidths() {
  if (_skipNextLock) { _skipNextLock = false; return; }

  // Pin column widths as percentages so table always fills 100% width.
  const tbl  = document.getElementById('tblMain');
  const ths  = document.querySelectorAll('#tblSortRow th');
  if (!tbl || !ths.length) return;
  const totalW = tbl.getBoundingClientRect().width;
  if (totalW < 10) return;
  ths.forEach(th => {
    const w = th.getBoundingClientRect().width;
    if (w > 0) {
      const pct = (w / totalW * 100).toFixed(2) + '%';
      th.style.width    = pct;
      th.style.minWidth = '';
    }
  });
}

// ?? Column resize ?????????????????????????????????????????????
const COL_W_KEY = 'entriesColWidths_v5'; // v5: fresh start after epic feature changes

function loadColWidths() {
  try { return JSON.parse(localStorage.getItem(COL_W_KEY)) || {}; } catch { return {}; }
}
function saveColWidth(col, px) {
  // Convert px to percentage for storage
  const tbl = document.getElementById('tblMain');
  const totalW = tbl ? tbl.getBoundingClientRect().width : 0;
  const val = totalW > 0 ? (px / totalW * 100).toFixed(2) + '%' : px + 'px';
  const w = loadColWidths(); w[col] = val; localStorage.setItem(COL_W_KEY, JSON.stringify(w));
}
function clearColWidth(col) {
  const w = loadColWidths(); delete w[col]; localStorage.setItem(COL_W_KEY, JSON.stringify(w));
}

function applyColWidths() {
  const widths = loadColWidths();
  Object.entries(widths).forEach(([col, pct]) => {
    document.querySelectorAll(`#tblSortRow th[data-col="${col}"]`).forEach(el => {
      el.style.width = pct;
      el.style.minWidth = '';
    });
  });
}

function initColResize() {
  document.querySelectorAll('#tblSortRow th[data-col]').forEach(th => {
    // Skip if handle already added
    if (th.querySelector('.col-resize-handle')) return;
    const handle = document.createElement('div');
    handle.className = 'col-resize-handle';
    handle.title = 'Drag to resize ? Double-click to reset';
    th.appendChild(handle);

    let startX, startW, col;

    handle.addEventListener('mousedown', e => {
      e.preventDefault();
      e.stopPropagation(); // don't trigger column drag
      col    = th.dataset.col;
      startX = e.clientX;
      startW = th.offsetWidth;
      handle.classList.add('dragging');
      document.body.style.cursor = 'col-resize';
      document.body.style.userSelect = 'none';

      function onMove(e) {
        const newW = Math.max(40, startW + (e.clientX - startX));
        document.querySelectorAll(`#tblMain th[data-col="${col}"], #tblMain td[data-col="${col}"]`).forEach(el => {
          el.style.width = newW + 'px';
          el.style.minWidth = newW + 'px';
        });
      }
      function onUp(e) {
        const newW = Math.max(40, startW + (e.clientX - startX));
        saveColWidth(col, newW);
        handle.classList.remove('dragging');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });

    // Double-click ? reset to auto width
    handle.addEventListener('dblclick', e => {
      e.stopPropagation();
      col = th.dataset.col;
      clearColWidth(col);
      document.querySelectorAll(`#tblMain th[data-col="${col}"], #tblMain td[data-col="${col}"]`).forEach(el => {
        el.style.width = '';
        el.style.minWidth = '';
      });
    });
  });
}



// ?? Bulk sync check (Jira / Zentao) ??????????????????????
function bulkCheckSync(type, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking?';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const url  = type === 'jira' ? '<?= url('api/jira-sync/bulk-check') ?>' : '<?= url('api/zentao-sync/bulk-check') ?>';
  fetch(url, { method: 'POST', body: new URLSearchParams({ _csrf: csrf, force: '1' }) })
    .then(r => r.json())
    .then(d => {
      btn.disabled = false; btn.innerHTML = orig;
      const changed = d.changed ?? 0;
      if (changed > 0) {
        if (typeof showToast === 'function') showToast(changed + ' entr' + (changed === 1 ? 'y has' : 'ies have') + ' new changes from ' + (type === 'jira' ? 'Jira' : 'Zentao') + '.', 'warning');
        setTimeout(() => location.reload(), 1200);
      } else {
        if (typeof showToast === 'function') showToast((type === 'jira' ? 'Jira' : 'Zentao') + ': all entries up to date.', 'success');
      }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = orig; });
}

// ?? Sprint dropdown (lazy-load) ???????????????????????????
let _sprintsLoaded = false;
document.getElementById('sprintDropdown')?.closest('.dropdown')?.addEventListener('show.bs.dropdown', () => {
  if (_sprintsLoaded) return;
  fetch('<?= url('api/sprints') ?>')
    .then(r => r.json())
    .then(sprints => {
      const ul = document.getElementById('sprintDropdown');
      if (!sprints.length) {
        ul.innerHTML = '<li><a class="dropdown-item text-muted small" href="<?= url('sprints/create') ?>">No sprints ? create one</a></li>';
      } else {
        ul.innerHTML = sprints.map(s =>
          `<li><button class="dropdown-item small" onclick="addSelectedToSprint(${s.id}, ${JSON.stringify(s.name)})">
            <span class="badge bg-${s.status==='active'?'success':'secondary'} me-1" style="font-size:.6rem">${s.status}</span>
            ${s.name}
          </button></li>`
        ).join('') + '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item small" href="<?= url('sprints/create') ?>"><i class="bi bi-plus-lg me-1"></i>New Sprint</a></li>';
      }
      _sprintsLoaded = true;
    });
});

function addSelectedToSprint(sprintId, sprintName) {
  if (!selectedIds.size) { if(typeof showToast==='function') showToast('No entries selected.','warning'); return; }
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const body = new URLSearchParams({ _csrf: csrf });
  selectedIds.forEach(id => body.append('entry_ids[]', id));
  fetch(`/sprints/${sprintId}/entries`, { method: 'POST', body })
    .then(r => { if (r.redirected || r.ok) showToast(`${selectedIds.size} ticket(s) added to "${sprintName}".`, 'success'); })
    .catch(() => showToast('Failed to add to sprint.', 'danger'));
}

// Flip chevron when mobile filter panel opens/closes
(function() {
  const fb = document.getElementById('filterBody');
  const ch = document.getElementById('filterChevron');
  if (!fb || !ch) return;
  fb.addEventListener('show.bs.collapse', () => { ch.classList.replace('bi-chevron-down', 'bi-chevron-up'); });
  fb.addEventListener('hide.bs.collapse', () => { ch.classList.replace('bi-chevron-up', 'bi-chevron-down'); });
})();

// ?? Multi-Level Collapsible Grouping ?????????????????????????
let _groupLevels    = JSON.parse(localStorage.getItem('tbl_group_levels')    || '["","","",""]');
let _collapsedPaths = new Set(JSON.parse(localStorage.getItem('tbl_collapsed_groups') || '[]'));

function onGroupLevelChange() {
  const sels  = [...document.querySelectorAll('.group-level-sel')];
  let empty   = false;
  _groupLevels = sels.map(s => s.value).map(v => { if (empty||!v){empty=true;return '';} return v; });
  sels.forEach((s,i) => { if (s.value !== _groupLevels[i]) s.value = _groupLevels[i]; });
  _collapsedPaths.clear();
  _saveGroupState(); _updateGroupBtn(); reapplyGrouping();
}

function clearGrouping() {
  _groupLevels = ['','','',''];
  _collapsedPaths.clear();
  document.querySelectorAll('.group-level-sel').forEach(s => s.value = '');
  _saveGroupState(); _updateGroupBtn(); reapplyGrouping();
}

function toggleGroup(enc) {
  const path = decodeURIComponent(enc);
  if (_collapsedPaths.has(path)) _collapsedPaths.delete(path);
  else                            _collapsedPaths.add(path);
  _saveGroupState(); reapplyGrouping();
}

function _saveGroupState() {
  try { localStorage.setItem('tbl_group_levels',    JSON.stringify(_groupLevels)); } catch(e) {}
  try { localStorage.setItem('tbl_collapsed_groups', JSON.stringify([..._collapsedPaths])); } catch(e) {}
}

function _updateGroupBtn() {
  const n = _groupLevels.filter(l => l).length;
  const lbl = document.getElementById('groupByLabel');
  const btn = document.getElementById('groupByBtn');
  if (lbl) lbl.textContent = n ? 'Grouped (' + n + ')' : 'Group by';
  if (btn) btn.classList.toggle('btn-warning', n > 0);
}

function _isAncestorCollapsed(gVals, depth) {
  for (let i = 0; i < depth; i++) {
    if (_collapsedPaths.has(gVals.slice(0, i+1).join('\x00'))) return true;
  }
  return false;
}

function reapplyGrouping() {
  document.querySelectorAll('#tblBody tr.tbl-group-header').forEach(r => r.remove());
  const actLvls = _groupLevels.filter(l => l);
  const tbody   = document.getElementById('tblBody');
  if (!tbody || !actLvls.length) return;

  // Re-evaluate filter visibility from the actual search/filter inputs ? never from style.display,
  // because style.display also reflects group-collapse state which would corrupt the counts.
  const _srchQ  = (document.getElementById('tblGlobalSearch')?.value || '').trim().toLowerCase();
  const _colFlt = {};
  document.querySelectorAll('.tbl-filter-input').forEach(inp => {
    const v = inp.value.trim(); if (v) _colFlt[inp.dataset.col] = v.toLowerCase();
  });
  const filterVis = {};
  tbody.querySelectorAll('tr.tbl-main-row').forEach(r => {
    let fvals = {};
    try { fvals = JSON.parse(r.dataset.fvals || '{}'); } catch(e) {}
    let ok = true;
    if (_srchQ) ok = Object.values(fvals).join(' ').toLowerCase().includes(_srchQ);
    if (ok && Object.keys(_colFlt).length)
      ok = Object.entries(_colFlt).every(([col, q]) => (fvals[col] || '').toLowerCase().includes(q));
    filterVis[r.dataset.id] = ok;
  });

  // Collect ALL data rows (filtered + unfiltered) with their group values
  const pairs = [];
  tbody.querySelectorAll('tr.tbl-main-row').forEach(row => {
    const detail  = tbody.querySelector('tr.tbl-detail-row[data-id="' + row.dataset.id + '"]');
    let fvals = {};
    try { fvals = JSON.parse(row.dataset.fvals || '{}'); } catch(e) {}
    const gVals   = actLvls.map(l => (String(fvals[l] || '')).trim() || '?');
    const visible = filterVis[row.dataset.id] !== false;
    pairs.push({ row, detail, gVals, visible });
  });

  // Multi-key stable sort
  pairs.sort((a, b) => {
    for (let i = 0; i < actLvls.length; i++) {
      const c = a.gVals[i].localeCompare(b.gVals[i], undefined, { sensitivity: 'base' });
      if (c) return c;
    }
    return 0;
  });

  // Count visible rows per group path (for the counter badge)
  const counts = {};
  pairs.forEach(({ gVals, visible }) => {
    if (!visible) return;
    for (let i = 0; i < actLvls.length; i++) {
      const k = gVals.slice(0, i+1).join('\x00');
      counts[k] = (counts[k] || 0) + 1;
    }
  });

  const colSpan  = tbody.closest('table').querySelector('thead tr:first-child')?.children.length || 12;
  const lastVals = actLvls.map(() => undefined);
  const LVL_BG  = ['rgba(255,255,255,.09)','rgba(255,255,255,.055)','rgba(255,255,255,.03)','rgba(255,255,255,.015)'];
  const LVL_BDR = [.35, .22, .13, .07];
  const LVL_OP  = [1, .85, .75, .65];

  pairs.forEach(({ row, detail, gVals, visible }) => {
    // Find first changed level
    let firstChanged = -1;
    for (let i = 0; i < actLvls.length; i++) {
      if (gVals[i] !== lastVals[i]) { firstChanged = i; break; }
    }

    if (firstChanged >= 0) {
      for (let j = firstChanged; j < actLvls.length; j++) {
        lastVals[j] = gVals[j];
        const path = gVals.slice(0, j+1).join('\x00');
        if (!(counts[path] > 0)) continue; // skip groups with no visible rows
        const parentHidden = _isAncestorCollapsed(gVals, j);
        const collapsed    = _collapsedPaths.has(path);
        const indent       = j * 16;
        const enc          = encodeURIComponent(path);
        const hdr          = document.createElement('tr');
        hdr.className      = 'tbl-group-header';
        hdr.dataset.groupPath  = path;
        hdr.dataset.groupLevel = String(j);
        hdr.style.display  = parentHidden ? 'none' : '';
        hdr.innerHTML =
          '<td colspan="' + colSpan + '" onclick="toggleGroup(\'' + enc.replace(/'/g,"\\'") + '\')" style="' +
          'background:' + LVL_BG[j] + ';padding:3px 8px 3px ' + (10+indent) + 'px;' +
          'cursor:pointer;border-left:3px solid rgba(255,255,255,' + LVL_BDR[j] + ')">' +
          '<i class="bi bi-chevron-' + (collapsed?'right':'down') + ' me-1" style="font-size:.6rem;opacity:.7"></i>' +
          '<span style="font-size:.72rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;opacity:' + LVL_OP[j] + '">' +
          escHtml(gVals[j]) + '</span>' +
          '<span class="text-muted ms-2" style="font-size:.68rem">(' + counts[path] + ')</span>' +
          '</td>';
        tbody.appendChild(hdr);
      }
    }

    // Apply visibility: hidden by filter OR by collapsed ancestor
    const grpHidden = _isAncestorCollapsed(gVals, actLvls.length);
    row.style.display = (!visible || grpHidden) ? 'none' : '';
    tbody.appendChild(row);
    if (detail) {
      tbody.appendChild(detail);
      if (!visible || grpHidden) detail.style.display = 'none';
    }
  });
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Re-apply grouping after any filter change
(function() {
  ['applyGlobalSearch', 'applyFilters'].forEach(fn => {
    const orig = window[fn];
    if (typeof orig === 'function') {
      window[fn] = function(...args) { orig.apply(this, args); reapplyGrouping(); };
    }
  });
})();

// Init on page load: restore selects and re-apply grouping
document.addEventListener('DOMContentLoaded', () => {
  _groupLevels.forEach((v, i) => { const s = document.getElementById('groupLevel' + i); if (s && v) s.value = v; });
  _updateGroupBtn();
  if (_groupLevels.some(l => l)) reapplyGrouping();
});

// ??????????????????????????????????????????????????????????????
// Preset-Verbesserungen: Button-Leiste ?ber der Liste + Standard-Preset
// ??????????????????????????????????????????????????????????????
const _dpCsrf = '<?= e(Auth::csrfToken()) ?>';
let _defaultPresetId = null;
let _defaultApplied  = false;

function ensurePresetBar() {
  let bar = document.getElementById('presetBar');
  if (bar) return bar;
  const viewTable = document.getElementById('viewTable');
  if (!viewTable) return null;
  const card = viewTable.querySelector('.card');
  bar = document.createElement('div');
  bar.id = 'presetBar';
  bar.className = 'mb-2 d-flex flex-wrap align-items-center gap-1';
  if (card && card.parentNode) card.parentNode.insertBefore(bar, card);
  else viewTable.insertBefore(bar, viewTable.firstChild);
  return bar;
}

function _presetIsDefault(p) { return !!p && _defaultPresetId != null && p.id == _defaultPresetId; }

function _presetMatchesCurrent(p) {
  try {
    const data = (typeof p.config === 'string') ? JSON.parse(p.config) : p.config;
    const cfg  = tblGet();
    return JSON.stringify(data.order || [])   === JSON.stringify(cfg.order)
        && JSON.stringify(data.visible || {}) === JSON.stringify(cfg.visible)
        && JSON.stringify(data.rows || {})    === JSON.stringify(cfg.rows);
  } catch (e) { return false; }
}

function renderPresetBar() {
  const bar = ensurePresetBar(); if (!bar) return;
  const presets = _serverPresets || [];
  if (!presets.length) {
    bar.innerHTML = '<span class="text-muted small"><i class="bi bi-bookmark me-1"></i>Noch keine Presets gespeichert</span>';
    return;
  }
  bar.innerHTML = '<span class="text-muted small me-1"><i class="bi bi-bookmark me-1"></i>Presets:</span>' +
    presets.map(function (p) {
      const safe   = String(p.name).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      const isDef  = _presetIsDefault(p);
      const active = _presetMatchesCurrent(p);
      return '<div class="btn-group btn-group-sm me-1 mb-1" role="group">'
        + '<button type="button" class="btn ' + (active ? 'btn-primary' : 'btn-outline-secondary') + '" onclick="loadPreset(' + p.id + ')" title="Diese Ansicht laden">'
        + (isDef ? '<i class="bi bi-star-fill text-warning me-1"></i>' : '') + safe
        + '</button>'
        + '<button type="button" class="btn ' + (isDef ? 'btn-warning' : 'btn-outline-secondary') + '" onclick="togglePresetDefault(' + p.id + ')" title="' + (isDef ? 'Als Standard-Ansicht entfernen' : 'Als Standard-Ansicht festlegen') + '">'
        + '<i class="bi ' + (isDef ? 'bi-star-fill' : 'bi-star') + '"></i>'
        + '</button>'
        + '</div>';
    }).join('');
}

// Wrap the existing renderPresetList so the bar + default stay in sync
const _origRenderPresetList = (typeof renderPresetList === 'function') ? renderPresetList : null;
renderPresetList = function () {
  if (_origRenderPresetList) _origRenderPresetList();
  renderPresetBar();
  tryApplyDefaultPreset();
};

function tryApplyDefaultPreset() {
  if (_defaultApplied) return;
  if (sessionStorage.getItem('presetDefaultApplied') === '1') { _defaultApplied = true; return; }
  if (_defaultPresetId == null) return;
  const def = (_serverPresets || []).find(function (p) { return p.id == _defaultPresetId; });
  if (def) {
    _defaultApplied = true;
    sessionStorage.setItem('presetDefaultApplied', '1');
    loadPreset(def.id);
  }
}

function togglePresetDefault(id) {
  const makeDefault = (_defaultPresetId != id);
  fetch('<?= url('api/presets/') ?>' + id + '/default', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _dpCsrf, value: makeDefault ? '1' : '0' })
  })
  .then(function (r) { return r.json(); })
  .then(function (d) {
    if (d && d.success) {
      _defaultPresetId = makeDefault ? id : null;
      renderPresetList();
      if (typeof showToast === 'function')
        showToast(makeDefault ? 'Standard-Ansicht festgelegt.' : 'Standard-Ansicht entfernt.', 'success');
    } else if (typeof showToast === 'function') {
      showToast((d && d.error) || 'Fehler beim Speichern.', 'danger');
    }
  })
  .catch(function () {});
}

// Which preset is the default? (then maybe auto-apply it on first load)
fetch('<?= url('api/preset-default') ?>?type=entry_table')
  .then(function (r) { return r.json(); })
  .then(function (d) { _defaultPresetId = (d && d.default_id) ? d.default_id : null; renderPresetBar(); tryApplyDefaultPreset(); })
  .catch(function () {});

// Build the bar right away (fills once presets have loaded)
renderPresetBar();


// Sub-ticket collapse/expand
var _subCollapsed = {};


function toggleSubTicketsById(parentId) {
  // Called from chevron in title cell ? find the toggle btn and delegate
  var btn = document.querySelector('.sub-toggle-btn[data-parent="' + parentId + '"]');
  if (btn) { toggleSubTickets(btn, parentId); return; }
  // No button visible ? toggle directly
  _subCollapsed[parentId] = !_subCollapsed[parentId];
  var collapsed = _subCollapsed[parentId];
  document.querySelectorAll('tr[data-parent-id="' + parentId + '"]').forEach(function(r) {
    r.style.display = collapsed ? 'none' : '';
  });
  var chev = document.getElementById('sub-chev-' + parentId);
  if (chev) chev.className = (collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-down') + ' text-info me-1 sub-row-chevron';
}

function toggleSubTickets(btn, parentId) {
  var collapsed = _subCollapsed[parentId];
  _subCollapsed[parentId] = !collapsed;
  var rows = document.querySelectorAll('tr[data-parent-id="' + parentId + '"]');
  rows.forEach(function(row) { row.style.display = collapsed ? '' : 'none'; });
  var icon = btn.querySelector('i');
  icon.className = collapsed ? 'bi bi-diagram-2' : 'bi bi-diagram-2-fill';
  btn.classList.toggle('btn-outline-info', collapsed);
  btn.classList.toggle('btn-outline-secondary', !collapsed);
  // Update chevron in title cell
  var chev = document.getElementById('sub-chev-' + parentId);
  if (chev) chev.className = (!collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-down') + ' text-info me-1 sub-row-chevron';
  // Persist per-parent state
  try {
    var saved = JSON.parse(localStorage.getItem(_LS_KEY_P) || '{}');
    saved[parentId] = !collapsed;
    localStorage.setItem(_LS_KEY_P, JSON.stringify(saved));
  } catch(e) {}
}

var _epicCollapsed = {};
function toggleEpicGroup(epicId) {
  _epicCollapsed[epicId] = !_epicCollapsed[epicId];
  var collapsed = _epicCollapsed[epicId];
  // Hide/show rows belonging to this epic
  document.querySelectorAll('#tblBody tr.tbl-main-row[data-epic-id="' + epicId + '"]').forEach(function(row) {
    row.style.display = collapsed ? 'none' : '';
    var det = document.querySelector('#tblBody .tbl-detail-row[data-id="' + row.dataset.id + '"]');
    if (det) det.style.display = 'none';
  });
  // Update chevron
  var chev = document.getElementById('epic-chev-' + epicId);
  if (chev) chev.className = (collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-down') + ' me-1';
  // Persist state
  try {
    var s = JSON.parse(localStorage.getItem(_LS_EPIC) || '{}');
    s[epicId] = collapsed;
    localStorage.setItem(_LS_EPIC, JSON.stringify(s));
  } catch(e) {}
}

// Enter key on filter inputs triggers server-side filter
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.tbl-filter-input').forEach(function(inp) {
    inp.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') _applyServerFilters();
    });
  });
});

// Preserve _f_* params on entries page navigation
document.addEventListener('click', function(e) {
  var a = e.target.closest('a[href]');
  if (!a) return;
  var href = a.getAttribute('href');
  if (!href || href.startsWith('#') || href.startsWith('http')) return;
  if (!/\/entries(\?|$)/.test(href)) return;
  if (/\/entries\/\d+/.test(href)) return;
  var cur = new URLSearchParams(location.search);
  var hasF = [...cur.keys()].some(k => k.startsWith('_f_'));
  if (!hasF) return;
  e.preventDefault();
  var u = new URL(href, location.origin);
  cur.forEach(function(v,k) { if (k.startsWith('_f_')) u.searchParams.set(k,v); });
  location.href = u.pathname + '?' + u.searchParams.toString();
}, true);
</script>

<?php
$ewCols = [
  ['id',                   'ID',              'bi-hash'],
  ['epic',                 'Epic',            'bi-lightning-fill'],
  ['parent_title',         'Parent Ticket',   'bi-diagram-2'],
  ['is_sub',               'Sub-Ticket',      'bi-arrow-return-right'],
  ['entry_date',           'Datum',           'bi-calendar'],
  ['entry_time',           'Zeit',            'bi-clock'],
  ['title',                'Titel',           'bi-card-text'],
  ['status',               'Status',          'bi-circle-fill'],
  ['priority',             'Priorität',       'bi-exclamation-triangle'],
  ['type_name',            'Typ',             'bi-tag'],
  ['cat_name',             'Kategorie',       'bi-folder'],
  ['project_name',         'Projekt',         'bi-briefcase'],
  ['mower_serial',         'Seriennummer',    'bi-upc'],
  ['firmware_version',     'Firmware',        'bi-cpu'],
  ['app_version',          'App Version',     'bi-phone'],
  ['creator',              'Ersteller',       'bi-person'],
  ['description',          'Beschreibung',    'bi-text-paragraph'],
  ['tags',                 'Tags',            'bi-tags'],
  ['jira_issue_key',       'Jira Key',        'bi-bug'],
  ['jira_issue_url',       'Jira URL',        'bi-link-45deg'],
  ['jira_status',          'Jira Status',     'bi-circle-half'],
  ['zentao_bug_id',        'Zentao ID',       'bi-bug-fill'],
  ['zentao_bug_url',       'Zentao URL',      'bi-link-45deg'],
  ['zentao_status',        'Zentao Status',   'bi-circle-half'],
  ['project_status_robot', 'Robot Status',    'bi-robot'],
  ['temperature',          'Temperatur',      'bi-thermometer'],
  ['weather_condition',    'Wetter',          'bi-cloud'],
  ['gps_lat',              'GPS Lat',         'bi-geo-alt'],
  ['gps_lon',              'GPS Lon',         'bi-geo-alt-fill'],
  ['sharepoint_url',       'Sharepoint Link', 'bi-cloud-arrow-up'],
];
$ewDefault = ['entry_date','title','status','priority','type_name','cat_name','project_name',
              'mower_serial','firmware_version','jira_issue_key','zentao_bug_id','creator'];
?>

<div class="modal fade" id="exportWizardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-download me-2"></i>Export</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-2 mb-4">
          <span class="badge rounded-pill bg-primary" id="ewBadge1">1 Format</span>
          <span class="text-muted">&rsaquo;</span>
          <span class="badge rounded-pill bg-secondary" id="ewBadge2">2 Spalten</span>
          <span class="text-muted">&rsaquo;</span>
          <span class="badge rounded-pill bg-secondary" id="ewBadge3">3 Optionen</span>
        </div>

        <!-- Step 1: Format -->
        <div id="ewStep1">
          <p class="text-muted small mb-3">Welches Format soll exportiert werden?</p>
          <div class="row g-3">
            <?php foreach ([
              ['xlsx','Excel (.xlsx)','bi-file-earmark-spreadsheet','text-success'],
              ['csv', 'CSV',          'bi-filetype-csv',            'text-info'],
              ['pdf', 'PDF',          'bi-file-pdf',                'text-danger'],
            ] as [$fmt,$lbl,$ico,$clr]): ?>
            <div class="col-4">
              <div class="card border-secondary bg-secondary text-center p-3 ew-fmt-card"
                   id="ewFmt_<?= $fmt ?>" onclick="ewSelectFormat('<?= $fmt ?>')" style="cursor:pointer">
                <i class="bi <?= $ico ?> <?= $clr ?>" style="font-size:2rem"></i>
                <div class="fw-semibold mt-2"><?= $lbl ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Step 2: Spalten -->
        <div id="ewStep2" style="display:none">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted small mb-0">Welche Spalten exportieren?</p>
            <div class="d-flex gap-1 flex-wrap">
              <button class="btn btn-outline-primary btn-sm py-0 px-2" onclick="ewUsePresetCols()" title="Sichtbare Spalten aus aktivem Preset übernehmen">
                <i class="bi bi-bookmark me-1"></i>Aktuelles Preset
              </button>
              <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="ewToggleAll(true)">Alle</button>
              <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="ewToggleAll(false)">Keine</button>
            </div>
          </div>
          <div class="row g-2">
            <?php foreach ($ewCols as [$key,$label,$icon]):
              $checked = in_array($key, $ewDefault); ?>
            <div class="col-6">
              <label class="d-flex align-items-center gap-2 p-2 rounded border ew-col-row <?= $checked ? 'border-primary bg-primary bg-opacity-10' : 'border-secondary' ?>"
                     style="cursor:pointer">
                <input type="checkbox" class="form-check-input ew-col-cb mt-0 flex-shrink-0"
                       value="<?= $key ?>" <?= $checked ? 'checked' : '' ?>>
                <i class="bi <?= $icon ?> text-muted flex-shrink-0" style="font-size:.75rem"></i>
                <span style="font-size:.85rem"><?= $label ?></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Step 3: Optionen -->
        <div id="ewStep3" style="display:none">
          <p class="text-muted small mb-3">Weitere Optionen</p>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Umfang</label>
            <div class="d-flex flex-column gap-2">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="ewScope" id="ewScopeAll" value="all" checked>
                <label class="form-check-label" for="ewScopeAll">
                  Alle gefilterten Einträge <span class="badge bg-secondary ms-1"><?= number_format($pag['total'] ?? 0) ?></span>
                </label>
              </div>
              <div class="form-check" id="ewScopeSelWrap" style="display:none">
                <input class="form-check-input" type="radio" name="ewScope" id="ewScopeSel" value="selected">
                <label class="form-check-label" for="ewScopeSel">
                  Nur ausgewählte Einträge <span class="badge bg-secondary ms-1" id="ewSelCount"></span>
                </label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="ewSort">Sortierung</label>
            <select class="form-select form-select-sm bg-dark text-white border-secondary" id="ewSort">
              <option value="date_desc">Datum absteigend (neueste zuerst)</option>
              <option value="date_asc">Datum aufsteigend</option>
              <option value="title_asc">Titel A&ndash;Z</option>
              <option value="status">Status</option>
            </select>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="ewTruncDesc" checked>
            <label class="form-check-label small" for="ewTruncDesc">Beschreibung kürzen (max. 300 Zeichen)</label>
          </div>
          <div class="form-check mt-2" id="ewImagesRow">
            <input class="form-check-input" type="checkbox" id="ewIncludeImages" checked>
            <label class="form-check-label small" for="ewIncludeImages">
              <i class="bi bi-image me-1"></i>Bilder einbetten (nur XLSX)
            </label>
          </div>
        </div>
      </div>

      <div class="modal-footer border-secondary">
        <button class="btn btn-outline-secondary" id="ewBtnBack" style="display:none" onclick="ewPrev()">
          <i class="bi bi-chevron-left me-1"></i>Zurück
        </button>
        <button class="btn btn-secondary ms-auto" data-bs-dismiss="modal">Abbrechen</button>
        <button class="btn btn-primary" id="ewBtnNext" onclick="ewNext()">
          Weiter <i class="bi bi-chevron-right ms-1"></i>
        </button>
        <button class="btn btn-success" id="ewBtnExport" style="display:none" onclick="ewExport()">
          <i class="bi bi-download me-1"></i>Exportieren
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.ew-fmt-card { transition: border-color .15s, background .15s; border: 2px solid transparent !important; }
.ew-fmt-card.selected { border-color: #6366f1 !important; background: #1e1e4a !important; }
.ew-col-row { transition: background .12s, border-color .12s; }
</style>

<script>
(function() {
  var _ewStep = 1;
  var _ewFmt  = '';
  var _ewBase = '<?= addslashes($exportParams) ?>';
  var _ewBase2 = '<?= addslashes(url('export/entries')) ?>';

  window.openExportWizard = function() {
    _ewStep = 1; _ewFmt = '';
    ewShowStep(1);
    ['xlsx','csv','pdf'].forEach(function(f) {
      var el = document.getElementById('ewFmt_'+f);
      if (el) el.classList.remove('selected');
    });
    var checked = document.querySelectorAll('.entry-check:checked');
    var wrap = document.getElementById('ewScopeSelWrap');
    if (wrap) {
      wrap.style.display = checked.length ? '' : 'none';
      var cnt = document.getElementById('ewSelCount');
      if (cnt) cnt.textContent = checked.length;
    }
    new bootstrap.Modal(document.getElementById('exportWizardModal')).show();
  };

  window.ewSelectFormat = function(fmt) {
    _ewFmt = fmt;
    ['xlsx','csv','pdf'].forEach(function(f) {
      var el = document.getElementById('ewFmt_'+f);
      if (el) el.classList.toggle('selected', f === fmt);
    });
  };

  function ewShowStep(s) {
    [1,2,3].forEach(function(n) {
      var step = document.getElementById('ewStep'+n);
      var badge = document.getElementById('ewBadge'+n);
      if (step) step.style.display = n === s ? '' : 'none';
      if (badge) {
        badge.className = 'badge rounded-pill ' + (n === s ? 'bg-primary' : (n < s ? 'bg-success' : 'bg-secondary'));
      }
    });
    document.getElementById('ewBtnBack').style.display  = s > 1 ? '' : 'none';
    document.getElementById('ewBtnNext').style.display  = s < 3 ? '' : 'none';
    document.getElementById('ewBtnExport').style.display = s === 3 ? '' : 'none';
  }

  window.ewNext = function() {
    if (_ewStep === 1 && !_ewFmt) { alert('Bitte ein Format wählen.'); return; }
    if (_ewStep === 2) {
      var any = document.querySelectorAll('.ew-col-cb:checked').length;
      if (!any) { alert('Bitte mindestens eine Spalte wählen.'); return; }
    }
    _ewStep = Math.min(3, _ewStep + 1);
    ewShowStep(_ewStep);
  };

  window.ewPrev = function() {
    _ewStep = Math.max(1, _ewStep - 1);
    ewShowStep(_ewStep);
  };

  window.ewUsePresetCols = function() {
    // Get currently visible columns from the table header
    var visibleCols = [];
    document.querySelectorAll('#tblSortRow th[data-col]').forEach(function(th) {
      if (th.offsetParent !== null) visibleCols.push(th.dataset.col);
    });
    if (!visibleCols.length) { alert('Kein aktives Preset erkannt.'); return; }
    // Map table col keys to export col keys (they may differ slightly)
    var colMap = { 'type': 'type_name', 'category': 'cat_name', 'project': 'project_name',
                   'creator': 'creator', 'jira': 'jira_issue_key', 'zentao': 'zentao_bug_id',
                   'serial': 'mower_serial', 'firmware': 'firmware_version' };
    document.querySelectorAll('.ew-col-cb').forEach(function(cb) {
      var exportKey = cb.value;
      var tableKey  = Object.keys(colMap).find(k => colMap[k] === exportKey) || exportKey;
      var on = visibleCols.includes(exportKey) || visibleCols.includes(tableKey);
      cb.checked = on;
      var row = cb.closest('.ew-col-row');
      if (row) {
        row.classList.toggle('border-primary', on);
        row.classList.toggle('bg-primary', on);
        row.classList.toggle('bg-opacity-10', on);
        row.classList.toggle('border-secondary', !on);
      }
    });
  };

  window.ewToggleAll = function(state) {
    document.querySelectorAll('.ew-col-cb').forEach(function(cb) {
      cb.checked = state;
      var row = cb.closest('.ew-col-row');
      if (row) {
        row.classList.toggle('border-primary', state);
        row.classList.toggle('bg-primary', state);
        row.classList.toggle('bg-opacity-10', state);
        row.classList.toggle('border-secondary', !state);
      }
    });
  };

  // Toggle col row highlight on change
  document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('ew-col-cb')) return;
    var row = e.target.closest('.ew-col-row');
    if (!row) return;
    var on = e.target.checked;
    row.classList.toggle('border-primary', on);
    row.classList.toggle('bg-primary', on);
    row.classList.toggle('bg-opacity-10', on);
    row.classList.toggle('border-secondary', !on);
  });

  window.ewExport = function() {
    var cols = [];
    document.querySelectorAll('.ew-col-cb:checked').forEach(function(cb) { cols.push(cb.value); });
    var scope = (document.querySelector('input[name="ewScope"]:checked') || {}).value || 'all';
    var ids = '';
    if (scope === 'selected') {
      ids = [...document.querySelectorAll('.entry-check:checked')].map(function(cb) { return cb.value; }).join(',');
    }
    var sort     = (document.getElementById('ewSort') || {}).value || 'date_desc';
    var truncDesc = document.getElementById('ewTruncDesc')?.checked ? '1' : '0';
    var includeImages = document.getElementById('ewIncludeImages')?.checked ? '1' : '0';

    var params = new URLSearchParams(_ewBase);
    params.set('format', _ewFmt);
    params.set('cols', cols.join(','));
    params.set('sort', sort);
    params.set('trunc_desc', truncDesc);
    params.set('include_images', includeImages);
    if (ids) params.set('ids', ids);

    // Add current _f_* params
    new URLSearchParams(location.search).forEach(function(v, k) {
      if (k.startsWith('_f_')) params.set(k, v);
    });

    var url = _ewBase2 + '?' + params.toString();
    if (_ewFmt === 'pdf') window.open(url, '_blank');
    else location.href = url;

    bootstrap.Modal.getInstance(document.getElementById('exportWizardModal'))?.hide();
  };
})();
</script>
