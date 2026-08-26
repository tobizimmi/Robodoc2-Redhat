<div class="row g-4 mb-4">
  <?php foreach (['users'=>['Users','person','primary'],'entries'=>['Entries','journal-text','info'],'projects'=>['Projects','folder','success'],'test_plans'=>['Test Plans','clipboard','warning']] as $k=>[$l,$icon,$c]): ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:var(--bs-<?= $c ?>)">
      <div class="card-body p-3">
        <div class="text-muted small"><?= $l ?></div>
        <div class="stat-number"><?= $stats[$k] ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <?php
  $sections = [
    ['Manage Users', 'Create, edit, assign roles', 'person-badge', 'admin/users', 'primary'],
    ['Sync Review', 'Review all pending Jira & Zentao changes', 'arrow-left-right', 'admin/sync-review', 'warning'],
    ['Settings', 'App name, Jira URL, entry type filters', 'gear', 'admin/settings', 'secondary'],
    ['Jira Settings', 'Templates, field mapping', 'plugin', 'admin/jira', 'warning'],
    ['Zentao Settings', 'URL, token, templates, mapping', 'diagram-3', 'admin/zentao', 'info'],
    ['Microsoft SSO', 'Sign in with Microsoft (Entra ID)', 'microsoft', 'admin/microsoft-sso', 'primary'],
    ['Live-Sync', 'Neue Einträge automatisch an ein anderes RoboDoc2-System übertragen', 'arrow-left-right', 'admin/live-sync', 'purple'],
    ['Entry Types', 'Bug, Error, Feature Request…', 'tags', 'admin/entry-types', 'info'],
    ['Error Categories', 'Hardware, Software, Navigation…', 'exclamation-triangle', 'admin/categories', 'warning'],
    ['Custom Fields', 'Additional fields for entries', 'input-cursor-text', 'admin/custom-fields', 'success'],
    ['Test Case Fields', 'Custom fields for test cases', 'clipboard-plus', 'admin/test-case-fields', 'success'],

    ['Test Environments', 'iOS, Android, Browser…', 'laptop', 'admin/environments', 'purple'],
    ['Checklists', 'Reusable test checklists', 'list-check', 'admin/checklists', 'pink'],
    ['Automatic Backup', 'DB + uploads, schedule, rotation', 'archive', 'admin/backup', 'success'],
    ['Cron Jobs', 'Automatische Hintergrundaufgaben verwalten', 'clock-history', 'admin/cron', 'info'],
        ['NIS2 Compliance', 'EU NIS2 & BSI Anforderungen prüfen', 'shield-check', 'admin/nis2', 'success'],
        ['Security', 'IP bans, failed logins, brute-force monitoring', 'shield-exclamation', 'admin/security', 'danger'],
        ['Export Templates', 'Define header, footer, branding for entry exports', 'file-earmark-richtext', 'admin/export-templates', 'info'],
        ['Feedback Inbox', 'User bug reports and improvement ideas', 'inbox', 'admin/feedback', 'info'],
        ['Audit Log', 'View all activities', 'shield-check', 'admin/audit', 'danger'],
  ];
  foreach ($sections as [$title, $desc, $icon, $path, $color]):
  ?>
  <div class="col-md-6 col-xl-3">
    <a href="<?= url($path) ?>" class="card card-hover text-decoration-none text-white h-100">
      <div class="card-body">
        <div class="mb-2"><i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-4"></i></div>
        <h6 class="fw-semibold mb-1"><?= $title ?></h6>
        <p class="text-muted small mb-0"><?= $desc ?></p>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
