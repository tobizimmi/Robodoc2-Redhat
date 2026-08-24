<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Test Mowers</h5>
</div>
<div class="row g-4">
  <div class="col-md-5">
    <div class="card"><div class="card-header border-secondary fw-semibold small">Add Mower</div>
      <div class="card-body">
        <form method="POST" action="<?= url('admin/test-mowers') ?>">
          <?= csrfField() ?><input type="hidden" name="action" value="create">
          <div class="mb-2"><label class="form-label small">Label / Name <span class="text-danger">*</span></label>
            <input type="text" name="label" class="form-control form-control-sm" required placeholder="e.g. AM520 #3"></div>
          <div class="mb-2"><label class="form-label small">Serial Number</label>
            <input type="text" name="serial_number" class="form-control form-control-sm" placeholder="e.g. SN-12345678"></div>
          <div class="mb-2"><label class="form-label small">Model</label>
            <input type="text" name="model" class="form-control form-control-sm" placeholder="e.g. Automower 520"></div>
          <div class="mb-2"><label class="form-label small">Firmware Version</label>
            <input type="text" name="firmware_version" class="form-control form-control-sm" placeholder="e.g. 4.2.1"></div>
          <div class="mb-2"><label class="form-label small">Notes</label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea></div>
          <button class="btn btn-primary btn-sm w-100">Add</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card"><div class="card-header border-secondary fw-semibold small">Mowers (<?= count($mowers) ?>)</div>
      <?php if (!$mowers): ?><div class="card-body text-muted small text-center p-4">No mowers yet.</div><?php endif; ?>
      <div class="list-group list-group-flush">
        <?php foreach ($mowers as $m): ?>
        <div class="list-group-item bg-transparent border-secondary py-2 px-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <span class="fw-semibold small"><?= e($m['label']) ?></span>
              <?php if ($m['serial_number']): ?><small class="text-muted ms-2"><?= e($m['serial_number']) ?></small><?php endif; ?>
              <?php if ($m['model']): ?><span class="badge bg-secondary ms-1"><?= e($m['model']) ?></span><?php endif; ?>
              <?php if ($m['firmware_version']): ?><span class="badge bg-info ms-1">FW: <?= e($m['firmware_version']) ?></span><?php endif; ?>
              <?php if ($m['notes']): ?><div class="text-muted small mt-1"><?= e($m['notes']) ?></div><?php endif; ?>
            </div>
            <form method="POST" action="<?= url('admin/test-mowers/' . $m['id'] . '/delete') ?>" data-confirm="Delete mower?">
              <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
