<div class="row g-3">
  <div class="col-md-8">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Session Info</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" value="<?= e($data['title'] ?? '') ?>" required
                 placeholder="e.g. Firmware 3.4.2 mowing test – Garden A">
        </div>
        <div class="mb-3">
          <label class="form-label">Description / Goals</label>
          <textarea name="description" class="form-control" rows="3"><?= e($data['description'] ?? '') ?></textarea>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small">Project</label>
            <select name="project_id" class="form-select form-select-sm">
              <option value="">— None —</option>
              <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>" <?= ($data['project_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Test Area</label>
            <select name="test_area_id" class="form-select form-select-sm">
              <option value="">— None —</option>
              <?php foreach ($areas as $a): ?>
              <option value="<?= $a['id'] ?>" <?= ($data['test_area_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Environmental Conditions</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small">Temperature (°C)</label>
            <input type="number" name="temperature" class="form-control form-control-sm" step="0.1"
                   value="<?= e($data['temperature'] ?? '') ?>" placeholder="e.g. 18.5">
          </div>
          <div class="col-md-8">
            <label class="form-label small">Weather Condition</label>
            <select name="weather_condition" class="form-select form-select-sm">
              <option value="">—</option>
              <?php foreach (['Sunny', 'Partly Cloudy', 'Overcast', 'Light Rain', 'Heavy Rain', 'Foggy', 'Windy'] as $w): ?>
              <option <?= ($data['weather_condition'] ?? '') === $w ? 'selected' : '' ?>><?= $w ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label small">Terrain Notes</label>
          <textarea name="terrain_notes" class="form-control form-control-sm" rows="2"
                    placeholder="Wet grass, high grass, last mowed 2 weeks ago…"><?= e($data['terrain_notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Robot / Software</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label small">Firmware Version</label>
          <input type="text" name="firmware_version" class="form-control form-control-sm"
                 value="<?= e($data['firmware_version'] ?? '') ?>" placeholder="e.g. 3.4.2.1234">
        </div>
        <div class="mb-3">
          <label class="form-label small">App Version</label>
          <input type="text" name="app_version" class="form-control form-control-sm"
                 value="<?= e($data['app_version'] ?? '') ?>" placeholder="e.g. 2.1.0">
        </div>
        <div class="mb-3">
          <label class="form-label small">Operator</label>
          <select name="operator_id" class="form-select form-select-sm">
            <option value="">— None —</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= ($data['operator_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (!empty($mowers)): ?>
        <div class="mb-0">
          <label class="form-label small">Mowers (select all tested)</label>
          <div class="d-flex flex-column gap-1">
            <?php foreach ($mowers as $m): ?>
            <div class="form-check form-check-sm">
              <input type="checkbox" class="form-check-input" name="mower_ids[]" value="<?= $m['id'] ?>"
                     id="mow-<?= $m['id'] ?>" <?= in_array($m['id'], $selectedMowerIds ?? []) ? 'checked' : '' ?>>
              <label class="form-check-label small" for="mow-<?= $m['id'] ?>">
                <?= e($m['label']) ?>
                <?php if ($m['serial_number']): ?><span class="text-muted">(<?= e($m['serial_number']) ?>)</span><?php endif; ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!isset($session)): ?>
    <div class="card mb-3 border-success">
      <div class="card-body">
        <div class="form-check">
          <input type="checkbox" name="start_now" id="startNow" class="form-check-input" value="1" checked>
          <label for="startNow" class="form-check-label small">
            <strong>Set as active session</strong><br>
            <span class="text-muted" style="font-size:.75rem">New entries will be automatically linked to this session</span>
          </label>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Test Cycle + Test Case Link -->
    <?php if (!empty($testCycles)): ?>
    <div class="card mb-3 border-info">
      <div class="card-header border-info fw-semibold small text-info">
        <i class="bi bi-link-45deg me-1"></i>Test Cycle & Test Case
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label small">Test Cycle</label>
          <select name="test_cycle_id" class="form-select form-select-sm" id="sessionCycleSelect">
            <option value="">— No Test Cycle —</option>
            <?php foreach ($testCycles as $tc): ?>
            <option value="<?= $tc['id'] ?>"
                    <?= ($data['test_cycle_id'] ?? 0) == $tc['id'] ? 'selected' : '' ?>>
              <?= e($tc['plan_name'] ? $tc['plan_name'].' › ' : '') ?><?= e($tc['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label small">Test Case <span class="text-muted fw-normal">(optional)</span></label>
          <select name="test_plan_item_id" class="form-select form-select-sm" id="sessionCaseSelect">
            <option value="">— Select Test Cycle first —</option>
            <?php if (!empty($data['test_plan_item_id'])): ?>
            <option value="<?= $data['test_plan_item_id'] ?>" selected>
              Test Case #<?= $data['test_plan_item_id'] ?>
            </option>
            <?php endif; ?>
          </select>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="d-grid gap-2">
      <button type="submit" class="btn btn-primary">
        <?= isset($session) ? 'Save Changes' : 'Create Session' ?>
      </button>
      <a href="<?= url('test-sessions') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </div>
</div>
<script>
(function() {
  var cycleSelect = document.getElementById('sessionCycleSelect');
  var caseSelect  = document.getElementById('sessionCaseSelect');
  if (!cycleSelect || !caseSelect) return;
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
      .catch(() => { caseSelect.innerHTML = '<option value="">— Error loading —</option>'; });
  });
})();
</script>
