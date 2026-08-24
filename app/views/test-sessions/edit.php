<form method="POST" action="<?= url('test-sessions/' . $session['id'] . '/edit') ?>">
  <?= csrfField() ?>
  <?php include __DIR__ . '/_form.php'; ?>
</form>
