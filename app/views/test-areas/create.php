<form method="POST" action="<?= url('test-areas/create') ?>" enctype="multipart/form-data">
  <?= csrfField() ?>
  <?php include __DIR__ . '/_form.php'; ?>
</form>
