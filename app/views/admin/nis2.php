<?php
/**
 * NIS2 / BSI Compliance Overview
 * EU Directive 2022/2555 — Article 21 Security Measures
 */
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0"><i class="bi bi-shield-check me-2 text-success"></i>NIS2 & IT-Sicherheit Compliance</h5>
  <span class="badge bg-success fs-6">Stand: <?= date('d.m.Y') ?></span>
</div>

<?php
$checks = [
  'NIS2 Art.21 — Authentifizierung' => [
    ['Passwort-Hashing (bcrypt cost 12)', true, 'PASSWORD_BCRYPT mit cost=12'],
    ['Mindestlänge 10 Zeichen + Komplexität', true, 'Groß/Klein/Zahl/Sonderzeichen erforderlich'],
    ['2-Faktor-Authentifizierung (TOTP)', true, 'Google/MS Authenticator kompatibel'],
    ['Backup-Codes für 2FA', true, '10 Einmalcodes bei 2FA-Aktivierung'],
    ['Brute-Force-Schutz', true, '5 Versuche → Wartezeit, 10 → IP-Sperre'],
    ['Session-Timeout (8h Inaktivität)', true, 'Automatischer Logout nach 8h'],
    ['Session Fixation Schutz', true, 'session_regenerate_id() bei Login'],
    ['Sichere Cookie-Flags', true, 'HttpOnly, SameSite=Lax, Secure (HTTPS)'],
  ],
  'NIS2 Art.21 — Zugangskontrolle' => [
    ['Rollenbasierte Zugriffskontrolle (RBAC)', true, 'admin/user/viewer Rollen'],
    ['Projektbasierte Zugangsbeschränkung', true, 'Gruppenbasierter Projektzugang'],
    ['Private Einträge', true, 'Sichtbarkeit auf Ersteller beschränkbar'],
    ['IP-Sperren (Administrativ)', true, 'Manuelle + automatische IP-Bans'],
    ['Aktive Sessions überwachbar', true, 'Admin kann Sessions terminieren'],
    ['Account-Sperrung (disabled/pending)', true, 'Admin-Freigabe für neue Accounts'],
  ],
  'NIS2 Art.21 — Protokollierung & Monitoring' => [
    ['Audit-Log für alle Sicherheitsereignisse', true, 'Login, Logout, Fehlversuche, 2FA'],
    ['Datenzugriffs-Protokollierung', true, 'Lesezugriffe auf Einträge geloggt'],
    ['IP + User-Agent im Audit-Log', true, 'Vollständige Nachverfolgbarkeit'],
    ['Audit-Aufbewahrung 2 Jahre', true, 'Automatische DB-Bereinigung nach 730 Tagen'],
    ['Admin-Benachrichtigung bei Sicherheitsvorfällen', true, 'E-Mail bei IP-Ban, Brute-Force'],
    ['Fehlgeschlagene Login-Übersicht', true, 'Admin → Security Dashboard'],
  ],
  'NIS2 Art.21 — Verschlüsselung & Übertragungssicherheit' => [
    ['API-Keys verschlüsselt in DB (AES-256-GCM)', true, 'Jira/SharePoint Keys mit APP_SECRET verschlüsselt'],
    ['HTTPS / TLS erzwungen', true, 'HSTS Header gesetzt (1 Jahr)'],
    ['Datenverschlüsselung at rest', false, 'DB-Verschlüsselung: Serverseitig konfigurieren'],
    ['Sichere Passwort-Reset-Links', true, 'HMAC-Token, 1h Gültigkeit, Einmalnutzung'],
    ['CSRF-Schutz auf allen POST-Requests', true, 'Synchronizer Token Pattern'],
    ['SQL-Injection-Schutz', true, 'Ausschließlich Prepared Statements'],
    ['XSS-Schutz', true, 'htmlspecialchars() + CSP-Header'],
  ],
  'NIS2 Art.21 — Sicherheitsheader' => [
    ['X-Frame-Options: SAMEORIGIN', true, 'Clickjacking-Schutz'],
    ['X-Content-Type-Options: nosniff', true, 'MIME-Sniffing-Schutz'],
    ['Content-Security-Policy', true, 'XSS-Schutz via CSP'],
    ['Strict-Transport-Security (HSTS)', true, 'HTTPS erzwungen'],
    ['Referrer-Policy', true, 'strict-origin-when-cross-origin'],
    ['Permissions-Policy', true, 'Kamera/Mikrofon/Standort gesperrt'],
    ['Server-Header versteckt', true, 'X-Powered-By entfernt'],
  ],
  'DSGVO / BDSG — Datenschutz' => [
    ['Personenbezogene Daten minimiert', true, 'Nur Name + E-Mail gespeichert'],
    ['Audit-Log für Datenzugriffe', true, 'Nachvollziehbarkeit der Zugriffe'],
    ['Passwort-Reset E-Mail-Schutz', true, 'Kein Enumeration-Angriff möglich'],
    ['Private Einträge (Vertraulichkeit)', true, 'Datenschutz auf Eintragsebene'],
    ['Datenexport pro Nutzer', false, 'DSGVO Art.20: Datenportabilität fehlt noch'],
  ],
  'BSI IT-Grundschutz' => [
    ['Passwort-Policy (BSI M 4.133)', true, 'Mind. 10 Zeichen, Komplexität'],
    ['Mehrfaktor-Authentifizierung (BSI ORP.4)', true, 'TOTP implementiert'],
    ['Protokollierung (BSI OPS.1.1.7)', true, 'Audit-Log mit 2J Aufbewahrung'],
    ['Sitzungsverwaltung (BSI APP.3.1)', true, 'Timeout, Fixation-Schutz, Tracking'],
    ['Eingabevalidierung (BSI APP.3.1)', true, 'Prepared Statements, htmlspecialchars'],
    ['Patch-Management', false, 'Prozess für regelmäßige Updates definieren'],
    ['Datensicherung / Backup', false, 'Backup-Konzept auf Serverebene erforderlich'],
    ['Notfallplan (BSI BCM)', false, 'Business Continuity Plan dokumentieren'],
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
        <div class="text-muted small"><?= $passed ?> von <?= $total ?> Anforderungen erfüllt</div>
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
  <strong>Hinweis:</strong> Diese Übersicht bezieht sich auf Maßnahmen auf Anwendungsebene.
  Serverseitige Maßnahmen (Backup, DB-Verschlüsselung, Patch-Management, Netzwerksicherheit)
  müssen zusätzlich auf Infrastrukturebene umgesetzt werden.
</div>
