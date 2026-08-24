<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'RoboDoc') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #0f1117; color: #e5e7eb; min-height: 100vh; }
    .form-control, .form-select { background: #1e2125; border-color: #374151; color: #e5e7eb; }
    .form-control:focus, .form-select:focus { background: #1e2125; border-color: #6366f1; color: #e5e7eb; box-shadow: 0 0 0 .2rem rgba(99,102,241,.25); }
    .form-check-input { background-color: #1e2125; border-color: #4b5563; }
    label { color: #d1d5db; }
  </style>
</head>
<body>
  <div class="container py-4" style="max-width:600px">
    <?= $content ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>