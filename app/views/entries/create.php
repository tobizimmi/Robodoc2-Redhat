<?php
  // Detect if this should be a Test Result Entry
  $trTypeIds   = array_filter(array_map('intval', explode(',', appSetting('test_result_entry_type_ids',''))));
  $isTestEntry = !empty($trTypeIds) && in_array((int)($data['entry_type_id']??0), $trTypeIds);
  // Also check URL param: ?form=test-result
  if (($_GET['form'] ?? '') === 'test-result') $isTestEntry = true;
?>
<div class="mb-3 d-flex align-items-center gap-2">
  <span class="text-muted small">Entry type:</span>
  <a href="<?= url('entries/create') ?>?project_id=<?= (int)($data['project_id']??0) ?>"
     class="btn btn-sm <?= !$isTestEntry ? 'btn-primary' : 'btn-outline-secondary' ?>">
    <i class="bi bi-journal-text me-1"></i>Standard Entry
  </a>
  <a href="<?= url('entries/create') ?>?form=test-result&project_id=<?= (int)($data['project_id']??0) ?>"
     class="btn btn-sm <?= $isTestEntry ? 'btn-info' : 'btn-outline-secondary' ?>">
    <i class="bi bi-clipboard2-check me-1"></i>Test Result Entry
  </a>
</div>
<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('entries') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1">New Entry</h5>
  <button type="submit" form="entryForm" class="btn btn-primary btn-sm submit-entry-btn"><i class="bi bi-check-lg me-1"></i>Save</button>
</div>
<form method="POST" action="<?= url('entries/create') ?>" enctype="multipart/form-data" id="entryForm">
  <?= csrfField() ?>
  <?php include __DIR__ . '/_form.php'; ?>
  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary submit-entry-btn" id="submitBtn"><i class="bi bi-check-lg me-1"></i>Save</button>
    <a href="<?= url('entries') ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
  <div id="uploadProgressBox" class="alert alert-info mt-3" style="display:none">
    <div class="d-flex align-items-center gap-2">
      <span class="spinner-border spinner-border-sm"></span>
      <span id="uploadProgressText">Uploading attachments, please keep this page open?</span>
    </div>
    <div class="text-muted small mt-1">Large files (videos) can take a while on mobile connections. Do not close or refresh this page.</div>
  </div>
</form>
<script>
(function() {
  const form = document.getElementById('entryForm');
  if (!form) return;
  let submitted = false;
  form.addEventListener('submit', function(e) {
    // Hard guard against double-submit (double-tap, double-click, two buttons)
    if (submitted) { e.preventDefault(); return; }
    submitted = true;

    const hasFiles = document.getElementById('fileInput')?.files?.length > 0
                  || document.getElementById('cameraInput')?.files?.length > 0;

    // Disable ALL submit buttons immediately so neither can be tapped again
    document.querySelectorAll('.submit-entry-btn').forEach(function(btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving?';
    });

    if (hasFiles) {
      const box = document.getElementById('uploadProgressBox');
      if (box) box.style.display = '';
      const text = document.getElementById('uploadProgressText');
      if (text) text.textContent = 'Uploading attachments ? large videos can take a minute or more on mobile?';
    }
    // Let the browser submit normally (no preventDefault) ? server handles it as one request
  });
})();
</script>
