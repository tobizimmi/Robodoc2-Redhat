<?php
$selectedPlanId  = (int)($_GET['plan_id'] ?? 0);
$selectedCycleId = (int)($_GET['cycle_id'] ?? 0);
// Load cycles for the selected plan
$selectedCycles  = $selectedPlanId
    ? Database::fetchAll('SELECT id, name FROM test_cycles WHERE test_plan_id=? ORDER BY created_at DESC', [$selectedPlanId])
    : [];
?>
<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-runs') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Neuer Test Run</h5>
</div>

<div class="card" style="max-width:560px">
  <div class="card-body">
    <form method="POST" action="<?= url('test-runs/create') ?>">
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Test Plan <span class="text-danger">*</span></label>
        <select name="test_plan_id" id="planSelect" class="form-select" required onchange="loadCycles(this.value)">
          <option value="">-- Plan auswaehlen --</option>
          <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $selectedPlanId === (int)$p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?> (<?= e($p['project_name']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3" id="cycleRow">
        <label class="form-label">Test Cycle <span class="text-muted small">(optional)</span></label>
        <select name="test_cycle_id" id="cycleSelect" class="form-select">
          <option value="">-- Kein Cycle --</option>
          <?php foreach ($selectedCycles as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $selectedCycleId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Optionaler Test Cycle dem dieser Run zugeordnet wird.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" placeholder="z.B. Regression Sprint 12" required>
      </div>
      <div class="mb-3">
        <label class="form-label small">Umgebung</label>
        <input type="text" name="environment" class="form-control" placeholder="z.B. Chrome, Android">
      </div>
      <div class="mb-3">
        <label class="form-label small">Beschreibung</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-play me-1"></i>Starten</button>
        <a href="<?= url('test-runs') ?>" class="btn btn-outline-secondary">Abbrechen</a>
      </div>
    </form>
  </div>
</div>

<script>
function loadCycles(planId) {
  const sel = document.getElementById('cycleSelect');
  sel.innerHTML = '<option value="">-- Kein Cycle --</option>';
  if (!planId) return;
  fetch('<?= url('test-plans/') ?>' + planId + '/cycles-json', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
    .then(r => r.json())
    .then(cycles => {
      cycles.forEach(c => {
        const o = document.createElement('option');
        o.value = c.id; o.textContent = c.name;
        sel.appendChild(o);
      });
    });
}
</script>
