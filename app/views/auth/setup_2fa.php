<?php $csrf = Auth::csrfToken(); ?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card border-secondary">
      <div class="card-header border-secondary">
        <h5 class="mb-0"><i class="bi bi-shield-lock me-2 text-info"></i>Enable Two-Factor Authentication</h5>
      </div>
      <div class="card-body">
        <ol class="mb-4" style="line-height:2">
          <li>Install <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>, or <strong>Authy</strong> on your phone.</li>
          <li>Open the app, tap <strong>"+"</strong> and choose <strong>"Enter setup key manually"</strong>.</li>
          <li>Enter the key below, then enter the 6-digit code to confirm.</li>
        </ol>

        <!-- Manual key entry (most reliable) -->
        <div class="card border-warning mb-3">
          <div class="card-header border-warning bg-transparent py-2">
            <span class="fw-semibold small"><i class="bi bi-key me-1 text-warning"></i>Setup Key (Account Name: RoboDoc)</span>
          </div>
          <div class="card-body py-3 text-center">
            <div class="font-monospace fw-bold mb-2" style="font-size:1.4rem;letter-spacing:.25em;word-break:break-all">
              <?= e(chunk_split($secret, 4, ' ')) ?>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    onclick="navigator.clipboard.writeText('<?= e($secret) ?>');this.innerHTML='<i class=&quot;bi bi-check&quot;></i> Copied!'">
              <i class="bi bi-clipboard"></i> Copy key
            </button>
          </div>
        </div>

        <!-- QR Code via Google Charts API (server-side fetch) -->
        <div class="text-center mb-3">
          <div class="text-muted small mb-2">Or scan QR code:</div>
          <div class="d-inline-block border rounded p-2 bg-white">
            <?php
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($uri);
            $qrData = @file_get_contents($qrUrl);
            if ($qrData && strlen($qrData) > 100) {
                echo '<img src="data:image/png;base64,' . base64_encode($qrData) . '" width="200" height="200" alt="QR Code">';
            } else {
                echo '<p class="text-muted small m-0">QR nicht verfügbar — bitte Schlüssel manuell eingeben.</p>';
            }
            ?>
          </div>
        </div>

        <!-- In-app setup instructions -->
        <div class="alert alert-info small mb-3 py-2">
          <strong>Manual setup steps in authenticator app:</strong><br>
          • Account name: <code>RoboDoc</code><br>
          • Key: <code><?= e($secret) ?></code><br>
          • Type: <strong>Time-based (TOTP)</strong>
        </div>

        <form method="POST" action="<?= url('profile/2fa/setup') ?>">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <input type="hidden" name="secret" value="<?= e($secret) ?>">
          <div class="mb-3">
            <label class="form-label">Verification Code <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control form-control-lg text-center"
                   maxlength="6" placeholder="000000" autocomplete="one-time-code"
                   required autofocus inputmode="numeric" style="letter-spacing:.3em;font-size:1.4rem">
            <div class="form-text">Enter the 6-digit code shown in your authenticator app.</div>
          </div>
          <button type="submit" class="btn btn-success w-100">
            <i class="bi bi-shield-check me-1"></i>Enable Two-Factor Authentication
          </button>
        </form>
        <div class="mt-3 text-center">
          <a href="<?= url('profile') ?>" class="text-muted small">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</div>
