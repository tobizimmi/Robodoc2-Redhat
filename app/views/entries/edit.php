<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('entries/' . $entry['id']) ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1">Edit Entry</h5>
  <button type="submit" form="entryForm" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save</button>
</div>
<?php $attachments = Database::fetchAll('SELECT * FROM entry_attachments WHERE entry_id = ? ORDER BY created_at', [$entry['id']]); ?>
<form method="POST" action="<?= url('entries/' . $entry['id'] . '/edit') ?>" enctype="multipart/form-data" id="entryForm">
  <?= csrfField() ?>
  <?php include __DIR__ . '/_form.php'; ?>
  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary" id="submitBtn"><i class="bi bi-check-lg me-1"></i>Save</button>
    <a href="<?= url('entries/' . $entry['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
    <form method="POST" action="<?= url('entries/' . $entry['id'] . '/delete') ?>" data-confirm="Really delete this entry?" class="ms-auto">
      <?= csrfField() ?>
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
    </form>
  </div>
</form>
<script>
// Replace edit page in browser history so Back button skips it
if (window.history.replaceState) {
  window.history.replaceState(null, document.title, window.location.href);
}
</script>
<script>
document.getElementById('entryForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  const hasFiles = document.getElementById('fileInput')?.files?.length > 0
                || document.getElementById('cameraInput')?.files?.length > 0;
  if (hasFiles && btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading?';
  }
});
</script>
