<?php
$csrf = Auth::csrfToken();
$jiraUrl = $jiraUrl ?? '#';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><i class="bi bi-bug-fill me-2 text-warning"></i>Xray Test Management</h4>
    <small class="text-muted">Project: <strong><?= e($project ?? 'BRSQ') ?></strong></small>
  </div>
  <?php if (Auth::canEdit('testing')): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPlanModal">
      <i class="bi bi-plus-lg me-1"></i>New Test Plan
    </button>
    <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#createExecModal">
      <i class="bi bi-plus-lg me-1"></i>New Test Execution
    </button>
  </div>
  <?php endif; ?>
</div>

<?php if ($error): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="xrayTabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabPlans">
    <i class="bi bi-journal-check me-1"></i>Test Plans <span class="badge bg-secondary ms-1"><?= count($testPlans) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabExecs">
    <i class="bi bi-play-circle me-1"></i>Test Executions <span class="badge bg-secondary ms-1"><?= count($executions) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabSync">
    <i class="bi bi-arrow-repeat me-1"></i>Sync RoboDoc ↔ Xray</a></li>
</ul>

<div class="tab-content">

  <!-- Test Plans -->
  <div class="tab-pane fade show active" id="tabPlans">
    <?php if (!$testPlans): ?>
    <div class="text-muted text-center py-5"><i class="bi bi-journal-check fs-1 d-block mb-2 opacity-25"></i>No test plans found.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle">
        <thead><tr>
          <th>Key</th><th>Summary</th><th>Status</th><th>Linked RoboDoc Plan</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($testPlans as $p): ?>
          <?php $linked = Database::fetchOne('SELECT name FROM test_plans WHERE xray_key=?', [$p['key']]); ?>
          <tr>
            <td><a href="<?= e($jiraUrl) ?>/browse/<?= e($p['key']) ?>" target="_blank"
                   class="badge bg-dark text-warning text-decoration-none border border-warning">
              <?= e($p['key']) ?></a></td>
            <td><a href="<?= url('xray/plan/'.urlencode($p['key'])) ?>" class="text-white"><?= e($p['summary']) ?></a></td>
            <td><span class="badge bg-secondary"><?= e($p['status']) ?></span></td>
            <td><?= $linked ? e($linked['name']) : '<span class="text-muted small">—</span>' ?></td>
            <td class="text-end">
              <a href="<?= url('xray/plan/'.urlencode($p['key'])) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Test Executions -->
  <div class="tab-pane fade" id="tabExecs">
    <?php if (!$executions): ?>
    <div class="text-muted text-center py-5"><i class="bi bi-play-circle fs-1 d-block mb-2 opacity-25"></i>No test executions found.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle">
        <thead><tr>
          <th>Key</th><th>Summary</th><th>Status</th><th>Linked RoboDoc Run</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($executions as $e): ?>
          <?php $linked = Database::fetchOne('SELECT name FROM test_runs WHERE xray_key=?', [$e['key']]); ?>
          <tr>
            <td><a href="<?= e($jiraUrl) ?>/browse/<?= e($e['key']) ?>" target="_blank"
                   class="badge bg-dark text-warning text-decoration-none border border-warning">
              <?= e($e['key']) ?></a></td>
            <td><a href="<?= url('xray/execution/'.urlencode($e['key'])) ?>" class="text-white"><?= e($e['summary']) ?></a></td>
            <td><span class="badge bg-secondary"><?= e($e['status']) ?></span></td>
            <td><?= $linked ? e($linked['name']) : '<span class="text-muted small">—</span>' ?></td>
            <td class="text-end">
              <a href="<?= url('xray/execution/'.urlencode($e['key'])) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Sync Tab -->
  <div class="tab-pane fade" id="tabSync">
    <div class="row g-4">

      <!-- Sync Test Plans -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header border-secondary fw-semibold small">
            <i class="bi bi-journal-check me-1"></i>Sync Test Plans
          </div>
          <div class="card-body">
            <table class="table table-dark table-sm align-middle" style="font-size:.82rem">
              <thead><tr><th>RoboDoc Plan</th><th>Xray Key</th><th>Last Sync</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($localPlans as $lp): ?>
                <tr>
                  <td><?= e($lp['name']) ?></td>
                  <td>
                    <?php if ($lp['xray_key']): ?>
                      <a href="<?= e($jiraUrl) ?>/browse/<?= e($lp['xray_key']) ?>" target="_blank"
                         class="badge bg-warning text-dark text-decoration-none"><?= e($lp['xray_key']) ?></a>
                    <?php else: ?>
                      <input type="text" class="form-control form-control-sm bg-dark text-white border-secondary"
                             placeholder="e.g. BRSQ-1" id="xk-plan-<?= $lp['id'] ?>" style="width:110px">
                    <?php endif; ?>
                  </td>
                  <td class="text-muted" style="font-size:.7rem"><?= $lp['xray_synced_at'] ? formatDate($lp['xray_synced_at'],'d.m. H:i') : '—' ?></td>
                  <td>
                    <button class="btn btn-outline-warning btn-sm" onclick="syncItem('plan',<?= $lp['id'] ?>,'<?= e($lp['xray_key'] ?? '') ?>','<?= e($csrf) ?>')" title="Sync bidirectional">
                      <i class="bi bi-arrow-repeat"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Sync Test Runs/Executions -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header border-secondary fw-semibold small">
            <i class="bi bi-play-circle me-1"></i>Sync Test Runs / Executions
          </div>
          <div class="card-body">
            <table class="table table-dark table-sm align-middle" style="font-size:.82rem">
              <thead><tr><th>RoboDoc Run</th><th>Xray Key</th><th>Last Sync</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($localRuns as $lr): ?>
                <tr>
                  <td><?= e($lr['name']) ?></td>
                  <td>
                    <?php if ($lr['xray_key']): ?>
                      <a href="<?= e($jiraUrl) ?>/browse/<?= e($lr['xray_key']) ?>" target="_blank"
                         class="badge bg-warning text-dark text-decoration-none"><?= e($lr['xray_key']) ?></a>
                    <?php else: ?>
                      <input type="text" class="form-control form-control-sm bg-dark text-white border-secondary"
                             placeholder="e.g. BRSQ-2" id="xk-run-<?= $lr['id'] ?>" style="width:110px">
                    <?php endif; ?>
                  </td>
                  <td class="text-muted" style="font-size:.7rem"><?= $lr['xray_synced_at'] ? formatDate($lr['xray_synced_at'],'d.m. H:i') : '—' ?></td>
                  <td>
                    <button class="btn btn-outline-warning btn-sm" onclick="syncItem('execution',<?= $lr['id'] ?>,'<?= e($lr['xray_key'] ?? '') ?>','<?= e($csrf) ?>')" title="Sync bidirectional">
                      <i class="bi bi-arrow-repeat"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php if (Auth::canEdit('testing')): ?>
<!-- Create Test Plan Modal -->
<div class="modal fade" id="createPlanModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title">New Xray Test Plan</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label">Summary *</label>
        <input type="text" id="newPlanSummary" class="form-control" placeholder="Test Plan name"></div>
      <div class="mb-3"><label class="form-label">Description</label>
        <textarea id="newPlanDesc" class="form-control" rows="3"></textarea></div>
      <div class="mb-3"><label class="form-label">Link to RoboDoc Test Plan</label>
        <select id="newPlanLocal" class="form-select">
          <option value="">— none —</option>
          <?php foreach ($localPlans as $lp): ?>
          <option value="<?= $lp['id'] ?>"><?= e($lp['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div id="createPlanResult"></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-primary" onclick="createXrayPlan('<?= e($csrf) ?>')">Create in Xray</button>
    </div>
  </div></div>
</div>

<!-- Create Test Execution Modal -->
<div class="modal fade" id="createExecModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title">New Xray Test Execution</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label">Summary *</label>
        <input type="text" id="newExecSummary" class="form-control" placeholder="Test Execution name"></div>
      <div class="mb-3"><label class="form-label">Description</label>
        <textarea id="newExecDesc" class="form-control" rows="3"></textarea></div>
      <div class="mb-3"><label class="form-label">Link to Test Plan (Xray Key)</label>
        <select id="newExecPlan" class="form-select">
          <option value="">— none —</option>
          <?php foreach ($testPlans as $tp): ?>
          <option value="<?= e($tp['key']) ?>"><?= e($tp['key']) ?> — <?= e(mb_substr($tp['summary'],0,40)) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="mb-3"><label class="form-label">Link to RoboDoc Test Run</label>
        <select id="newExecLocal" class="form-select">
          <option value="">— none —</option>
          <?php foreach ($localRuns as $lr): ?>
          <option value="<?= $lr['id'] ?>"><?= e($lr['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div id="createExecResult"></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-primary" onclick="createXrayExecution('<?= e($csrf) ?>')">Create in Xray</button>
    </div>
  </div></div>
</div>
<?php endif; ?>

<div id="syncToast" class="position-fixed bottom-0 end-0 m-3 p-3 rounded bg-dark border border-secondary"
     style="display:none;z-index:9999;max-width:360px;font-size:.82rem"></div>

<script>
function showSyncToast(html, ok) {
  const t = document.getElementById('syncToast');
  t.innerHTML = '<i class="bi bi-' + (ok ? 'check-circle text-success' : 'x-circle text-danger') + ' me-2"></i>' + html;
  t.style.display = '';
  clearTimeout(t._t); t._t = setTimeout(() => t.style.display='none', 5000);
}

function syncItem(type, localId, xrayKey, csrf) {
  // If no xray key yet, read from input field
  if (!xrayKey) {
    const inp = document.getElementById('xk-' + type + '-' + localId);
    xrayKey = inp ? inp.value.trim() : '';
  }
  showSyncToast('Syncing...', true);
  fetch('<?= url('xray/sync') ?>', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, type, local_id: localId, xray_key: xrayKey, direction: 'both'})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      showSyncToast('Sync complete. ' + (d.log || []).length + ' actions.', true);
      setTimeout(() => location.reload(), 1500);
    } else {
      showSyncToast(d.error || 'Sync failed', false);
    }
  })
  .catch(() => showSyncToast('Network error', false));
}

function createXrayPlan(csrf) {
  const summary = document.getElementById('newPlanSummary').value.trim();
  const desc    = document.getElementById('newPlanDesc').value.trim();
  const local   = document.getElementById('newPlanLocal').value;
  const res     = document.getElementById('createPlanResult');
  if (!summary) { res.innerHTML = '<div class="alert alert-warning py-1 small">Summary required</div>'; return; }
  res.innerHTML = '<div class="text-muted small">Creating...</div>';
  fetch('<?= url('xray/create-test-plan') ?>', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, summary, description: desc, robodoc_plan_id: local})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      res.innerHTML = '<div class="alert alert-success py-1 small">Created: <a href="' + d.url + '" target="_blank">' + d.key + '</a></div>';
      setTimeout(() => location.reload(), 2000);
    } else {
      res.innerHTML = '<div class="alert alert-danger py-1 small">' + JSON.stringify(d.error) + '</div>';
    }
  });
}

function createXrayExecution(csrf) {
  const summary  = document.getElementById('newExecSummary').value.trim();
  const desc     = document.getElementById('newExecDesc').value.trim();
  const planKey  = document.getElementById('newExecPlan').value;
  const localRun = document.getElementById('newExecLocal').value;
  const res      = document.getElementById('createExecResult');
  if (!summary) { res.innerHTML = '<div class="alert alert-warning py-1 small">Summary required</div>'; return; }
  res.innerHTML = '<div class="text-muted small">Creating...</div>';
  fetch('<?= url('xray/create-test-execution') ?>', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, summary, description: desc, test_plan_key: planKey, robodoc_run_id: localRun})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      res.innerHTML = '<div class="alert alert-success py-1 small">Created: <a href="' + d.url + '" target="_blank">' + d.key + '</a></div>';
      setTimeout(() => location.reload(), 2000);
    } else {
      res.innerHTML = '<div class="alert alert-danger py-1 small">' + JSON.stringify(d.error) + '</div>';
    }
  });
}
</script>
