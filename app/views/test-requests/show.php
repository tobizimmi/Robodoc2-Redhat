<?php
$statusColors = ['draft'=>'secondary','submitted'=>'info','approved'=>'success','rejected'=>'danger','closed'=>'dark'];
$statusColor  = $statusColors[$request['status']] ?? 'secondary';
?>
<div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-requests') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1"><?= e($request['summary']) ?></h5>
  <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($request['status']) ?></span>
  <?php if ($request['jira_issue_key']): ?>
  <a href="<?= e($request['jira_issue_url']) ?>" target="_blank" class="badge bg-primary text-decoration-none fs-6">
    <i class="bi bi-box-arrow-up-right me-1"></i><?= e($request['jira_issue_key']) ?>
  </a>
  <?php endif; ?>
  <a href="<?= url('test-requests/' . $request['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-pencil me-1"></i>Edit
  </a>
</div>

<?php
$jiraComments = Database::fetchAll(
    'SELECT * FROM jira_comments WHERE source_type=? AND source_id=? ORDER BY jira_created_at',
    ['test_request', $request['id']]
);
?>

<?php if (!empty($request['jira_has_changes']) && !empty($request['jira_issue_key'])): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 py-2 mb-3">
  <i class="bi bi-arrow-repeat fs-5 flex-shrink-0"></i>
  <div class="flex-grow-1 small">
    <strong>Jira has new changes</strong> on
    <a href="<?= e($request['jira_issue_url']) ?>" target="_blank" class="alert-link"><?= e($request['jira_issue_key']) ?></a>
    since this request was last synced.
  </div>
  <a href="<?= url('jira-sync/test-request/' . $request['id']) ?>" class="btn btn-warning btn-sm text-nowrap">
    <i class="bi bi-eye me-1"></i>Review Changes
  </a>
</div>
<?php endif; ?>

<div class="row g-4">
  <!-- Main info -->
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Details</div>
      <div class="card-body">
        <div class="row g-3 small">
          <div class="col-sm-6">
            <div class="text-muted">Product</div>
            <div><?= e($request['product'] ?: '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted">Development Type</div>
            <div><?= e($request['development_type'] ?: '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted">Project Name</div>
            <div><?= e($request['project_name'] ?: '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted">Project Number</div>
            <div><?= e($request['project_number'] ?: '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted">Order Number</div>
            <div><?= e($request['order_number'] ?: '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted">Initiator</div>
            <div><?= e($request['initiator'] ?: '—') ?></div>
          </div>
          <?php if ($request['labels']): ?>
          <div class="col-12">
            <div class="text-muted mb-1">Labels</div>
            <div>
              <?php foreach (array_filter(array_map('trim', explode(',', $request['labels']))) as $lbl): ?>
              <span class="badge bg-secondary me-1"><?= e($lbl) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <div class="col-sm-6">
            <div class="text-muted">Created by</div>
            <div><?= e($request['creator_name'] ?? '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted">Created at</div>
            <div><?= formatDateTime($request['created_at']) ?></div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($request['description']): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Description</div>
      <div class="card-body">
        <pre class="mb-0" style="white-space:pre-wrap;font-family:inherit"><?= e($request['description']) ?></pre>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($jiraComments): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">
        <i class="bi bi-chat-left-text me-2 text-warning"></i>Jira Comments
        <span class="badge bg-secondary ms-1"><?= count($jiraComments) ?></span>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($jiraComments as $jc): ?>
        <div class="list-group-item bg-transparent py-2">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-person-circle text-muted small"></i>
            <span class="fw-semibold small"><?= e($jc['author_name']) ?></span>
            <span class="text-muted small"><?= e(substr($jc['jira_created_at'] ?? '', 0, 16)) ?></span>
          </div>
          <pre class="mb-0 small ms-4" style="white-space:pre-wrap;font-family:inherit"><?= e($jc['body']) ?></pre>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($attachments): ?>
    <div class="card">
      <div class="card-header border-secondary fw-semibold small">Attachments</div>
      <div class="list-group list-group-flush">
        <?php foreach ($attachments as $att): ?>
        <div class="list-group-item bg-transparent d-flex align-items-center gap-3 py-2 small">
          <i class="bi bi-paperclip text-muted"></i>
          <span class="flex-grow-1"><?= e($att['display_name'] ?: $att['original_name']) ?></span>
          <span class="text-muted"><?= formatFileSize((int)($att['file_size'] ?? 0)) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar: Jira -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
        <i class="bi bi-boxes me-1 text-primary"></i>Jira
      </div>
      <div class="card-body">
        <?php if ($request['jira_issue_key']): ?>
        <div class="alert alert-success py-2 small mb-3">
          <i class="bi bi-check-circle me-1"></i>
          Linked: <a href="<?= e($request['jira_issue_url']) ?>" target="_blank" class="alert-link"><?= e($request['jira_issue_key']) ?></a>
        </div>
        <?php else: ?>
        <p class="text-muted small mb-3">No Jira issue linked yet.</p>
        <?php endif; ?>

        <?php if ($request['jira_issue_key']): ?>
        <!-- Sync status -->
        <div class="mb-3">
          <?php if (!empty($request['jira_has_changes'])): ?>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-warning text-dark"><i class="bi bi-arrow-repeat me-1"></i>Changes detected</span>
          </div>
          <a href="<?= url('jira-sync/test-request/' . $request['id']) ?>" class="btn btn-warning btn-sm w-100 mb-2">
            <i class="bi bi-eye me-1"></i>Review Changes
          </a>
          <?php else: ?>
          <div class="text-muted small mb-2" id="jiraSyncStatus">
            <?php if ($request['jira_synced_at']): ?>
            <i class="bi bi-check-circle text-success me-1"></i>Last checked: <?= formatDateTime($request['jira_synced_at']) ?>
            <?php else: ?>
            <i class="bi bi-dash-circle text-muted me-1"></i>Not yet checked
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <button class="btn btn-outline-secondary btn-sm w-100" id="jiraCheckBtn"
                  onclick="checkJiraChanges('test_request', <?= $request['id'] ?>, this)">
            <i class="bi bi-arrow-repeat me-1"></i>Check Jira for Changes
          </button>
        </div>
        <?php else: ?>
        <?php $jiraProjectKey = appSetting('jira_test_request_project'); ?>
        <button class="btn btn-primary btn-sm w-100" id="pushJiraBtn" onclick="pushToJira()"
                <?= $jiraProjectKey ? '' : 'disabled title="Jira Test Request project not configured in Admin → Settings"' ?>>
          <i class="bi bi-box-arrow-up me-1"></i>Create Jira Issue
        </button>
        <?php if (!$jiraProjectKey): ?>
        <div class="form-text text-warning mt-1">Configure <em>Jira Test Request Project</em> in Admin → Settings first.</div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function checkJiraChanges(sourceType, sourceId, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking…';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  fetch('<?= url('api/jira-sync/check-record') ?>', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: csrf, source_type: sourceType, source_id: sourceId })
  })
  .then(r => r.json())
  .then(d => {
    btn.disabled = false; btn.innerHTML = orig;
    if (d.error) { alert('Jira error: ' + d.error); return; }
    if (d.has_changes) {
      location.reload();
    } else {
      const statusEl = document.getElementById('jiraSyncStatus');
      if (statusEl) statusEl.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Up to date (checked just now)';
      btn.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Up to date';
      setTimeout(() => { btn.innerHTML = orig; }, 3000);
    }
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = orig; alert('Network error'); });
}
</script>

<?php if (!$request['jira_issue_key']): ?>
<script>
function pushToJira() {
  const btn = document.getElementById('pushJiraBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating…';
  fetch('<?= url('test-requests/' . $request['id'] . '/jira') ?>', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: '_csrf=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]')?.content || ''),
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        location.reload();
      } else {
        alert('Jira error: ' + (d.error || 'Unknown error'));
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-box-arrow-up me-1"></i>Create Jira Issue';
      }
    })
    .catch(() => {
      alert('Network error.');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-box-arrow-up me-1"></i>Create Jira Issue';
    });
}
</script>
<?php endif; ?>
