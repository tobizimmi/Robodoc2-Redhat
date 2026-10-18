<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-runs/' . $run['id']) ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Edit Test Run</h5>
</div>
<div class="card" style="max-width:600px">
  <div class="card-body p-4">
    <form method="POST" action="<?= url('test-runs/' . $run['id'] . '/edit') ?>">
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="<?= e($run['name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['planned'=>'Planned','active'=>'Active','completed'=>'Completed','aborted'=>'Aborted'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $run['status']===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Environment</label>
        <input type="text" name="environment" class="form-control" value="<?= e($run['environment']) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($run['description']) ?></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="<?= url('test-runs/' . $run['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
    <!-- Standalone form, deliberately NOT nested inside the Save form above -
         a <form> inside another <form> is invalid HTML and browsers silently
         drop the inner tag, so this button would otherwise submit the Save
         form instead of ever reaching the delete route. -->
    <form method="POST" action="<?= url('test-runs/' . $run['id'] . '/delete') ?>" data-confirm="Delete test run?" class="mt-2 text-end">
      <?= csrfField() ?><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
    </form>
  </div>
</div>
