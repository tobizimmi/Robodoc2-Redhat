<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-0">Robot History</h4>
    <small class="text-muted">All tracked robots with entry history</small>
  </div>
</div>

<?php if (!$robots): ?>
<div class="card"><div class="card-body text-muted text-center py-5">
  <i class="bi bi-cpu fs-1 d-block mb-2 opacity-25"></i>
  No entries with serial numbers yet. Add a serial number when creating entries.
</div></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($robots as $r):
  $inv = $invBySerial[$r['mower_serial']] ?? null;
  $statusColor = match($inv['status'] ?? '') { 'active' => 'success', 'retired' => 'secondary', 'repair' => 'warning', default => 'info' };
?>
<div class="col-md-6 col-xl-4">
  <a href="<?= url('robots/' . urlencode($r['mower_serial'])) ?>" class="card card-hover text-decoration-none text-white h-100">
    <div class="card-body">
      <div class="d-flex align-items-start gap-3">
        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px">
          <i class="bi bi-cpu fs-5"></i>
        </div>
        <div class="flex-grow-1 min-width-0">
          <div class="fw-semibold text-truncate"><?= e($r['mower_serial']) ?></div>
          <?php if ($inv): ?>
          <div class="small text-muted text-truncate"><?= e($inv['name']) ?>
            <span class="badge bg-<?= $statusColor ?> ms-1" style="font-size:.6rem"><?= e($inv['status']) ?></span>
          </div>
          <?php endif; ?>
          <?php $managed = $managedBySerial[$r['mower_serial']] ?? null; ?>
          <div class="mt-2 d-flex gap-2 flex-wrap">
            <?php if ($r['entry_count']): ?><span class="badge bg-secondary"><?= $r['entry_count'] ?> entries</span><?php endif; ?>
            <?php if ($managed && $managed['session_count']): ?><span class="badge" style="background:#d97706"><?= $managed['session_count'] ?> sessions</span><?php endif; ?>
            <?php if ($managed && $managed['test_entry_count']): ?><span class="badge" style="background:#0ea5e9"><?= $managed['test_entry_count'] ?> test entries</span><?php endif; ?>
            <?php if ($r['last_seen']): ?>
            <span class="text-muted small">
              <i class="bi bi-calendar3 me-1"></i><?= e($r['first_seen']) ?> – <?= e($r['last_seen']) ?>
            </span>
            <?php endif; ?>
          </div>
          <?php if ($r['firmwares']): ?>
          <div class="text-muted small mt-1 text-truncate">
            <i class="bi bi-code-slash me-1"></i><?= e($r['firmwares']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </a>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
