<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard2-data me-2"></i>Test Results</h5>
  <span class="text-muted small"><?= count($entries) ?> results</span>
</div>

<!-- Filters -->
<form method="GET" class="card mb-4">
  <div class="card-body p-3">
    <div class="row g-2">
      <!-- Source -->
      <div class="col-md-2">
        <select name="source" class="form-select form-select-sm">
          <option value="" <?= !$source?'selected':'' ?>>All Sources</option>
          <option value="run"     <?= $source==='run'?'selected':'' ?>>Test Runs</option>
          <option value="session" <?= $source==='session'?'selected':'' ?>>Test Sessions</option>
        </select>
      </div>
      <!-- Mower -->
      <div class="col-md-2">
        <select name="mower_id" class="form-select form-select-sm">
          <option value="">All Mowers</option>
          <?php foreach ($allMowers as $m): ?>
          <option value="<?= $m['id'] ?>" <?= $mowerId==$m['id']?'selected':'' ?>><?= e($m['label']) ?><?= $m['serial_number'] ? ' (' . e($m['serial_number']) . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Test Run -->
      <div class="col-md-2">
        <select name="run_id" class="form-select form-select-sm">
          <option value="">All Runs</option>
          <?php foreach ($allRuns as $r): ?>
          <option value="<?= $r['id'] ?>" <?= $runId==$r['id']?'selected':'' ?>><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Session -->
      <div class="col-md-2">
        <select name="session_id" class="form-select form-select-sm">
          <option value="">All Sessions</option>
          <?php foreach ($allSessions as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $sessionId==$s['id']?'selected':'' ?>><?= e($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Date -->
      <div class="col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>" placeholder="From">
      </div>
      <div class="col-md-1">
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>" placeholder="To">
      </div>
      <div class="col-md-1">
        <button class="btn btn-outline-primary btn-sm w-100">Filter</button>
      </div>
    </div>
    <?php if ($mowerId || $runId || $sessionId || $dateFrom || $dateTo || $source): ?>
    <div class="mt-2"><a href="<?= url('test-results') ?>" class="text-danger small">✕ Reset</a></div>
    <?php endif; ?>
  </div>
</form>

<?php if (!$entries): ?>
<div class="card"><div class="card-body text-center text-muted p-5">No test results found.</div></div>
<?php else: ?>

<!-- Group by date -->
<?php
$grouped = [];
foreach ($entries as $e) {
    $grouped[$e['entry_date']][] = $e;
}
?>

<?php foreach ($grouped as $date => $dayEntries): ?>
<div class="mb-4">
  <div class="d-flex align-items-center gap-2 mb-2">
    <span class="fw-semibold small text-muted"><?= formatDate($date) ?></span>
    <span class="badge bg-secondary"><?= count($dayEntries) ?></span>
  </div>
  <div class="card">
    <div class="list-group list-group-flush">
      <?php foreach ($dayEntries as $e): ?>
      <div class="list-group-item bg-transparent border-secondary py-2 px-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
              <!-- Type badge -->
              <span class="badge" style="background:<?= e($e['type_color'] ?? '#6366f1') ?>"><?= e($e['type_name'] ?? 'Entry') ?></span>
              <!-- Source badge -->
              <?php if ($e['is_test_entry'] && $e['run_id']): ?>
              <a href="<?= url('test-runs/' . $e['run_id']) ?>" class="badge bg-primary text-decoration-none"><?= e($e['run_name']) ?></a>
              <?php elseif ($e['session_id']): ?>
              <a href="<?= url('test-sessions/' . $e['session_id']) ?>" class="badge bg-secondary text-decoration-none"><?= e($e['session_title']) ?></a>
              <?php endif; ?>
              <!-- Category -->
              <?php if ($e['cat_name']): ?>
              <span class="badge bg-secondary"><?= e($e['cat_name']) ?></span>
              <?php endif; ?>
              <!-- Time -->
              <span class="text-muted small"><?= substr($e['entry_time'] ?? '', 0, 5) ?></span>
            </div>

            <!-- Title / description -->
            <a href="<?= url('entries/' . $e['id']) ?>" class="fw-semibold small text-decoration-none d-block">
              <?= e($e['title'] ?: substr($e['description'], 0, 80)) ?>
            </a>

            <!-- Test case context -->
            <?php if ($e['item_title']): ?>
            <div class="text-muted small mt-1"><i class="bi bi-check2-square me-1"></i><?= e($e['item_title']) ?></div>
            <?php endif; ?>

            <!-- Meta row -->
            <div class="d-flex flex-wrap gap-3 mt-1 small text-muted">
              <?php if ($e['project_name']): ?>
              <span><i class="bi bi-folder2 me-1"></i><?= e($e['project_name']) ?></span>
              <?php endif; ?>
              <?php if ($e['area_name']): ?>
              <span><i class="bi bi-map me-1"></i><?= e($e['area_name']) ?></span>
              <?php endif; ?>
              <?php if ($e['environment_name']): ?>
              <span><i class="bi bi-laptop me-1"></i><?= e($e['environment_name']) ?></span>
              <?php endif; ?>
              <?php if ($e['firmware_version']): ?>
              <span><i class="bi bi-cpu me-1"></i><?= e($e['firmware_version']) ?></span>
              <?php endif; ?>
              <?php if ($e['temperature'] !== null): ?>
              <span><i class="bi bi-thermometer me-1"></i><?= $e['temperature'] ?>°C</span>
              <?php endif; ?>
              <?php if ($e['weather_condition']): ?>
              <span><i class="bi bi-cloud me-1"></i><?= e($e['weather_condition']) ?></span>
              <?php endif; ?>
              <?php if ($e['creator']): ?>
              <span><i class="bi bi-person me-1"></i><?= e($e['creator']) ?></span>
              <?php endif; ?>
              <?php if ($e['att_count']): ?>
              <span><i class="bi bi-paperclip me-1"></i><?= $e['att_count'] ?></span>
              <?php endif; ?>
            </div>

            <!-- Mowers -->
            <?php if ($e['mowers']): ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
              <?php foreach ($e['mowers'] as $m): ?>
              <a href="<?= url('test-results?mower_id=' . $m['mower_id']) ?>" class="badge bg-dark text-decoration-none">
                <i class="bi bi-robot me-1"></i><?= e($m['label']) ?>
                <?php if ($m['serial_number']): ?><span class="opacity-75"><?= e($m['serial_number']) ?></span><?php endif; ?>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
