<?php
// Lane-based Kanban board renderer
// Variables: $lanes (array of slug => [label, color, entries])
$_lCsrf = Auth::csrfToken();
$_priColors = ['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];
$_priStyle  = fn($p) => ($_priColors[$p]??'secondary')==='orange' ? 'background:#f97316' : 'background:var(--bs-'.($_priColors[$p]??'secondary').')';
?>
<style>
.lane-board    { display:flex; gap:16px; overflow-x:auto; align-items:stretch; min-height:60vh; padding-bottom:12px; }
.lane-col      { min-width:260px; max-width:290px; flex-shrink:0; display:flex; flex-direction:column; }
.lane-cards    { flex-grow:1; }
.lane-col-hdr  { border-radius:.375rem .375rem 0 0; padding:8px 12px; font-size:.82rem; font-weight:600;
                 display:flex; align-items:center; gap:6px;
                 position:sticky; top:0; z-index:20; }
.lane-cards    { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
                 border-top:none; border-radius:0 0 .375rem .375rem;
                 min-height:80px; padding:8px; display:flex; flex-direction:column; gap:8px;
                 transition:background .15s; }
.lane-cards.drag-over { background:rgba(255,255,255,.1); outline:2px dashed rgba(255,255,255,.3); }
.lane-card     { background:#1e2125; border:1px solid rgba(255,255,255,.1); border-radius:.375rem;
                 padding:10px 12px; cursor:grab; transition:opacity .15s, box-shadow .15s; user-select:none;
                 position:relative; }
.lane-card:hover     { box-shadow:0 4px 12px rgba(0,0,0,.4); border-color:rgba(255,255,255,.2); }
.lane-card.dragging  { opacity:.4; cursor:grabbing; }
.lane-card.drag-placeholder { background:rgba(255,255,255,.06); border:2px dashed rgba(255,255,255,.2);
                               min-height:60px; border-radius:.375rem; }
.lane-card.has-note  { border-left:3px solid #facc15; }
.archive-note  { font-size:.65rem; color:#9ca3af; text-align:center; padding:4px 0 0; }
.bg-purple     { background:#7c3aed !important; color:#fff; }
.bg-teal       { background:#0d9488 !important; color:#fff; }
/* Collapsed lane ? narrow vertical strip */
.lane-col-collapsed { min-width:38px !important; max-width:38px !important; cursor:pointer; }
.lane-col-collapsed .lane-cards { display:none !important; }
.lane-col-collapsed .lane-col-hdr {
  writing-mode:vertical-lr;
  transform:rotate(180deg);
  min-height:160px;
  border-radius:.375rem;
  justify-content:flex-end;
  padding:10px 8px;
  gap:8px;
  pointer-events:none;
}
.lane-col-collapsed .lane-col-hdr .badge { display:none; }
.lane-col-collapsed .lane-col-hdr .lane-toggle-btn { display:none; }
/* Invisible full-area click overlay on collapsed lane */
.lane-expand-overlay {
  display:none;
  position:absolute;inset:0;
  z-index:10;
  cursor:pointer;
  background:transparent;
}
.lane-col-collapsed .lane-expand-overlay { display:block; }
.lane-col { position:relative; }

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

<div class="lane-board" id="laneBoard">
<?php foreach ($lanes as $slug => $col): ?>
<div class="lane-col" data-slug="<?= $slug ?>">
  <div class="lane-expand-overlay" onclick="toggleLane('<?= $slug ?>')" title="Show lane"></div>
  <div class="lane-col-hdr bg-<?= $col['color'] ?>"
       style="<?= in_array($col['color'],['secondary','info','primary','warning'])&&$col['color']!=='warning'?'':'color:#fff' ?>">
    <span class="flex-grow-1"><?= e($col['label']) ?></span>
    <span class="badge bg-dark bg-opacity-25" id="lcnt-<?= $slug ?>"><?= count($col['entries']) ?></span>
    <button class="lane-toggle-btn ms-2" onclick="event.stopPropagation();toggleLane('<?= $slug ?>')" title="Hide lane"
            style="background:rgba(0,0,0,.25);border:none;border-radius:4px;padding:2px 6px;cursor:pointer;color:#fff;line-height:1.4">
      <i class="bi bi-eye-slash" id="ltoggle-<?= $slug ?>" style="font-size:.8rem"></i>
    </button>
  </div>
  <div class="lane-cards" data-lane="<?= $slug ?>" id="lcol-<?= $slug ?>">
    <?php
    // Group entries by status
    $grouped = [];
    foreach ($col['entries'] as $e) {
        $grouped[$e['status'] ?? 'open'][] = $e;
    }
    $statusOrder = array_keys(entryStatuses());
    uksort($grouped, fn($a,$b) => (array_search($a,$statusOrder)?:99) <=> (array_search($b,$statusOrder)?:99));
    $firstGroup = true;
    foreach ($grouped as $statusSlug => $groupEntries):
        $statusLabel = entryStatuses()[$statusSlug] ?? ucfirst($statusSlug);
        $statusCol   = entryStatusColor($statusSlug);
    ?>
    <!-- Status group header -->
    <div class="d-flex align-items-center gap-1 px-1 <?= $firstGroup ? 'mt-1' : 'mt-2' ?>" style="font-size:.65rem">
      <span class="badge bg-<?= $statusCol ?> bg-opacity-75" style="font-size:.58rem"><?= e($statusLabel) ?></span>
      <span class="text-muted"><?= count($groupEntries) ?></span>
      <hr class="flex-grow-1 m-0 border-secondary">
    </div>
    <?php $firstGroup = false; foreach ($groupEntries as $e): ?>
    <?php $hasNote = !empty($e['kanban_note']); ?>
    <div class="lane-card <?= $hasNote ? 'has-note' : '' ?>" draggable="<?= Auth::canEdit('kanban') || Auth::canEditEntry($e) ? 'true' : 'false' ?>"
         data-entry-id="<?= $e['id'] ?>"
         data-lane="<?= e($e['kanban_lane'] ?: 'new') ?>"
         data-title="<?= strtolower(e($e['title']??'')) ?>"
         data-note="<?= e($e['kanban_note']) ?>">

      <div class="d-flex gap-1 mb-2 flex-wrap align-items-start">
        <span class="badge" style="background:<?= e($e['type_color']) ?>;font-size:.6rem"><?= e($e['type_name']) ?></span>
        <?php $pv=$e['priority']??'Medium'; ?>
        <span class="badge" style="font-size:.6rem;<?= $_priStyle($pv) ?>"><?= $pv ?></span>
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
        <!-- Note toggle button -->
        <?php if (Auth::canEdit('kanban_notes')): ?>
        <button class="note-icon-btn <?= $hasNote ? 'active' : '' ?> ms-auto"
                title="<?= $hasNote ? 'Edit note' : 'Add note' ?>"
                onclick="toggleNotePopup(event, <?= $e['id'] ?>)"
                data-entry-id="<?= $e['id'] ?>">
          <i class="bi bi-sticky<?= $hasNote ? '-fill' : '' ?>"></i>
        </button>
        <?php endif; ?>
      </div>

      <?php if (!empty($e['epic_id']) && !empty($epicGroups[$e['epic_id']])): ?>
      <?php $_ep = $epicGroups[$e['epic_id']]; ?>
      <div class="mt-1 mb-1" style="border-left:3px solid <?= e($_ep['color']) ?>;padding-left:5px;">
        <span style="color:<?= e($_ep['color']) ?>;font-size:.62rem;font-weight:600">
          <i class="bi bi-lightning-fill me-1"></i><?= e($_ep['title']) ?>
        </span>
      </div>
      <?php endif; ?>
      <a href="<?= url('entries/'.$e['id']) ?>" class="text-white text-decoration-none d-block"
         style="font-size:.82rem;line-height:1.35" onclick="event.stopPropagation()">
        <?= e($e['title'] ?: '(no title)') ?>
      </a>
      <div class="d-flex align-items-center justify-content-between mt-2">
        <span class="text-muted" style="font-size:.68rem"><?= e($e['project_name'] ?? '') ?></span>
        <span class="text-muted" style="font-size:.68rem"><?= formatDate($e['entry_date'],'d.m.') ?></span>
      </div>
      <?php if ($e['cat_name']): ?>
      <div class="text-muted mt-1" style="font-size:.65rem"><?= e($e['cat_name']) ?></div>
      <?php endif; ?>
      <div class="mt-1">
        <span class="badge bg-<?= entryStatusColor($e['status']??'new') ?> bg-opacity-50" style="font-size:.55rem">
          <?= entryStatuses()[$e['status']??'new'] ?? $e['status'] ?>
        </span>
      </div>

      <?php if ($hasNote): ?>
      <div class="mt-2 px-1 py-1 rounded" style="background:rgba(250,204,21,.08);border-left:2px solid #facc15;font-size:.72rem;color:#fef08a;white-space:pre-wrap;word-break:break-word;"
           onclick="toggleNotePopup(event, <?= $e['id'] ?>)" style="cursor:pointer">
        <?= e($e['kanban_note']) ?>
      </div>
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
      <!-- note data stored on card, popup is a shared fixed overlay -->
    </div>
    <?php endforeach; endforeach; ?>
    <?php if ($slug === 'archive'): ?>
    <div class="archive-note"><i class="bi bi-archive me-1"></i>Last 5 shown</div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Shared note popup overlay -->
<div class="note-popup" id="sharedNotePopup">
  <div style="font-size:.7rem;color:#facc15;font-weight:600;margin-bottom:5px">
    <i class="bi bi-sticky-fill me-1"></i>My Note
  </div>
  <textarea id="sharedNoteText" placeholder="Enter note..."></textarea>
  <div class="note-popup-actions">
    <button class="note-btn note-btn-save" id="sharedNoteSave">
      <i class="bi bi-check-lg me-1"></i>Save
    </button>
    <button class="note-btn note-btn-delete" id="sharedNoteDelete">
      <i class="bi bi-trash me-1"></i>Delete
    </button>
    <button class="note-btn note-btn-promote" id="sharedNotePromote"
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

<script>
(function() {
  const CSRF          = '<?= e($_lCsrf) ?>';
  const CAN_NOTE      = <?= Auth::canEdit('kanban_notes') ? 'true' : 'false' ?>;
  const CAN_COMMENT   = <?= Auth::canEdit('entry_comments') ? 'true' : 'false' ?>;
  const BASE    = '<?= url('entries/') ?>';
  let _dragCard = null, _placeholder = null;
  let _openPopup = null;

  // ?? Toast ?????????????????????????????????????????????????????
  function showLaneToast(msg, ok = true) {
    const t = document.getElementById('laneToast');
    document.getElementById('laneToastMsg').textContent = msg;
    document.getElementById('laneToastIcon').className = ok
      ? 'bi bi-check-circle text-success' : 'bi bi-x-circle text-danger';
    t.style.display = '';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 2800);
  }

  function updateLaneCount(slug) {
    const el  = document.getElementById('lcnt-' + slug);
    const col = document.getElementById('lcol-' + slug);
    if (el && col) el.textContent = col.querySelectorAll('.lane-card:not(.drag-placeholder)').length;
  }

  // ?? Shared note popup (fixed overlay, never clipped) ?????????
  const sharedPopup   = document.getElementById('sharedNotePopup');
  const sharedText    = document.getElementById('sharedNoteText');
  const sharedSave    = document.getElementById('sharedNoteSave');
  const sharedDelete  = document.getElementById('sharedNoteDelete');
  const sharedPromote = document.getElementById('sharedNotePromote');
  let _activeNoteId   = null;

  function positionPopup(anchorEl) {
    const rect     = anchorEl.getBoundingClientRect();
    const popupH   = 220;
    const popupW   = Math.min(300, window.innerWidth - 20);
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;

    // position:fixed ? coordinates are relative to viewport, NO scrollY
    sharedPopup.style.width = popupW + 'px';

    // Horizontal: align with anchor, clamp to viewport
    const leftRaw = rect.left;
    sharedPopup.style.left = Math.max(4, Math.min(leftRaw, window.innerWidth - popupW - 4)) + 'px';

    if (spaceBelow >= popupH || spaceBelow >= spaceAbove) {
      // Open downward
      sharedPopup.style.top    = Math.min(rect.bottom + 6, window.innerHeight - popupH - 4) + 'px';
      sharedPopup.style.bottom = 'auto';
    } else {
      // Open upward
      sharedPopup.style.top    = Math.max(4, rect.top - popupH - 6) + 'px';
      sharedPopup.style.bottom = 'auto';
    }
  }

  function openNotePopup(entryId, anchorEl) {
    // Disable actions based on permissions
    sharedSave.disabled    = !CAN_NOTE;
    sharedDelete.disabled  = !CAN_NOTE;
    sharedPromote.disabled = !CAN_COMMENT;
    sharedText.readOnly    = !CAN_NOTE;
    const card = document.querySelector(`.lane-card[data-entry-id="${entryId}"]`);
    const note = card?.dataset.note ?? '';
    _activeNoteId = entryId;
    sharedText.value = note;
    sharedDelete.style.display  = note ? '' : 'none';
    sharedPromote.style.display = note ? '' : 'none';
    positionPopup(anchorEl);
    sharedPopup.classList.add('open');
    setTimeout(() => sharedText.focus(), 30);
  }

  function closeNotePopup() {
    sharedPopup.classList.remove('open');
    _activeNoteId = null;
  }

  window.toggleNotePopup = function(e, entryId) {
    e.stopPropagation();
    e.preventDefault();
    if (_activeNoteId === entryId && sharedPopup.classList.contains('open')) {
      closeNotePopup(); return;
    }
    openNotePopup(entryId, e.currentTarget);
  };

  // Close on outside click
  document.addEventListener('click', function(e) {
    if (sharedPopup.classList.contains('open')
        && !sharedPopup.contains(e.target)
        && !e.target.closest('.note-icon-btn, .note-display')) {
      closeNotePopup();
    }
  });

  // Reposition on scroll/resize
  window.addEventListener('scroll', () => { if (_activeNoteId) closeNotePopup(); }, true);
  window.addEventListener('resize', () => { if (_activeNoteId) closeNotePopup(); });

  function doSaveNote(note) {
    const entryId = _activeNoteId;
    if (!entryId) return;
    const card = document.querySelector(`.lane-card[data-entry-id="${entryId}"]`);

    fetch(BASE + entryId + '/note', {
      method: 'POST',
      body: new URLSearchParams({ _csrf: CSRF, note })
    })
    .then(r => r.json())
    .then(d => {
      if (!d.success) { showLaneToast('Error saving', false); return; }
      applyNoteToCard(card, entryId, note);
      closeNotePopup();
      showLaneToast(note ? 'Note saved' : 'Note deleted', true);
    })
    .catch(() => showLaneToast('Network error', false));
  }

  sharedSave.addEventListener('click', e => {
    e.stopPropagation();
    doSaveNote(sharedText.value.trim());
  });

  sharedDelete.addEventListener('click', e => {
    e.stopPropagation();
    doSaveNote('');
  });

  sharedPromote.addEventListener('click', e => {
    e.stopPropagation();
    const entryId = _activeNoteId;
    if (!entryId) return;
    if (!confirm('Save note as a real comment on this entry and delete the note?')) return;
    const card = document.querySelector(`.lane-card[data-entry-id="${entryId}"]`);

    fetch(BASE + entryId + '/note/promote', {
      method: 'POST',
      body: new URLSearchParams({ _csrf: CSRF })
    })
    .then(r => r.json())
    .then(d => {
      if (!d.success) { showLaneToast(d.error || 'Fehler', false); return; }
      applyNoteToCard(card, entryId, '');
      closeNotePopup();
      showLaneToast('Saved as comment', true);
    })
    .catch(() => showLaneToast('Network error', false));
  });

  // Ctrl+Enter to save
  sharedText.addEventListener('keydown', e => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) doSaveNote(sharedText.value.trim());
    if (e.key === 'Escape') closeNotePopup();
  });

  function applyNoteToCard(card, entryId, note) {
    if (!card) return;
    const iconBtn = card.querySelector('.note-icon-btn');
    card.dataset.note = note;
    card.querySelector('.note-display')?.remove();

    if (note) {
      card.classList.add('has-note');
      if (iconBtn) { iconBtn.classList.add('active'); iconBtn.querySelector('i').className = 'bi bi-sticky-fill'; }
      const div = document.createElement('div');
      div.className = 'note-display mt-2 px-1 py-1 rounded';
      div.style.cssText = 'background:rgba(250,204,21,.08);border-left:2px solid #facc15;font-size:.72rem;color:#fef08a;white-space:pre-wrap;word-break:break-word;cursor:pointer';
      div.textContent = note;
      div.onclick = ev => toggleNotePopup(ev, entryId);
      // Insert before status badge row (last child before possible note-display)
      card.appendChild(div);
    } else {
      card.classList.remove('has-note');
      if (iconBtn) { iconBtn.classList.remove('active'); iconBtn.querySelector('i').className = 'bi bi-sticky'; }
    }
  }

  // ?? Drag & Drop lanes ?????????????????????????????????????????
  document.querySelectorAll('#laneBoard .lane-col').forEach(col => {
    col.addEventListener('dragover', e => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      col.querySelector('.lane-cards')?.classList.add('drag-over');
      if (_placeholder && !col.contains(_placeholder))
        col.querySelector('.lane-cards')?.appendChild(_placeholder);
    });
    col.addEventListener('dragleave', e => {
      if (!col.contains(e.relatedTarget))
        col.querySelector('.lane-cards')?.classList.remove('drag-over');
    });
    col.addEventListener('drop', e => {
      e.preventDefault();
      col.querySelector('.lane-cards')?.classList.remove('drag-over');
      if (!_dragCard) return;

      const cards   = col.querySelector('.lane-cards');
      const newLane = col.dataset.slug;
      const oldLane = _dragCard.dataset.lane;
      const entryId = _dragCard.dataset.entryId;

      if (_placeholder && cards?.contains(_placeholder)) {
        cards.insertBefore(_dragCard, _placeholder);
      } else {
        cards?.appendChild(_dragCard);
      }
      _placeholder?.remove();

      if (!newLane || newLane === oldLane) return;

      // Remember original column so we can revert on error
      const origCol = document.getElementById('lcol-' + oldLane);

      _dragCard.style.opacity = '.5';
      fetch(BASE + entryId + '/lane', {
        method: 'POST',
        body: new URLSearchParams({ _csrf: CSRF, lane: newLane })
      })
      .then(r => r.json())
      .then(d => {
        _dragCard.style.opacity = '';
        if (d.success) {
          _dragCard.dataset.lane = newLane;
          updateLaneCount(oldLane);
          updateLaneCount(newLane);
          showLaneToast('Lane: ' + d.label, true);
        } else {
          // Revert: move card back to original column
          if (origCol) origCol.prepend(_dragCard);
          updateLaneCount(oldLane);
          updateLaneCount(newLane);
          showLaneToast(d.error || 'No permission', false);
        }
      })
      .catch(() => {
        _dragCard.style.opacity = '';
        if (origCol) origCol.prepend(_dragCard);
        updateLaneCount(oldLane);
        updateLaneCount(newLane);
        showLaneToast('Network error', false);
      });
    });
  });

  document.querySelectorAll('#laneBoard .lane-card').forEach(card => {
    card.addEventListener('dragstart', e => {
      // Don't start drag if a popup is open or clicking note controls
      if (e.target.closest('.note-popup, .note-icon-btn, .note-display')) { e.preventDefault(); return; }
      _dragCard = card;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.entryId);
      setTimeout(() => card.classList.add('dragging'), 0);
      _placeholder = document.createElement('div');
      _placeholder.className = 'lane-card drag-placeholder';
      _placeholder.style.height = card.offsetHeight + 'px';
    });
    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
      _placeholder?.remove(); _placeholder = null;
      _dragCard = null;
    });
    card.addEventListener('dragover', e => {
      if (!_placeholder || !_dragCard || _dragCard === card) return;
      e.preventDefault();
      const mid = card.getBoundingClientRect().top + card.offsetHeight / 2;
      if (e.clientY < mid) card.before(_placeholder);
      else card.after(_placeholder);
    });
  });

})();
// Global: toggle lane collapsed (hides cards, keeps header visible)
function toggleLane(slug) {
  var col  = document.querySelector('.lane-col[data-slug="' + slug + '"]');
  var icon = document.getElementById('ltoggle-' + slug);
  if (!col) return;
  var isCollapsed = col.classList.contains('lane-col-collapsed');
  col.classList.toggle('lane-col-collapsed', !isCollapsed);
  // Update icon and title
  var btn = col.querySelector('.lane-toggle-btn');
  if (icon) icon.className = isCollapsed ? 'bi bi-eye-slash' : 'bi bi-eye';
  if (btn)  btn.title      = isCollapsed ? 'Hide lane' : 'Show lane';
  // Persist
  try { localStorage.setItem('rd_lane_' + slug, isCollapsed ? '0' : '1'); } catch(x) {}
}
// Restore collapsed lanes on load
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.lane-col[data-slug]').forEach(function(col) {
    var slug = col.dataset.slug;
    try {
      if (localStorage.getItem('rd_lane_' + slug) === '1') {
        col.classList.add('lane-col-collapsed');
        var icon = document.getElementById('ltoggle-' + slug);
        if (icon) icon.className = 'bi bi-eye';
      }
    } catch(x) {}
  });
});

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
</script>