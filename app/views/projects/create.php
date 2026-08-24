<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('projects') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">New Project</h5>
</div>
<div class="card" style="max-width:800px">
  <div class="card-body p-4">
    <form method="POST" action="<?= url('projects/create') ?>">
      <?= csrfField() ?>
      <?php include __DIR__ . '/_form.php'; ?>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="<?= url('projects') ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
