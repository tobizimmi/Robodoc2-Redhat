<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-archive me-2 text-muted"></i>Inventar — Projekt wählen</h5>
  <div class="d-flex gap-2">
    <a href="<?= url('inventory/import') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-upload me-1"></i>CSV Import
    </a>
    <a href="<?= url('inventory/create') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>New Device
    </a>
  </div>
</div>

<div class="row g-3">
  <?php foreach ($projects as $p): ?>
  <div class="col-sm-6 col-md-4 col-xl-3">
    <a href="<?= url('inventory?project_id=' . $p['id']) ?>" class="card card-hover text-decoration-none text-white h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="rounded-circle flex-shrink-0" style="width:12px;height:12px;background:<?= e($p['color'] ?? '#6c757d') ?>"></span>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-semibold text-truncate"><?= e($p['name']) ?></div>
          <div class="text-muted small"><?= $p['item_count'] ?> device<?= $p['item_count'] !== 1 ? 's' : '' ?></div>
        </div>
        <i class="bi bi-chevron-right text-muted"></i>
      </div>
    </a>
  </div>
  <?php endforeach; ?>

  <?php if ($unassignedCount > 0): ?>
  <div class="col-sm-6 col-md-4 col-xl-3">
    <a href="<?= url('inventory?project_id=0') ?>" class="card card-hover text-decoration-none text-white h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="rounded-circle flex-shrink-0" style="width:12px;height:12px;background:#6c757d"></span>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-semibold text-truncate text-muted">Unassigned</div>
          <div class="text-muted small"><?= $unassignedCount ?> device<?= $unassignedCount !== 1 ? 's' : '' ?></div>
        </div>
        <i class="bi bi-chevron-right text-muted"></i>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (!$projects && !$unassignedCount): ?>
  <div class="col-12">
    <div class="card"><div class="card-body text-center text-muted py-5">
      <i class="bi bi-archive display-4 d-block mb-3 opacity-25"></i>
      <p>No inventory items yet.</p>
      <a href="<?= url('inventory/create') ?>" class="btn btn-primary">Add first device</a>
    </div></div>
  </div>
  <?php endif; ?>
</div>
