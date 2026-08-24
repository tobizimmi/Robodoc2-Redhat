<?php
// Embed image attachments as base64 (self-contained export); non-images shown as a filename tag.
function eightd_embed_attachments(array $attachments): string {
    if (!$attachments) return '';
    $html = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">';
    foreach ($attachments as $att) {
        if (!is_file($att['file_path'])) continue;
        $name = e($att['original_name']);
        if (str_starts_with($att['mime_type'] ?? '', 'image/')) {
            $data = 'data:' . $att['mime_type'] . ';base64,' . base64_encode(file_get_contents($att['file_path']));
            $html .= '<img src="' . $data . '" style="max-width:180px;max-height:140px;object-fit:contain;border:1px solid #ddd;border-radius:4px" alt="' . $name . '">';
        } else {
            $html .= '<div style="border:1px solid #ddd;border-radius:4px;padding:8px;font-size:8.5pt;max-width:180px;align-self:flex-start">' . $name . '</div>';
        }
    }
    return $html . '</div>';
}

$sixM = ['Mensch', 'Maschine', 'Methode', 'Material', 'Mitwelt', 'Messung'];
$isIsNotFields = ['what' => 'Was', 'where' => 'Wo', 'when' => 'Wann', 'extent' => 'Umfang'];
$disciplineLabels = [
    'd3' => 'D3 — Sofortmaßnahmen (Containment)',
    'd5' => 'D5 — Dauerhafte Korrekturmaßnahmen',
    'd6' => 'D6 — Umsetzung & Validierung',
    'd7' => 'D7 — Vorbeugende / systemische Maßnahmen',
];
$statusLabels = ['open' => 'Offen', 'in_progress' => 'In Arbeit', 'done' => 'Erledigt', 'verified' => 'Verifiziert'];
?>
<style>
  body { font-family: Arial, sans-serif; font-size: 10.5pt; color: #1a1a1a; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 20px; }
  .hdr { background: #1e3a5f; color: #fff; padding: 18px 22px; margin-bottom: 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
  .hdr .ref { font-family: monospace; font-size: 13pt; font-weight: 700; }
  .hdr h1 { font-size: 15pt; margin-top: 4px; }
  .hdr .status { font-size: 8.5pt; padding: 3px 10px; border-radius: 10px; background: rgba(255,255,255,.2); }
  .section { margin-bottom: 20px; page-break-inside: avoid; }
  .section-title { font-size: 11pt; font-weight: 700; color: #1e3a5f; border-bottom: 2px solid #3b82f6; padding-bottom: 4px; margin-bottom: 10px; }
  .meta { font-size: 8.5pt; color: #666; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 9pt; vertical-align: top; }
  th { background: #f3f4f6; }
  .box { border: 1px solid #ddd; border-radius: 4px; padding: 10px 12px; margin-bottom: 8px; white-space: pre-wrap; font-size: 9.5pt; min-height: 1.2em; }
  .fw-chain { border: 1px solid #ddd; border-radius: 4px; padding: 8px 12px; margin-bottom: 8px; }
  .fw-chain .why { font-size: 9pt; margin: 2px 0; }
  .ik-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
  .ik-cat { border: 1px solid #ddd; border-radius: 4px; padding: 6px 10px; }
  .ik-cat .cat-name { font-weight: 700; font-size: 9pt; margin-bottom: 4px; }
  .ik-cat ul { margin-left: 16px; font-size: 8.5pt; }
  .badge-status { display:inline-block; padding: 2px 8px; border-radius: 8px; font-size: 8pt; background:#e5e7eb; }
</style>
<div class="wrap">
  <div class="hdr">
    <div>
      <div class="ref"><?= e($report['reference']) ?></div>
      <h1><?= e($report['title']) ?></h1>
    </div>
    <div class="status"><?= $report['status'] === 'closed' ? 'Abgeschlossen' : 'Offen' ?></div>
  </div>

  <div class="meta">
    <?php if ($project): ?>Projekt: <?= e($project['name']) ?> &nbsp;·&nbsp; <?php endif; ?>
    Erstellt: <?= date('d.m.Y', strtotime($report['created_at'])) ?>
    <?php if ($report['d8_closed_at']): ?> &nbsp;·&nbsp; Abgeschlossen: <?= date('d.m.Y', strtotime($report['d8_closed_at'])) ?><?php endif; ?>
    <?php if ($linkedEntry): ?> &nbsp;·&nbsp; Verknüpfter Eintrag: <?= e($linkedEntry['title']) ?><?php endif; ?>
  </div>

  <?php if ($report['d0_symptom'] || $report['d0_emergency_response']): ?>
  <div class="section">
    <div class="section-title">D0 — Sofortreaktion</div>
    <?php if ($report['d0_symptom']): ?>
    <div style="font-weight:700;font-size:9.5pt;margin-bottom:4px">Symptom / erste Beobachtung</div>
    <div class="box"><?= e($report['d0_symptom']) ?></div>
    <?php endif; ?>
    <?php if ($report['d0_emergency_response']): ?>
    <div style="font-weight:700;font-size:9.5pt;margin:8px 0 4px">Sofortreaktion zum Kundenschutz</div>
    <div class="box"><?= e($report['d0_emergency_response']) ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="section">
    <div class="section-title">D1 — Team</div>
    <?php if ($report['d1_champion']): ?><div class="box">Champion: <?= e($report['d1_champion']) ?></div><?php endif; ?>
    <?php if ($team): ?>
    <table>
      <thead><tr><th>Name</th><th>Rolle</th><th>Abteilung</th></tr></thead>
      <tbody>
      <?php foreach ($team as $m): ?>
      <tr><td><?= e($m['name']) ?></td><td><?= e($m['role'] ?? '') ?></td><td><?= e($m['department'] ?? '') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="section">
    <div class="section-title">D2 — Problembeschreibung</div>
    <div class="box"><?= e($report['d2_problem_description'] ?: '—') ?></div>
    <table>
      <thead><tr><th style="width:15%"></th><th>Ist</th><th>Ist nicht</th></tr></thead>
      <tbody>
      <?php foreach ($isIsNotFields as $key => $label): ?>
      <tr>
        <th><?= $label ?></th>
        <td><?= e($isIsNot[$key . '_is'] ?? '') ?></td>
        <td><?= e($isIsNot[$key . '_isnot'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?= eightd_embed_attachments($attachmentsByDiscipline['d2']) ?>
  </div>

  <div class="section page-break">
    <div class="section-title">D3 — Sofortmaßnahmen (Containment)</div>
    <?php if (empty($actionsByDiscipline['d3'])): ?><p style="color:#888;font-size:9pt">Keine Maßnahmen erfasst.</p><?php else: ?>
    <table>
      <thead><tr><th>Maßnahme</th><th>Verantwortlich</th><th>Fällig</th><th>Status</th><th>Verifizierung</th></tr></thead>
      <tbody>
      <?php foreach ($actionsByDiscipline['d3'] as $a): ?>
      <tr>
        <td><?= nl2br(e($a['description'])) ?></td>
        <td><?= e($a['responsible_user_name'] ?? $a['responsible'] ?? '') ?></td>
        <td><?= $a['due_date'] ? date('d.m.Y', strtotime($a['due_date'])) : '' ?></td>
        <td><span class="badge-status"><?= $statusLabels[$a['status']] ?></span></td>
        <td><?= e($a['verification'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <?= eightd_embed_attachments($attachmentsByDiscipline['d3']) ?>
  </div>

  <div class="section page-break">
    <div class="section-title">D4 — Ursachenanalyse</div>

    <div style="font-weight:700;font-size:9.5pt;margin-bottom:6px">5-Why Analyse</div>
    <?php if (empty($fiveWhy)): ?><p style="color:#888;font-size:9pt">Keine 5-Why-Analyse erfasst.</p><?php else: ?>
    <?php foreach ($fiveWhy as $chain): ?>
    <div class="fw-chain">
      <div style="font-weight:700;font-size:9pt">Problem: <?= e($chain['problem'] ?? '') ?></div>
      <?php foreach (($chain['whys'] ?? []) as $i => $w): if (trim((string)$w) === '') continue; ?>
      <div class="why">Warum <?= $i + 1 ?>? <?= e($w) ?></div>
      <?php endforeach; ?>
      <?php if (!empty($chain['root_cause'])): ?>
      <div class="why" style="font-weight:700;margin-top:4px">→ Grundursache: <?= e($chain['root_cause']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div style="font-weight:700;font-size:9.5pt;margin:12px 0 6px">Ishikawa-Diagramm (Ursache-Wirkung)</div>
    <div class="ik-grid">
      <?php foreach ($sixM as $cat): $causes = $ishikawa[$cat] ?? []; ?>
      <div class="ik-cat">
        <div class="cat-name"><?= $cat ?></div>
        <?php if ($causes): ?>
        <ul><?php foreach ($causes as $c): if (trim((string)$c) === '') continue; ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
        <?php else: ?>
        <div style="color:#aaa;font-size:8.5pt">—</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="font-weight:700;font-size:9.5pt;margin:12px 0 4px">Grundursache</div>
    <div class="box"><?= e($report['d4_root_cause'] ?: '—') ?></div>
    <div style="font-weight:700;font-size:9.5pt;margin:8px 0 4px">Escape Point</div>
    <div class="box"><?= e($report['d4_escape_point'] ?: '—') ?></div>
    <?= eightd_embed_attachments($attachmentsByDiscipline['d4']) ?>
  </div>

  <?php foreach (['d5', 'd6', 'd7'] as $disc): ?>
  <div class="section">
    <div class="section-title"><?= $disciplineLabels[$disc] ?></div>
    <?php if ($disc === 'd5' && $report['d5_selected_solution']): ?>
    <div class="box"><?= e($report['d5_selected_solution']) ?></div>
    <?php elseif ($disc === 'd6' && $report['d6_validation']): ?>
    <div class="box"><?= e($report['d6_validation']) ?></div>
    <?php elseif ($disc === 'd7' && $report['d7_systemic_actions']): ?>
    <div class="box"><?= e($report['d7_systemic_actions']) ?></div>
    <?php endif; ?>
    <?php if (empty($actionsByDiscipline[$disc])): ?><p style="color:#888;font-size:9pt">Keine Maßnahmen erfasst.</p><?php else: ?>
    <table>
      <thead><tr><th>Maßnahme</th><th>Verantwortlich</th><th>Fällig</th><th>Status</th><th>Verifizierung</th></tr></thead>
      <tbody>
      <?php foreach ($actionsByDiscipline[$disc] as $a): ?>
      <tr>
        <td><?= nl2br(e($a['description'])) ?></td>
        <td><?= e($a['responsible_user_name'] ?? $a['responsible'] ?? '') ?></td>
        <td><?= $a['due_date'] ? date('d.m.Y', strtotime($a['due_date'])) : '' ?></td>
        <td><span class="badge-status"><?= $statusLabels[$a['status']] ?></span></td>
        <td><?= e($a['verification'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <?php if ($disc === 'd6'): ?><?= eightd_embed_attachments($attachmentsByDiscipline['d6']) ?><?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div class="section">
    <div class="section-title">D8 — Abschluss</div>
    <div class="box"><?= e($report['d8_team_recognition'] ?: '—') ?></div>
  </div>
</div>
