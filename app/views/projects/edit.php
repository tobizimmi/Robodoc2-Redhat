<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('projects/' . $project['id']) ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Edit Project</h5>
</div>
<div class="card" style="max-width:800px">
  <div class="card-body p-4">
    <form method="POST" action="<?= url('projects/' . $project['id'] . '/edit') ?>">
      <?= csrfField() ?>
      <?php include __DIR__ . '/_form.php'; ?>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="<?= url('projects/' . $project['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
        <?php if (Auth::isAdmin()): ?>
        <form method="POST" action="<?= url('projects/' . $project['id'] . '/delete') ?>" data-confirm="Delete project and all entries?" class="ms-auto">
          <?= csrfField() ?>
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
        </form>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
