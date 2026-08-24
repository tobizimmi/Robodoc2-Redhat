<?php $isEdit = isset($project); ?>
<div class="row g-3">
  <div class="col-md-8">
    <div class="mb-3">
      <label class="form-label">Project Name <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
             value="<?= e($data['name'] ?? '') ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
    </div>
    <div class="mb-3">
      <label class="form-label">Project Number</label>
      <input type="text" name="project_number" class="form-control" value="<?= e($data['project_number'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="3"><?= e($data['description'] ?? '') ?></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">SharePoint Folder <span class="text-muted small">(optional ' used for file uploads)</span></label>
      <input type="text" name="sharepoint_folder" class="form-control" value="<?= e($data['sharepoint_folder'] ?? '') ?>"
             placeholder="e.g. RoboDoc/ProjectName/Attachments">
      <div class="form-text">Relative path in SharePoint drive root. Subfolders can be specified per upload.</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="mb-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="active"    <?= ($data['status'] ?? 'active') === 'active'    ? 'selected' : '' ?>>Active</option>
        <option value="archived"  <?= ($data['status'] ?? '') === 'archived'  ? 'selected' : '' ?>>Archived</option>
        <option value="completed" <?= ($data['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Color</label>
      <div class="d-flex align-items-center gap-2">
        <span id="colorPreview" class="rounded-circle" style="width:24px;height:24px;background:<?= e($data['color'] ?? '#4f46e5') ?>"></span>
        <input type="color" name="color" class="form-control form-control-color" data-preview="colorPreview"
               value="<?= e($data['color'] ?? '#4f46e5') ?>">
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary small fw-semibold">Milestones (optional)</div>
      <div class="card-body">
        <div class="row g-3">
          <?php foreach (['prototype_date' => 'Prototype', 'ep0_date' => 'EP0', 'ep1_date' => 'EP1', 'ep3_date' => 'EP3'] as $field => $label): ?>
          <div class="col-md-3">
            <label class="form-label small"><?= $label ?></label>
            <input type="date" name="<?= $field ?>" class="form-control form-control-sm"
                   value="<?= e($data[$field] ?? '') ?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Jira Project Destinations - only shown when editing an existing project -->
<?php
$_formProjectId = $project['id'] ?? null;
$_jiraConfigs = $_formProjectId
    ? Database::fetchAll('SELECT * FROM project_jira_configs WHERE project_id=? ORDER BY sort_order, id', [(int)$_formProjectId])
    : [];
?>
<?php if ($_formProjectId): ?>
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary d-flex align-items-center justify-content-between">
        <span class="small fw-semibold"><i class="bi bi-bug-fill text-warning me-2"></i>Jira Project Destinations</span>
        <button type="button" class="btn btn-outline-warning btn-sm" onclick="addJiraConfig()">
          <i class="bi bi-plus-lg me-1"></i>Add Jira Project
        </button>
      </div>
      <div class="card-body p-0">
        <?php if (!$_jiraConfigs): ?>
        <div class="text-muted small p-3">No Jira destinations configured yet. Click "Add Jira Project" to add one.</div>
        <?php else: ?>
        <table class="table table-dark table-sm mb-0" id="jiraConfigTable">
          <thead class="text-muted" style="font-size:.75rem">
            <tr><th>Jira Key</th><th>Label</th><th>Default Issue Type</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($_jiraConfigs as $jc): ?>
            <tr data-id="<?= $jc['id'] ?>">
              <td><code><?= e($jc['jira_project_key']) ?></code></td>
              <td><?= e($jc['label']) ?></td>
              <td><?= e($jc['issue_type']) ?></td>
              <td class="text-end">
                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2"
                        onclick="editJiraConfig(<?= $jc['id'] ?>,'<?= e(addslashes($jc['jira_project_key'])) ?>','<?= e(addslashes($jc['label'])) ?>','<?= e(addslashes($jc['issue_type'])) ?>')">
                  <i class="bi bi-pencil" style="font-size:.7rem"></i>
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2"
                        onclick="deleteJiraConfig(<?= $jc['id'] ?>,'<?= e(Auth::csrfToken()) ?>')">
                  <i class="bi bi-trash" style="font-size:.7rem"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Jira Config Modal -->
<div class="modal fade" id="jiraConfigModal" tabindex="-1">
  <div class="modal-dialog modal-sm"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary py-2">
      <h6 class="modal-title" id="jiraConfigModalTitle">Jira Project</h6>
      <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="jcId">
      <div class="mb-2">
        <label class="form-label small">Jira Project Key <span class="text-danger">*</span></label>
        <input type="text" id="jcKey" class="form-control form-control-sm" placeholder="e.g. GRSPT" style="text-transform:uppercase">
      </div>
      <div class="mb-2">
        <label class="form-label small">Label <span class="text-muted">(shown in dropdown)</span></label>
        <input type="text" id="jcLabel" class="form-control form-control-sm" placeholder="e.g. Husqvarna GRSPT">
      </div>
      <div class="mb-2">
        <label class="form-label small">Default Issue Type</label>
        <input type="text" id="jcType" class="form-control form-control-sm" value="Bug">
      </div>
    </div>
    <div class="modal-footer border-secondary py-2">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-warning btn-sm" onclick="saveJiraConfig('<?= e(Auth::csrfToken()) ?>')">Save</button>
    </div>
  </div></div>
</div>

<script>
var _jcProjectId = <?= (int)$_formProjectId ?>;
var _jcBase = '<?= url('projects/') ?>' + _jcProjectId + '/jira-configs';

function addJiraConfig() {
  document.getElementById('jcId').value = '';
  document.getElementById('jcKey').value = '';
  document.getElementById('jcLabel').value = '';
  document.getElementById('jcType').value = 'Bug';
  document.getElementById('jiraConfigModalTitle').textContent = 'Add Jira Project';
  new bootstrap.Modal(document.getElementById('jiraConfigModal')).show();
}

function editJiraConfig(id, key, label, type) {
  document.getElementById('jcId').value = id;
  document.getElementById('jcKey').value = key;
  document.getElementById('jcLabel').value = label;
  document.getElementById('jcType').value = type;
  document.getElementById('jiraConfigModalTitle').textContent = 'Edit Jira Project';
  new bootstrap.Modal(document.getElementById('jiraConfigModal')).show();
}

function saveJiraConfig(csrf) {
  var id    = document.getElementById('jcId').value;
  var key   = document.getElementById('jcKey').value.trim().toUpperCase();
  var label = document.getElementById('jcLabel').value.trim() || key;
  var type  = document.getElementById('jcType').value.trim() || 'Bug';
  if (!key) { alert('Jira Project Key required'); return; }
  var configs = [];
  document.querySelectorAll('#jiraConfigTable tbody tr').forEach(function(row) {
    if (row.dataset.id == id) return;
    var cells = row.querySelectorAll('td');
    configs.push({
      jira_project_key: cells[0]?.textContent?.trim(),
      label: cells[1]?.textContent?.trim(),
      issue_type: cells[2]?.textContent?.trim()
    });
  });
  configs.push({jira_project_key: key, label: label, issue_type: type});
  fetch(_jcBase, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, configs: JSON.stringify(configs)})
  }).then(function(r){return r.json();}).then(function(d){
    if (d.success) { bootstrap.Modal.getInstance(document.getElementById('jiraConfigModal')).hide(); location.reload(); }
    else alert(d.error || 'Error');
  });
}

function deleteJiraConfig(id, csrf) {
  if (!confirm('Remove this Jira destination?')) return;
  var configs = [];
  document.querySelectorAll('#jiraConfigTable tbody tr').forEach(function(row) {
    if (row.dataset.id == id) return;
    var cells = row.querySelectorAll('td');
    configs.push({
      jira_project_key: cells[0]?.textContent?.trim(),
      label: cells[1]?.textContent?.trim(),
      issue_type: cells[2]?.textContent?.trim()
    });
  });
  fetch(_jcBase, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, configs: JSON.stringify(configs)})
  }).then(function(r){return r.json();}).then(function(d){
    if (d.success) location.reload();
    else alert(d.error || 'Error');
  });
}
</script>
<?php endif; ?>
