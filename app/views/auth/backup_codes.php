<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card border-warning">
      <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-key-fill me-2"></i>Save Your Backup Codes</h5>
      </div>
      <div class="card-body">
        <div class="alert alert-warning">
          <strong>Important:</strong> Save these codes in a safe place. Each code can only be used once.
          If you lose access to your authenticator app, these codes are the only way to recover your account.
        </div>
        <?php if (!empty($codes)): ?>
        <div class="row g-2 mb-3 font-monospace">
          <?php foreach ($codes as $code): ?>
          <div class="col-6">
            <div class="bg-dark border border-secondary rounded px-3 py-2 text-center fw-semibold" style="letter-spacing:.15em">
              <?= e($code) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-outline-secondary btn-sm w-100 mb-3"
                onclick="navigator.clipboard.writeText(<?= json_encode(implode('
', $codes)) ?>);this.textContent='Copied!'">
          <i class="bi bi-clipboard me-1"></i>Copy All Codes
        </button>
        <?php else: ?>
        <p class="text-muted">Codes have already been shown. Generate new ones from your profile if needed.</p>
        <?php endif; ?>
        <a href="<?= url('dashboard') ?>" class="btn btn-primary w-100">
          <i class="bi bi-check-lg me-1"></i>I have saved my codes
        </a>
      </div>
    </div>
  </div>
</div>