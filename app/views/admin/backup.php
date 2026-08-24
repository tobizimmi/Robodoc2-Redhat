<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0"><i class="bi bi-archive me-2 text-success"></i>Automatic Backup</h5>
</div>

<?php
$secret   = $s['backup_secret'] ?? '';
$path     = $s['backup_path']   ?? '';
$keep     = (int)($s['backup_keep'] ?? 7);
$sched    = $s['backup_schedule'] ?? '0 2 * * *';
$lastRun  = $s['backup_last_run']  ?? null;
$lastFile = $s['backup_last_file'] ?? null;

// Build cron URL
$fwd    = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$scheme = $fwd === 'https' ? 'https' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$cronUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/api/backup/run?token=' . urlencode($secret);
$cronCmd = $sched . '  curl -s "' . $cronUrl . '" > /dev/null 2>&1';
?>

<div class="row g-4" style="max-width:900px">

  <!-- Settings -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small"><i class="bi bi-gear me-1"></i>Settings</div>
      <div class="card-body p-4">
        <form method="POST" action="<?= url('admin/backup') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save" id="backupAction">

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label">Backup Directory <span class="text-danger">*</span></label>
              <input type="text" name="backup_path" class="form-control font-monospace"
                     value="<?= e($path) ?>"
                     placeholder="/var/www/vhosts/zimmimail.de/robodoc_backups">
              <div class="form-text">Absolute path on the server. Should be <strong>outside</strong> the web root. Folder is created automatically.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Keep Versions</label>
              <input type="number" name="backup_keep" class="form-control" value="<?= $keep ?>" min="1" max="99">
              <div class="form-text">Older backups are deleted automatically.</div>
            </div>
          </div>

          <!-- Schedule -->
          <div class="mb-4">
            <label class="form-label">Schedule (Cron Expression)</label>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <input type="text" name="backup_schedule" id="backupSchedInput" class="form-control font-monospace"
                     value="<?= e($sched) ?>" style="max-width:200px" oninput="updateCronPreview()">
              <div class="d-flex gap-1 flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPreset('0 2 * * *')">Daily 02:00</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPreset('0 3 * * 0')">Weekly Sun 03:00</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPreset('0 1 * * 1')">Weekly Mon 01:00</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPreset('0 4 1 * *')">Monthly 1st 04:00</button>
              </div>
            </div>
            <div class="form-text mt-1">Format: <code>minute hour day month weekday</code> &nbsp;·&nbsp; <span id="cronHuman" class="text-info"></span></div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="document.getElementById('backupAction').value='run'">
              <i class="bi bi-play-fill me-1"></i>Save &amp; Run Now
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Cron Setup -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small"><i class="bi bi-clock me-1"></i>Automatic Scheduling (Cron Setup)</div>
      <div class="card-body p-4">
        <p class="text-muted small mb-2">
          Add this line to your server's crontab (<code>crontab -e</code>) or in Plesk under
          <strong>Scheduled Tasks</strong>:
        </p>
        <div class="input-group mb-3">
          <input type="text" class="form-control font-monospace small bg-dark text-info border-secondary"
                 id="cronCmdField" value="<?= e($cronCmd) ?>" readonly>
          <button type="button" class="btn btn-outline-secondary"
                  onclick="navigator.clipboard.writeText(document.getElementById('cronCmdField').value).then(()=>{this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',2000)})">
            Copy
          </button>
        </div>
        <p class="text-muted small mb-2">
          Or call the URL directly (e.g. for a web-based cron service):
        </p>
        <div class="input-group">
          <input type="text" class="form-control font-monospace small bg-dark text-warning border-secondary"
                 id="cronUrlField" value="<?= e($cronUrl) ?>" readonly>
          <button type="button" class="btn btn-outline-secondary"
                  onclick="navigator.clipboard.writeText(document.getElementById('cronUrlField').value).then(()=>{this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',2000)})">
            Copy
          </button>
        </div>
        <?php if ($lastRun): ?>
        <div class="mt-3 text-muted small">
          <i class="bi bi-check-circle text-success me-1"></i>
          Last backup: <strong><?= e($lastRun) ?></strong>
          <?php if ($lastFile): ?>&nbsp;·&nbsp; <?= e($lastFile) ?><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Backup List -->
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small d-flex align-items-center justify-content-between">
        <span><i class="bi bi-archive me-1"></i>Existing Backups</span>
        <?php if ($path): ?>
        <span class="text-muted fw-normal font-monospace" style="font-size:.7rem"><?= e($path) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!$path): ?>
      <div class="card-body text-muted small text-center py-4">Configure a backup directory above to see backups here.</div>
      <?php elseif (!is_dir($path)): ?>
      <div class="card-body text-muted small text-center py-4">Directory does not exist yet — it will be created on the first backup run.</div>
      <?php elseif (!$backups): ?>
      <div class="card-body text-muted small text-center py-4">No backups found yet. Click <strong>Save &amp; Run Now</strong> to create the first one.</div>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($backups as $b): ?>
        <div class="list-group-item bg-transparent border-secondary py-2 px-3 d-flex align-items-center gap-3">
          <i class="bi bi-file-zip text-success fs-5 flex-shrink-0"></i>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold small font-monospace text-truncate"><?= e($b['name']) ?></div>
            <div class="text-muted" style="font-size:.72rem">
              <?= date('d.m.Y H:i', $b['mtime']) ?> &nbsp;·&nbsp; <?= formatFileSize((int)$b['size']) ?>
            </div>
          </div>
          <div class="d-flex gap-1 flex-shrink-0">
            <a href="<?= url('admin/backup/download?file=' . urlencode($b['name'])) ?>"
               class="btn btn-outline-success btn-sm py-0 px-2" title="Download">
              <i class="bi bi-download"></i>
            </a>
            <form method="POST" action="<?= url('admin/backup/delete') ?>"
                  data-confirm="Delete backup <?= e($b['name']) ?>?">
              <?= csrfField() ?>
              <input type="hidden" name="file" value="<?= e($b['name']) ?>">
              <button class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card-footer border-secondary text-muted small">
        <?= count($backups) ?> backup<?= count($backups) !== 1 ? 's' : '' ?> stored
        &nbsp;·&nbsp; keeping <?= $keep ?> version<?= $keep !== 1 ? 's' : '' ?>
        &nbsp;·&nbsp; Total: <?= formatFileSize(array_sum(array_column($backups, 'size'))) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
const _cronBase = '<?= e($sched) ?>';

function setPreset(expr) {
  document.getElementById('backupSchedInput').value = expr;
  updateCronPreview();
  updateCronCmd(expr);
}

function updateCronPreview() {
  const expr = document.getElementById('backupSchedInput').value.trim();
  updateCronCmd(expr);
  const lbl = document.getElementById('cronHuman');
  if (lbl) lbl.textContent = describeCron(expr);
}

function updateCronCmd(expr) {
  const url  = <?= json_encode($cronUrl) ?>;
  const cmd  = expr + '  curl -s "' + url + '" > /dev/null 2>&1';
  const f1 = document.getElementById('cronCmdField');
  if (f1) f1.value = cmd;
}

function describeCron(expr) {
  const parts = expr.trim().split(/\s+/);
  if (parts.length !== 5) return '';
  const [min, hr, dom, mon, dow] = parts;
  const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  if (dom === '*' && mon === '*' && dow === '*') {
    return `Every day at ${hr.padStart(2,'0')}:${min.padStart(2,'0')}`;
  }
  if (dom === '*' && mon === '*' && /^\d$/.test(dow)) {
    return `Every ${days[+dow] || 'week'} at ${hr.padStart(2,'0')}:${min.padStart(2,'0')}`;
  }
  if (/^\d+$/.test(dom) && mon === '*' && dow === '*') {
    return `Monthly on day ${dom} at ${hr.padStart(2,'0')}:${min.padStart(2,'0')}`;
  }
  return expr;
}

updateCronPreview();
</script>
