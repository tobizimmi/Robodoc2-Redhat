<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0"><i class="bi bi-people me-2"></i>Testkunden Aufträge</h5>
  <div class="d-flex gap-2">
    <a href="<?= url('test-customers/feedback') ?>" class="btn btn-outline-info btn-sm">
      <i class="bi bi-chat-left-text me-1"></i>Gesamtes Feedback
    </a>
    <a href="<?= url('test-customers/customers') ?>" class="btn btn-outline-warning btn-sm">
      <i class="bi bi-people me-1"></i>Testkunden Verzeichnis
    </a>
    <a href="<?= url('test-customers/templates') ?>" class="btn btn-outline-info btn-sm">
      <i class="bi bi-file-text me-1"></i>Fragebogen Templates
    </a>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createOrderModal">
      <i class="bi bi-plus-lg me-1"></i>Neuer Auftrag
    </button>
  </div>
</div>

<div class="alert alert-secondary py-2 small mb-4 d-flex align-items-center gap-2">
  <i class="bi bi-info-circle text-info flex-shrink-0"></i>
  <span>
    <strong>Tipp:</strong> Erstelle zuerst
    <a href="<?= url('test-customers/templates') ?>" class="alert-link">Fragebogen Templates</a>
    die du bei jedem Auftrag wiederverwenden kannst.
    Templates findest du auch oben rechts über den Button <strong>Fragebogen Templates verwalten</strong>.
  </span>
</div>

<?php if (empty($orders)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-people" style="font-size:3rem;opacity:.3"></i>
  <p class="mt-3">Noch keine Testkunden Aufträge. Erstelle einen neuen Auftrag.</p>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($orders as $o): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card border-secondary h-100">
      <div class="card-header border-secondary d-flex align-items-center gap-2">
        <span class="badge" style="background:<?= e($o['project_color'] ?? '#666') ?>;font-size:.7rem">
          <?= e($o['project_name'] ?? '?') ?>
        </span>
        <span class="badge <?= $o['status'] === 'active' ? 'bg-success' : ($o['status'] === 'draft' ? 'bg-secondary' : 'bg-danger') ?> ms-auto">
          <?= e($o['status']) ?>
        </span>
      </div>
      <div class="card-body">
        <h6 class="fw-semibold mb-2"><?= e($o['title']) ?></h6>
        <?php if ($o['description']): ?>
        <p class="text-muted small mb-3"><?= e(mb_substr($o['description'], 0, 100)) ?><?= strlen($o['description']) > 100 ? '...' : '' ?></p>
        <?php endif; ?>
        <div class="d-flex gap-3 text-muted small">
          <span><i class="bi bi-chat-left-text me-1"></i><?= $o['feedback_count'] ?> Feedback
            <?php if ($o['pending_count'] > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $o['pending_count'] ?> neu</span>
            <?php endif; ?>
          </span>
          <span><i class="bi bi-list-check me-1"></i><?= $o['questionnaire_count'] ?> Fragebögen</span>
        </div>
      </div>
      <div class="card-footer border-secondary d-flex gap-2">
        <a href="<?= url('test-customers/' . $o['id']) ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
          <i class="bi bi-arrow-right me-1"></i>Öffnen
        </a>
        <form method="POST" action="<?= url('test-customers/' . $o['id'] . '/delete') ?>"
              onsubmit="return confirm('Auftrag löschen?')">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Order Modal -->
<div class="modal fade" id="createOrderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Neuer Testkunden Auftrag</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= url('test-customers/create') ?>">
        <?= csrfField() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Titel <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required maxlength="200">
          </div>
          <div class="mb-3">
            <label class="form-label">Projekt <span class="text-danger">*</span></label>
            <select name="project_id" class="form-select" required>
              <option value="">-- Projekt wählen --</option>
              <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Beschreibung</label>
            <textarea name="description" class="form-control" rows="3" maxlength="2000"></textarea>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-primary">Erstellen</button>
        </div>
      </form>
    </div>
  </div>
</div>
