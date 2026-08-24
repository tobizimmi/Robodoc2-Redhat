<?php
$cfg        = $config ?? [];
$branding   = $cfg['branding'] ?? [];
$header     = $cfg['header']   ?? [];
$footer     = $cfg['footer']   ?? [];
$blocks     = $cfg['blocks']   ?? [];
$filters    = $cfg['filters']  ?? ($cfg['_runtime'] ?? []);
$primary    = $branding['primaryColor'] ?? '#1e3a5f';
$font       = $branding['font'] ?? 'Arial';
$logo        = $branding['logo'] ?? '';
$orientation = $cfg['orientation'] ?? 'portrait';  // portrait or landscape
$entries    = $data['entries']    ?? [];
$project    = $data['project']    ?? null;
$byType     = $data['byType']     ?? [];
$byStatus   = $data['byStatus']   ?? [];
$byFirmware = $data['byFirmware'] ?? [];

$total  = count($entries);
$openSt = ['new','open','internal','reviewed','pending_at_supplier','ready_for_test'];
$doneSt = ['finished','finalized','rejected'];
$open   = count(array_filter($entries, fn($e) => in_array($e['status']??'', $openSt)));
$done   = count(array_filter($entries, fn($e) => in_array($e['status']??'', $doneSt)));
$types  = count(array_unique(array_filter(array_column($entries,'type_name'))));

$colLabels = [
    'entry_date'=>'Datum','title'=>'Titel','status'=>'Status','priority'=>'Priorität',
    'type_name'=>'Typ','project_name'=>'Projekt','creator'=>'Ersteller',
    'description'=>'Beschreibung','mower_serial'=>'Seriennummer',
    'firmware_version'=>'Firmware','app_version'=>'App Version',
    'epic_title'=>'Epic','parent_title'=>'Parent','tag_names'=>'Tags',
    'jira_issue_key'=>'Jira','zentao_bug_id'=>'Zentao','project_status_robot'=>'Robot',
];
$statusColors = [
    'new'=>'#6b7280','open'=>'#6b7280','internal'=>'#0ea5e9','reviewed'=>'#3b82f6',
    'pending_at_supplier'=>'#f59e0b','ready_for_test'=>'#10b981',
    'finished'=>'#22c55e','finalized'=>'#22c55e','rejected'=>'#ef4444',
];
$priorityColors = ['Highest'=>'#dc2626','High'=>'#f97316','Medium'=>'#3b82f6','Low'=>'#22c55e'];

// Default column widths (out of 12 units)
$colDefaultWidths = [
    'entry_date'=>1,'status'=>1,'priority'=>1,'type_name'=>1,'project_name'=>2,
    'creator'=>1,'title'=>3,'description'=>4,'mower_serial'=>2,'firmware_version'=>1,
    'app_version'=>1,'epic_title'=>2,'parent_title'=>2,'tag_names'=>2,
    'jira_issue_key'=>1,'zentao_bug_id'=>1,'project_status_robot'=>1,
];
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($header['title'] ?? $tpl['name']) ?></title>
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
html, body { width:100%; }
body { font-family: <?= htmlspecialchars($font) ?>, Arial, sans-serif; font-size:10px; color:#1a1a2e; background:#fff; line-height:1.4; margin:0; padding:0; }
.rpt-wrap { padding:0; margin-top:0; }

/* Print page setup */
<?php if ($orientation === 'landscape'): ?>
@page { size: A4 landscape; margin:10mm 9mm 10mm 9mm; }
<?php else: ?>
@page { size: A4 portrait; margin:12mm 9mm 10mm 9mm; }
<?php endif; ?>
@media print {
  .no-print     { display:none !important; }
  .page-break   { page-break-before:always; break-before:page; }
  body          { -webkit-print-color-adjust:exact; print-color-adjust:exact; margin:0 !important; padding:0 !important; }
  html          { margin:0 !important; padding:0 !important; }
  .rpt-header   { page-break-inside:avoid; }
  .section      { page-break-inside:auto; break-inside:auto; }
  .section-title{ page-break-after:avoid; }
  .kpi-card     { page-break-inside:avoid; }
  .bar-row      { page-break-inside:avoid; }
  table         { page-break-inside:auto; }
  tr            { page-break-inside:avoid; page-break-after:auto; }
  thead         { display:table-header-group; }
  .block-grid   { display:block !important; gap:0 !important; }
  .block-col    { display:block !important; width:100% !important; }
  .kpi-grid     { grid-template-columns:repeat(4,1fr) !important; }
}

/* Report header */
.rpt-header   { background:<?= htmlspecialchars($primary) ?>; color:#fff; padding:12px 18px; margin-bottom:12px; display:flex; align-items:flex-start; gap:14px; border-radius:4px; }
.rpt-header img { max-height:44px; max-width:130px; object-fit:contain; flex-shrink:0; }
.rpt-header-text { flex:1; }
.rpt-header-text h1 { font-size:16px; font-weight:800; margin-bottom:2px; }
.rpt-header-text p  { font-size:11px; opacity:.85; margin-top:2px; }
.rpt-header-meta { text-align:right; font-size:10px; opacity:.7; flex-shrink:0; white-space:nowrap; }

/* Report header */
/* Sections */
.section       { margin-bottom:8px; }
.section-title { font-size:10px; font-weight:800; color:<?= htmlspecialchars($primary) ?>; border-bottom:2px solid <?= htmlspecialchars($primary) ?>; padding-bottom:4px; margin-bottom:10px; text-transform:uppercase; letter-spacing:.4px; }

/* Block layout grid (side-by-side for screen, stack for print) */
.block-grid    { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:4px; }
.block-col.w-full      { width:100%; }
.block-col.w-half      { width:49%; }
.block-col.w-third     { width:32%; }
.block-col.w-two-third { width:65%; }

/* Project header */
.proj-header-bar  { padding:13px 18px; color:#fff; display:flex; align-items:center; gap:12px; border-radius:6px 6px 0 0; }
.proj-header-body { padding:9px 18px; background:#f8f9ff; border:1px solid; border-top:none; border-radius:0 0 6px 6px; }

/* KPI cards */
.kpi-grid { display:grid; gap:9px; }
.kpi-card { background:#f4f6ff; border-radius:5px; padding:10px 8px; text-align:center; border:1px solid #e8ecf4; }
.kpi-num  { font-size:24px; font-weight:800; color:<?= htmlspecialchars($primary) ?>; line-height:1.1; }
.kpi-lbl  { font-size:9px; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; margin-top:3px; }

/* Bar charts */
.bar-row   { display:flex; align-items:center; gap:6px; margin-bottom:3px; font-size:9px; }
.bar-lbl   { width:120px; text-align:right; color:#4b5563; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:0; }
.bar-track { flex:1; background:#e8ecf0; border-radius:3px; overflow:hidden; }
.bar-fill  { height:100%; border-radius:3px; display:flex; align-items:center; padding-left:4px; font-size:9px; color:#fff; font-weight:600; }
.bar-val   { width:28px; text-align:right; color:#6b7280; flex-shrink:0; font-size:10px; }

/* Table */
.tbl-wrap  { overflow:visible; }
table      { width:100%; border-collapse:collapse; font-size:8px; table-layout:fixed; }
th         { background:<?= htmlspecialchars($primary) ?>; color:#fff; padding:4px 5px; text-align:left; font-size:7.5px; text-transform:uppercase; letter-spacing:.2px; white-space:nowrap; overflow:hidden; }
td         { padding:3px 5px; border-bottom:1px solid #e8ecf0; vertical-align:top; white-space:normal; word-break:break-word; overflow-wrap:break-word; }
td.wrap    { white-space:normal; word-break:break-word; }
td.nowrap  { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:0; }
tr:nth-child(even) td { background:#f8f9ff; }
tr:nth-child(even) td.alt { background:#f8f9ff; }

/* Table multi-row layout */
.tbl-row-main { font-weight:600; }
.tbl-row-sub  { font-size:8.5px; color:#6b7280; margin-top:2px; line-height:1.3; }
.tbl-row-main { font-weight:500; }

.badge-s { display:inline-block; padding:1px 6px; border-radius:8px; font-size:8.5px; font-weight:600; color:#fff; white-space:nowrap; }
.badge-p { display:inline-block; padding:1px 6px; border-radius:8px; font-size:8.5px; font-weight:600; color:#fff; white-space:nowrap; }

/* Text block */
.text-block { background:#f8f9fa; border-left:4px solid <?= htmlspecialchars($primary) ?>; padding:10px 14px; border-radius:0 5px 5px 0; line-height:1.6; }

/* Timeline */
.tl-item    { display:flex; gap:10px; margin-bottom:7px; font-size:10px; }
.tl-dot     { width:10px; flex-shrink:0; padding-top:3px; }
.tl-dot span{ display:block; width:8px; height:8px; border-radius:50%; }
.tl-content { flex:1; }

/* Footer */
.rpt-footer { margin-top:20px; padding-top:8px; border-top:1px solid #ddd; display:flex; justify-content:space-between; font-size:9px; color:#9ca3af; }

/* Print topbar */
.print-bar { position:fixed; top:0; left:0; right:0; background:<?= htmlspecialchars($primary) ?>; color:#fff; padding:8px 18px; display:flex; align-items:center; gap:12px; z-index:999; font-size:13px; box-shadow:0 2px 8px rgba(0,0,0,.3); }
.print-bar button { background:#fff; color:<?= htmlspecialchars($primary) ?>; border:none; padding:5px 16px; border-radius:4px; font-weight:700; cursor:pointer; font-size:12px; }
</style>
<script>
function rdPrintLandscape() {
  var s = document.createElement('style');
  s.id = 'rdLandscapeStyle';
  s.innerHTML = '@page { size: A4 landscape !important; margin:10mm 9mm; }';
  document.head.appendChild(s);
  setTimeout(function() {
    window.print();
    setTimeout(function() {
      var el = document.getElementById('rdLandscapeStyle');
      if (el) el.remove();
    }, 1000);
  }, 100);
}
function rdHideHint() {
  var h = document.getElementById('rdPrintHint');
  if (h) h.remove();
}
function rdPrint() {
  var d = document.createElement('div');
  d.id = 'rdPrintHint';
  d.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;display:flex;align-items:center;justify-content:center';
  var box = document.createElement('div');
  box.style.cssText = 'background:#1e3a5f;color:#fff;padding:28px 32px;border-radius:10px;max-width:420px;text-align:center';
  box.innerHTML = '<div style="font-size:18px;font-weight:700;margin-bottom:12px">&#128196; PDF speichern</div>'
    + '<p style="font-size:13px;line-height:1.7;margin-bottom:18px">Im Druckdialog bitte einstellen:<br>'
    + '<b>&#10112; R&auml;nder &rarr; Keine</b><br>'
    + '<b>&#10113; Kopf- und Fu&szlig;zeilen &rarr; Aus</b></p>'
    + '<button id="rdDoPrint" style="background:#fff;color:#1e3a5f;border:none;padding:9px 18px;border-radius:6px;font-weight:700;font-size:13px;cursor:pointer;margin-right:6px">Hochformat</button>'
    + '<button id="rdDoLandscape" style="background:#e8f0ff;color:#1e3a5f;border:none;padding:9px 18px;border-radius:6px;font-weight:700;font-size:13px;cursor:pointer;margin-right:6px">Querformat</button>'
    + '<button id="rdCancelPrint" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,.5);padding:9px 14px;border-radius:6px;font-size:12px;cursor:pointer">Abbrechen</button>';
  d.appendChild(box);
  document.body.appendChild(d);
  document.getElementById('rdDoPrint').addEventListener('click', function() { rdHideHint(); window.print(); });
  document.getElementById('rdDoLandscape').addEventListener('click', function() { rdHideHint(); rdPrintLandscape(); });
  document.getElementById('rdCancelPrint').addEventListener('click', rdHideHint);
}
window.addEventListener('load', function() {
  if (window.location.search.includes('autoprint')) {
    setTimeout(rdPrint, 500);
  }
});
</script>
</head>
<body>

<!-- Print bar -->
<div class="print-bar no-print">
  <strong><?= htmlspecialchars($tpl['name']) ?></strong>
  <a href="javascript:history.back()" style="color:#fff;opacity:.8;font-size:12px;margin-left:4px">← Zurück</a>
  <?php if (!empty($project)): ?>
  <span style="font-size:11px;opacity:.8;margin-left:8px">
    <i style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($project['color']??'#888') ?>;vertical-align:middle;margin-right:4px"></i>
    <?= htmlspecialchars($project['name']) ?>
    <?php if (!empty($filters['date_from'])): ?>
    · <?= htmlspecialchars($filters['date_from']) ?> – <?= htmlspecialchars($filters['date_to'] ?: 'heute') ?>
    <?php endif; ?>
  </span>
  <?php endif; ?>
  <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
    <span style="font-size:10px;opacity:.6">Ränder: Keine · Kopf/Fußzeilen: Aus</span>
    <button onclick="rdPrint()" style="background:#fff;color:<?= htmlspecialchars($primary) ?>;border:none;padding:5px 16px;border-radius:4px;font-weight:700;cursor:pointer;font-size:12px">🖨 PDF speichern</button>
  </div>
</div>
<div style="height:44px" class="no-print"></div>

<div class="rpt-wrap">
<!-- Report Header -->
<div class="rpt-header">
  <?php if ($logo): ?>
  <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" onerror="this.style.display='none'">
  <?php endif; ?>
  <div class="rpt-header-text">
    <h1><?= htmlspecialchars($header['title'] ?? $tpl['name']) ?></h1>
    <?php if (!empty($header['subtitle'])): ?>
    <p><?= htmlspecialchars($header['subtitle']) ?></p>
    <?php endif; ?>
  </div>
  <?php if (!empty($header['showDate'])): ?>
  <div class="rpt-header-meta">
    <?= date('d.m.Y H:i') ?>
    <?php if (!empty($filters['date_from'])): ?>
    <br><?= htmlspecialchars($filters['date_from']) ?> – <?= htmlspecialchars($filters['date_to'] ?: 'heute') ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Blocks -->
<?php
// Group blocks into rows for side-by-side layout
$blockRows = [];
$curRow    = [];
foreach ($blocks as $blk) {
    $w = $blk['cfg']['width'] ?? $blk['config']['width'] ?? 'full';
    if ($w === 'full') {
        if ($curRow) { $blockRows[] = $curRow; $curRow = []; }
        $blockRows[] = [$blk];
    } else {
        $curRow[] = $blk;
        // If two halves or third+two-third -> close row
        $totalW = array_sum(array_map(fn($b) => match($b['cfg']['width']??$b['config']['width']??'full'){
            'half'=>50,'third'=>33,'two-third'=>67, default=>100}, $curRow));
        if ($totalW >= 95) { $blockRows[] = $curRow; $curRow = []; }
    }
}
if ($curRow) $blockRows[] = $curRow;
?>

<?php foreach ($blockRows as $row): ?>
<div class="block-grid">
<?php foreach ($row as $block):
  $btype = $block['type'] ?? '';
  $bcfg  = $block['cfg'] ?? $block['config'] ?? [];
  $bw    = $bcfg['width'] ?? 'full';
  $barH  = max(8,  min(32, (int)($bcfg['barHeight'] ?? 12)));
  $kpiCols = max(2, min(4, (int)($bcfg['kpiCols'] ?? 4)));
?>
<div class="block-col w-<?= htmlspecialchars($bw) ?>">

<?php if ($btype === 'page_break'): ?>
</div></div>
<div class="page-break"></div>
<div class="block-grid"><div class="block-col w-full">

<?php elseif ($btype === 'divider'): ?>
<hr style="border:none;border-top:1px solid #e0e0e0;margin:4px 0">

<?php elseif ($btype === 'project_header' && $project): ?>
<div class="section" style="margin-bottom:16px">
  <div class="proj-header-bar" style="background:<?= htmlspecialchars($project['color'] ?? $primary) ?>">
    <div style="flex:1">
      <div style="font-size:15px;font-weight:800"><?= htmlspecialchars($project['name']) ?></div>
      <?php if (!empty($bcfg['showStatus']) && !empty($project['status'])): ?>
      <div style="font-size:10px;opacity:.85;margin-top:2px">Status: <?= htmlspecialchars($project['status']) ?></div>
      <?php endif; ?>
    </div>
    <?php if ($bcfg['showStats'] ?? true): ?>
    <div style="text-align:right">
      <div style="font-size:20px;font-weight:800;line-height:1"><?= $total ?></div>
      <div style="font-size:9px;opacity:.8">Einträge</div>
    </div>
    <?php endif; ?>
  </div>
  <?php if (($bcfg['showDesc'] ?? true) && !empty($project['description'])): ?>
  <div class="proj-header-body" style="border-color:<?= htmlspecialchars($project['color'] ?? $primary) ?>">
    <p style="font-size:10px;color:#4b5563;margin:0"><?= htmlspecialchars($project['description']) ?></p>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($btype === 'project_header' && !$project): ?>
<div class="section" style="background:#fff3cd;padding:9px 12px;border-radius:5px;font-size:10px;color:#856404">
  ⚠ Kein Projekt ausgewählt — Projektinfo-Header leer.
</div>

<?php elseif ($btype === 'summary'): ?>
<div class="section no-break">
  <div class="section-title">Kennzahlen</div>
  <div class="kpi-grid" style="grid-template-columns:repeat(<?= $kpiCols ?>,1fr)">
    <div class="kpi-card"><div class="kpi-num"><?= $total ?></div><div class="kpi-lbl">Gesamt</div></div>
    <div class="kpi-card"><div class="kpi-num"><?= $open ?></div><div class="kpi-lbl">Offen</div></div>
    <div class="kpi-card"><div class="kpi-num"><?= $done ?></div><div class="kpi-lbl">Erledigt</div></div>
    <div class="kpi-card"><div class="kpi-num"><?= $types ?></div><div class="kpi-lbl">Typen</div></div>
  </div>
</div>

<?php elseif ($btype === 'chart_type' && $byType): ?>
<div class="section">
  <div class="section-title">Nach Typ</div>
  <?php $max = max(array_column($byType,'cnt')?:[1]); ?>
  <?php foreach ($byType as $row): ?>
  <div class="bar-row">
    <div class="bar-lbl"><?= htmlspecialchars($row['name']??'–') ?></div>
    <div class="bar-track" style="height:<?= $barH ?>px">
      <div class="bar-fill" style="width:<?= round($row['cnt']/$max*100) ?>%;background:<?= htmlspecialchars($row['color']??$primary) ?>">
        <?= $row['cnt'] > 2 ? $row['cnt'] : '' ?>
      </div>
    </div>
    <div class="bar-val"><?= $row['cnt'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($btype === 'chart_status' && $byStatus): ?>
<div class="section">
  <div class="section-title">Nach Status</div>
  <?php $max = max(array_column($byStatus,'cnt')?:[1]); ?>
  <?php foreach ($byStatus as $row): ?>
  <?php $sc = $statusColors[$row['status']??''] ?? '#6b7280'; ?>
  <div class="bar-row">
    <div class="bar-lbl"><?= htmlspecialchars($row['status']??'–') ?></div>
    <div class="bar-track" style="height:<?= $barH ?>px">
      <div class="bar-fill" style="width:<?= round($row['cnt']/$max*100) ?>%;background:<?= $sc ?>">
        <?= $row['cnt'] > 2 ? $row['cnt'] : '' ?>
      </div>
    </div>
    <div class="bar-val"><?= $row['cnt'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($btype === 'chart_priority'): ?>
<?php
  $byPrio = [];
  foreach ($entries as $e) { $p=$e['priority']??'–'; $byPrio[$p]=($byPrio[$p]??0)+1; }
  arsort($byPrio); $maxP = max($byPrio?:[1]);
?>
<?php if ($byPrio): ?>
<div class="section">
  <div class="section-title">Nach Priorität</div>
  <?php foreach ($byPrio as $prio => $cnt): ?>
  <?php $pc = $priorityColors[$prio] ?? '#6b7280'; ?>
  <div class="bar-row">
    <div class="bar-lbl"><?= htmlspecialchars($prio) ?></div>
    <div class="bar-track" style="height:<?= $barH ?>px">
      <div class="bar-fill" style="width:<?= round($cnt/$maxP*100) ?>%;background:<?= $pc ?>">
        <?= $cnt > 2 ? $cnt : '' ?>
      </div>
    </div>
    <div class="bar-val"><?= $cnt ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($btype === 'chart_firmware' && $byFirmware): ?>
<div class="section">
  <div class="section-title">Nach Firmware</div>
  <?php $max = max(array_column($byFirmware,'cnt')?:[1]); ?>
  <?php foreach ($byFirmware as $row): ?>
  <div class="bar-row">
    <div class="bar-lbl"><?= htmlspecialchars($row['firmware_version']??'–') ?></div>
    <div class="bar-track" style="height:<?= $barH ?>px">
      <div class="bar-fill" style="width:<?= round($row['cnt']/$max*100) ?>%;background:#8b5cf6">
        <?= $row['cnt'] > 2 ? $row['cnt'] : '' ?>
      </div>
    </div>
    <div class="bar-val"><?= $row['cnt'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($btype === 'table'): ?>
<?php
  $limit     = (int)($bcfg['limit'] ?? 50);
  $rawWidths = $bcfg['colWidths'] ?? [];
  $colWidths = array_map('intval', is_array($rawWidths) ? $rawWidths : []);

  // rowGroups: array of arrays — each defines columns for that sub-row
  $rowGroups = $bcfg['rowGroups'] ?? null;
  if (!$rowGroups || !is_array($rowGroups)) {
      // Fall back to single row or multiRow mode
      $selColsFb = $bcfg['columns'] ?? ['entry_date','title','status','priority','type_name'];
      $multiRow  = filter_var($bcfg['multiRow'] ?? false, FILTER_VALIDATE_BOOLEAN);
      $rowGroups = $multiRow
          ? [$selColsFb, ['description']]  // auto: cols on row1, description on row2
          : [$selColsFb];
  }
  // First row defines the table columns (header)
  $selCols    = $rowGroups[0];
  $extraRows  = array_slice($rowGroups, 1); // additional sub-rows
  $rows       = array_slice($entries, 0, $limit);
  $totalUnits = array_sum(array_map(fn($c) => $colWidths[$c] ?? $colDefaultWidths[$c] ?? 2, $selCols));
?>
<?php
  $blkOrient = $bcfg['orientation'] ?? 'inherit';
  $blkOrientClass = ($blkOrient === 'landscape') ? 'blk-landscape' : (($blkOrient === 'portrait') ? 'blk-portrait' : '');
?>
<div class="section" style="page-break-before:auto;break-before:auto">
  <div class="section-title">Einträge<?= $multiRow ? ' <span style="font-weight:400;font-size:9px;opacity:.7">(mehrzeilig)</span>' : '' ?></div>
  <div class="tbl-wrap">
  <table>
    <colgroup>
      <?php foreach ($selCols as $col): ?>
      <?php $units = $colWidths[$col] ?? $colDefaultWidths[$col] ?? 2; $minW = $units * 8; ?>
      <col style="min-width:<?= $minW ?>px">
      <?php endforeach; ?>
    </colgroup>
    <thead><tr>
      <?php foreach ($selCols as $col): ?>
      <th><?= htmlspecialchars($colLabels[$col] ?? $col) ?></th>
      <?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $e): ?>
    <tr>
      <?php foreach ($selCols as $col): ?>
      <?php
        $units   = $colWidths[$col] ?? $colDefaultWidths[$col] ?? 2;
        $isWide  = $units >= 2;
        // Title and description always wrap; multiRow forces wrap on title
        $forceWrap = $multiRow && in_array($col, ['title','description']);
        $tdClass = ($isWide || $forceWrap) ? 'wrap' : 'nowrap';
      ?>
      <td class="<?= $tdClass ?>"><?php
        if ($col === 'entry_date') {
          echo date('d.m.Y', strtotime($e['entry_date']));
          if ($multiRow) {
            $t = $e['entry_time'] ?? '';
            if ($t) echo '<div class="tbl-row-sub">'.htmlspecialchars(substr($t,0,5)).'</div>';
          }
        } elseif ($col === 'title') {
          echo '<span class="tbl-row-main">'.htmlspecialchars($e['title']).'</span>';
          if ($multiRow) {
            // Show type + status as compact sub-line
            $subParts = [];
            if (!empty($e['type_name'])) $subParts[] = htmlspecialchars($e['type_name']);
            if (!empty($e['creator']))   $subParts[] = htmlspecialchars($e['creator']);
            if ($subParts) echo '<div class="tbl-row-sub">'.implode(' · ', $subParts).'</div>';
            // Show description on third line
            if (!empty($e['description'])) {
              $desc = mb_substr($e['description'], 0, 200);
              if (mb_strlen($e['description']) > 200) $desc .= '…';
              echo '<div class="tbl-row-sub" style="color:#4b5563;margin-top:2px">'.htmlspecialchars($desc).'</div>';
            }
          }
        } elseif ($col === 'status') {
          $sc = $statusColors[$e['status']??''] ?? '#6b7280';
          echo '<span class="badge-s" style="background:'.$sc.'">'.htmlspecialchars($e['status']??'–').'</span>';
        } elseif ($col === 'priority') {
          $pc = $priorityColors[$e['priority']??''] ?? '#6b7280';
          echo '<span class="badge-p" style="background:'.$pc.'">'.htmlspecialchars($e['priority']??'–').'</span>';
        } elseif ($col === 'type_name') {
          $tc = htmlspecialchars($e['type_color'] ?? '#6b7280');
          echo '<span style="color:'.$tc.';font-weight:600">'.htmlspecialchars($e['type_name']??'–').'</span>';
        } elseif ($col === 'description') {
          $maxC = $isWide ? 300 : 120;
          echo htmlspecialchars(mb_substr($e[$col]??'',0,$maxC));
          if (mb_strlen($e[$col]??'') > $maxC) echo '…';
        } else {
          echo htmlspecialchars($e[$col]??'–');
        }
      ?></td>
      <?php endforeach; ?>
    </tr>
    <?php if ($extraRows): ?>
    <tr class="tbl-subrow">
      <td colspan="<?= count($selCols) ?>" style="padding:0 0 4px 0;border-bottom:1px solid #e8ecf0">
        <div style="display:flex;flex-wrap:wrap;gap:0 16px;padding:3px 6px;background:#f8f9ff;font-size:9px;color:#4b5563">
        <?php foreach ($extraRows as $extraGrp): ?>
        <?php foreach ($extraGrp as $ec): ?>
        <?php if (!empty($e[$ec])): ?>
        <span><span style="color:#9ca3af;font-size:8px"><?= htmlspecialchars($colLabels[$ec] ?? $ec) ?>:</span>
        <?php if ($ec === 'description'): ?>
          <?= htmlspecialchars(mb_substr($e['description']??'', 0, 300)) ?><?= mb_strlen($e['description']??'')>300?'…':'' ?>
        <?php elseif ($ec === 'status'): ?>
          <?php $sc2=$statusColors[$e['status']??'']??'#6b7280'; ?>
          <span class="badge-s" style="background:<?= $sc2 ?>"><?= htmlspecialchars($e['status']??'') ?></span>
        <?php elseif ($ec === 'priority'): ?>
          <?php $pc2=$priorityColors[$e['priority']??'']??'#6b7280'; ?>
          <span class="badge-p" style="background:<?= $pc2 ?>"><?= htmlspecialchars($e['priority']??'') ?></span>
        <?php else: ?>
          <?= htmlspecialchars($e[$ec]??'') ?>
        <?php endif; ?>
        </span>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endforeach; ?>
        </div>
      </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if (count($entries) > $limit): ?>
  <p style="font-size:9px;color:#9ca3af;margin-top:4px">… und <?= count($entries)-$limit ?> weitere Einträge</p>
  <?php endif; ?>
</div>

<?php elseif ($btype === 'top_issues'): ?>
<?php
  $lim  = (int)($bcfg['limit'] ?? 10);
  $pmap = ['Highest'=>0,'High'=>1,'Medium'=>2,'Low'=>3];
  $sorted = $entries;
  usort($sorted, fn($a,$b) => ($pmap[$a['priority']??'']??9) <=> ($pmap[$b['priority']??'']??9));
  $top = array_slice($sorted, 0, $lim);
?>
<div class="section">
  <div class="section-title">Top <?= $lim ?> Issues</div>
  <table style="table-layout:auto">
    <thead><tr>
      <th style="width:24px">#</th><th>Titel</th>
      <th style="width:70px">Priorität</th><th style="width:70px">Status</th>
      <th style="width:80px">Typ</th><th style="width:70px">Datum</th>
    </tr></thead>
    <tbody>
    <?php foreach ($top as $i => $e): ?>
    <?php $pc=$priorityColors[$e['priority']??'']??'#6b7280'; $sc=$statusColors[$e['status']??'']??'#6b7280'; ?>
    <tr>
      <td style="color:#9ca3af"><?= $i+1 ?></td>
      <td class="wrap">
        <strong><?= htmlspecialchars($e['title']) ?></strong>
        <?php if (!empty($e['epic_title'])): ?>
        <div class="tbl-row-sub">Epic: <?= htmlspecialchars($e['epic_title']) ?></div>
        <?php endif; ?>
      </td>
      <td><span class="badge-p" style="background:<?= $pc ?>"><?= htmlspecialchars($e['priority']??'–') ?></span></td>
      <td><span class="badge-s" style="background:<?= $sc ?>"><?= htmlspecialchars($e['status']??'–') ?></span></td>
      <td style="color:#6b7280"><?= htmlspecialchars($e['type_name']??'–') ?></td>
      <td style="color:#9ca3af;white-space:nowrap"><?= date('d.m.Y',strtotime($e['entry_date'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($btype === 'timeline'): ?>
<div class="section">
  <div class="section-title">Timeline</div>
  <?php
    $grouped = []; foreach ($entries as $e) $grouped[$e['entry_date']][] = $e;
    krsort($grouped); $shown = 0; $maxTl = (int)($bcfg['limit'] ?? 50);
  ?>
  <?php foreach ($grouped as $date => $dayEntries): ?>
  <?php if ($shown >= $maxTl) break; ?>
  <div style="margin-bottom:9px">
    <div style="font-size:9.5px;font-weight:700;color:<?= htmlspecialchars($primary) ?>;border-bottom:1px solid <?= htmlspecialchars($primary) ?>33;padding-bottom:2px;margin-bottom:4px">
      <?= date('d. F Y', strtotime($date)) ?>
      <span style="font-weight:400;color:#9ca3af;margin-left:4px"><?= count($dayEntries) ?>×</span>
    </div>
    <?php foreach ($dayEntries as $e): ?>
    <?php if ($shown++ >= $maxTl) break; ?>
    <?php $sc=$statusColors[$e['status']??'']??'#6b7280'; ?>
    <div class="tl-item">
      <div class="tl-dot"><span style="background:<?= $sc ?>"></span></div>
      <div class="tl-content">
        <strong><?= htmlspecialchars($e['title']) ?></strong>
        <span style="font-size:9px;color:#9ca3af;margin-left:4px"><?= htmlspecialchars($e['type_name']??'') ?></span>
        <span class="badge-s" style="background:<?= $sc ?>;font-size:8px;margin-left:3px"><?= htmlspecialchars($e['status']??'') ?></span>
        <?php if (!empty($e['description'])): ?>
        <div class="tbl-row-sub"><?= htmlspecialchars(mb_substr($e['description'],0,120)) ?><?= mb_strlen($e['description']??'')>120?'…':'' ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($btype === 'text' && !empty($bcfg['text'])): ?>
<div class="section">
  <div class="text-block"><?= nl2br(htmlspecialchars($bcfg['text'])) ?></div>
</div>

<?php endif; ?>

</div><!-- .block-col -->
<?php endforeach; ?>
</div><!-- .block-grid -->
<?php endforeach; ?>

<!-- Footer -->
<?php if (!empty($footer['text']) || !empty($footer['showPage'])): ?>
<div class="rpt-footer">
  <span><?= htmlspecialchars($footer['text'] ?? '') ?></span>
  <?php if (!empty($footer['showPage'])): ?><span>Seite 1</span><?php endif; ?>
</div>
<?php endif; ?>

</div><!-- .rpt-wrap -->
</body>
</html>
