<?php $csrf = Auth::csrfToken(); ?>
<div class="card border-secondary" style="max-width:420px;margin:80px auto">
  <div class="card-body p-4">
    <div class="text-center mb-4">
      <i class="bi bi-shield-lock-fill fs-1 text-info"></i>
      <h4 class="mt-2">Two-Factor Authentication</h4>
      <p class="text-muted small">Enter the 6-digit code from your authenticator app, or a backup code.</p>
    </div>
    <form method="POST" action="<?= url('login/2fa') ?>">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <div class="mb-3">
        <label class="form-label">Authenticator Code</label>
        <input type="text" name="code" class="form-control form-control-lg text-center"
               maxlength="8" placeholder="000000" autocomplete="one-time-code"
               autofocus inputmode="numeric" style="letter-spacing:.3em;font-size:1.4rem">
        <div class="form-text">6-digit code or 8-character backup code.</div>
      </div>
      <button type="submit" class="btn btn-primary w-100">Verify</button>
    </form>
    <div class="text-center mt-3">
      <a href="<?= url('login') ?>" class="text-muted small">← Back to login</a>
    </div>
  </div>
</div>