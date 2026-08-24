<?php
$projectStatuses = json_decode(appSetting('project_statuses', '["Prototype","EP0","EP1","EP2","MP","SOP"]'), true) ?: [];
?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-requests') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1">Test Request Templates</h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newTemplateForm">
    <i class="bi bi-plus-lg me-1"></i>New Template
  </button>
</div>

<div class="collapse mb-4" id="newTemplateForm">
  <div class="card">
    <div class="card-header border-secondary fw-semibold small">New Template</div>
    <div class="card-body">
      <form method="POST" action="<?= url('test-requests/templates/create') ?>">
        <?= csrfField() ?>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Template Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Safety Test Template">
          </div>
          <div class="col-md-4">
            <label class="form-label">Product</label>
            <input type="text" name="product" class="form-control" placeholder="e.g. Automower 520">
          </div>
          <div class="col-md-4">
            <label class="form-label">Project Name</label>
            <input type="text" name="project_name" class="form-control" placeholder="e.g. Husqvarna M4">
          </div>
          <div class="col-md-4">
            <label class="form-label">Project Number</label>
            <input type="text" name="project_number" class="form-control" placeholder="e.g. 12345">
          </div>
          <div class="col-md-4">
            <label class="form-label">Order Number</label>
            <input type="text" name="order_number" class="form-control" placeholder="e.g. PO-98765">
          </div>
          <div class="col-md-4">
            <label class="form-label">Initiator</label>
            <input type="text" name="initiator" class="form-control" placeholder="Name of requesting person">
          </div>
          <div class="col-md-4">
            <label class="form-label">Development Type</label>
            <select name="development_type" class="form-select">
              <option value="">— select —</option>
              <?php foreach ($projectStatuses as $ps): ?>
              <option value="<?= e($ps) ?>"><?= e($ps) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Labels <span class="text-muted small">(comma-separated)</span></label>
            <input type="text" name="labels" class="form-control" placeholder="e.g. regression, safety, EP1">
          </div>
          <div class="col-12">
            <label class="form-label">Default Description</label>
            <textarea name="description" class="form-control font-monospace" rows="6"
                      placeholder="Default description text to fill in when this template is loaded…"></textarea>
          </div>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-primary btn-sm">Create Template</button>
          <button type="button" class="btn btn-outline-secondary btn-sm ms-2" data-bs-toggle="collapse" data-bs-target="#newTemplateForm">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if (!$templates): ?>
<div class="text-muted text-center py-5">No templates yet.</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($templates as $t): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title fw-semibold"><?= e($t['name']) ?></h6>
        <div class="small text-muted mb-2 row g-1">
          <?php if ($t['product']): ?>
          <div class="col-auto"><span class="badge bg-secondary"><?= e($t['product']) ?></span></div>
          <?php endif; ?>
          <?php if ($t['development_type']): ?>
          <div class="col-auto"><span class="badge bg-info text-dark"><?= e($t['development_type']) ?></span></div>
          <?php endif; ?>
        </div>
        <?php if ($t['project_name'] || $t['project_number']): ?>
        <div class="small text-muted mb-1">
          <i class="bi bi-folder me-1"></i><?= e(trim(($t['project_name'] ?? '') . ($t['project_number'] ? ' (' . $t['project_number'] . ')' : ''))) ?>
        </div>
        <?php endif; ?>
        <?php if ($t['order_number']): ?>
        <div class="small text-muted mb-1"><i class="bi bi-receipt me-1"></i><?= e($t['order_number']) ?></div>
        <?php endif; ?>
        <?php if ($t['initiator']): ?>
        <div class="small text-muted mb-1"><i class="bi bi-person me-1"></i><?= e($t['initiator']) ?></div>
        <?php endif; ?>
        <?php if ($t['labels']): ?>
        <div class="mb-2 mt-1">
          <?php foreach (array_filter(array_map('trim', explode(',', $t['labels']))) as $lbl): ?>
          <span class="badge bg-secondary me-1"><?= e($lbl) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($t['description']): ?>
        <p class="small text-muted mb-0 mt-2" style="white-space:pre-wrap;max-height:80px;overflow:hidden"><?= e(substr($t['description'], 0, 200)) ?><?= strlen($t['description']) > 200 ? '…' : '' ?></p>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-transparent d-flex gap-2">
        <a href="<?= url('test-requests/templates/' . $t['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm flex-grow-1">
          <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <form method="POST" action="<?= url('test-requests/templates/' . $t['id'] . '/delete') ?>"
              data-confirm="Delete template '<?= e($t['name']) ?>'?">
          <?= csrfField() ?>
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
