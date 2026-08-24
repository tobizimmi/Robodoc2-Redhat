<!-- Preferences card (full width, shown first) -->
<div class="card mb-4" style="max-width:800px">
  <div class="card-header border-secondary fw-semibold small">Preferences</div>
  <div class="card-body">
    <form method="POST" action="<?= url('profile') ?>">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="preferences">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="jiraAutoCreate" name="jira_auto_create" value="1"
                   <?= !empty($user['jira_auto_create']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="jiraAutoCreate">
              <strong>Jira auto-create</strong><br>
              <span class="text-muted small">Pre-check "Create Jira issue" when creating a new entry</span>
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="notifyNewEntries" name="notify_new_entries" value="1"
                   <?= !empty($user['notify_new_entries']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="notifyNewEntries">
              <strong>Email on new entries</strong><br>
              <span class="text-muted small">Receive an email when other team members create an entry</span>
            </label>
          </div>
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn btn-outline-primary btn-sm">Save Preferences</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-4" style="max-width:800px">
  <!-- Profile info -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small">Profile</div>
      <div class="card-body">
        <form method="POST" action="<?= url('profile') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="profile">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Change password -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small">Change Password</div>
      <div class="card-body">
        <form method="POST" action="<?= url('profile') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="password">
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="8">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="8">
          </div>
          <button type="submit" class="btn btn-warning btn-sm">Change Password</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Jira credentials -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small">Jira Credentials</div>
      <div class="card-body">
        <form method="POST" action="<?= url('profile') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="jira">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Jira Email <span class="text-muted small">(Cloud only — leave blank for PAT)</span></label>
              <input type="email" name="jira_email" class="form-control" value="<?= e($user['jira_email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">API Token / PAT <span class="text-danger">*</span></label>
              <input type="password" name="jira_api_key" class="form-control" value="<?= e($user['jira_api_key'] ?? '') ?>" autocomplete="off">
            </div>
          </div>
          <div class="form-text mt-2">For Jira Server/Data Center: enter your Personal Access Token and leave email blank. For Jira Cloud: enter both email and API token.</div>
          <div class="mt-3">
            <button type="submit" class="btn btn-outline-primary btn-sm">Save Jira Credentials</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- SharePoint -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small d-flex align-items-center justify-content-between">
        <span><i class="bi bi-cloud-arrow-up me-2 text-info"></i>SharePoint (Microsoft Graph)</span>
        <?php if (!empty($user['sharepoint_refresh_token'])): ?>
        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Connected</span>
        <?php else: ?>
        <span class="badge bg-secondary">Not connected</span>
        <?php endif; ?>
      </div>
      <div class="card-body">

        <!-- Step 1: App credentials -->
        <form method="POST" action="<?= url('profile') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="sharepoint">
          <?php
            $fwd       = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
            $scheme    = $fwd === 'https' ? 'https'
                       : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
            $callbackUri = $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/sharepoint/callback';
          ?>
          <p class="text-muted small mb-2">
            <strong>Step 1 — App Registration</strong> (Azure Portal → App registrations)<br>
            Register an app, then add this exact URL as a <em>Redirect URI</em> (type: Web):
          </p>
          <div class="input-group input-group-sm mb-3">
            <input type="text" class="form-control form-control-sm font-monospace bg-dark text-info border-secondary"
                   id="spCallbackUri" value="<?= e($callbackUri) ?>" readonly>
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    onclick="navigator.clipboard.writeText(document.getElementById('spCallbackUri').value).then(()=>this.textContent='Copied!').catch(()=>{})">
              Copy
            </button>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small">Tenant ID</label>
              <input type="text" name="sharepoint_tenant_id" class="form-control form-control-sm"
                     value="<?= e($user['sharepoint_tenant_id'] ?? '') ?>"
                     placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
            </div>
            <div class="col-md-6">
              <label class="form-label small">Client ID (App ID)</label>
              <input type="text" name="sharepoint_client_id" class="form-control form-control-sm"
                     value="<?= e($user['sharepoint_client_id'] ?? '') ?>"
                     placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
            </div>
            <div class="col-md-6">
              <label class="form-label small">Client Secret</label>
              <input type="password" name="sharepoint_client_secret" class="form-control form-control-sm"
                     value="<?= e($user['sharepoint_client_secret'] ?? '') ?>"
                     autocomplete="off" placeholder="Leave blank to keep existing">
            </div>
            <div class="col-md-6">
              <label class="form-label small">Default Site URL</label>
              <input type="text" name="sharepoint_site_url" class="form-control form-control-sm"
                     value="<?= e($user['sharepoint_site_url'] ?? '') ?>"
                     placeholder="https://company.sharepoint.com/sites/Team">
            </div>
            <div class="col-12">
              <label class="form-label small">Upload Path Template</label>
              <input type="text" name="sharepoint_path_template" class="form-control form-control-sm font-monospace"
                     value="<?= e($user['sharepoint_path_template'] ?? '') ?>"
                     placeholder="e.g. /03 - Quality and Testing/Attachments Bug Reports/{{jira_key}}_{{title}}">
              <div class="form-text">
                Wird als Standardpfad im Upload-Dialog vorausgefüllt — kann dort jederzeit manuell angepasst werden.<br>
                <span class="text-muted">Beispiel:</span>
                <code class="user-select-all">/03 - Quality and Testing/Attachments Bug Reports/{{jira_key}}_{{title}}</code><br>
                Verfügbare Variablen:
                <code>{{jira_key}}</code>
                <code>{{title}}</code>
                <code>{{project}}</code>
                <code>{{serial}}</code>
                <code>{{firmware}}</code>
                <code>{{type}}</code>
                <code>{{category}}</code>
                <code>{{date}}</code>
                <code>{{id}}</code>
                <code>{{status}}</code>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-outline-secondary btn-sm">Save App Credentials</button>
          </div>
        </form>

        <hr class="border-secondary my-4">

        <!-- Step 2: User login -->
        <p class="text-muted small mb-3">
          <strong>Step 2 — Connect your Microsoft account</strong><br>
          Log in with your Husqvarna account. Uploads will use your personal permissions —
          no tenant-wide admin consent needed for the upload itself.
          <span class="text-warning">Requires Delegated permissions</span>
          (<code>Files.ReadWrite.All</code>, <code>Sites.ReadWrite.All</code>) set in Azure and
          one-time admin consent for those delegated scopes.
        </p>

        <?php if (!empty($user['sharepoint_refresh_token'])): ?>
        <div class="alert alert-success py-2 small d-flex align-items-center gap-3 mb-3">
          <i class="bi bi-check-circle-fill"></i>
          <div>Microsoft account connected.
            <?php if (!empty($user['sharepoint_token_expires_at'])): ?>
            Token valid until <?= date('d.m.Y H:i', $user['sharepoint_token_expires_at']) ?> (auto-refreshes).
            <?php endif; ?>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= url('sharepoint/connect') ?>" class="btn btn-outline-info btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i>Reconnect / Switch account
          </a>
          <form method="POST" action="<?= url('sharepoint/disconnect') ?>" data-confirm="Disconnect SharePoint?">
            <?= csrfField() ?>
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Disconnect</button>
          </form>
        </div>
        <?php else: ?>
        <a href="<?= url('sharepoint/connect') ?>" class="btn btn-info btn-sm">
          <i class="bi bi-microsoft me-2"></i>Connect with Microsoft
        </a>
        <?php endif; ?>

<!-- DSGVO Datenexport -->
<div class="card border-secondary mt-4">
  <div class="card-header border-secondary d-flex align-items-center justify-content-between">
    <span class="fw-semibold"><i class="bi bi-download me-2"></i>Meine Daten exportieren</span>
    <span class="badge bg-info">DSGVO Art. 20</span>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">
      Gemäß DSGVO Art. 20 haben Sie das Recht, alle Ihre gespeicherten Daten in einem
      maschinenlesbaren Format zu erhalten. Der Export enthält: Konto, Einträge, Kommentare,
      Feedback und Aktivitätsprotokoll.
    </p>
    <div class="d-flex gap-2">
      <a href="<?= url('profile/gdpr-export?format=json') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-filetype-json me-1"></i>JSON exportieren
      </a>
      <a href="<?= url('profile/gdpr-export?format=csv') ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-filetype-csv me-1"></i>CSV exportieren (Excel)
      </a>
    </div>
  </div>
</div>

<!-- 2FA Section -->
<div class="card border-secondary mt-4">
  <div class="card-header border-secondary d-flex align-items-center justify-content-between">
    <span class="fw-semibold"><i class="bi bi-shield-lock me-2"></i>Two-Factor Authentication</span>
    <?php if ($currentUser['totp_enabled'] ?? false): ?>
    <span class="badge bg-success">Enabled</span>
    <?php else: ?>
    <span class="badge bg-secondary">Disabled</span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if ($currentUser['totp_enabled'] ?? false): ?>
    <p class="text-muted small mb-3">2FA is active. Your account is protected with an authenticator app.</p>
    <?php if (!empty($currentUser['totp_verified_at'])): ?>
    <p class="text-muted small">Enabled: <?= date('d.m.Y H:i', strtotime($currentUser['totp_verified_at'])) ?></p>
    <?php endif; ?>
    <div class="d-flex gap-2">
      <a href="<?= url('profile/2fa/setup') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-repeat me-1"></i>Reset 2FA
      </a>
      <form method="POST" action="<?= url('profile/2fa/disable') ?>"
            onsubmit="return confirm('Disable 2FA? Your account will be less secure.')">
        <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-shield-x me-1"></i>Disable 2FA
        </button>
      </form>
    </div>
    <?php else: ?>
    <p class="text-muted small mb-3">
      Enable two-factor authentication to add an extra layer of security to your account.
      You will need an authenticator app like <strong>Google Authenticator</strong> or <strong>Microsoft Authenticator</strong>.
    </p>
    <a href="<?= url('profile/2fa/setup') ?>" class="btn btn-success btn-sm">
      <i class="bi bi-shield-check me-1"></i>Enable 2FA
    </a>
    <?php endif; ?>
  </div>
</div>

      </div>
    </div>
  </div>

  <!-- Jira default templates -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small">Jira Default Template</div>
      <div class="card-body">
        <form method="POST" action="<?= url('profile') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="jira_template">
          <?php
            $allVars = '{{id}} {{type}} {{title}} {{serial}} {{firmware}} {{app_version}} {{project}} {{project_status}} {{category}} {{environment}} {{test_area}} {{status}} {{date}} {{time}} {{creator}} {{temperature}} {{weather}} {{sharepoint}} {{description}}';
            $defaultTitle = '[{{type}}] {{title}}';
            $defaultDesc  = "*Type:* {{type}}\n*Category:* {{category}}\n*Project:* {{project}}\n*Project Status:* {{project_status}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*App Version:* {{app_version}}\n*Environment:* {{environment}}\n*Test Area:* {{test_area}}\n*Date:* {{date}} {{time}}\n*Creator:* {{creator}}\n\n{{description}}";
          ?>
          <div class="mb-3">
            <label class="form-label small">Summary Template</label>
            <input type="text" name="jira_title_template" class="form-control form-control-sm font-monospace"
                   value="<?= e($user['jira_title_template'] ?? $defaultTitle) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small">Description Template</label>
            <textarea name="jira_desc_template" class="form-control form-control-sm font-monospace" rows="12"><?= e($user['jira_desc_template'] ?? $defaultDesc) ?></textarea>
          </div>
          <div class="form-text mb-3">
            Available variables:<br>
            <code><?= e($allVars) ?></code>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary btn-sm">Save Template</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetJiraTemplate()">Reset to Default</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<script>
function resetJiraTemplate() {
  if (!confirm('Reset templates to default?')) return;
  document.querySelector('input[name="jira_title_template"]').value = <?= json_encode($defaultTitle) ?>;
  document.querySelector('textarea[name="jira_desc_template"]').value = <?= json_encode($defaultDesc) ?>;
}
</script>

  <!-- Confluence credentials -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small">Confluence Credentials</div>
      <div class="card-body">
        <form method="POST" action="<?= url('profile') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="confluence">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Confluence Email <span class="text-muted small">(Cloud only — leave blank for PAT)</span></label>
              <input type="email" name="confluence_email" class="form-control" value="<?= e($user['confluence_email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">API Token / PAT <span class="text-danger">*</span></label>
              <input type="password" name="confluence_token" class="form-control" value="<?= e($user['confluence_token'] ?? '') ?>" autocomplete="off">
            </div>
          </div>
          <div class="form-text mt-2">For Confluence Server/Data Center: enter your Personal Access Token and leave email blank. For Confluence Cloud: enter both email and API token.</div>
          <div class="mt-3">
            <button type="submit" class="btn btn-outline-primary btn-sm">Save Confluence Credentials</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
