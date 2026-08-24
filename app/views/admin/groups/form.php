<?php $isEdit = $group !== null; ?>
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin/groups') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0"><?= $isEdit ? 'Gruppe bearbeiten: ' . e($group['name']) : 'Neue Zugriffsgruppe' ?></h5>
</div>

<form method="POST" action="<?= $isEdit ? url('admin/groups/' . $group['id'] . '/edit') : url('admin/groups/create') ?>">
  <?= csrfField() ?>
  <div class="row g-4">

    <!-- Col 1: Group details + permissions matrix -->
    <div class="col-lg-5">

      <!-- Group details -->
      <div class="card mb-3">
        <div class="card-header border-secondary fw-semibold small">Gruppendetails</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= e($group['name'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Beschreibung</label>
            <textarea name="description" class="form-control" rows="2"
                      placeholder="Wofür ist diese Gruppe?"><?= e($group['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Module permission matrix -->
      <div class="card mb-3">
        <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
          <i class="bi bi-shield-lock me-1"></i>Modul-Berechtigungen
          <span class="text-muted small fw-normal ms-1">— leer = kein Zugriff</span>
        </div>
        <div class="card-body p-0">
          <table class="table table-dark table-sm mb-0 align-middle" style="font-size:.82rem">
            <thead>
              <tr>
                <th class="ps-3">Modul</th>
                <th class="text-center" style="width:72px"><span class="text-info" title="Einträge anderer User sehen">Anzeigen</span></th>
                <th class="text-center" style="width:72px"><span class="text-success" title="Eigene anlegen &amp; bearbeiten">Eigene</span></th>
                <th class="text-center" style="width:72px"><span class="text-warning" title="Alle Einträge bearbeiten">Bearbeiten</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($modules as $slug => $label): ?>
              <?php
                $defaultView = $isEdit ? ($groupPerms[$slug]['view'] ?? false) : true;
                $defaultOwn  = $isEdit ? ($groupPerms[$slug]['own']  ?? false) : true;
                $defaultEdit = $isEdit ? ($groupPerms[$slug]['edit'] ?? false) : true;
              ?>
              <tr>
                <td class="ps-3"><?= e($label) ?></td>
                <td class="text-center">
                  <input type="checkbox" name="perm_view[<?= $slug ?>]" value="1"
                         class="form-check-input perm-view-cb" data-module="<?= $slug ?>"
                         <?= $defaultView ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                  <?php if (in_array($slug, ['entries', 'quick_capture'])): ?>
                  <input type="checkbox" name="perm_own[<?= $slug ?>]" value="1"
                         class="form-check-input perm-own-cb" data-module="<?= $slug ?>"
                         <?= $defaultOwn ? 'checked' : '' ?>>
                  <?php else: ?>
                  <span class="text-muted" style="font-size:.7rem">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <input type="checkbox" name="perm_edit[<?= $slug ?>]" value="1"
                         class="form-check-input perm-edit-cb" data-module="<?= $slug ?>"
                         <?= $defaultEdit ? 'checked' : '' ?>>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer border-secondary py-2 d-flex gap-3">
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAllPerms(true)">
            <i class="bi bi-check2-all me-1"></i>Alle aktivieren
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAllPerms(false)">
            <i class="bi bi-x-lg me-1"></i>Alle deaktivieren
          </button>
          <button type="button" class="btn btn-outline-info btn-sm ms-auto" onclick="setViewOnly()">
            <i class="bi bi-eye me-1"></i>Nur Lesen
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Änderungen speichern' : 'Gruppe erstellen' ?>
      </button>
    </div>

    <!-- Col 2: Members -->
    <div class="col-lg-3">
      <div class="card h-100">
        <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
          <i class="bi bi-people me-1"></i>Mitglieder
          <span class="badge bg-secondary ms-auto" id="memberCount"><?= count($memberIds) ?></span>
        </div>
        <div class="card-body p-0" style="max-height:520px;overflow-y:auto">
          <?php foreach ($users as $u): ?>
          <?php $checked = in_array($u['id'], $memberIds); ?>
          <label class="d-flex align-items-center gap-2 px-3 py-2 border-bottom border-secondary" style="cursor:pointer">
            <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>"
                   class="form-check-input m-0 member-cb" <?= $checked ? 'checked' : '' ?>>
            <span class="small"><?= e($u['name']) ?></span>
          </label>
          <?php endforeach; ?>
          <?php if (!$users): ?>
          <div class="text-muted small p-3">Noch keine aktiven Benutzer.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Col 3: Projects -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
          <i class="bi bi-folder me-1"></i>Erlaubte Projekte
          <span class="badge bg-secondary ms-auto" id="projectCount"><?= count($projectIds) ?></span>
        </div>
        <div class="card-body p-0" style="max-height:520px;overflow-y:auto">
          <?php foreach ($projects as $p): ?>
          <?php $checked = in_array($p['id'], $projectIds); ?>
          <?php $vis = $projectVis[$p['id']] ?? 'all'; ?>
          <div class="px-3 py-2 border-bottom border-secondary">
            <label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer">
              <input type="checkbox" name="project_ids[]" value="<?= $p['id'] ?>"
                     class="form-check-input m-0 project-cb" data-pid="<?= $p['id'] ?>"
                     <?= $checked ? 'checked' : '' ?>>
              <span class="small fw-semibold"><?= e($p['name']) ?></span>
            </label>
            <div class="project-vis-row mt-2 ms-4" id="vis-<?= $p['id'] ?>"
                 <?= $checked ? '' : 'style="display:none"' ?>>
              <div class="d-flex gap-3">
                <label class="d-flex align-items-center gap-1 small" style="cursor:pointer">
                  <input type="radio" name="project_vis[<?= $p['id'] ?>]" value="all"
                         class="form-check-input m-0" <?= $vis === 'all' ? 'checked' : '' ?>>
                  <span>Alle Einträge</span>
                </label>
                <label class="d-flex align-items-center gap-1 small" style="cursor:pointer">
                  <input type="radio" name="project_vis[<?= $p['id'] ?>]" value="own"
                         class="form-check-input m-0" <?= $vis === 'own' ? 'checked' : '' ?>>
                  <span class="text-warning">Nur eigene</span>
                </label>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$projects): ?>
          <div class="text-muted small p-3">Noch keine Projekte.</div>
          <?php endif; ?>
        </div>
        <div class="card-footer border-secondary py-2 small text-muted">
          <i class="bi bi-info-circle me-1"></i>
          „Nur eigene" zeigt auch Einträge die dem User zugewiesen wurden.
        </div>
      </div>
    </div>

  </div>
</form>

<script>
// Projekt-Checkboxen
document.querySelectorAll('.project-cb').forEach(cb => {
  cb.addEventListener('change', () => {
    const vis = document.getElementById('vis-' + cb.dataset.pid);
    if (vis) vis.style.display = cb.checked ? '' : 'none';
    document.getElementById('projectCount').textContent =
      document.querySelectorAll('.project-cb:checked').length;
  });
});

document.querySelectorAll('.member-cb').forEach(cb => {
  cb.addEventListener('change', () => {
    document.getElementById('memberCount').textContent =
      document.querySelectorAll('.member-cb:checked').length;
  });
});

// edit → own → view cascade
document.querySelectorAll('.perm-edit-cb').forEach(cb => {
  cb.addEventListener('change', () => {
    const m = cb.dataset.module;
    if (cb.checked) {
      const ownCb  = document.querySelector(`.perm-own-cb[data-module="${m}"]`);
      const viewCb = document.querySelector(`.perm-view-cb[data-module="${m}"]`);
      if (ownCb)  ownCb.checked  = true;
      if (viewCb) viewCb.checked = true;
    }
  });
});

document.querySelectorAll('.perm-own-cb').forEach(cb => {
  cb.addEventListener('change', () => {
    const m = cb.dataset.module;
    if (cb.checked) {
      const viewCb = document.querySelector(`.perm-view-cb[data-module="${m}"]`);
      if (viewCb) viewCb.checked = true;
    } else {
      const editCb = document.querySelector(`.perm-edit-cb[data-module="${m}"]`);
      if (editCb) editCb.checked = false;
    }
  });
});

document.querySelectorAll('.perm-view-cb').forEach(cb => {
  cb.addEventListener('change', () => {
    if (!cb.checked) {
      const m = cb.dataset.module;
      document.querySelector(`.perm-own-cb[data-module="${m}"]`).checked  = false;
      document.querySelector(`.perm-edit-cb[data-module="${m}"]`).checked = false;
    }
  });
});

function setAllPerms(val) {
  document.querySelectorAll('.perm-view-cb, .perm-own-cb, .perm-edit-cb').forEach(cb => cb.checked = val);
}

function setViewOnly() {
  document.querySelectorAll('.perm-view-cb').forEach(cb => cb.checked = true);
  document.querySelectorAll('.perm-own-cb, .perm-edit-cb').forEach(cb => cb.checked = false);
}
</script>
