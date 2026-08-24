<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('entries') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1">Unlinked Jira Issues</h5>
  <form method="GET" action="<?= url('jira-unlinked') ?>" class="d-flex gap-2">
    <input type="text" name="jql" class="form-control form-control-sm" style="min-width:360px"
           value="<?= e($jql) ?>" placeholder="JQL e.g. project=RD AND issuetype=Bug ORDER BY created DESC">
    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
  </form>
</div>

<?php if ($error): ?>
<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
<?php elseif (!$issues && !$dismissed): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25"></i>
  All Jira issues are linked, dismissed, or none found for this query.
</div></div>
<?php else: ?>

<?php if ($issues): ?>
<div class="card mb-3">
  <div class="card-header border-secondary d-flex align-items-center justify-content-between">
    <span class="small fw-semibold">
      <?= count($issues) ?> unlinked issue<?= count($issues) !== 1 ? 's' : '' ?>
      <span class="text-muted fw-normal ms-1">(linked/dismissed hidden)</span>
    </span>
    <div class="d-flex gap-2 align-items-center">
      <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="jiraSelectAll()" id="jiraSelAllBtn">
        <i class="bi bi-check2-square me-1"></i>Select All
      </button>
    </div>
  </div>
  <div class="list-group list-group-flush" id="issueList">
    <?php foreach ($issues as $issue): ?>
    <div class="list-group-item bg-transparent border-secondary py-2 px-3" id="row-<?= e($issue['key']) ?>"
         data-key="<?= e($issue['key']) ?>" data-summary="<?= e(addslashes($issue['summary'])) ?>">
      <div class="d-flex align-items-start gap-2">
        <input type="checkbox" class="form-check-input jira-item-cb flex-shrink-0 mt-1"
               value="<?= e($issue['key']) ?>" onchange="jiraUpdateBulkBar()">
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <a href="<?= e($jiraUrl ?? '#') ?>/browse/<?= e($issue['key']) ?>" target="_blank"
               class="text-warning text-decoration-none fw-bold small">
              <i class="bi bi-bug-fill me-1"></i><?= e($issue['key']) ?>
            </a>
            <span class="badge bg-secondary small"><?= e($issue['status']) ?></span>
            <?php if ($issue['project_name'] ?? null): ?>
            <span class="text-muted small"><?= e($issue['project_name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="small"><?= e($issue['summary']) ?></div>
        </div>
        <div class="d-flex gap-1 flex-shrink-0">
          <button class="btn btn-outline-info btn-sm py-0 px-2"
                  onclick="showLinkForm('<?= e(addslashes($issue['key'])) ?>', this)" title="Link to existing entry">
            <i class="bi bi-link-45deg"></i>
          </button>
          <button class="btn btn-outline-success btn-sm py-0 px-2"
                  onclick="showCreateForm('<?= e(addslashes($issue['key'])) ?>', '<?= e(addslashes($issue['summary'])) ?>', this)"
                  title="Create new entry">
            <i class="bi bi-plus-lg"></i>
          </button>
          <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                  onclick="dismissIssue('<?= e(addslashes($issue['key'])) ?>', this)" title="Dismiss">
            <i class="bi bi-eye-slash"></i>
          </button>
        </div>
      </div>
      <div class="inline-form mt-2 d-none" id="link-form-<?= e($issue['key']) ?>">
        <div class="input-group input-group-sm">
          <input type="text" class="form-control entry-search-input" placeholder="Search existing entries…"
                 data-issue="<?= e($issue['key']) ?>" oninput="entrySearchDebounce(this)">
          <button class="btn btn-outline-secondary" type="button"
                  onclick="document.getElementById('link-form-<?= e($issue['key']) ?>').classList.add('d-none')">
            <i class="bi bi-x"></i>
          </button>
        </div>
        <div class="entry-results mt-1" style="max-height:160px;overflow-y:auto"></div>
      </div>
      <div class="inline-form mt-2 d-none" id="create-form-<?= e($issue['key']) ?>">
        <form method="POST" action="<?= url('jira-unlinked/create-entry') ?>" class="d-flex gap-2 flex-wrap align-items-end">
          <?= csrfField() ?>
          <input type="hidden" name="issue_key" value="<?= e($issue['key']) ?>">
          <input type="text" name="title" class="form-control form-control-sm" style="min-width:220px"
                 value="<?= e($issue['summary']) ?>" placeholder="Entry title">
          <select name="project_id" class="form-select form-select-sm" style="max-width:160px" required>
            <option value="">— Project —</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="entry_type_id" class="form-select form-select-sm" style="max-width:140px" required>
            <option value="">— Type —</option>
            <?php foreach ($entryTypes as $t): ?>
            <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i>Create</button>
          <button type="button" class="btn btn-outline-secondary btn-sm"
                  onclick="document.getElementById('create-form-<?= e($issue['key']) ?>').classList.add('d-none')">Cancel</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php elseif (!$error): ?>
<div class="card mb-3"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25"></i>All issues linked or dismissed.
</div></div>
<?php endif; ?>

<?php if ($dismissed): ?>
<div class="card">
  <button type="button"
          class="d-flex align-items-center justify-content-between w-100 px-3 py-2 bg-transparent border-0 text-start"
          data-bs-toggle="collapse" data-bs-target="#dismissedJiraList" aria-expanded="false">
    <span class="small fw-semibold text-muted"><i class="bi bi-eye-slash me-1"></i>Dismissed (<?= count($dismissed) ?>)</span>
    <i class="bi bi-chevron-down text-muted" id="dismissedJiraChevron"></i>
  </button>
  <div class="collapse" id="dismissedJiraList">
    <div class="list-group list-group-flush">
      <?php foreach ($dismissed as $d): ?>
      <div class="list-group-item bg-transparent border-secondary py-2 px-3 d-flex align-items-center justify-content-between gap-2" id="dismissed-row-<?= e($d['issue_key']) ?>">
        <span class="text-muted small">
          <i class="bi bi-bug-fill me-1 text-warning opacity-50"></i>
          <a href="<?= e($jiraUrl ?? '#') ?>/browse/<?= e($d['issue_key']) ?>" target="_blank"
             class="text-warning text-decoration-none opacity-75"><?= e($d['issue_key']) ?></a>
          <span class="ms-2 opacity-50">dismissed <?= e(formatDate($d['dismissed_at'], 'd.m.Y')) ?></span>
        </span>
        <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                onclick="undismissIssue('<?= e(addslashes($d['issue_key'])) ?>', this)" title="Restore">
          <i class="bi bi-eye me-1"></i>Restore
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Bulk Import Bar -->
<div id="jiraBulkBar" class="card border-primary" style="display:none;position:sticky;bottom:1rem;z-index:100">
  <div class="card-body py-2 px-3 d-flex align-items-center gap-3 flex-wrap">
    <span class="fw-semibold text-primary small"><span id="jiraBulkCount">0</span> selected</span>
    <input type="text" id="jiraBulkPrefix" class="form-control form-control-sm" style="max-width:200px" placeholder="Title prefix (optional)">
    <select id="jiraBulkProject" class="form-select form-select-sm" style="max-width:180px">
      <option value="">— Project —</option>
      <?php foreach ($projects as $p): ?>
      <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="jiraBulkType" class="form-select form-select-sm" style="max-width:160px">
      <option value="">— Type —</option>
      <?php foreach ($entryTypes as $t): ?>
      <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm" onclick="jiraBulkImport()">
      <i class="bi bi-cloud-download me-1"></i>Import Selected
    </button>
    <button class="btn btn-link btn-sm text-muted ms-auto p-0" onclick="jiraClearSelection()">✕ Clear</button>
  </div>
</div>

<script>
const _ulCsrf = '<?= e(Auth::csrfToken()) ?>';

// ── Selection ─────────────────────────────────────────────────
let _jiraAllSel = false;
function jiraUpdateBulkBar() {
  const checked = document.querySelectorAll('.jira-item-cb:checked');
  const bar = document.getElementById('jiraBulkBar');
  document.getElementById('jiraBulkCount').textContent = checked.length;
  bar.style.display = checked.length ? '' : 'none';
}
function jiraSelectAll() {
  _jiraAllSel = !_jiraAllSel;
  document.querySelectorAll('.jira-item-cb').forEach(cb => cb.checked = _jiraAllSel);
  document.getElementById('jiraSelAllBtn').innerHTML = _jiraAllSel
    ? '<i class="bi bi-square me-1"></i>Deselect All'
    : '<i class="bi bi-check2-square me-1"></i>Select All';
  jiraUpdateBulkBar();
}
function jiraClearSelection() {
  document.querySelectorAll('.jira-item-cb').forEach(cb => cb.checked = false);
  _jiraAllSel = false;
  document.getElementById('jiraSelAllBtn').innerHTML = '<i class="bi bi-check2-square me-1"></i>Select All';
  jiraUpdateBulkBar();
}

// ── Bulk Import ───────────────────────────────────────────────
function jiraBulkImport() {
  const projectId = document.getElementById('jiraBulkProject').value;
  const typeId    = document.getElementById('jiraBulkType').value;
  if (!projectId || !typeId) { alert('Please select a project and entry type.'); return; }

  const items = {};
  document.querySelectorAll('.jira-item-cb:checked').forEach(cb => {
    const row = cb.closest('[data-key]');
    if (row) items[row.dataset.key] = row.dataset.summary || row.dataset.key;
  });
  if (!Object.keys(items).length) return;

  const prefix = document.getElementById('jiraBulkPrefix').value.trim();
  const body   = new URLSearchParams({ _csrf: _ulCsrf, project_id: projectId, entry_type_id: typeId, title_prefix: prefix });
  Object.entries(items).forEach(([k, v]) => body.append('items[' + k + ']', v));

  const btn = document.querySelector('#jiraBulkBar .btn-primary');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing…';

  fetch('<?= url('jira-unlinked/bulk-create') ?>', { method: 'POST', body })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        // Remove imported rows
        Object.keys(items).forEach(k => document.getElementById('row-' + k)?.remove());
        jiraClearSelection();
        const msg = d.created + ' entr' + (d.created === 1 ? 'y' : 'ies') + ' created.';
        showJiraToast(msg + (d.errors?.length ? ' Errors: ' + d.errors.join(', ') : ''), d.errors?.length ? 'warning' : 'success');
      } else {
        alert(d.error || 'Import failed');
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Import Selected';
    });
}

function showJiraToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = 'alert alert-' + type + ' position-fixed top-0 end-0 m-3 small';
  t.style.cssText = 'z-index:9999;min-width:250px';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

// ── Dismiss / Undismiss ───────────────────────────────────────
function showLinkForm(key, btn) {
  document.getElementById('create-form-' + key)?.classList.add('d-none');
  document.getElementById('link-form-' + key)?.classList.toggle('d-none');
}
function showCreateForm(key, title, btn) {
  document.getElementById('link-form-' + key)?.classList.add('d-none');
  document.getElementById('create-form-' + key)?.classList.toggle('d-none');
}
function dismissIssue(key, btn) {
  btn.disabled = true;
  fetch('<?= url('jira-unlinked/dismiss') ?>', {
    method: 'POST', body: new URLSearchParams({ _csrf: _ulCsrf, issue_key: key })
  }).then(r => r.json()).then(d => {
    if (d.success) { document.getElementById('row-' + key)?.remove(); jiraUpdateBulkBar(); }
    else btn.disabled = false;
  });
}
function undismissIssue(key, btn) {
  btn.disabled = true;
  fetch('<?= url('jira-unlinked/undismiss') ?>', {
    method: 'POST', body: new URLSearchParams({ _csrf: _ulCsrf, issue_key: key })
  }).then(r => r.json()).then(d => {
    if (d.success) document.getElementById('dismissed-row-' + key)?.remove();
    else btn.disabled = false;
  });
}

(function() {
  const el = document.getElementById('dismissedJiraList');
  const ch = document.getElementById('dismissedJiraChevron');
  if (!el || !ch) return;
  el.addEventListener('show.bs.collapse', () => ch.classList.replace('bi-chevron-down','bi-chevron-up'));
  el.addEventListener('hide.bs.collapse', () => ch.classList.replace('bi-chevron-up','bi-chevron-down'));
})();

// ── Entry search ─────────────────────────────────────────────
let _entrySearchTimers = {};
function entrySearchDebounce(inp) {
  clearTimeout(_entrySearchTimers[inp.dataset.issue]);
  _entrySearchTimers[inp.dataset.issue] = setTimeout(() => entrySearch(inp), 300);
}
function entrySearch(inp) {
  const key = inp.dataset.issue, q = inp.value.trim();
  const res = inp.closest('.inline-form').querySelector('.entry-results');
  if (!q) { res.innerHTML = ''; return; }
  res.innerHTML = '<span class="text-muted small">Searching…</span>';
  fetch('<?= url('api/entries/search') ?>?q=' + encodeURIComponent(q))
    .then(r => r.json()).then(items => {
      if (!items.length) { res.innerHTML = '<span class="text-muted small">No entries found.</span>'; return; }
      res.innerHTML = items.map(e =>
        `<div class="d-flex align-items-center gap-2 py-1 border-bottom border-secondary small">
          <div class="flex-grow-1"><span class="fw-semibold">${e.title}</span> <span class="text-muted ms-1">${e.project_name} · ${e.entry_date}</span></div>
          <button class="btn btn-info btn-sm py-0 px-2" onclick="linkEntry('${key}', ${e.id}, this)">Link</button>
        </div>`).join('');
    });
}
function linkEntry(issueKey, entryId, btn) {
  btn.disabled = true;
  fetch('<?= url('jira-unlinked/link') ?>', {
    method: 'POST', body: new URLSearchParams({ _csrf: _ulCsrf, issue_key: issueKey, entry_id: entryId })
  }).then(r => r.json()).then(d => {
    if (d.success) {
      const row = document.getElementById('row-' + issueKey);
      if (row) row.innerHTML = '<div class="py-2 px-3 text-success small"><i class="bi bi-check-circle me-1"></i>Linked to entry #' + entryId + '</div>';
      jiraUpdateBulkBar();
    } else { alert(d.error || 'Link failed'); btn.disabled = false; }
  });
}
</script>
