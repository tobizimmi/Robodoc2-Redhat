<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-requests/' . $request['id']) ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1">Edit Test Request #<?= $request['id'] ?></h5>
  <button type="submit" form="trForm" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save</button>
</div>

<form method="POST" action="<?= url('test-requests/' . $request['id'] . '/edit') ?>" enctype="multipart/form-data" id="trForm">
  <?= csrfField() ?>
  <?php include __DIR__ . '/_form.php'; ?>
  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
    <a href="<?= url('test-requests/' . $request['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
    <form method="POST" action="<?= url('test-requests/' . $request['id'] . '/delete') ?>" data-confirm="Really delete this test request?" class="ms-auto">
      <?= csrfField() ?>
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
    </form>
  </div>
</form>

<?php if ($attachments): ?>
<div class="mt-4">
  <h6 class="text-muted">Existing Attachments</h6>
  <div class="list-group list-group-flush">
    <?php foreach ($attachments as $att): ?>
    <div class="list-group-item bg-transparent d-flex align-items-center gap-3 py-2">
      <i class="bi bi-paperclip text-muted"></i>
      <span class="flex-grow-1 small"><?= e($att['display_name'] ?: $att['original_name']) ?></span>
      <span class="text-muted small"><?= formatFileSize((int)($att['file_size'] ?? 0)) ?></span>
      <form method="POST" action="<?= url('test-requests/' . $request['id'] . '/attachments/' . $att['id'] . '/delete') ?>"
            data-confirm="Delete this attachment?">
        <?= csrfField() ?>
        <button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
