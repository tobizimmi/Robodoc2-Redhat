<?php $enabled = ($s['live_sync_enabled'] ?? '0') === '1'; ?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Live-Sync Settings</h5>
  <span class="badge <?= $enabled ? 'bg-success' : 'bg-secondary' ?> ms-2"><?= $enabled ? 'Active' : 'Inactive' ?></span>
</div>

<div class="card mb-4" style="max-width:800px">
  <div class="card-body p-3 d-flex align-items-center justify-content-between">
    <div>
      <div class="fw-semibold small">Live-Sync <?= $enabled ? 'is active' : 'is turned off' ?></div>
      <div class="text-muted small">One click immediately turns sending, receiving and pulling off completely — regardless of the other settings.</div>
    </div>
    <form method="POST" action="<?= url('admin/live-sync/toggle') ?>" class="ms-3">
      <?= csrfField() ?>
      <button type="submit" class="btn <?= $enabled ? 'btn-outline-danger' : 'btn-success' ?> btn-sm">
        <i class="bi <?= $enabled ? 'bi-stop-circle' : 'bi-play-circle' ?> me-1"></i><?= $enabled ? 'Deactivate' : 'Activate' ?>
      </button>
    </form>
  </div>
</div>

<?php if (!empty($s['live_sync_pull_source_url'])): ?>
<div class="card mb-4" style="max-width:800px">
  <div class="card-body p-3 d-flex align-items-center justify-content-between">
    <div>
      <div class="fw-semibold small">Pull manually</div>
      <div class="text-muted small">Asks the source system immediately instead of waiting for the next cron run (every 15 min).</div>
    </div>
    <form method="POST" action="<?= url('admin/live-sync/pull-now') ?>" class="ms-3">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Pull now</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="alert alert-info small" style="max-width:800px">
  <i class="bi bi-info-circle me-1"></i>
  Automatically transfers newly created entries (incl. photos/attachments) from this system to another
  RoboDoc2 system. The same secret must be stored on both instances.
  <strong>Two transfer paths</strong> — depending on who can reach whom:
  <ul class="mb-0 mt-1">
    <li><strong>Send (Push):</strong> this system actively sends to a reachable target system.</li>
    <li><strong>Pull:</strong> if the target system (e.g. an OpenShift route) cannot be
      reached from outside, it instead fetches the data itself via cron from the source system — just like
      the Zentao relay, "one reaching the other" in one direction is enough.</li>
  </ul>
</div>

<form method="POST" action="<?= url('admin/live-sync') ?>">
  <?= csrfField() ?>
  <input type="hidden" name="live_sync_enabled" value="<?= $enabled ? '1' : '0' ?>">

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-cloud-download me-1"></i>Pull (this system fetches data from the source system)</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Source system URL <span class="text-muted small">(leave empty = no pulling)</span></label>
        <input type="url" name="live_sync_pull_source_url" class="form-control" value="<?= e($s['live_sync_pull_source_url'] ?? '') ?>" placeholder="https://zimmimail.de">
        <div class="form-text">The base URL of the system to pull from (without path). Must be reachable there under "Receive" with the same secret. Also enable the "Live-Sync Retry" cron job under Admin → Cron Jobs so pulling happens regularly.</div>
      </div>
    </div>
  </div>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-cloud-upload me-1"></i>Send (this system actively sends to a target system)</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Target URL <span class="text-muted small">(leave empty = no sending from this system)</span></label>
        <input type="url" name="live_sync_target_url" class="form-control" value="<?= e($s['live_sync_target_url'] ?? '') ?>" placeholder="https://<target-system>/api/sync/entry">
        <div class="form-text">Only use this if the target system is directly reachable from here. If not (e.g. an OpenShift route not reachable from outside), configure "Pull" on the target system instead and leave this field empty.</div>
      </div>
    </div>
  </div>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-inbox me-1"></i>Receive (endpoints this system provides for other systems)</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">This system's ingest URL <span class="text-muted small">(for "Send" on the other instance)</span></label>
        <input type="text" class="form-control" value="<?= e($ingestUrl) ?>" readonly onclick="this.select()">
      </div>
      <div class="mb-3">
        <label class="form-label">Allowed source host for attachments <span class="text-muted small">(empty = no check, not recommended)</span></label>
        <input type="text" name="live_sync_source_host" class="form-control" value="<?= e($s['live_sync_source_host'] ?? '') ?>" placeholder="zimmimail.de">
        <div class="form-text">When pulling/receiving photos/attachments, the host of the supplied download URL is checked — only this host is accepted (protection against misuse with a leaked secret).</div>
      </div>
    </div>
  </div>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-key me-1"></i>Secret</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Live-Sync Secret</label>
        <input type="password" name="live_sync_secret" class="form-control" placeholder="<?= $hasSecret ? '•••••••• (leave unchanged = do not change)' : '' ?>">
        <div class="form-text">Must be identical on both systems. Leave empty to keep the stored value.</div>
      </div>
    </div>
  </div>

  <div class="alert alert-warning small" style="max-width:800px">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Prerequisite: projects and entry types must be named identically on both systems — matching
    is done by name. Unknown projects/types are rejected, not created automatically.
    Only <strong>newly created</strong> entries are synced (no sync back). Photos/attachments added
    later are picked up on a subsequent pull, but other later changes (title,
    status, priority, …) are not.
    To protect against misuse/DoS, all endpoints are rate-limited per sender IP (max. 10 wrong secret attempts
    or 300 requests per 10/1 minutes) and each pull/send run is capped at 50 entries.
  </div>

  <div style="max-width:800px">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
  </div>
</form>
