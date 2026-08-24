<div class="d-flex justify-content-between align-items-center mb-4">
  <p class="text-muted mb-0 small"><?= count($projects) ?> projects</p>
  <a href="<?= url('projects/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Project
  </a>
</div>

<form method="GET" class="d-flex gap-2 mb-4 flex-wrap">
  <input type="text" name="search" class="form-control form-control-sm" style="max-width:220px"
         placeholder="Search…" value="<?= e($search) ?>">
  <select name="status" class="form-select form-select-sm" style="max-width:150px">
    <option value="">All Status</option>
    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
    <option value="archived"  <?= $status === 'archived'  ? 'selected' : '' ?>>Archived</option>
    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
  </select>
  <button class="btn btn-outline-secondary btn-sm">Filter</button>
  <?php if ($search || $status): ?>
  <a href="<?= url('projects') ?>" class="btn btn-outline-danger btn-sm">Reset</a>
  <?php endif; ?>
</form>

<?php if (!$projects): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-folder display-4 mb-3 d-block"></i>
  <p>No projects found.</p>
  <a href="<?= url('projects/create') ?>" class="btn btn-primary">Create first project</a>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($projects as $p): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100 card-hover">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-2">
          <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle" style="width:14px;height:14px;background:<?= e($p['color']) ?>;display:inline-block;flex-shrink:0"></span>
            <a href="<?= url('projects/' . $p['id']) ?>" class="fw-semibold text-decoration-none text-white"><?= e($p['name']) ?></a>
          </div>
          <?php
          $statusMap = ['active' => ['Active','success'], 'archived' => ['Archived','secondary'], 'completed' => ['Completed','info']];
          [$sl, $sc] = $statusMap[$p['status']] ?? ['?', 'secondary'];
          ?>
          <span class="badge bg-<?= $sc ?>"><?= $sl ?></span>
        </div>
        <?php if ($p['project_number']): ?>
        <div class="text-muted small mb-1"><?= e($p['project_number']) ?></div>
        <?php endif; ?>
        <?php if ($p['description']): ?>
        <p class="text-muted small mb-2 text-truncate"><?= e($p['description']) ?></p>
        <?php endif; ?>
        <div class="d-flex align-items-center justify-content-between mt-3">
          <div class="text-muted small">
            <i class="bi bi-journal-text me-1"></i><?= $p['entry_count'] ?> entries
            <?php if ($p['last_entry']): ?>
            &middot; last <?= formatDate($p['last_entry']) ?>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-1">
            <a href="<?= url('projects/' . $p['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">
              <i class="bi bi-pencil"></i>
            </a>
            <?php if (Auth::isAdmin()): ?>
            <form method="POST" action="<?= url('projects/' . $p['id'] . '/delete') ?>" data-confirm="Delete project and all entries?">
              <?= csrfField() ?>
              <button class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-trash"></i></button>
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
