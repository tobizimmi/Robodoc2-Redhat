<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Microsoft SSO Settings</h5>
</div>

<form method="POST" action="<?= url('admin/microsoft-sso') ?>">
  <?= csrfField() ?>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-microsoft me-1"></i>Connection</div>
    <div class="card-body p-4">
      <div class="mb-3 form-check">
        <input type="checkbox" name="ms_sso_enabled" class="form-check-input" id="msEnabled" value="1"
               <?= ($s['ms_sso_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
        <label class="form-check-label" for="msEnabled">Enable "Sign in with Microsoft" on the login page</label>
      </div>
      <div class="mb-3">
        <label class="form-label">Directory (Tenant) ID</label>
        <input type="text" name="ms_tenant_id" class="form-control" value="<?= e($s['ms_tenant_id'] ?? '') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
      </div>
      <div class="mb-3">
        <label class="form-label">Application (Client) ID</label>
        <input type="text" name="ms_client_id" class="form-control" value="<?= e($s['ms_client_id'] ?? '') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
      </div>
      <div class="mb-3">
        <label class="form-label">Client Secret</label>
        <input type="password" name="ms_client_secret" class="form-control" placeholder="<?= $hasClientSecret ? '•••••••• (leave unchanged = do not change)' : '' ?>">
        <div class="form-text">Leave empty to keep the already saved value.</div>
      </div>
      <div class="alert alert-info small mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Redirect URI for the app registration in Entra ID:
        <code><?= e((($_SERVER['HTTPS'] ?? '') === 'on' || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/auth/microsoft/callback') ?></code>
      </div>
    </div>
  </div>

  <div class="alert alert-warning small" style="max-width:800px">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Microsoft sign-in does not replace password login — it is an additional option.
    Logging in via Microsoft only works for <strong>already existing</strong> RoboDoc2 accounts
    (matched by email address) — no new accounts are created automatically.
  </div>

  <div style="max-width:800px">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
  </div>
</form>
