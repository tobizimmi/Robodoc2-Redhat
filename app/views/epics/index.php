<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-lightning-fill text-warning me-2"></i>Epics</h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createEpicModal">
    <i class="bi bi-plus-lg me-1"></i>New Epic
  </button>
</div>

<?php if (!$epics): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-lightning-fill fs-1 d-block mb-2 opacity-25"></i>
  No epics yet. Create one to group related entries.
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($epics as $ep): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100" style="border-left:4px solid <?= e($ep['color']) ?>">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div>
            <div class="fw-semibold"><?= e($ep['title']) ?></div>
            <?php if ($ep['project_name']): ?>
            <div class="small text-muted mt-1">
              <span class="color-dot" style="background:<?= e($ep['project_color']) ?>"></span>
              <?= e($ep['project_name']) ?>
            </div>
            <?php endif; ?>
            <?php if ($ep['jira_epic_key']): ?>
            <div class="small text-warning mt-1"><i class="bi bi-bug-fill me-1"></i><?= e($ep['jira_epic_key']) ?></div>
            <?php endif; ?>
            <?php if ($ep['description']): ?>
            <div class="small text-muted mt-2"><?= e(mb_substr($ep['description'],0,100)) ?></div>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-1 flex-shrink-0">
            <a href="<?= url('entries?epic_id='.$ep['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" title="View entries">
              <span class="badge bg-secondary" style="font-size:.65rem"><?= $ep['entry_count'] ?></span>
            </a>
            <a href="<?= url('epics/'.$ep['id'].'/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-pencil" style="font-size:.7rem"></i></a>
            <form method="POST" action="<?= url('epics/'.$ep['id'].'/delete') ?>" data-confirm="Delete this epic? Entries will NOT be deleted.">
              <?= csrfField() ?>
              <button class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-trash" style="font-size:.7rem"></i></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Epic Modal -->
<div class="modal fade" id="createEpicModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-lightning-fill text-warning me-2"></i>New Epic</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" action="<?= url('epics') ?>">
      <?= csrfField() ?>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" required placeholder="e.g. Navigation Improvements">
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="form-label small">Project</label>
            <select name="project_id" class="form-select">
              <option value="">-- All Projects --</option>
              <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Color</label>
            <input type="color" name="color" class="form-control form-control-color w-100" value="#8b5cf6">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Jira Epic Key <span class="text-muted">(optional)</span></label>
          <input type="text" name="jira_epic_key" class="form-control" placeholder="e.g. PROJ-123">
        </div>
        <div class="mb-3">
          <label class="form-label small">Description</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Create Epic</button>
      </div>
    </form>
  </div></div>
</div>
