<div class="d-flex align-items-center gap-3 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-sessions') ?>"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1 min-width-0">
    <h4 class="mb-0 text-truncate"><?= e($session['title']) ?></h4>
    <small class="text-muted">
      <?= e(formatDate($session['started_at'])) ?>
      <?php if ($session['ended_at']): ?>
      – <?= e(formatDate($session['ended_at'])) ?>
      <?php endif; ?>
    </small>
  </div>
  <div class="d-flex gap-2 flex-shrink-0">
    <?php if ($activeId == $session['id']): ?>
    <form method="POST" action="<?= url('test-sessions/' . $session['id'] . '/deactivate') ?>">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-pause-circle me-1"></i>Deactivate</button>
    </form>
    <?php elseif ($session['status'] === 'active'): ?>
    <form method="POST" action="<?= url('test-sessions/' . $session['id'] . '/activate') ?>">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-play-circle me-1"></i>Set Active</button>
    </form>
    <?php endif; ?>
    <?php if ($session['status'] === 'active'): ?>
    <form method="POST" action="<?= url('test-sessions/' . $session['id'] . '/complete') ?>">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-check-circle me-1"></i>Complete</button>
    </form>
    <?php endif; ?>
    <a href="<?= url('test-sessions/' . $session['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-download me-1"></i>Export
      </button>
      <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
        <li><a class="dropdown-item" href="<?= url('test-sessions/' . $session['id'] . '/export?format=pdf') ?>" target="_blank">
          <i class="bi bi-file-pdf me-2"></i>PDF (Print)
        </a></li>
        <li><a class="dropdown-item" href="<?= url('test-sessions/' . $session['id'] . '/export?format=word') ?>">
          <i class="bi bi-file-word me-2"></i>Word (.doc)
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="exportConfluence(<?= $session['id'] ?>); return false">
          <i class="bi bi-cloud-upload me-2"></i>Confluence
        </a></li>
      </ul>
    </div>
  </div>
</div>

<?php if ($activeId == $session['id']): ?>
<div class="alert alert-success small mb-4">
  <i class="bi bi-play-circle-fill me-2"></i>
  <strong>This session is active.</strong> New entries you create will be automatically linked to this session.
</div>
<?php endif; ?>

<!-- Meta row -->
<div class="row g-3 mb-4">
  <?php if ($session['firmware_version']): ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:#6366f1">
      <div class="card-body p-3">
        <div class="text-muted small">Firmware</div>
        <div class="fw-semibold"><?= e($session['firmware_version']) ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($session['app_version']): ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:#0ea5e9">
      <div class="card-body p-3">
        <div class="text-muted small">App Version</div>
        <div class="fw-semibold"><?= e($session['app_version']) ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($area): ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:#10b981">
      <div class="card-body p-3">
        <div class="text-muted small">Test Area</div>
        <div class="fw-semibold text-truncate"><?= e($area['name']) ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:#f59e0b">
      <div class="card-body p-3">
        <div class="text-muted small">Entries</div>
        <div class="stat-number"><?= count($entries) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-8">

    <!-- Entry list -->
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small d-flex align-items-center justify-content-between">
        <span><i class="bi bi-journal-text me-2"></i>Entries <span class="badge bg-secondary ms-1"><?= count($entries) ?></span></span>
        <?php if (!empty($testCycleLinked)): ?>
        <a href="<?= url('entries/create') ?>?form=test-result&project_id=<?= $session['project_id'] ?>&session_id=<?= $session['id'] ?>"
           class="btn btn-sm btn-info">
          <i class="bi bi-clipboard2-check me-1"></i>Add Test Result Entry
        </a>
        <?php else: ?>
        <a href="<?= url('entries/create') ?>?project_id=<?= $session['project_id'] ?>"
           class="btn btn-sm btn-outline-primary">
          <i class="bi bi-plus-lg me-1"></i>Add Entry
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if (!$entries): ?>
        <p class="text-muted text-center py-4 small">No entries linked to this session yet.</p>
        <?php endif; ?>
        <?php foreach ($entries as $ent): ?>
        <a href="<?= url('entries/' . $ent['id']) ?>"
           class="d-flex align-items-start gap-3 px-3 py-2 border-bottom border-secondary text-decoration-none text-white entry-row">
          <div class="flex-shrink-0 mt-1">
            <?php if ($ent['type_color']): ?>
            <span class="badge" style="background:<?= e($ent['type_color']) ?>;font-size:.65rem"><?= e($ent['type_name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="flex-grow-1 min-width-0">
            <div class="fw-semibold text-truncate" style="font-size:.875rem">
              <?= e($ent['title'] ?: substr($ent['description'], 0, 80)) ?>
            </div>
            <div class="text-muted" style="font-size:.75rem">
              <?= e(formatDate($ent['entry_date'])) ?>
              <?php if ($ent['mower_serial']): ?>&middot; <i class="bi bi-cpu me-1"></i><?= e($ent['mower_serial']) ?><?php endif; ?>
              <?php if ($ent['firmware_version']): ?>&middot; <?= e($ent['firmware_version']) ?><?php endif; ?>
              <?php if ($ent['weather_condition'] || $ent['temperature'] !== null): ?>
              &middot; <i class="bi bi-cloud me-1"></i><?= e($ent['weather_condition'] ?? '') ?><?= $ent['temperature'] !== null ? ' ' . $ent['temperature'] . '°C' : '' ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="flex-shrink-0">
            <span class="badge bg-<?= $ent['status'] === 'finalized' ? 'success' : ($ent['status'] === 'ongoing' ? 'warning text-dark' : 'secondary') ?>" style="font-size:.6rem">
              <?= e($ent['status']) ?>
            </span>
            <?php if ($ent['att_count']): ?>
            <div class="text-muted mt-1" style="font-size:.7rem"><i class="bi bi-paperclip"></i> <?= $ent['att_count'] ?></div>
            <?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
  <div class="col-md-4">

    <!-- Info card -->
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Details</div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-5 text-muted">Status</dt>
          <dd class="col-7">
            <span class="badge bg-<?= $session['status'] === 'completed' ? 'success' : 'warning text-dark' ?>"><?= e($session['status']) ?></span>
          </dd>
          <?php if ($project): ?>
          <dt class="col-5 text-muted">Project</dt>
          <dd class="col-7 text-truncate"><?= e($project['name']) ?></dd>
          <?php endif; ?>
          <?php if (!empty($testCycleLinked)): ?>
          <dt class="col-5 text-muted">Test Cycle</dt>
          <dd class="col-7">
            <a href="<?= url('test-cycles/'.$testCycleLinked['id']) ?>" class="text-decoration-none">
              <?= e($testCycleLinked['plan_name'] ? $testCycleLinked['plan_name'].' › ' : '') ?><?= e($testCycleLinked['name']) ?>
            </a>
          </dd>
          <?php endif; ?>
          <?php if (!empty($testCaseLinked)): ?>
          <dt class="col-5 text-muted">Test Case</dt>
          <dd class="col-7"><?= e($testCaseLinked['name'] ?? 'Test Case #'.$testCaseLinked['id']) ?></dd>
          <?php endif; ?>
          <?php if ($area): ?>
          <dt class="col-5 text-muted">Area</dt>
          <dd class="col-7">
            <a href="<?= url('test-areas/' . $area['id']) ?>" class="text-decoration-none"><?= e($area['name']) ?></a>
          </dd>
          <?php endif; ?>
          <?php if ($session['weather_condition']): ?>
          <dt class="col-5 text-muted">Weather</dt>
          <dd class="col-7"><?= e($session['weather_condition']) ?></dd>
          <?php endif; ?>
          <?php if ($session['temperature'] !== null): ?>
          <dt class="col-5 text-muted">Temperature</dt>
          <dd class="col-7"><?= e($session['temperature']) ?> °C</dd>
          <?php endif; ?>
        </dl>
        <?php if ($session['description']): ?>
        <hr class="border-secondary">
        <p class="small text-muted mb-0"><?= nl2br(e($session['description'])) ?></p>
        <?php endif; ?>
        <?php if ($session['terrain_notes']): ?>
        <hr class="border-secondary">
        <div class="small text-muted"><strong>Terrain:</strong> <?= nl2br(e($session['terrain_notes'])) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Type breakdown -->
    <?php if ($byType): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">By Type</div>
      <div class="card-body">
        <?php foreach ($byType as $typeName => $cnt): ?>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="small flex-grow-1"><?= e($typeName) ?></span>
          <span class="badge bg-secondary"><?= $cnt ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Danger zone -->
    <div class="card border-danger">
      <div class="card-body">
        <form method="POST" action="<?= url('test-sessions/' . $session['id'] . '/delete') ?>"
              onsubmit="return confirm('Delete this session? Entries will not be deleted but will lose the session link.')">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-outline-danger btn-sm w-100">
            <i class="bi bi-trash me-1"></i>Delete Session
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

<!-- Confluence export modal -->
<div class="modal fade" id="confluenceModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h6 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Export to Confluence</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Space Key</label>
          <input type="text" id="confluenceSpace" class="form-control form-control-sm" placeholder="e.g. PROJ">
        </div>
        <div class="mb-3">
          <label class="form-label small">Parent Page ID (optional)</label>
          <input type="text" id="confluenceParent" class="form-control form-control-sm" placeholder="e.g. 123456">
        </div>
        <div id="confluenceResult"></div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="doExportConfluence()">
          <i class="bi bi-cloud-upload me-1"></i>Export
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function exportConfluence(id) {
  new bootstrap.Modal(document.getElementById('confluenceModal')).show();
  document.getElementById('confluenceResult').innerHTML = '';
}

function doExportConfluence() {
  const btn = event.target;
  btn.disabled = true;
  const body = new FormData();
  body.append('_csrf', document.querySelector('meta[name="csrf-token"]').content);
  body.append('space_key', document.getElementById('confluenceSpace').value);
  body.append('parent_id', document.getElementById('confluenceParent').value);

  fetch('<?= url('test-sessions/' . $session['id'] . '/export?format=confluence') ?>', {method:'POST', body})
    .then(r => r.json())
    .then(d => {
      const el = document.getElementById('confluenceResult');
      if (d.success) {
        el.innerHTML = '<div class="alert alert-success small">Published! <a href="'+d.url+'" target="_blank">Open page</a></div>';
      } else {
        el.innerHTML = '<div class="alert alert-danger small">' + (d.error || 'Export failed') + '</div>';
      }
    })
    .catch(() => document.getElementById('confluenceResult').innerHTML = '<div class="alert alert-danger small">Network error</div>')
    .finally(() => btn.disabled = false);
}
</script>
