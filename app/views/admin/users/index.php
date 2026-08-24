<div class="d-flex justify-content-between align-items-center mb-4">
  <a href="<?= url('admin') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Admin</a>
  <a href="<?= url('admin/users/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New User</a>
</div>

<?php if ($pending): ?>
<div class="card border-warning mb-4">
  <div class="card-header border-warning d-flex align-items-center gap-2">
    <i class="bi bi-person-exclamation text-warning"></i>
    <span class="fw-semibold">Pending Approval</span>
    <span class="badge bg-warning text-dark ms-1"><?= count($pending) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-dark table-hover mb-0 align-middle">
      <thead><tr><th>Name</th><th>Email</th><th>Registered</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pending as $u): ?>
        <tr>
          <td class="fw-semibold"><?= e($u['name']) ?></td>
          <td class="text-muted small"><?= e($u['email']) ?></td>
          <td class="text-muted small"><?= formatDate($u['created_at']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <form method="POST" action="<?= url('admin/users/' . $u['id'] . '/approve') ?>">
                <?= csrfField() ?>
                <button class="btn btn-success btn-sm py-0 px-2" title="Approve"><i class="bi bi-check-lg"></i> Approve</button>
              </form>
              <form method="POST" action="<?= url('admin/users/' . $u['id'] . '/reject') ?>" data-confirm="Reject and delete this registration?">
                <?= csrfField() ?>
                <button class="btn btn-outline-danger btn-sm py-0 px-2" title="Reject"><i class="bi bi-x-lg"></i> Reject</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header border-secondary fw-semibold small">Active Users</div>
  <div class="table-responsive">
    <table class="table table-dark table-hover mb-0 align-middle">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td class="fw-semibold"><?= e($u['name']) ?></td>
          <td class="text-muted small"><?= e($u['email']) ?></td>
          <td><span class="badge <?= $u['role']==='admin' ? 'bg-primary' : 'bg-secondary' ?>"><?= e($u['role']) ?></span></td>
          <td>
            <?php if (($u['status'] ?? 'active') === 'disabled'): ?>
            <span class="badge bg-danger">disabled</span>
            <?php else: ?>
            <span class="badge bg-success">active</span>
            <?php endif; ?>
          </td>
          <td class="text-muted small"><?= formatDate($u['created_at']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= url('admin/users/' . $u['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-pencil"></i></a>
              <?php if ($u['id'] != Auth::id()): ?>
              <form method="POST" action="<?= url('admin/users/' . $u['id'] . '/delete') ?>" data-confirm="Delete user?">
                <?= csrfField() ?><button class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
