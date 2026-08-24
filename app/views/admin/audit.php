<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Audit Log (letzte 200)</h5>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-dark table-hover table-sm mb-0">
      <thead><tr><th>Time</th><th>Users</th><th>Action</th><th>Resource</th><th>Details</th></tr></thead>
      <tbody>
        <?php if (!$logs): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No entries yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td class="text-muted small text-nowrap"><?= formatDateTime($l['created_at']) ?></td>
          <td class="small"><?= e($l['user_name'] ?? 'System') ?></td>
          <td><span class="badge bg-secondary"><?= e($l['action']) ?></span></td>
          <td class="small text-muted"><?= e($l['resource_type']) ?> <?= $l['resource_id'] ? '#' . $l['resource_id'] : '' ?></td>
          <td class="small text-muted text-truncate" style="max-width:200px"><?= e($l['data']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
