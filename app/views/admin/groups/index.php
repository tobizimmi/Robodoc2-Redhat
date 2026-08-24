<div class="d-flex justify-content-between align-items-center mb-4">
  <a href="<?= url('admin') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Admin</a>
  <a href="<?= url('admin/groups/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Group</a>
</div>

<div class="alert alert-secondary small mb-4">
  <i class="bi bi-info-circle me-2"></i>
  Users in a group can only see entries from the projects assigned to that group. Users in no group see all non-private entries. Private entries are always restricted to their creator.
</div>

<?php if (!$groups): ?>
<div class="card"><div class="card-body text-center text-muted py-5">No groups yet. <a href="<?= url('admin/groups/create') ?>">Create one</a>.</div></div>
<?php else: ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-dark table-hover mb-0 align-middle">
      <thead><tr><th>Name</th><th>Description</th><th>Members</th><th>Projects</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($groups as $g): ?>
        <tr>
          <td class="fw-semibold"><?= e($g['name']) ?></td>
          <td class="text-muted small"><?= e($g['description'] ?? '') ?></td>
          <td><span class="badge bg-secondary"><?= $g['member_count'] ?> users</span></td>
          <td><span class="badge bg-secondary"><?= $g['project_count'] ?> projects</span></td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= url('admin/groups/' . $g['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-pencil"></i></a>
              <form method="POST" action="<?= url('admin/groups/' . $g['id'] . '/delete') ?>" data-confirm="Delete group? Members will lose restricted access.">
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
<?php endif; ?>
