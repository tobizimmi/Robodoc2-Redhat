<?php
$projects      = $projects      ?? [];
$categories    = $categories    ?? [];
$items         = $items         ?? [];
$detailsByItem = $detailsByItem ?? [];
$jiraBase      = rtrim(appSetting('jira_url') ?: '', '/');

$statusColors = ['new'=>'info','pending'=>'secondary','passed'=>'success','failed'=>'danger','skipped'=>'secondary','blocked'=>'warning'];
$statusLabels = ['new'=>'Neu','pending'=>'Pending','passed'=>'Passed','failed'=>'Failed','skipped'=>'Skipped','blocked'=>'Blocked'];

// Group items by category - items with no (or an unknown/deleted) category
// land in a trailing "Ohne Kategorie" bucket.
$grouped = [];
foreach ($categories as $cat) { $grouped[$cat['id']] = ['cat' => $cat, 'items' => []]; }
$uncategorized = [];
foreach ($items as $it) {
    $cid = $it['category_id'] ?? null;
    if ($cid && isset($grouped[$cid])) { $grouped[$cid]['items'][] = $it; }
    else { $uncategorized[] = $it; }
}
if ($uncategorized) { $grouped['none'] = ['cat' => ['id' => 'none', 'name' => 'Ohne Kategorie'], 'items' => $uncategorized]; }
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>Test Übersicht</h5>
    <small class="text-muted">Alle Test Cases und ihr Status über alle Test Runs hinweg, pro Projekt</small>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <?php if ($projectId && $items): ?>
    <a href="<?= url('test-overview/export.csv?project_id=' . $projectId) ?>" class="btn btn-outline-success btn-sm">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel
    </a>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="rdPrintOverview()">
      <i class="bi bi-file-earmark-pdf me-1"></i>PDF
    </button>
    <?php endif; ?>
    <form method="GET" class="d-flex gap-2">
      <select name="project_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
        <option value="">— Projekt wählen —</option>
        <?php foreach ($projects as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $projectId == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<?php if (!$projectId): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-folder2-open fs-1 d-block mb-2 opacity-50"></i>
  <div>Bitte ein Projekt auswählen.</div>
</div></div>
<?php elseif (!$items): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
  <div>Keine Test Cases in diesem Projekt.</div>
</div></div>
<?php else: ?>

<div id="rdOverviewPrint">
<?php foreach ($grouped as $gi => $group):
  $cat   = $group['cat'];
  $gItems = $group['items'];
  if (!$gItems) continue;
  $gPassed = count(array_filter($gItems, fn($i) => $i['latest_status'] === 'passed'));
  $gFailed = count(array_filter($gItems, fn($i) => $i['latest_status'] === 'failed'));
  $collapseId = 'cat-' . preg_replace('/[^a-z0-9]/i', '', (string)$gi);
?>
<div class="card mb-3">
  <div class="card-header border-secondary d-flex align-items-center gap-2" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
    <i class="bi bi-chevron-down small text-muted collapse-caret"></i>
    <i class="bi bi-tag text-muted"></i>
    <span class="fw-semibold"><?= e($cat['name']) ?></span>
    <span class="badge bg-secondary"><?= count($gItems) ?></span>
    <span class="ms-2 small text-muted">
      <?php if ($gPassed): ?><span class="text-success"><?= $gPassed ?> bestanden</span><?php endif; ?>
      <?php if ($gPassed && $gFailed): ?> · <?php endif; ?>
      <?php if ($gFailed): ?><span class="text-danger"><?= $gFailed ?> fehlgeschlagen</span><?php endif; ?>
    </span>
  </div>
  <div class="collapse show" id="<?= $collapseId ?>">
    <div class="table-responsive">
      <table class="table table-dark table-hover mb-0" style="font-size:.85rem">
        <thead class="text-muted" style="font-size:.72rem">
          <tr>
            <th style="width:24px"></th>
            <th>Test Case</th>
            <th>Plan</th>
            <th>Priorität</th>
            <th>Letzter Status</th>
            <th>Historie</th>
            <th>Zuletzt ausgeführt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($gItems as $it):
            $pc = match($it['priority']) { 'critical'=>'danger','high'=>'warning','low'=>'secondary',default=>'info' };
            $ls = $it['latest_status'];
            $rows = $detailsByItem[$it['id']] ?? [];
            $rowId = 'row-' . $it['id'];
          ?>
          <tr style="cursor:pointer" onclick="rdToggleRow(<?= $it['id'] ?>)">
            <td class="text-muted"><i class="bi bi-chevron-right small rd-row-caret" id="caret-<?= $it['id'] ?>"></i></td>
            <td>
              <a href="<?= url('test-plans/' . $it['test_plan_id'] . '/items/' . $it['id'] . '/edit') ?>" class="text-white text-decoration-none" onclick="event.stopPropagation()">
                <?= e($it['title']) ?>
              </a>
            </td>
            <td class="text-muted small">
              <a href="<?= url('test-plans/' . $it['test_plan_id']) ?>" class="text-muted" onclick="event.stopPropagation()"><?= e($it['plan_name']) ?></a>
            </td>
            <td><span class="badge bg-<?= $pc ?>" style="font-size:.65rem"><?= e($it['priority']) ?></span></td>
            <td><span class="badge bg-<?= $statusColors[$ls] ?? 'secondary' ?>"><?= e($statusLabels[$ls] ?? $ls) ?></span></td>
            <td class="small">
              <?php foreach (['passed'=>'c_passed','failed'=>'c_failed','pending'=>'c_pending','new'=>'c_new','skipped'=>'c_skipped','blocked'=>'c_blocked'] as $st => $col): ?>
              <?php if ((int)($it[$col] ?? 0) > 0): ?>
              <span class="text-<?= $statusColors[$st] === 'secondary' ? 'muted' : $statusColors[$st] ?> me-2"><?= (int)$it[$col] ?>&times; <?= $statusLabels[$st] ?></span>
              <?php endif; ?>
              <?php endforeach; ?>
              <?php if (!(int)($it['total_runs'] ?? 0)): ?><span class="text-muted">— noch nie ausgeführt</span><?php endif; ?>
            </td>
            <td class="text-muted small"><?= $it['last_executed_at'] ? formatDate($it['last_executed_at'], 'd.m.Y H:i') : '—' ?></td>
          </tr>
          <tr class="rd-detail-row d-none" id="<?= $rowId ?>">
            <td colspan="7" class="bg-black bg-opacity-25 p-3">
              <?php if (!$rows): ?>
              <div class="text-muted small">Noch nie in einem Test Run ausgeführt.</div>
              <?php else: ?>
              <?php if ($it['description']): ?>
              <div class="small text-muted mb-2"><strong>Beschreibung:</strong> <?= e($it['description']) ?></div>
              <?php endif; ?>
              <?php if ($it['expected_result']): ?>
              <div class="small text-muted mb-2"><strong>Erwartetes Ergebnis:</strong> <?= e($it['expected_result']) ?></div>
              <?php endif; ?>
              <table class="table table-dark table-sm mb-0" style="font-size:.78rem">
                <thead class="text-muted" style="font-size:.68rem">
                  <tr>
                    <th>Test Run</th><th>Status</th><th>PQL</th><th>Tester</th><th>Ausgeführt am</th><th>Notizen</th><th>Bericht</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $dr): ?>
                  <tr>
                    <td><a href="<?= url('test-runs/' . $dr['run_id']) ?>" class="text-white text-decoration-none"><?= e($dr['run_name'] ?: ('Run #' . $dr['run_id'])) ?></a></td>
                    <td><span class="badge bg-<?= $statusColors[$dr['status']] ?? 'secondary' ?>"><?= e($statusLabels[$dr['status']] ?? $dr['status']) ?></span></td>
                    <td>
                      <?php if (!empty($dr['pql_issue_key'])): ?>
                      <?php if ($jiraBase): ?>
                      <a href="<?= e($jiraBase . '/browse/' . $dr['pql_issue_key']) ?>" target="_blank" rel="noopener" class="text-info text-decoration-none"><?= e($dr['pql_issue_key']) ?></a>
                      <?php else: ?><?= e($dr['pql_issue_key']) ?><?php endif; ?>
                      <?php if (!empty($dr['pql_status'])): ?> <span class="badge bg-info text-dark"><?= e($dr['pql_status']) ?></span><?php endif; ?>
                      <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-muted"><?= e($dr['tester_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= $dr['executed_at'] ? formatDate($dr['executed_at'], 'd.m.Y H:i') : '—' ?></td>
                    <td class="text-muted"><?= e($dr['notes'] ?: '—') ?></td>
                    <td>
                      <?php if (!empty($dr['report_file_path'])): ?>
                      <a href="<?= url('test-runs/' . $dr['test_run_id'] . '/results/' . $dr['id'] . '/report') ?>" class="text-decoration-none"><i class="bi bi-paperclip"></i></a>
                      <?php endif; ?>
                      <?php if (!empty($dr['report_link'])): ?>
                      <a href="<?= e($dr['report_link']) ?>" target="_blank" rel="noopener" class="text-decoration-none ms-1"><i class="bi bi-box-arrow-up-right"></i></a>
                      <?php endif; ?>
                      <?php if (empty($dr['report_file_path']) && empty($dr['report_link'])): ?>—<?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.card-header[data-bs-toggle="collapse"][aria-expanded="false"] .collapse-caret,
.card-header[data-bs-toggle="collapse"].collapsed .collapse-caret { transform: rotate(-90deg); }
.collapse-caret { transition: transform .15s ease; display: inline-block; }
.rd-row-caret.open { transform: rotate(90deg); }
.rd-row-caret { transition: transform .15s ease; display: inline-block; }
@media print {
  .navbar, .sidebar, nav, form, .btn { display: none !important; }
  .collapse { display: block !important; height: auto !important; }
  .rd-detail-row { display: table-row !important; }
}
</style>

<script>
function rdToggleRow(id) {
  const row = document.getElementById('row-' + id);
  const caret = document.getElementById('caret-' + id);
  if (!row) return;
  row.classList.toggle('d-none');
  if (caret) caret.classList.toggle('open');
}
function rdPrintOverview() {
  // Expand every detail row so the printed/PDF'd version shows everything,
  // not just whatever happened to be open on screen.
  document.querySelectorAll('.rd-detail-row').forEach(r => r.classList.remove('d-none'));
  window.print();
}
</script>
