<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-diagram-3-fill me-2 text-warning"></i>8D-Berichte</h5>
  <?php if (Auth::isAdmin() || Auth::canOwn('eight_d') || Auth::canEdit('eight_d')): ?>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#create8dModal">
    <i class="bi bi-plus-lg me-1"></i>Neuer 8D-Bericht
  </button>
  <?php endif; ?>
</div>

<form method="GET" action="<?= url('8d') ?>" class="card border-secondary p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1 text-muted">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Alle</option>
        <option value="open"   <?= $status === 'open'   ? 'selected' : '' ?>>Offen</option>
        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Abgeschlossen</option>
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label small mb-1 text-muted">Suche</label>
      <input type="text" name="search" class="form-control form-control-sm" placeholder="Referenz oder Titel..." value="<?= e($search) ?>">
    </div>
    <div class="col-auto d-flex gap-1">
      <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-search"></i></button>
      <?php if ($status || $search): ?>
      <a href="<?= url('8d') ?>" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-x"></i></a>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if (empty($reports)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-diagram-3-fill" style="font-size:3rem;opacity:.3"></i>
  <p class="mt-3">Noch keine 8D-Berichte. Erstelle einen neuen Bericht.</p>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($reports as $r): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card border-secondary h-100">
      <div class="card-header border-secondary d-flex align-items-center gap-2">
        <code class="text-info"><?= e($r['reference']) ?></code>
        <?php if ($r['project_name']): ?>
        <span class="badge" style="background:<?= e($r['project_color'] ?? '#666') ?>;font-size:.65rem"><?= e($r['project_name']) ?></span>
        <?php endif; ?>
        <span class="badge <?= $r['status'] === 'closed' ? 'bg-success' : 'bg-warning text-dark' ?> ms-auto">
          <?= $r['status'] === 'closed' ? 'Abgeschlossen' : 'Offen' ?>
        </span>
      </div>
      <div class="card-body">
        <h6 class="fw-semibold mb-2"><?= e($r['title']) ?></h6>
        <div class="d-flex gap-3 text-muted small flex-wrap">
          <span><i class="bi bi-person me-1"></i><?= e($r['creator_name'] ?? '?') ?></span>
          <span><i class="bi bi-calendar3 me-1"></i><?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
          <?php if ((int)$r['open_actions'] > 0): ?>
          <span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i><?= $r['open_actions'] ?> offene Maßnahme<?= $r['open_actions'] == 1 ? '' : 'n' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-footer border-secondary d-flex gap-2">
        <a href="<?= url('8d/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
          <i class="bi bi-arrow-right me-1"></i>Öffnen
        </a>
        <a href="<?= url('8d/' . $r['id'] . '/export') ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Exportieren">
          <i class="bi bi-file-earmark-arrow-down"></i>
        </a>
        <?php if (Auth::isAdmin() || Auth::canEdit('eight_d')): ?>
        <form method="POST" action="<?= url('8d/' . $r['id'] . '/delete') ?>" onsubmit="return confirm('8D-Bericht <?= e($r['reference']) ?> wirklich löschen?')">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create 8D Modal -->
<div class="modal fade" id="create8dModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Neuer 8D-Bericht</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= url('8d/create') ?>">
        <?= csrfField() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Titel <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required maxlength="255" placeholder="Kurzbeschreibung des Problems">
          </div>
          <div class="mb-3">
            <label class="form-label">Projekt <span class="text-muted">(optional)</span></label>
            <select name="project_id" class="form-select">
              <option value="">-- kein Projekt --</option>
              <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-text">Die eindeutige 8D-Referenznummer wird automatisch vergeben.</div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-primary">Erstellen</button>
        </div>
      </form>
    </div>
  </div>
</div>
