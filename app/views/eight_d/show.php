<?php
$activeTab = $_GET['tab'] ?? 'd0';
$csrf = Auth::csrfToken();
$sixM = ['Mensch', 'Maschine', 'Methode', 'Material', 'Mitwelt', 'Messung'];
foreach ($sixM as $cat) { if (!isset($ishikawa[$cat])) $ishikawa[$cat] = []; }

$isIsNotFields = [
    'what'   => 'Was',
    'where'  => 'Wo',
    'when'   => 'Wann',
    'extent' => 'Umfang',
];

function eightd_attachment_gallery(array $attachments, string $discipline, int $reportId, bool $canEdit, string $csrf): void {
    ?>
    <div class="section-title mb-2 fw-semibold"><i class="bi bi-paperclip me-1"></i>Anhänge / Fotos</div>
    <?php if (empty($attachments)): ?>
    <p class="text-muted small">Noch keine Anhänge.</p>
    <?php else: ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php foreach ($attachments as $att): $isImg = str_starts_with($att['mime_type'] ?? '', 'image/'); ?>
      <div class="card border-secondary" style="width:140px">
        <a href="<?= url('8d/attachment/' . $att['id']) ?>" target="_blank">
          <?php if ($isImg): ?>
          <img src="<?= url('8d/attachment/' . $att['id']) ?>" class="card-img-top" style="height:100px;object-fit:cover" alt="<?= e($att['original_name']) ?>">
          <?php else: ?>
          <div class="d-flex align-items-center justify-content-center bg-secondary bg-opacity-25" style="height:100px">
            <i class="bi bi-file-earmark-text" style="font-size:2rem"></i>
          </div>
          <?php endif; ?>
        </a>
        <div class="card-body p-2">
          <div class="text-truncate small" title="<?= e($att['original_name']) ?>"><?= e($att['original_name']) ?></div>
          <?php if ($canEdit): ?>
          <form method="POST" action="<?= url('8d/' . $reportId . '/attachment/' . $att['id'] . '/delete') ?>" onsubmit="return confirm('Anhang löschen?')">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-sm btn-outline-danger w-100 mt-1"><i class="bi bi-trash"></i></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <form method="POST" action="<?= url('8d/' . $reportId . '/attachment') ?>" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
      <?= csrfField() ?>
      <input type="hidden" name="discipline" value="<?= $discipline ?>">
      <input type="file" name="file" class="form-control form-control-sm" accept="image/*,.pdf,.txt,.csv" required style="max-width:320px">
      <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-upload me-1"></i>Hochladen</button>
    </form>
    <div class="form-text">Bilder (JPG, PNG, GIF, WebP, HEIC), PDF, TXT oder CSV.</div>
    <?php endif; ?>
    <?php
}

function eightd_action_table(array $actions, string $discipline, int $reportId, bool $canEdit, string $csrf, array $users = []): void {
    $statusLabels = ['open' => 'Offen', 'in_progress' => 'In Arbeit', 'done' => 'Erledigt', 'verified' => 'Verifiziert'];
    $statusColors = ['open' => 'secondary', 'in_progress' => 'warning', 'done' => 'success', 'verified' => 'success'];
    ?>
    <?php if (empty($actions)): ?>
    <p class="text-muted small">Noch keine Maßnahmen erfasst.</p>
    <?php else: ?>
    <?php if ($canEdit): ?>
    <?php /* One empty <form> per row, referenced via the HTML5 form="" attribute
              on each field below — a <form> can't legally be a direct child of
              <tr> (sibling to <td>), browsers foster-parent it out of the table
              and the fields silently stop submitting with it. */ ?>
    <?php foreach ($actions as $a): ?>
    <form id="af<?= $a['id'] ?>" method="POST" action="<?= url('8d/' . $reportId . '/action/' . $a['id']) ?>">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    </form>
    <?php endforeach; ?>
    <?php endif; ?>
    <div class="table-responsive mb-3">
      <table class="table table-sm table-dark align-middle">
        <thead><tr>
          <th>Maßnahme</th><th>Verantwortlich</th><th>Fällig</th><th>Status</th><th>Verifizierung</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($actions as $a): $fid = 'af' . $a['id']; ?>
        <tr>
          <td style="min-width:200px">
            <?= $canEdit
                ? '<textarea form="' . $fid . '" name="description" class="form-control form-control-sm bg-dark text-white" rows="1">' . e($a['description']) . '</textarea>'
                : nl2br(e($a['description'])) ?>
          </td>
          <td style="min-width:160px">
            <?php if ($canEdit): ?>
            <select form="<?= $fid ?>" name="responsible_user_id" class="form-select form-select-sm bg-dark text-white mb-1">
              <option value="">— kein Nutzer —</option>
              <?php foreach ($users as $u): ?>
              <option value="<?= $u['id'] ?>" <?= (int)($a['responsible_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <input form="<?= $fid ?>" type="text" name="responsible" class="form-control form-control-sm bg-dark text-white" placeholder="oder Freitext..." value="<?= e($a['responsible'] ?? '') ?>">
            <?php else: ?>
            <?= e($a['responsible_user_name'] ?? $a['responsible'] ?? '-') ?>
            <?php endif; ?>
          </td>
          <td style="min-width:130px">
            <?= $canEdit
                ? '<input form="' . $fid . '" type="date" name="due_date" class="form-control form-control-sm bg-dark text-white" value="' . e($a['due_date'] ?? '') . '">'
                : e($a['due_date'] ? date('d.m.Y', strtotime($a['due_date'])) : '-') ?>
          </td>
          <td style="min-width:130px">
            <?php if ($canEdit): ?>
            <select form="<?= $fid ?>" name="status" class="form-select form-select-sm bg-dark text-white">
              <?php foreach ($statusLabels as $val => $label): ?>
              <option value="<?= $val ?>" <?= $a['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <?php else: ?>
            <span class="badge bg-<?= $statusColors[$a['status']] ?>"><?= $statusLabels[$a['status']] ?></span>
            <?php endif; ?>
          </td>
          <td style="min-width:160px">
            <?= $canEdit
                ? '<input form="' . $fid . '" type="text" name="verification" class="form-control form-control-sm bg-dark text-white" value="' . e($a['verification'] ?? '') . '" placeholder="Nachweis...">'
                : e($a['verification'] ?? '-') ?>
          </td>
          <td class="text-end" style="white-space:nowrap">
            <?php if ($canEdit): ?>
            <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-outline-primary" title="Speichern"><i class="bi bi-check-lg"></i></button>
            <form method="POST" action="<?= url('8d/' . $reportId . '/action/' . $a['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Maßnahme löschen?')">
              <?= csrfField() ?>
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <form method="POST" action="<?= url('8d/' . $reportId . '/action') ?>" class="row g-2 align-items-end">
      <?= csrfField() ?>
      <input type="hidden" name="discipline" value="<?= $discipline ?>">
      <div class="col-md-5">
        <input type="text" name="description" class="form-control form-control-sm" placeholder="Neue Maßnahme..." required>
      </div>
      <div class="col-md-3">
        <select name="responsible_user_id" class="form-select form-select-sm mb-1">
          <option value="">— kein Nutzer —</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="responsible" class="form-control form-control-sm" placeholder="oder Freitext">
      </div>
      <div class="col-md-2">
        <input type="date" name="due_date" class="form-control form-control-sm">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Hinzufügen</button>
      </div>
    </form>
    <?php endif; ?>
    <?php
}
?>
<div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <code class="text-info fs-6"><?= e($report['reference']) ?></code>
      <span class="badge <?= $report['status'] === 'closed' ? 'bg-success' : 'bg-warning text-dark' ?>">
        <?= $report['status'] === 'closed' ? 'Abgeschlossen' : 'Offen' ?>
      </span>
    </div>
    <h5 class="mb-0"><?= e($report['title']) ?></h5>
    <?php if ($linkedEntry): ?>
    <div class="small text-muted mt-1">
      <i class="bi bi-link-45deg me-1"></i>Verknüpfter Eintrag:
      <a href="<?= url('entries/' . $linkedEntry['id']) ?>"><?= e($linkedEntry['title']) ?></a>
    </div>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('8d') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Liste</a>
    <a href="<?= url('8d/' . $report['id'] . '/export') ?>" target="_blank" class="btn btn-outline-info btn-sm">
      <i class="bi bi-file-earmark-arrow-down me-1"></i>Exportieren
    </a>
    <?php if ($canEdit): ?>
      <?php if ($report['status'] === 'open'): ?>
      <form method="POST" action="<?= url('8d/' . $report['id'] . '/close') ?>" onsubmit="return confirm('8D-Bericht als abgeschlossen markieren?')">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Abschließen</button>
      </form>
      <?php else: ?>
      <form method="POST" action="<?= url('8d/' . $report['id'] . '/reopen') ?>">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Wieder öffnen</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<ul class="nav nav-tabs border-secondary mb-3 flex-nowrap overflow-auto" id="d8dTabs">
  <?php
  $tabs = [
      'd0' => 'D0 Sofortreaktion', 'd1' => 'D1 Team', 'd2' => 'D2 Problem', 'd3' => 'D3 Sofortmaßnahmen',
      'd4' => 'D4 Ursachenanalyse', 'd5' => 'D5 Korrekturmaßnahmen', 'd6' => 'D6 Umsetzung',
      'd7' => 'D7 Vorbeugung', 'd8' => 'D8 Abschluss',
  ];
  foreach ($tabs as $key => $label):
  ?>
  <li class="nav-item">
    <a class="nav-link text-nowrap d8d-tab-link <?= $activeTab === $key ? 'active' : 'text-muted' ?>"
       href="#" data-tab="<?= $key ?>" onclick="d8dShowTab('<?= $key ?>');return false;"><?= $label ?></a>
  </li>
  <?php endforeach; ?>
</ul>

<?php /* d8dForm is a standalone, empty <form> — every field it saves carries
          a form="d8dForm" attribute instead of living inside the tag itself.
          That's required here: the D1/D3/D5/D6/D7 tabs below also contain
          their own small forms (team members, actions), and <form> elements
          cannot legally nest — a literal wrapping <form> would make the
          browser silently drop those inner ones during parsing. */ ?>
<form method="POST" action="<?= url('8d/' . $report['id'] . '/update') ?>" id="d8dForm">
  <?= csrfField() ?>
  <input type="hidden" name="tab" value="<?= e($activeTab) ?>" id="d8dActiveTabInput">
  <input type="hidden" name="five_why_json" id="fiveWhyJson">
  <input type="hidden" name="ishikawa_json" id="ishikawaJson">
</form>

<div>
  <?php /* ── D0: Sofortreaktion ───────────────────────────────────── */ ?>
  <div class="<?= $activeTab === 'd0' ? '' : 'd-none' ?>" data-tab-pane="d0">
    <div class="card border-secondary p-3 mb-3">
      <label class="form-label small text-muted">Symptom / erste Beobachtung</label>
      <textarea form="d8dForm" name="d0_symptom" class="form-control" rows="3" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d0_symptom'] ?? '') ?></textarea>
    </div>
    <div class="card border-secondary p-3">
      <label class="form-label small text-muted">Sofortreaktion zum Kundenschutz (falls nötig)</label>
      <textarea form="d8dForm" name="d0_emergency_response" class="form-control" rows="3" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d0_emergency_response'] ?? '') ?></textarea>
    </div>
  </div>

  <?php /* ── D1: Team ─────────────────────────────────────────────── */ ?>
  <div class="<?= $activeTab === 'd1' ? '' : 'd-none' ?>" data-tab-pane="d1">
    <div class="card border-secondary p-3 mb-3">
      <label class="form-label small text-muted">Projektleiter / Champion</label>
      <input type="text" form="d8dForm" name="d1_champion" class="form-control" value="<?= e($report['d1_champion'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?> placeholder="Name">
    </div>
    <div class="card border-secondary p-3">
      <div class="section-title mb-2 fw-semibold">Team-Mitglieder</div>
      <?php if (empty($team)): ?>
      <p class="text-muted small">Noch keine Team-Mitglieder erfasst.</p>
      <?php else: ?>
      <table class="table table-sm table-dark mb-3">
        <thead><tr><th>Name</th><th>Rolle</th><th>Abteilung</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($team as $m): ?>
        <tr>
          <td><?= e($m['name']) ?></td>
          <td><?= e($m['role'] ?? '-') ?></td>
          <td><?= e($m['department'] ?? '-') ?></td>
          <td class="text-end">
            <?php if ($canEdit): ?>
            <form method="POST" action="<?= url('8d/' . $report['id'] . '/team/' . $m['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Mitglied entfernen?')">
              <?= csrfField() ?>
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
      <?php if ($canEdit): ?>
      <form method="POST" action="<?= url('8d/' . $report['id'] . '/team') ?>" class="row g-2 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="Name *" required></div>
        <div class="col-md-3"><input type="text" name="role" class="form-control form-control-sm" placeholder="Rolle"></div>
        <div class="col-md-3"><input type="text" name="department" class="form-control form-control-sm" placeholder="Abteilung"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-plus-lg"></i></button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <?php /* ── D2: Problembeschreibung ──────────────────────────────── */ ?>
  <div class="<?= $activeTab === 'd2' ? '' : 'd-none' ?>" data-tab-pane="d2">
    <div class="card border-secondary p-3 mb-3">
      <label class="form-label small text-muted">Problembeschreibung</label>
      <textarea form="d8dForm" name="d2_problem_description" class="form-control" rows="5" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d2_problem_description'] ?? '') ?></textarea>
    </div>
    <div class="card border-secondary p-3">
      <div class="section-title mb-2 fw-semibold">Is / Is Not Analyse</div>
      <div class="table-responsive">
        <table class="table table-sm table-dark align-middle mb-0">
          <thead><tr><th style="width:120px"></th><th>Ist</th><th>Ist nicht</th></tr></thead>
          <tbody>
          <?php foreach ($isIsNotFields as $key => $label): ?>
          <tr>
            <th class="text-muted"><?= $label ?></th>
            <td><input type="text" form="d8dForm" name="isisnot_<?= $key ?>_is" class="form-control form-control-sm bg-dark text-white" value="<?= e($isIsNot[$key . '_is'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
            <td><input type="text" form="d8dForm" name="isisnot_<?= $key ?>_isnot" class="form-control form-control-sm bg-dark text-white" value="<?= e($isIsNot[$key . '_isnot'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card border-secondary p-3 mt-3">
      <?php eightd_attachment_gallery($attachmentsByDiscipline['d2'], 'd2', (int)$report['id'], $canEdit, $csrf); ?>
    </div>
  </div>

  <?php /* ── D3: Sofortmaßnahmen ──────────────────────────────────── */ ?>
  <div class="<?= $activeTab === 'd3' ? '' : 'd-none' ?>" data-tab-pane="d3">
    <div class="card border-secondary p-3 mb-3">
      <div class="section-title mb-2 fw-semibold">Sofortmaßnahmen (Containment)</div>
      <?php eightd_action_table($actionsByDiscipline['d3'], 'd3', (int)$report['id'], $canEdit, $csrf, $users); ?>
    </div>
    <div class="card border-secondary p-3">
      <?php eightd_attachment_gallery($attachmentsByDiscipline['d3'], 'd3', (int)$report['id'], $canEdit, $csrf); ?>
    </div>
  </div>

  <?php /* ── D4: Ursachenanalyse (5-Why + Ishikawa) ───────────────── */ ?>
  <div class="<?= $activeTab === 'd4' ? '' : 'd-none' ?>" data-tab-pane="d4">
    <div class="card border-secondary p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="section-title fw-semibold">5-Why Analyse</div>
        <?php if ($canEdit): ?>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="fwAddChain()"><i class="bi bi-plus-lg me-1"></i>Kette hinzufügen</button>
        <?php endif; ?>
      </div>
      <div id="fiveWhyList"></div>
    </div>

    <div class="card border-secondary p-3 mb-3">
      <div class="section-title fw-semibold mb-2">Ishikawa-Diagramm (Ursache-Wirkungs-Analyse)</div>
      <div class="row g-3" id="ishikawaGrid"></div>
    </div>

    <div class="card border-secondary p-3 mb-3">
      <label class="form-label small text-muted">Identifizierte Grundursache</label>
      <textarea form="d8dForm" name="d4_root_cause" class="form-control mb-3" rows="3" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d4_root_cause'] ?? '') ?></textarea>
      <label class="form-label small text-muted">Warum wurde das Problem nicht früher erkannt? (Escape Point)</label>
      <textarea form="d8dForm" name="d4_escape_point" class="form-control" rows="2" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d4_escape_point'] ?? '') ?></textarea>
    </div>
    <div class="card border-secondary p-3">
      <?php eightd_attachment_gallery($attachmentsByDiscipline['d4'], 'd4', (int)$report['id'], $canEdit, $csrf); ?>
    </div>
  </div>

  <?php /* ── D5: Korrekturmaßnahmen ───────────────────────────────── */ ?>
  <div class="<?= $activeTab === 'd5' ? '' : 'd-none' ?>" data-tab-pane="d5">
    <div class="card border-secondary p-3 mb-3">
      <label class="form-label small text-muted">Ausgewählte dauerhafte Korrekturmaßnahme</label>
      <textarea form="d8dForm" name="d5_selected_solution" class="form-control" rows="3" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d5_selected_solution'] ?? '') ?></textarea>
    </div>
    <div class="card border-secondary p-3">
      <div class="section-title mb-2 fw-semibold">Maßnahmen</div>
      <?php eightd_action_table($actionsByDiscipline['d5'], 'd5', (int)$report['id'], $canEdit, $csrf, $users); ?>
    </div>
  </div>

  <?php /* ── D6: Umsetzung & Validierung ──────────────────────────── */ ?>
  <div class="<?= $activeTab === 'd6' ? '' : 'd-none' ?>" data-tab-pane="d6">
    <div class="card border-secondary p-3 mb-3">
      <label class="form-label small text-muted">Validierung der Wirksamkeit</label>
      <textarea form="d8dForm" name="d6_validation" class="form-control" rows="3" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d6_validation'] ?? '') ?></textarea>
    </div>
    <div class="card border-secondary p-3 mb-3">
      <div class="section-title mb-2 fw-semibold">Umsetzungsmaßnahmen</div>
      <?php eightd_action_table($actionsByDiscipline['d6'], 'd6', (int)$report['id'], $canEdit, $csrf, $users); ?>
    </div>
    <div class="card border-secondary p-3">
      <?php eightd_attachment_gallery($attachmentsByDiscipline['d6'], 'd6', (int)$report['id'], $canEdit, $csrf); ?>
    </div>
  </div>

  <?php /* ── D7: Vorbeugung ────────────────────────────────────────── */ ?>
  <div class="<?= $activeTab === 'd7' ? '' : 'd-none' ?>" data-tab-pane="d7">
    <div class="card border-secondary p-3 mb-3">
      <label class="form-label small text-muted">Systemische Maßnahmen zur Vermeidung ähnlicher Probleme</label>
      <textarea form="d8dForm" name="d7_systemic_actions" class="form-control" rows="3" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d7_systemic_actions'] ?? '') ?></textarea>
    </div>
    <div class="card border-secondary p-3">
      <div class="section-title mb-2 fw-semibold">Vorbeugende Maßnahmen</div>
      <?php eightd_action_table($actionsByDiscipline['d7'], 'd7', (int)$report['id'], $canEdit, $csrf, $users); ?>
    </div>
  </div>

  <?php /* ── D8: Abschluss ─────────────────────────────────────────── */ ?>
  <div class="<?= $activeTab === 'd8' ? '' : 'd-none' ?>" data-tab-pane="d8">
    <div class="card border-secondary p-3">
      <label class="form-label small text-muted">Würdigung des Teams / Abschlussbemerkung</label>
      <textarea form="d8dForm" name="d8_team_recognition" class="form-control" rows="4" <?= $canEdit ? '' : 'readonly' ?>><?= e($report['d8_team_recognition'] ?? '') ?></textarea>
      <?php if ($report['d8_closed_at']): ?>
      <div class="form-text mt-2">Abgeschlossen am <?= date('d.m.Y H:i', strtotime($report['d8_closed_at'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($canEdit): ?>
  <div class="mt-3">
    <button type="submit" form="d8dForm" class="btn btn-primary"><i class="bi bi-save me-1"></i>Speichern</button>
  </div>
  <?php endif; ?>
</div>

<script>
var _fw = <?= json_encode($fiveWhy, JSON_UNESCAPED_UNICODE) ?>;
var _ik = <?= json_encode($ishikawa, JSON_UNESCAPED_UNICODE) ?>;
var _canEdit = <?= $canEdit ? 'true' : 'false' ?>;

function fwRender() {
  var wrap = document.getElementById('fiveWhyList');
  wrap.innerHTML = '';
  _fw.forEach(function(chain, idx) {
    var card = document.createElement('div');
    card.className = 'card border-secondary p-3 mb-2';
    var whys = (chain.whys || []).concat(['', '', '', '', '']).slice(0, 5);
    var html = '<div class="d-flex align-items-start gap-2 mb-2">'
      + '<div class="flex-grow-1"><label class="form-label small text-muted">Problem</label>'
      + '<input type="text" class="form-control form-control-sm mb-2" data-fw="' + idx + '" data-field="problem" value="' + esc(chain.problem || '') + '"' + (_canEdit ? '' : ' readonly') + '></div>'
      + (_canEdit ? '<button type="button" class="btn btn-sm btn-outline-danger mt-4" onclick="fwRemoveChain(' + idx + ')"><i class="bi bi-trash"></i></button>' : '')
      + '</div>';
    whys.forEach(function(w, wi) {
      html += '<label class="form-label small text-muted mb-1">Warum ' + (wi + 1) + '?</label>'
        + '<input type="text" class="form-control form-control-sm mb-2" data-fw="' + idx + '" data-field="why" data-why-idx="' + wi + '" value="' + esc(w) + '"' + (_canEdit ? '' : ' readonly') + '>';
    });
    html += '<label class="form-label small text-muted mb-1">Grundursache</label>'
      + '<input type="text" class="form-control form-control-sm" data-fw="' + idx + '" data-field="root_cause" value="' + esc(chain.root_cause || '') + '"' + (_canEdit ? '' : ' readonly') + '>';
    card.innerHTML = html;
    wrap.appendChild(card);
  });
  if (!_fw.length) wrap.innerHTML = '<p class="text-muted small">Noch keine 5-Why-Kette erfasst.</p>';
  wrap.querySelectorAll('input').forEach(function(inp) {
    inp.addEventListener('input', function() {
      var i = parseInt(inp.dataset.fw, 10);
      if (inp.dataset.field === 'why') {
        _fw[i].whys = _fw[i].whys || ['', '', '', '', ''];
        _fw[i].whys[parseInt(inp.dataset.whyIdx, 10)] = inp.value;
      } else {
        _fw[i][inp.dataset.field] = inp.value;
      }
    });
  });
}
function fwAddChain() {
  _fw.push({problem: '', whys: ['', '', '', '', ''], root_cause: ''});
  fwRender();
}
function fwRemoveChain(idx) {
  _fw.splice(idx, 1);
  fwRender();
}
function esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

var IK_CATEGORIES = <?= json_encode($sixM) ?>;
function ikRender() {
  var grid = document.getElementById('ishikawaGrid');
  grid.innerHTML = '';
  IK_CATEGORIES.forEach(function(cat) {
    var causes = _ik[cat] || [];
    var col = document.createElement('div');
    col.className = 'col-md-6 col-xl-4';
    var rows = causes.map(function(c, ci) {
      return '<div class="d-flex align-items-center gap-1 mb-1">'
        + '<input type="text" class="form-control form-control-sm" data-ik-cat="' + cat + '" data-ik-idx="' + ci + '" value="' + esc(c) + '"' + (_canEdit ? '' : ' readonly') + '>'
        + (_canEdit ? '<button type="button" class="btn btn-sm btn-outline-danger" onclick="ikRemoveCause(\'' + cat + '\',' + ci + ')"><i class="bi bi-x"></i></button>' : '')
        + '</div>';
    }).join('');
    col.innerHTML = '<div class="card border-secondary h-100 p-2">'
      + '<div class="fw-semibold small mb-2"><i class="bi bi-tag me-1"></i>' + cat + '</div>'
      + '<div>' + (rows || '<p class="text-muted small mb-2">Keine Ursachen.</p>') + '</div>'
      + (_canEdit ? '<button type="button" class="btn btn-sm btn-outline-primary w-100 mt-1" onclick="ikAddCause(\'' + cat + '\')"><i class="bi bi-plus-lg"></i></button>' : '')
      + '</div>';
    grid.appendChild(col);
  });
  grid.querySelectorAll('input[data-ik-cat]').forEach(function(inp) {
    inp.addEventListener('input', function() {
      _ik[inp.dataset.ikCat][parseInt(inp.dataset.ikIdx, 10)] = inp.value;
    });
  });
}
function ikAddCause(cat) {
  _ik[cat] = _ik[cat] || [];
  _ik[cat].push('');
  ikRender();
}
function ikRemoveCause(cat, idx) {
  _ik[cat].splice(idx, 1);
  ikRender();
}

fwRender();
ikRender();

// Client-side tab switching (no page reload) so unsaved input in other tabs
// isn't lost when navigating between D1-D8 — every tab lives in the same
// <form>, only Speichern actually submits.
function d8dShowTab(tab) {
  document.querySelectorAll('[data-tab-pane]').forEach(function(pane) {
    pane.classList.toggle('d-none', pane.dataset.tabPane !== tab);
  });
  document.querySelectorAll('.d8d-tab-link').forEach(function(link) {
    var active = link.dataset.tab === tab;
    link.classList.toggle('active', active);
    link.classList.toggle('text-muted', !active);
  });
  document.getElementById('d8dActiveTabInput').value = tab;
  history.replaceState(null, '', '?tab=' + tab);
}

document.getElementById('d8dForm').addEventListener('submit', function() {
  document.getElementById('fiveWhyJson').value = JSON.stringify(_fw);
  document.getElementById('ishikawaJson').value = JSON.stringify(_ik);
});
</script>
