<?php $csrf = Auth::csrfToken(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h5 class="mb-0"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Security</h5>
</div>

<div class="row g-4">
  <!-- IP Bans -->
  <div class="col-12">
    <div class="card border-secondary">
      <div class="card-header border-secondary d-flex align-items-center justify-content-between">
        <span class="fw-semibold">IP Bans</span>
        <button class="btn btn-outline-danger btn-sm" onclick="document.getElementById('addBanForm').classList.toggle('d-none')">
          <i class="bi bi-plus-lg me-1"></i>Add Ban
        </button>
      </div>
      <div class="card-body">
        <!-- Add ban form -->
        <form method="POST" action="<?= url('admin/security/ban') ?>" class="d-none mb-3 p-3 border border-danger rounded" id="addBanForm">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label small">IP Address</label>
              <input type="text" name="ip" class="form-control form-control-sm" placeholder="192.168.1.1" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small">Reason</label>
              <input type="text" name="reason" class="form-control form-control-sm" placeholder="Manual ban">
            </div>
            <div class="col-md-2">
              <label class="form-label small">Duration</label>
              <select name="duration" class="form-select form-select-sm">
                <option value="1">1 hour</option>
                <option value="24" selected>24 hours</option>
                <option value="168">7 days</option>
                <option value="0">Permanent</option>
              </select>
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-danger btn-sm w-100">Ban IP</button>
            </div>
          </div>
        </form>
        <!-- IP ban list -->
        <?php
        $bans = Database::fetchAll('SELECT b.*, u.name admin_name FROM ip_bans b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.banned_at DESC');
        ?>
        <?php if (!$bans): ?>
        <p class="text-muted small">No active IP bans.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-dark mb-0">
            <thead><tr><th>IP</th><th>Reason</th><th>Banned</th><th>Expires</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($bans as $ban): ?>
              <tr class="<?= (!$ban['expires_at'] || strtotime($ban['expires_at']) > time()) ? '' : 'opacity-50' ?>">
                <td class="font-monospace"><?= e($ban['ip_address']) ?></td>
                <td class="text-muted small"><?= e($ban['reason'] ?? '—') ?></td>
                <td class="small"><?= date('d.m.Y H:i', strtotime($ban['banned_at'])) ?></td>
                <td class="small"><?= $ban['expires_at'] ? date('d.m.Y H:i', strtotime($ban['expires_at'])) : '<span class="text-danger">Permanent</span>' ?></td>
                <td>
                  <form method="POST" action="<?= url('admin/security/unban/'.$ban['id']) ?>">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm py-0">Unban</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Active Sessions — NIS2: session visibility -->
  <div class="col-12">
    <div class="card border-secondary">
      <div class="card-header border-secondary d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-people me-2"></i>Active Sessions</span>
        <span class="badge bg-info" id="sessionCount"></span>
      </div>
      <div class="card-body">
        <?php
        try {
          $sessions = Database::fetchAll(
            'SELECT s.*, u.name user_name, u.email FROM active_sessions s
             LEFT JOIN users u ON u.id=s.user_id
             ORDER BY s.last_activity DESC LIMIT 50'
          );
        } catch (Throwable) { $sessions = []; }
        ?>
        <?php if (!$sessions): ?>
        <p class="text-muted small">No active sessions tracked yet.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-dark mb-0">
            <thead><tr><th>User</th><th>IP</th><th>Browser</th><th>Started</th><th>Last Active</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($sessions as $s): ?>
              <tr>
                <td><strong><?= e($s['user_name'] ?? '?') ?></strong><br><small class="text-muted"><?= e($s['email'] ?? '') ?></small></td>
                <td class="font-monospace small"><?= e($s['ip_address'] ?? '') ?></td>
                <td class="small text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(substr($s['user_agent'] ?? '',0,60)) ?></td>
                <td class="small"><?= date('d.m H:i', strtotime($s['created_at'])) ?></td>
                <td class="small"><?= date('d.m H:i', strtotime($s['last_activity'])) ?></td>
                <td>
                  <form method="POST" action="<?= url('admin/security/kill-session/'.$s['id']) ?>">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <button class="btn btn-outline-danger btn-sm py-0" title="Terminate session">✕</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <script>document.getElementById('sessionCount').textContent = '<?= count($sessions) ?> active';</script>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Failed Logins -->
  <div class="col-12">
    <div class="card border-secondary">
      <div class="card-header border-secondary fw-semibold">Recent Failed Login Attempts</div>
      <div class="card-body">
        <?php
        $attempts = Database::fetchAll(
            'SELECT identifier, COUNT(*) cnt, MAX(failed_at) last_at FROM login_attempts
             WHERE failed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY identifier ORDER BY cnt DESC LIMIT 20'
        );
        ?>
        <?php if (!$attempts): ?>
        <p class="text-muted small">No failed attempts in the last 24 hours.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-dark mb-0">
            <thead><tr><th>Identifier (hashed)</th><th>Attempts</th><th>Last attempt</th></tr></thead>
            <tbody>
              <?php foreach ($attempts as $a): ?>
              <tr>
                <td class="font-monospace small"><?= e(substr($a['identifier'],0,20)) ?>…</td>
                <td><span class="badge bg-<?= $a['cnt']>=5?'danger':'warning' ?>"><?= $a['cnt'] ?></span></td>
                <td class="small"><?= date('d.m.Y H:i', strtotime($a['last_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>