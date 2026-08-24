<?php
$csrf    = Auth::csrfToken();
$canEdit = Auth::canEdit('testing');
$cycles  = $cycles ?? [];
$runsWithoutCycle = $runsWithoutCycle ?? [];
$allSteps = $allSteps ?? [];
$runsByCycle = $runsByCycle ?? [];

// Overall progress across all cycles
$totR = 0; $totP = 0; $totF = 0;
foreach ($runsByCycle as $crs) {
    foreach ($crs as $cr) { $totR += (int)$cr['rc']; $totP += (int)$cr['p']; $totF += (int)$cr['f']; }
}
$totPct = $totR > 0 ? round($totP/$totR*100) : 0;
?>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div class="d-flex align-items-center gap-2">
    <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-plans') ?>"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5 class="mb-0 fw-bold"><?= e($plan['name']) ?></h5>
      <small class="text-muted"><?= e($plan['project_name']) ?></small>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <?php if (!empty($plan['xray_key'])): ?>
    <a href="<?= url('synapse') ?>" class="badge bg-warning text-dark text-decoration-none" style="font-size:.72rem">
      <i class="bi bi-arrow-repeat me-1"></i><?= e($plan['xray_key']) ?>
    </a>
    <button class="btn btn-outline-warning btn-sm" onclick="synapseSyncPlan(<?= $plan['id'] ?>, '<?= e($csrf) ?>')">
      <i class="bi bi-arrow-repeat me-1"></i>Sync
    </button>
    <?php else: ?>
    <a href="<?= url('synapse') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-link-45deg me-1"></i>SynapseRT</a>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createCycleModal"><i class="bi bi-plus-lg me-1"></i>Neuer Cycle</button>
    <?php endif; ?>
    <a href="<?= url('test-plans/' . $plan['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
  </div>
</div>

<!-- Overall progress bar -->
<?php if ($totR > 0): ?>
<div class="card mb-3 border-0" style="background:rgba(255,255,255,.04)">
  <div class="card-body py-2 px-3">
    <div class="d-flex align-items-center gap-3">
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between small mb-1">
          <span class="text-muted fw-semibold">Gesamtfortschritt</span>
          <span><?= $totP ?>/<?= $totR ?> &middot; <strong><?= $totPct ?>%</strong></span>
        </div>
        <div class="progress" style="height:8px">
          <div class="progress-bar bg-success" style="width:<?= $totPct ?>%"></div>
          <div class="progress-bar bg-danger"  style="width:<?= $totR>0?round($totF/$totR*100):0 ?>%"></div>
        </div>
      </div>
      <div class="d-flex gap-3 flex-shrink-0 text-center" style="font-size:.75rem">
        <div><div class="fw-bold text-success"><?= $totP ?></div><div class="text-muted">ok</div></div>
        <div><div class="fw-bold text-danger"><?= $totF ?></div><div class="text-muted">fail</div></div>
        <div><div class="fw-bold text-muted"><?= $totR-$totP-$totF ?></div><div class="text-muted">offen</div></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-4">
<div class="col-lg-8">

<!-- Test Cases -->
<div class="card mb-4">
  <div class="card-header border-secondary d-flex align-items-center justify-content-between">
    <span class="fw-semibold small"><i class="bi bi-list-check me-1"></i>Test Cases <span class="badge bg-secondary ms-1"><?= count($items) ?></span></span>
    <?php if (!empty($plan['xray_key'])): ?>
    <span class="text-muted small"><?= count(array_filter($items, fn($i)=>!empty($i['synapse_key']))) ?>/<?= count($items) ?> in SynapseRT</span>
    <?php endif; ?>
  </div>
  <?php if ($canEdit): ?>
  <div class="card-body border-bottom border-secondary pb-3">
    <form method="POST" action="<?= url('test-plans/'.$plan['id'].'/items') ?>">
      <?= csrfField() ?>
      <div class="row g-2">
        <div class="col-md-7"><input type="text" name="title" class="form-control form-control-sm" placeholder="Title *" required></div>
        <div class="col-md-2"><select name="priority" class="form-select form-select-sm"><option value="low">Niedrig</option><option value="medium" selected>Mittel</option><option value="high">Hoch</option><option value="critical">Kritisch</option></select></div>
        <div class="col-md-3"><button class="btn btn-primary btn-sm w-100">Add</button></div>
        <div class="col-12"><textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Description (optional)"></textarea></div>
        <div class="col-12"><textarea name="expected_result" class="form-control form-control-sm" rows="2" placeholder="Erwartetes Ergebnis (optional)"></textarea></div>
      </div>
    </form>
  </div>
  <?php endif; ?>
  <?php if ($items): ?>
  <div class="list-group list-group-flush">
    <?php foreach ($items as $item):
      $pc = match($item['priority']) { 'critical'=>'danger','high'=>'warning','low'=>'secondary',default=>'info' };
      $steps = $allSteps[$item['id']] ?? [];
    ?>
    <div class="list-group-item bg-dark border-secondary py-2 px-3">
      <div class="d-flex align-items-start gap-2">
        <span class="badge bg-<?= $pc ?> mt-1 flex-shrink-0" style="font-size:.65rem"><?= e($item['priority']) ?></span>
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <span class="fw-semibold small"><?= e($item['title']) ?></span>
            <?php if (!empty($item['synapse_key'])): ?>
            <span class="badge bg-dark border border-warning text-warning" style="font-size:.6rem" title="Status: <?= e($item['synapse_status'] ?? '') ?>">
              <i class="bi bi-link-45deg me-1"></i><?= e($item['synapse_key']) ?>
              <?php if ($item['synapse_status']): ?>&middot; <?= e($item['synapse_status']) ?><?php endif; ?>
            </span>
            <?php endif; ?>
          </div>
          <?php if ($item['description']): ?>
          <div class="text-muted small mb-1"><?= e(mb_substr($item['description'],0,100)) ?></div>
          <?php endif; ?>
          <?php if (!empty($item['req_summary'])): ?>
          <div class="text-muted small mb-1">
            <i class="bi bi-link-45deg me-1"></i>
            <a href="<?= url('test-requests/'.$item['req_id']) ?>" class="text-muted"><?= e($item['req_summary']) ?></a>
            <span class="badge bg-secondary ms-1" style="font-size:.6rem"><?= e($item['req_status']) ?></span>
          </div>
          <?php endif; ?>
          <!-- Steps -->
          <div class="mt-2">
            <?php if ($steps): ?>
            <table class="table table-dark table-sm mb-1" style="font-size:.72rem">
              <thead class="text-muted"><tr>
                <th style="width:28px">#</th><th>Step</th>
                <th style="width:22%">Test Data</th>
                <th style="width:25%">Expected Result</th>
                <?php if ($canEdit): ?><th style="width:54px"></th><?php endif; ?>
              </tr></thead>
              <tbody>
                <?php foreach ($steps as $st): ?>
                <tr id="sr-<?= $st['id'] ?>">
                  <td class="text-muted"><?= (int)$st['step_number'] ?></td>
                  <td><?= nl2br(e($st['step_action']??'')) ?></td>
                  <td class="text-muted"><?= nl2br(e($st['test_data']??'')) ?></td>
                  <td><?= nl2br(e($st['expected_result']??'')) ?></td>
                  <?php if ($canEdit): ?>
                  <td>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="editStep(<?= $st['id'] ?>,<?= $item['id'] ?>,<?= $plan['id'] ?>)" title="Bearbeiten"><i class="bi bi-pencil" style="font-size:.65rem"></i></button>
                    <button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="delStep(<?= $st['id'] ?>,<?= $item['id'] ?>,<?= $plan['id'] ?>,'<?= e($csrf) ?>')" title="Loeschen"><i class="bi bi-trash" style="font-size:.65rem"></i></button>
                  </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
            <?php if ($canEdit): ?>
            <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem" onclick="toggleAddStep(<?= $item['id'] ?>)">
              <i class="bi bi-plus-lg me-1"></i>Step
            </button>
            <div id="as-<?= $item['id'] ?>" style="display:none" class="mt-1">
              <div class="row g-1 align-items-center">
                <div class="col-md-4"><input type="text" id="sa-<?= $item['id'] ?>" class="form-control form-control-sm" placeholder="Step Action *"></div>
                <div class="col-md-3"><input type="text" id="sd-<?= $item['id'] ?>" class="form-control form-control-sm" placeholder="Test Data"></div>
                <div class="col-md-3"><input type="text" id="se-<?= $item['id'] ?>" class="form-control form-control-sm" placeholder="Expected Result"></div>
                <div class="col-md-2 d-flex gap-1">
                  <button class="btn btn-primary btn-sm flex-grow-1" onclick="saveNewStep(<?= $item['id'] ?>,<?= $plan['id'] ?>,'<?= e($csrf) ?>')">+</button>
                  <button class="btn btn-secondary btn-sm" onclick="toggleAddStep(<?= $item['id'] ?>)">x</button>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($canEdit): ?>
        <div class="d-flex gap-1 flex-shrink-0">
          <a href="<?= url('test-plans/'.$plan['id'].'/items/'.$item['id'].'/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-1"><i class="bi bi-pencil" style="font-size:.7rem"></i></a>
          <form method="POST" action="<?= url('test-plans/'.$plan['id'].'/items/'.$item['id'].'/delete') ?>" class="d-inline">
            <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="return confirm('Loeschen?')"><i class="bi bi-trash" style="font-size:.7rem"></i></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card-body text-muted small text-center py-3">Noch keine Test Cases.</div>
  <?php endif; ?>
</div>

<!-- Test Cycles -->
<div class="card">
  <div class="card-header border-secondary d-flex align-items-center gap-2">
    <span class="fw-semibold small"><i class="bi bi-arrow-repeat me-1"></i>Test Cycles <span class="badge bg-secondary"><?= count($cycles) ?></span></span>
    <?php if ($canEdit): ?>
    <button class="btn btn-outline-success btn-sm py-0 ms-auto" data-bs-toggle="modal" data-bs-target="#createCycleModal" style="font-size:.75rem"><i class="bi bi-plus-lg me-1"></i>Neuer Cycle</button>
    <?php endif; ?>
  </div>
  <?php if ($cycles): ?>
  <div class="list-group list-group-flush">
    <?php foreach ($cycles as $cycle):
      $cb = match($cycle['status']) { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
      $crs = $runsByCycle[$cycle['id']] ?? [];
      $cR = 0; $cP = 0; $cF = 0;
      foreach ($crs as $cr) { $cR += (int)$cr['rc']; $cP += (int)$cr['p']; $cF += (int)$cr['f']; }
      $cPct = $cR > 0 ? round($cP/$cR*100) : 0;
    ?>
    <div class="list-group-item bg-dark border-secondary py-3 px-3" id="cycle-<?= $cycle['id'] ?>">
      <div class="d-flex align-items-start justify-content-between gap-2">
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <a href="<?= url('test-cycles/'.$cycle['id']) ?>" class="fw-semibold text-white text-decoration-none"><?= e($cycle['name']) ?></a>
            <span class="badge bg-<?= $cb ?>" style="font-size:.65rem"><?= e($cycle['status']) ?></span>
            <?php if ($cycle['synapse_cycle_id']): ?>
            <span class="badge bg-dark border border-warning text-warning" style="font-size:.6rem"><i class="bi bi-link-45deg me-1"></i>SynapseRT <?= e($cycle['synapse_cycle_id']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($cycle['environment'] || $cycle['build']): ?>
          <div class="text-muted small mb-2">
            <?php if ($cycle['environment']): ?><i class="bi bi-display me-1"></i><?= e($cycle['environment']) ?><?php endif; ?>
            <?php if ($cycle['build']): ?> &middot; Build: <?= e($cycle['build']) ?><?php endif; ?>
          </div>
          <?php endif; ?>
          <!-- Cycle progress bar -->
          <?php if ($cR > 0): ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between" style="font-size:.7rem">
              <span class="text-muted"><?= $cP ?>/<?= $cR ?> bestanden</span>
              <span class="fw-semibold"><?= $cPct ?>%</span>
            </div>
            <div class="progress" style="height:5px">
              <div class="progress-bar bg-success" style="width:<?= $cPct ?>%"></div>
              <div class="progress-bar bg-danger" style="width:<?= $cR>0?round($cF/$cR*100):0 ?>%"></div>
            </div>
          </div>
          <?php endif; ?>
          <!-- Runs -->
          <?php if ($crs): ?>
          <div class="d-flex flex-column gap-1 ms-2">
            <?php foreach ($crs as $cr):
              $rb = match($cr['status']) { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
            ?>
            <div class="d-flex align-items-center gap-2 py-1 border-top border-secondary">
              <a href="<?= url('test-runs/'.$cr['id']) ?>" class="text-white text-decoration-none small fw-semibold flex-grow-1">
                <i class="bi bi-play-fill me-1 text-<?= $rb ?>"></i><?= e($cr['name']) ?>
              </a>
              <span class="badge bg-<?= $rb ?>" style="font-size:.6rem"><?= e($cr['status']) ?></span>
              <?php if ($cr['rc'] > 0): ?><span class="text-muted small"><?= (int)$cr['p'] ?>/<?= (int)$cr['rc'] ?> ok</span><?php endif; ?>
              <a href="<?= url('test-runs/'.$cr['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-1"><i class="bi bi-eye" style="font-size:.7rem"></i></a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="text-muted small ms-2">Noch keine Test Runs.</div>
          <?php endif; ?>
          <?php if ($canEdit): ?>
          <div class="mt-2">
            <a href="<?= url('test-runs/create?plan_id='.$plan['id'].'&cycle_id='.$cycle['id']) ?>" class="btn btn-primary btn-sm"><i class="bi bi-play me-1"></i>Test Run starten</a>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($canEdit): ?>
        <form method="POST" action="<?= url('test-plans/'.$plan['id'].'/cycles/'.$cycle['id'].'/delete') ?>">
          <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="return confirm('Cycle loeschen?')"><i class="bi bi-trash" style="font-size:.7rem"></i></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card-body text-muted small text-center py-3">Noch keine Test Cycles. <?php if ($canEdit): ?><a href="#" data-bs-toggle="modal" data-bs-target="#createCycleModal">Jetzt erstellen</a><?php endif; ?></div>
  <?php endif; ?>
</div>

<?php if ($runsWithoutCycle): ?>
<div class="card mt-3">
  <div class="card-header border-secondary fw-semibold small text-muted"><i class="bi bi-archive me-1"></i>Test Runs ohne Cycle (Legacy)</div>
  <div class="list-group list-group-flush">
    <?php foreach ($runsWithoutCycle as $r):
      $rb = match($r['status']) { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
    ?>
    <a href="<?= url('test-runs/'.$r['id']) ?>" class="list-group-item list-group-item-action bg-dark border-secondary d-flex align-items-center gap-2 py-2" style="font-size:.82rem">
      <span class="badge bg-<?= $rb ?>"><?= e($r['status']) ?></span>
      <span class="fw-semibold flex-grow-1"><?= e($r['name']) ?></span>
      <span class="text-muted small"><?= (int)$r['passed'] ?>/<?= (int)$r['result_count'] ?> ok</span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</div>
<div class="col-lg-4">
  <?php if ($canEdit): ?>
  <div class="card mb-3">
    <div class="card-header border-secondary fw-semibold small">CSV-Import</div>
    <div class="card-body">
      <form method="POST" action="<?= url('test-plans/'.$plan['id'].'/import-csv') ?>" enctype="multipart/form-data">
        <?= csrfField() ?><div class="mb-2"><input type="file" name="csv" class="form-control form-control-sm" accept=".csv"></div>
        <button class="btn btn-outline-secondary btn-sm w-100">Importieren</button>
      </form>
      <div class="text-muted small mt-1">Format: Title;Description;Erwartet;Priority</div>
    </div>
  </div>
  <?php endif; ?>
  <div class="card">
    <div class="card-header border-secondary fw-semibold small">Uebersicht</div>
    <div class="card-body py-2">
      <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Test Cases</span><span class="fw-semibold"><?= count($items) ?></span></div>
      <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Test Cycles</span><span class="fw-semibold"><?= count($cycles) ?></span></div>
      <div class="d-flex justify-content-between small mb-2"><span class="text-muted">Test Runs</span><span class="fw-semibold"><?= count($runs) ?></span></div>
      <?php if ($totR > 0): ?>
      <div class="progress mb-1" style="height:6px">
        <div class="progress-bar bg-success" style="width:<?= $totPct ?>%"></div>
        <div class="progress-bar bg-danger" style="width:<?= $totR>0?round($totF/$totR*100):0 ?>%"></div>
      </div>
      <div class="text-muted small"><?= $totPct ?>% bestanden</div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<!-- Create Cycle Modal -->
<?php if ($canEdit): ?>
<div class="modal fade" id="createCycleModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Neuer Test Cycle</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="<?= url('test-plans/'.$plan['id'].'/cycles') ?>">
      <?= csrfField() ?>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
        <div class="row g-2 mb-3">
          <div class="col"><label class="form-label small">Umgebung</label><input type="text" name="environment" class="form-control"></div>
          <div class="col"><label class="form-label small">Build</label><input type="text" name="build" class="form-control"></div>
        </div>
        <div class="mb-3"><label class="form-label small">Beschreibung</label><textarea name="description" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i>Erstellen</button>
      </div>
    </form>
  </div></div>
</div>
<!-- Edit Step Modal -->
<div class="modal fade" id="editStepModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title">Step bearbeiten</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="esStepId"><input type="hidden" id="esItemId"><input type="hidden" id="esPlanId">
      <div class="mb-3"><label class="form-label small">Step Action</label><textarea id="esAction" class="form-control" rows="3"></textarea></div>
      <div class="mb-3"><label class="form-label small">Test Data</label><input id="esData" type="text" class="form-control"></div>
      <div class="mb-3"><label class="form-label small">Expected Result</label><textarea id="esExpected" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-primary btn-sm" onclick="saveEditStep('<?= e($csrf) ?>')">Speichern</button>
    </div>
  </div></div>
</div>
<?php endif; ?>

<script>
const _csrf = '<?= e($csrf) ?>';

function synapseSyncPlan(planId, csrf) {
  const btn = event.target.closest('button');
  if (btn) { btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split me-1"></i>Sync...'; }
  fetch('<?= url("synapse/sync-plan") ?>', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf:csrf, plan_id:planId, direction:'both'}) })
  .then(r=>r.json()).then(d=>{
    if (btn) { btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat me-1"></i>Sync'; }
    const msg = d.success ? ('Sync fertig - '+(d.log||[]).length+' Aktionen.') : (d.error||'Fehler');
    if (typeof showToast==='function') showToast(msg, d.success?'success':'danger'); else alert(msg);
    if (d.success && (d.log||[]).length>0) setTimeout(()=>location.reload(),1500);
  }).catch(()=>{ if(btn){btn.disabled=false;btn.innerHTML='<i class="bi bi-arrow-repeat me-1"></i>Sync';} });
}

function toggleAddStep(itemId) {
  const el = document.getElementById('as-'+itemId);
  if (el.style.display==='none') { el.style.display=''; document.getElementById('sa-'+itemId).focus(); }
  else el.style.display='none';
}

function saveNewStep(itemId, planId, csrf) {
  const action = document.getElementById('sa-'+itemId).value.trim();
  const data   = document.getElementById('sd-'+itemId).value.trim();
  const exp    = document.getElementById('se-'+itemId).value.trim();
  if (!action && !exp) return;
  fetch('<?= url("test-plans/") ?>'+planId+'/items/'+itemId+'/steps', {
    method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf:csrf, step_action:action, test_data:data, expected_result:exp})
  }).then(r=>r.json()).then(d=>{
    if (d.success) location.reload();
    else alert(d.error||'Fehler');
  });
}

function editStep(stepId, itemId, planId) {
  const row = document.getElementById('sr-'+stepId);
  if (!row) return;
  const cells = row.querySelectorAll('td');
  document.getElementById('esStepId').value = stepId;
  document.getElementById('esItemId').value = itemId;
  document.getElementById('esPlanId').value = planId;
  document.getElementById('esAction').value   = cells[1]?.innerText?.trim() || '';
  document.getElementById('esData').value     = cells[2]?.innerText?.trim() || '';
  document.getElementById('esExpected').value = cells[3]?.innerText?.trim() || '';
  new bootstrap.Modal(document.getElementById('editStepModal')).show();
}

function saveEditStep(csrf) {
  const stepId = document.getElementById('esStepId').value;
  const itemId = document.getElementById('esItemId').value;
  const planId = document.getElementById('esPlanId').value;
  fetch('<?= url("test-plans/") ?>'+planId+'/items/'+itemId+'/steps/'+stepId+'/update', {
    method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf:csrf, step_action:document.getElementById('esAction').value, test_data:document.getElementById('esData').value, expected_result:document.getElementById('esExpected').value})
  }).then(r=>r.json()).then(d=>{
    if (d.success) { bootstrap.Modal.getInstance(document.getElementById('editStepModal')).hide(); location.reload(); }
    else alert(d.error||'Fehler');
  });
}

function delStep(stepId, itemId, planId, csrf) {
  if (!confirm('Step loeschen?')) return;
  fetch('<?= url("test-plans/") ?>'+planId+'/items/'+itemId+'/steps/'+stepId+'/delete', {
    method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf:csrf})
  }).then(r=>r.json()).then(d=>{ if (d.success) { document.getElementById('sr-'+stepId)?.remove(); } });
}
</script>
