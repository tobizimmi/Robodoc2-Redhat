<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-0">Test Areas</h4>
    <small class="text-muted">Physical test locations with terrain info</small>
  </div>
  <a href="<?= url('test-areas/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Area
  </a>
</div>

<?php if (!$areas): ?>
<div class="card"><div class="card-body text-muted text-center py-5">
  <i class="bi bi-map fs-1 d-block mb-2 opacity-25"></i>
  No test areas yet. Create one to link entries and sessions to physical locations.
</div></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($areas as $a): ?>
<div class="col-md-6 col-xl-4">
  <div class="card h-100">
    <div class="card-body">
      <div class="d-flex align-items-start justify-content-between mb-2">
        <h6 class="mb-0 text-truncate"><i class="bi bi-map-fill me-2 text-success"></i><?= e($a['name']) ?></h6>
        <div class="dropdown">
          <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= url('test-areas/' . $a['id']) ?>"><i class="bi bi-eye me-2"></i>View</a></li>
            <li><a class="dropdown-item" href="<?= url('test-areas/' . $a['id'] . '/edit') ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="POST" action="<?= url('test-areas/' . $a['id'] . '/delete') ?>" onsubmit="return confirm('Delete this area?')">
                <?= csrfField() ?>
                <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
      <?php if ($a['location_description']): ?>
      <p class="text-muted small mb-2"><?= e(substr($a['location_description'], 0, 100)) ?></p>
      <?php endif; ?>
      <div class="d-flex flex-wrap gap-2 mt-auto">
        <?php if ($a['slope_max_percent'] !== null): ?>
        <span class="badge bg-warning text-dark"><i class="bi bi-chevron-bar-up me-1"></i><?= $a['slope_max_percent'] ?>% slope</span>
        <?php endif; ?>
        <?php if ($a['area_sqm']): ?>
        <span class="badge bg-secondary"><?= number_format((float)$a['area_sqm'], 0) ?> m²</span>
        <?php endif; ?>
        <?php if ($a['entry_count']): ?>
        <span class="badge bg-info text-dark"><i class="bi bi-journal-text me-1"></i><?= $a['entry_count'] ?></span>
        <?php endif; ?>
        <?php if ($a['photo_count']): ?>
        <span class="badge bg-secondary"><i class="bi bi-images me-1"></i><?= $a['photo_count'] ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-footer border-secondary py-2">
      <a href="<?= url('test-areas/' . $a['id']) ?>" class="btn btn-sm btn-outline-secondary w-100">View Details</a>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
