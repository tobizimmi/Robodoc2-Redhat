<?php
$primary = e($tpl['primary_color'] ?? '#1e3a5f');
$accent  = e($tpl['accent_color']  ?? '#3b82f6');
$font    = e($tpl['font_family']   ?? 'Arial, sans-serif');

// Image size chosen in the export wizard (small/medium/large/full)
$imgSizes = [
    'small'  => ['minWidth' => 120, 'maxHeight' => 110],
    'medium' => ['minWidth' => 200, 'maxHeight' => 180],
    'large'  => ['minWidth' => 320, 'maxHeight' => 300],
    'full'   => ['minWidth' => 100, 'maxHeight' => 600], // 1 column via minWidth below
];
$imgSize = $imgSizes[$imageSize ?? 'medium'] ?? $imgSizes['medium'];
$imgMinWidth  = ($imageSize ?? 'medium') === 'full' ? '100%' : $imgSize['minWidth'] . 'px';
$imgMaxHeight = $imgSize['maxHeight'];

// Helper: embed image as base64
function embedImage(string $path): string {
    if (!file_exists($path)) return '';
    $mime = mime_content_type($path) ?: 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
}
// Resolve an attachment the same way AttachmentController does (file_path
// first, UPLOAD_DIR.filename as fallback for older records) before embedding
// it. Attachments live in per-entry subdirectories under UPLOAD_DIR, so
// UPLOAD_DIR . filename alone almost never resolves to a real file.
function embedAttachmentImage(array $att): string {
    if (!empty($att['file_path']) && file_exists($att['file_path'])) {
        return embedImage($att['file_path']);
    }
    if (!empty($att['filename']) && file_exists(UPLOAD_DIR . $att['filename'])) {
        return embedImage(UPLOAD_DIR . $att['filename']);
    }
    return '';
}
function isImageFile(string $filename): bool {
    return (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i', $filename);
}
?>
<style>
  body { font-family: <?= $font ?>; font-size: 11pt; color: #1a1a1a; }
  .export-wrap { max-width: 900px; margin: 0 auto; padding: 20px; }
  /* Header / Footer */
  .export-header { background: <?= $primary ?>; color: #fff; padding: 20px 24px; margin-bottom: 24px; display:flex; align-items:center; gap:16px; }
  .export-header img.logo { max-height: 48px; max-width: 160px; object-fit:contain; }
  .export-header .hdr-text h1 { font-size: 14pt; font-weight: 700; }
  .export-header .hdr-text p  { font-size: 9pt; opacity:.85; margin-top:3px; }
  .export-footer { border-top: 2px solid <?= $primary ?>; margin-top: 32px; padding-top: 10px; font-size: 8pt; color: #666; display:flex; justify-content:space-between; }
  /* Entry title */
  .entry-title { font-size: 18pt; font-weight: 700; color: <?= $primary ?>; margin-bottom: 6px; }
  .entry-subtitle { font-size: 9pt; color: #666; margin-bottom: 20px; }
  /* Section */
  .section { margin-bottom: 24px; }
  .section-title { font-size: 11pt; font-weight: 700; color: <?= $primary ?>; border-bottom: 2px solid <?= $accent ?>; padding-bottom: 4px; margin-bottom: 12px; }
  /* Metadata grid */
  .meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
  .meta-item { background: #f8f9fa; border-radius: 4px; padding: 8px 10px; }
  .meta-label { font-size: 7.5pt; color: #888; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 2px; }
  .meta-value { font-size: 9.5pt; font-weight: 600; }
  /* Description */
  .description { background: #f8f9fa; border-left: 4px solid <?= $accent ?>; padding: 12px 16px; border-radius: 0 4px 4px 0; line-height: 1.6; white-space: pre-wrap; }
  /* Badges */
  .badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:8pt; font-weight:600; }
  .badge-status-new      { background:#3b82f6;color:#fff; }
  .badge-status-open     { background:#f59e0b;color:#fff; }
  .badge-status-done     { background:#10b981;color:#fff; }
  .badge-status-closed   { background:#6b7280;color:#fff; }
  .badge-priority-high   { background:#ef4444;color:#fff; }
  .badge-priority-medium { background:#f59e0b;color:#fff; }
  .badge-priority-low    { background:#10b981;color:#fff; }
  /* Comments */
  .comment { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; margin-bottom: 10px; }
  .comment-meta { font-size: 8pt; color: #888; margin-bottom: 4px; }
  .comment-text { font-size: 9.5pt; line-height: 1.5; white-space: pre-wrap; }
  /* Attachments */
  .attachment-list { display: flex; flex-wrap: wrap; gap: 8px; }
  .attachment-item { border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px 10px; font-size: 8.5pt; background: #f9fafb; }
  .img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(<?= $imgMinWidth ?>, 1fr)); gap: 10px; margin-top: 10px; }
  .img-grid img { width: 100%; border-radius: 4px; border: 1px solid #e5e7eb; max-height: <?= $imgMaxHeight ?>px; object-fit: contain; }
  /* Test Results */
  .tr-card { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
  .tr-card-hdr { background: <?= $primary ?>; color: #fff; padding: 7px 12px; font-size: 9pt; font-weight: 600; display:flex; justify-content:space-between; }
  .tr-card-body { padding: 10px 12px; font-size: 9pt; }
  .tr-field { margin-bottom: 6px; }
  .tr-field-label { font-size: 7.5pt; color: #888; text-transform: uppercase; }
  .tr-outcome { padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight:600; }
  .tr-outcome-passed  { background:#d1fae5;color:#065f46; }
  .tr-outcome-failed  { background:#fee2e2;color:#991b1b; }
  .tr-outcome-blocked { background:#fef3c7;color:#92400e; }
  /* History */
  .history-row { display:flex; gap:10px; font-size:8.5pt; padding:4px 0; border-bottom:1px solid #f3f4f6; }
  .history-date { color:#888; min-width:110px; }
  /* Print button */
  .print-bar { position:fixed; top:10px; right:10px; z-index:999; background:#fff; border:1px solid #ddd; border-radius:6px; padding:8px 12px; box-shadow:0 2px 8px rgba(0,0,0,.15); display:flex; gap:8px; }
  .print-bar button { padding:6px 14px; border-radius:4px; border:none; cursor:pointer; font-size:9pt; }
  .btn-print { background:<?= $primary ?>;color:#fff; }
  .btn-close-exp { background:#f3f4f6;color:#333; }
  @media print { .print-bar { display:none; } }
</style>

<div class="no-print print-bar">
  <button class="btn-print" onclick="window.print()"><i>🖨</i> Print / Save PDF</button>
  <button class="btn-close-exp" onclick="window.close()">Close</button>
</div>

<div class="export-wrap">

<?php /* ── HEADER ──────────────────────────────────────────────────── */ ?>
<div class="export-header">
  <?php if (!empty($tpl['logo_path']) && file_exists($tpl['logo_path'])): ?>
  <img class="logo" src="<?= embedImage($tpl['logo_path']) ?>" alt="Logo">
  <?php endif; ?>
  <?php if (!empty($tpl['header_html'])): ?>
  <div class="hdr-text"><?= $tpl['header_html'] ?></div>
  <?php else: ?>
  <div class="hdr-text">
    <h1><?= e($project['name'] ?? 'RoboDoc') ?></h1>
    <p>Entry Report &mdash; <?= date('d.m.Y') ?></p>
  </div>
  <?php endif; ?>
</div>

<?php /* ── TITLE ─────────────────────────────────────────────────── */ ?>
<div class="entry-title"><?= e($entry['title']) ?></div>
<div class="entry-subtitle">
  <span class="badge badge-status-<?= e($entry['status'] ?? 'new') ?>"><?= ucfirst($entry['status'] ?? 'new') ?></span>
  &nbsp;
  <?php if (!empty($entry['priority'])): ?>
  <span class="badge badge-priority-<?= e($entry['priority']) ?>"><?= ucfirst($entry['priority']) ?></span>
  &nbsp;
  <?php endif; ?>
  <span style="color:#888"><?= e($entry['entry_type_name'] ?? '') ?> &nbsp;|&nbsp; #<?= $entry['id'] ?></span>
</div>

<?php /* ── METADATA ──────────────────────────────────────────────── */ ?>
<?php if ($fields['metadata']): ?>
<div class="section">
  <div class="section-title">Details</div>
  <div class="meta-grid">
    <?php $metaFields = [
      'Date'         => date('d.m.Y', strtotime($entry['entry_date'])),
      'Project'      => $entry['project_name'] ?? '—',
      'Type'         => $entry['entry_type_name'] ?? '—',
      'Created by'   => $entry['created_by_name'] ?? '—',
      'Assigned to'  => $entry['assigned_to_name'] ?? '—',
      'Category'     => $entry['error_category_name'] ?? '—',
      'Environment'  => $entry['environment_name'] ?? '—',
      'Firmware'     => $entry['firmware_version'] ?? '—',
      'Mower Serial' => $entry['mower_serial'] ?? '—',
    ];
    if (!empty($entry['epic_title'])) $metaFields['Epic'] = $entry['epic_title'];
    if (!empty($entry['jira_issue_key']) && $fields['jira_info']) $metaFields['Jira'] = $entry['jira_issue_key'];
    foreach ($metaFields as $label => $value): ?>
    <div class="meta-item">
      <div class="meta-label"><?= e($label) ?></div>
      <div class="meta-value"><?= e($value) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php /* ── DESCRIPTION ───────────────────────────────────────────── */ ?>
<?php if ($fields['description'] && !empty($entry['description'])): ?>
<div class="section">
  <div class="section-title">Description</div>
  <div class="description"><?= e($entry['description']) ?></div>
</div>
<?php endif; ?>

<?php /* ── TEST RESULTS ──────────────────────────────────────────── */ ?>
<?php if ($fields['test_results'] && !empty($testResults)): ?>
<div class="section">
  <div class="section-title">Partial Results (<?= count($testResults) ?>)</div>
  <?php foreach ($testResults as $i => $tr): ?>
  <div class="tr-card">
    <div class="tr-card-hdr">
      <span>Result #<?= $i+1 ?><?= !empty($tr['mower_serial'])?' — '.$tr['mower_serial']:'' ?></span>
      <?php if (!empty($tr['test_result'])): ?>
      <span class="tr-outcome tr-outcome-<?= strtolower($tr['test_result']) ?>"><?= e($tr['test_result']) ?></span>
      <?php endif; ?>
    </div>
    <div class="tr-card-body">
      <?php if (!empty($tr['test_setup'])): ?><div class="tr-field"><div class="tr-field-label">Setup</div><?= e($tr['test_setup']) ?></div><?php endif; ?>
      <?php if (!empty($tr['test_doc'])): ?><div class="tr-field"><div class="tr-field-label">Documentation</div><?= e($tr['test_doc']) ?></div><?php endif; ?>
      <?php if (!empty($tr['notes'])): ?><div class="tr-field"><div class="tr-field-label">Notes</div><?= e($tr['notes']) ?></div><?php endif; ?>
      <?php if ($fields['images'] && !empty($tr['attachments'])): ?>
      <div class="img-grid">
        <?php foreach ($tr['attachments'] as $att): ?>
        <?php if (isImageFile($att['filename'] ?? '')): ?>
        <img src="<?= embedAttachmentImage($att) ?>" alt="<?= e($att['original_name'] ?? '') ?>">
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ── ATTACHMENTS + IMAGES ──────────────────────────────────── */ ?>
<?php if ($fields['attachments'] && !empty($attachments)): ?>
<?php $imgAtts  = array_filter($attachments, fn($a) => isImageFile($a['filename'] ?? '')); ?>
<?php $fileAtts = array_filter($attachments, fn($a) => !isImageFile($a['filename'] ?? '')); ?>
<?php if ($fields['images'] && $imgAtts): ?>
<div class="section">
  <div class="section-title">Images (<?= count($imgAtts) ?>)</div>
  <div class="img-grid">
    <?php foreach ($imgAtts as $att): ?>
    <figure style="margin:0;text-align:center">
      <img src="<?= embedAttachmentImage($att) ?>" alt="<?= e($att['original_name'] ?? '') ?>">
      <figcaption style="font-size:7.5pt;color:#888;margin-top:3px"><?= e($att['original_name'] ?? $att['display_name'] ?? '') ?></figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php if ($fileAtts): ?>
<div class="section">
  <div class="section-title">Attachments (<?= count($fileAtts) ?>)</div>
  <div class="attachment-list">
    <?php foreach ($fileAtts as $att): ?>
    <div class="attachment-item">📎 <?= e($att['original_name'] ?? $att['display_name'] ?? $att['filename'] ?? '') ?>
      <span style="color:#aaa;font-size:7.5pt"> (<?= round(($att['file_size']??0)/1024) ?>KB)</span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php /* ── COMMENTS ──────────────────────────────────────────────── */ ?>
<?php if ($fields['comments'] && !empty($comments)): ?>
<div class="section">
  <div class="section-title">Comments (<?= count($comments) ?>)</div>
  <?php foreach ($comments as $c): ?>
  <div class="comment">
    <div class="comment-meta">
      <strong><?= e($c['user_name'] ?? 'Unknown') ?></strong> &mdash;
      <?= date('d.m.Y H:i', strtotime($c['created_at'])) ?>
    </div>
    <div class="comment-text"><?= e($c['comment'] ?? $c['body'] ?? '') ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ── SUB-ENTRIES ───────────────────────────────────────────── */ ?>
<?php if ($fields['sub_entries'] && !empty($subEntries)): ?>
<div class="section">
  <div class="section-title">Sub-Entries (<?= count($subEntries) ?>)</div>
  <?php foreach ($subEntries as $sub): ?>
  <div style="display:flex;gap:10px;padding:5px 0;border-bottom:1px solid #f3f4f6;font-size:9pt">
    <span style="color:#888;min-width:80px"><?= date('d.m.Y',strtotime($sub['entry_date'])) ?></span>
    <span class="badge badge-status-<?= e($sub['status']??'new') ?>"><?= ucfirst($sub['status']??'new') ?></span>
    <span><?= e($sub['title']) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ── HISTORY ───────────────────────────────────────────────── */ ?>
<?php if ($fields['history'] && !empty($history)): ?>
<div class="section">
  <div class="section-title">Change History</div>
  <?php foreach ($history as $h): ?>
  <div class="history-row">
    <span class="history-date"><?= date('d.m.Y H:i', strtotime($h['changed_at'] ?? $h['created_at'] ?? '')) ?></span>
    <span style="color:#555;min-width:100px"><?= e($h['user_name']??'') ?></span>
    <span><?= e($h['field_name']??'') ?> changed: <?= e($h['old_value']??'') ?> → <?= e($h['new_value']??'') ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ── FOOTER ────────────────────────────────────────────────── */ ?>
<div class="export-footer">
  <?php if (!empty($tpl['footer_html'])): ?>
  <?= $tpl['footer_html'] ?>
  <?php else: ?>
  <span><?= e($project['name'] ?? 'RoboDoc') ?> &mdash; Entry #<?= $entry['id'] ?></span>
  <span>Generated <?= date('d.m.Y H:i') ?></span>
  <?php endif; ?>
</div>

</div><!-- .export-wrap -->
