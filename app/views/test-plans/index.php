<div class="d-flex justify-content-between align-items-center mb-4">
  <p class="text-muted mb-0 small"><?= count($plans) ?> Test Plans</p>
  <a href="<?= url('test-plans/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Test Plan</a>
</div>

<form method="GET" class="d-flex gap-2 mb-4">
  <select name="project_id" class="form-select form-select-sm" style="max-width:200px">
    <option value="">All Projects</option>
    <?php foreach ($projects as $p): ?>
    <option value="<?= $p['id'] ?>" <?= $projectId == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-secondary btn-sm">Filter</button>
</form>

<?php if (!$plans): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-clipboard display-4 mb-3 d-block"></i>
  <p>Keine Test Plans vorhanden.</p>
  <a href="<?= url('test-plans/create') ?>" class="btn btn-primary">Ersten Test Plan erstellen</a>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($plans as $p): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100 card-hover">
      <div class="card-body">
        <h6 class="fw-semibold mb-1"><a href="<?= url('test-plans/' . $p['id']) ?>" class="text-white text-decoration-none"><?= e($p['name']) ?></a></h6>
        <div class="text-muted small mb-3"><?= e($p['project_name']) ?></div>
        <?php if ($p['description']): ?><p class="text-muted small mb-2 text-truncate"><?= e($p['description']) ?></p><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center">
          <span class="badge bg-secondary"><?= $p['item_count'] ?> Items</span>
          <div class="d-flex gap-1">
            <a href="<?= url('test-plans/' . $p['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-pencil"></i></a>
            <?php if (Auth::isAdmin()): ?>
            <form method="POST" action="<?= url('test-plans/' . $p['id'] . '/delete') ?>" data-confirm="Delete test plan?">
              <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
