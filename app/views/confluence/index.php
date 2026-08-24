<div class="d-flex align-items-center gap-2 mb-4">
  <h5 class="mb-0"><i class="bi bi-cloud-upload me-2"></i>Confluence Export</h5>
</div>

<?php if (isset($result['error'])): ?>
<div class="alert alert-danger" id="confResult"><i class="bi bi-x-circle me-2"></i><?= e($result['error']) ?></div>
<?php endif; ?>

<form method="POST" action="<?= url('confluence') ?>" id="confForm">
  <?= csrfField() ?>

  <!-- Step tabs -->
  <ul class="nav nav-tabs mb-4" id="confTabs">
    <li class="nav-item"><a class="nav-link active" id="tab0" href="#" onclick="return showStep(0)">1 · Select Content</a></li>
    <li class="nav-item"><a class="nav-link" id="tab1" href="#" onclick="return showStep(1)">2 · Columns</a></li>
    <li class="nav-item"><a class="nav-link" id="tab2" href="#" onclick="return showStep(2)">3 · Page Settings</a></li>
  </ul>

  <!-- ───── Step 0: Select content ───── -->
  <div class="conf-step" id="step0">
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <label class="form-label small">Export type</label>
        <select name="mode" id="modeSelect" class="form-select" onchange="updateMode()">
          <option value="entries">Entry List</option>
          <option value="inventory">Inventory List</option>
          <option value="mower_history">Mower History</option>
        </select>
      </div>
    </div>

    <!-- Entry selection (shown when mode=entries) -->
    <div id="entrySection">
      <!-- Preset quick-select -->
      <?php if (!empty($presets)): ?>
      <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <span class="text-muted small"><i class="bi bi-bookmark me-1"></i>Preset:</span>
        <?php foreach ($presets as $pr):
          $prData = json_decode($pr['config'] ?? '{}', true);
          $prFilters = $prData['colFilters'] ?? [];
        ?>
        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 cf-preset-btn"
                data-filters='<?= e(json_encode($prFilters)) ?>'
                onclick="cfApplyPreset(this)">
          <?= e($pr['name']) ?>
        </button>
        <?php endforeach; ?>
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="cfClearFilters()">
          <i class="bi bi-x me-1"></i>Filter löschen
        </button>
      </div>
      <?php endif; ?>

      <!-- Server-side filters -->
      <div class="card bg-dark border-secondary mb-3 p-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="form-label small mb-1">Projekt</label>
            <select id="cfProject" class="form-select form-select-sm bg-dark text-white border-secondary"
                    onchange="cfNavigate()">
              <option value="">Alle Projekte</option>
              <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>" <?= ($cfFiltersActive['project_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php
          $cfInputs = [
            'type'     => ['Typ',        'z.B. Bug,Finding'],
            'status'   => ['Status',     'z.B. New,Open'],
            'priority' => ['Priorität',  'z.B. High,Highest'],
            'category' => ['Kategorie',  'z.B. Firmware'],
            'creator'  => ['Ersteller',  'z.B. Tobi'],
            'serial'   => ['Seriennr.',  'z.B. SN-123'],
            'firmware' => ['Firmware',   'z.B. 3.28'],
            'title'    => ['Titel',      'Suchbegriff...'],
          ];
          foreach ($cfInputs as $cfKey => [$cfLabel, $cfPh]):
            $cfVal = $cfFiltersActive[$cfKey] ?? '';
          ?>
          <div class="col-md-2">
            <label class="form-label small mb-1"><?= $cfLabel ?></label>
            <input type="text" class="form-control form-control-sm bg-dark text-white border-secondary cf-filter-input"
                   data-key="<?= $cfKey ?>" placeholder="<?= $cfPh ?>"
                   value="<?= e($cfVal) ?>"
                   onkeydown="if(event.key==='Enter') cfNavigate()">
          </div>
          <?php endforeach; ?>
          <div class="col-auto">
            <button type="button" class="btn btn-primary btn-sm" onclick="cfNavigate()">
              <i class="bi bi-search"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="cfClearFilters()">
              <i class="bi bi-x"></i>
            </button>
          </div>
        </div>
        <?php if (!empty($cfFiltersActive)): ?>
        <div class="mt-2 d-flex flex-wrap gap-1">
          <?php foreach ($cfFiltersActive as $cfK => $cfV): ?>
          <span class="badge bg-warning text-dark"><?= e($cfK) ?>: <?= e($cfV) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="d-flex align-items-center justify-content-between mb-2">
        <p class="text-muted small mb-0">
          <i class="bi bi-info-circle me-1"></i>
          <?php if (!empty($cfFiltersActive)): ?>
            <span class="text-warning fw-semibold"><?= count($allEntries ?? []) ?> gefilterte</span> Einträge — einzelne abwählen oder alle übernehmen.
          <?php else: ?>
            Filter setzen oder Einträge einzeln auswählen. Ohne Auswahl werden <strong>alle</strong> Einträge exportiert.
          <?php endif; ?>
        </p>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="toggleSelectAll()">Alle wählen</button>
      </div>

      <!-- Entry list with checkboxes -->
      <div class="card" style="max-height:400px;overflow-y:auto">
        <div id="entryList">
          <?php foreach ($allEntries as $e): ?>
          <div class="entry-item d-flex align-items-start gap-2 px-3 py-2 border-bottom border-secondary">
            <input type="checkbox" name="entry_ids[]" value="<?= $e['id'] ?>"
                   class="form-check-input mt-1 flex-shrink-0 cf-entry-cb" id="ec<?= $e['id'] ?>">
            <label for="ec<?= $e['id'] ?>" class="flex-grow-1" style="cursor:pointer">
              <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <span class="badge" style="background:<?= e($e['type_color'] ?? '#666') ?>;font-size:.7rem"><?= e($e['type_name'] ?? '?') ?></span>
                <span class="badge bg-secondary" style="font-size:.65rem"><?= e($e['status'] ?? '') ?></span>
                <span class="text-muted small"><?= e($e['entry_date']) ?></span>
                <span class="text-muted small"><?= e($e['project_name'] ?? '') ?></span>
                <span class="text-muted small"><?= e($e['creator'] ?? '') ?></span>
              </div>
              <div class="fw-semibold small"><?= e($e['title'] ?: mb_substr($e['description'] ?? '', 0, 80)) ?></div>
            </label>
          </div>
          <?php endforeach; ?>
          <?php if (empty($allEntries)): ?>
          <div class="text-muted text-center p-4 small">Keine Einträge gefunden.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="mt-2 text-muted small">
        <span id="selectedCount">0</span> ausgewählt &nbsp;·&nbsp;
        <?= count($allEntries) ?> gefilterte Einträge
      </div>
    </div>

    <!-- Inventory selection -->
    <div id="inventorySection" style="display:none">
      <?php
      // Group inventory by project for the filter
      $invByProject = [];
      foreach ($inventory as $inv) {
          $pid = $inv['project_id'] ?? 0;
          $invByProject[$pid][] = $inv;
      }
      $invProjects = array_unique(array_column($inventory, 'project_id'));
      ?>
      <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
        <span class="text-muted small flex-grow-1">Select items to export (leave all unchecked to export all)</span>
        <select id="invProjectFilter" class="form-select form-select-sm" style="max-width:200px" onchange="filterInventoryByProject(this.value)">
          <option value="">All Projects</option>
          <?php foreach ($projects as $p): ?>
          <?php if (isset($invByProject[$p['id']])): ?>
          <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
          <?php endif; ?>
          </select>
          <?php endforeach; ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAllInventory()">Select All</button>
      </div>
      <div class="card" id="inventoryItemList">
        <?php foreach ($inventory as $inv): ?>
        <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom border-secondary inv-item"
             data-project="<?= (int)($inv['project_id'] ?? 0) ?>">
          <input type="checkbox" name="item_ids[]" value="<?= $inv['id'] ?>"
                 class="form-check-input mt-1 flex-shrink-0 inv-cb" id="inv<?= $inv['id'] ?>">
          <label for="inv<?= $inv['id'] ?>" class="flex-grow-1" style="cursor:pointer">
            <div class="fw-semibold small"><?= e($inv['name']) ?></div>
            <div class="text-muted" style="font-size:.75rem">
              <?= $inv['serial_number'] ? 'SN: ' . e($inv['serial_number']) : 'No serial' ?>
              <?= $inv['firmware_version'] ? ' &nbsp;·&nbsp; FW: ' . e($inv['firmware_version']) : '' ?>
              <?= $inv['status'] ? ' &nbsp;·&nbsp; ' . e($inv['status']) : '' ?>
            </div>
          </label>
        </div>
        <?php endforeach; ?>
        <?php if (!$inventory): ?>
        <div class="text-muted text-center p-4 small">No inventory items found.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mower history selection -->
    <div id="mowerSection" style="display:none">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small">Select mowers to include (leave all unchecked to include all)</span>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAllMowers()">Select All</button>
      </div>
      <div class="card">
        <?php
        $mowerItems = array_filter($inventory, fn($i) => !empty($i['serial_number']));
        ?>
        <?php foreach ($mowerItems as $inv): ?>
        <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom border-secondary">
          <input type="checkbox" name="serials[]" value="<?= e($inv['serial_number']) ?>"
                 class="form-check-input mt-1 flex-shrink-0 mower-cb" id="mwr<?= $inv['id'] ?>">
          <label for="mwr<?= $inv['id'] ?>" class="flex-grow-1" style="cursor:pointer">
            <div class="fw-semibold small"><?= e($inv['serial_number']) ?> – <?= e($inv['name']) ?></div>
            <div class="text-muted" style="font-size:.75rem">
              <?= $inv['firmware_version'] ? 'FW: ' . e($inv['firmware_version']) : '' ?>
              <?= $inv['status'] ? ' &nbsp;·&nbsp; ' . e($inv['status']) : '' ?>
            </div>
          </label>
        </div>
        <?php endforeach; ?>
        <?php if (!$mowerItems): ?>
        <div class="text-muted text-center p-4 small">No inventory items with serial numbers found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ───── Step 1: Columns ───── -->
  <div class="conf-step" id="step1" style="display:none">

    <!-- Entry columns -->
    <div id="colsEntries">
      <p class="text-muted small mb-3">Select the columns to include in the entry table.</p>
      <?php
      $entryCols = [
        'date'        => 'Datum',
        'time'        => 'Zeit',
        'type'        => 'Typ',
        'project'     => 'Projekt',
        'title'       => 'Titel',
        'description' => 'Beschreibung',
        'status'      => 'Status',
        'priority'    => 'Priorität',
        'category'    => 'Kategorie',
        'epic'        => 'Epic',
        'parent'      => 'Parent Ticket',
        'serial'      => 'Seriennummer',
        'firmware'    => 'Firmware Version',
        'app_version' => 'App Version',
        'creator'     => 'Ersteller',
        'assigned_to' => 'Zugewiesen an',
        'tags'        => 'Tags',
        'jira'        => 'Jira Issue',
        'zentao'      => 'Zentao Bug',
        'sharepoint'  => 'SharePoint',
        'temperature' => 'Temperatur',
        'weather'     => 'Wetter',
        'test_area'   => 'Test Area',
        'environment' => 'Environment',
        'images'      => 'Bilder (eingebettet)',
        'attachments' => 'Anhänge (Links)',
      ];
      $entryDefault = ['date', 'type', 'project', 'title', 'description', 'serial', 'firmware', 'status', 'jira', 'images'];
      ?>
      <div class="row g-2">
        <?php foreach ($entryCols as $key => $label): ?>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="columns[]" value="<?= $key ?>"
                   id="col_<?= $key ?>" <?= in_array($key, $entryDefault) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="col_<?= $key ?>"><?= $label ?></label>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Inventory columns -->
    <div id="colsInventory" style="display:none">
      <p class="text-muted small mb-3">Select the columns to include in the inventory table.</p>
      <?php
      $invCols = [
        'name'      => 'Name',
        'serial'    => 'Serial No.',
        'project'   => 'Project',
        'firmware'  => 'Firmware Version',
        'status'    => 'Status',
        'location'  => 'Location',
        'notes'     => 'Notes',
        'comment'   => 'Comment',
        'purchased' => 'Purchased',
      ];
      $invDefault = ['name', 'serial', 'project', 'firmware', 'status', 'location', 'notes'];
      ?>
      <div class="row g-2">
        <?php foreach ($invCols as $key => $label): ?>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="inv_columns[]" value="<?= $key ?>"
                   id="invcol_<?= $key ?>" <?= in_array($key, $invDefault) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="invcol_<?= $key ?>"><?= $label ?></label>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Mower history columns -->
    <div id="colsMower" style="display:none">
      <p class="text-muted small mb-3">Select the columns for logbook entries within each mower section.</p>
      <?php
      $mowerCols = [
        'date'        => 'Date',
        'time'        => 'Time',
        'type'        => 'Entry Type',
        'title'       => 'Title',
        'description' => 'Description',
        'firmware'    => 'Firmware Version',
        'app_version' => 'App Version',
        'status'      => 'Status',
        'jira'        => 'Jira Issue',
      ];
      $mowerDefault = ['date', 'type', 'title', 'firmware', 'app_version'];
      ?>
      <div class="row g-2">
        <?php foreach ($mowerCols as $key => $label): ?>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="mower_columns[]" value="<?= $key ?>"
                   id="mowercol_<?= $key ?>" <?= in_array($key, $mowerDefault) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="mowercol_<?= $key ?>"><?= $label ?></label>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- ───── Step 2: Page settings ───── -->
  <div class="conf-step" id="step2" style="display:none">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label small">Space Key <span class="text-danger">*</span></label>
        <input type="text" name="space_key" class="form-control" value="<?= e($settings['confluence_default_space'] ?? '') ?>" placeholder="e.g. RD" required>
      </div>
      <div class="col-md-8" id="pageTitleField">
        <label class="form-label small">Page Title</label>
        <input type="text" name="page_title" class="form-control" value="RoboDoc Export <?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-6" id="parentPageField">
        <label class="form-label small">Parent Page <span class="text-muted">(optional)</span></label>
        <div class="position-relative">
          <input type="text" id="parentPageSearch" class="form-control" placeholder="Type to search pages…"
                 autocomplete="off" oninput="searchConfPages(this,'parentPageResults','parent_id')">
          <div id="parentPageResults" class="list-group position-absolute w-100 shadow" style="z-index:99;display:none;max-height:200px;overflow-y:auto"></div>
        </div>
        <input type="hidden" name="parent_id" id="parent_id">
        <div id="parentPageLabel" class="form-text text-success" style="display:none"></div>
      </div>
      <div class="col-md-6">
        <label class="form-label small">Mode</label>
        <select name="append_mode" class="form-select" id="appendModeSelect" onchange="updateAppendMode()">
          <option value="new">Create new page</option>
          <option value="append">Append to existing page</option>
        </select>
      </div>
      <div class="col-md-6" id="existingPageField" style="display:none">
        <label class="form-label small">Existing Page</label>
        <div class="position-relative">
          <input type="text" id="existingPageSearch" class="form-control" placeholder="Type to search pages…"
                 autocomplete="off" oninput="searchConfPages(this,'existingPageResults','existing_page_id')">
          <div id="existingPageResults" class="list-group position-absolute w-100 shadow" style="z-index:99;display:none;max-height:200px;overflow-y:auto"></div>
        </div>
        <input type="hidden" name="existing_page_id" id="existing_page_id">
        <div id="existingPageLabel" class="form-text text-success" style="display:none"></div>
      </div>
    </div>
  </div>

  <!-- Navigation buttons -->
  <div class="mt-4 d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" id="prevBtn" onclick="navigate(-1)" style="display:none">
      <i class="bi bi-arrow-left me-1"></i>Back
    </button>
    <button type="button" class="btn btn-primary" id="nextBtn" onclick="navigate(1)">
      Next <i class="bi bi-arrow-right ms-1"></i>
    </button>
    <button type="submit" class="btn btn-success" id="submitBtn" style="display:none">
      <i class="bi bi-cloud-upload me-1"></i>Publish to Confluence
    </button>
  </div>
</form>

<?php if (isset($result['success'])): ?>
<div class="alert alert-success mt-4" id="confResult">
  <div class="d-flex align-items-start gap-3">
    <i class="bi bi-check-circle-fill fs-4 text-success flex-shrink-0"></i>
    <div>
      <div class="fw-semibold mb-1">Page published successfully!</div>
      <div class="mb-2"><?= e($result['title']) ?></div>
      <a href="<?= e($result['url']) ?>" target="_blank" class="btn btn-success btn-sm">
        <i class="bi bi-box-arrow-up-right me-1"></i>Open in Confluence
      </a>
      <div class="mt-2 text-muted small" style="word-break:break-all"><?= e($result['url']) ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ───── Export history ───── -->
<div class="mt-5">
  <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Export History</h6>
  <?php if ($exports): ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-secondary">
        <tr>
          <th>Page</th>
          <th>Space</th>
          <th>Type</th>
          <th>Date</th>
          <th>By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($exports as $ex): ?>
        <tr>
          <td>
            <a href="<?= e($ex['page_url']) ?>" target="_blank" class="text-decoration-none">
              <?= e($ex['page_title']) ?>
              <i class="bi bi-box-arrow-up-right ms-1 text-muted" style="font-size:.7rem"></i>
            </a>
            <?php if ($ex['append_mode']): ?>
            <span class="badge bg-secondary ms-1" style="font-size:.65rem">appended</span>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-primary"><?= e($ex['space_key']) ?></span></td>
          <td class="text-muted small"><?= e(match($ex['export_mode']) {
            'inventory'     => 'Inventory',
            'mower_history' => 'Mower History',
            default         => 'Entries',
          }) ?></td>
          <td class="text-muted small text-nowrap"><?= e(date('Y-m-d H:i', strtotime($ex['exported_at']))) ?></td>
          <td class="text-muted small"><?= e($ex['exported_by_name'] ?? '–') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="text-muted small">No exports yet.</p>
  <?php endif; ?>
</div>

<script>
let currentStep = 0;
const totalSteps = 3;

function getMode() { return document.getElementById('modeSelect').value; }

function showStep(n) {
  document.querySelectorAll('.conf-step').forEach((s, i) => s.style.display = i === n ? '' : 'none');
  document.querySelectorAll('#confTabs .nav-link').forEach((t, i) => t.classList.toggle('active', i === n));
  document.getElementById('prevBtn').style.display = n > 0 ? '' : 'none';
  document.getElementById('nextBtn').style.display = n < totalSteps - 1 ? '' : 'none';
  document.getElementById('submitBtn').style.display = n === totalSteps - 1 ? '' : 'none';
  if (n === 1) {
    const mode = getMode();
    document.getElementById('colsEntries').style.display   = mode === 'entries'       ? '' : 'none';
    document.getElementById('colsInventory').style.display = mode === 'inventory'     ? '' : 'none';
    document.getElementById('colsMower').style.display     = mode === 'mower_history' ? '' : 'none';
  }
  currentStep = n;
  return false;
}

function navigate(dir) {
  showStep(Math.max(0, Math.min(totalSteps - 1, currentStep + dir)));
}

function updateMode() {
  const mode = getMode();
  document.getElementById('entrySection').style.display    = mode === 'entries'       ? '' : 'none';
  document.getElementById('inventorySection').style.display = mode === 'inventory'    ? '' : 'none';
  document.getElementById('mowerSection').style.display    = mode === 'mower_history' ? '' : 'none';
}

function updateAppendMode() {
  const append = document.getElementById('appendModeSelect').value === 'append';
  document.getElementById('existingPageField').style.display = append ? '' : 'none';
  document.getElementById('pageTitleField').style.display    = append ? 'none' : '';
  document.getElementById('parentPageField').style.display   = append ? 'none' : '';
}

function cfNavigate() {
  var params = new URLSearchParams(location.search);
  // Remove old _cf_* params
  [...params.keys()].filter(k => k.startsWith('_cf_') || k === 'cf_project_id').forEach(k => params.delete(k));
  // Add current filter values
  document.querySelectorAll('.cf-filter-input').forEach(function(inp) {
    if (inp.value.trim()) params.set('_cf_' + inp.dataset.key, inp.value.trim());
  });
  var proj = document.getElementById('cfProject');
  if (proj && proj.value) params.set('cf_project_id', proj.value);
  location.href = location.pathname + '?' + params.toString();
}

function cfClearFilters() {
  var params = new URLSearchParams(location.search);
  [...params.keys()].filter(k => k.startsWith('_cf_') || k === 'cf_project_id').forEach(k => params.delete(k));
  location.href = location.pathname + (params.toString() ? '?' + params.toString() : '');
}

function cfApplyPreset(btn) {
  var filters = JSON.parse(btn.dataset.filters || '{}');
  // Set filter inputs from preset colFilters
  document.querySelectorAll('.cf-filter-input').forEach(function(inp) {
    inp.value = filters[inp.dataset.key] || '';
  });
  cfNavigate();
}

function filterEntries() {
  const proj   = document.getElementById('filterProject').value;
  const type   = document.getElementById('filterType').value;
  const search = document.getElementById('filterSearch').value.toLowerCase();
  document.querySelectorAll('.entry-item').forEach(item => {
    const matchProj   = !proj   || item.dataset.project === proj;
    const matchType   = !type   || item.dataset.type    === type;
    const matchSearch = !search || item.dataset.title.includes(search);
    item.style.display = (matchProj && matchType && matchSearch) ? '' : 'none';
  });
  updateSelectedCount();
}

let allSelected = false;
function toggleSelectAll() {
  allSelected = !allSelected;
  document.querySelectorAll('.entry-item').forEach(item => {
    if (item.style.display !== 'none') {
      const cb = item.querySelector('input[type="checkbox"]');
      if (cb) cb.checked = allSelected;
    }
  });
  const btn = document.querySelector('[onclick="toggleSelectAll()"]');
  if (btn) btn.textContent = allSelected ? 'Deselect All' : 'Select All';
  updateSelectedCount();
}

function updateSelectedCount() {
  const n = document.querySelectorAll('.cf-entry-cb:checked').length;
  document.getElementById('selectedCount').textContent = n;
}

document.querySelectorAll('input[name="entry_ids[]"]').forEach(cb => {
  cb.addEventListener('change', updateSelectedCount);
});

let _invAllSel = false;
function toggleSelectAllInventory() {
  _invAllSel = !_invAllSel;
  document.querySelectorAll('.inv-item:not([style*="display:none"]) .inv-cb').forEach(cb => cb.checked = _invAllSel);
  document.querySelector('[onclick="toggleSelectAllInventory()"]').textContent = _invAllSel ? 'Deselect All' : 'Select All';
}
function filterInventoryByProject(pid) {
  document.querySelectorAll('.inv-item').forEach(el => {
    el.style.display = (!pid || el.dataset.project == pid) ? '' : 'none';
  });
  _invAllSel = false;
}

let _mwrAllSel = false;
function toggleSelectAllMowers() {
  _mwrAllSel = !_mwrAllSel;
  document.querySelectorAll('.mower-cb').forEach(cb => cb.checked = _mwrAllSel);
  document.querySelector('[onclick="toggleSelectAllMowers()"]').textContent = _mwrAllSel ? 'Deselect All' : 'Select All';
}

// Confluence page search autocomplete
let _confSearchTimer = null;
function searchConfPages(inputEl, resultsId, hiddenId) {
  const q = inputEl.value.trim();
  const results = document.getElementById(resultsId);
  const hidden  = document.getElementById(hiddenId);
  const label   = document.getElementById(resultsId.replace('Results', 'Label'));

  // Clear selection when user types again
  hidden.value = '';
  if (label) { label.style.display = 'none'; label.textContent = ''; }

  clearTimeout(_confSearchTimer);
  if (q.length < 2) { results.style.display = 'none'; results.innerHTML = ''; return; }

  _confSearchTimer = setTimeout(() => {
    const space = document.querySelector('input[name="space_key"]')?.value.trim() || '';
    fetch('<?= url('api/confluence/search-pages') ?>?q=' + encodeURIComponent(q) + (space ? '&space=' + encodeURIComponent(space) : ''))
      .then(r => r.json())
      .then(pages => {
        if (!pages.length) {
          results.innerHTML = '<div class="list-group-item list-group-item-dark small text-muted py-2">No pages found</div>';
          results.style.display = '';
          return;
        }
        results.innerHTML = pages.map((p, i) =>
          `<button type="button" data-idx="${i}" class="list-group-item list-group-item-action list-group-item-dark py-2 px-3 small">
            <span class="fw-semibold"></span>
            <span class="text-muted ms-2" style="font-size:.7rem"></span>
          </button>`
        ).join('');
        results.querySelectorAll('button').forEach((btn, i) => {
          btn.querySelector('.fw-semibold').textContent = pages[i].title;
          btn.querySelector('.text-muted').textContent  = 'ID: ' + pages[i].id;
          btn.addEventListener('click', () => selectConfPage(pages[i].id, pages[i].title, inputEl.id, hiddenId, resultsId));
        });
        results.style.display = '';
      })
      .catch(() => { results.style.display = 'none'; });
  }, 300);
}

function selectConfPage(id, title, inputId, hiddenId, resultsId) {
  document.getElementById(inputId).value  = title;
  document.getElementById(hiddenId).value = id;
  const results = document.getElementById(resultsId);
  if (results) { results.style.display = 'none'; results.innerHTML = ''; }
  const labelId = resultsId.replace('Results', 'Label');
  const label   = document.getElementById(labelId);
  if (label) { label.textContent = 'Selected: ' + title + ' (ID: ' + id + ')'; label.style.display = ''; }
}

// Hide dropdowns when clicking outside
document.addEventListener('click', e => {
  if (!e.target.closest('#parentPageSearch, #parentPageResults, #existingPageSearch, #existingPageResults')) {
    document.getElementById('parentPageResults').style.display   = 'none';
    document.getElementById('existingPageResults').style.display = 'none';
  }
});

// Scroll to result after form submission
const _confResult = document.getElementById('confResult');
if (_confResult) _confResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
</script>
