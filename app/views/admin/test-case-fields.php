<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Test Case Custom Fields</h5>
</div>
<p class="text-muted small mb-3">These fields appear on every test case. Use <code>{{variable_name}}</code> in test request templates to auto-fill from test case values.</p>
<div class="row g-4">
  <div class="col-md-5">
    <div class="card"><div class="card-header border-secondary fw-semibold small">New Field</div>
      <div class="card-body">
        <form method="POST" action="<?= url('admin/test-case-fields') ?>">
          <?= csrfField() ?><input type="hidden" name="action" value="create">
          <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
          <div class="mb-2"><label class="form-label small">Variable Name (auto-generated if empty)</label><input type="text" name="variable_name" class="form-control form-control-sm" placeholder="Automatisch aus Name"></div>
          <div class="mb-2"><label class="form-label small">Field Type</label>
            <select name="field_type" class="form-select form-select-sm">
              <option value="text">Text</option><option value="textarea">Textarea</option>
              <option value="select">Select</option><option value="number">Number</option>
            </select></div>
          <div class="mb-2"><label class="form-label small">Options (one per line, select type only)</label>
            <textarea name="options" class="form-control form-control-sm" rows="3"></textarea></div>
          <div class="mb-2"><label class="form-label small">Placeholder</label><input type="text" name="placeholder" class="form-control form-control-sm"></div>
          <div class="mb-2"><label class="form-label small">Order</label><input type="number" name="sort_order" class="form-control form-control-sm" value="0"></div>
          <button class="btn btn-primary btn-sm w-100">Create</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card"><div class="card-header border-secondary fw-semibold small">Existing Fields</div>
      <?php if (!$fields): ?><div class="card-body text-muted small text-center p-4">No fields yet.</div><?php endif; ?>
      <div class="list-group list-group-flush">
        <?php foreach ($fields as $f): ?>
        <div class="list-group-item bg-transparent border-secondary py-2 px-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <span class="fw-semibold small"><?= e($f['name']) ?></span>
              <span class="badge bg-secondary ms-1"><?= e($f['field_type']) ?></span>
              <small class="text-muted ms-1">var: <code>{{<?= e($f['variable_name']) ?>}}</code></small>
            </div>
            <form method="POST" action="<?= url('admin/test-case-fields/' . $f['id'] . '/delete') ?>" data-confirm="Delete field and all values?">
              <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
