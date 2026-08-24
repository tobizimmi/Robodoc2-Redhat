<div class="d-flex align-items-center gap-3 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('robots') ?>"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="mb-0"><i class="bi bi-cpu me-2 text-primary"></i><?= e($serial) ?></h4>
    <?php if ($invItem): ?>
    <small class="text-muted"><?= e($invItem['name']) ?> &middot; <?= e($invItem['status']) ?></small>
    <?php endif; ?>
  </div>
  <div class="ms-auto d-flex gap-2">
    <div class="dropdown">
      <button class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-download me-1"></i>Export
      </button>
      <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" style="min-width:220px">
        <li><h6 class="dropdown-header text-muted" style="font-size:.7rem">ENTRIES + LOGBOOK</h6></li>
        <li><a class="dropdown-item" href="<?= url('robots/'.urlencode($serial).'/export?format=xlsx&include=both') ?>"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (.xlsx)</a></li>
        <li><a class="dropdown-item" href="<?= url('robots/'.urlencode($serial).'/export?format=csv&include=both') ?>"><i class="bi bi-filetype-csv me-2 text-info"></i>CSV</a></li>
        <li><a class="dropdown-item" href="<?= url('robots/'.urlencode($serial).'/export?format=json&include=both') ?>"><i class="bi bi-filetype-json me-2 text-warning"></i>JSON</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><h6 class="dropdown-header text-muted" style="font-size:.7rem">ENTRIES ONLY</h6></li>
        <li><a class="dropdown-item" href="<?= url('robots/'.urlencode($serial).'/export?format=xlsx&include=entries') ?>"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (.xlsx)</a></li>
        <li><a class="dropdown-item" href="<?= url('robots/'.urlencode($serial).'/export?format=csv&include=entries') ?>"><i class="bi bi-filetype-csv me-2 text-info"></i>CSV</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><h6 class="dropdown-header text-muted" style="font-size:.7rem">LOGBOOK ONLY</h6></li>
        <li><a class="dropdown-item" href="<?= url('robots/'.urlencode($serial).'/export?format=xlsx&include=logbook') ?>"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (.xlsx)</a></li>
        <li><a class="dropdown-item" href="<?= url('robots/'.urlencode($serial).'/export?format=csv&include=logbook') ?>"><i class="bi bi-filetype-csv me-2 text-info"></i>CSV</a></li>
      </ul>
    </div>
    <a href="<?= url('inventory') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-box-seam me-1"></i>Inventory
    </a>
  </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:#6366f1">
      <div class="card-body p-3">
        <div class="text-muted small">Total Entries</div>
        <div class="stat-number"><?= $stats['total'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:#f59e0b">
      <div class="card-body p-3">
        <div class="text-muted small">Open</div>
        <div class="stat-number"><?= $stats['open'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:#22c55e">
      <div class="card-body p-3">
        <div class="text-muted small">Logbook / Sessions</div>
        <div class="stat-number"><?= $stats['logbook'] ?> / <?= $stats['test_sessions'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card h-100">
      <div class="card-body p-3">
        <div class="text-muted small mb-2">By Type</div>
        <?php foreach ($stats['by_type'] as $typeName => $cnt): ?>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="small flex-grow-1"><?= e($typeName) ?></span>
          <span class="badge bg-secondary"><?= $cnt ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$stats['by_type']): ?>
        <div class="text-muted small">No typed entries</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Firmware progression -->
<?php if ($fwHistory): ?>
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small"><i class="bi bi-code-slash me-2"></i>Firmware Progression</div>
  <div class="card-body p-0">
    <div class="d-flex overflow-auto py-2 px-3 gap-2">
      <?php foreach ($fwHistory as $fw): ?>
      <div class="border border-secondary rounded p-2 flex-shrink-0 text-center" style="min-width:120px">
        <div class="fw-semibold text-primary" style="font-size:.85rem"><?= e($fw['firmware_version']) ?></div>
        <div class="text-muted" style="font-size:.7rem"><?= e($fw['first_date']) ?></div>
        <div class="badge bg-secondary mt-1"><?= $fw['cnt'] ?> entries</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Add Logbook Entry -->
<?php if ($invItem): ?>
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small">
    <i class="bi bi-journal-plus me-2 text-success"></i>Add Logbook Entry
  </div>
  <div class="card-body">
    <form method="POST" action="<?= url('robots/' . urlencode($serial) . '/logbook') ?>">
      <?= csrfField() ?>
      <div class="row g-2">
        <div class="col-md-2">
          <input type="date" name="log_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-2">
          <input type="time" name="log_time" class="form-control form-control-sm" value="<?= date('H:i') ?>">
        </div>
        <div class="col-md-5">
          <input type="text" name="action" class="form-control form-control-sm" placeholder="Action (e.g. Firmware Update, Maintenance) *" required>
        </div>
        <div class="col-md-3">
          <button class="btn btn-success btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Add Logbook Entry</button>
        </div>
        <div class="col-12">
          <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Description (optional)"></textarea>
        </div>
      </div>
    </form>
  </div>
</div>
<?php else: ?>
<div class="alert alert-secondary small mb-4">
  <i class="bi bi-info-circle me-2"></i>No inventory item linked to serial <strong><?= e($serial) ?></strong> — add it to <a href="<?= url('inventory') ?>" class="alert-link">Inventory</a> first to enable logbook entries here.
</div>
<?php endif; ?>

<!-- Combined Timeline -->
<div class="card">
  <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2 flex-wrap">
    <i class="bi bi-clock-history me-1"></i>Activity Timeline
    <span class="badge bg-secondary" id="timelineCount"><?= count($timeline) ?></span>
    <div class="ms-auto">
      <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-outline-secondary active" onclick="filterTimeline('all')" id="filterAll">
          All <span class="badge bg-secondary ms-1"><?= count($timeline) ?></span>
        </button>
        <button type="button" class="btn btn-outline-primary" onclick="filterTimeline('entry')" id="filterEntry">
          Entries <span class="badge bg-primary ms-1"><?= count($entries) ?></span>
        </button>
        <button type="button" class="btn btn-outline-info" onclick="filterTimeline('test_entry')" id="filterTestEntry">
          Test Entries <span class="badge ms-1" style="background:#0ea5e9"><?= count($testRunEntries) ?></span>
        </button>
        <button type="button" class="btn btn-outline-warning" onclick="filterTimeline('test_session')" id="filterTestSession">
          Sessions <span class="badge ms-1" style="background:#d97706"><?= count($testSessions) ?></span>
        </button>
        <button type="button" class="btn btn-outline-success" onclick="filterTimeline('logbook')" id="filterLogbook">
          Logbook <span class="badge ms-1" style="background:#166534"><?= count($logbook) ?></span>
        </button>
      </div>
    </div>
  </div>
  <div class="card-body p-0" id="timelineBody">
    <?php if (!$timeline): ?>
    <p class="text-muted text-center py-4 small">No activity yet</p>
    <?php endif; ?>
    <?php
    $lastDate = '';
    foreach ($timeline as $item):
      $isLogbook    = $item['_kind'] === 'logbook';
      $isSession    = $item['_kind'] === 'test_session';
      $isTestEntry  = $item['_kind'] === 'test_entry';
      if ($isLogbook)      $itemDate = $item['log_date'] ?? '1970-01-01';
      elseif ($isSession)  $itemDate = substr($item['started_at'] ?? '1970-01-01', 0, 10);
      else                 $itemDate = $item['entry_date'] ?? '1970-01-01';
      $dateStr = formatDate($itemDate);
      if ($dateStr !== $lastDate):
        $lastDate = $dateStr;
    ?>
    <div class="px-3 py-2 border-bottom border-secondary timeline-date-header" style="background:rgba(255,255,255,.03)">
      <small class="text-muted fw-semibold"><?= e($dateStr) ?></small>
    </div>
    <?php endif; ?>

    <?php if ($isLogbook): ?>
    <!-- Logbook row -->
    <div class="d-flex align-items-start gap-3 px-3 py-2 border-bottom border-secondary" data-kind="logbook">
      <div class="flex-shrink-0 mt-1">
        <span class="badge" style="background:#166534;font-size:.65rem"><i class="bi bi-journal-text me-1"></i>Logbook</span>
      </div>
      <div class="flex-grow-1">
        <div class="fw-semibold" style="font-size:.875rem"><?= e($item['action']) ?></div>
        <?php if ($item['description']): ?>
        <div class="text-muted small mt-1"><?= e($item['description']) ?></div>
        <?php endif; ?>
        <div class="text-muted" style="font-size:.75rem">
          <?= substr($item['log_time'], 0, 5) ?> &middot; <?= e($item['user_name'] ?? '—') ?>
        </div>
      </div>
      <div class="flex-shrink-0">
        <form method="POST" action="<?= url('robots/' . urlencode($serial) . '/logbook/' . $item['id'] . '/delete') ?>"
              onsubmit="return confirm('Delete this logbook entry?')">
          <?= csrfField() ?>
          <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash small"></i></button>
        </form>
      </div>
    </div>

    <?php elseif ($isSession): ?>
    <!-- Test Session row -->
    <a href="<?= url('test-sessions/' . $item['id']) ?>" class="d-flex align-items-start gap-3 px-3 py-2 border-bottom border-secondary text-decoration-none text-white" data-kind="test_session">
      <div class="flex-shrink-0 mt-1">
        <span class="badge" style="background:#d97706;font-size:.65rem"><i class="bi bi-camera-video me-1"></i>Session</span>
      </div>
      <div class="flex-grow-1 min-width-0">
        <div class="fw-semibold text-truncate" style="font-size:.875rem"><?= e($item['title']) ?></div>
        <div class="text-muted" style="font-size:.75rem">
          <?php if ($item['firmware_version']): ?><i class="bi bi-code-slash me-1"></i><?= e($item['firmware_version']) ?><?php endif; ?>
          <?php if ($item['area_name']): ?>&middot; <i class="bi bi-map me-1"></i><?= e($item['area_name']) ?><?php endif; ?>
          <?php if ($item['weather_condition']): ?>&middot; <i class="bi bi-cloud me-1"></i><?= e($item['weather_condition']) ?><?php endif; ?>
          <?php if ($item['temperature'] !== null): ?>&middot; <?= $item['temperature'] ?>°C<?php endif; ?>
          <?php if ($item['operator_name']): ?>&middot; <?= e($item['operator_name']) ?><?php endif; ?>
        </div>
        <?php if ($item['entry_count']): ?>
        <div class="text-muted" style="font-size:.7rem"><i class="bi bi-journal-text me-1"></i><?= $item['entry_count'] ?> entries</div>
        <?php endif; ?>
      </div>
      <div class="flex-shrink-0 text-end">
        <?php $ssc = ['active'=>'success','completed'=>'secondary','planned'=>'primary']; ?>
        <span class="badge bg-<?= $ssc[$item['status']] ?? 'secondary' ?>" style="font-size:.6rem"><?= e($item['status']) ?></span>
        <?php if ($item['ended_at']): ?>
        <div class="text-muted mt-1" style="font-size:.7rem"><?= substr($item['ended_at'], 11, 5) ?></div>
        <?php endif; ?>
      </div>
    </a>

    <?php elseif ($isTestEntry): ?>
    <!-- Test Entry row (linked via mower junction) -->
    <a href="<?= url('entries/' . $item['id']) ?>" class="d-flex align-items-start gap-3 px-3 py-2 border-bottom border-secondary text-decoration-none text-white" data-kind="test_entry">
      <div class="flex-shrink-0 mt-1">
        <span class="badge" style="background:#0ea5e9;font-size:.65rem"><i class="bi bi-clipboard-check me-1"></i><?= e($item['type_name'] ?? 'Test') ?></span>
      </div>
      <div class="flex-grow-1 min-width-0">
        <div class="fw-semibold text-truncate" style="font-size:.875rem">
          <?= e($item['title'] ?: substr($item['description'], 0, 80)) ?>
        </div>
        <div class="text-muted" style="font-size:.75rem">
          <?php if ($item['item_title'] ?? null): ?><i class="bi bi-check2-square me-1"></i><?= e($item['item_title']) ?><?php endif; ?>
          <?php if ($item['run_name'] ?? null): ?>&middot; <a href="<?= url('test-runs/' . $item['run_id']) ?>" class="text-info text-decoration-none" onclick="event.stopPropagation()"><?= e($item['run_name']) ?></a><?php endif; ?>
          <?php if ($item['firmware_version']): ?>&middot; <i class="bi bi-code-slash me-1"></i><?= e($item['firmware_version']) ?><?php endif; ?>
        </div>
      </div>
      <div class="flex-shrink-0 text-end">
        <?php if ($item['att_count']): ?>
        <div class="text-muted" style="font-size:.7rem"><i class="bi bi-paperclip"></i> <?= $item['att_count'] ?></div>
        <?php endif; ?>
      </div>
    </a>

    <?php else: ?>
    <!-- Entry row -->
    <a href="<?= url('entries/' . $item['id']) ?>" class="d-flex align-items-start gap-3 px-3 py-2 border-bottom border-secondary text-decoration-none text-white entry-row" data-kind="entry">
      <div class="flex-shrink-0 mt-1">
        <?php if ($item['type_color']): ?>
        <span class="badge" style="background:<?= e($item['type_color']) ?>;font-size:.65rem"><?= e($item['type_name']) ?></span>
        <?php else: ?>
        <span class="badge bg-primary" style="font-size:.65rem">Entry</span>
        <?php endif; ?>
      </div>
      <div class="flex-grow-1 min-width-0">
        <div class="fw-semibold text-truncate" style="font-size:.875rem">
          <?= e($item['title'] ?: substr($item['description'], 0, 80)) ?>
        </div>
        <div class="text-muted" style="font-size:.75rem">
          <?php if ($item['firmware_version']): ?>
          <i class="bi bi-code-slash me-1"></i><?= e($item['firmware_version']) ?>
          <?php endif; ?>
          <?php if ($item['project_name']): ?>
          &middot; <?= e($item['project_name']) ?>
          <?php endif; ?>
          <?php if ($item['jira_issue_key']): ?>
          &middot; <a href="<?= e($item['jira_issue_url']) ?>" target="_blank" class="text-warning text-decoration-none" onclick="event.stopPropagation()"><?= e($item['jira_issue_key']) ?></a>
          <?php if ($item['jira_status']): ?><span class="badge bg-secondary ms-1" style="font-size:.6rem"><?= e($item['jira_status']) ?></span><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="flex-shrink-0 text-end">
        <span class="badge bg-<?= $item['status'] === 'finalized' ? 'success' : ($item['status'] === 'ongoing' ? 'warning text-dark' : 'secondary') ?>" style="font-size:.6rem">
          <?= e($item['status']) ?>
        </span>
        <?php if ($item['att_count']): ?>
        <div class="text-muted mt-1" style="font-size:.7rem"><i class="bi bi-paperclip"></i> <?= $item['att_count'] ?></div>
        <?php endif; ?>
      </div>
    </a>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

<script>
function filterTimeline(kind) {
  // Update button active states
  ['All','Entry','TestEntry','TestSession','Logbook'].forEach(k => {
    const btn = document.getElementById('filter' + k);
    const btnKind = k === 'All' ? 'all' : k.replace(/([A-Z])/g, m => '_' + m.toLowerCase()).replace(/^_/, '');
    if (btn) btn.classList.toggle('active', btnKind === kind || (kind === 'all' && k === 'All'));
  });

  // Show/hide rows.
  // Bootstrap's d-flex uses display:flex !important so we must remove it when hiding
  // and restore it when showing — style.display='none' won't beat !important.
  const rows = document.querySelectorAll('[data-kind]');
  rows.forEach(row => {
    const match = kind === 'all' || row.dataset.kind === kind;
    if (match) {
      row.classList.remove('d-none');
      row.classList.add('d-flex');
    } else {
      row.classList.remove('d-flex');
      row.classList.add('d-none');
    }
  });

  // Hide date headers that have no visible rows beneath them
  const cardBody = document.getElementById('timelineBody');
  if (!cardBody) return;
  const children = Array.from(cardBody.children);
  let header = null, headerHasVisible = false;
  children.forEach(el => {
    if (el.classList.contains('timeline-date-header')) {
      if (header) header.classList.toggle('d-none', !headerHasVisible);
      header = el; headerHasVisible = false;
    } else if (el.dataset.kind) {
      if (!el.classList.contains('d-none')) headerHasVisible = true;
    }
  });
  if (header) header.classList.toggle('d-none', !headerHasVisible);
}
</script>
