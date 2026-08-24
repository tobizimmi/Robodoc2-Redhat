<?php
$csrf    = Auth::csrfToken();
$canEdit = Auth::canEdit('testing');

// Compute totals
$totR = 0; $totP = 0; $totF = 0; $totPend = 0;
foreach ($runs as $r) {
    $totR    += (int)$r['result_count'];
    $totP    += (int)$r['passed'];
    $totF    += (int)$r['failed'];
    $totPend += (int)$r['pending'];
}
$pct = $totR > 0 ? round($totP / $totR * 100) : 0;
$cb = match($cycle['status']??'planned') { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
?>

<div class="d-flex align-items-start justify-content-between mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= url('test-plans/' . $cycle['plan_id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5 class="mb-0 fw-bold"><?= e($cycle['name']) ?></h5>
      <small class="text-muted">
        <a href="<?= url('test-plans/' . $cycle['plan_id']) ?>" class="text-muted"><?= e($cycle['plan_name']) ?></a>
        &middot; <span class="badge bg-<?= $cb ?>"><?= e($cycle['status']??'planned') ?></span>
        <?php if ($cycle['environment']): ?>&middot; <i class="bi bi-display me-1"></i><?= e($cycle['environment']) ?><?php endif; ?>
        <?php if ($cycle['build']): ?>&middot; Build: <?= e($cycle['build']) ?><?php endif; ?>
      </small>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($canEdit): ?>
    <a href="<?= url('test-cycles/' . $cycle['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Bearbeiten</a>
    <a href="<?= url('test-runs/create?plan_id=' . $cycle['plan_id'] . '&cycle_id=' . $cycle['id']) ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-play me-1"></i>Neuer Test Run
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Progress -->
<?php if ($totR > 0): ?>
<div class="card mb-4">
  <div class="card-body py-2">
    <div class="d-flex align-items-center gap-3">
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between small mb-1">
          <span class="text-muted fw-semibold">Fortschritt</span>
          <span><?= $totP ?>/<?= $totR ?> &middot; <strong><?= $pct ?>%</strong></span>
        </div>
        <div class="progress" style="height:8px">
          <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
          <div class="progress-bar bg-danger" style="width:<?= $totR>0?round($totF/$totR*100):0 ?>%"></div>
        </div>
      </div>
      <div class="d-flex gap-3 text-center flex-shrink-0" style="font-size:.75rem">
        <div><div class="fw-bold text-success"><?= $totP ?></div><div class="text-muted">ok</div></div>
        <div><div class="fw-bold text-danger"><?= $totF ?></div><div class="text-muted">fail</div></div>
        <div><div class="fw-bold text-warning"><?= $totPend ?></div><div class="text-muted">offen</div></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Test Runs -->
<div class="card">
  <div class="card-header border-secondary d-flex align-items-center gap-2">
    <span class="fw-semibold small"><i class="bi bi-play-circle me-1"></i>Test Runs <span class="badge bg-secondary"><?= count($runs) ?></span></span>
    <?php if ($canEdit): ?>
    <a href="<?= url('test-runs/create?plan_id='.$cycle['plan_id'].'&cycle_id='.$cycle['id']) ?>" class="btn btn-outline-success btn-sm py-0 ms-auto"><i class="bi bi-plus-lg me-1"></i>Neuer Run</a>
    <?php endif; ?>
  </div>
  <?php if ($runs): ?>
  <div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0" style="font-size:.83rem">
      <thead class="text-muted" style="font-size:.72rem">
        <tr><th>Name</th><th>Status</th><th>Ergebnisse</th><th>Fortschritt</th><th>Erstellt</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($runs as $r):
          $rb = match($r['status']) { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
          $rpct = (int)$r['result_count'] > 0 ? round((int)$r['passed'] / (int)$r['result_count'] * 100) : 0;
        ?>
        <tr>
          <td><a href="<?= url('test-runs/'.$r['id']) ?>" class="text-white fw-semibold text-decoration-none"><?= e($r['name']) ?></a></td>
          <td><span class="badge bg-<?= $rb ?>"><?= e($r['status']) ?></span></td>
          <td class="text-muted small">
            <span class="text-success"><?= (int)$r['passed'] ?></span> /
            <span class="text-danger"><?= (int)$r['failed'] ?></span> /
            <?= (int)$r['result_count'] ?> total
          </td>
          <td style="min-width:80px">
            <?php if ($r['result_count'] > 0): ?>
            <div class="progress" style="height:5px">
              <div class="progress-bar bg-success" style="width:<?= $rpct ?>%"></div>
            </div>
            <span class="text-muted" style="font-size:.7rem"><?= $rpct ?>%</span>
            <?php endif; ?>
          </td>
          <td class="text-muted small"><?= formatDate($r['created_at'],'d.m.Y') ?></td>
          <td>
            <a href="<?= url('test-runs/'.$r['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-eye"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="card-body text-muted small text-center py-4">
    <i class="bi bi-play-circle fs-2 d-block mb-2 opacity-25"></i>
    Noch keine Test Runs in diesem Cycle.
    <?php if ($canEdit): ?>
    <div class="mt-2"><a href="<?= url('test-runs/create?plan_id='.$cycle['plan_id'].'&cycle_id='.$cycle['id']) ?>" class="btn btn-primary btn-sm"><i class="bi bi-play me-1"></i>Test Run starten</a></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Synapse info -->
<?php if ($cycle['synapse_cycle_id']): ?>
<div class="card mt-3">
  <div class="card-header border-secondary fw-semibold small">SynapseRT Info</div>
  <div class="card-body py-2">
    <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Cycle ID</span><span class="badge bg-dark border border-warning text-warning"><?= e($cycle['synapse_cycle_id']) ?></span></div>
    <div class="d-flex justify-content-between small"><span class="text-muted">Plan Key</span><span class="text-muted"><?= e($cycle['synapse_plan_key']??'') ?></span></div>
  </div>
</div>
<?php endif; ?>
