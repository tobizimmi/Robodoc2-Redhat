<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="mb-0"><i class="bi bi-lightning-charge me-2 text-warning"></i>Sprints</h4>
    <small class="text-muted">Plan and track your work in time-boxed iterations</small>
  </div>
  <a href="<?= url('sprints/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Sprint
  </a>
</div>

<?php if (!$sprints): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-lightning-charge fs-1 d-block mb-3 opacity-25"></i>
  No sprints yet. <a href="<?= url('sprints/create') ?>">Create your first sprint</a>.
</div></div>
<?php else: ?>

<?php
$groups = ['active'=>[],'planning'=>[],'completed'=>[]];
foreach ($sprints as $s) { $groups[$s['status']][] = $s; }
$groupLabels = ['active'=>'Active','planning'=>'Planning','completed'=>'Completed'];
$groupColors = ['active'=>'success','planning'=>'info','completed'=>'secondary'];
?>

<?php foreach ($groups as $status => $list):
  if (!$list && $status === 'completed') continue; ?>
<h6 class="text-muted fw-semibold mb-2 mt-4 text-uppercase" style="font-size:.7rem;letter-spacing:.08em">
  <i class="bi bi-circle-fill text-<?= $groupColors[$status] ?> me-1" style="font-size:.5rem"></i>
  <?= $groupLabels[$status] ?> (<?= count($list) ?>)
</h6>
<?php foreach ($list as $s):
  $total = (int)$s['total_entries'];
  $done  = (int)$s['done_entries'] + (int)$s['rejected_entries'];
  $pct   = $total > 0 ? round($done/$total*100) : 0;
  $totalPts = (int)$s['total_points'];
?>
<div class="card mb-3 <?= $status==='active'?'border-success':'' ?>">
  <div class="card-body py-3">
    <div class="row align-items-center g-3">
      <div class="col-md-5">
        <div class="d-flex align-items-start gap-2">
          <div>
            <a href="<?= url('sprints/'.$s['id']) ?>" class="fw-bold text-white text-decoration-none">
              <?= e($s['name']) ?>
            </a>
            <span class="badge bg-<?= $groupColors[$status] ?> ms-2" style="font-size:.6rem"><?= $status ?></span>
            <?php if ($s['goal']): ?>
            <div class="text-muted small mt-1"><?= e($s['goal']) ?></div>
            <?php endif; ?>
            <?php if ($s['start_date'] || $s['end_date']): ?>
            <div class="text-muted small mt-1">
              <i class="bi bi-calendar3 me-1"></i>
              <?= $s['start_date'] ? formatDate($s['start_date'],'d.m.Y') : '?' ?>
              →
              <?= $s['end_date'] ? formatDate($s['end_date'],'d.m.Y') : '?' ?>
              <?php if ($s['status']==='active' && $s['end_date']): ?>
              <?php $dl=(new DateTime())->diff(new DateTime($s['end_date'])); ?>
              <span class="badge <?= $dl->invert?'bg-danger':'bg-secondary' ?> ms-1" style="font-size:.6rem">
                <?= $dl->invert ? 'Overdue '.$dl->days.'d' : $dl->days.'d left' ?>
              </span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="d-flex justify-content-between mb-1 small text-muted">
          <span><?= $done ?> / <?= $total ?> tickets done</span>
          <span><?= $pct ?>%</span>
        </div>
        <div class="progress" style="height:8px">
          <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
        </div>
        <?php if ($totalPts): ?>
        <div class="text-muted mt-1" style="font-size:.72rem"><?= $totalPts ?> story points total</div>
        <?php endif; ?>
      </div>
      <div class="col-md-3 d-flex gap-2 justify-content-end flex-wrap">
        <?php if ($s['status']==='planning'): ?>
        <form method="POST" action="<?= url('sprints/'.$s['id'].'/start') ?>">
          <?= csrfField() ?><button class="btn btn-success btn-sm"><i class="bi bi-play-fill me-1"></i>Start</button>
        </form>
        <?php elseif ($s['status']==='active'): ?>
        <form method="POST" action="<?= url('sprints/'.$s['id'].'/complete') ?>">
          <?= csrfField() ?><button class="btn btn-outline-success btn-sm"><i class="bi bi-check2-all me-1"></i>Complete</button>
        </form>
        <?php endif; ?>
        <a href="<?= url('sprints/'.$s['id']) ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-eye me-1"></i>View
        </a>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
