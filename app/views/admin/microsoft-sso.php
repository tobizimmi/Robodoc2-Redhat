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
        <input type="password" name="ms_client_secret" class="form-control" placeholder="<?= $hasClientSecret ? '•••••••• (unverändert lassen = nicht ändern)' : '' ?>">
        <div class="form-text">Leer lassen, um den bereits gespeicherten Wert zu behalten.</div>
      </div>
      <div class="alert alert-info small mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Redirect URI für die App-Registrierung in Entra ID:
        <code><?= e((($_SERVER['HTTPS'] ?? '') === 'on' || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/auth/microsoft/callback') ?></code>
      </div>
    </div>
  </div>

  <div class="alert alert-warning small" style="max-width:800px">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Microsoft-Anmeldung ersetzt das Passwort-Login nicht — sie ist eine zusätzliche Option.
    Ein Login über Microsoft funktioniert nur für <strong>bereits existierende</strong> RoboDoc2-Konten
    (Zuordnung über die E-Mail-Adresse) — es werden keine neuen Konten automatisch angelegt.
  </div>

  <div style="max-width:800px">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
  </div>
</form>
