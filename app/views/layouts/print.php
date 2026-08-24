<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e($title ?? 'Report') ?></title>
<style>
body{font-family:Arial,sans-serif;font-size:11pt;color:#000;margin:2cm}
h1{font-size:18pt;margin-bottom:4px}h2{font-size:13pt;margin-top:20px}
table{border-collapse:collapse;width:100%;margin-bottom:16px}
td,th{border:1px solid #bbb;padding:5px 8px;text-align:left;vertical-align:top;font-size:10pt}
th{background:#f0f0f0;font-weight:bold}
.badge{padding:2px 5px;border-radius:3px;color:#fff;font-size:9pt}
@media print{
  button{display:none}
  @page{margin:1.5cm}
}
</style>
</head>
<body>
<div style="text-align:right;margin-bottom:16px">
  <button onclick="window.print()" style="padding:8px 20px;background:#333;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11pt">
    &#128438; Print / Save as PDF
  </button>
</div>
<?= $content ?>
</body>
</html>
