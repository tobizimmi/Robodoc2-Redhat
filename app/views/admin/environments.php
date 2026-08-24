<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Test Environments</h5>
</div>
<div class="row g-4">
  <div class="col-md-5">
    <div class="card"><div class="card-header border-secondary fw-semibold small">New Environment</div>
      <div class="card-body">
        <form method="POST" action="<?= url('admin/environments') ?>">
          <?= csrfField() ?><input type="hidden" name="action" value="create">
          <div class="mb-2"><label class="form-label small">Name *</label><input type="text" name="name" class="form-control form-control-sm" required></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">OS</label><input type="text" name="os" class="form-control form-control-sm" placeholder="iOS 17"></div>
            <div class="col-6"><label class="form-label small">Device</label><input type="text" name="device" class="form-control form-control-sm" placeholder="iPhone 14"></div>
          </div>
          <div class="mb-2"><label class="form-label small">Firmware</label><input type="text" name="firmware" class="form-control form-control-sm"></div>
          <div class="mb-2"><label class="form-label small">Beschreibung</label><textarea name="description" class="form-control form-control-sm" rows="2"></textarea></div>
          <button class="btn btn-primary btn-sm w-100">Create</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card"><div class="card-header border-secondary fw-semibold small">Environments</div>
      <?php if (!$envs): ?><div class="card-body text-muted small text-center p-4">Keine Environments.</div><?php endif; ?>
      <div class="list-group list-group-flush">
        <?php foreach ($envs as $env): ?>
        <div class="list-group-item bg-transparent border-secondary py-2 px-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold small"><?= e($env['name']) ?></div>
              <div class="text-muted small"><?= e($env['os']) ?> <?= e($env['device']) ?></div>
            </div>
            <form method="POST" action="<?= url('admin/environments/' . $env['id'] . '/delete') ?>" data-confirm="Delete environment?">
              <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
