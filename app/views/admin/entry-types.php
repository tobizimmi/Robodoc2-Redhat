<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Entry Types</h5>
</div>
<div class="row g-4">
  <div class="col-md-5">
    <div class="card"><div class="card-header border-secondary fw-semibold small">New Type</div>
      <div class="card-body">
        <form method="POST" action="<?= url('admin/entry-types') ?>">
          <?= csrfField() ?><input type="hidden" name="action" value="create">
          <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
          <div class="mb-2 d-flex gap-2">
            <div class="flex-grow-1"><label class="form-label small">Color</label><input type="color" name="color" class="form-control form-control-color form-control-sm" value="#6366f1"></div>
            <div class="flex-grow-1"><label class="form-label small">Icon</label><input type="text" name="icon" class="form-control form-control-sm" value="tag" placeholder="Bootstrap icon name"></div>
            <div style="width:70px"><label class="form-label small">Order</label><input type="number" name="sort_order" class="form-control form-control-sm" value="0"></div>
          </div>
          <button class="btn btn-primary btn-sm w-100">Create</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card"><div class="card-header border-secondary fw-semibold small">Existing Types</div>
      <div class="list-group list-group-flush">
        <?php foreach ($types as $t): ?>
        <div class="list-group-item bg-transparent border-secondary py-2 px-3">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <span class="badge" style="background:<?= e($t['color']) ?>"><?= e($t['name']) ?></span>
              <small class="text-muted">icon: <?= e($t['icon']) ?></small>
            </div>
            <div class="d-flex gap-1">
              <button class="btn btn-outline-secondary btn-sm py-0 px-1" data-bs-toggle="collapse" data-bs-target="#edit-type-<?= $t['id'] ?>"><i class="bi bi-pencil"></i></button>
              <form method="POST" action="<?= url('admin/entry-types/' . $t['id'] . '/delete') ?>" data-confirm="Delete type?">
                <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
          <div class="collapse mt-2" id="edit-type-<?= $t['id'] ?>">
            <form method="POST" action="<?= url('admin/entry-types') ?>">
              <?= csrfField() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $t['id'] ?>">
              <div class="row g-2">
                <div class="col-5"><input type="text" name="name" class="form-control form-control-sm" value="<?= e($t['name']) ?>" required></div>
                <div class="col-3"><input type="color" name="color" class="form-control form-control-color form-control-sm" value="<?= e($t['color']) ?>"></div>
                <div class="col-2"><input type="text" name="icon" class="form-control form-control-sm" value="<?= e($t['icon']) ?>"></div>
                <div class="col-1"><input type="number" name="sort_order" class="form-control form-control-sm" value="<?= $t['sort_order'] ?>"></div>
                <div class="col-1"><button class="btn btn-success btn-sm w-100"><i class="bi bi-check"></i></button></div>
              </div>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
