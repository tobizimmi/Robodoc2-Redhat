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
  <h5 class="mb-0">New Test Run</h5>
</div>

<div class="card" style="max-width:720px">
  <div class="card-body">
    <form method="POST" action="<?= url('test-runs/create') ?>" id="createRunForm">
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Test Plan <span class="text-danger">*</span></label>
        <select name="test_plan_id" id="planSelect" class="form-select" required onchange="onPlanChange(this.value)">
          <option value="">-- Select plan --</option>
          <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $selectedPlanId === (int)$p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?> (<?= e($p['project_name']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3" id="cycleRow">
        <label class="form-label">Test Cycle <span class="text-danger">*</span></label>
        <select name="test_cycle_id" id="cycleSelect" class="form-select" required>
          <option value="">-- Select cycle --</option>
          <?php foreach ($selectedCycles as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $selectedCycleId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text" id="cycleHint">
          Every run belongs to a Test Cycle (the container, e.g. "Sprint 23 Regression"). No cycle yet for this plan?
          <a href="<?= $selectedPlanId ? url('test-plans/'.$selectedPlanId) : '#' ?>" id="createCycleLink">Create one on the Test Plan page</a>.
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" placeholder="e.g. Regression Sprint 12" required>
      </div>
      <div class="mb-3">
        <label class="form-label small">Environment</label>
        <input type="text" name="environment" class="form-control" placeholder="e.g. Chrome, Android">
      </div>
      <div class="mb-3">
        <label class="form-label small">Description</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
      </div>

      <!-- Test case selection: a run only covers what's picked here, not
           automatically the whole plan - most runs only exercise a subset. -->
      <div class="mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
          <label class="form-label mb-0">Test Cases <span class="text-danger">*</span></label>
          <span class="badge bg-info" id="selCount">0 selected</span>
        </div>
        <div id="itemsEmptyHint" class="text-muted small border border-secondary rounded p-3 text-center">
          Select a Test Plan above to choose which test cases this run covers.
        </div>
        <div id="itemsPanel" style="display:none">
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <select id="filterCategory" class="form-select form-select-sm" onchange="applyItemFilter()">
                <option value="">All categories</option>
              </select>
            </div>
            <div class="col-md-4">
              <select id="filterPriority" class="form-select form-select-sm" onchange="applyItemFilter()">
                <option value="">All priorities</option>
              </select>
            </div>
            <div class="col-md-4">
              <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="Search title..." oninput="applyItemFilter()">
            </div>
          </div>
          <div class="d-flex gap-2 mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="toggleAllItems(true)">Select All</button>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="toggleAllItems(false)">Select None</button>
          </div>
          <div id="itemsList" class="border border-secondary rounded" style="max-height:320px;overflow-y:auto"></div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-play me-1"></i>Start Test Run</button>
        <a href="<?= url('test-runs') ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
var _rdItems = [];

function onPlanChange(planId) {
  loadCycles(planId);
  loadItems(planId);
}

function loadCycles(planId) {
  const sel = document.getElementById('cycleSelect');
  sel.innerHTML = '<option value="">-- Select cycle --</option>';
  const link = document.getElementById('createCycleLink');
  if (link) link.href = planId ? '<?= url('test-plans/') ?>' + planId : '#';
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

function loadItems(planId) {
  const empty = document.getElementById('itemsEmptyHint');
  const panel = document.getElementById('itemsPanel');
  _rdItems = [];
  if (!planId) {
    empty.style.display = '';
    panel.style.display = 'none';
    return;
  }
  fetch('<?= url('test-plans/') ?>' + planId + '/items-json', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
    .then(r => r.json())
    .then(items => {
      _rdItems = items;
      empty.style.display = 'none';
      panel.style.display = '';
      renderItemFilters();
      renderItemsList();
    });
}

function renderItemFilters() {
  const catSel = document.getElementById('filterCategory');
  const priSel = document.getElementById('filterPriority');
  catSel.innerHTML = '<option value="">All categories</option>';
  priSel.innerHTML = '<option value="">All priorities</option>';
  const cats = [], pris = [];
  _rdItems.forEach(i => {
    if (i.category_id && cats.every(c => c.id !== i.category_id)) cats.push({id: i.category_id, name: i.category_name || ('#' + i.category_id)});
    if (i.priority && pris.indexOf(i.priority) === -1) pris.push(i.priority);
  });
  cats.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name; catSel.appendChild(o); });
  pris.forEach(p => { const o = document.createElement('option'); o.value = p; o.textContent = p; priSel.appendChild(o); });
}

function renderItemsList() {
  const list = document.getElementById('itemsList');
  list.innerHTML = _rdItems.map(function(i) {
    return '<label class="list-group-item list-group-item-action bg-transparent border-secondary py-2 px-3 d-flex align-items-center gap-2 rd-item-row" '
      + 'style="cursor:pointer" data-category="' + (i.category_id || '') + '" data-priority="' + (i.priority || '') + '" '
      + 'data-title="' + (i.title || '').toLowerCase().replace(/"/g,'&quot;') + '">'
      + '<input type="checkbox" class="form-check-input flex-shrink-0 rd-item-cb" name="item_ids[]" value="' + i.id + '" checked onchange="updateSelCount()">'
      + '<span class="small flex-grow-1">' + (i.title || '').replace(/</g,'&lt;') + '</span>'
      + (i.category_name ? '<span class="badge bg-secondary" style="font-size:.6rem">' + i.category_name.replace(/</g,'&lt;') + '</span>' : '')
      + (i.priority ? '<span class="badge bg-info text-dark" style="font-size:.6rem">' + i.priority + '</span>' : '')
      + '</label>';
  }).join('') || '<div class="text-muted small p-3">This plan has no test cases yet.</div>';
  updateSelCount();
}

function applyItemFilter() {
  const cat = document.getElementById('filterCategory').value;
  const pri = document.getElementById('filterPriority').value;
  const q   = document.getElementById('filterSearch').value.trim().toLowerCase();
  document.querySelectorAll('.rd-item-row').forEach(function(row) {
    const matches = (!cat || row.dataset.category === cat)
      && (!pri || row.dataset.priority === pri)
      && (!q || row.dataset.title.indexOf(q) !== -1);
    row.style.display = matches ? '' : 'none';
  });
}

function toggleAllItems(checked) {
  document.querySelectorAll('.rd-item-row').forEach(function(row) {
    if (row.style.display === 'none') return; // only affect currently filtered-in rows
    const cb = row.querySelector('.rd-item-cb');
    if (cb) cb.checked = checked;
  });
  updateSelCount();
}

function updateSelCount() {
  const n = document.querySelectorAll('.rd-item-cb:checked').length;
  document.getElementById('selCount').textContent = n + ' selected';
}

<?php if ($selectedPlanId): ?>
onPlanChange('<?= $selectedPlanId ?>');
<?php endif; ?>
</script>
