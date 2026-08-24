<?php $csrf = Auth::csrfToken(); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0"><i class="bi bi-tags me-2"></i>Tags verwalten</h5>
</div>

<div class="row g-4">
<div class="col-lg-5">
  <div class="card">
    <div class="card-header border-secondary fw-semibold small">Neuer Tag</div>
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col"><input type="text" id="newTagName" class="form-control" placeholder="Name *"></div>
        <div class="col-auto d-flex align-items-center gap-2">
          <input type="color" id="newTagColor" class="form-control form-control-color" value="#6c757d" style="width:44px;height:38px">
          <button class="btn btn-success" onclick="createTag('<?= e($csrf) ?>')"><i class="bi bi-plus-lg me-1"></i>Add</button>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="col-lg-7">
  <div class="card">
    <div class="card-header border-secondary fw-semibold small">
      Alle Tags <span class="badge bg-secondary ms-1"><?= count($tags) ?></span>
    </div>
    <?php if ($tags): ?>
    <div class="list-group list-group-flush" id="tagList">
      <?php foreach ($tags as $tag): ?>
      <div class="list-group-item bg-dark border-secondary d-flex align-items-center gap-3 py-2" id="tag-row-<?= $tag['id'] ?>">
        <div class="rounded-circle flex-shrink-0" style="width:14px;height:14px;background:<?= e($tag['color']) ?>"></div>
        <span class="flex-grow-1 fw-semibold small" id="tag-name-<?= $tag['id'] ?>"><?= e($tag['name']) ?></span>
        <span class="badge bg-secondary" style="font-size:.65rem"><?= (int)$tag['entry_count'] ?> Eintraege</span>
        <div class="d-flex gap-1">
          <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="editTag(<?= $tag['id'] ?>,'<?= e(addslashes($tag['name'])) ?>','<?= e($tag['color']) ?>')" title="Bearbeiten">
            <i class="bi bi-pencil" style="font-size:.7rem"></i>
          </button>
          <button class="btn btn-outline-danger btn-sm py-0 px-2" onclick="deleteTag(<?= $tag['id'] ?>,'<?= e($csrf) ?>')" title="Loeschen">
            <i class="bi bi-trash" style="font-size:.7rem"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card-body text-muted small text-center py-4">Noch keine Tags. Ersten Tag anlegen.</div>
    <?php endif; ?>
  </div>
</div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editTagModal" tabindex="-1">
  <div class="modal-dialog modal-sm"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary py-2"><h6 class="modal-title">Tag bearbeiten</h6>
      <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="editTagId">
      <div class="mb-2"><input type="text" id="editTagName" class="form-control form-control-sm" placeholder="Name"></div>
      <input type="color" id="editTagColor" class="form-control form-control-color w-100" style="height:38px">
    </div>
    <div class="modal-footer border-secondary py-2">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-primary btn-sm" onclick="saveTag('<?= e($csrf) ?>')">Speichern</button>
    </div>
  </div></div>
</div>

<div id="tagToast" class="position-fixed bottom-0 end-0 m-3 p-3 rounded bg-dark border-secondary" style="display:none;z-index:9999;font-size:.82rem"></div>

<script>
function _toast(msg, ok) {
  const t = document.getElementById('tagToast');
  t.innerHTML = '<i class="bi bi-'+(ok?'check-circle text-success':'x-circle text-danger')+' me-2"></i>'+msg;
  t.style.display=''; clearTimeout(t._t); t._t=setTimeout(()=>t.style.display='none',4000);
}

function createTag(csrf) {
  const name  = document.getElementById('newTagName').value.trim();
  const color = document.getElementById('newTagColor').value;
  if (!name) return;
  const body = new URLSearchParams({_csrf:csrf, name, color});
  fetch('<?= url('tags') ?>', {method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body})
  .then(r=>r.json()).then(d=>{
    if (d.success) { _toast('Tag "'+d.name+'" erstellt.', true); setTimeout(()=>location.reload(),800); }
    else _toast(d.error||'Fehler', false);
  });
}

function editTag(id, name, color) {
  document.getElementById('editTagId').value = id;
  document.getElementById('editTagName').value = name;
  document.getElementById('editTagColor').value = color;
  new bootstrap.Modal(document.getElementById('editTagModal')).show();
}

function saveTag(csrf) {
  const id    = document.getElementById('editTagId').value;
  const name  = document.getElementById('editTagName').value.trim();
  const color = document.getElementById('editTagColor').value;
  fetch('<?= url('tags/') ?>'+id+'/update', {method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf:csrf, name, color})})
  .then(r=>r.json()).then(d=>{
    if (d.success) { bootstrap.Modal.getInstance(document.getElementById('editTagModal')).hide(); _toast('Gespeichert.', true); setTimeout(()=>location.reload(),800); }
    else _toast(d.error||'Fehler', false);
  });
}

function deleteTag(id, csrf) {
  if (!confirm('Tag loeschen? Wird von allen Eintraegen entfernt.')) return;
  fetch('<?= url('tags/') ?>'+id+'/delete', {method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf:csrf})})
  .then(r=>r.json()).then(d=>{
    if (d.success) { document.getElementById('tag-row-'+id)?.remove(); _toast('Geloescht.', true); }
    else _toast(d.error||'Fehler', false);
  });
}
</script>
