<?php
/**
 * NIS2 / BSI Compliance Overview
 * EU Directive 2022/2555 — Article 21 Security Measures
 */
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0"><i class="bi bi-shield-check me-2 text-success"></i>NIS2 & IT Security Compliance</h5>
  <span class="badge bg-success fs-6">As of: <?= date('d.m.Y') ?></span>
</div>

<?php
$checks = [
  'NIS2 Art.21 — Authentication' => [
    ['Password hashing (bcrypt cost 12)', true, 'PASSWORD_BCRYPT with cost=12'],
    ['Minimum length 10 characters + complexity', true, 'Upper/lower case, digit, special character required'],
    ['Two-factor authentication (TOTP)', true, 'Compatible with Google/MS Authenticator'],
    ['Backup codes for 2FA', true, '10 one-time codes on 2FA activation'],
    ['Brute-force protection', true, '5 attempts → delay, 10 → IP ban'],
    ['Session timeout (8h inactivity)', true, 'Automatic logout after 8h'],
    ['Session fixation protection', true, 'session_regenerate_id() on login'],
    ['Secure cookie flags', true, 'HttpOnly, SameSite=Lax, Secure (HTTPS)'],
  ],
  'NIS2 Art.21 — Access Control' => [
    ['Role-based access control (RBAC)', true, 'admin/user/viewer roles'],
    ['Project-based access restriction', true, 'Group-based project access'],
    ['Private entries', true, 'Visibility restrictable to creator'],
    ['IP bans (administrative)', true, 'Manual + automatic IP bans'],
    ['Active sessions monitorable', true, 'Admin can terminate sessions'],
    ['Account lockout (disabled/pending)', true, 'Admin approval for new accounts'],
  ],
  'NIS2 Art.21 — Logging & Monitoring' => [
    ['Audit log for all security events', true, 'Login, logout, failed attempts, 2FA'],
    ['Data access logging', true, 'Read access to entries logged'],
    ['IP + user agent in audit log', true, 'Full traceability'],
    ['Audit retention 2 years', true, 'Automatic DB cleanup after 730 days'],
    ['Admin notification on security incidents', true, 'Email on IP ban, brute-force'],
    ['Failed login overview', true, 'Admin → Security Dashboard'],
  ],
  'NIS2 Art.21 — Encryption & Transmission Security' => [
    ['API keys encrypted in DB (AES-256-GCM)', true, 'Jira/SharePoint keys encrypted with APP_SECRET'],
    ['HTTPS / TLS enforced', true, 'HSTS header set (1 year)'],
    ['Data encryption at rest', false, 'DB encryption: configure server-side'],
    ['Secure password reset links', true, 'HMAC token, 1h validity, single use'],
    ['CSRF protection on all POST requests', true, 'Synchronizer token pattern'],
    ['SQL injection protection', true, 'Exclusively prepared statements'],
    ['XSS protection', true, 'htmlspecialchars() + CSP header'],
  ],
  'NIS2 Art.21 — Security Headers' => [
    ['X-Frame-Options: SAMEORIGIN', true, 'Clickjacking protection'],
    ['X-Content-Type-Options: nosniff', true, 'MIME-sniffing protection'],
    ['Content-Security-Policy', true, 'XSS protection via CSP'],
    ['Strict-Transport-Security (HSTS)', true, 'HTTPS enforced'],
    ['Referrer-Policy', true, 'strict-origin-when-cross-origin'],
    ['Permissions-Policy', true, 'Camera/microphone/location blocked'],
    ['Server header hidden', true, 'X-Powered-By removed'],
  ],
  'GDPR / Data Protection' => [
    ['Personal data minimized', true, 'Only name + email stored'],
    ['Audit log for data access', true, 'Traceability of access'],
    ['Password reset email protection', true, 'No enumeration attack possible'],
    ['Private entries (confidentiality)', true, 'Data protection at entry level'],
    ['Per-user data export', false, 'GDPR Art.20: data portability still missing'],
  ],
  'BSI IT Baseline Protection' => [
    ['Password policy (BSI M 4.133)', true, 'Min. 10 characters, complexity'],
    ['Multi-factor authentication (BSI ORP.4)', true, 'TOTP implemented'],
    ['Logging (BSI OPS.1.1.7)', true, 'Audit log with 2yr retention'],
    ['Session management (BSI APP.3.1)', true, 'Timeout, fixation protection, tracking'],
    ['Input validation (BSI APP.3.1)', true, 'Prepared statements, htmlspecialchars'],
    ['Patch management', false, 'Define process for regular updates'],
    ['Data backup', false, 'Backup concept required at server level'],
    ['Emergency plan (BSI BCM)', false, 'Document business continuity plan'],
  ],
];
$total = 0; $passed = 0;
foreach ($checks as $section => $items) {
    foreach ($items as $item) { $total++; if ($item[1]) $passed++; }
}
$pct = round($passed / $total * 100);
?>

<!-- Overall score -->
<div class="card border-secondary mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center gap-4">
      <div class="text-center" style="min-width:80px">
        <div style="font-size:2.5rem;font-weight:700;color:<?= $pct>=80?'#10b981':($pct>=60?'#f59e0b':'#ef4444') ?>"><?= $pct ?>%</div>
        <div class="text-muted small">Compliance</div>
      </div>
      <div class="flex-grow-1">
        <div class="progress mb-2" style="height:12px">
          <div class="progress-bar bg-<?= $pct>=80?'success':($pct>=60?'warning':'danger') ?>"
               style="width:<?= $pct ?>%"></div>
        </div>
        <div class="text-muted small"><?= $passed ?> of <?= $total ?> requirements met</div>
      </div>
    </div>
  </div>
</div>

<!-- Detail checks -->
<?php foreach ($checks as $section => $items): ?>
<?php $sectionPassed = count(array_filter($items, fn($i)=>$i[1])); ?>
<div class="card border-secondary mb-3">
  <div class="card-header border-secondary d-flex align-items-center justify-content-between">
    <span class="fw-semibold"><?= e($section) ?></span>
    <span class="badge bg-<?= $sectionPassed===count($items)?'success':'warning' ?>">
      <?= $sectionPassed ?>/<?= count($items) ?>
    </span>
  </div>
  <div class="card-body p-0">
    <?php foreach ($items as [$label, $ok, $detail]): ?>
    <div class="d-flex align-items-start gap-3 px-3 py-2 border-bottom border-secondary">
      <span class="mt-1" style="font-size:1.1rem"><?= $ok ? '✅' : '⚠️' ?></span>
      <div>
        <div class="<?= $ok?'':'text-warning' ?> fw-semibold" style="font-size:.9rem"><?= e($label) ?></div>
        <div class="text-muted" style="font-size:.78rem"><?= e($detail) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="alert alert-info mt-3">
  <i class="bi bi-info-circle me-2"></i>
  <strong>Note:</strong> This overview covers measures at the application level.
  Server-side measures (backup, DB encryption, patch management, network security)
  must additionally be implemented at the infrastructure level.
</div>
