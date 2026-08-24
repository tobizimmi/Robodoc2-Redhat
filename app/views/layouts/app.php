<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
<meta name="theme-color" content="#1e293b">
<meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="RoboDoc">
<link rel="manifest" href="<?= url('manifest.json') ?>">
<link rel="apple-touch-icon" href="<?= asset('icons/icon-192.png') ?>">
<title><?= e($title ?? 'RoboDoc') ?> &ndash; <?= e(appSetting('app_name', APP_NAME)) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>

<div class="d-flex" id="wrapper">

  <!-- Sidebar -->
  <nav id="sidebar" class="d-flex flex-column flex-shrink-0 p-0 bg-dark border-end border-secondary">
    <a href="<?= url() ?>" class="d-flex align-items-center gap-2 px-3 py-3 text-white text-decoration-none border-bottom border-secondary">
      <i class="bi bi-robot fs-5 text-primary"></i>
      <span class="fw-bold fs-6"><?= e(appSetting('app_name', APP_NAME)) ?></span>
      <span class="badge bg-primary ms-auto" style="font-size:.65rem"><?= APP_VERSION ?></span>
    </a>
    <ul class="nav nav-pills flex-column mb-auto px-2 py-2 gap-1">
      <li class="nav-item">
        <a href="<?= url('dashboard') ?>" class="nav-link text-white <?= isActive('dashboard') ?: isActive('/') ?>">
          <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>
      </li>
      <?php if (Auth::canView('entries')): ?>
      <li class="nav-item">
        <a href="<?= url('entries') ?>" class="nav-link text-white <?= isActive('entries') ?>">
          <i class="bi bi-journal-text me-2"></i>Entries
        </a>
      </li>
      <?php if (Auth::canView('entries')): ?>
      <li class="nav-item">
        <a href="<?= url('test-results') ?>" class="nav-link text-white <?= isActive('test-results') ?>">
          <i class="bi bi-clipboard2-check me-2"></i>Test Results
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= url('other-entries') ?>" class="nav-link text-white <?= isActive('other-entries') ?>">
          <i class="bi bi-collection me-2"></i>Other Entries
        </a>
      </li>
      <?php endif; ?>
      <li class="nav-item">
        <a href="<?= url('epics') ?>" class="nav-link text-white <?= isActive('epics') ?>">
          <i class="bi bi-lightning-fill me-2"></i>Epics
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('quick_capture')): ?>
      <li class="nav-item">
        <a href="<?= url('test-customers') ?>" class="nav-link text-white <?= isActive('test-customers') ?>">
          <i class="bi bi-people me-2"></i>Testkunden
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('eight_d')): ?>
      <li class="nav-item">
        <a href="<?= url('8d') ?>" class="nav-link text-white <?= isActive('8d') ?>">
          <i class="bi bi-diagram-3-fill me-2"></i>8D-Berichte
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('quick_capture')): ?>
      <li class="nav-item">
        <a href="<?= url('feedback') ?>" class="nav-link text-white <?= isActive('feedback') || isActive('quick-captures') ?>">
          <i class="bi bi-chat-left-text me-2"></i>Feedback
          <?php
            $qcPending = 0; $tcPending = 0;
            try { $qcPending = (int)(Database::fetchOne("SELECT COUNT(*) c FROM quick_captures WHERE status='pending'")['c'] ?? 0); } catch (\Throwable $e) {}
            try { $tcPending = (int)(Database::fetchOne("SELECT COUNT(*) c FROM test_customer_feedback WHERE status='pending'")['c'] ?? 0); } catch (\Throwable $e) {} // only pending = new
            $totalPending = $qcPending + $tcPending;
          ?>
          <?php if ($totalPending > 0): ?><span class="badge bg-danger ms-1"><?= $totalPending ?></span><?php endif; ?>
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('sprint')): ?>
      <li class="nav-item">
        <a href="<?= url('sprints') ?>" class="nav-link text-white <?= isActive('sprints') ?>">
          <i class="bi bi-lightning-charge me-2"></i>Sprints
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('kanban')): ?>
      <li class="nav-item">
        <a href="<?= url('kanban') ?>" class="nav-link text-white <?= isActive('kanban') ?>">
          <i class="bi bi-kanban me-2"></i>Kanban
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('projects')): ?>
      <li class="nav-item">
        <a href="<?= url('projects') ?>" class="nav-link text-white <?= isActive('projects') ?>">
          <i class="bi bi-folder me-2"></i>Projects
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('reports')): ?>
      <li class="nav-item">
        <a href="<?= url('reports') ?>" class="nav-link text-white <?= isActive('reports') ?>">
          <i class="bi bi-bar-chart-line me-2"></i>Reports
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('confluence')): ?>
      <li class="nav-item">
        <a href="<?= url('confluence') ?>" class="nav-link text-white <?= isActive('confluence') ?>">
          <i class="bi bi-cloud-upload me-2"></i>Confluence
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canTestRequests() && Auth::canView('test_requests')): ?>
      <li class="nav-item">
        <a href="<?= url('test-requests') ?>" class="nav-link text-white <?= isActive('test-requests') ?>">
          <i class="bi bi-send me-2"></i>Test Requests
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('synapse')): ?>
      <li class="nav-item">
        <a href="<?= url('synapse') ?>" class="nav-link text-white <?= isActive('synapse') ?>">
          <i class="bi bi-clipboard2-check me-2"></i>SynapseRT
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('testing')): ?>
      <li class="nav-item">
        <a href="<?= url('test-plans') ?>" class="nav-link text-white <?= isActive('test-plans') ?>">
          <i class="bi bi-clipboard-check me-2"></i>Test Plans
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= url('test-cycles') ?>" class="nav-link text-white <?= isActive('test-cycles') ?>">
          <i class="bi bi-arrow-repeat me-2"></i>Test Cycles
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('inventory')): ?>
      <li class="nav-item">
        <a href="<?= url('inventory') ?>" class="nav-link text-white <?= isActive('inventory') ?>">
          <i class="bi bi-box-seam me-2"></i>Inventory
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('inventory')): ?>
      <li class="nav-item">
        <a href="<?= url('robots') ?>" class="nav-link text-white <?= isActive('robots') ?>">
          <i class="bi bi-cpu me-2"></i>Robot History
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('testing')): ?>
      <li class="nav-item">
        <a href="<?= url('test-sessions') ?>" class="nav-link text-white <?= isActive('test-sessions') ?>">
          <i class="bi bi-play-btn me-2"></i>Test Sessions
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('test_areas')): ?>
      <li class="nav-item">
        <a href="<?= url('test-areas') ?>" class="nav-link text-white <?= isActive('test-areas') ?>">
          <i class="bi bi-map me-2"></i>Test Areas
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('requirements')): ?>
      <li class="nav-item">
        <a href="<?= url('requirements') ?>" class="nav-link text-white <?= isActive('requirements') ?>">
          <i class="bi bi-list-check me-2"></i>Requirements
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('todos')): ?>
      <li class="nav-item">
        <a href="<?= url('todos') ?>" class="nav-link text-white <?= isActive('todos') ?>">
          <i class="bi bi-check2-square me-2"></i>Todos
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('search')): ?>
      <li class="nav-item">
        <a href="<?= url('search') ?>" class="nav-link text-white <?= isActive('search') ?>">
          <i class="bi bi-search me-2"></i>Search
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::canView('tags')): ?>
      <li class="nav-item">
        <a href="<?= url('tags/manage') ?>" class="nav-link text-white <?= isActive('tags') ?>">
          <i class="bi bi-tags me-2"></i>Tags
        </a>
      </li>
      <?php endif; ?>
      <?php if (Auth::isAdmin()): ?>
      <li class="mt-2"><small class="text-muted px-2 text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Admin</small></li>
      <li class="nav-item">
        <a href="<?= url('admin') ?>" class="nav-link text-white <?= isActive('admin') ?>">
          <i class="bi bi-gear me-2"></i>Settings
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= url('admin/users') ?>" class="nav-link text-white <?= isActive('admin/users') ?>">
          <i class="bi bi-people me-2"></i>Users
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= url('admin/groups') ?>" class="nav-link text-white <?= isActive('admin/groups') ?>">
          <i class="bi bi-person-badge me-2"></i>Groups
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= url('migrate') ?>" class="nav-link text-white <?= isActive('migrate') ?>">
          <i class="bi bi-arrow-left-right me-2"></i>Migration
        </a>
      </li>
      <?php endif; ?>
    </ul>
    <div class="border-top border-secondary p-2">
      <div class="dropdown">
        <a href="#" class="d-flex align-items-center gap-2 text-white text-decoration-none dropdown-toggle px-2 py-1 rounded" data-bs-toggle="dropdown">
          <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;min-width:32px">
            <span class="fw-bold" style="font-size:.8rem"><?= strtoupper(substr(Auth::user()['name'] ?? 'U', 0, 1)) ?></span>
          </div>
          <div class="text-truncate" style="max-width:130px">
            <div class="fw-semibold" style="font-size:.85rem"><?= e(Auth::user()['name'] ?? '') ?></div>
            <div class="text-muted" style="font-size:.7rem"><?= e(Auth::user()['role'] ?? '') ?></div>
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark mb-1">
          <li><a class="dropdown-item" href="<?= url('tool-feedback/new') ?>">
                <i class="bi bi-chat-left-text me-2"></i>Submit Feedback
              </a>
              <a class="dropdown-item" href="<?= url('tool-feedback') ?>">
                <i class="bi bi-inbox me-2"></i>My Feedback
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?= url('profile') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <form method="POST" action="<?= url('logout') ?>">
              <?= csrfField() ?>
              <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-left me-2"></i>Sign Out</button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Main content -->
  <div id="content" class="flex-grow-1 d-flex flex-column min-vh-100">
    <!-- Top bar (fixed) -->
    <header id="topbar" class="bg-dark border-bottom border-secondary px-3 py-2 d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
        <i class="bi bi-list"></i>
      </button>
      <h5 class="mb-0 fw-semibold text-truncate" style="max-width:40vw"><?= e($title ?? 'Dashboard') ?></h5>
      <div class="ms-auto d-flex gap-2 align-items-center">
        <?php
          // Global project filter indicator
          $gFilter = Auth::globalProjectFilter();
          $gFilterActive = $gFilter !== null && count($gFilter) > 0;
          $gFilterProjects = [];
          if ($gFilterActive) {
              $ph = implode(',', array_fill(0, count($gFilter), '?'));
              $gFilterProjects = Database::fetchAll("SELECT id, name, color FROM projects WHERE id IN ($ph) ORDER BY name", $gFilter);
          }
          // All accessible projects for the dropdown
          [$pSql, $pParams] = Auth::projectAccessClause('p');
          $gAllProjects = Database::fetchAll("SELECT id, name, color FROM projects p WHERE $pSql AND status='active' ORDER BY name", $pParams);
        ?>
        <div class="dropdown">
          <button class="btn btn-sm <?= $gFilterActive ? 'btn-warning' : 'btn-outline-secondary' ?> d-flex align-items-center gap-1"
                  data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Project Filter">
            <i class="bi bi-funnel<?= $gFilterActive ? '-fill' : '' ?>"></i>
            <?php if ($gFilterActive): ?>
            <span class="d-none d-md-inline" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= count($gFilterProjects) === 1 ? e($gFilterProjects[0]['name']) : count($gFilterProjects) . ' Projects' ?>
            </span>
            <span class="badge bg-dark text-warning ms-1" style="font-size:.65rem"><?= count($gFilterProjects) ?></span>
            <?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow p-0" style="min-width:260px;max-height:480px;overflow:hidden">
            <div style="display:flex;flex-direction:column;max-height:480px">
            <div class="px-3 py-2 border-bottom border-secondary d-flex align-items-center justify-content-between">
              <span class="fw-semibold small"><i class="bi bi-funnel me-1"></i>Project Filter</span>
              <?php if ($gFilterActive): ?>
              <button class="btn btn-outline-danger btn-sm py-0 px-2" onclick="clearGlobalFilter('<?= e(Auth::csrfToken()) ?>')" title="Clear filter">
                <i class="bi bi-x-lg" style="font-size:.7rem"></i> All Projects
              </button>
              <?php endif; ?>
            </div>
            <div class="px-2 pt-2 pb-1">
              <input type="text" id="gfSearch" class="form-control form-control-sm bg-dark border-secondary text-white"
                     placeholder="Search projects..." oninput="gfFilterList(this.value)">
            </div>
            <div style="overflow-y:auto;max-height:280px" id="gfProjectList">
              <?php foreach ($gAllProjects as $gp):
                $isSelected = $gFilterActive && in_array((int)$gp['id'], $gFilter ?? []);
              ?>
              <div class="gf-project-item d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                   data-id="<?= $gp['id'] ?>" data-name="<?= e(strtolower($gp['name'])) ?>"
                   style="cursor:pointer" onclick="gfToggleProject(<?= $gp['id'] ?>)">
                <div class="flex-shrink-0" style="width:10px;height:10px;border-radius:50%;background:<?= e($gp['color'] ?? '#6c757d') ?>"></div>
                <span class="flex-grow-1 small"><?= e($gp['name']) ?></span>
                <i class="bi bi-check-lg text-warning <?= $isSelected ? '' : 'invisible' ?>" id="gf-check-<?= $gp['id'] ?>"></i>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="px-3 py-2 border-top border-secondary d-flex gap-2 flex-shrink-0">
              <button class="btn btn-warning btn-sm flex-grow-1" onclick="applyGlobalFilter('<?= e(Auth::csrfToken()) ?>')">
                <i class="bi bi-check-lg me-1"></i>Apply
              </button>
              <button class="btn btn-outline-secondary btn-sm" onclick="clearGlobalFilter('<?= e(Auth::csrfToken()) ?>')">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            </div>
          </div>
        </div>
        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#quickCaptureModal" title="Quick Capture">
          <i class="bi bi-camera me-1"></i><span class="d-none d-md-inline">Capture</span>
        </button>
        <a href="<?= url('entries/create') ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">New Entry</span><span class="d-sm-none">New</span>
        </a>
        <!-- User menu ? visible on mobile where sidebar bottom is hard to reach -->
        <div class="dropdown d-md-none">
          <button class="border-0 p-0 bg-transparent dropdown-toggle-no-caret" data-bs-toggle="dropdown" aria-expanded="false" style="line-height:1">
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;min-width:32px">
              <span class="fw-bold text-white" style="font-size:.8rem"><?= strtoupper(substr(Auth::user()['name'] ?? 'U', 0, 1)) ?></span>
            </div>
          </button>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow" style="min-width:180px">
            <li class="px-3 py-2">
              <div class="fw-semibold" style="font-size:.85rem"><?= e(Auth::user()['name'] ?? '') ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= e(Auth::user()['role'] ?? '') ?></div>
            </li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item" href="<?= url('tool-feedback/new') ?>">
                <i class="bi bi-chat-left-text me-2"></i>Submit Feedback
              </a>
              <a class="dropdown-item" href="<?= url('tool-feedback') ?>">
                <i class="bi bi-inbox me-2"></i>My Feedback
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?= url('profile') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
            <li>
              <form method="POST" action="<?= url('logout') ?>">
                <?= csrfField() ?>
                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-left me-2"></i>Sign Out</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <!-- Quick Capture Modal -->
    <div class="modal fade" id="quickCaptureModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
          <div class="modal-header border-secondary py-2">
            <h6 class="modal-title mb-0"><i class="bi bi-camera me-2 text-info"></i>Quick Capture</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <p class="text-muted small mb-4 text-center">Take a photo or video ? a draft entry is created and you fill in the details afterwards.</p>
            <form method="POST" action="<?= url('entries/quick-capture') ?>" enctype="multipart/form-data" id="qcForm">
              <?= csrfField() ?>
              <input type="file" id="qcCamera" name="file" accept="image/*,video/*" capture="environment"
                     style="position:absolute;opacity:0;width:1px;height:1px" onchange="document.getElementById('qcForm').submit()">
              <input type="file" id="qcLibrary" name="file" accept="image/*,video/*"
                     style="position:absolute;opacity:0;width:1px;height:1px" onchange="document.getElementById('qcForm').submit()">
              <div class="d-grid gap-2">
                <label for="qcCamera" class="btn btn-info btn-lg">
                  <i class="bi bi-camera fs-5 me-2"></i>Take Photo / Video
                </label>
                <label for="qcLibrary" class="btn btn-outline-secondary">
                  <i class="bi bi-folder2-open me-2"></i>Choose from Library
                </label>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Page content (flash messages live inside main so they clear the fixed topbar) -->
    <main class="flex-grow-1 p-4">
      <?php $success = getFlash('success'); $error = getFlash('error'); $warning = getFlash('warning'); $info = getFlash('info'); ?>
      <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>
      <?php if ($warning): ?>
      <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= e($warning) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>
      <?php if ($info): ?>
      <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-info-circle me-2"></i><?= e($info) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>
      <?php if (!Auth::isAdmin() && !Auth::hasAnyAccess()): ?>
      <div class="d-flex flex-column align-items-center justify-content-center text-center py-5" style="min-height:50vh">
        <div class="mb-4" style="font-size:3rem;opacity:.3"><i class="bi bi-shield-lock"></i></div>
        <h4 class="mb-2">Kein Zugriff</h4>
        <p class="text-muted mb-4" style="max-width:420px">
          Deinem Account sind noch keine Berechtigungen zugewiesen.<br>
          Bitte wende dich an einen Administrator.
        </p>
        <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-house me-1"></i>Zum Dashboard
        </a>
      </div>
      <?php else: ?>
      <?= $content ?>
      <?php endif; ?>
    </main>
  </div>
</div>

<div class="toast-container position-fixed end-0 p-3" id="toastContainer" style="z-index:9999;top:56px"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($scripts)): ?>
<?= $scripts ?>
<?php endif; ?>
<script>
if ('serviceWorker' in navigator) {
  // updateViaCache: 'none' + an explicit update() call on every load make sure
  // browsers don't keep serving a stale cached sw.js — without this, fixes to
  // the service worker itself can silently not reach users for up to 24h
  // (the browser's default SW update-check throttle).
  navigator.serviceWorker.register('<?= url('sw.js') ?>', { updateViaCache: 'none' })
    .then(reg => reg.update().catch(() => {}))
    .catch(() => {});
}
// Fire-and-forget bulk sync checks (throttled server-side to every 15 min).
// Each integration is checked independently so Zentao-only installs (no Jira
// configured) still get their automatic background status check.
<?php if (Auth::check()): ?>
<?php if (appSetting('jira_url')): ?>
fetch('<?= url('api/jira-sync/bulk-check') ?>', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'_csrf=<?= Auth::csrfToken() ?>'}).catch(()=>{});
<?php endif; ?>
<?php if (!empty(appSetting('zentao_url')) && !empty(appSetting('zentao_token'))): ?>
fetch('<?= url('api/zentao-sync/bulk-check') ?>', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'_csrf=<?= Auth::csrfToken() ?>'}).catch(()=>{});
<?php endif; ?>
<?php endif; ?>
</script>

<script>
(function() {
  /* RoboDoc back-button history stack (sessionStorage) */
  var STACK_KEY = 'rd_nav_stack';
  var MAX = 25;

  function getStack() {
    try { return JSON.parse(sessionStorage.getItem(STACK_KEY) || '[]'); } catch(e) { return []; }
  }
  function saveStack(s) {
    sessionStorage.setItem(STACK_KEY, JSON.stringify(s.slice(-MAX)));
  }

  /* Push current URL on every page load except browser back/forward */
  var navType = '';
  try { navType = performance.getEntriesByType('navigation')[0].type; } catch(e) {}
  if (navType !== 'back_forward') {
    var stack = getStack();
    var cur = location.href;
    if (stack[stack.length - 1] !== cur) { stack.push(cur); saveStack(stack); }
  }

  function goBack(fallback) {
    var stack = getStack();
    var cur   = location.href;
    var target = null;
    /* pop until we find a URL different from current */
    while (stack.length > 0) {
      var candidate = stack[stack.length - 1];
      stack.pop();
      if (candidate !== cur) { target = candidate; break; }
    }
    saveStack(stack);
    if (target) { location.href = target; }
    else if (fallback && fallback !== '#') { location.href = fallback; }
    else { history.back(); }
  }

  function wireBackButtons() {
    document.querySelectorAll('.rd-back').forEach(function(btn) {
      /* avoid double-wiring */
      if (btn.dataset.rdWired) return;
      btn.dataset.rdWired = '1';
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        goBack(btn.dataset.fallback || btn.getAttribute('href') || '#');
      });
      /* tooltip showing destination */
      var stack = getStack();
      var cur   = location.href;
      var prevItems = stack.filter(function(u) { return u !== cur; });
      var prev = prevItems[prevItems.length - 1];
      if (prev) {
        try {
          var url  = new URL(prev);
          var path = decodeURIComponent(url.pathname).replace(/^\/robodoc2/, '');
          btn.title = '<- ' + (path || '/');
        } catch(e) {}
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireBackButtons);
  } else {
    wireBackButtons();
  }
  /* also wire after turbo/htmx style navigation if ever added */
  document.addEventListener('DOMContentLoaded', wireBackButtons);
})();
</script>
<script>
// Global Project Filter
var _gfSelected = new Set([<?php echo implode(',', $gFilter ?? []) ?>]);

function gfToggleProject(id) {
  if (_gfSelected.has(id)) { _gfSelected.delete(id); }
  else { _gfSelected.add(id); }
  var check = document.getElementById('gf-check-' + id);
  if (check) check.classList.toggle('invisible', !_gfSelected.has(id));
}

function gfFilterList(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.gf-project-item').forEach(function(el) {
    el.style.display = !q || el.dataset.name.includes(q) ? '' : 'none';
  });
}

function applyGlobalFilter(csrf) {
  var ids = Array.from(_gfSelected);
  var body = new URLSearchParams();
  body.append('_csrf', csrf);
  if (ids.length === 0) {
    body.append('project_ids', 'all');
  } else {
    ids.forEach(function(id) { body.append('project_ids[]', id); });
  }
  fetch('<?= url("global-filter/set") ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: body
  })
  .then(r => r.json())
  .then(d => { if (d.success) location.reload(); });
}

function clearGlobalFilter(csrf) {
  fetch('<?= url("global-filter/clear") ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf})
  })
  .then(r => r.json())
  .then(d => { if (d.success) location.reload(); });
}
</script>
</body>
</html>