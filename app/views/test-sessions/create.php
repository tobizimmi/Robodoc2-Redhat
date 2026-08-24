<form method="POST" action="<?= url('test-sessions/create') ?>">
  <?= csrfField() ?>
  <?php include __DIR__ . '/_form.php'; ?>
</form>
