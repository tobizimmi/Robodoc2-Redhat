<?php
$csrf    = Auth::csrfToken();
$canEdit = Auth::canEdit('testing');
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0"><i class="bi bi-arrow-repeat me-2"></i>Test Cycles</h5>
  <?php if ($canEdit): ?>
  <a href="<?= url('test-plans') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-clipboard-check me-1"></i>Test Plans
  </a>
  <?php endif; ?>
</div>

<?php if (!$plans): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-arrow-repeat fs-1 d-block mb-2 opacity-25"></i>
  Noch keine Test Planes. <a href="<?= url('test-plans/create') ?>">Ersten Plan erstellen</a>
</div>
<?php else: ?>

<?php foreach ($plans as $plan):
  $cycles = $cyclesByPlan[$plan['id']] ?? [];
  if (!$cycles && !$legacyRuns) continue; // skip plans with no cycles
  if (!$cycles) continue;
  // Plan totals
  $planR = 0; $planP = 0; $planF = 0;
  foreach ($cycles as $cyc) { $planR += (int)$cyc['result_count']; $planP += (int)$cyc['passed']; $planF += (int)$cyc['failed']; }
  $planPct = $planR > 0 ? round($planP/$planR*100) : 0;
?>
<div class="card mb-4">
  <!-- Plan header -->
  <div class="card-header border-secondary d-flex align-items-center gap-3 py-2">
    <div class="flex-grow-1">
      <a href="<?= url('test-plans/'.$plan['id']) ?>" class="fw-semibold text-white text-decoration-none">
        <i class="bi bi-clipboard-check me-2 text-primary"></i><?= e($plan['name']) ?>
      </a>
      <span class="text-muted small ms-2"><?= e($plan['project_name']??'') ?></span>
    </div>
    <?php if ($planR > 0): ?>
    <div class="d-flex align-items-center gap-2 flex-shrink-0">
      <div style="width:100px">
        <div class="progress" style="height:5px">
          <div class="progress-bar bg-success" style="width:<?= $planPct ?>%"></div>
          <div class="progress-bar bg-danger" style="width:<?= $planR>0?round($planF/$planR*100):0 ?>%"></div>
        </div>
      </div>
      <span class="text-muted small"><?= $planPct ?>%</span>
    </div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <a href="<?= url('test-runs/create?plan_id='.$plan['id']) ?>" class="btn btn-outline-success btn-sm py-0">
      <i class="bi bi-plus-lg me-1"></i>Neuer Cycle
    </a>
    <?php endif; ?>
  </div>

  <!-- Cycles -->
  <?php foreach ($cycles as $cyc):
    $cb = match($cyc['status']??'planned') { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
    $runs = $runsByCycle[$cyc['id']] ?? [];
    $cR = (int)$cyc['result_count']; $cP = (int)$cyc['passed']; $cF = (int)$cyc['failed'];
    $cPct = $cR > 0 ? round($cP/$cR*100) : 0;
  ?>
  <div class="border-bottom border-secondary">
    <!-- Cycle row -->
    <div class="d-flex align-items-center gap-3 px-3 py-2" style="background:rgba(255,255,255,.02)">
      <div class="flex-grow-1 d-flex align-items-center gap-2 flex-wrap">
        <i class="bi bi-arrow-repeat text-muted"></i>
        <a href="<?= url('test-cycles/'.$cyc['id']) ?>" class="fw-semibold text-white text-decoration-none">
          <?= e($cyc['name']) ?>
        </a>
        <span class="badge bg-<?= $cb ?>" style="font-size:.65rem"><?= e($cyc['status']??'planned') ?></span>
        <?php if ($cyc['environment']): ?>
        <span class="text-muted small"><i class="bi bi-display me-1"></i><?= e($cyc['environment']) ?></span>
        <?php endif; ?>
        <?php if ($cyc['build']): ?>
        <span class="text-muted small">Build: <?= e($cyc['build']) ?></span>
        <?php endif; ?>
      </div>
      <!-- Cycle progress -->
      <?php if ($cR > 0): ?>
      <div class="d-flex align-items-center gap-2 flex-shrink-0" style="font-size:.75rem">
        <span class="text-success"><?= $cP ?> ok</span>
        <span class="text-danger"><?= $cF ?> fail</span>
        <span class="text-muted"><?= $cR-$cP-$cF ?> offen</span>
        <div style="width:80px">
          <div class="progress" style="height:5px">
            <div class="progress-bar bg-success" style="width:<?= $cPct ?>%"></div>
            <div class="progress-bar bg-danger" style="width:<?= $cR>0?round($cF/$cR*100):0 ?>%"></div>
          </div>
        </div>
        <span class="fw-semibold"><?= $cPct ?>%</span>
      </div>
      <?php endif; ?>
      <!-- Cycle actions -->
      <div class="d-flex gap-1 flex-shrink-0">
        <?php if ($canEdit): ?>
        <a href="<?= url('test-runs/create?plan_id='.$plan['id'].'&cycle_id='.$cyc['id']) ?>"
           class="btn btn-primary btn-sm py-0 px-2" title="Neuer Test Run">
          <i class="bi bi-play-fill"></i>
        </a>
        <a href="<?= url('test-cycles/'.$cyc['id'].'/edit') ?>"
           class="btn btn-outline-secondary btn-sm py-0 px-2" title="Bearbeiten">
          <i class="bi bi-pencil" style="font-size:.75rem"></i>
        </a>
        <?php endif; ?>
        <a href="<?= url('test-cycles/'.$cyc['id']) ?>"
           class="btn btn-outline-secondary btn-sm py-0 px-2" title="Oeffnen">
          <i class="bi bi-chevron-right" style="font-size:.75rem"></i>
        </a>
      </div>
    </div>

    <!-- Test Runs under this cycle -->
    <?php if ($runs): ?>
    <div class="px-4 pb-1">
      <?php foreach ($runs as $run):
        $rb = match($run['status']) { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
        $rPct = (int)$run['rc'] > 0 ? round((int)$run['p']/(int)$run['rc']*100) : 0;
      ?>
      <div class="d-flex align-items-center gap-3 py-1 border-top border-secondary" style="font-size:.82rem">
        <i class="bi bi-play-fill text-<?= $rb ?>" style="font-size:.7rem"></i>
        <a href="<?= url('test-runs/'.$run['id']) ?>" class="text-white text-decoration-none flex-grow-1">
          <?= e($run['name']) ?>
        </a>
        <span class="badge bg-<?= $rb ?>" style="font-size:.6rem"><?= e($run['status']) ?></span>
        <?php if ($run['rc'] > 0): ?>
        <span class="text-muted small"><?= (int)$run['p'] ?>/<?= (int)$run['rc'] ?> ok</span>
        <div style="width:60px">
          <div class="progress" style="height:4px">
            <div class="progress-bar bg-success" style="width:<?= $rPct ?>%"></div>
          </div>
        </div>
        <?php endif; ?>
        <a href="<?= url('test-runs/'.$run['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-1">
          <i class="bi bi-eye" style="font-size:.7rem"></i>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="px-4 py-1 text-muted small border-top border-secondary">
      Noch keine Test Runs.
      <?php if ($canEdit): ?>
      <a href="<?= url('test-runs/create?plan_id='.$plan['id'].'&cycle_id='.$cyc['id']) ?>">Jetzt starten</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if (!$cycles && $canEdit): ?>
  <div class="card-body text-muted small text-center py-3">
    Noch keine Cycles.
    <a href="<?= url('test-plans/'.$plan['id']) ?>">Im Test Plan erstellen</a>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Legacy runs without cycle -->
<?php if ($legacyRuns): ?>
<div class="card mt-2">
  <div class="card-header border-secondary d-flex align-items-center gap-2">
    <span class="fw-semibold small text-muted"><i class="bi bi-archive me-1"></i>Test Runs ohne Cycle</span>
    <span class="badge bg-secondary"><?= count($legacyRuns) ?></span>
    <span class="text-muted small ms-2">Diese Runs wurden vor der Cycle-Einfuehrung erstellt.</span>
  </div>
  <div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0" style="font-size:.82rem">
      <thead class="text-muted" style="font-size:.72rem">
        <tr><th>Name</th><th>Plan</th><th>Status</th><th>Ergebnisse</th><th>Cycle zuweisen</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($legacyRuns as $r):
          $rb = match($r['status']) { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
        ?>
        <tr>
          <td><a href="<?= url('test-runs/'.$r['id']) ?>" class="text-white text-decoration-none fw-semibold"><?= e($r['name']) ?></a></td>
          <td class="text-muted small"><?= e($r['plan_name']??'-') ?></td>
          <td><span class="badge bg-<?= $rb ?>"><?= e($r['status']) ?></span></td>
          <td class="text-muted small">
            <?php if ($r['rc'] > 0): ?>
            <span class="text-success"><?= (int)$r['p'] ?></span>/<span class="text-muted"><?= (int)$r['rc'] ?></span>
            <?php else: ?>-<?php endif; ?>
          </td>
          <?php if ($canEdit): ?>
          <td>
            <div class="d-flex gap-1 align-items-center">
              <select class="form-select form-select-sm" id="cyc-sel-<?= $r['id'] ?>" style="max-width:180px">
                <option value="">-- kein Cycle --</option>
                <?php
                  $runPlan = Database::fetchOne('SELECT id FROM test_plans WHERE id=?', [$r['test_plan_id'] ?? 0]);
                  $availCycles = $runPlan
                    ? Database::fetchAll('SELECT id, name FROM test_cycles WHERE test_plan_id=? ORDER BY created_at DESC', [$r['test_plan_id']])
                    : [];
                  foreach ($availCycles as $ac):
                ?>
                <option value="<?= $ac['id'] ?>"><?= e($ac['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-success btn-sm py-0 px-2"
                      onclick="assignCycle(<?= $r['id'] ?>,'<?= e($csrf) ?>')"
                      title="Zuweisen">
                <i class="bi bi-check-lg"></i>
              </button>
            </div>
          </td>
          <?php else: ?><td>-</td><?php endif; ?>
          <td>
            <a href="<?= url('test-runs/'.$r['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-eye"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
function assignCycle(runId, csrf) {
  const sel = document.getElementById('cyc-sel-' + runId);
  const cycleId = sel ? sel.value : '';
  fetch('<?= url("test-runs/") ?>' + runId + '/assign-cycle', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, cycle_id: cycleId})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      if (typeof showToast === 'function')
        showToast(cycleId ? ('Dem Cycle "' + d.cycle_name + '" zugewiesen.') : 'Cycle-Zuweisung entfernt.', 'success');
      setTimeout(() => location.reload(), 1000);
    } else alert(d.error || 'Fehler');
  });
}
</script>
