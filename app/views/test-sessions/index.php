<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-0">Test Sessions</h4>
    <small class="text-muted">Group entries with shared metadata (firmware, area, weather)</small>
  </div>
  <a href="<?= url('test-sessions/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Session
  </a>
</div>

<?php if ($activeId): ?>
<?php $activeSession = array_values(array_filter($sessions, fn($s) => $s['id'] == $activeId))[0] ?? null; ?>
<?php if ($activeSession): ?>
<div class="alert alert-success d-flex align-items-center gap-3 mb-4">
  <i class="bi bi-play-circle-fill fs-4"></i>
  <div class="flex-grow-1">
    <strong>Active Session:</strong> <?= e($activeSession['title']) ?>
    <?php if ($activeSession['firmware_version']): ?>
    &middot; <span class="badge bg-primary"><?= e($activeSession['firmware_version']) ?></span>
    <?php endif; ?>
    — New entries are automatically linked to this session.
  </div>
  <form method="POST" action="<?= url('test-sessions/' . $activeSession['id'] . '/deactivate') ?>">
    <?= csrfField() ?>
    <button type="submit" class="btn btn-sm btn-outline-light">Deactivate</button>
  </form>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!$sessions): ?>
<div class="card"><div class="card-body text-muted text-center py-5">
  <i class="bi bi-play-circle fs-1 d-block mb-2 opacity-25"></i>
  No sessions yet. Create a session to group entries and export reports.
</div></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($sessions as $s): ?>
<?php $isActive = ($s['id'] == $activeId); ?>
<div class="col-md-6 col-xl-4">
  <div class="card h-100 <?= $isActive ? 'border-success' : '' ?>">
    <?php if ($isActive): ?>
    <div class="card-header bg-success bg-opacity-25 border-success py-1 small">
      <i class="bi bi-play-circle-fill me-1 text-success"></i><strong class="text-success">Active</strong>
    </div>
    <?php endif; ?>
    <div class="card-body">
      <div class="d-flex align-items-start justify-content-between mb-2">
        <h6 class="mb-0 text-truncate"><?= e($s['title']) ?></h6>
        <div class="dropdown">
          <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= url('test-sessions/' . $s['id']) ?>"><i class="bi bi-eye me-2"></i>View</a></li>
            <li><a class="dropdown-item" href="<?= url('test-sessions/' . $s['id'] . '/edit') ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
            <li><hr class="dropdown-divider"></li>
            <?php if (!$isActive): ?>
            <li>
              <form method="POST" action="<?= url('test-sessions/' . $s['id'] . '/activate') ?>">
                <?= csrfField() ?>
                <button class="dropdown-item text-success"><i class="bi bi-play-circle me-2"></i>Set Active</button>
              </form>
            </li>
            <?php endif; ?>
            <?php if ($s['status'] === 'active'): ?>
            <li>
              <form method="POST" action="<?= url('test-sessions/' . $s['id'] . '/complete') ?>">
                <?= csrfField() ?>
                <button class="dropdown-item text-warning"><i class="bi bi-check-circle me-2"></i>Complete</button>
              </form>
            </li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="POST" action="<?= url('test-sessions/' . $s['id'] . '/delete') ?>" onsubmit="return confirm('Delete session?')">
                <?= csrfField() ?>
                <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
              </form>
            </li>
          </ul>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 mb-2">
        <span class="badge bg-<?= $s['status'] === 'completed' ? 'success' : 'warning text-dark' ?>"><?= e($s['status']) ?></span>
        <?php if ($s['firmware_version']): ?>
        <span class="badge bg-primary" style="font-size:.7rem"><i class="bi bi-code-slash me-1"></i><?= e($s['firmware_version']) ?></span>
        <?php endif; ?>
        <?php if ($s['entry_count']): ?>
        <span class="badge bg-secondary"><i class="bi bi-journal-text me-1"></i><?= $s['entry_count'] ?></span>
        <?php endif; ?>
      </div>

      <div class="text-muted small">
        <?php if ($s['area_name']): ?>
        <div><i class="bi bi-map me-1"></i><?= e($s['area_name']) ?></div>
        <?php endif; ?>
        <?php if ($s['project_name']): ?>
        <div><i class="bi bi-folder me-1"></i><?= e($s['project_name']) ?></div>
        <?php endif; ?>
        <?php if ($s['weather_condition']): ?>
        <div><i class="bi bi-cloud me-1"></i><?= e($s['weather_condition']) ?><?= $s['temperature'] !== null ? ' · ' . $s['temperature'] . ' °C' : '' ?></div>
        <?php endif; ?>
        <div class="mt-1"><i class="bi bi-calendar3 me-1"></i><?= e(formatDate($s['started_at'])) ?></div>
      </div>
    </div>
    <div class="card-footer border-secondary py-2 d-flex gap-2">
      <a href="<?= url('test-sessions/' . $s['id']) ?>" class="btn btn-sm btn-outline-secondary flex-grow-1">View</a>
      <a href="<?= url('test-sessions/' . $s['id'] . '/export?format=pdf') ?>" class="btn btn-sm btn-outline-secondary" title="Export PDF"><i class="bi bi-file-pdf"></i></a>
      <a href="<?= url('test-sessions/' . $s['id'] . '/export?format=word') ?>" class="btn btn-sm btn-outline-secondary" title="Export Word"><i class="bi bi-file-word"></i></a>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
