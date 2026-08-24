<?php
$statusColors = ['PASS'=>'success','FAIL'=>'danger','SKIPPED'=>'secondary','TODO'=>'warning','EXECUTING'=>'info'];
$csrf = Auth::csrfToken();
?>
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('xray') ?>">
    <i class="bi bi-arrow-left"></i></a>
  <div>
    <h5 class="mb-0"><i class="bi bi-journal-check me-2 text-warning"></i><?= e($plan['summary']) ?></h5>
    <div class="d-flex gap-2 align-items-center mt-1">
      <a href="<?= e($plan['url']) ?>" target="_blank"
         class="badge bg-dark text-warning border border-warning text-decoration-none">
        <i class="bi bi-box-arrow-up-right me-1"></i><?= e($plan['key']) ?>
      </a>
      <span class="badge bg-secondary"><?= e($plan['status']) ?></span>
      <?php if ($localPlan): ?>
      <span class="badge bg-info text-dark">
        <i class="bi bi-link-45deg me-1"></i>RoboDoc: <?= e($localPlan['name']) ?>
      </span>
      <?php endif; ?>
    </div>
  </div>
  <?php if (Auth::canEdit('testing')): ?>
  <div class="ms-auto d-flex gap-2">
    <button class="btn btn-outline-warning btn-sm"
            onclick="syncPlan('<?= e($plan['key']) ?>','<?= e($csrf) ?>')"
            title="Bidirectional sync">
      <i class="bi bi-arrow-repeat me-1"></i>Sync
    </button>
    <a href="<?= e($plan['url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-jira me-1"></i>Open in Jira
    </a>
  </div>
  <?php endif; ?>
</div>

<div class="row g-4">

  <!-- Tests in this plan -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
        <i class="bi bi-list-check me-1"></i>Tests in this Plan
        <span class="badge bg-secondary ms-auto"><?= count($planTests) ?></span>
      </div>
      <?php if ($planTests): ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="font-size:.82rem">
          <thead><tr><th>Key</th><th>Summary</th><th>Status</th><th>Type</th></tr></thead>
          <tbody>
            <?php foreach ($planTests as $t): ?>
            <?php $sc = $statusColors[strtoupper($t['status'])] ?? 'secondary'; ?>
            <tr>
              <td><a href="<?= e($jiraUrl) ?>/browse/<?= e($t['key']) ?>" target="_blank"
                     class="badge bg-dark text-warning border border-warning text-decoration-none">
                <?= e($t['key']) ?></a></td>
              <td><?= e($t['summary']) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= e($t['status']) ?></span></td>
              <td class="text-muted"><?= e($t['type']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-muted small">No tests linked to this plan.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Test Executions -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small">
        <i class="bi bi-play-circle me-1"></i>Test Executions
      </div>
      <?php if ($planExecutions): ?>
      <div class="list-group list-group-flush">
        <?php foreach ($planExecutions as $e): ?>
        <a href="<?= url('xray/execution/'.urlencode($e['key'])) ?>"
           class="list-group-item list-group-item-action bg-dark border-secondary d-flex justify-content-between align-items-center"
           style="font-size:.82rem">
          <div>
            <span class="badge bg-dark text-warning border border-warning me-2"><?= e($e['key']) ?></span>
            <?= e(mb_substr($e['summary'],0,35)) ?>
          </div>
          <span class="badge bg-secondary"><?= e($e['status']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="card-body text-muted small">No executions found.</div>
      <?php endif; ?>
    </div>

    <!-- RoboDoc link -->
    <?php if (!$localPlan && Auth::canEdit('testing')): ?>
    <div class="card mt-3">
      <div class="card-header border-secondary fw-semibold small">
        <i class="bi bi-link-45deg me-1"></i>Link to RoboDoc Plan
      </div>
      <div class="card-body">
        <select id="linkLocalPlan" class="form-select form-select-sm mb-2">
          <option value="">— select —</option>
          <?php $rdPlans = Database::fetchAll('SELECT id,name FROM test_plans ORDER BY name'); ?>
          <?php foreach ($rdPlans as $p): ?>
          <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-info btn-sm w-100" onclick="linkRoboDocPlan('<?= e($plan['key']) ?>','<?= e($csrf) ?>')">
          Link & Sync
        </button>
        <div id="linkPlanResult" class="mt-2"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<div id="syncToast" class="position-fixed bottom-0 end-0 m-3 p-3 rounded bg-dark border border-secondary"
     style="display:none;z-index:9999;max-width:360px;font-size:.82rem"></div>

<script>
function showToastXray(html, ok) {
  const t = document.getElementById('syncToast');
  t.innerHTML = '<i class="bi bi-' + (ok?'check-circle text-success':'x-circle text-danger') + ' me-2"></i>' + html;
  t.style.display=''; clearTimeout(t._t); t._t=setTimeout(()=>t.style.display='none',5000);
}
function syncPlan(xrayKey, csrf) {
  const localPlan = <?= $localPlan ? $localPlan['id'] : 'null' ?>;
  if (!localPlan) { showToastXray('No RoboDoc plan linked yet.', false); return; }
  showToastXray('Syncing...', true);
  fetch('<?= url('xray/sync') ?>', {
    method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf:csrf, type:'plan', local_id:localPlan, xray_key:xrayKey, direction:'both'})
  }).then(r=>r.json()).then(d=>{
    if(d.success) showToastXray('Sync complete — '+d.log.length+' actions', true);
    else showToastXray(d.error||'Error', false);
  });
}
function linkRoboDocPlan(xrayKey, csrf) {
  const localId = document.getElementById('linkLocalPlan').value;
  if (!localId) return;
  const res = document.getElementById('linkPlanResult');
  res.innerHTML = '<span class="text-muted small">Linking & syncing...</span>';
  fetch('<?= url('xray/sync') ?>', {
    method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf:csrf, type:'plan', local_id:localId, xray_key:xrayKey, direction:'both'})
  }).then(r=>r.json()).then(d=>{
    if(d.success) { res.innerHTML='<span class="text-success small">Linked!</span>'; setTimeout(()=>location.reload(),1200); }
    else res.innerHTML='<span class="text-danger small">'+(d.error||'Error')+'</span>';
  });
}
</script>
