<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-requests/templates') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Edit Template: <?= e($tpl['name']) ?></h5>
</div>

<div class="card" style="max-width:800px">
  <div class="card-body">
    <form method="POST" action="<?= url('test-requests/templates/' . $tpl['id'] . '/edit') ?>">
      <?= csrfField() ?>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Template Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required value="<?= e($tpl['name']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Product</label>
          <input type="text" name="product" class="form-control" value="<?= e($tpl['product'] ?? '') ?>" placeholder="e.g. Automower 520">
        </div>
        <div class="col-md-4">
          <label class="form-label">Project Name</label>
          <input type="text" name="project_name" class="form-control" value="<?= e($tpl['project_name'] ?? '') ?>" placeholder="e.g. Husqvarna M4">
        </div>
        <div class="col-md-4">
          <label class="form-label">Project Number</label>
          <input type="text" name="project_number" class="form-control" value="<?= e($tpl['project_number'] ?? '') ?>" placeholder="e.g. 12345">
        </div>
        <div class="col-md-4">
          <label class="form-label">Order Number</label>
          <input type="text" name="order_number" class="form-control" value="<?= e($tpl['order_number'] ?? '') ?>" placeholder="e.g. PO-98765">
        </div>
        <div class="col-md-4">
          <label class="form-label">Initiator</label>
          <input type="text" name="initiator" class="form-control" value="<?= e($tpl['initiator'] ?? '') ?>" placeholder="Name of requesting person">
        </div>
        <div class="col-md-4">
          <label class="form-label">Development Type</label>
          <select name="development_type" class="form-select">
            <option value="">— select —</option>
            <?php foreach ($projectStatuses as $ps): ?>
            <option value="<?= e($ps) ?>" <?= ($tpl['development_type'] ?? '') === $ps ? 'selected' : '' ?>><?= e($ps) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Labels <span class="text-muted small">(comma-separated)</span></label>
          <input type="text" name="labels" class="form-control" value="<?= e($tpl['labels'] ?? '') ?>" placeholder="e.g. regression, safety, EP1">
        </div>
        <div class="col-12">
          <label class="form-label">Default Description</label>
          <textarea name="description" class="form-control font-monospace" rows="8"><?= e($tpl['description'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">Save Template</button>
        <a href="<?= url('test-requests/templates') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
      </div>
    </form>
  </div>
</div>
