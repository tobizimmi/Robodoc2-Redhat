<?php
$statusOptions = ['draft','submitted','approved','rejected','closed'];
$r = $request ?? null;
$isCreate = $r === null;
?>

<!-- Template loader (create mode only) -->
<?php if ($isCreate && $templates): ?>
<div class="mb-3">
  <label class="form-label small text-muted">Load from Template</label>
  <div class="input-group input-group-sm">
    <select id="templateSelect" class="form-select form-select-sm">
      <option value="">— select template —</option>
      <?php foreach ($templates as $t): ?>
      <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadTemplate()">Load</button>
  </div>
</div>
<hr class="border-secondary">
<?php endif; ?>

<div class="row g-3">
  <div class="col-12">
    <label class="form-label">Summary <span class="text-danger">*</span></label>
    <input type="text" name="summary" class="form-control" required
           value="<?= e($r['summary'] ?? '') ?>" placeholder="Brief title of the test request">
  </div>

  <div class="col-md-4">
    <label class="form-label">Product</label>
    <input type="text" name="product" class="form-control"
           value="<?= e($r['product'] ?? '') ?>" placeholder="e.g. Automower 520">
  </div>
  <div class="col-md-4">
    <label class="form-label">Project Name</label>
    <input type="text" name="project_name" class="form-control"
           value="<?= e($r['project_name'] ?? '') ?>" placeholder="e.g. Husqvarna M4">
  </div>
  <div class="col-md-4">
    <label class="form-label">Project Number</label>
    <input type="text" name="project_number" class="form-control"
           value="<?= e($r['project_number'] ?? '') ?>" placeholder="e.g. 12345">
  </div>

  <div class="col-md-4">
    <label class="form-label">Order Number</label>
    <input type="text" name="order_number" class="form-control"
           value="<?= e($r['order_number'] ?? '') ?>" placeholder="e.g. PO-98765">
  </div>
  <div class="col-md-4">
    <label class="form-label">Initiator</label>
    <input type="text" name="initiator" class="form-control"
           value="<?= e($r['initiator'] ?? '') ?>" placeholder="Name of requesting person">
  </div>
  <div class="col-md-4">
    <label class="form-label">Development Type</label>
    <select name="development_type" class="form-select">
      <option value="">— select —</option>
      <?php foreach ($projectStatuses as $ps): ?>
      <option value="<?= e($ps) ?>" <?= ($r['development_type'] ?? '') === $ps ? 'selected' : '' ?>><?= e($ps) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Labels <span class="text-muted small">(comma-separated)</span></label>
    <input type="text" name="labels" class="form-control"
           value="<?= e($r['labels'] ?? '') ?>" placeholder="e.g. regression, safety, EP1">
  </div>
  <div class="col-md-6">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      <?php foreach ($statusOptions as $s): ?>
      <option value="<?= $s ?>" <?= ($r['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-12">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control font-monospace" rows="10"
              placeholder="Detailed description. You can reference Test Plans and Test Runs here."><?= e($r['description'] ?? '') ?></textarea>
    <div class="form-text">You can link Test Plans with <code>#TP-{id}</code> and Test Runs with <code>#TR-{id}</code>.</div>
  </div>

  <!-- Attachments -->
  <div class="col-12">
    <label class="form-label">Attachments</label>
    <input type="file" name="attachments[]" class="form-control" multiple
           accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.log">
  </div>
</div>

<?php if ($isCreate): ?>
<div class="mt-3 form-check">
  <input type="checkbox" class="form-check-input" name="push_to_jira" id="pushToJira" value="1">
  <label class="form-check-label" for="pushToJira">
    <strong>Create Jira issue</strong>
    <span class="text-muted small">(issue type: Request, project: <?= e(appSetting('jira_test_request_project') ?: 'not configured') ?>)</span>
  </label>
</div>
<?php endif; ?>

<?php if ($isCreate && $templates): ?>
<script>
function loadTemplate() {
  const id = document.getElementById('templateSelect').value;
  if (!id) return;
  fetch('<?= url('test-requests/templates/') ?>' + id + '/load')
    .then(r => r.json())
    .then(t => {
      const set = (name, val) => {
        const el = document.querySelector('[name="' + name + '"]');
        if (el && val) el.value = val;
      };
      set('description',      t.description);
      set('labels',           t.labels);
      set('project_name',     t.project_name);
      set('project_number',   t.project_number);
      set('order_number',     t.order_number);
      set('product',          t.product);
      set('initiator',        t.initiator);
      set('development_type', t.development_type);
    })
    .catch(() => alert('Failed to load template.'));
}
</script>
<?php endif; ?>
