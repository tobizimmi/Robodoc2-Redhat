<div class="d-flex align-items-center gap-3 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-areas') ?>"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="mb-0"><i class="bi bi-map-fill me-2 text-success"></i><?= e($area['name']) ?></h4>
    <?php if ($area['boundary_type']): ?>
    <small class="text-muted">Boundary: <?= e($area['boundary_type']) ?></small>
    <?php endif; ?>
  </div>
  <div class="ms-auto d-flex gap-2">
    <a href="<?= url('test-areas/' . $area['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-8">

    <?php if ($area['location_description']): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Location</div>
      <div class="card-body">
        <p class="mb-0 text-muted"><?= nl2br(e($area['location_description'])) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($photos)): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Photos</div>
      <div class="card-body">
        <div class="row g-2">
          <?php foreach ($photos as $p): ?>
          <div class="col-4 col-md-3">
            <div class="position-relative">
              <a href="<?= url('test-areas/' . $area['id'] . '/photos/' . $p['id']) ?>" target="_blank">
                <img src="<?= url('test-areas/' . $area['id'] . '/photos/' . $p['id'] . '/thumb') ?>"
                     class="img-fluid rounded" style="height:100px;width:100%;object-fit:cover" alt="">
              </a>
              <form method="POST" action="<?= url('test-areas/' . $area['id'] . '/photos/' . $p['id'] . '/delete') ?>"
                    class="position-absolute top-0 end-0 m-1">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger btn-sm py-0 px-1" style="font-size:.7rem"
                        onclick="return confirm('Remove photo?')"><i class="bi bi-x"></i></button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($area['obstacles'] || $area['surface_types']): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Terrain Details</div>
      <div class="card-body">
        <?php if ($area['surface_types']): ?>
        <div class="mb-2">
          <span class="text-muted small">Surface Types:</span>
          <span class="ms-2 small"><?= e($area['surface_types']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($area['obstacles']): ?>
        <div>
          <span class="text-muted small">Obstacles / Features:</span>
          <p class="mt-1 mb-0 small"><?= nl2br(e($area['obstacles'])) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($area['notes']): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Notes</div>
      <div class="card-body">
        <p class="mb-0 small"><?= nl2br(e($area['notes'])) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Linked Entries -->
    <?php if (!empty($entries)): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small d-flex align-items-center">
        <i class="bi bi-journal-text me-2"></i>Entries from this Area
        <span class="badge bg-secondary ms-2"><?= count($entries) ?></span>
      </div>
      <div class="card-body p-0">
        <?php foreach ($entries as $ent): ?>
        <a href="<?= url('entries/' . $ent['id']) ?>"
           class="d-flex align-items-center gap-3 px-3 py-2 border-bottom border-secondary text-decoration-none text-white entry-row">
          <div class="flex-grow-1 min-width-0">
            <div class="fw-semibold text-truncate" style="font-size:.875rem">
              <?= e($ent['title'] ?: substr($ent['description'], 0, 80)) ?>
            </div>
            <div class="text-muted" style="font-size:.75rem">
              <?= e(formatDate($ent['entry_date'])) ?>
              <?php if ($ent['firmware_version']): ?>
              &middot; <i class="bi bi-code-slash me-1"></i><?= e($ent['firmware_version']) ?>
              <?php endif; ?>
            </div>
          </div>
          <span class="badge bg-<?= $ent['status'] === 'finalized' ? 'success' : ($ent['status'] === 'ongoing' ? 'warning text-dark' : 'secondary') ?>"
                style="font-size:.6rem"><?= e($ent['status']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Dimensions</div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <?php if ($area['slope_max_percent'] !== null): ?>
          <dt class="col-7 text-muted">Max Slope</dt>
          <dd class="col-5"><?= e($area['slope_max_percent']) ?>%</dd>
          <?php endif; ?>
          <?php if ($area['area_sqm']): ?>
          <dt class="col-7 text-muted">Area</dt>
          <dd class="col-5"><?= number_format((float)$area['area_sqm'], 0) ?> m²</dd>
          <?php endif; ?>
          <?php if ($area['boundary_type']): ?>
          <dt class="col-7 text-muted">Boundary</dt>
          <dd class="col-5"><?= e($area['boundary_type']) ?></dd>
          <?php endif; ?>
          <?php if ($area['boundary_length_m']): ?>
          <dt class="col-7 text-muted">Boundary Length</dt>
          <dd class="col-5"><?= e($area['boundary_length_m']) ?> m</dd>
          <?php endif; ?>
        </dl>
        <?php if (!$area['slope_max_percent'] && !$area['area_sqm'] && !$area['boundary_type']): ?>
        <p class="text-muted small mb-0">No dimensions recorded.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($area['gps_lat'] && $area['gps_lon']): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">GPS Location</div>
      <div class="card-body">
        <div class="small mb-2">
          <span class="text-muted">Lat:</span> <?= e($area['gps_lat']) ?><br>
          <span class="text-muted">Lon:</span> <?= e($area['gps_lon']) ?>
        </div>
        <a href="https://maps.google.com/?q=<?= e($area['gps_lat']) ?>,<?= e($area['gps_lon']) ?>"
           target="_blank" class="btn btn-outline-secondary btn-sm w-100">
          <i class="bi bi-map me-1"></i>Open in Maps
        </a>
      </div>
    </div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex flex-column gap-2">
          <span class="text-muted small"><i class="bi bi-images me-1"></i><?= count($photos) ?> photo<?= count($photos) !== 1 ? 's' : '' ?></span>
          <span class="text-muted small"><i class="bi bi-journal-text me-1"></i><?= count($entries) ?> linked entr<?= count($entries) !== 1 ? 'ies' : 'y' ?></span>
          <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>Created <?= e(formatDate($area['created_at'])) ?></span>
        </div>
      </div>
    </div>

    <form method="POST" action="<?= url('test-areas/' . $area['id'] . '/delete') ?>"
          onsubmit="return confirm('Delete this test area? This cannot be undone.')">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-outline-danger btn-sm w-100">
        <i class="bi bi-trash me-1"></i>Delete Area
      </button>
    </form>
  </div>
</div>
