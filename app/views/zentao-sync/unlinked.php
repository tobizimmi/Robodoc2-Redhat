<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('entries') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Unlinked Zentao Bugs</h5>
</div>

<?php if ($error): ?>
<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
<?php elseif (!$bugs && !$dismissed): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25"></i>
  All Zentao bugs are linked, dismissed, or none found.
</div></div>
<?php else: ?>

<?php if ($bugs): ?>
<div class="card mb-3">
  <div class="card-header border-secondary d-flex align-items-center justify-content-between">
    <span class="small fw-semibold">
      <?= count($bugs) ?> unlinked bug<?= count($bugs) !== 1 ? 's' : '' ?>
      <span class="text-muted fw-normal ms-1">(linked/dismissed hidden)</span>
    </span>
    <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="zentaoSelectAll()" id="zentaoSelAllBtn">
      <i class="bi bi-check2-square me-1"></i>Select All
    </button>
  </div>
  <div class="list-group list-group-flush">
    <?php foreach ($bugs as $bug): ?>
    <?php $bugId = (int)($bug['id'] ?? 0); ?>
    <div class="list-group-item bg-transparent border-secondary py-2 px-3" id="zrow-<?= $bugId ?>"
         data-bug-id="<?= $bugId ?>" data-title="<?= e(addslashes($bug['title'] ?? '')) ?>">
      <div class="d-flex align-items-start gap-2">
        <input type="checkbox" class="form-check-input zentao-item-cb flex-shrink-0 mt-1"
               value="<?= $bugId ?>" onchange="zentaoUpdateBulkBar()">
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <a href="<?= e(rtrim($zentaoUrl ?? '#', '/')) ?>/bug-view-<?= $bugId ?>.html" target="_blank"
               class="text-info text-decoration-none fw-bold small">
              <i class="bi bi-bug me-1"></i>Bug #<?= $bugId ?>
            </a>
            <span class="badge bg-secondary small"><?= e($bug['status'] ?? '') ?></span>
            <span class="badge bg-dark small">P<?= e($bug['pri'] ?? '?') ?></span>
          </div>
          <div class="small"><?= e($bug['title'] ?? '') ?></div>
        </div>
        <div class="d-flex gap-1 flex-shrink-0">
          <button class="btn btn-outline-info btn-sm py-0 px-2"
                  onclick="showZLinkForm(<?= $bugId ?>, this)" title="Link to existing entry">
            <i class="bi bi-link-45deg"></i>
          </button>
          <button class="btn btn-outline-success btn-sm py-0 px-2"
                  onclick="showZCreateForm(<?= $bugId ?>, '<?= e(addslashes($bug['title'] ?? '')) ?>', this)"
                  title="Create new entry">
            <i class="bi bi-plus-lg"></i>
          </button>
          <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                  onclick="dismissBug(<?= $bugId ?>, this)" title="Dismiss">
            <i class="bi bi-eye-slash"></i>
          </button>
        </div>
      </div>
      <div class="inline-form mt-2 d-none" id="zlink-form-<?= $bugId ?>">
        <div class="input-group input-group-sm">
          <input type="text" class="form-control zentao-entry-search" placeholder="Search existing entries…"
                 data-bug="<?= $bugId ?>" oninput="zentaoEntrySearchDebounce(this)">
          <button class="btn btn-outline-secondary" type="button"
                  onclick="document.getElementById('zlink-form-<?= $bugId ?>').classList.add('d-none')">
            <i class="bi bi-x"></i>
          </button>
        </div>
        <div class="zentao-entry-results mt-1" style="max-height:160px;overflow-y:auto"></div>
      </div>
      <div class="inline-form mt-2 d-none" id="zcreate-form-<?= $bugId ?>">
        <form method="POST" action="<?= url('zentao-unlinked/create-entry') ?>" class="d-flex gap-2 flex-wrap align-items-end">
          <?= csrfField() ?>
          <input type="hidden" name="bug_id" value="<?= $bugId ?>">
          <input type="text" name="title" class="form-control form-control-sm" style="min-width:220px"
                 value="<?= e($bug['title'] ?? '') ?>" placeholder="Entry title">
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
                  onclick="document.getElementById('zcreate-form-<?= $bugId ?>').classList.add('d-none')">Cancel</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php elseif (!$error): ?>
<div class="card mb-3"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25"></i>All bugs linked or dismissed.
</div></div>
<?php endif; ?>

<?php if ($dismissed): ?>
<div class="card">
  <button type="button"
          class="d-flex align-items-center justify-content-between w-100 px-3 py-2 bg-transparent border-0 text-start"
          data-bs-toggle="collapse" data-bs-target="#dismissedZentaoList" aria-expanded="false">
    <span class="small fw-semibold text-muted"><i class="bi bi-eye-slash me-1"></i>Dismissed (<?= count($dismissed) ?>)</span>
    <i class="bi bi-chevron-down text-muted" id="dismissedZentaoChevron"></i>
  </button>
  <div class="collapse" id="dismissedZentaoList">
    <div class="list-group list-group-flush">
      <?php foreach ($dismissed as $d): ?>
      <div class="list-group-item bg-transparent border-secondary py-2 px-3 d-flex align-items-center justify-content-between gap-2" id="zdismissed-row-<?= (int)$d['bug_id'] ?>">
        <span class="text-muted small">
          <i class="bi bi-bug me-1 text-info opacity-50"></i>
          <a href="<?= e(rtrim($zentaoUrl ?? '#', '/')) ?>/bug-view-<?= (int)$d['bug_id'] ?>.html" target="_blank"
             class="text-info text-decoration-none opacity-75">Bug #<?= (int)$d['bug_id'] ?></a>
          <span class="ms-2 opacity-50">dismissed <?= e(formatDate($d['dismissed_at'], 'd.m.Y')) ?></span>
        </span>
        <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                onclick="undismissBug(<?= (int)$d['bug_id'] ?>, this)" title="Restore">
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
<div id="zentaoBulkBar" class="card border-info" style="display:none;position:sticky;bottom:1rem;z-index:100">
  <div class="card-body py-2 px-3 d-flex align-items-center gap-3 flex-wrap">
    <span class="fw-semibold text-info small"><span id="zentaoBulkCount">0</span> selected</span>
    <input type="text" id="zentaoBulkPrefix" class="form-control form-control-sm" style="max-width:200px" placeholder="Title prefix (optional)">
    <select id="zentaoBulkProject" class="form-select form-select-sm" style="max-width:180px">
      <option value="">— Project —</option>
      <?php foreach ($projects as $p): ?>
      <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="zentaoBulkType" class="form-select form-select-sm" style="max-width:160px">
      <option value="">— Type —</option>
      <?php foreach ($entryTypes as $t): ?>
      <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-info btn-sm" onclick="zentaoBulkImport()">
      <i class="bi bi-cloud-download me-1"></i>Import Selected
    </button>
    <button class="btn btn-link btn-sm text-muted ms-auto p-0" onclick="zentaoClearSelection()">✕ Clear</button>
  </div>
</div>

<script>
const _zulCsrf = '<?= e(Auth::csrfToken()) ?>';

// ── Selection ─────────────────────────────────────────────────
let _zentaoAllSel = false;
function zentaoUpdateBulkBar() {
  const checked = document.querySelectorAll('.zentao-item-cb:checked');
  document.getElementById('zentaoBulkCount').textContent = checked.length;
  document.getElementById('zentaoBulkBar').style.display = checked.length ? '' : 'none';
}
function zentaoSelectAll() {
  _zentaoAllSel = !_zentaoAllSel;
  document.querySelectorAll('.zentao-item-cb').forEach(cb => cb.checked = _zentaoAllSel);
  document.getElementById('zentaoSelAllBtn').innerHTML = _zentaoAllSel
    ? '<i class="bi bi-square me-1"></i>Deselect All'
    : '<i class="bi bi-check2-square me-1"></i>Select All';
  zentaoUpdateBulkBar();
}
function zentaoClearSelection() {
  document.querySelectorAll('.zentao-item-cb').forEach(cb => cb.checked = false);
  _zentaoAllSel = false;
  document.getElementById('zentaoSelAllBtn').innerHTML = '<i class="bi bi-check2-square me-1"></i>Select All';
  zentaoUpdateBulkBar();
}

// ── Bulk Import ───────────────────────────────────────────────
function zentaoBulkImport() {
  const projectId = document.getElementById('zentaoBulkProject').value;
  const typeId    = document.getElementById('zentaoBulkType').value;
  if (!projectId || !typeId) { alert('Please select a project and entry type.'); return; }

  const items = {};
  document.querySelectorAll('.zentao-item-cb:checked').forEach(cb => {
    const row = cb.closest('[data-bug-id]');
    if (row) items[row.dataset.bugId] = row.dataset.title || ('Bug #' + row.dataset.bugId);
  });
  if (!Object.keys(items).length) return;

  const prefix = document.getElementById('zentaoBulkPrefix').value.trim();
  const body   = new URLSearchParams({ _csrf: _zulCsrf, project_id: projectId, entry_type_id: typeId, title_prefix: prefix });
  Object.entries(items).forEach(([id, t]) => body.append('items[' + id + ']', t));

  const btn = document.querySelector('#zentaoBulkBar .btn-info');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing…';

  fetch('<?= url('zentao-unlinked/bulk-create') ?>', { method: 'POST', body })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        Object.keys(items).forEach(id => document.getElementById('zrow-' + id)?.remove());
        zentaoClearSelection();
        const msg = d.created + ' entr' + (d.created === 1 ? 'y' : 'ies') + ' created.';
        showZentaoToast(msg + (d.errors?.length ? ' Errors: ' + d.errors.join(', ') : ''), d.errors?.length ? 'warning' : 'success');
      } else {
        alert(d.error || 'Import failed');
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Import Selected';
    });
}

function showZentaoToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = 'alert alert-' + type + ' position-fixed top-0 end-0 m-3 small';
  t.style.cssText = 'z-index:9999;min-width:250px';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

// ── Dismiss / Undismiss ───────────────────────────────────────
function showZLinkForm(id) {
  document.getElementById('zcreate-form-' + id)?.classList.add('d-none');
  document.getElementById('zlink-form-' + id)?.classList.toggle('d-none');
}
function showZCreateForm(id) {
  document.getElementById('zlink-form-' + id)?.classList.add('d-none');
  document.getElementById('zcreate-form-' + id)?.classList.toggle('d-none');
}
function dismissBug(id, btn) {
  btn.disabled = true;
  fetch('<?= url('zentao-unlinked/dismiss') ?>', {
    method: 'POST', body: new URLSearchParams({ _csrf: _zulCsrf, bug_id: id })
  }).then(r => r.json()).then(d => {
    if (d.success) { document.getElementById('zrow-' + id)?.remove(); zentaoUpdateBulkBar(); }
    else btn.disabled = false;
  });
}
function undismissBug(id, btn) {
  btn.disabled = true;
  fetch('<?= url('zentao-unlinked/undismiss') ?>', {
    method: 'POST', body: new URLSearchParams({ _csrf: _zulCsrf, bug_id: id })
  }).then(r => r.json()).then(d => {
    if (d.success) document.getElementById('zdismissed-row-' + id)?.remove();
    else btn.disabled = false;
  });
}

(function() {
  const el = document.getElementById('dismissedZentaoList');
  const ch = document.getElementById('dismissedZentaoChevron');
  if (!el || !ch) return;
  el.addEventListener('show.bs.collapse', () => ch.classList.replace('bi-chevron-down','bi-chevron-up'));
  el.addEventListener('hide.bs.collapse', () => ch.classList.replace('bi-chevron-up','bi-chevron-down'));
})();

// ── Entry search ─────────────────────────────────────────────
let _ztimers = {};
function zentaoEntrySearchDebounce(inp) {
  clearTimeout(_ztimers[inp.dataset.bug]);
  _ztimers[inp.dataset.bug] = setTimeout(() => zentaoEntrySearch(inp), 300);
}
function zentaoEntrySearch(inp) {
  const bugId = inp.dataset.bug, q = inp.value.trim();
  const res = inp.closest('.inline-form').querySelector('.zentao-entry-results');
  if (!q) { res.innerHTML = ''; return; }
  res.innerHTML = '<span class="text-muted small">Searching…</span>';
  fetch('<?= url('api/entries/search-for-zentao') ?>?q=' + encodeURIComponent(q))
    .then(r => r.json()).then(items => {
      if (!items.length) { res.innerHTML = '<span class="text-muted small">No entries found.</span>'; return; }
      res.innerHTML = items.map(e =>
        `<div class="d-flex align-items-center gap-2 py-1 border-bottom border-secondary small">
          <div class="flex-grow-1"><span class="fw-semibold">${e.title}</span> <span class="text-muted ms-1">${e.project_name} · ${e.entry_date}</span></div>
          <button class="btn btn-info btn-sm py-0 px-2" onclick="zentaoLinkEntry(${bugId}, ${e.id}, this)">Link</button>
        </div>`).join('');
    });
}
function zentaoLinkEntry(bugId, entryId, btn) {
  btn.disabled = true;
  fetch('<?= url('zentao-unlinked/link') ?>', {
    method: 'POST', body: new URLSearchParams({ _csrf: _zulCsrf, bug_id: bugId, entry_id: entryId })
  }).then(r => r.json()).then(d => {
    if (d.success) {
      const row = document.getElementById('zrow-' + bugId);
      if (row) row.innerHTML = '<div class="py-2 px-3 text-success small"><i class="bi bi-check-circle me-1"></i>Linked to entry #' + entryId + '</div>';
      zentaoUpdateBulkBar();
    } else { alert(d.error || 'Link failed'); btn.disabled = false; }
  });
}
</script>
