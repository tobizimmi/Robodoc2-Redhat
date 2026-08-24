<?php
$fromTestCase = $fromTestCase ?? null;
$prefill      = $prefill ?? [];
$customFields = $customFields ?? [];
$cfValues     = $cfValues ?? [];
$formAction   = $fromTestCase
    ? url('test-requests/from-test-case/' . $fromTestCase['id'])
    : url('test-requests/create');
?>
<div class="mb-4 d-flex align-items-center gap-2">
  <?php if ($fromTestCase): ?>
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-plans/' . ($fromTestCase['test_plan_id'] ?? '')) ?>"><i class="bi bi-arrow-left"></i></a>
  <?php else: ?>
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-requests') ?>"><i class="bi bi-arrow-left"></i></a>
  <?php endif; ?>
  <h5 class="mb-0 flex-grow-1"><?= $fromTestCase ? 'Test Request from: ' . e($fromTestCase['title']) : 'New Test Request' ?></h5>
  <button type="submit" form="trForm" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save</button>
</div>

<?php if ($fromTestCase && $customFields): ?>
<div class="card mb-3 border-info">
  <div class="card-header border-info fw-semibold small text-info"><i class="bi bi-clipboard-check me-1"></i>Test Case Fields (for variable substitution)</div>
  <div class="card-body p-2">
    <div class="row g-2">
      <?php foreach ($customFields as $cf): ?>
      <?php $val = $cfValues[$cf['id']] ?? ''; ?>
      <div class="col-md-4 small"><strong><?= e($cf['name']) ?>:</strong> <?= e($val ?: '—') ?> <span class="text-muted">({{<?= e($cf['variable_name']) ?>}})</span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data" id="trForm">
  <?= csrfField() ?>
  <?php $request = $prefill ?: null; include __DIR__ . '/_form.php'; ?>
  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
    <a href="<?= url('test-requests') ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
