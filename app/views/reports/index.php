<div class="d-flex align-items-center gap-3 mb-4">
  <div>
    <h1 class="h3 mb-0">Reports</h1>
    <p class="text-muted mb-0 small">Filter and analyse entries</p>
  </div>
  </div>
  <a href="<?= url('reports/builder') ?>" class="btn btn-primary">
    <i class="bi bi-layout-text-window me-2"></i>Report Builder
  </a>

<!-- Filter panel -->
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small">Filters</div>
  <div class="card-body">
    <form method="POST" action="<?= url('reports') ?>">
      <?= csrfField() ?>
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label small">Project</label>
          <select name="project_id" class="form-select form-select-sm">
            <option value="">All Projects</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($report['filters']['projectId'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
              <?= e($p['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Date From</label>
          <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($report['filters']['dateFrom'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small">Date To</label>
          <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($report['filters']['dateTo'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Test Plan (optional)</label>
          <select name="test_plan_id" class="form-select form-select-sm">
            <option value="">— No Test Plan —</option>
            <?php foreach ($testPlans as $tp): ?>
            <option value="<?= $tp['id'] ?>" <?= ($report['filters']['testPlanId'] ?? 0) == $tp['id'] ? 'selected' : '' ?>>
              <?= e($tp['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="only_report_relevant" value="1"
           id="onlyReportRelevant" checked>
    <label class="form-check-label" for="onlyReportRelevant">
      <i class="bi bi-bar-chart-fill text-success me-1"></i>
      Only show entries marked <strong>Relevant for Reporting</strong>
    </label>
  </div>
  <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-bar-chart-line me-1"></i>Generate
          </button>
        </div>
      </div>
      <!-- Entry type chips -->
      <?php if ($entryTypes): ?>
      <div class="d-flex flex-wrap gap-2">
        <span class="text-muted small me-1 align-self-center">Types:</span>
        <?php foreach ($entryTypes as $et): ?>
        <label class="chip" style="cursor:pointer">
          <input type="checkbox" name="type_ids[]" value="<?= $et['id'] ?>" class="d-none"
                 <?= in_array($et['id'], $report['filters']['typeIds'] ?? []) ? 'checked' : '' ?>>
          <span class="rounded-circle d-inline-block me-1" style="width:8px;height:8px;background:<?= e($et['color']) ?>"></span>
          <?= e($et['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($report): ?>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100" style="border-left-color:#6366f1">
      <div class="card-body p-3">
        <div class="text-muted small">Total Entries</div>
        <div class="stat-number"><?= $report['total'] ?></div>
      </div>
    </div>
  </div>
  <?php if ($report['planData']): ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100" style="border-left-color:#10b981">
      <div class="card-body p-3">
        <div class="text-muted small">Passed</div>
        <div class="stat-number text-success"><?= $report['planData']['counts']['passed'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100" style="border-left-color:#ef4444">
      <div class="card-body p-3">
        <div class="text-muted small">Failed</div>
        <div class="stat-number text-danger"><?= $report['planData']['counts']['failed'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100" style="border-left-color:#94a3b8">
      <div class="card-body p-3">
        <div class="text-muted small">Pending</div>
        <div class="stat-number text-muted"><?= $report['planData']['counts']['pending'] ?></div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <?php foreach (array_slice($report['byType'], 0, 3) as $bt): ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100" style="border-left-color:<?= e($bt['color']) ?>">
      <div class="card-body p-3">
        <div class="text-muted small"><?= e($bt['name']) ?></div>
        <div class="stat-number"><?= $bt['cnt'] ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Test plan progress -->
<?php if ($report['planData']): ?>
<?php $pd = $report['planData']; $total = array_sum($pd['counts']); ?>
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small">
    Test Plan Progress — <?= e($pd['plan']['name']) ?>
  </div>
  <div class="card-body">
    <div class="d-flex gap-3 flex-wrap mb-3 small fw-semibold">
      <span class="text-success">✓ <?= $pd['counts']['passed'] ?> passed</span>
      <span class="text-danger">✗ <?= $pd['counts']['failed'] ?> failed</span>
      <span class="text-muted">— <?= $pd['counts']['skipped'] ?> skipped</span>
      <span class="text-secondary">○ <?= $pd['counts']['pending'] ?> pending</span>
    </div>
    <?php if ($total > 0): ?>
    <div style="height:28px;background:rgba(255,255,255,.05);border-radius:4px;overflow:hidden;display:flex">
      <?php foreach (['passed'=>'#10b981','failed'=>'#ef4444','skipped'=>'#94a3b8','pending'=>'rgba(148,163,184,.3)'] as $st=>$col): ?>
      <?php $pct = round($pd['counts'][$st] / $total * 100); ?>
      <?php if ($pct): ?>
      <div style="width:<?= $pct ?>%;background:<?= $col ?>;transition:width .3s" title="<?= ucfirst($st) ?>: <?= $pd['counts'][$st] ?>"></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Failed test cases -->
<?php $failed = array_filter($pd['items'], fn($i) => $i['status'] === 'failed'); ?>
<?php if ($failed): ?>
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small">Failed Test Cases (<?= count($failed) ?>)</div>
  <div class="card-body">
    <?php foreach ($failed as $item): ?>
    <div class="mb-3 p-3 border border-secondary rounded" style="border-left:3px solid #ef4444 !important">
      <div class="d-flex align-items-center gap-2 mb-1">
        <?php $pc = ['low'=>'secondary','medium'=>'primary','high'=>'warning','critical'=>'danger']; ?>
        <span class="badge bg-<?= $pc[$item['priority']] ?? 'secondary' ?>"><?= ucfirst($item['priority'] ?? 'medium') ?></span>
        <span class="fw-semibold small"><?= e($item['title']) ?></span>
      </div>
      <?php if ($item['expected_result']): ?>
      <div class="text-muted small"><strong>Expected:</strong> <?= e($item['expected_result']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Entry table -->
<div class="card mb-4">
  <div class="card-header border-secondary d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Entries (<?= $report['total'] ?>)</span>
    <div class="d-flex gap-2">
      <a href="<?= url('export/entries?' . http_build_query([
          'project_id' => $report['filters']['projectId'] ?: '',
          'date_from'  => $report['filters']['dateFrom'],
          'date_to'    => $report['filters']['dateTo'],
      ])) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i>CSV
      </a>
      <?php if (!empty($settings['confluence_url'])): ?>
      <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#confExportModal">
        <i class="bi bi-cloud-upload me-1"></i>Confluence
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$report['entries']): ?>
  <div class="card-body text-muted text-center p-4">No entries match these filters.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-dark table-hover table-sm mb-0 align-middle" id="reportTable">
      <thead>
        <tr>
          <th style="cursor:pointer" onclick="sortTable(0)">Date ↕</th>
          <th>Type</th>
          <th>Project</th>
          <th>Title / Description</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report['entries'] as $e): ?>
        <tr onclick="location.href='<?= url('entries/' . $e['id']) ?>'" style="cursor:pointer">
          <td class="text-muted small text-nowrap"><?= formatDate($e['entry_date']) ?></td>
          <td><span class="badge" style="background:<?= e($e['type_color']) ?>"><?= e($e['type_name']) ?></span></td>
          <td class="small">
            <span class="color-dot" style="background:<?= e($e['project_color']) ?>"></span>
            <?= e($e['project_name']) ?>
          </td>
          <td class="small"><?= e($e['title'] ?: substr($e['description'], 0, 80)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($settings['confluence_url'])): ?>
<!-- Confluence Export Modal -->
<div class="modal fade" id="confExportModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-cloud-upload me-2"></i>Export Report to Confluence</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <div id="confExportResult"></div>
        <div class="mb-3">
          <label class="form-label small">Space Key <span class="text-danger">*</span></label>
          <input type="text" id="confSpace" class="form-control" value="<?= e($settings['confluence_default_space'] ?? '') ?>" placeholder="e.g. RD">
        </div>
        <div class="mb-3">
          <label class="form-label small">Page Title</label>
          <input type="text" id="confTitle" class="form-control" value="RoboDoc Report <?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-info" onclick="exportReportToConf()">
          <i class="bi bi-cloud-upload me-1"></i>Publish
        </button>
      </div>
    </div>
  </div>
</div>
<script>
function exportReportToConf() {
  const space = document.getElementById('confSpace').value.trim();
  const title = document.getElementById('confTitle').value.trim();
  const resultEl = document.getElementById('confExportResult');
  if (!space) { resultEl.innerHTML = '<div class="alert alert-danger py-2 small">Space key required.</div>'; return; }
  const btn = document.querySelector('#confExportModal .btn-info');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Publishing…';
  resultEl.innerHTML = '';
  // Collect current entry IDs from table
  const rows = document.querySelectorAll('#reportTable tbody tr');
  const ids = Array.from(rows).map(r => r.onclick?.toString().match(/\/(\d+)/)?.[1]).filter(Boolean);
  const fd = new FormData();
  fd.append('_csrf', document.querySelector('input[name=_csrf]')?.value || '');
  fd.append('space_key', space);
  fd.append('page_title', title);
  fd.append('mode', 'report');
  ids.forEach(id => fd.append('entry_ids[]', id));
  fetch('<?= url('confluence') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-upload me-1"></i>Publish';
      if (data.error) {
        resultEl.innerHTML = `<div class="alert alert-danger py-2 small">${data.error}</div>`;
      } else {
        resultEl.innerHTML = `<div class="alert alert-success py-2 small">Published: <a href="${data.url}" target="_blank" class="alert-link">${data.title}</a></div>`;
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-upload me-1"></i>Publish';
      resultEl.innerHTML = '<div class="alert alert-danger py-2 small">Request failed.</div>';
    });
}
</script>
<?php endif; ?>

<script>
function sortTable(col) {
  const table = document.getElementById('reportTable');
  const tbody = table.tBodies[0];
  const rows  = Array.from(tbody.rows);
  const asc   = table.dataset.sortCol == col && table.dataset.sortDir === 'asc';
  rows.sort((a, b) => {
    const va = a.cells[col].textContent.trim();
    const vb = b.cells[col].textContent.trim();
    return asc ? vb.localeCompare(va) : va.localeCompare(vb);
  });
  rows.forEach(r => tbody.appendChild(r));
  table.dataset.sortCol = col;
  table.dataset.sortDir = asc ? 'desc' : 'asc';
}
</script>

<?php endif; ?>

<!-- Firmware Comparison Report -->
<div class="card mt-4">
  <div class="card-header border-secondary fw-semibold small d-flex align-items-center justify-content-between">
    <span><i class="bi bi-code-slash me-2 text-primary"></i>Firmware Comparison</span>
    <button class="btn btn-outline-secondary btn-sm" onclick="loadFirmwareReport()" id="fwLoadBtn">
      <i class="bi bi-arrow-clockwise me-1"></i>Load
    </button>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <select id="fwProjectFilter" class="form-select form-select-sm">
          <option value="">All Projects</option>
          <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div id="fwReportContainer">
      <p class="text-muted small text-center py-3">Click "Load" to generate the firmware comparison.</p>
    </div>
  </div>
</div>

<script>
function loadFirmwareReport() {
  const btn = document.getElementById('fwLoadBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading…';
  const projectId = document.getElementById('fwProjectFilter').value;
  const url = '<?= url('api/reports/firmware') ?>' + (projectId ? '?project_id=' + projectId : '');
  fetch(url)
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Reload';
      renderFirmwareReport(data);
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Load';
      document.getElementById('fwReportContainer').innerHTML = '<p class="text-danger small">Failed to load report.</p>';
    });
}

function renderFirmwareReport(data) {
  const rows = data.rows || [];
  if (!rows.length) {
    document.getElementById('fwReportContainer').innerHTML = '<p class="text-muted small">No firmware data found.</p>';
    return;
  }

  let html = '<div class="table-responsive"><table class="table table-dark table-sm">';
  html += '<thead><tr><th>Firmware</th><th>Total Entries</th><th>Bugs</th><th>Resolved</th><th>First Used</th><th>Last Used</th></tr></thead><tbody>';
  rows.forEach(r => {
    const resolvedPct = r.total > 0 ? Math.round(r.resolved / r.total * 100) : 0;
    html += `<tr>
      <td><code>${r.firmware_version}</code></td>
      <td>${r.total}</td>
      <td><span class="badge bg-danger">${r.bugs}</span></td>
      <td>
        <span class="badge bg-success">${r.resolved}</span>
        <small class="text-muted ms-1">${resolvedPct}%</small>
      </td>
      <td class="text-muted small">${r.first_date}</td>
      <td class="text-muted small">${r.last_date}</td>
    </tr>`;
  });
  html += '</tbody></table></div>';

  // Type breakdown per firmware
  const byType = data.byType || {};
  const versions = Object.keys(byType);
  if (versions.length > 0) {
    const allTypes = [...new Set(versions.flatMap(v => Object.keys(byType[v])))];
    html += '<h6 class="mt-3 mb-2 text-muted small">Entry Types per Firmware</h6>';
    html += '<div class="table-responsive"><table class="table table-dark table-sm">';
    html += '<thead><tr><th>Type</th>' + versions.map(v => `<th><code style="font-size:.7rem">${v}</code></th>`).join('') + '</tr></thead><tbody>';
    allTypes.forEach(t => {
      html += `<tr><td class="small">${t}</td>` + versions.map(v => `<td class="small">${byType[v][t] || 0}</td>`).join('') + '</tr>';
    });
    html += '</tbody></table></div>';
  }

  document.getElementById('fwReportContainer').innerHTML = html;
}
</script>
