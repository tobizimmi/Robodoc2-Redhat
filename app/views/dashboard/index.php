<?php
$typeLabels  = array_column($byType, 'name');
$typeCounts  = array_column($byType, 'cnt');
$typeColors  = array_column($byType, 'color');
$catLabels   = array_column($byCategory, 'name');
$catCounts   = array_column($byCategory, 'cnt');
$catColors   = array_column($byCategory, 'color');
$trendLabels = array_column($trend, 'label');
$trendCounts = array_column($trend, 'count');
?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['label' => 'Today',     'value' => $stats['today'],    'icon' => 'calendar-day',   'color' => '#6366f1'],
    ['label' => 'Week',     'value' => $stats['week'],     'icon' => 'calendar-week',  'color' => '#3b82f6'],
    ['label' => '30 Days',   'value' => $stats['month'],    'icon' => 'bar-chart',      'color' => '#10b981'],
    ['label' => 'Total',    'value' => $stats['total'],    'icon' => 'journal-text',   'color' => '#f59e0b'],
    ['label' => 'Projects',  'value' => $stats['projects'], 'icon' => 'folder',         'color' => '#8b5cf6'],
    ['label' => 'Open Todos', 'value' => $stats['open_todos'], 'icon' => 'check2-square', 'color' => '#ec4899'],
  ];
  foreach ($cards as $c):
  ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100" style="border-left-color:<?= e($c['color']) ?>">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="text-muted small"><?= e($c['label']) ?></span>
          <i class="bi bi-<?= e($c['icon']) ?>" style="color:<?= e($c['color']) ?>"></i>
        </div>
        <div class="stat-number"><?= $c['value'] ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts row -->
<div class="row g-3 mb-4">
  <!-- Trend line -->
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-header border-secondary d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Entries – last 6 months</span>
      </div>
      <div class="card-body">
        <canvas id="trendChart" height="160"></canvas>
      </div>
    </div>
  </div>
  <!-- Donut by type -->
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-header border-secondary">
        <span class="fw-semibold">By Type</span>
      </div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="typeChart" style="max-height:200px"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Category bars + Recent entries -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header border-secondary"><span class="fw-semibold">By Category</span></div>
      <div class="card-body">
        <?php foreach ($byCategory as $cat): ?>
        <?php if (!$cat['cnt']) continue; ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between mb-1">
            <span style="font-size:.8rem"><?= e($cat['name']) ?></span>
            <span class="badge" style="background:<?= e($cat['color']) ?>"><?= $cat['cnt'] ?></span>
          </div>
          <div class="progress" style="height:6px">
            <div class="progress-bar" style="width:<?= max(5, round($cat['cnt'] / max(1, max($catCounts)) * 100)) ?>%;background:<?= e($cat['color']) ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!array_sum($catCounts)): ?>
        <p class="text-muted text-center small mt-3">No data</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Recent Entries</span>
        <a href="<?= url('entries') ?>" class="btn btn-outline-secondary btn-sm">All</a>
      </div>
      <div class="card-body p-0">
        <div class="list-group list-group-flush">
          <?php foreach ($recentEntries as $e): ?>
          <a href="<?= url('entries/' . $e['id']) ?>" class="list-group-item list-group-item-action bg-transparent border-secondary entry-row py-2 px-3">
            <div class="d-flex align-items-start gap-2">
              <span class="badge mt-1 flex-shrink-0" style="background:<?= htmlspecialchars($e['type_color']) ?>"><?= htmlspecialchars($e['type_name']) ?></span>
              <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold" style="font-size:.875rem;word-break:break-word">
                  <?= htmlspecialchars($e['title'] ?: substr($e['description'], 0, 60)) ?>
                </div>
                <div class="text-muted" style="font-size:.75rem">
                  <span class="color-dot" style="background:<?= htmlspecialchars($e['project_color']) ?>"></span>
                  <?= htmlspecialchars($e['project_name']) ?> &middot;
                  <?= htmlspecialchars(formatDate($e['entry_date'])) ?>
                </div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
          <?php if (!$recentEntries): ?>
          <p class="text-muted text-center p-4 small">No entries yet</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Active Projects -->
<?php if ($projects): ?>
<div class="row g-3">
  <?php foreach ($projects as $p): ?>
  <div class="col-md-6 col-xl-3">
    <a href="<?= url('projects/' . $p['id']) ?>" class="card card-hover text-decoration-none text-white h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="rounded" style="width:12px;height:12px;background:<?= htmlspecialchars($p['color']) ?>;display:inline-block"></span>
          <span class="fw-semibold text-truncate"><?= htmlspecialchars($p['name']) ?></span>
        </div>
        <?php if ($p['project_number']): ?>
        <div class="text-muted small mb-1"><?= htmlspecialchars($p['project_number']) ?></div>
        <?php endif; ?>
        <div class="mt-auto">
          <span class="badge bg-secondary"><?= $p['entry_count'] ?> entries</span>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div id="lightbox">
  <button id="lb-close" class="btn btn-outline-light position-fixed top-0 end-0 m-3">
    <i class="bi bi-x-lg"></i>
  </button>
  <img id="lb-img" src="" alt="">
  <video id="lb-vid" controls style="display:none"></video>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function() {
  Chart.defaults.color = '#94a3b8';
  Chart.defaults.borderColor = 'rgba(255,255,255,.08)';

  var trendLabels = <?= json_encode($trendLabels, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  var trendCounts = <?= json_encode($trendCounts, JSON_HEX_TAG) ?>;
  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: trendLabels,
      datasets: [{
        data: trendCounts,
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99,102,241,.15)',
        fill: true,
        tension: .4,
        pointRadius: 3,
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
  });

  var typeData   = <?= json_encode($typeCounts, JSON_HEX_TAG) ?>;
  var typeLabels = <?= json_encode($typeLabels,  JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  var typeColors = <?= json_encode($typeColors,  JSON_HEX_TAG) ?>;
  if (typeData.some(function(v){ return v > 0; })) {
    new Chart(document.getElementById('typeChart'), {
      type: 'doughnut',
      data: {
        labels: typeLabels,
        datasets: [{ data: typeData, backgroundColor: typeColors, borderWidth: 0 }]
      },
      options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, cutout: '65%' }
    });
  }
})();
</script>
