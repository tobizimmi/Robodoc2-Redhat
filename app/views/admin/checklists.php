<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Checklists</h5>
</div>
<div class="row g-4">
  <div class="col-md-5">
    <div class="card"><div class="card-header border-secondary fw-semibold small">New Checklist</div>
      <div class="card-body">
        <form method="POST" action="<?= url('admin/checklists') ?>">
          <?= csrfField() ?><input type="hidden" name="action" value="create">
          <div class="mb-2"><label class="form-label small">Name *</label><input type="text" name="name" class="form-control form-control-sm" required></div>
          <div class="mb-2"><label class="form-label small">Description</label><input type="text" name="description" class="form-control form-control-sm"></div>
          <div class="mb-2"><label class="form-label small">Items (one per line)</label>
            <textarea name="items" class="form-control form-control-sm" rows="6" placeholder="Check GPS&#10;Note app version&#10;Scan serial number"></textarea></div>
          <button class="btn btn-primary btn-sm w-100">Create</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card"><div class="card-header border-secondary fw-semibold small">Checklists</div>
      <?php if (!$cls): ?><div class="card-body text-muted small text-center p-4">Keine Checklists.</div><?php endif; ?>
      <div class="list-group list-group-flush">
        <?php foreach ($cls as $cl): ?>
        <div class="list-group-item bg-transparent border-secondary py-2 px-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold small"><?= e($cl['name']) ?></div>
              <?php if ($cl['items']): ?>
              <div class="text-muted small mt-1">
                <?php foreach (explode("\n", $cl['items']) as $line): ?>
                <div>• <?= e($line) ?></div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <form method="POST" action="<?= url('admin/checklists/' . $cl['id'] . '/delete') ?>" data-confirm="Delete checklist?">
              <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
