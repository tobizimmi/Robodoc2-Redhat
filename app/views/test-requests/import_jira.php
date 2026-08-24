<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-requests') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1">Import Test Requests from Jira</h5>
</div>

<?php if (!$projectKey): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>Jira Test Request project not configured.</strong>
  Go to <a href="<?= url('admin/settings') ?>" class="alert-link">Admin → Settings</a> and set the <em>Jira Test Request Project</em> key first.
</div>
<?php else: ?>

<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small">
    <i class="bi bi-search me-2 text-primary"></i>Search Jira Issues
    <span class="badge bg-secondary ms-2">project: <?= e($projectKey) ?>, type: Request</span>
  </div>
  <div class="card-body">
    <div class="input-group">
      <input type="text" id="jiraSearchInput" class="form-control"
             placeholder="Search by summary keyword… (leave empty to load all recent)">
      <button class="btn btn-primary" onclick="doSearch()">
        <i class="bi bi-search me-1"></i>Search
      </button>
    </div>
    <div class="form-text">Returns up to 30 results. Use keywords to narrow down.</div>
  </div>
</div>

<form method="POST" action="<?= url('test-requests/import-jira') ?>" id="importForm">
  <?= csrfField() ?>
  <input type="hidden" name="jql" id="lastJql" value="">

  <div id="resultsSection" style="display:none">
    <div class="d-flex align-items-center gap-3 mb-2">
      <span id="resultCount" class="text-muted small"></span>
      <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll(true)">Select all</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll(false)">Deselect all</button>
      </div>
    </div>

    <div class="table-responsive">
    <table class="table table-hover align-middle small">
      <thead class="table-dark">
        <tr>
          <th style="width:1%"><input type="checkbox" id="selectAllCb" onchange="selectAll(this.checked)" class="form-check-input m-0"></th>
          <th>Key</th>
          <th>Summary</th>
          <th>Product</th>
          <th>Project</th>
          <th>Dev Type</th>
          <th>Labels</th>
          <th>Jira Status</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="resultsBody"></tbody>
    </table>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button type="submit" class="btn btn-primary" id="importBtn" disabled>
        <i class="bi bi-download me-1"></i>Import Selected
      </button>
      <span class="text-muted small align-self-center" id="selectedCount"></span>
    </div>
  </div>

  <div id="loadingSpinner" style="display:none" class="text-center py-4">
    <div class="spinner-border text-primary"></div>
    <div class="text-muted small mt-2">Fetching from Jira…</div>
  </div>

  <div id="errorBox" style="display:none" class="alert alert-danger"></div>
</form>

<script>
const existingKeys = <?= json_encode(
    array_column(Database::fetchAll('SELECT jira_issue_key FROM test_requests WHERE jira_issue_key IS NOT NULL'), 'jira_issue_key')
) ?>;

function doSearch() {
  const q = document.getElementById('jiraSearchInput').value.trim();
  document.getElementById('resultsSection').style.display = 'none';
  document.getElementById('errorBox').style.display = 'none';
  document.getElementById('loadingSpinner').style.display = 'block';

  const url = '<?= url('api/test-requests/jira-search') ?>?q=' + encodeURIComponent(q);
  fetch(url)
    .then(r => r.json())
    .then(data => {
      document.getElementById('loadingSpinner').style.display = 'none';
      if (data.error) {
        showError(data.error);
        return;
      }
      const jqlInput = document.getElementById('jiraSearchInput').value.trim();
      const projectKey = '<?= e($projectKey) ?>';
      let jql = 'project = ' + projectKey + ' AND issuetype = Request';
      if (jqlInput) jql += ' AND summary ~ ' + JSON.stringify(jqlInput + '*');
      jql += ' ORDER BY created DESC';
      document.getElementById('lastJql').value = jql;
      renderResults(data.issues || []);
    })
    .catch(err => {
      document.getElementById('loadingSpinner').style.display = 'none';
      showError('Network error: ' + err.message);
    });
}

function renderResults(issues) {
  const tbody = document.getElementById('resultsBody');
  tbody.innerHTML = '';

  document.getElementById('resultCount').textContent = issues.length + ' issue(s) found';
  document.getElementById('resultsSection').style.display = '';

  issues.forEach(issue => {
    const alreadyImported = existingKeys.includes(issue.key);
    const tr = document.createElement('tr');
    if (alreadyImported) tr.classList.add('opacity-50');

    const labels = issue.labels ? issue.labels.split(',').filter(s => s.trim()).map(l =>
      '<span class="badge bg-secondary me-1">' + esc(l.trim()) + '</span>'
    ).join('') : '';

    tr.innerHTML = `
      <td><input type="checkbox" name="keys[]" value="${esc(issue.key)}" class="form-check-input m-0 row-cb"
                 ${alreadyImported ? 'disabled' : ''} onchange="updateCount()"></td>
      <td><span class="badge bg-primary">${esc(issue.key)}</span></td>
      <td>
        <div class="fw-semibold">${esc(issue.summary)}</div>
        ${issue.description ? '<div class="text-muted small mt-1" style="max-height:48px;overflow:hidden">' + esc(issue.description.substring(0, 150)) + (issue.description.length > 150 ? '…' : '') + '</div>' : ''}
      </td>
      <td>${esc(issue.product)}</td>
      <td>${esc((issue.project_name || '') + (issue.project_number ? ' (' + issue.project_number + ')' : ''))}</td>
      <td>${esc(issue.development_type)}</td>
      <td>${labels || '<span class="text-muted">—</span>'}</td>
      <td><span class="badge bg-secondary">${esc(issue.status)}</span></td>
      <td class="text-muted">${esc(issue.created)}</td>
      <td>${alreadyImported ? '<span class="badge bg-success text-nowrap"><i class="bi bi-check me-1"></i>Imported</span>' : ''}</td>
    `;
    tbody.appendChild(tr);
  });

  updateCount();
}

function selectAll(checked) {
  document.querySelectorAll('.row-cb:not(:disabled)').forEach(cb => cb.checked = checked);
  document.getElementById('selectAllCb').checked = checked;
  updateCount();
}

function updateCount() {
  const n = document.querySelectorAll('.row-cb:checked').length;
  document.getElementById('selectedCount').textContent = n + ' selected';
  document.getElementById('importBtn').disabled = n === 0;
}

function showError(msg) {
  const box = document.getElementById('errorBox');
  box.textContent = msg;
  box.style.display = '';
}

function esc(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Auto-search on Enter
document.getElementById('jiraSearchInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
});

// Load on page open
doSearch();
</script>

<?php endif; ?>
