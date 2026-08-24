<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'Entry Export') ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: <?= e($tpl['font_family'] ?? 'Arial, sans-serif') ?>; font-size: 11pt; color: #1a1a1a; background: #fff; }
    @media print {
      .no-print { display: none !important; }
      .page-break { page-break-before: always; }
      body { font-size: 10pt; }
    }
    @page { margin: 20mm 15mm; }
  </style>
</head>
<body>
<?= $content ?>
</body>
</html>