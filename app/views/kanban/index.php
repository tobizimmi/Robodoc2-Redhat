<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><i class="bi bi-kanban me-2 text-info"></i>Kanban Board</h4>
    <small class="text-muted"><?= array_sum(array_map(fn($c)=>count($c['entries']),$cols)) ?> tickets ? drag cards to change status or lane</small>
  </div>
  <a href="<?= url('entries/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Entry
  </a>
</div>

<!-- View mode tabs -->
<ul class="nav nav-tabs mb-3" id="kanbanViewTabs">
  <li class="nav-item">
    <a class="nav-link <?= $viewMode==='status'?'active':'' ?>"
       href="<?= url('kanban') ?>?<?= http_build_query(array_merge($_GET, ['view'=>'status'])) ?>">
      <i class="bi bi-kanban me-1"></i>Status View
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $viewMode==='lane'?'active':'' ?>"
       href="<?= url('kanban') ?>?<?= http_build_query(array_merge($_GET, ['view'=>'lane'])) ?>">
      <i class="bi bi-layout-three-columns me-1"></i>Lane View
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="<?= url('kanban/tag-view') ?>">
      <i class="bi bi-tags me-1"></i>Tag View
    </a>
  </li>
</ul>

<!-- Filter bar -->
<form method="GET" action="<?= url('kanban') ?>" class="card mb-3">
  <input type="hidden" name="view" value="<?= e($viewMode) ?>">
  <div class="card-body p-3">
    <div class="row g-2 mb-2">
      <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title?" value="<?= e($search) ?>">
      </div>
      <div class="col-md-2">
        <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Projects</option>
          <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $projectId==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="cat_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catId==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Priorities</option>
          <?php foreach (['Blocker','Highest','High','Medium','Low'] as $prio): ?>
          <option value="<?= $prio ?>" <?= $priority===$prio?'selected':'' ?>><?= $prio ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-outline-primary btn-sm w-100">Apply</button>
      </div>
      <div class="col-md-1">
        <a href="<?= url('kanban') ?>?view=<?= e($viewMode) ?>" class="btn btn-outline-secondary btn-sm w-100" title="Reset">?</a>
      </div>
    </div>

    <!-- Type chips -->
    <?php if ($entryTypes): ?>
    <div class="d-flex flex-wrap gap-2 mb-2">
      <span class="text-muted small me-1 align-self-center">Type:</span>
      <?php foreach ($entryTypes as $et): ?>
      <label class="chip" style="cursor:pointer;font-size:.8rem">
        <input type="checkbox" name="type_ids[]" value="<?= $et['id'] ?>" class="d-none"
               <?= in_array($et['id'],$typeIds)?'checked':'' ?> onchange="this.closest('form').submit()">
        <span class="rounded-circle d-inline-block me-1" style="width:8px;height:8px;background:<?= e($et['color']) ?>"></span>
        <?= e($et['name']) ?>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Status column toggles ? only shown in status view -->
    <?php if ($viewMode === 'status'): ?>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="text-muted small me-1">Columns:</span>
      <?php foreach (entryStatuses() as $slug => $label): ?>
      <label class="d-flex align-items-center gap-1 small" style="cursor:pointer">
        <input type="checkbox" name="vis_status[]" value="<?= $slug ?>"
               class="form-check-input" style="width:14px;height:14px"
               <?= in_array($slug,(array)$visStatuses,true)?'checked':'' ?>
               onchange="this.closest('form').submit()">
        <span class="badge bg-<?= entryStatusColor($slug) ?>" style="font-size:.6rem"><?= $label ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</form>

<?php if ($viewMode === 'status'): ?>

  <!-- Kanban Presets (status view only) -->
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <span class="text-muted small"><i class="bi bi-bookmark me-1"></i>Presets:</span>
    <div id="kanbanPresetList" class="d-flex gap-1 flex-wrap"></div>
    <div class="input-group input-group-sm ms-auto" style="max-width:260px">
      <input type="text" id="kanbanPresetName" class="form-control form-control-sm bg-dark text-white border-secondary"
             placeholder="Save current view as?">
      <button class="btn btn-outline-success btn-sm" onclick="saveKanbanPreset()">
        <i class="bi bi-check-lg"></i>
      </button>
    </div>
  </div>

  <!-- Status Kanban board -->
  <?php if (!array_sum(array_map(fn($c)=>count($c['entries']),$cols))): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-kanban fs-1 d-block mb-2 opacity-25"></i>
    No entries match the current filters.
    <a href="<?= url('kanban') ?>" class="d-block mt-2">Reset filters</a>
  </div>
  <?php else: ?>
  <?php include __DIR__ . '/_board.php'; ?>
  <?php endif; ?>

<?php else: ?>

  <!-- Lane view info -->
  <div class="alert alert-secondary py-2 px-3 mb-3" style="font-size:.82rem">
    <i class="bi bi-info-circle me-1"></i>
    Drag cards between lanes to change their category, independent of status.
    <strong>Archive</strong> shows only the last 5 entries.
  </div>

  <!-- Lane board -->
  <?php if (!array_sum(array_map(fn($c)=>count($c['entries']),$lanes))): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-layout-three-columns fs-1 d-block mb-2 opacity-25"></i>
    No entries match the current filters.
    <a href="<?= url('kanban') ?>?view=lane" class="d-block mt-2">Reset filters</a>
  </div>
  <?php else: ?>
  <?php include __DIR__ . '/_lane_board.php'; ?>
  <?php endif; ?>

<?php endif; ?>

<script>
const _kanbanCsrf    = '<?= e(Auth::csrfToken()) ?>';
const _kanbanBaseUrl = '<?= url('kanban') ?>';
let   _kanbanPresets = [];

// ?? Load presets from server ??????????????????????????????????
fetch('<?= url('api/presets') ?>?type=kanban')
  .then(r => r.json())
  .then(rows => { _kanbanPresets = rows || []; renderKanbanPresets(); })
  .catch(() => {});

function renderKanbanPresets() {
  const list = document.getElementById('kanbanPresetList');
  if (!list) return;
  if (!_kanbanPresets.length) {
    list.innerHTML = '<span class="text-muted small">No presets saved yet</span>';
    return;
  }
  list.innerHTML = _kanbanPresets.map(p => {
    const safeName = p.name.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    return `<div class="d-flex align-items-center gap-1">
       <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.75rem"
               onclick="loadKanbanPreset(${p.id})">${p.name}</button>
       <button class="btn btn-link p-0 text-danger" style="font-size:.7rem"
               onclick="deleteKanbanPreset(${p.id},'${safeName}')" title="Delete preset">
         <i class="bi bi-x-lg"></i>
       </button>
     </div>`;
  }).join('');
}

// ?? Save current state as preset ?????????????????????????????
function saveKanbanPreset() {
  const name = document.getElementById('kanbanPresetName')?.value.trim();
  if (!name) return;

  const params    = new URLSearchParams(window.location.search);
  const filters   = {};
  ['search','project_id','cat_id','priority'].forEach(k => {
    if (params.get(k)) filters[k] = params.get(k);
  });
  const typeIds = params.getAll('type_ids[]');
  if (typeIds.length) filters['type_ids[]'] = typeIds;

  const visStatus = params.getAll('vis_status[]');
  const colOrder  = JSON.parse(localStorage.getItem('kanbanColOrder_global') || 'null');
  const config    = JSON.stringify({ filters, vis_status: visStatus, col_order: colOrder });

  fetch('<?= url('api/presets') ?>', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _kanbanCsrf, type: 'kanban', name, config })
  })
  .then(r => r.json())
  .then(d => {
    if (d.error) { if(typeof showToast==='function') showToast(d.error,'danger'); return; }
    const idx = _kanbanPresets.findIndex(p => p.name === name);
    if (idx >= 0) _kanbanPresets[idx] = d.preset;
    else _kanbanPresets.push(d.preset);
    _kanbanPresets.sort((a,b) => a.name.localeCompare(b.name));
    renderKanbanPresets();
    document.getElementById('kanbanPresetName').value = '';
    if(typeof showToast==='function') showToast('Preset "' + name + '" saved.','success');
  });
}

// ?? Load a preset ?????????????????????????????????????????????
function loadKanbanPreset(id) {
  const p = _kanbanPresets.find(x => x.id == id);
  if (!p) return;
  let data;
  try { data = typeof p.config === 'string' ? JSON.parse(p.config) : p.config; } catch { return; }

  if (data.col_order) localStorage.setItem('kanbanColOrder_global', JSON.stringify(data.col_order));

  const url = new URL(_kanbanBaseUrl, window.location.origin);
  const f   = data.filters || {};
  Object.entries(f).forEach(([k, v]) => {
    if (Array.isArray(v)) v.forEach(val => url.searchParams.append(k, val));
    else url.searchParams.set(k, v);
  });
  (data.vis_status || []).forEach(s => url.searchParams.append('vis_status[]', s));

  window.location.href = url.toString();
}

// ?? Delete a preset ???????????????????????????????????????????
function deleteKanbanPreset(id, name) {
  if (!confirm('Delete preset "' + name + '"?')) return;
  fetch('<?= url('api/presets/') ?>' + id + '/delete', {
    method: 'POST', body: new URLSearchParams({ _csrf: _kanbanCsrf })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      _kanbanPresets = _kanbanPresets.filter(p => p.id != id);
      renderKanbanPresets();
    }
  });
}
</script>
