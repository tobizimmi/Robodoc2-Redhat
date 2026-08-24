<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Jira Settings</h5>
</div>

<form method="POST" action="<?= url('admin/jira') ?>">
  <?= csrfField() ?>

  <!-- Templates -->
  <div class="card mb-4" style="max-width:860px">
    <div class="card-header border-secondary fw-semibold small">
      <i class="bi bi-file-text me-1"></i>Issue Templates
      <span class="text-muted fw-normal ms-2">System defaults — users can override in their profile</span>
    </div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Title Template</label>
        <input type="text" name="jira_default_title_template" class="form-control font-monospace"
               value="<?= e($s['jira_default_title_template'] ?? '[{{type}}] {{title}}') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Description Template</label>
        <textarea name="jira_default_desc_template" class="form-control font-monospace" rows="12"><?= e($s['jira_default_desc_template'] ?? "*Type:* {{type}}\n*Category:* {{category}}\n*Project:* {{project}}\n*Project Status:* {{project_status}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*App Version:* {{app_version}}\n*Environment:* {{environment}}\n*Test Area:* {{test_area}}\n*Date:* {{date}} {{time}}\n*Creator:* {{creator}}\n\n{{description}}") ?></textarea>
        <div class="form-text mt-2">
          Available variables:
          <code>{{id}}</code> <code>{{type}}</code> <code>{{title}}</code> <code>{{description}}</code>
          <code>{{serial}}</code> <code>{{firmware}}</code> <code>{{app_version}}</code>
          <code>{{project}}</code> <code>{{project_status}}</code> <code>{{category}}</code>
          <code>{{environment}}</code> <code>{{test_area}}</code> <code>{{date}}</code> <code>{{time}}</code>
          <code>{{creator}}</code> <code>{{sharepoint}}</code> <code>{{temperature}}</code> <code>{{weather}}</code> <code>{{status}}</code> <code>{{attachments}}</code>
        </div>
      </div>
    </div>
  </div>

  <!-- Field Mapping -->
  <div class="card mb-4" style="max-width:860px">
    <div class="card-header border-secondary d-flex align-items-center gap-2">
      <span class="fw-semibold small"><i class="bi bi-diagram-3 me-1"></i>Field Mapping</span>
      <span class="text-muted small fw-normal">Map tool fields directly to Jira custom fields (sent alongside the description)</span>
      <button type="button" class="btn btn-outline-secondary btn-sm ms-auto py-0 px-2" id="loadFieldsBtn"
              onclick="loadJiraFields()">
        <i class="bi bi-arrow-repeat me-1"></i>Load Jira Fields
      </button>
    </div>
    <div class="card-body p-0">
      <div id="fieldsLoadError" class="alert alert-warning m-3 py-2 small d-none"></div>
      <table class="table table-sm mb-0 small align-middle">
        <thead class="table-dark">
          <tr>
            <th class="ps-3" style="width:28%">Tool Field</th>
            <th style="width:45%">Jira Field</th>
            <th class="pe-3" style="width:27%">Value Type</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mappableFields as $localField => $label):
            $conf      = $mapping[$localField] ?? null;
            $savedId   = is_array($conf) ? ($conf['id']   ?? '') : (string)($conf ?? '');
            $savedType = is_array($conf) ? ($conf['type'] ?? 'text') : 'text';
          ?>
          <tr>
            <td class="ps-3 fw-semibold"><?= e($label) ?></td>
            <td>
              <select name="fm_id_<?= e($localField) ?>" class="form-select form-select-sm jira-field-select"
                      data-current="<?= e($savedId) ?>">
                <option value="">— not mapped —</option>
                <?php if ($savedId): ?>
                <option value="<?= e($savedId) ?>" selected><?= e($savedId) ?> (click Load to see name)</option>
                <?php endif; ?>
              </select>
            </td>
            <td class="pe-3">
              <select name="fm_type_<?= e($localField) ?>" class="form-select form-select-sm fm-type-select"
                      data-field="<?= e($localField) ?>">
                <option value="text"        <?= $savedType === 'text'        ? 'selected' : '' ?>>Text / Number</option>
                <option value="select"      <?= $savedType === 'select'      ? 'selected' : '' ?>>Single Select</option>
                <option value="multiselect" <?= $savedType === 'multiselect' ? 'selected' : '' ?>>Multi-Select</option>
                <option value="version"     <?= $savedType === 'version'     ? 'selected' : '' ?>>Version / Fix Version</option>
                <option value="labels"      <?= $savedType === 'labels'      ? 'selected' : '' ?>>Labels (string array)</option>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="card-footer bg-transparent border-secondary">
        <div class="form-text">
          <strong>Text / Number</strong> — sends plain string (text, number, date fields).<br>
          <strong>Single Select</strong> — sends <code>{"value": "…"}</code> (Jira select lists).<br>
          <strong>Multi-Select</strong> — sends <code>[{"value": "…"}]</code> (multi-select, checkboxes).<br>
          <strong>Version / Fix Version</strong> — sends <code>[{"name": "…"}]</code> (Affects Version/s, Fix Version/s).<br>
          <strong>Labels</strong> — sends <code>["…"]</code> (Jira labels field).<br>
          Mapped fields are sent alongside the description. Remove matching lines from the template above to avoid duplication.
        </div>
      </div>
    </div>
  </div>

  <!-- Status Mapping: Jira status → local entry status -->
  <div class="card mb-4" style="max-width:860px">
    <div class="card-header border-secondary fw-semibold small">
      <i class="bi bi-arrow-left-right me-1"></i>Status Mapping (Jira → RoboDoc)
      <span class="text-muted fw-normal ms-2 small">When a Jira status is synced, map it to the corresponding RoboDoc status.</span>
    </div>
    <div class="card-body p-4">
      <?php
      $jiraStatusMap  = json_decode($s['jira_status_map'] ?? '{}', true) ?: [];
      $jiraStatuses   = ['To Do','In Progress','In Review','Done','Closed','Resolved','Won\'t Do','Blocked','Reopened'];
      $localStatuses  = entryStatuses();
      $defaults       = ['To Do'=>'new','In Progress'=>'internal','In Review'=>'reviewed','Done'=>'finished','Closed'=>'finished','Resolved'=>'finished','Won\'t Do'=>'rejected','Blocked'=>'pending_at_supplier','Reopened'=>'new'];
      ?>
      <?php
      // Merge fixed statuses + any extra custom entries already saved
      $allJiraStatuses = $jiraStatuses;
      foreach (array_keys($jiraStatusMap) as $saved) {
          if (!in_array($saved, $allJiraStatuses, true)) $allJiraStatuses[] = $saved;
      }
      ?>
      <table class="table table-sm small align-middle" style="max-width:580px" id="statusMapTable">
        <thead class="table-dark"><tr><th>Jira Status</th><th>RoboDoc Status</th><th style="width:36px"></th></tr></thead>
        <tbody id="statusMapBody">
          <?php foreach ($allJiraStatuses as $js):
            $isCustom = !in_array($js, $jiraStatuses, true);
          ?>
          <tr>
            <td>
              <?php if ($isCustom): ?>
              <input type="text" name="jira_status_map_keys[]" value="<?= e($js) ?>" class="form-control form-control-sm">
              <?php else: ?>
              <span class="fw-semibold"><?= e($js) ?></span>
              <input type="hidden" name="jira_status_map_keys[]" value="<?= e($js) ?>">
              <?php endif; ?>
            </td>
            <td>
              <select name="jira_status_map_vals[]" class="form-select form-select-sm">
                <?php foreach ($localStatuses as $lv => $ll): ?>
                <option value="<?= $lv ?>" <?= ($jiraStatusMap[$js] ?? $defaults[$js] ?? 'new') === $lv ? 'selected' : '' ?>><?= e($ll) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <?php if ($isCustom): ?>
              <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1"
                      onclick="this.closest('tr').remove()" title="Remove">
                <i class="bi bi-x-lg"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addStatusRow()">
        <i class="bi bi-plus-lg me-1"></i>Add custom Jira status
      </button>
      <div class="form-text mt-2">Add any Jira status names specific to your project (e.g. "Ready for Test", "In QA"). The slug-normalization fallback handles names that match a RoboDoc status automatically even without an explicit entry here.</div>
    </div>
  </div>

  <!-- Priority Mapping: RoboDoc priority → Jira priority name -->
  <div class="card mb-4" style="max-width:860px">
    <div class="card-header border-secondary fw-semibold small">
      <i class="bi bi-flag me-1"></i>Priority Mapping (RoboDoc → Jira)
      <span class="text-muted fw-normal ms-2 small">Only needed if this Jira project's priority names differ from RoboDoc's.</span>
    </div>
    <div class="card-body p-4">
      <?php $jiraPriMap = $jiraPriMap ?? []; ?>
      <div class="form-text mb-3">
        Wenn hier nichts eingetragen ist, wird der RoboDoc-Prioritätsname (z.B. "Medium") unverändert als
        Jira-Prioritätsname gesendet. Verwendet dieses Jira-Projekt andere Namen (z.B. Zahlen wie "3.0"),
        muss hier der exakte Jira-Name eingetragen werden — sonst lehnt Jira das Feld ab und die Priorität
        wird beim Push nicht übernommen.
      </div>
      <table class="table table-sm small align-middle mb-0" style="max-width:520px">
        <thead class="table-dark"><tr><th>RoboDoc Priority</th><th>Jira Priority Name</th></tr></thead>
        <tbody>
          <?php foreach (['Low','Medium','High','Highest','Blocker'] as $pl): ?>
          <tr>
            <td class="fw-semibold"><?= $pl ?></td>
            <td>
              <input type="text" name="jira_priority_map[<?= $pl ?>]" class="form-control form-control-sm"
                     value="<?= e($jiraPriMap[$pl] ?? '') ?>" placeholder="<?= $pl ?> (unverändert)">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sync configuration -->
  <div class="card mb-4" style="max-width:860px">
    <div class="card-header border-secondary fw-semibold small">
      <i class="bi bi-arrow-repeat me-1"></i>Sync Configuration
    </div>
    <div class="card-body p-4">
      <?php
      $jiraQuick = json_decode($s['jira_quick_sync_fields'] ?? '["status","priority"]', true) ?: ['status','priority'];
      $jiraFull  = json_decode($s['jira_full_sync_fields']  ?? '["status","priority","description","comments"]', true) ?: ['status','priority','description','comments'];
      $syncFields = ['status'=>'Status','priority'=>'Priority','description'=>'Description','comments'=>'Comments','attachments'=>'Attachments'];
      ?>
      <div class="row g-4">
        <div class="col-md-6">
          <div class="fw-semibold small mb-2"><i class="bi bi-lightning text-warning me-1"></i>Quick Sync checks</div>
          <div class="text-muted small mb-2">Fast per-entry check — compares these fields only.</div>
          <?php foreach ($syncFields as $sf => $sl): ?>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" name="jira_quick_sync_fields[]"
                   value="<?= $sf ?>" id="jqs_<?= $sf ?>" <?= in_array($sf,$jiraQuick)?'checked':'' ?>>
            <label class="form-check-label small" for="jqs_<?= $sf ?>"><?= $sl ?></label>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="col-md-6">
          <div class="fw-semibold small mb-2"><i class="bi bi-arrow-repeat text-info me-1"></i>Full Sync checks</div>
          <div class="text-muted small mb-2">Comprehensive check — compares all selected fields.</div>
          <?php foreach ($syncFields as $sf => $sl): ?>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" name="jira_full_sync_fields[]"
                   value="<?= $sf ?>" id="jfs_<?= $sf ?>" <?= in_array($sf,$jiraFull)?'checked':'' ?>>
            <label class="form-check-label small" for="jfs_<?= $sf ?>"><?= $sl ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div style="max-width:860px">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
  </div>
</form>

<script>
let _jiraFieldsLoaded = false;

function loadJiraFields() {
  const btn = document.getElementById('loadFieldsBtn');
  const errEl = document.getElementById('fieldsLoadError');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading…';
  errEl.classList.add('d-none');

  fetch('<?= url('api/jira/fields') ?>')
    .then(r => r.json())
    .then(data => {
      if (data.error) { throw new Error(data.error); }
      const fields = data.fields || [];
      document.querySelectorAll('.jira-field-select').forEach(sel => {
        const current = sel.dataset.current || sel.value;
        sel.innerHTML = '<option value="">— not mapped —</option>';
        fields.forEach(f => {
          const opt = new Option(f.name + '  (' + f.id + ')', f.id, false, f.id === current);
          sel.appendChild(opt);
        });
        if (current) sel.value = current;
      });
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>' + fields.length + ' fields loaded';
      btn.disabled = false;
      _jiraFieldsLoaded = true;
    })
    .catch(err => {
      errEl.textContent = 'Could not load Jira fields: ' + err.message + '. Make sure Jira URL is configured and your API token is set in your profile.';
      errEl.classList.remove('d-none');
      btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Retry';
      btn.disabled = false;
    });
}

// Auto-load if Jira is configured
<?php if (!empty($s['jira_url'])): ?>
loadJiraFields();
<?php endif; ?>

// ── Add custom status row ──────────────────────────────────
const _localStatuses = <?= json_encode(array_map(fn($v, $l) => ['value' => $v, 'label' => $l], array_keys(entryStatuses()), array_values(entryStatuses())), JSON_UNESCAPED_UNICODE) ?>;

function addStatusRow() {
  const tbody = document.getElementById('statusMapBody');
  const opts = _localStatuses.map(s => `<option value="${s.value}">${s.label}</option>`).join('');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" name="jira_status_map_keys[]" class="form-control form-control-sm" placeholder="e.g. Ready for Test"></td>
    <td><select name="jira_status_map_vals[]" class="form-select form-select-sm">${opts}</select></td>
    <td><button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="this.closest('tr').remove()"><i class="bi bi-x-lg"></i></button></td>`;
  tbody.appendChild(tr);
  tr.querySelector('input').focus();
}
</script>
