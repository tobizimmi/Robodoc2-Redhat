<div class="d-flex justify-content-between align-items-center mb-4">
  <p class="text-muted mb-0 small"><?= count($reqs) ?> Requirements</p>
  <a href="<?= url('requirements/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Requirement</a>
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

<div class="card">
  <div class="table-responsive">
    <table class="table table-dark table-hover mb-0 align-middle">
      <thead><tr><th>Name</th><th>Projekt</th><th>Status</th><th>Priority</th><th></th></tr></thead>
      <tbody>
        <?php if (!$reqs): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Keine Requirements. <a href="<?= url('requirements/create') ?>">Erste anlegen.</a></td></tr>
        <?php endif; ?>
        <?php foreach ($reqs as $r): ?>
        <?php
        $statColors = ['planning'=>'secondary','approved'=>'info','in_progress'=>'primary','completed'=>'success','cancelled'=>'danger'];
        $prioColors = ['low'=>'secondary','medium'=>'warning','high'=>'orange','critical'=>'danger'];
        ?>
        <tr>
          <td><span class="fw-semibold"><?= e($r['name']) ?></span>
            <?php if ($r['description']): ?><div class="text-muted small text-truncate"><?= e($r['description']) ?></div><?php endif; ?>
          </td>
          <td class="small text-muted"><?= e($r['project_name'] ?: '—') ?></td>
          <td><span class="badge bg-<?= $statColors[$r['status']] ?? 'secondary' ?>"><?= e($r['status']) ?></span></td>
          <td><span class="badge bg-<?= $prioColors[$r['priority']] ?? 'secondary' ?>"><?= e($r['priority']) ?></span></td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= url('requirements/' . $r['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-pencil"></i></a>
              <form method="POST" action="<?= url('requirements/' . $r['id'] . '/delete') ?>" data-confirm="Delete requirement?">
                <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
