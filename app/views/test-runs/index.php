<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0"><i class="bi bi-play-circle me-2"></i>Test Runs</h5>
  <?php if (Auth::canEdit('testing')): ?>
  <a href="<?= url('test-runs/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Neuer Test Run</a>
  <?php endif; ?>
</div>

<?php if ($runs): ?>
<div class="table-responsive">
  <table class="table table-dark table-hover align-middle" style="font-size:.83rem">
    <thead class="text-muted" style="font-size:.72rem">
      <tr><th>Name</th><th>Cycle</th><th>Plan</th><th>Status</th><th>Ergebnisse</th><th>Erstellt</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($runs as $r):
        $badge = match($r['status']) { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
      ?>
      <tr>
        <td><a href="<?= url('test-runs/' . $r['id']) ?>" class="text-white fw-semibold text-decoration-none"><?= e($r['name']) ?></a></td>
        <td class="text-muted small"><?= $r['cycle_name'] ? e($r['cycle_name']) : '<span class="text-muted">-</span>' ?></td>
        <td class="text-muted small"><?= e($r['plan_name'] ?? '-') ?></td>
        <td><span class="badge bg-<?= $badge ?>"><?= e($r['status']) ?></span></td>
        <td class="text-muted small">
          <?php if ($r['result_count'] > 0): ?>
          <span class="text-success"><?= (int)$r['passed'] ?></span> / <?= (int)$r['result_count'] ?>
          <?php if ($r['failed'] > 0): ?><span class="text-danger ms-1"><?= (int)$r['failed'] ?> fail</span><?php endif; ?>
          <?php else: ?>-<?php endif; ?>
        </td>
        <td class="text-muted small"><?= formatDate($r['created_at'], 'd.m.Y') ?></td>
        <td class="text-end">
          <a href="<?= url('test-runs/' . $r['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-eye"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-play-circle fs-1 d-block mb-2 opacity-25"></i>
  Noch keine Test Runs.
</div>
<?php endif; ?>
