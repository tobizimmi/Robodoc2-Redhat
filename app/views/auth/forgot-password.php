<h5 class="mb-1 fw-semibold">Reset Password</h5>
<p class="text-muted small mb-4">Gib deine E-Mail-Adresse ein und wir senden dir einen Reset-Link.</p>
<form method="POST" action="<?= url('forgot-password') ?>">
  <?= csrfField() ?>
  <div class="mb-3">
    <label class="form-label">E-Mail</label>
    <input type="email" name="email" class="form-control" required autofocus>
  </div>
  <button type="submit" class="btn btn-primary w-100">Reset-Link senden</button>
</form>
<div class="text-center mt-3">
  <a href="<?= url('login') ?>" class="text-muted small">Back to Sign In</a>
</div>
