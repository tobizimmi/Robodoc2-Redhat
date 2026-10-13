<?php $csrf = Auth::csrfToken(); ?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= url('test-cycles/' . $cycle['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Edit Test Cycle</h5>
</div>
<div class="card" style="max-width:560px">
  <div class="card-body">
    <form method="POST" action="<?= url('test-cycles/' . $cycle['id'] . '/edit') ?>">
      <?= csrfField() ?>
      <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= e($cycle['name']) ?>" required></div>
      <div class="mb-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['planned'=>'Planned','active'=>'Active','completed'=>'Completed','aborted'=>'Aborted'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($cycle['status']??'')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="row g-2 mb-3">
        <div class="col"><label class="form-label small">Environment</label>
          <input type="text" name="environment" class="form-control" value="<?= e($cycle['environment']??'') ?>"></div>
        <div class="col"><label class="form-label small">Build</label>
          <input type="text" name="build" class="form-control" value="<?= e($cycle['build']??'') ?>"></div>
      </div>
      <div class="mb-3"><label class="form-label small">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($cycle['description']??'') ?></textarea></div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="<?= url('test-cycles/' . $cycle['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
