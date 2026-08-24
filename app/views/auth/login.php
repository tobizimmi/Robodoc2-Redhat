<h5 class="mb-4 fw-semibold">Sign In</h5>
<form method="POST" action="<?= url('login') . (isset($_GET['next']) ? '?next='.urlencode($_GET['next']) : '') ?>">
  <?= csrfField() ?>
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" required autofocus
           value="<?= e($_POST['email'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label d-flex justify-content-between">
      Password
      <a href="<?= url('forgot-password') ?>" class="text-muted small">Forgot?</a>
    </label>
    <input type="password" name="password" class="form-control" required>
  </div>
  <button type="submit" class="btn btn-primary w-100">Sign In</button>
</form>

<hr class="border-secondary my-3">
<a href="<?= url('quick-capture') ?>" class="btn btn-outline-light w-100">
  <i class="bi bi-lightning-charge me-2"></i>Quick Capture – ohne Login erfassen
</a>
<p class="text-center text-muted small mt-3 mb-0">
  No account yet? <a href="<?= url('register') ?>" class="text-primary">Register</a>
</p>
