<?php $csrf = Auth::csrfToken(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h5 class="mb-0"><i class="bi bi-file-earmark-richtext me-2 text-info"></i>Export Templates</h5>
  <button class="btn btn-primary btn-sm" onclick="openTemplateModal(0)">
    <i class="bi bi-plus-lg me-1"></i>New Template
  </button>
</div>

<div class="row g-3">
  <?php foreach ($templates as $tpl): ?>
  <div class="col-md-4">
    <div class="card border-secondary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="rounded-circle d-inline-block" style="width:18px;height:18px;background:<?= e($tpl['primary_color']) ?>"></span>
          <span class="fw-semibold"><?= e($tpl['name']) ?></span>
          <?php if ($tpl['is_default']): ?>
          <span class="badge bg-success ms-auto">Default</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($tpl['description'])): ?>
        <p class="text-muted small mb-2"><?= e($tpl['description']) ?></p>
        <?php endif; ?>
        <div class="d-flex gap-1 flex-wrap">
          <span class="badge" style="background:<?= e($tpl['primary_color']) ?>"><?= e($tpl['primary_color']) ?></span>
          <span class="badge" style="background:<?= e($tpl['accent_color']) ?>"><?= e($tpl['accent_color']) ?></span>
        </div>
      </div>
      <div class="card-footer border-secondary d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm flex-grow-1" onclick="openTemplateModal(<?= $tpl['id'] ?>)">
          <i class="bi bi-pencil me-1"></i>Edit
        </button>
        <?php if (!$tpl['is_default']): ?>
        <form method="POST" action="<?= url('admin/export-templates/'.$tpl['id'].'/delete') ?>"
              onsubmit="return confirm('Delete this template?')">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Template Modal -->
<div class="modal fade" id="tplModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <form method="POST" action="<?= url('admin/export-templates/save') ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="id" id="tplId">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="tplModalTitle">Export Template</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name *</label>
              <input type="text" name="name" id="tplName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Description</label>
              <input type="text" name="description" id="tplDesc" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Primary Color</label>
              <input type="color" name="primary_color" id="tplPrimary" class="form-control form-control-color w-100" value="#1e3a5f">
            </div>
            <div class="col-md-4">
              <label class="form-label">Accent Color</label>
              <input type="color" name="accent_color" id="tplAccent" class="form-control form-control-color w-100" value="#3b82f6">
            </div>
            <div class="col-md-4">
              <label class="form-label">Font</label>
              <select name="font_family" id="tplFont" class="form-select">
                <option value="Arial, sans-serif">Arial</option>
                <option value="'Helvetica Neue', Helvetica, sans-serif">Helvetica</option>
                <option value="Georgia, serif">Georgia</option>
                <option value="'Times New Roman', serif">Times New Roman</option>
                <option value="'Segoe UI', system-ui, sans-serif">Segoe UI</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Logo <span class="text-muted small">(PNG/JPG/SVG)</span></label>
              <input type="file" name="logo" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
              <label class="form-label">Header HTML <span class="text-muted small">(optional)</span></label>
              <textarea name="header_html" id="tplHeader" class="form-control font-monospace" rows="3"
                        placeholder="<h1>My Company</h1><p>Test Report</p>"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Footer HTML <span class="text-muted small">(optional)</span></label>
              <textarea name="footer_html" id="tplFooter" class="form-control font-monospace" rows="3"
                        placeholder="<span>Confidential</span><span>Page 1</span>"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Default Fields</label>
              <div class="row g-2">
                <?php $fieldLabels = [
                  'description'=>'Description','metadata'=>'Metadata','attachments'=>'Attachments',
                  'images'=>'Images (embedded)','comments'=>'Comments','test_results'=>'Test Results',
                  'jira_info'=>'Jira Info','history'=>'Change History','sub_entries'=>'Sub-Entries'
                ]; ?>
                <?php foreach ($fieldLabels as $key => $label): ?>
                <div class="col-md-4">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="df_<?= $key ?>"
                           id="df_<?= $key ?>" checked>
                    <label class="form-check-label small" for="df_<?= $key ?>"><?= $label ?></label>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" name="is_default" id="tplDefault">
                <label class="form-check-label" for="tplDefault">Set as default template</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Template</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const _tplData = <?= json_encode(array_column($templates, null, 'id')) ?>;
function openTemplateModal(id) {
  const tpl = _tplData[id] || {};
  document.getElementById('tplId').value = id || '';
  document.getElementById('tplModalTitle').textContent = id ? 'Edit Template' : 'New Template';
  document.getElementById('tplName').value    = tpl.name || '';
  document.getElementById('tplDesc').value    = tpl.description || '';
  document.getElementById('tplPrimary').value = tpl.primary_color || '#1e3a5f';
  document.getElementById('tplAccent').value  = tpl.accent_color  || '#3b82f6';
  document.getElementById('tplFont').value    = tpl.font_family || 'Arial, sans-serif';
  document.getElementById('tplHeader').value  = tpl.header_html || '';
  document.getElementById('tplFooter').value  = tpl.footer_html || '';
  document.getElementById('tplDefault').checked = !!tpl.is_default;
  const fields = JSON.parse(tpl.default_fields || '{}');
  ['description','metadata','attachments','images','comments','test_results','jira_info','history','sub_entries'].forEach(k => {
    const cb = document.getElementById('df_' + k);
    if (cb) cb.checked = fields[k] !== undefined ? !!fields[k] : (k !== 'history' && k !== 'sub_entries');
  });
  new bootstrap.Modal(document.getElementById('tplModal')).show();
}
</script>
