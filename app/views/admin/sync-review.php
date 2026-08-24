<?php
$csrf    = Auth::csrfToken();
$total   = count($jiraEntries) + count($zentaoEntries);
$priStyle= fn($p)=>(['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'][$p]??'secondary');
$priCss  = fn($p)=>($priStyle($p)==='orange'?'background:#f97316':'background:var(--bs-'.$priStyle($p).')');
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2 text-warning"></i>Sync Review</h4>
    <small class="text-muted"><?= $total ?> entr<?= $total===1?'y':'ies'?> with detected changes — review and decide for each</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('admin') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Admin</a>
    <?php if ($jiraEntries): ?>
    <div class="dropdown">
      <button class="btn btn-outline-warning btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-bug-fill me-1"></i>All Jira (<?= count($jiraEntries) ?>)</button>
      <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
        <li><button class="dropdown-item small" onclick="bulkAction('jira','accept')"><i class="bi bi-cloud-download me-2 text-success"></i>Accept all from Jira</button></li>
        <li><button class="dropdown-item small" onclick="bulkAction('jira','push')"><i class="bi bi-cloud-upload me-2 text-warning"></i>Push all to Jira</button></li>
        <li><hr class="dropdown-divider"></li>
        <li><button class="dropdown-item small text-muted" onclick="bulkAction('jira','dismiss')"><i class="bi bi-eye-slash me-2"></i>Dismiss all (ignore)</button></li>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($zentaoEntries): ?>
    <div class="dropdown">
      <button class="btn btn-outline-info btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-bug me-1"></i>All Zentao (<?= count($zentaoEntries) ?>)</button>
      <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
        <li><button class="dropdown-item small" onclick="bulkAction('zentao','accept')"><i class="bi bi-cloud-download me-2 text-success"></i>Accept all from Zentao</button></li>
        <li><button class="dropdown-item small" onclick="bulkAction('zentao','push')"><i class="bi bi-cloud-upload me-2 text-info"></i>Push all to Zentao</button></li>
        <li><hr class="dropdown-divider"></li>
        <li><button class="dropdown-item small text-muted" onclick="bulkAction('zentao','dismiss')"><i class="bi bi-eye-slash me-2"></i>Dismiss all (ignore)</button></li>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$total): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-check-circle fs-1 d-block mb-2 text-success opacity-50"></i>
  <div class="fw-semibold">All in sync — no changes detected.</div>
  <small>Run the bulk checks from the entry list to detect new differences.</small>
</div></div>
<?php else: ?>

<!-- ── JIRA CHANGES ────────────────────────────────────────────── -->
<?php if ($jiraEntries): ?>
<div class="card mb-4">
  <div class="card-header border-secondary d-flex align-items-center gap-2">
    <i class="bi bi-bug-fill text-warning"></i>
    <span class="fw-semibold">Jira Changes</span>
    <span class="badge bg-warning text-dark ms-1"><?= count($jiraEntries) ?></span>
    <small class="text-muted ms-2">Entries where Jira differs from RoboDoc (based on last check)</small>
  </div>
  <div class="list-group list-group-flush" id="jiraList">
    <?php foreach ($jiraEntries as $e):
      $diffs = $jiraDiffs[$e['id']] ?? [];
    ?>
    <div class="list-group-item bg-transparent border-secondary py-3 px-3" id="jira-row-<?= $e['id'] ?>">
      <div class="row align-items-start g-3">
        <!-- Entry info -->
        <div class="col-md-4">
          <div class="d-flex gap-2 align-items-start">
            <span class="badge" style="background:<?= e($e['type_color']) ?>;font-size:.65rem;flex-shrink:0"><?= e($e['type_name']) ?></span>
            <div>
              <a href="<?= url('entries/'.$e['id']) ?>" class="fw-semibold text-white text-decoration-none" style="font-size:.85rem">
                <?= e($e['title'] ?: '—') ?>
              </a>
              <div class="text-muted" style="font-size:.72rem"><?= e($e['project_name']) ?> · <?= formatDate($e['entry_date'],'d.m.Y') ?></div>
              <a href="<?= e($e['jira_issue_url']??'#') ?>" target="_blank" class="text-warning text-decoration-none" style="font-size:.72rem">
                <i class="bi bi-bug-fill me-1"></i><?= e($e['jira_issue_key']) ?>
              </a>
            </div>
          </div>
        </div>

        <!-- Differences -->
        <div class="col-md-4">
          <?php if ($diffs): ?>
          <div class="d-flex flex-column gap-1">
            <?php foreach ($diffs as $d): ?>
            <div class="d-flex align-items-center gap-2 small">
              <span class="text-muted fw-semibold" style="min-width:60px"><?= e($d['field']) ?></span>
              <span class="badge bg-secondary"><?= e($d['local']) ?></span>
              <i class="bi bi-arrow-right text-muted"></i>
              <span class="badge bg-warning text-dark"><?= e($d['remote']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <span class="text-muted small">No specific field diff detected — description or comment may have changed.
            <a href="<?= url('jira-sync/entry/'.$e['id']) ?>" class="text-warning ms-1">Review details</a>
          </span>
          <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="col-md-4 d-flex gap-2 flex-wrap justify-content-end align-items-start">
          <button class="btn btn-success btn-sm" onclick="entryAction('jira',<?= $e['id'] ?>,'accept',this)"
                  title="Accept changes from Jira into RoboDoc">
            <i class="bi bi-cloud-download me-1"></i>Accept from Jira
          </button>
          <button class="btn btn-outline-warning btn-sm" onclick="entryAction('jira',<?= $e['id'] ?>,'push',this)"
                  title="Push RoboDoc values to Jira">
            <i class="bi bi-cloud-upload me-1"></i>Push to Jira
          </button>
          <a href="<?= url('jira-sync/entry/'.$e['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i>Review
          </a>
          <button class="btn btn-outline-secondary btn-sm" onclick="entryAction('jira',<?= $e['id'] ?>,'dismiss',this)"
                  title="Ignore this difference without changing anything">
            <i class="bi bi-eye-slash me-1"></i>Dismiss
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── ZENTAO CHANGES ─────────────────────────────────────────── -->
<?php if ($zentaoEntries): ?>
<div class="card mb-4">
  <div class="card-header border-secondary d-flex align-items-center gap-2">
    <i class="bi bi-bug text-info"></i>
    <span class="fw-semibold">Zentao Changes</span>
    <span class="badge bg-info text-dark ms-1"><?= count($zentaoEntries) ?></span>
    <small class="text-muted ms-2">Entries where Zentao differs from RoboDoc (based on last check)</small>
  </div>
  <div class="list-group list-group-flush" id="zentaoList">
    <?php foreach ($zentaoEntries as $e):
      $diffs = $zentaoDiffs[$e['id']] ?? [];
    ?>
    <div class="list-group-item bg-transparent border-secondary py-3 px-3" id="zentao-row-<?= $e['id'] ?>">
      <div class="row align-items-start g-3">
        <div class="col-md-4">
          <div class="d-flex gap-2 align-items-start">
            <span class="badge" style="background:<?= e($e['type_color']) ?>;font-size:.65rem;flex-shrink:0"><?= e($e['type_name']) ?></span>
            <div>
              <a href="<?= url('entries/'.$e['id']) ?>" class="fw-semibold text-white text-decoration-none" style="font-size:.85rem">
                <?= e($e['title'] ?: '—') ?>
              </a>
              <div class="text-muted" style="font-size:.72rem"><?= e($e['project_name']) ?> · <?= formatDate($e['entry_date'],'d.m.Y') ?></div>
              <a href="<?= e($e['zentao_bug_url']??'#') ?>" target="_blank" class="text-info text-decoration-none" style="font-size:.72rem">
                <i class="bi bi-bug me-1"></i>Bug #<?= e($e['zentao_bug_id']) ?>
              </a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <?php if ($diffs): ?>
          <div class="d-flex flex-column gap-1">
            <?php foreach ($diffs as $d): ?>
            <div class="d-flex align-items-center gap-2 small">
              <span class="text-muted fw-semibold" style="min-width:60px"><?= e($d['field']) ?></span>
              <span class="badge bg-secondary"><?= e($d['local']) ?></span>
              <i class="bi bi-arrow-right text-muted"></i>
              <span class="badge bg-info text-dark"><?= e($d['remote']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <span class="text-muted small">No specific field diff.
            <a href="<?= url('zentao-sync/entry/'.$e['id']) ?>" class="text-info ms-1">Review details</a>
          </span>
          <?php endif; ?>
        </div>
        <div class="col-md-4 d-flex gap-2 flex-wrap justify-content-end align-items-start">
          <button class="btn btn-success btn-sm" onclick="entryAction('zentao',<?= $e['id'] ?>,'accept',this)">
            <i class="bi bi-cloud-download me-1"></i>Accept from Zentao
          </button>
          <button class="btn btn-outline-info btn-sm" onclick="entryAction('zentao',<?= $e['id'] ?>,'push',this)">
            <i class="bi bi-cloud-upload me-1"></i>Push to Zentao
          </button>
          <a href="<?= url('zentao-sync/entry/'.$e['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i>Review
          </a>
          <button class="btn btn-outline-secondary btn-sm" onclick="entryAction('zentao',<?= $e['id'] ?>,'dismiss',this)">
            <i class="bi bi-eye-slash me-1"></i>Dismiss
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div id="srToast" class="toast align-items-center position-fixed bottom-0 end-0 m-3 bg-dark border-secondary"
     style="z-index:9999;display:none;min-width:260px">
  <div class="d-flex p-2 gap-2 align-items-center">
    <i id="srToastIcon" class="bi bi-check-circle text-success"></i>
    <span id="srToastMsg" class="small flex-grow-1"></span>
    <button class="btn-close btn-close-white btn-sm" onclick="document.getElementById('srToast').style.display='none'"></button>
  </div>
</div>

<script>
const _srCsrf = '<?= e($csrf) ?>';

function srToast(msg, ok) {
  const t = document.getElementById('srToast');
  document.getElementById('srToastMsg').textContent = msg;
  document.getElementById('srToastIcon').className = ok ? 'bi bi-check-circle text-success' : 'bi bi-x-circle text-danger';
  t.style.display = '';
  clearTimeout(t._t);
  t._t = setTimeout(() => t.style.display = 'none', 3500);
}

function entryAction(source, id, action, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  fetch(`<?= url('admin/sync-review/') ?>${source}/${id}/${action}`, {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _srCsrf })
  })
  .then(r => r.json())
  .then(d => {
    if (d.error) {
      btn.innerHTML = orig; btn.disabled = false;
      srToast(d.error, false);
    } else {
      const label = action === 'accept' ? 'Accepted' : action === 'push' ? 'Pushed' : 'Dismissed';
      const notes = [];
      if (d.transition && !d.transition.startsWith('transitioned')) notes.push('Status not applied: ' + d.transition);
      if (d.priority   && !d.priority.startsWith('set to'))         notes.push('Priority not applied: ' + d.priority);
      if (notes.length) {
        // Jira rejected the field — the entry stays flagged server-side, so reload
        // instead of removing the row, otherwise it looks resolved when it isn't.
        btn.innerHTML = orig; btn.disabled = false;
        srToast(`${label}, but: ${notes.join(' / ')}`, false);
        setTimeout(() => location.reload(), 2500);
        return;
      }
      const row = document.getElementById(`${source}-row-${id}`);
      if (row) {
        row.style.opacity = '.4';
        setTimeout(() => row.remove(), 500);
      }
      srToast(`${label} successfully.` + (d.transition ? ' Status: ' + d.transition : ''), true);
      updateCountBadge(source);
    }
  })
  .catch(() => { btn.innerHTML = orig; btn.disabled = false; srToast('Network error', false); });
}

function bulkAction(source, action) {
  const label = action === 'accept' ? 'Accepting' : action === 'push' ? 'Pushing' : 'Dismissing';
  if (!confirm(`${label} all ${source} changes. Continue?`)) return;
  srToast(`${label} all…`, true);
  fetch(`<?= url('admin/sync-review/') ?>${source}/bulk/${action}`, {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _srCsrf })
  })
  .then(r => r.json())
  .then(d => {
    if (d.error) { srToast(d.error, false); return; }
    srToast(`Done — ${d.processed} processed.` + (d.errors?.length ? ` ${d.errors.length} errors.` : ''), !d.errors?.length);
    setTimeout(() => location.reload(), 1200);
  })
  .catch(() => srToast('Network error', false));
}

function updateCountBadge(source) {
  const list = document.getElementById(source + 'List');
  if (!list) return;
  const remaining = list.querySelectorAll('.list-group-item').length;
  // rough count update
}
</script>
