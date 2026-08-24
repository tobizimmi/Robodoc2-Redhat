<?php
// Shared Kanban board renderer + drag & drop JS
// Variables: $cols (array of status => [label, color, entries])
// Optional: $_kBoardKey (localStorage key for column order, default 'kanbanColOrder_global')
$_kCsrf     = Auth::csrfToken();
$_kBoardKey = $_kBoardKey ?? 'kanbanColOrder_global';
$_priColors = ['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];
$_priStyle  = fn($p) => ($_priColors[$p]??'secondary')==='orange' ? 'background:#f97316' : 'background:var(--bs-'.($_priColors[$p]??'secondary').')';
?>
<style>
.kanban-board   { display:flex; gap:16px; overflow-x:auto; align-items:stretch; min-height:60vh; padding-bottom:12px; }
/* Collapsed column */
.kanban-col { position:relative; }
.kanban-col-collapsed { min-width:38px !important; max-width:38px !important; cursor:pointer; }
.kanban-col-collapsed .kanban-cards { display:none !important; }
.kanban-col-collapsed .kanban-col-hdr { writing-mode:vertical-lr; transform:rotate(180deg); min-height:160px; border-radius:.375rem; justify-content:flex-end; padding:10px 8px; gap:8px; pointer-events:none; cursor:default; }
.kanban-col-collapsed .kanban-col-hdr .badge { display:none; }
.kanban-col-collapsed .kanban-col-hdr .col-grip { display:none; }
.kanban-col-collapsed .col-toggle-btn { display:none; }
.col-expand-overlay { display:none; position:absolute; inset:0; z-index:10; cursor:pointer; background:transparent; }
.kanban-col-collapsed .col-expand-overlay { display:block; }
.kanban-col     { min-width:240px; max-width:280px; flex-shrink:0; transition:opacity .15s; display:flex; flex-direction:column; }
.kanban-cards   { flex-grow:1; }
.kanban-col.col-dragging { opacity:.35; }
.kanban-col.col-drag-over > .kanban-col-hdr { outline:3px dashed rgba(255,255,255,.5); outline-offset:2px; }
.kanban-col-hdr { border-radius:.375rem .375rem 0 0; padding:8px 12px; font-size:.82rem; font-weight:600;
                  display:flex; align-items:center; gap:6px; cursor:grab; user-select:none;
                  position:sticky; top:0; z-index:20; }
.kanban-col-hdr:active { cursor:grabbing; }
.col-grip       { opacity:.5; font-size:.9rem; flex-shrink:0; }
.col-grip:hover { opacity:1; }
.kanban-cards   { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
                  border-top:none; border-radius:0 0 .375rem .375rem;
                  min-height:80px; padding:8px; display:flex; flex-direction:column; gap:8px;
                  transition:background .15s; }
.kanban-cards.drag-over { background:rgba(255,255,255,.1); outline:2px dashed rgba(255,255,255,.3); }
.kanban-card    { background:#1e2125; border:1px solid rgba(255,255,255,.1); border-radius:.375rem;
                  padding:10px 12px; cursor:grab; transition:opacity .15s, box-shadow .15s; user-select:none; }
.kanban-card:hover    { box-shadow:0 4px 12px rgba(0,0,0,.4); border-color:rgba(255,255,255,.2); }
.kanban-card.dragging { opacity:.4; cursor:grabbing; }
.kanban-card.drag-placeholder { background:rgba(255,255,255,.06); border:2px dashed rgba(255,255,255,.2);
                                 min-height:60px; border-radius:.375rem; }
</style>

<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="resetKanbanColOrder()" title="Reset column order">
    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset column order
  </button>
</div>

<div class="kanban-board" id="kanbanBoard">
<?php foreach ($cols as $slug => $col): ?>
<div class="kanban-col" data-slug="<?= $slug ?>">
  <div class="col-expand-overlay" onclick="toggleKanbanCol('<?= $slug ?>')" title="Show column"></div>
  <div class="kanban-col-hdr bg-<?= $col['color'] ?>"
       style="<?= in_array($col['color'],['secondary','info','primary','warning'])?'':'color:#000' ?>"
       draggable="true" data-col-slug="<?= $slug ?>" title="Drag to reorder column">
    <span class="col-grip">?</span>
    <span class="flex-grow-1"><?= e($col['label']) ?></span>
    <span class="badge bg-dark bg-opacity-25" id="cnt-<?= $slug ?>"><?= count($col['entries']) ?></span>
    <button class="col-toggle-btn ms-1" onclick="event.stopPropagation();toggleKanbanCol('<?= $slug ?>')" title="Hide column"
            style="background:rgba(0,0,0,.2);border:none;border-radius:4px;padding:1px 5px;cursor:pointer;color:inherit;line-height:1.4">
      <i class="bi bi-eye-slash" id="ctoggle-<?= $slug ?>" style="font-size:.75rem"></i>
    </button>
  </div>
  <div class="kanban-cards" data-status="<?= $slug ?>" id="col-<?= $slug ?>">
    <?php foreach ($col['entries'] as $e): ?>
    <div class="kanban-card" draggable="<?= Auth::canEdit('entries') || Auth::canEditEntry($e) ? 'true' : 'false' ?>"
         data-entry-id="<?= $e['id'] ?>"
         data-status="<?= e($e['status']) ?>"
         data-title="<?= strtolower(e($e['title']??'')) ?>"
         data-type="<?= e($e['type_name']??'') ?>">
      <div class="d-flex gap-1 mb-2 flex-wrap">
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
      <?php $childrenMap = $childrenMap ?? []; $subCards = $childrenMap[$e['id']] ?? []; ?>
      <?php if (!empty($subCards)): ?>
      <div class="mt-2 border-top border-secondary pt-2">
        <div class="d-flex align-items-center justify-content-between" style="cursor:pointer"
             onclick="toggleKanbanSub(<?= $e['id'] ?>, this)">
          <span class="text-info" style="font-size:.65rem">
            <i class="bi bi-diagram-2 me-1"></i><?= count($subCards) ?> Sub-Ticket<?= count($subCards)>1?'s':'' ?>
          </span>
          <i class="bi bi-chevron-right text-info" style="font-size:.6rem" id="ksubchev-<?= $e['id'] ?>"></i>
        </div>
        <div id="ksub-<?= $e['id'] ?>" style="display:none;margin-top:6px">
          <?php foreach ($subCards as $sub): ?>
          <div class="d-flex align-items-start gap-1 py-1 border-bottom border-secondary" style="font-size:.72rem">
            <i class="bi bi-arrow-return-right text-info flex-shrink-0 mt-1" style="font-size:.6rem"></i>
            <div class="flex-grow-1">
              <a href="<?= url('entries/'.$sub['id']) ?>" class="text-white text-decoration-none"
                 onclick="event.stopPropagation()"><?= e($sub['title'] ?: '(no title)') ?></a>
              <div class="text-muted" style="font-size:.65rem"><?= e($sub['status']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<div id="kanbanToast" class="toast align-items-center position-fixed bottom-0 end-0 m-3 bg-dark border-secondary"
     role="alert" style="z-index:9999;display:none;min-width:220px">
  <div class="d-flex p-2 gap-2 align-items-center">
    <i class="bi bi-check-circle text-success" id="kanbanToastIcon"></i>
    <span id="kanbanToastMsg" class="small flex-grow-1"></span>
    <button class="btn-close btn-close-white btn-sm" onclick="document.getElementById('kanbanToast').style.display='none'"></button>
  </div>
</div>

<script>
(function() {
  const CSRF         = '<?= e($_kCsrf) ?>';
  const COL_ORDER_KEY = '<?= e($_kBoardKey) ?>';

  // ?? State ?????????????????????????????????????????????????????
  let _dragCard = null, _placeholder = null;
  let _dragCol  = null;  // column being dragged for reorder
  let _isDraggingCol = false;

  // ?? Toast ?????????????????????????????????????????????????????
  function showKanbanToast(msg, ok = true) {
    const t = document.getElementById('kanbanToast');
    document.getElementById('kanbanToastMsg').textContent = msg;
    document.getElementById('kanbanToastIcon').className = ok
      ? 'bi bi-check-circle text-success' : 'bi bi-x-circle text-danger';
    t.style.display = '';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 2500);
  }

  function updateCount(slug) {
    const el = document.getElementById('cnt-' + slug);
    const col = document.getElementById('col-' + slug);
    if (el && col) el.textContent = col.querySelectorAll('.kanban-card:not(.drag-placeholder)').length;
  }

  // ?? Column order persistence ??????????????????????????????????
  function saveColOrder() {
    const board = document.getElementById('kanbanBoard');
    const order = Array.from(board.querySelectorAll('.kanban-col')).map(c => c.dataset.slug);
    localStorage.setItem(COL_ORDER_KEY, JSON.stringify(order));
  }

  function applyColOrder() {
    const saved = JSON.parse(localStorage.getItem(COL_ORDER_KEY) || 'null');
    if (!saved || !saved.length) return;
    const board = document.getElementById('kanbanBoard');
    saved.forEach(slug => {
      const col = board.querySelector(`.kanban-col[data-slug="${slug}"]`);
      if (col) board.appendChild(col); // moves to end in saved order
    });
  }

  window.resetKanbanColOrder = function() {
    localStorage.removeItem(COL_ORDER_KEY);
    location.reload();
  };

  // ?? Column drag-to-reorder ????????????????????????????????????
  document.querySelectorAll('#kanbanBoard .kanban-col-hdr').forEach(hdr => {
    hdr.addEventListener('dragstart', e => {
      _isDraggingCol = true;
      _dragCol = hdr.closest('.kanban-col');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('application/kanban-col', _dragCol.dataset.slug);
      setTimeout(() => _dragCol.classList.add('col-dragging'), 0);
    });

    hdr.addEventListener('dragend', () => {
      _isDraggingCol = false;
      _dragCol?.classList.remove('col-dragging');
      document.querySelectorAll('#kanbanBoard .kanban-col').forEach(c => c.classList.remove('col-drag-over'));
      _dragCol = null;
    });
  });

  document.querySelectorAll('#kanbanBoard .kanban-col').forEach(col => {
    col.addEventListener('dragover', e => {
      if (_isDraggingCol) {
        // Column reorder mode
        e.preventDefault();
        if (_dragCol && _dragCol !== col) {
          col.classList.add('col-drag-over');
          const board = document.getElementById('kanbanBoard');
          const cols  = Array.from(board.querySelectorAll('.kanban-col'));
          const srcIdx = cols.indexOf(_dragCol);
          const tgtIdx = cols.indexOf(col);
          if (srcIdx < tgtIdx) col.after(_dragCol);
          else col.before(_dragCol);
        }
      } else {
        // Card drop mode
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        col.querySelector('.kanban-cards')?.classList.add('drag-over');
        if (_placeholder && !col.contains(_placeholder))
          col.querySelector('.kanban-cards')?.appendChild(_placeholder);
      }
    });

    col.addEventListener('dragleave', e => {
      if (_isDraggingCol) {
        if (!col.contains(e.relatedTarget)) col.classList.remove('col-drag-over');
      } else {
        if (!col.contains(e.relatedTarget))
          col.querySelector('.kanban-cards')?.classList.remove('drag-over');
      }
    });

    col.addEventListener('drop', e => {
      e.preventDefault();
      col.classList.remove('col-drag-over');
      col.querySelector('.kanban-cards')?.classList.remove('drag-over');

      if (_isDraggingCol) {
        // Save new column order
        saveColOrder();
        showKanbanToast('Column order saved', true);
        return;
      }

      // Card drop
      if (!_dragCard) return;
      const cards   = col.querySelector('.kanban-cards');
      const newStatus = col.dataset.slug ?? col.querySelector('.kanban-cards')?.dataset.status;
      const oldStatus = _dragCard.dataset.status;
      const entryId   = _dragCard.dataset.entryId;

      if (_placeholder && cards?.contains(_placeholder)) {
        cards.insertBefore(_dragCard, _placeholder);
      } else {
        cards?.appendChild(_dragCard);
      }
      _placeholder?.remove();

      if (!newStatus || newStatus === oldStatus) return;

      // Remember original column to revert on error
      const origStatusCol = document.getElementById('col-' + oldStatus);

      _dragCard.style.opacity = '.5';
      fetch('<?= url('entries/') ?>' + entryId + '/status', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ _csrf: CSRF, status: newStatus })
      })
      .then(r => {
        if (!r.ok && r.status !== 400 && r.status !== 403 && r.status !== 422) {
          throw new Error('HTTP ' + r.status);
        }
        return r.json().catch(() => ({ success: false, error: 'Ungültige Serverantwort (HTTP ' + r.status + ')' }));
      })
      .then(d => {
        _dragCard.style.opacity = '';
        if (d.success) {
          _dragCard.dataset.status = newStatus;
          updateCount(oldStatus);
          updateCount(newStatus);
          showKanbanToast('Status ? ' + d.label, true);
        } else {
          // Revert: move card back to original column
          if (origStatusCol) origStatusCol.prepend(_dragCard);
          updateCount(oldStatus);
          updateCount(newStatus);
          showKanbanToast(d.error || 'Keine Berechtigung', false);
        }
      })
      .catch(err => {
        _dragCard.style.opacity = '';
        if (origStatusCol) origStatusCol.prepend(_dragCard);
        updateCount(oldStatus);
        updateCount(newStatus);
        showKanbanToast('Fehler: ' + err.message, false);
      });
    });
  });

  // ?? Card drag ?????????????????????????????????????????????????
  document.querySelectorAll('#kanbanBoard .kanban-card').forEach(card => {
    card.addEventListener('dragstart', e => {
      if (_isDraggingCol) return; // don't start card drag if column is being dragged
      _dragCard = card;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.entryId);
      setTimeout(() => card.classList.add('dragging'), 0);
      _placeholder = document.createElement('div');
      _placeholder.className = 'kanban-card drag-placeholder';
      _placeholder.style.height = card.offsetHeight + 'px';
    });

    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
      _placeholder?.remove(); _placeholder = null;
      _dragCard = null;
    });

    card.addEventListener('dragover', e => {
      if (_isDraggingCol || !_placeholder || !_dragCard || _dragCard === card) return;
      e.preventDefault();
      const mid = card.getBoundingClientRect().top + card.offsetHeight / 2;
      if (e.clientY < mid) card.before(_placeholder);
      else card.after(_placeholder);
    });
  });

  // ?? Apply saved column order on load ?????????????????????????
  applyColOrder();

})();
// Sub-ticket expand/collapse in kanban card
var _kSubCollapsed = {};
function toggleKanbanSub(entryId, trigger) {
  var currentlyCollapsed = _kSubCollapsed[entryId] !== undefined ? _kSubCollapsed[entryId] : true;
  _kSubCollapsed[entryId] = !currentlyCollapsed;
  var collapsed = _kSubCollapsed[entryId];
  var container = document.getElementById('ksub-' + entryId);
  var chev      = document.getElementById('ksubchev-' + entryId);
  if (container) container.style.display = collapsed ? 'none' : '';
  if (chev) { chev.className = (collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-down') + ' text-info'; chev.style.fontSize='.6rem'; }
}

// Column collapse toggle
function toggleKanbanCol(slug) {
  var col  = document.querySelector('.kanban-col[data-slug="' + slug + '"]');
  var icon = document.getElementById('ctoggle-' + slug);
  var btn  = col && col.querySelector('.col-toggle-btn');
  if (!col) return;
  var isCollapsed = col.classList.contains('kanban-col-collapsed');
  col.classList.toggle('kanban-col-collapsed', !isCollapsed);
  if (icon) icon.className = isCollapsed ? 'bi bi-eye-slash' : 'bi bi-eye';
  if (btn)  btn.title      = isCollapsed ? 'Hide column' : 'Show column';
  try { localStorage.setItem('rd_kcol_' + slug, isCollapsed ? '0' : '1'); } catch(x) {}
}
// Restore collapsed state on load
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.kanban-col[data-slug]').forEach(function(col) {
    var slug = col.dataset.slug;
    try {
      if (localStorage.getItem('rd_kcol_' + slug) === '1') {
        col.classList.add('kanban-col-collapsed');
        var icon = document.getElementById('ctoggle-' + slug);
        if (icon) icon.className = 'bi bi-eye';
      }
    } catch(x) {}
  });
});
</script>