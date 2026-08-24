<h5 class="mb-4 fw-semibold">Neues Passwort</h5>
<form method="POST" action="<?= url('reset-password') ?>">
  <?= csrfField() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="mb-3">
    <label class="form-label">Neues Passwort</label>
    <input type="password" name="password" class="form-control" required minlength="8">
  </div>
  <div class="mb-3">
    <label class="form-label">Confirm Password</label>
    <input type="password" name="confirm" class="form-control" required minlength="8">
  </div>
  <button type="submit" class="btn btn-primary w-100">Passwort speichern</button>
</form>
