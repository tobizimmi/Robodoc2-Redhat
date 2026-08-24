<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('inventory') ?>"><i class="bi bi-arrow-left"></i></a>
    <div>
      <?php if (isset($project) && $project['id']): ?>
      <span class="fw-semibold"><?= e($project['name']) ?></span>
      <?php else: ?>
      <span class="fw-semibold text-muted">Unassigned</span>
      <?php endif; ?>
      <span class="text-muted small ms-2"><?= count($items) ?> device<?= count($items) !== 1 ? 's' : '' ?></span>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('inventory/import' . (isset($projectId) && $projectId ? '?project_id=' . $projectId : '')) ?>"
       class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-upload me-1"></i>CSV Import
    </a>
    <a href="<?= url('inventory/create') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>New Device
    </a>
  </div>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-dark table-hover mb-0 align-middle">
      <thead><tr>
        <th>Name</th><th>Serial No.</th><th>Firmware</th><th>Project</th><th>Location</th><th>Status</th><th>Log</th><th></th>
      </tr></thead>
      <tbody>
        <?php if (!$items): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No inventory items. <a href="<?= url('inventory/create') ?>">Add first device</a></td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
        <?php $sc = ['available'=>'success','in_use'=>'primary','maintenance'=>'warning','retired'=>'secondary']; ?>
        <tr>
          <td><a href="<?= url('inventory/' . $item['id']) ?>" class="text-white text-decoration-none fw-semibold"><?= e($item['name']) ?></a></td>
          <td class="small text-muted"><?= e($item['serial_number'] ?: '—') ?></td>
          <td class="small text-muted"><?= e($item['firmware_version'] ?: '—') ?></td>
          <td class="small text-muted"><?= e($item['project_name'] ?: '—') ?></td>
          <td class="small text-muted"><?= e($item['location'] ?: '—') ?></td>
          <td><span class="badge bg-<?= $sc[$item['status']] ?? 'secondary' ?>"><?= e($item['status']) ?></span></td>
          <td class="small text-muted"><?= $item['log_count'] ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= url('inventory/' . $item['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-pencil"></i></a>
              <form method="POST" action="<?= url('inventory/' . $item['id'] . '/delete') ?>" data-confirm="Delete device?">
                <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
