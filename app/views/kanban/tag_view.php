<?php
$csrf    = Auth::csrfToken();
$canEdit = Auth::canEdit('kanban');
?>
<style>
.kanban-card    { background:#1e2125; border:1px solid rgba(255,255,255,.1); border-radius:.375rem;
                  padding:10px 12px; transition:box-shadow .15s, border-color .15s; cursor:pointer; }
.kanban-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.4); border-color:rgba(255,255,255,.2); }

/* Bucket collapse */
.tag-bucket-collapsed .tag-cards,
.tag-bucket-collapsed .tag-chips  { display:none !important; }
.tag-bucket-collapsed { min-width:44px !important; max-width:44px !important; cursor:pointer; overflow:hidden; }
.tag-bucket-collapsed .bucket-name { writing-mode:vertical-lr; transform:rotate(180deg); min-height:140px; display:flex; align-items:center; }
.tag-bucket-collapsed .bucket-toggle-btn { display:none; }
.bucket-expand-overlay { display:none; position:absolute; inset:0; z-index:1; }
.tag-bucket-collapsed .bucket-expand-overlay { display:block; cursor:pointer; }
.tag-bucket { position:relative; transition:min-width .2s, max-width .2s; }
/* Note card indicator */
.kanban-card.has-note { border-left:3px solid #facc15; }
.note-display { font-size:.68rem; color:#d1b44f; margin-top:6px; cursor:pointer;
  background:rgba(250,204,21,.08); border-radius:.25rem; padding:4px 6px; }
.note-icon-btn { background:none; border:none; color:#6b7280; cursor:pointer; padding:2px 4px; opacity:.6; font-size:.8rem; }
.note-icon-btn:hover, .note-icon-btn.active { opacity:1; color:#facc15; }
/* Note popup */
/* Note popup rendered as fixed overlay ? never clipped by parent containers */
.note-popup {
  display:none; position:fixed; z-index:9100; width:280px;
  background:#1a1d21; border:1px solid #facc15;
  border-radius:.5rem; padding:10px; box-shadow:0 8px 24px rgba(0,0,0,.7);
}
.note-popup.open { display:block; }
.note-popup textarea {
  width:100%; background:#111317; border:1px solid rgba(255,255,255,.15);
  border-radius:.3rem; color:#f3f4f6; font-size:.78rem; padding:6px 8px;
  resize:vertical; min-height:72px; outline:none;
}
.note-popup textarea:focus { border-color:#facc15; }
.note-popup-actions { display:flex; gap:6px; margin-top:6px; flex-wrap:wrap; }
.note-btn { font-size:.72rem; padding:3px 8px; border-radius:.25rem; border:none; cursor:pointer; white-space:nowrap; }
.note-btn-save    { background:#facc15; color:#111; font-weight:600; }
.note-btn-save:hover { background:#fde047; }
.note-btn-delete  { background:transparent; color:#f87171; border:1px solid #f87171; }
.note-btn-delete:hover { background:#f87171; color:#fff; }
.note-btn-promote { background:transparent; color:#818cf8; border:1px solid #818cf8; margin-left:auto; }
.note-btn-promote:hover { background:#818cf8; color:#fff; }
.note-icon-btn {
  background:none; border:none; padding:0 2px; cursor:pointer; line-height:1;
  opacity:.45; font-size:.82rem; transition:opacity .15s, color .15s;
}
.note-icon-btn:hover, .note-icon-btn.active { opacity:1; color:#facc15; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><i class="bi bi-kanban me-2 text-info"></i>Kanban Board</h4>
    <small class="text-muted">Tag View &middot; Meine persoenlichen Buckets</small>
  </div>
  <a href="<?= url('entries/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Entry</a>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="<?= url('kanban?view=status') ?>"><i class="bi bi-kanban me-1"></i>Status View</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('kanban?view=lane') ?>"><i class="bi bi-layout-three-columns me-1"></i>Lane View</a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= url('kanban/tag-view') ?>"><i class="bi bi-tags me-1"></i>Tag View</a></li>
</ul>

<?php if (!$buckets): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-tags fs-1 d-block mb-2 opacity-25"></i>
  Noch keine Buckets. Erstelle deinen ersten Tag-Bucket!
  <div class="mt-3">
    <button class="btn btn-primary" onclick="openCreateBucket()"><i class="bi bi-plus-lg me-1"></i>Ersten Bucket erstellen</button>
  </div>
</div>
<?php else: ?>

<div class="d-flex gap-3 overflow-auto align-items-stretch pb-3" id="tagBoard" style="min-height:60vh">
  <?php foreach ($buckets as $bucket):
    // Collect entries for this bucket (union of all tag entries, deduplicated)
    $bucketEntries = [];
    $seen = [];
    foreach ($bucket['tags'] as $tag) {
      foreach (($entriesByTag[$tag['id']] ?? []) as $entry) {
        if (!isset($seen[$entry['id']])) {
          $seen[$entry['id']] = true;
          $bucketEntries[] = $entry;
        }
      }
    }
  ?>
  <div class="tag-bucket flex-shrink-0" data-bid="<?= $bucket['id'] ?>" style="min-width:280px;max-width:310px;display:flex;flex-direction:column;border-radius:8px;overflow:hidden;border:1px solid #334155" data-id="<?= $bucket['id'] ?>">
    <!-- Bucket header -->
    <!-- Bucket header -->
    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-top"
         style="background:<?= e($bucket['color']) ?>;color:#fff">
      <div class="bucket-expand-overlay" onclick="toggleTagBucket('<?= $bucket['id'] ?>')"></div>
      <span class="fw-semibold flex-grow-1 bucket-name" style="font-size:.85rem"><?= e($bucket['name']) ?></span>
      <span class="badge" style="background:rgba(0,0,0,.3);font-size:.65rem"><?= count($bucketEntries) ?></span>
      <button class="btn-sm border-0 bg-transparent text-white p-0 opacity-75 bucket-toggle-btn" style="line-height:1"
              onclick="event.stopPropagation();toggleTagBucket('<?= $bucket['id'] ?>')" title="Hide bucket">
        <i class="bi bi-eye-slash" id="btoggle-<?= $bucket['id'] ?>" style="font-size:.72rem"></i>
      </button>
      <button class="btn-sm border-0 bg-transparent text-white p-0 opacity-75" style="line-height:1"
              onclick="editBucket(<?= $bucket['id'] ?>,'<?= e(addslashes($bucket['name'])) ?>','<?= e($bucket['color']) ?>',<?= json_encode(array_column($bucket['tags'],'id')) ?>)" title="Bearbeiten">
        <i class="bi bi-pencil" style="font-size:.72rem"></i>
      </button>
      <button class="btn-sm border-0 bg-transparent text-white p-0 opacity-75" style="line-height:1"
              onclick="deleteBucket(<?= $bucket['id'] ?>,'<?= e($csrf) ?>')" title="Loeschen">
        <i class="bi bi-trash" style="font-size:.72rem"></i>
      </button>
    </div>
    <!-- Tag chips -->
    <div class="tag-chips d-flex flex-wrap gap-1 px-2 pt-1 pb-1" style="background:rgba(0,0,0,.15);min-height:22px">
      <?php foreach ($bucket['tags'] as $tag): ?>
      <span class="badge rounded-pill" style="background:<?= e($tag['color']) ?>;font-size:.58rem;opacity:.9"><?= e($tag['name']) ?></span>
      <?php endforeach; ?>
      <?php if (!$bucket['tags']): ?>
      <span class="text-muted" style="font-size:.65rem;line-height:1.8">No tags assigned</span>
      <?php endif; ?>
    </div>
    <!-- Cards -->
    <div class="tag-cards d-flex flex-column gap-2 p-2 flex-grow-1"
         style="background:#0f172a;min-height:120px">
      <?php if (!$bucketEntries): ?>
      <div class="text-muted text-center py-3" style="font-size:.75rem">Keine Eintraege mit diesen Tags.</div>
      <?php else:
        // Group by status
        $tGrouped = [];
        foreach ($bucketEntries as $e) { $tGrouped[$e['status']??'open'][] = $e; }
        $tOrder = array_keys(entryStatuses());
        uksort($tGrouped, fn($a,$b) => (array_search($a,$tOrder)?:99) <=> (array_search($b,$tOrder)?:99));
        $tFirst = true;
        foreach ($tGrouped as $tStatus => $tEntries):
          $tLabel = entryStatuses()[$tStatus] ?? ucfirst($tStatus);
          $tColor = entryStatusColor($tStatus);
      ?>
      <!-- Status group divider -->
      <div class="d-flex align-items-center gap-1 mx-1 <?= $tFirst ? 'mt-0' : 'mt-3' ?>" style="font-size:.62rem">
        <div class="rounded-circle flex-shrink-0" style="width:7px;height:7px;background:var(--bs-<?= $tColor ?>)"></div>
        <span class="fw-semibold text-uppercase" style="letter-spacing:.04em;color:#94a3b8"><?= e($tLabel) ?></span>
        <span class="text-muted ms-1"><?= count($tEntries) ?></span>
        <div class="flex-grow-1 border-bottom border-secondary ms-1" style="opacity:.4"></div>
      </div>
      <?php
        $tFirst = false;
        $_priColorsTV = ['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];
        $_priStyleTV  = fn($p) => ($_priColorsTV[$p]??'secondary')==='orange' ? 'background:#f97316' : 'background:var(--bs-'.($_priColorsTV[$p]??'secondary').')';
        foreach ($tEntries as $e):
      ?>
      <?php $hasNoteTV = !empty($e['kanban_note']); ?>
      <div class="kanban-card <?= $hasNoteTV?'has-note':'' ?>" data-entry-id="<?= $e['id'] ?>"
           data-note="<?= e($e['kanban_note'] ?? '') ?>">
        <div class="d-flex gap-1 mb-2 flex-wrap">
          <span class="badge" style="background:<?= e($e['type_color'] ?? '#6c757d') ?>;font-size:.6rem"><?= e($e['type_name'] ?? '') ?></span>
          <?php $pv = $e['priority'] ?? 'Medium'; ?>
          <span class="badge" style="font-size:.6rem;<?= $_priStyleTV($pv) ?>"><?= e($pv) ?></span>
          <?php if ($e['jira_issue_key']): ?>
          <a href="<?= e($e['jira_issue_url']??'#') ?>" target="_blank"
             class="badge <?= $e['jira_has_changes']?'bg-warning text-dark':'bg-dark text-warning' ?> text-decoration-none ms-auto" style="font-size:.6rem">
            <i class="bi bi-bug-fill"></i> <?= e($e['jira_issue_key']) ?>
          </a>
          <?php endif; ?>
          <?php if ($e['zentao_bug_id']): ?>
          <a href="<?= e($e['zentao_bug_url']??'#') ?>" target="_blank"
             class="badge <?= $e['zentao_has_changes']?'bg-warning text-dark':'bg-dark text-info' ?> text-decoration-none" style="font-size:.6rem">
            <i class="bi bi-bug"></i> #<?= e($e['zentao_bug_id']) ?>
          </a>
          <?php endif; ?>
        </div>
        <?php if (!empty($e['epic_id']) && !empty($epicGroups[$e['epic_id']])): ?>
        <?php $_ep = $epicGroups[$e['epic_id']]; ?>
        <div class="mt-1 mb-1" style="border-left:3px solid <?= e($_ep['color']) ?>;padding-left:5px">
          <span style="color:<?= e($_ep['color']) ?>;font-size:.62rem;font-weight:600">
            <i class="bi bi-lightning-fill me-1"></i><?= e($_ep['title']) ?>
          </span>
        </div>
        <?php endif; ?>
        <a href="<?= url('entries/'.$e['id']) ?>" class="text-white text-decoration-none d-block"
           style="font-size:.82rem;line-height:1.35">
          <?= e($e['title'] ?: '(no title)') ?>
        </a>
        <div class="d-flex align-items-center justify-content-between mt-2">
          <span class="text-muted" style="font-size:.68rem"><?= e($e['project_name'] ?? '') ?></span>
          <span class="text-muted" style="font-size:.68rem"><?= !empty($e['entry_date']) ? formatDate($e['entry_date'],'d.m.') : '' ?></span>
        </div>
        <?php if (!empty($e['cat_name'])): ?>
        <div class="text-muted mt-1" style="font-size:.65rem"><?= e($e['cat_name']) ?></div>
        <?php endif; ?>
      <?php $_childrenMap = $childrenMap ?? []; $_subCards = $_childrenMap[$e['id']] ?? []; ?>
      <?php if (!empty($_subCards)): ?>
      <div class="mt-2 border-top border-secondary pt-2">
        <div class="d-flex align-items-center justify-content-between" style="cursor:pointer"
             onclick="toggleKanbanSub(<?= $e['id'] ?>, this)">
          <span class="text-info" style="font-size:.65rem">
            <i class="bi bi-diagram-2 me-1"></i><?= count($_subCards) ?> Sub-Ticket<?= count($_subCards)>1?'s':'' ?>
          </span>
          <i class="bi bi-chevron-right text-info" id="ksubchev-<?= $e['id'] ?>" style="font-size:.6rem"></i>
        </div>
        <div id="ksub-<?= $e['id'] ?>" style="display:none;margin-top:6px">
          <?php foreach ($_subCards as $_sub): ?>
          <div class="d-flex align-items-start gap-1 py-1 border-bottom border-secondary" style="font-size:.72rem">
            <i class="bi bi-arrow-return-right text-info flex-shrink-0 mt-1" style="font-size:.6rem"></i>
            <div class="flex-grow-1">
              <a href="<?= url('entries/'.$_sub['id']) ?>" class="text-white text-decoration-none"
                 onclick="event.stopPropagation()"><?= e($_sub['title'] ?: '(no title)') ?></a>
              <div class="text-muted" style="font-size:.65rem"><?= e($_sub['status']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($hasNoteTV): ?>
      <div class="note-display" onclick="event.stopPropagation();toggleNotePopupTV(event,<?= $e['id'] ?>)">
        <?= e($e['kanban_note']) ?>
      </div>
      <?php endif; ?>
      <?php if (Auth::canEdit('kanban_notes')): ?>
      <div class="d-flex justify-content-end mt-1">
        <button class="note-icon-btn <?= $hasNoteTV?'active':'' ?>"
                onclick="event.stopPropagation();toggleNotePopupTV(event,<?= $e['id'] ?>)">
          <i class="bi bi-sticky<?= $hasNoteTV?'-fill':'' ?>"></i>
        </button>
      </div>
      <?php endif; ?>
      </div>
      <?php endforeach; endforeach; endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Add bucket button -->
  <div class="flex-shrink-0 d-flex align-items-start pt-1">
    <button class="btn btn-outline-secondary" onclick="openCreateBucket()" style="min-width:160px">
      <i class="bi bi-plus-lg me-1"></i>Neuer Bucket
    </button>
  </div>
</div>
<?php endif; ?>

<!-- Create/Edit Bucket Modal -->
<div class="modal fade" id="bucketModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title" id="bucketModalTitle">Neuer Bucket</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="bucketId">
      <div class="mb-3">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="bucketName" class="form-control" placeholder="z.B. Meine Bugs">
      </div>
      <div class="mb-3">
        <label class="form-label small">Farbe</label>
        <input type="color" id="bucketColor" class="form-control form-control-color w-100" value="#6c757d" style="height:40px">
      </div>
      <div class="mb-3">
        <label class="form-label small">Tags (Eintraege mit diesen Tags erscheinen im Bucket)</label>
        <input type="text" id="tagSearch" class="form-control form-control-sm mb-2" placeholder="Tag suchen..." oninput="searchTags(this.value)">
        <div id="tagSearchResults" style="max-height:160px;overflow-y:auto" class="border border-secondary rounded p-1 mb-2"></div>
        <div id="selectedTags" class="d-flex flex-wrap gap-1"></div>
      </div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-primary btn-sm" onclick="saveBucket('<?= e($csrf) ?>')">Speichern</button>
    </div>
  </div></div>
</div>

<div id="tbToast" class="position-fixed bottom-0 end-0 m-3 p-3 rounded bg-dark border-secondary" style="display:none;z-index:9999;font-size:.82rem"></div>

<script>
const _csrf = '<?= e($csrf) ?>';
const _allTags = <?= json_encode($allTags) ?>;
let _selTagIds = new Set();
let _editBucketId = null;
let _tagTimer;

function _toast(msg, ok) {
  const t = document.getElementById('tbToast');
  t.innerHTML = '<i class="bi bi-'+(ok?'check-circle text-success':'x-circle text-danger')+' me-2"></i>'+msg;
  t.style.display=''; clearTimeout(t._t); t._t=setTimeout(()=>t.style.display='none',4000);
}

function openCreateBucket() {
  _editBucketId = null;
  _selTagIds = new Set();
  document.getElementById('bucketId').value = '';
  document.getElementById('bucketName').value = '';
  document.getElementById('bucketColor').value = '#6c757d';
  document.getElementById('bucketModalTitle').textContent = 'Neuer Bucket';
  renderSelectedTags();
  searchTags('');
  new bootstrap.Modal(document.getElementById('bucketModal')).show();
}

function editBucket(id, name, color, tagIds) {
  _editBucketId = id;
  _selTagIds = new Set(tagIds.map(Number));
  document.getElementById('bucketId').value = id;
  document.getElementById('bucketName').value = name;
  document.getElementById('bucketColor').value = color;
  document.getElementById('bucketModalTitle').textContent = 'Bucket bearbeiten';
  renderSelectedTags();
  searchTags('');
  new bootstrap.Modal(document.getElementById('bucketModal')).show();
}

function searchTags(q) {
  clearTimeout(_tagTimer);
  _tagTimer = setTimeout(() => {
    const filtered = q ? _allTags.filter(t => t.name.toLowerCase().includes(q.toLowerCase())) : _allTags;
    const res = document.getElementById('tagSearchResults');
    res.innerHTML = filtered.map(t => {
      const sel = _selTagIds.has(t.id);
      return '<div class="d-flex align-items-center gap-2 p-1 rounded" style="cursor:pointer;font-size:.82rem" onclick="toggleTag('+t.id+')">' +
        '<div style="width:10px;height:10px;border-radius:50%;background:'+t.color+';flex-shrink:0"></div>' +
        '<span class="flex-grow-1">'+t.name+'</span>' +
        '<i class="bi bi-check-lg text-warning '+(sel?'':'invisible')+'"></i></div>';
    }).join('') || '<div class="text-muted small p-1">Keine Tags gefunden.</div>';
  }, 200);
}

function toggleTag(id) {
  if (_selTagIds.has(id)) _selTagIds.delete(id);
  else _selTagIds.add(id);
  renderSelectedTags();
  searchTags(document.getElementById('tagSearch').value);
}

function renderSelectedTags() {
  const sel = document.getElementById('selectedTags');
  const tags = _allTags.filter(t => _selTagIds.has(t.id));
  sel.innerHTML = tags.map(t =>
    '<span class="badge d-flex align-items-center gap-1" style="background:'+t.color+'">'+t.name+
    '<button class="btn-close btn-close-white p-0 ms-1" style="font-size:.5rem" onclick="toggleTag('+t.id+')"></button></span>'
  ).join('');
}

function saveBucket(csrf) {
  const id    = document.getElementById('bucketId').value;
  const name  = document.getElementById('bucketName').value.trim();
  const color = document.getElementById('bucketColor').value;
  if (!name) { alert('Name erforderlich'); return; }
  const body = new URLSearchParams({_csrf: csrf, name, color});
  Array.from(_selTagIds).forEach(tid => body.append('tag_ids[]', tid));
  const url = id
    ? '<?= url('kanban/tag-buckets/') ?>'+id+'/update'
    : '<?= url('kanban/tag-buckets') ?>';
  fetch(url, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body})
  .then(r=>r.json()).then(d=>{
    if (d.success) { bootstrap.Modal.getInstance(document.getElementById('bucketModal')).hide(); _toast('Gespeichert.', true); setTimeout(()=>location.reload(),800); }
    else _toast(d.error||'Fehler', false);
  });
}

function deleteBucket(id, csrf) {
  if (!confirm('Bucket loeschen?')) return;
  fetch('<?= url('kanban/tag-buckets/') ?>'+id+'/delete', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf})})
  .then(r=>r.json()).then(d=>{
    if (d.success) { _toast('Geloescht.', true); setTimeout(()=>location.reload(),800); }
  });
}

// Sub-ticket expand/collapse in kanban cards
var _kSubCollapsed = {};
function toggleKanbanSub(entryId, trigger) {
  // On first click: container is hidden (display:none from PHP) but _kSubCollapsed is undefined
  // So undefined means collapsed=true (hidden), we want to show on first click
  var currentlyCollapsed = _kSubCollapsed[entryId] !== undefined ? _kSubCollapsed[entryId] : true;
  _kSubCollapsed[entryId] = !currentlyCollapsed;
  var collapsed = _kSubCollapsed[entryId];
  var container = document.getElementById('ksub-' + entryId);
  var chev      = document.getElementById('ksubchev-' + entryId);
  if (container) container.style.display = collapsed ? 'none' : '';
  if (chev) { chev.className = (collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-down') + ' text-info'; chev.style.fontSize='.6rem'; }
}
// ── Tag Bucket Collapse ─────────────────────────────────────────────────
function toggleTagBucket(id) {
  var col = document.querySelector('[data-bid="'+id+'"]');
  if (!col) return;
  var isCollapsed = col.classList.contains('tag-bucket-collapsed');
  col.classList.toggle('tag-bucket-collapsed', !isCollapsed);
  var icon = document.getElementById('btoggle-'+id);
  if (icon) icon.className = isCollapsed ? 'bi bi-eye-slash' : 'bi bi-eye';
  try { localStorage.setItem('rd_tbucket_'+id, isCollapsed?'0':'1'); } catch(x){}
}
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[data-bid]').forEach(function(col){
    var id = col.dataset.bid;
    try { if (localStorage.getItem('rd_tbucket_'+id)==='1') {
      col.classList.add('tag-bucket-collapsed');
      var icon = document.getElementById('btoggle-'+id);
      if (icon) icon.className='bi bi-eye';
    }} catch(x){}
  });
});

// ── Notes ────────────────────────────────────────────────────────────────
const TV_CAN_NOTE = <?= Auth::canEdit('kanban_notes') ? 'true' : 'false' ?>;
const TV_CSRF     = '<?= Auth::csrfToken() ?>';
const TV_BASE     = '<?= url('kanban/entries/') ?>';
let _tvActiveNoteId = null;
let _tvNotePopup, _tvNoteText, _tvNoteSave, _tvNoteDelete;
document.addEventListener('DOMContentLoaded', function() {
  _tvNotePopup  = document.getElementById('tvNotePopup');
  _tvNoteText   = document.getElementById('tvNoteText');
  _tvNoteSave   = document.getElementById('tvNoteSave');
  _tvNoteDelete = document.getElementById('tvNoteDelete');
  _tvNoteSave.addEventListener('click',()=>_tvSave(_tvNoteText.value.trim()));
  _tvNoteDelete.addEventListener('click',()=>_tvSave(''));
  document.getElementById('tvNotePromote')?.addEventListener('click',function(){
    if(!_tvActiveNoteId||!confirm('Save as comment and delete note?')) return;
    const id=_tvActiveNoteId;
    fetch(TV_BASE+id+'/note/promote',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},
      body:new URLSearchParams({_csrf:TV_CSRF})})
    .then(r=>r.json()).then(()=>{
      const card=document.querySelector('[data-entry-id="'+id+'"]');
      if(card) _tvApply(card,id,'');
      _tvNotePopup.classList.remove('open'); _tvActiveNoteId=null;
    });
  });
  _tvNoteText.addEventListener('keydown',e=>{
    if(e.key==='Enter'&&(e.ctrlKey||e.metaKey)) _tvSave(_tvNoteText.value.trim());
    if(e.key==='Escape'){_tvNotePopup.classList.remove('open');_tvActiveNoteId=null;}
  });
  document.addEventListener('click',e=>{
    if(_tvActiveNoteId&&!e.target.closest('#tvNotePopup')&&!e.target.closest('.note-icon-btn,.note-display')){
      _tvNotePopup.classList.remove('open'); _tvActiveNoteId=null;
    }
  });
});

function _tvApply(card, id, note) {
  card.dataset.note = note;
  card.classList.toggle('has-note', !!note);
  card.querySelector('.note-display')?.remove();
  const iconBtn = card.querySelector('.note-icon-btn');
  if (note) {
    iconBtn?.classList.add('active'); if(iconBtn) iconBtn.querySelector('i').className='bi bi-sticky-fill';
    const d=document.createElement('div'); d.className='note-display';
    d.textContent=note; d.onclick=ev=>{ev.stopPropagation();toggleNotePopupTV(ev,id);};
    card.querySelector('.d-flex.justify-content-end')?.before(d) || card.appendChild(d);
  } else {
    iconBtn?.classList.remove('active'); if(iconBtn) iconBtn.querySelector('i').className='bi bi-sticky';
  }
}

window.toggleNotePopupTV = function(e, id) {
  e.stopPropagation();
  if (_tvActiveNoteId===id && _tvNotePopup.classList.contains('open')){
    _tvNotePopup.classList.remove('open'); _tvActiveNoteId=null; return;
  }
  _tvNoteText.disabled = !TV_CAN_NOTE;
  _tvNoteSave.disabled = !TV_CAN_NOTE;
  const card = document.querySelector('[data-entry-id="'+id+'"]');
  _tvNoteText.value = card?.dataset.note ?? '';
  _tvActiveNoteId = id;
  _tvNoteDelete.style.display = _tvNoteText.value ? '' : 'none';
  const rect = e.currentTarget.getBoundingClientRect();
  _tvNotePopup.style.top  = (rect.bottom+4)+'px';
  _tvNotePopup.style.left = Math.min(rect.left, window.innerWidth-290)+'px';
  _tvNotePopup.classList.add('open');
  _tvNoteText.focus();
};

function _tvSave(note) {
  const id=_tvActiveNoteId; if(!id) return;
  fetch(TV_BASE+id+'/note',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},
    body:new URLSearchParams({_csrf:TV_CSRF,note:note})})
  .then(r=>r.json()).then(()=>{
    const card=document.querySelector('[data-entry-id="'+id+'"]');
    if(card) _tvApply(card,id,note);
    _tvNotePopup.classList.remove('open'); _tvActiveNoteId=null;
  });
}

</script>
<!-- Shared note popup overlay -->
<div class="note-popup" id="tvNotePopup">
  <div style="font-size:.7rem;color:#facc15;font-weight:600;margin-bottom:5px">
    <i class="bi bi-sticky-fill me-1"></i>My Note
  </div>
  <textarea id="tvNoteText" placeholder="Enter note..."></textarea>
  <div class="note-popup-actions">
    <button class="note-btn note-btn-save" id="tvNoteSave">
      <i class="bi bi-check-lg me-1"></i>Save
    </button>
    <button class="note-btn note-btn-delete" id="tvNoteDelete">
      <i class="bi bi-trash me-1"></i>Delete
    </button>
    <button class="note-btn note-btn-promote" id="tvNotePromote"
            title="Als echten Kommentar zum Eintrag hinzufügen">
      <i class="bi bi-arrow-up-right-circle me-1"></i>As Comment
    </button>
  </div>
</div>

<div id="laneToast" class="toast align-items-center position-fixed bottom-0 end-0 m-3 bg-dark border-secondary"
     role="alert" style="z-index:9999;display:none;min-width:220px">
  <div class="d-flex p-2 gap-2 align-items-center">
    <i class="bi bi-check-circle text-success" id="laneToastIcon"></i>
    <span id="laneToastMsg" class="small flex-grow-1"></span>
    <button class="btn-close btn-close-white btn-sm" onclick="document.getElementById('laneToast').style.display='none'"></button>
  </div>
</div>
