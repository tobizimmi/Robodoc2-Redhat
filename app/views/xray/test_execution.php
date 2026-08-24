<?php
$statusColors = ['PASS'=>'success','FAIL'=>'danger','SKIPPED'=>'secondary','TODO'=>'warning','EXECUTING'=>'info'];
$csrf = Auth::csrfToken();
?>
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('xray') ?>">
    <i class="bi bi-arrow-left"></i></a>
  <div>
    <h5 class="mb-0"><i class="bi bi-play-circle me-2 text-warning"></i><?= e($execution['summary']) ?></h5>
    <div class="d-flex gap-2 align-items-center mt-1">
      <a href="<?= e($execution['url']) ?>" target="_blank"
         class="badge bg-dark text-warning border border-warning text-decoration-none">
        <i class="bi bi-box-arrow-up-right me-1"></i><?= e($execution['key']) ?>
      </a>
      <span class="badge bg-secondary"><?= e($execution['status']) ?></span>
      <?php if ($localRun): ?>
      <span class="badge bg-info text-dark">
        <i class="bi bi-link-45deg me-1"></i>RoboDoc: <?= e($localRun['name']) ?>
      </span>
      <?php endif; ?>
    </div>
  </div>
  <?php if (Auth::canEdit('testing')): ?>
  <div class="ms-auto d-flex gap-2">
    <button class="btn btn-outline-warning btn-sm" onclick="syncExec('<?= e($execution['key']) ?>','<?= e($csrf) ?>')">
      <i class="bi bi-arrow-repeat me-1"></i>Sync
    </button>
    <a href="<?= e($execution['url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-jira me-1"></i>Open in Jira
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Pass/Fail summary -->
<?php
$total   = count($testRuns);
$passed  = count(array_filter($testRuns, fn($r)=>strtoupper($r['status'])==='PASS'));
$failed  = count(array_filter($testRuns, fn($r)=>strtoupper($r['status'])==='FAIL'));
$skipped = count(array_filter($testRuns, fn($r)=>strtoupper($r['status'])==='SKIPPED'));
$todo    = $total - $passed - $failed - $skipped;
?>
<?php if ($total > 0): ?>
<div class="d-flex gap-3 mb-4 flex-wrap">
  <div class="card text-center px-4 py-2"><div class="fw-bold text-success fs-4"><?= $passed ?></div><div class="text-muted small">Passed</div></div>
  <div class="card text-center px-4 py-2"><div class="fw-bold text-danger fs-4"><?= $failed ?></div><div class="text-muted small">Failed</div></div>
  <div class="card text-center px-4 py-2"><div class="fw-bold text-secondary fs-4"><?= $skipped ?></div><div class="text-muted small">Skipped</div></div>
  <div class="card text-center px-4 py-2"><div class="fw-bold text-warning fs-4"><?= $todo ?></div><div class="text-muted small">TODO</div></div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
    <i class="bi bi-list-check me-1"></i>Test Results
    <span class="badge bg-secondary ms-auto"><?= $total ?></span>
  </div>
  <?php if ($testRuns): ?>
  <div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0" style="font-size:.82rem">
      <thead><tr><th>Key</th><th>Test</th><th>Status</th><th>Executed By</th><th>Date</th><th>Comment</th></tr></thead>
      <tbody>
        <?php foreach ($testRuns as $t): ?>
        <?php $sc = $statusColors[strtoupper($t['status'])] ?? 'secondary'; ?>
        <tr>
          <td><a href="<?= e($jiraUrl) ?>/browse/<?= e($t['key']) ?>" target="_blank"
                 class="badge bg-dark text-warning border border-warning text-decoration-none">
            <?= e($t['key']) ?></a></td>
          <td><?= e($t['summary']) ?></td>
          <td><span class="badge bg-<?= $sc ?>"><?= e($t['status']) ?></span></td>
          <td class="text-muted"><?= e($t['executedBy']) ?></td>
          <td class="text-muted"><?= $t['executedOn'] ? formatDate($t['executedOn'],'d.m.') : '—' ?></td>
          <td class="text-muted" style="max-width:200px"><?= e(mb_substr($t['comment'],0,60)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="card-body text-muted small">No test results found in this execution.</div>
  <?php endif; ?>
</div>

<?php if (!$localRun && Auth::canEdit('testing')): ?>
<div class="card mt-4" style="max-width:400px">
  <div class="card-header border-secondary fw-semibold small">
    <i class="bi bi-link-45deg me-1"></i>Link to RoboDoc Test Run
  </div>
  <div class="card-body">
    <select id="linkLocalRun" class="form-select form-select-sm mb-2">
      <option value="">— select —</option>
      <?php $rdRuns = Database::fetchAll('SELECT id,name FROM test_runs ORDER BY name'); ?>
      <?php foreach ($rdRuns as $r): ?>
      <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-info btn-sm w-100" onclick="linkRoboDocRun('<?= e($execution['key']) ?>','<?= e($csrf) ?>')">
      Link & Sync
    </button>
    <div id="linkRunResult" class="mt-2"></div>
  </div>
</div>
<?php endif; ?>

<div id="syncToast" class="position-fixed bottom-0 end-0 m-3 p-3 rounded bg-dark border border-secondary"
     style="display:none;z-index:9999;max-width:360px;font-size:.82rem"></div>

<script>
function showToastXray(html,ok){
  const t=document.getElementById('syncToast');
  t.innerHTML='<i class="bi bi-'+(ok?'check-circle text-success':'x-circle text-danger')+' me-2"></i>'+html;
  t.style.display=''; clearTimeout(t._t); t._t=setTimeout(()=>t.style.display='none',5000);
}
function syncExec(xrayKey,csrf){
  const localRun=<?= $localRun ? $localRun['id'] : 'null' ?>;
  if(!localRun){showToastXray('No RoboDoc run linked yet.',false);return;}
  showToastXray('Syncing...',true);
  fetch('<?= url('xray/sync') ?>',{
    method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},
    body:new URLSearchParams({_csrf:csrf,type:'execution',local_id:localRun,xray_key:xrayKey,direction:'both'})
  }).then(r=>r.json()).then(d=>{
    if(d.success)showToastXray('Sync complete — '+d.log.length+' actions',true);
    else showToastXray(d.error||'Error',false);
  });
}
function linkRoboDocRun(xrayKey,csrf){
  const localId=document.getElementById('linkLocalRun').value;
  if(!localId)return;
  const res=document.getElementById('linkRunResult');
  res.innerHTML='<span class="text-muted small">Linking...</span>';
  fetch('<?= url('xray/sync') ?>',{
    method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},
    body:new URLSearchParams({_csrf:csrf,type:'execution',local_id:localId,xray_key:xrayKey,direction:'both'})
  }).then(r=>r.json()).then(d=>{
    if(d.success){res.innerHTML='<span class="text-success small">Linked!</span>';setTimeout(()=>location.reload(),1200);}
    else res.innerHTML='<span class="text-danger small">'+(d.error||'Error')+'</span>';
  });
}
</script>
