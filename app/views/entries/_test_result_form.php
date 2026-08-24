<?php
/**
 * Test Result Entry extended form section
 * Included from _form.php when isTestEntry === true
 */
$testCycles   = $testCycles   ?? [];
$testOutcomes = $testOutcomes ?? ['Passed','Failed','Blocked','Partial','Not Run'];
$testMowers   = $testMowers   ?? [];
$outcomes     = is_array($testOutcomes) ? $testOutcomes : ['Passed','Failed','Blocked','Partial','Not Run'];
?>

<!-- Test Cycle + Test Case Link -->
<div class="card mb-3 border-info">
  <div class="card-header border-info text-info fw-semibold small d-flex align-items-center gap-2">
    <i class="bi bi-link-45deg"></i>Test Cycle & Test Case Link
    <span class="badge bg-info ms-1" style="font-size:9px;font-weight:400">optional</span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Test Cycle</label>
        <select name="test_cycle_id" class="form-select" id="testCycleSelect">
          <option value="">— No Test Cycle —</option>
          <?php foreach ($testCycles as $tc): ?>
          <option value="<?= $tc['id'] ?>"
                  data-plan="<?= $tc['plan_name'] ?? '' ?>"
                  <?= ($data['test_cycle_id'] ?? 0) == $tc['id'] ? 'selected' : '' ?>>
            <?= e($tc['plan_name'] ? $tc['plan_name'].' › ' : '') ?><?= e($tc['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Test Case <span class="text-muted fw-normal small">(from selected cycle)</span></label>
        <select name="test_plan_item_id_ref" class="form-select" id="testCaseSelect">
          <option value="">— Select Test Cycle first —</option>
        </select>
        <div class="form-text">Test case that this entry documents.</div>
      </div>
    </div>
  </div>
</div>

<!-- Sub-Results -->
<div class="card mb-3 border-info">
  <div class="card-header border-info d-flex align-items-center justify-content-between">
    <span class="text-info fw-semibold small">
      <i class="bi bi-list-check me-1"></i>Partial Results
    </span>
    <button type="button" class="btn btn-outline-info btn-sm" id="addTrBtn">
      <i class="bi bi-plus-lg me-1"></i>Add Partial Result
    </button>
  </div>
  <div class="card-body p-0">
    <div id="trContainer">
      <!-- Pre-fill existing results when editing -->
      <?php if (!empty($testResults)): ?>
      <?php foreach ($testResults as $tri => $tr): ?>
      <div class="tr-item border-bottom border-secondary p-3" data-idx="<?= $tri ?>">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold small text-info">Partial Result #<span class="tr-num"><?= $tri+1 ?></span></span>
          <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 tr-remove">
            <i class="bi bi-trash"></i>
          </button>
        </div>
        <input type="hidden" name="tr_id[]" value="<?= $tr['id'] ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small">Test Setup</label>
            <textarea name="tr_setup[]" class="form-control form-control-sm" rows="3"><?= e($tr['test_setup'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Test Documentation</label>
            <textarea name="tr_doc[]" class="form-control form-control-sm" rows="3"><?= e($tr['test_doc'] ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Test Result <span class="text-danger">*</span></label>
            <select name="tr_result[]" class="form-select form-select-sm">
              <option value="">— Select outcome —</option>
              <?php foreach ($outcomes as $ov): ?>
              <option value="<?= e($ov) ?>" <?= ($tr['test_result']??'') === $ov ? 'selected' : '' ?>><?= e($ov) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Mower Serial Number</label>
            <?php if (!empty($testMowers)): ?>
            <select name="tr_serial[]" class="form-select form-select-sm">
              <option value="">— Select mower or type —</option>
              <?php foreach ($testMowers as $mow): ?>
              <option value="<?= e($mow['serial_number'] ?? $mow['label']) ?>"
                <?= ($tr['mower_serial']??'') === ($mow['serial_number'] ?? $mow['label']) ? 'selected' : '' ?>>
                <?= e($mow['label']) ?><?= $mow['serial_number'] ? ' ('.$mow['serial_number'].')' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="text" name="tr_serial[]" class="form-control form-control-sm"
                   value="<?= e($tr['mower_serial'] ?? '') ?>">
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Notes</label>
            <input type="text" name="tr_notes[]" class="form-control form-control-sm"
                   value="<?= e($tr['notes'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small">Attachments</label>
            <?php if (!empty($tr['attachments'])): ?>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <?php foreach ($tr['attachments'] as $att): ?>
              <a href="<?= url('attachments/'.$att['id']) ?>" target="_blank"
                 class="badge bg-secondary text-decoration-none d-flex align-items-center gap-1">
                <i class="bi bi-paperclip"></i><?= e($att['display_name'] ?? $att['original_name']) ?>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <input type="file" name="tr_file_new[<?= $tri ?>][]" multiple
                   class="form-control form-control-sm mt-1"
                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.log,.mp4,.mov">
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div id="trEmpty" class="text-center text-muted py-4" style="font-size:13px;<?= !empty($testResults) ? 'display:none' : '' ?>">
      <i class="bi bi-clipboard2-x" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px"></i>
      No partial results yet. Click "Add Partial Result" to document test outcomes.
    </div>
  </div>
</div>

<!-- Template for a result row -->
<template id="trTemplate">
  <div class="tr-item border-bottom border-secondary p-3" data-idx="__IDX__">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <span class="fw-semibold small text-info">Partial Result #<span class="tr-num">1</span></span>
      <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 tr-remove">
        <i class="bi bi-trash"></i>
      </button>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label small">Test Setup</label>
        <textarea name="tr_setup[]" class="form-control form-control-sm" rows="3"
                  placeholder="Describe the test setup, environment, conditions…"></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label small">Test Documentation</label>
        <textarea name="tr_doc[]" class="form-control form-control-sm" rows="3"
                  placeholder="Steps performed, observations…"></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label small">Test Result <span class="text-danger">*</span></label>
        <select name="tr_result[]" class="form-select form-select-sm">
          <option value="">— Select outcome —</option>
          <?php foreach ($outcomes as $ov): ?>
          <option value="<?= e($ov) ?>"><?= e($ov) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">Mower Serial Number</label>
        <?php if (!empty($testMowers)): ?>
        <select name="tr_serial[]" class="form-select form-select-sm">
          <option value="">— Select mower or type —</option>
          <?php foreach ($testMowers as $mow): ?>
          <option value="<?= e($mow['serial_number'] ?? $mow['label']) ?>">
            <?= e($mow['label']) ?><?= $mow['serial_number'] ? ' ('.$mow['serial_number'].')' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="text" name="tr_serial[]" class="form-control form-control-sm"
               placeholder="e.g. H700J2261311000046">
        <?php endif; ?>
      </div>
      <div class="col-md-4">
        <label class="form-label small">Notes</label>
        <input type="text" name="tr_notes[]" class="form-control form-control-sm"
               placeholder="Additional notes…">
      </div>
      <div class="col-12">
        <label class="form-label small">Attachments <span class="text-muted fw-normal">(added after saving)</span></label>
        <div class="text-muted small border rounded p-2">
          <i class="bi bi-info-circle me-1"></i>
          Attachments for individual partial results can be added after saving this entry via the entry detail view.
        </div>
      </div>
    </div>
  </div>
</template>

<script>
(function() {
  var container = document.getElementById('trContainer');
  var empty     = document.getElementById('trEmpty');
  var addBtn    = document.getElementById('addTrBtn');
  var template  = document.getElementById('trTemplate');
  var idx       = <?= !empty($testResults) ? count($testResults) : 0 ?>;

  function updateNumbers() {
    container.querySelectorAll('.tr-item').forEach(function(item, i) {
      item.querySelector('.tr-num').textContent = i + 1;
      item.dataset.idx = i;
    });
    empty.style.display = container.querySelectorAll('.tr-item').length ? 'none' : '';
  }

  // Wire up remove buttons for PHP-pre-rendered items
  container.querySelectorAll('.tr-remove').forEach(function(btn) {
    btn.addEventListener('click', function() {
      btn.closest('.tr-item').remove();
      updateNumbers();
    });
  });
  updateNumbers();

  addBtn.addEventListener('click', function() {
    var html = template.innerHTML.replace(/__IDX__/g, idx++);
    var div  = document.createElement('div');
    div.innerHTML = html;
    var item = div.firstElementChild;
    item.querySelector('.tr-remove').addEventListener('click', function() {
      item.remove(); updateNumbers();
    });
    container.appendChild(item);
    updateNumbers();
    item.scrollIntoView({behavior:'smooth', block:'nearest'});
  });

  // Test Cycle → load test cases
  var cycleSelect = document.getElementById('testCycleSelect');
  var caseSelect  = document.getElementById('testCaseSelect');
  if (cycleSelect && caseSelect) {
    cycleSelect.addEventListener('change', function() {
      var cid = this.value;
      if (!cid) { caseSelect.innerHTML = '<option value="">— Select Test Cycle first —</option>'; return; }
      caseSelect.innerHTML = '<option value="">Loading…</option>';
      fetch('<?= url('api/test-cycle-items') ?>?cycle_id=' + cid)
        .then(r => r.json())
        .then(items => {
          caseSelect.innerHTML = '<option value="">— No specific test case —</option>'
            + items.map(i => '<option value="'+i.id+'">'+i.name+'</option>').join('');
        })
        .catch(() => { caseSelect.innerHTML = '<option value="">— Error loading items —</option>'; });
    });
  }

})();
</script>
