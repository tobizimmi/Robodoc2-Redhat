<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin/users') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Edit User</h5>
</div>

<div class="row g-4">

  <!-- Left: user details -->
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">User Details</div>
      <div class="card-body">
        <form method="POST" action="<?= url('admin/users/' . $user['id'] . '/edit') ?>">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password <span class="text-muted small">(leave empty = unchanged)</span></label>
            <input type="password" name="password" class="form-control" minlength="8">
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
              <option value="user"  <?= $user['role']==='user'  ? 'selected':'' ?>>User</option>
              <option value="admin" <?= $user['role']==='admin' ? 'selected':'' ?>>Administrator</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active"   <?= ($user['status']??'active')==='active'   ? 'selected':'' ?>>Active</option>
              <option value="pending"  <?= ($user['status']??'')==='pending'  ? 'selected':'' ?>>Awaiting approval</option>
              <option value="disabled" <?= ($user['status']??'')==='disabled' ? 'selected':'' ?>>Disabled</option>
            </select>
          </div>
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="canTestRequests" name="can_test_requests" value="1"
                     <?= !empty($user['can_test_requests']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="canTestRequests">Access to Test Requests</label>
            </div>
          </div>

          <?php if ($userGroups): ?>
          <div class="mb-3">
            <label class="form-label small text-muted">Member of groups</label>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($userGroups as $g): ?>
              <span class="badge bg-secondary"><?= e($g['name']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Module permission matrix for this user -->
          <hr class="border-secondary">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="fw-semibold small"><i class="bi bi-shield-lock me-1"></i>Module Permissions</span>
            <span class="badge bg-<?= $userPerms ? 'warning text-dark' : 'secondary' ?> ms-auto" style="font-size:.65rem">
              <?= $userPerms ? 'Individual permissions active' : 'Default (group/default)' ?>
            </span>
          </div>
          <?php if ($userPerms): ?>
          <div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.78rem">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Individual permissions are set for this user. These override the group permissions.
          </div>
          <?php else: ?>
          <div class="alert alert-secondary py-2 px-3 mb-2" style="font-size:.78rem">
            <i class="bi bi-info-circle me-1"></i>
            No individual permissions — the user inherits the permissions of their groups.
            Set custom permissions below to override the group permissions.
          </div>
          <?php endif; ?>
          <?php if (!$userPerms): ?>
          <div class="text-muted small mb-2 px-1">
            <i class="bi bi-info-circle me-1"></i>
            Check a box to activate individual permissions. As long as nothing is set, the group permissions apply.
          </div>
          <?php endif; ?>
          <table class="table table-dark table-sm mb-2 align-middle" style="font-size:.8rem">
            <thead>
              <tr>
                <th class="ps-2">Module</th>
                <th class="text-center" style="width:68px"><span class="text-info" title="See all entries">View</span></th>
                <th class="text-center" style="width:68px"><span class="text-success" title="Create &amp; edit own">Own</span></th>
                <th class="text-center" style="width:68px"><span class="text-warning" title="Edit all">Edit</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($modules as $slug => $label): ?>
              <?php
                $hasIndividual = !empty($userPerms);
                $dv = $hasIndividual ? ($userPerms[$slug]['view'] ?? false) : false;
                $do = $hasIndividual ? ($userPerms[$slug]['own']  ?? false) : false;
                $de = $hasIndividual ? ($userPerms[$slug]['edit'] ?? false) : false;
              ?>
              <tr <?= !$hasIndividual ? 'class="opacity-50"' : '' ?>>
                <td class="ps-2"><?= e($label) ?></td>
                <td class="text-center">
                  <input type="checkbox" name="perm_view[<?= $slug ?>]" value="1"
                         class="form-check-input u-perm-view" data-module="<?= $slug ?>"
                         <?= $dv ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                  <?php if (in_array($slug, ['entries', 'quick_capture'])): ?>
                  <input type="checkbox" name="perm_own[<?= $slug ?>]" value="1"
                         class="form-check-input u-perm-own" data-module="<?= $slug ?>"
                         <?= $do ? 'checked' : '' ?>>
                  <?php else: ?>
                  <span class="text-muted" style="font-size:.7rem">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <input type="checkbox" name="perm_edit[<?= $slug ?>]" value="1"
                         class="form-check-input u-perm-edit" data-module="<?= $slug ?>"
                         <?= $de ? 'checked' : '' ?>>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="d-flex gap-2 flex-wrap mb-3 align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setUPerms(true)">All on</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setUPerms(false)">All off</button>
            <button type="button" class="btn btn-outline-info btn-sm" onclick="setUViewOnly()">View only</button>
          </div>

          <button type="submit" class="btn btn-primary w-100">Save</button>
        </form>

        <?php if ($userPerms): ?>
        <form method="POST" action="<?= url('admin/users/' . $user['id'] . '/clear-perms') ?>"
              class="mt-2"
              onsubmit="return confirm('Remove individual permissions? The group permissions will apply again afterwards.')">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-outline-danger btn-sm w-100">
            <i class="bi bi-x-lg me-1"></i>Delete individual permissions
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<script>
document.querySelectorAll('.u-perm-edit').forEach(cb => {
  cb.addEventListener('change', () => {
    const m = cb.dataset.module;
    if (cb.checked) {
      document.querySelector(`.u-perm-own[data-module="${m}"]`).checked  = true;
      document.querySelector(`.u-perm-view[data-module="${m}"]`).checked = true;
    }
  });
});
document.querySelectorAll('.u-perm-own').forEach(cb => {
  cb.addEventListener('change', () => {
    const m = cb.dataset.module;
    if (cb.checked) {
      document.querySelector(`.u-perm-view[data-module="${m}"]`).checked = true;
    } else {
      document.querySelector(`.u-perm-edit[data-module="${m}"]`).checked = false;
    }
  });
});
document.querySelectorAll('.u-perm-view').forEach(cb => {
  cb.addEventListener('change', () => {
    if (!cb.checked) {
      const m = cb.dataset.module;
      document.querySelector(`.u-perm-own[data-module="${m}"]`).checked  = false;
      document.querySelector(`.u-perm-edit[data-module="${m}"]`).checked = false;
    }
  });
});
function setUPerms(val) {
  document.querySelectorAll('.u-perm-view, .u-perm-own, .u-perm-edit').forEach(cb => cb.checked = val);
}
function setUViewOnly() {
  document.querySelectorAll('.u-perm-view').forEach(cb => cb.checked = true);
  document.querySelectorAll('.u-perm-own, .u-perm-edit').forEach(cb => cb.checked = false);
}

</script>
