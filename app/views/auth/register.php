<h5 class="mb-4 fw-semibold">Create Account</h5>
<form method="POST" action="<?= url('register') ?>">
  <?= csrfField() ?>
  <div class="mb-3">
    <label class="form-label">Full Name</label>
    <input type="text" name="name" class="form-control" required autofocus value="<?= e($_POST['name'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Password <small class="text-muted">(min. 8 characters)</small></label>
    <input type="password" name="password" class="form-control" required minlength="8">
  </div>
  <div class="mb-4">
    <label class="form-label">Confirm Password</label>
    <input type="password" name="confirm" class="form-control" required minlength="8">
  </div>
  <button type="submit" class="btn btn-primary w-100">Register</button>
</form>
<p class="text-center text-muted small mt-3 mb-0">
  Already have an account? <a href="<?= url('login') ?>" class="text-primary">Sign in</a>
</p>
<p class="text-center text-muted small mt-2">
  <i class="bi bi-info-circle me-1"></i>Your account will be activated after admin approval.
</p>
