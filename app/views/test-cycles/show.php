<?php
$csrf      = Auth::csrfToken();
$passed    = (int)($stats['passed']  ?? 0);
$failed    = (int)($stats['failed']  ?? 0);
$pending   = (int)($stats['pending'] ?? 0);
$skipped   = (int)($stats['skipped'] ?? 0);
$blocked   = (int)($stats['blocked'] ?? 0);
$total     = (int)($stats['total']   ?? 0);
$pct       = $total > 0 ? round($passed / $total * 100) : 0;
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0"><?= e($cycle['name']) ?></h5>
    <small class="text-muted">
      <a href="<?= url('test-plans/'.$cycle['plan_id']) ?>"><?= e($cycle['plan_name']) ?></a>
      <?php if ($cycle['environment']): ?> · <?= e($cycle['environment']) ?><?php endif; ?>
      <?php if ($cycle['build']): ?> · Build: <?= e($cycle['build']) ?><?php endif; ?>
    </small>
  </div>
  <div class="d-flex gap-2">
    <?php
    $statusColors = ['planned'=>'secondary','active'=>'primary','completed'=>'success','aborted'=>'danger'];
    $sc = $statusColors[$cycle['status']] ?? 'secondary';
    ?>
    <span class="badge bg-<?= $sc ?> fs-6"><?= e(ucfirst($cycle['status'])) ?></span>
  </div>
</div>

<!-- ── PIE CHART + STATS ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <!-- Pie Chart -->
  <div class="col-md-4">
    <div class="card border-secondary h-100">
      <div class="card-header border-secondary fw-semibold">
        <i class="bi bi-pie-chart me-2"></i>Test-Übersicht
      </div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <?php if ($total > 0): ?>
        <canvas id="testPieChart" width="200" height="200"></canvas>
        <div class="mt-2 text-center">
          <div class="fw-bold fs-4"><?= $pct ?>%</div>
          <div class="text-muted small">bestanden</div>
        </div>
        <?php else: ?>
        <p class="text-muted small">Noch keine Testergebnisse</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="col-md-8">
    <div class="row g-2 h-100">
      <?php
      $statCards = [
        ['label'=>'Gesamt',    'value'=>$total,   'color'=>'secondary', 'icon'=>'list-check'],
        ['label'=>'Bestanden', 'value'=>$passed,  'color'=>'success',   'icon'=>'check-circle-fill'],
        ['label'=>'Fehlgeschl.','value'=>$failed, 'color'=>'danger',    'icon'=>'x-circle-fill'],
        ['label'=>'Ausstehend','value'=>$pending, 'color'=>'warning',   'icon'=>'clock'],
        ['label'=>'Übersprungen','value'=>$skipped,'color'=>'info',     'icon'=>'skip-forward'],
        ['label'=>'Blockiert', 'value'=>$blocked, 'color'=>'dark',      'icon'=>'slash-circle'],
      ];
      ?>
      <?php foreach ($statCards as $sc2): ?>
      <div class="col-6 col-lg-4">
        <div class="card border-<?= $sc2['color'] ?> text-center py-2">
          <div class="text-<?= $sc2['color'] ?>" style="font-size:1.8rem;font-weight:700">
            <?= $sc2['value'] ?>
          </div>
          <div class="text-muted small">
            <i class="bi bi-<?= $sc2['icon'] ?> me-1"></i><?= $sc2['label'] ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── TEST RUNS ──────────────────────────────────────────────────── -->
<div class="card border-secondary mb-4">
  <div class="card-header border-secondary fw-semibold">
    <i class="bi bi-play-circle me-2"></i>Test Runs (<?= count($runs) ?>)
  </div>
  <div class="card-body p-0">
    <?php if (!$runs): ?>
    <p class="text-muted small p-3">Noch keine Test Runs in diesem Cycle.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-dark table-hover mb-0">
        <thead>
          <tr>
            <th>Name</th><th>Tester</th>
            <th class="text-success">✓</th>
            <th class="text-danger">✗</th>
            <th class="text-warning">⏳</th>
            <th class="text-info">↷</th>
            <th>Fortschritt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($runs as $run):
            $rTotal   = (int)$run['result_count'];
            $rPassed  = (int)$run['passed'];
            $rFailed  = (int)$run['failed'];
            $rPending = (int)$run['pending'];
            $rSkipped = (int)$run['skipped'];
            $rPct     = $rTotal > 0 ? round($rPassed/$rTotal*100) : 0;
          ?>
          <tr>
            <td>
              <a href="<?= url('test-runs/'.$run['id']) ?>">
                <?= e($run['name'] ?: 'Run #'.$run['id']) ?>
              </a>
            </td>
            <td class="small text-muted">—</td>
            <td class="text-success"><?= $rPassed ?></td>
            <td class="text-danger"><?= $rFailed ?></td>
            <td class="text-warning"><?= $rPending ?></td>
            <td class="text-info"><?= $rSkipped ?></td>
            <td style="min-width:120px">
              <div class="progress" style="height:8px">
                <div class="progress-bar bg-success" style="width:<?= $rPct ?>%"></div>
                <div class="progress-bar bg-danger"
                     style="width:<?= $rTotal>0?round($rFailed/$rTotal*100):0 ?>%"></div>
              </div>
              <small class="text-muted"><?= $rPct ?>%</small>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── FEHLGESCHLAGENE TESTS ──────────────────────────────────────── -->
<?php if ($failedResults): ?>
<div class="card border-danger mb-4">
  <div class="card-header border-danger d-flex align-items-center justify-content-between">
    <span class="fw-semibold text-danger">
      <i class="bi bi-x-circle me-2"></i>Fehlgeschlagene Tests (<?= count($failedResults) ?>)
    </span>
  </div>
  <div class="card-body p-0">
    <?php foreach ($failedResults as $r):
      $entryIds    = $r['entry_ids']    ? explode(',', $r['entry_ids'])    : [];
      $entryTitles = $r['entry_titles'] ? explode('||', $r['entry_titles']) : [];
    ?>
    <div class="p-3 border-bottom border-secondary">
      <div class="d-flex align-items-start gap-2">
        <span class="badge bg-danger mt-1">FAIL</span>
        <div class="flex-grow-1">
          <div class="fw-semibold"><?= e($r['test_name'] ?? 'Test #'.$r['id']) ?></div>
          <?php if ($r['notes']): ?>
          <div class="text-muted small mt-1">
            <i class="bi bi-chat-left-text me-1"></i><?= nl2br(e($r['notes'])) ?>
          </div>
          <?php endif; ?>
          <?php if ($r['test_desc']): ?>
          <div class="text-muted small mt-1 fst-italic"><?= e(substr($r['test_desc'],0,120)) ?></div>
          <?php endif; ?>
          <?php if ($entryIds): ?>
          <div class="mt-2 d-flex flex-wrap gap-1">
            <span class="text-muted small me-1"><i class="bi bi-link me-1"></i>Verknüpfte Einträge:</span>
            <?php foreach ($entryIds as $i => $eid): ?>
            <a href="<?= url('entries/'.$eid) ?>" target="_blank"
               class="badge bg-secondary text-decoration-none">
              #<?= e($eid) ?> <?= e(substr($entryTitles[$i] ?? '', 0, 30)) ?>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="text-muted small mt-1">
            <?= e($r['tester_name'] ?? '—') ?>
            <?php if ($r['executed_at']): ?>
            · <?= date('d.m.Y H:i', strtotime($r['executed_at'])) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── OFFENE TESTS ───────────────────────────────────────────────── -->
<?php if ($pendingResults): ?>
<div class="card border-warning mb-4">
  <div class="card-header border-warning d-flex align-items-center justify-content-between">
    <span class="fw-semibold">
      <i class="bi bi-clock me-2 text-warning"></i>Offene Tests (<?= count($pendingResults) ?>)
    </span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-dark mb-0">
        <thead><tr><th>Test Case</th><th>Beschreibung</th></tr></thead>
        <tbody>
          <?php foreach ($pendingResults as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e($r['test_name'] ?? 'Test #'.$r['id']) ?></td>
            <td class="small text-muted"><?= e(substr($r['test_desc'] ?? '', 0, 100)) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── PIE CHART JS ───────────────────────────────────────────────── -->
<?php if ($total > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const ctx = document.getElementById('testPieChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Bestanden', 'Fehlgeschlagen', 'Ausstehend', 'Übersprungen', 'Blockiert'],
      datasets: [{
        data: [<?= $passed ?>, <?= $failed ?>, <?= $pending ?>, <?= $skipped ?>, <?= $blocked ?>],
        backgroundColor: ['#10b981','#ef4444','#f59e0b','#3b82f6','#6b7280'],
        borderWidth: 2,
        borderColor: '#1e293b',
      }]
    },
    options: {
      responsive: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ctx.label + ': ' + ctx.raw +
              ' (' + Math.round(ctx.raw / <?= $total ?> * 100) + '%)'
          }
        }
      }
    }
  });
});
</script>
<?php endif; ?>
