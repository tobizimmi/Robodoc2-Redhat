<?php
$projects   = $projects   ?? [];
$categories = $categories ?? [];
$items      = $items      ?? [];

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
  <form method="GET" class="d-flex gap-2">
    <select name="project_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
      <option value="">— Projekt wählen —</option>
      <?php foreach ($projects as $p): ?>
      <option value="<?= $p['id'] ?>" <?= $projectId == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
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
          ?>
          <tr>
            <td>
              <a href="<?= url('test-plans/' . $it['test_plan_id'] . '/items/' . $it['id'] . '/edit') ?>" class="text-white text-decoration-none">
                <?= e($it['title']) ?>
              </a>
            </td>
            <td class="text-muted small">
              <a href="<?= url('test-plans/' . $it['test_plan_id']) ?>" class="text-muted"><?= e($it['plan_name']) ?></a>
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
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<style>
.card-header[data-bs-toggle="collapse"][aria-expanded="false"] .collapse-caret,
.card-header[data-bs-toggle="collapse"].collapsed .collapse-caret { transform: rotate(-90deg); }
.collapse-caret { transition: transform .15s ease; display: inline-block; }
</style>
