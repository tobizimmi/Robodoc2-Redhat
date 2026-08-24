<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-plans/' . $plan['id']) ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Edit Test Plan</h5>
</div>
<div class="card" style="max-width:600px">
  <div class="card-body p-4">
    <form method="POST" action="<?= url('test-plans/' . $plan['id'] . '/edit') ?>">
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Projekt</label>
        <select name="project_id" class="form-select">
          <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $plan['project_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="<?= e($plan['name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($plan['description']) ?></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="<?= url('test-plans/' . $plan['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
