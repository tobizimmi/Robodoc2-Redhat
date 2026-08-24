<div class="mb-4 d-flex align-items-center gap-2">
  <a href="<?= url('epics') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Edit Epic</h5>
</div>
<div class="card" style="max-width:600px">
  <div class="card-body">
    <form method="POST" action="<?= url('epics/'.$epic['id']) ?>">
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label small">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" required value="<?= e($epic['title']) ?>">
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-8">
          <label class="form-label small">Project</label>
          <select name="project_id" class="form-select">
            <option value="">-- All Projects --</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $p['id']==$epic['project_id']?'selected':'' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small">Color</label>
          <input type="color" name="color" class="form-control form-control-color w-100" value="<?= e($epic['color']) ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label small">Jira Epic Key</label>
        <input type="text" name="jira_epic_key" class="form-control" value="<?= e($epic['jira_epic_key'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label small">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($epic['description'] ?? '') ?></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
        <a href="<?= url('epics') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
      </div>
    </form>
  </div>
</div>
