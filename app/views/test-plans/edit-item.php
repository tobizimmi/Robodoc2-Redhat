<?php
$csrf = Auth::csrfToken();
$canEdit = Auth::canEdit('testing');
?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= url('test-plans/' . $plan['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h5 class="mb-0">Test Case bearbeiten</h5>
    <small class="text-muted"><?= e($plan['name']) ?></small>
  </div>
</div>

<div class="row g-4">
<div class="col-lg-8">
  <div class="card">
    <div class="card-body">
      <form method="POST" action="<?= url('test-plans/' . $plan['id'] . '/items/' . $item['id'] . '/edit') ?>">
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label">Titel <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" value="<?= e($item['title']) ?>" required>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Prioritaet</label>
            <select name="priority" class="form-select">
              <?php foreach (['low'=>'Niedrig','medium'=>'Mittel','high'=>'Hoch','critical'=>'Kritisch'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $item['priority']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['pending'=>'Ausstehend','active'=>'Aktiv','done'=>'Erledigt','skipped'=>'Uebersprungen'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $item['status']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Beschreibung</label>
          <textarea name="description" class="form-control" rows="4"><?= e($item['description']??'') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Erwartetes Ergebnis</label>
          <textarea name="expected_result" class="form-control" rows="3"><?= e($item['expected_result']??'') ?></textarea>
        </div>
        <?php if ($customFields): ?>
        <hr class="border-secondary">
        <h6 class="text-muted mb-3">Custom Fields</h6>
        <?php foreach ($customFields as $cf): ?>
        <div class="mb-3">
          <label class="form-label small"><?= e($cf['name']) ?></label>
          <?php $val = $customValues[$cf['id']] ?? ''; ?>
          <?php if ($cf['field_type']==='textarea'): ?>
          <textarea name="cf_<?= e($cf['variable_name']) ?>" class="form-control form-control-sm" rows="2"><?= e($val) ?></textarea>
          <?php elseif ($cf['field_type']==='select'): ?>
          <select name="cf_<?= e($cf['variable_name']) ?>" class="form-select form-select-sm">
            <option value="">--</option>
            <?php foreach (array_filter(array_map('trim', explode("\n", $cf['options']??''))) as $opt): ?>
            <option value="<?= e($opt) ?>" <?= $val===$opt?'selected':'' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <?php else: ?>
          <input type="text" name="cf_<?= e($cf['variable_name']) ?>" class="form-control form-control-sm" value="<?= e($val) ?>">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">Speichern</button>
          <a href="<?= url('test-plans/' . $plan['id']) ?>" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Test Steps -->
  <div class="card mt-4">
    <div class="card-header border-secondary d-flex align-items-center justify-content-between">
      <span class="fw-semibold small"><i class="bi bi-list-ol me-1"></i>Test Steps <span class="badge bg-secondary ms-1"><?= count($steps) ?></span></span>
    </div>
    <?php if ($steps): ?>
    <div class="table-responsive">
      <table class="table table-dark align-middle mb-0" style="font-size:.83rem" id="stepsTable">
        <thead class="text-muted" style="font-size:.72rem">
          <tr><th style="width:40px">#</th><th>Step Action</th><th style="width:22%">Test Data</th><th style="width:25%">Expected Result</th><th style="width:80px"></th></tr>
        </thead>
        <tbody>
          <?php foreach ($steps as $st): ?>
          <tr id="str-<?= $st['id'] ?>">
            <td class="text-muted"><?= (int)$st['step_number'] ?></td>
            <td><?= nl2br(e($st['step_action']??'')) ?></td>
            <td class="text-muted small"><?= nl2br(e($st['test_data']??'')) ?></td>
            <td class="small"><?= nl2br(e($st['expected_result']??'')) ?></td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="openEditStep(<?= $st['id'] ?>)" title="Bearbeiten"><i class="bi bi-pencil" style="font-size:.7rem"></i></button>
                <button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="delStep(<?= $st['id'] ?>,'<?= e($csrf) ?>')" title="Loeschen"><i class="bi bi-trash" style="font-size:.7rem"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <div class="card-body">
      <div class="row g-2" id="addStepForm">
        <div class="col-md-4"><input type="text" id="nAction" class="form-control form-control-sm" placeholder="Step Action *"></div>
        <div class="col-md-3"><input type="text" id="nData"   class="form-control form-control-sm" placeholder="Test Data"></div>
        <div class="col-md-3"><input type="text" id="nExp"    class="form-control form-control-sm" placeholder="Expected Result"></div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" onclick="addStep('<?= e($csrf) ?>')"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
      </div>
    </div>
  </div>
</div>

<div class="col-lg-4">
  <div class="card">
    <div class="card-header border-secondary fw-semibold small">Info</div>
    <div class="card-body py-2">
      <?php if (!empty($item['synapse_key'])): ?>
      <div class="d-flex justify-content-between small mb-1">
        <span class="text-muted">SynapseRT</span>
        <span class="badge bg-dark border border-warning text-warning"><?= e($item['synapse_key']) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($item['synapse_status']): ?>
      <div class="d-flex justify-content-between small mb-1">
        <span class="text-muted">Jira Status</span>
        <span class="badge bg-secondary"><?= e($item['synapse_status']) ?></span>
      </div>
      <?php endif; ?>
      <div class="d-flex justify-content-between small">
        <span class="text-muted">Erstellt</span>
        <span><?= formatDate($item['created_at'],'d.m.Y') ?></span>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Edit Step Modal -->
<div class="modal fade" id="editStepModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title">Step bearbeiten</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="esId">
      <div class="mb-3"><label class="form-label small">Step Action</label><textarea id="esAction" class="form-control" rows="3"></textarea></div>
      <div class="mb-3"><label class="form-label small">Test Data</label><input id="esData" type="text" class="form-control"></div>
      <div class="mb-3"><label class="form-label small">Expected Result</label><textarea id="esExpected" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-primary btn-sm" onclick="saveStep('<?= e($csrf) ?>')">Speichern</button>
    </div>
  </div></div>
</div>

<script>
const _planId = <?= $plan['id'] ?>;
const _itemId = <?= $item['id'] ?>;
const _base   = '<?= url('test-plans/'.$plan['id'].'/items/'.$item['id']) ?>';

function addStep(csrf) {
  const action = document.getElementById('nAction').value.trim();
  const data   = document.getElementById('nData').value.trim();
  const exp    = document.getElementById('nExp').value.trim();
  if (!action && !exp) return;
  fetch(_base + '/steps', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, step_action: action, test_data: data, expected_result: exp})
  }).then(r => r.json()).then(d => {
    if (d.success) location.reload();
    else alert(d.error || 'Fehler');
  });
}

function openEditStep(stepId) {
  const row = document.getElementById('str-' + stepId);
  if (!row) return;
  const cells = row.querySelectorAll('td');
  document.getElementById('esId').value       = stepId;
  document.getElementById('esAction').value   = cells[1]?.innerText?.trim() || '';
  document.getElementById('esData').value     = cells[2]?.innerText?.trim() || '';
  document.getElementById('esExpected').value = cells[3]?.innerText?.trim() || '';
  new bootstrap.Modal(document.getElementById('editStepModal')).show();
}

function saveStep(csrf) {
  const sid = document.getElementById('esId').value;
  fetch(_base + '/steps/' + sid + '/update', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, step_action: document.getElementById('esAction').value,
      test_data: document.getElementById('esData').value, expected_result: document.getElementById('esExpected').value})
  }).then(r => r.json()).then(d => {
    if (d.success) { bootstrap.Modal.getInstance(document.getElementById('editStepModal')).hide(); location.reload(); }
    else alert(d.error || 'Fehler');
  });
}

function delStep(stepId, csrf) {
  if (!confirm('Step loeschen?')) return;
  fetch(_base + '/steps/' + stepId + '/delete', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf})
  }).then(r => r.json()).then(d => {
    if (d.success) document.getElementById('str-' + stepId)?.remove();
  });
}
</script>
