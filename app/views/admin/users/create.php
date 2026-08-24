<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin/users') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">New User</h5>
</div>
<div class="card" style="max-width:500px"><div class="card-body p-4">
  <form method="POST" action="<?= url('admin/users/create') ?>">
    <?= csrfField() ?>
    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">E-Mail</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Passwort</label><input type="password" name="password" class="form-control" required minlength="8"></div>
    <div class="mb-3"><label class="form-label">Role</label>
      <select name="role" class="form-select">
        <option value="user">User</option>
        <option value="admin">Administrator</option>
      </select>
    </div>
    <div class="mb-3"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="active">Active</option>
        <option value="pending">Pending Approval</option>
        <option value="disabled">Disabled</option>
      </select>
    </div>
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">Create</button>
      <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div></div>
