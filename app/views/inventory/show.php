<div class="d-flex align-items-start justify-content-between mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('inventory') ?>"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5 class="mb-0 fw-bold"><?= e($item['name']) ?></h5>
      <?php if ($item['serial_number']): ?><small class="text-muted"><?= e($item['serial_number']) ?></small><?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($item['serial_number']): ?>
    <button class="btn btn-outline-secondary btn-sm" onclick="downloadQr()" title="Download QR Code">
      <i class="bi bi-qr-code"></i>
    </button>
    <?php endif; ?>
    <a href="<?= url('inventory/' . $item['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
  </div>
</div>

<?php if ($item['serial_number']): ?>
<canvas id="qrCanvas" style="display:none"></canvas>
<script>
(function() {
  const serial   = <?= json_encode($item['serial_number']) ?>;
  const firmware = <?= json_encode($item['firmware_version'] ?? '') ?>;
  const projId   = <?= json_encode((string)($item['project_id'] ?? '')) ?>;

  function buildQrData() {
    const parts = ['serial=' + encodeURIComponent(serial)];
    if (projId) parts.push('project_id=' + encodeURIComponent(projId));
    return 'ROBODOC:' + parts.join('&');
  }

  function doRender(qrData, cellSize) {
    const qr = qrcode(0, 'M');
    qr.addData(qrData);
    qr.make();

    const modules = qr.getModuleCount();
    const padding = cellSize * 4;
    const qrArea  = modules * cellSize + padding * 2;
    const labelH  = firmware ? cellSize * 5 : cellSize * 3;

    // Set final canvas size ONCE — resizing clears the canvas
    const canvas  = document.getElementById('qrCanvas');
    canvas.width  = qrArea;
    canvas.height = qrArea + labelH;
    const ctx     = canvas.getContext('2d');

    // White background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // QR modules
    ctx.fillStyle = '#000000';
    for (let r = 0; r < modules; r++) {
      for (let c = 0; c < modules; c++) {
        if (qr.isDark(r, c)) {
          ctx.fillRect(padding + c * cellSize, padding + r * cellSize, cellSize, cellSize);
        }
      }
    }

    // Serial label
    ctx.textAlign = 'center';
    ctx.fillStyle = '#000000';
    ctx.font = 'bold ' + (cellSize * 2) + 'px monospace';
    ctx.fillText(serial, qrArea / 2, qrArea + cellSize * 2.2);

    // Firmware label
    if (firmware) {
      ctx.font = (cellSize * 1.5) + 'px monospace';
      ctx.fillStyle = '#555555';
      ctx.fillText('FW: ' + firmware, qrArea / 2, qrArea + cellSize * 4);
    }
  }

  window.downloadQr = function() {
    const qrData   = buildQrData();
    const cellSize = 8;

    function generate() {
      doRender(qrData, cellSize);
      const canvas = document.getElementById('qrCanvas');
      const a = document.createElement('a');
      a.href     = canvas.toDataURL('image/png');
      a.download = 'QR_' + serial.replace(/[^a-zA-Z0-9_-]/g, '_') + '.png';
      a.click();
    }

    if (window.qrcode) { generate(); return; }
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
    s.onload = generate;
    document.head.appendChild(s);
  };
})();
</script>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Details</div>
      <div class="card-body p-0">
        <table class="table table-dark table-sm mb-0">
          <tbody>
            <?php foreach (['project_name'=>'Project','firmware_version'=>'Firmware','location'=>'Location','status'=>'Status','purchased_at'=>'Purchased'] as $k=>$l): ?>
            <?php if (!$item[$k]) continue; ?>
            <tr><th class="text-muted fw-normal small border-secondary" style="width:40%"><?= $l ?></th>
                <td class="border-secondary small"><?= e($k==='purchased_at' ? formatDate($item[$k]) : $item[$k]) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($item['comment']): ?>
    <div class="card mb-3">
      <div class="card-body small text-muted"><?= e($item['comment']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">New Logbook Entry</div>
      <div class="card-body">
        <form method="POST" action="<?= url('inventory/' . $item['id'] . '/logbook') ?>">
          <?= csrfField() ?>
          <div class="row g-2">
            <div class="col-md-3"><input type="date" name="log_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-2"><input type="time" name="log_time" class="form-control form-control-sm" value="<?= date('H:i') ?>"></div>
            <div class="col-md-4"><input type="text" name="action" class="form-control form-control-sm" placeholder="Action *" required></div>
            <div class="col-md-3"><button class="btn btn-primary btn-sm w-100">Add</button></div>
            <div class="col-12"><textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Description (optional)"></textarea></div>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header border-secondary fw-semibold small">Logbook (<?= count($logbook) ?>)</div>
      <?php if (!$logbook): ?>
      <div class="card-body text-muted text-center small p-4">No entries yet.</div>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($logbook as $log): ?>
        <div class="list-group-item bg-transparent border-secondary py-2 px-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold small"><?= e($log['action']) ?></div>
              <div class="text-muted small"><?= formatDate($log['log_date']) ?> <?= substr($log['log_time'],0,5) ?> &middot; <?= e($log['user_name'] ?? '—') ?></div>
              <?php if ($log['description']): ?><div class="text-muted small mt-1"><?= e($log['description']) ?></div><?php endif; ?>
            </div>
            <form method="POST" action="<?= url('inventory/' . $item['id'] . '/logbook/' . $log['id'] . '/delete') ?>" data-confirm="Delete entry?">
              <?= csrfField() ?><button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash small"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
