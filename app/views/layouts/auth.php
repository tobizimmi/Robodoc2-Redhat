<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Anmelden') ?> – <?= e(appSetting('app_name', APP_NAME)) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center min-vh-100">
<div class="w-100" style="max-width:420px">
  <div class="text-center mb-4">
    <i class="bi bi-robot text-primary" style="font-size:3rem"></i>
    <h2 class="mt-2 fw-bold"><?= e(appSetting('app_name', APP_NAME)) ?></h2>
    <p class="text-muted small">Testdokumentation & Fehlerverwaltung</p>
  </div>
  <div class="card border-secondary bg-dark-subtle">
    <div class="card-body p-4">
      <?php $error = getFlash('error'); $success = getFlash('success'); ?>
      <?php if ($error): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
