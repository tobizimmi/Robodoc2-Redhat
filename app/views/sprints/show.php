<?php
$priColors = ['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];
$priStyle  = fn($p) => ($priColors[$p]??'secondary')==='orange' ? 'background:#f97316' : 'background:var(--bs-'.($priColors[$p]??'secondary').')';
?>

<!-- Header -->
<div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('sprints') ?>"><i class="bi bi-arrow-left"></i></a>
      <h4 class="mb-0"><i class="bi bi-lightning-charge text-warning me-2"></i><?= e($sprint['name']) ?></h4>
      <?php $sc=['planning'=>'info','active'=>'success','completed'=>'secondary']; ?>
      <span class="badge bg-<?= $sc[$sprint['status']] ?>"><?= ucfirst($sprint['status']) ?></span>
    </div>
    <?php if ($sprint['goal']): ?>
    <div class="text-muted small ms-5"><i class="bi bi-bullseye me-1"></i><?= e($sprint['goal']) ?></div>
    <?php endif; ?>
    <?php if ($sprint['start_date'] || $sprint['end_date']): ?>
    <div class="text-muted small ms-5 mt-1">
      <i class="bi bi-calendar3 me-1"></i>
      <?= $sprint['start_date'] ? formatDate($sprint['start_date'],'d.m.Y') : '?' ?> ?
      <?= $sprint['end_date']   ? formatDate($sprint['end_date'],  'd.m.Y') : '?' ?>
      <?php if ($stats['daysLeft'] !== null): ?>
      <span class="badge <?= $stats['daysLeft']===0?'bg-danger':($stats['daysLeft']<=3?'bg-warning text-dark':'bg-secondary') ?> ms-1" style="font-size:.6rem">
        <?= $stats['daysLeft']===0 ? 'Due today' : $stats['daysLeft'].' days left' ?>
      </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($sprint['status']==='planning'): ?>
    <form method="POST" action="<?= url('sprints/'.$sprint['id'].'/start') ?>">
      <?= csrfField() ?><button class="btn btn-success btn-sm"><i class="bi bi-play-fill me-1"></i>Start Sprint</button>
    </form>
    <?php elseif ($sprint['status']==='active'): ?>
    <form method="POST" action="<?= url('sprints/'.$sprint['id'].'/complete') ?>">
      <?= csrfField() ?><button class="btn btn-outline-success btn-sm"><i class="bi bi-check2-all me-1"></i>Complete Sprint</button>
    </form>
    <?php elseif ($sprint['status']==='completed'): ?>
    <form method="POST" action="<?= url('sprints/'.$sprint['id'].'/reopen') ?>">
      <?= csrfField() ?><button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Re-open</button>
    </form>
    <?php endif; ?>
    <a href="<?= url('sprints/'.$sprint['id'].'/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
    <form method="POST" action="<?= url('sprints/'.$sprint['id'].'/delete') ?>" data-confirm="Delete this sprint? Tickets will NOT be deleted.">
      <?= csrfField() ?><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
    </form>
  </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <?php
  $statCards = [
    ['Total',       $stats['total'],      'secondary', 'ticket-detailed'],
    ['Done',        $stats['done'],       'success',   'check-circle'],
    ['Rejected',    $stats['rejected'],   'danger',    'x-circle'],
    ['In Progress', $stats['inprog'],     'warning',   'arrow-repeat'],
    ['Not Started', $stats['notStarted'],'secondary', 'circle'],
  ];
  ?>
  <?php foreach ($statCards as [$lbl,$val,$col,$ico]): ?>
  <div class="col-6 col-md-2">
    <div class="card stat-card text-center" style="border-left-color:var(--bs-<?= $col ?>)">
      <div class="card-body p-2">
        <div class="text-muted small"><?= $lbl ?></div>
        <div class="stat-number"><?= $val ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="col-6 col-md-2">
    <div class="card stat-card text-center" style="border-left-color:var(--bs-info)">
      <div class="card-body p-2">
        <div class="text-muted small">Points</div>
        <div class="stat-number">
          <?= $stats['donePts'] ?><span class="text-muted fs-6">/<?= $stats['totalPts'] ?></span>
          <?php if ($stats['capacity']): ?>
          <div class="text-muted" style="font-size:.65rem">Cap: <?= $stats['capacity'] ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Progress bar -->
<div class="mb-4">
  <div class="d-flex justify-content-between mb-1 small text-muted">
    <span>Progress</span><span><?= $stats['pct'] ?>%</span>
  </div>
  <div class="progress" style="height:10px">
    <div class="progress-bar bg-success" style="width:<?= $stats['pct'] ?>%" title="<?= $stats['done'] ?> done"></div>
    <div class="progress-bar bg-danger" style="width:<?= $stats['total']>0 ? round($stats['rejected']/$stats['total']*100) : 0 ?>%" title="<?= $stats['rejected'] ?> rejected"></div>
    <div class="progress-bar bg-warning" style="width:<?= $stats['total']>0 ? round($stats['inprog']/$stats['total']*100) : 0 ?>%;" title="<?= $stats['inprog'] ?> in progress"></div>
  </div>
</div>

<!-- View tabs -->
<ul class="nav nav-tabs mb-3 border-secondary" id="sprintTabs">
  <li class="nav-item"><a class="nav-link active text-white" data-bs-toggle="tab" href="#tabBoard">Board</a></li>
  <li class="nav-item"><a class="nav-link text-white" data-bs-toggle="tab" href="#tabList">List</a></li>
  <li class="nav-item"><a class="nav-link text-white" data-bs-toggle="tab" href="#tabAdd" id="tabAddLink">Add Tickets</a></li>
  <li class="nav-item"><a class="nav-link text-white" data-bs-toggle="tab" href="#tabManage" id="retro">Retrospective &amp; Management</a></li>
</ul>

<div class="tab-content">

<!-- ?? BOARD VIEW ?? -->
<div class="tab-pane fade show active" id="tabBoard">
  <div class="d-flex justify-content-end mb-2">
    <button class="btn btn-outline-success btn-sm" onclick="showSprintTab('tabAdd')">
      <i class="bi bi-plus-lg me-1"></i>Add Tickets
    </button>
  </div>
  <?php if (!$entries): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-lightning-charge fs-1 d-block mb-2 opacity-25"></i>
    No tickets in this sprint yet.
    <a href="#" class="ms-1" onclick="showSprintTab('tabAdd');return false">Add tickets now</a>
  </div>
  <?php else: ?>
  <?php $topEntries = $topEntries ?? array_filter($entries, fn($e) => !empty($e['is_top'])); ?>
  <?php if ($topEntries): ?>
  <div class="card border-warning mb-3">
    <div class="card-header border-warning bg-warning bg-opacity-10 py-2 d-flex align-items-center gap-2">
      <i class="bi bi-star-fill text-warning"></i>
      <span class="fw-semibold small text-warning">Top Tickets <span class="badge bg-warning text-dark ms-1"><?= count($topEntries) ?></span></span>
    </div>
    <div class="list-group list-group-flush">
      <?php foreach ($topEntries as $e): ?>
      <div class="list-group-item bg-dark border-secondary d-flex align-items-center gap-3 py-2">
        <i class="bi bi-star-fill text-warning flex-shrink-0"></i>
        <a href="<?= url('entries/'.$e['id']) ?>" class="text-white text-decoration-none fw-semibold small flex-grow-1"><?= e($e['title'] ?: '?') ?></a>
        <span class="badge" style="font-size:.6rem;<?= $priStyle($e['priority']??'Medium') ?>"><?= e($e['priority']??'Medium') ?></span>
        <span class="badge bg-<?= entryStatusColor($e['status']??'new') ?>" style="font-size:.6rem"><?= entryStatuses()[$e['status']??'new']??$e['status'] ?></span>
        <?php if ($e['jira_issue_key']): ?><span class="text-warning small"><i class="bi bi-bug-fill"></i> <?= e($e['jira_issue_key']) ?></span><?php endif; ?>
        <button class="btn btn-outline-warning btn-sm py-0 px-2 top-toggle-btn flex-shrink-0"
                data-sprint="<?= $sprint['id'] ?>" data-entry="<?= $e['id'] ?>" data-is-top="1"
                onclick="toggleTop(this)" title="Als Top Ticket entfernen">
          <i class="bi bi-star-fill" style="font-size:.7rem"></i>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php
  // Only show columns that have entries or are key defaults
  $cols = array_filter($board, fn($c,$slug) =>
    !empty($c['entries']) || in_array($slug, ['new','internal','finished']),
    ARRAY_FILTER_USE_BOTH
  );
  $_kBoardKey = 'kanbanColOrder_sprint_' . $sprint['id'];
  include __DIR__ . '/../kanban/_board.php';
  ?>
  <div class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Drag cards between columns to update status automatically.</div>
  <script>
  // Inject star toggle buttons onto all board cards for this sprint
  (function() {
    var sprintId = <?= (int)$sprint['id'] ?>;
    var topIds   = new Set([<?= implode(',', array_column(array_filter($entries, fn($e) => !empty($e['is_top'])), 'id')) ?>]);
    document.querySelectorAll('#tabBoard .kanban-card[data-entry-id]').forEach(function(card) {
      var eid = card.dataset.entryId;
      if (!eid || card.querySelector('.board-top-btn')) return; // already added
      var isTop = topIds.has(parseInt(eid));
      var btn = document.createElement('button');
      btn.className = 'board-top-btn btn btn-sm py-0 px-1 ' + (isTop ? 'btn-warning' : 'btn-outline-secondary');
      btn.style.cssText = 'font-size:.65rem;position:absolute;top:5px;right:5px;line-height:1;z-index:2';
      btn.title = isTop ? 'Als Top Ticket entfernen' : 'Als Top Ticket markieren';
      btn.innerHTML = '<i class="bi bi-' + (isTop ? 'star-fill' : 'star') + '"></i>';
      btn.dataset.sprint = sprintId;
      btn.dataset.entry  = eid;
      btn.addEventListener('click', function(ev) { ev.stopPropagation(); ev.preventDefault(); toggleTop(btn); });
      card.style.position = 'relative';
      card.appendChild(btn);
    });
  })();
  </script>
  <?php endif; ?>
</div>

<!-- ?? LIST VIEW ?? -->
<div class="tab-pane fade" id="tabList">
  <?php if (!$entries): ?>
  <div class="text-center text-muted py-5">No tickets in this sprint.</div>
  <?php else: ?>

  <!-- Filter bar -->
  <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
    <input type="text" id="listSearch" class="form-control form-control-sm" style="max-width:240px"
           placeholder="Search title?" oninput="filterSprintList()">
    <select id="listTypeFilter" class="form-select form-select-sm" style="max-width:160px" onchange="filterSprintList()">
      <option value="">All Types</option>
      <?php foreach (array_unique(array_column($entries,'type_name')) as $tn): ?>
      <option value="<?= e($tn) ?>"><?= e($tn) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="listStatusFilter" class="form-select form-select-sm" style="max-width:160px" onchange="filterSprintList()">
      <option value="">All Statuses</option>
      <?php foreach (entryStatuses() as $sv => $sl): ?>
      <option value="<?= $sv ?>"><?= e($sl) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="listPriFilter" class="form-select form-select-sm" style="max-width:140px" onchange="filterSprintList()">
      <option value="">All Priorities</option>
      <?php foreach (['Blocker','Highest','High','Medium','Low'] as $prio): ?>
      <option value="<?= $prio ?>"><?= $prio ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary btn-sm" onclick="clearSprintListFilter()" title="Clear filters">
      <i class="bi bi-x-lg"></i>
    </button>
    <span class="text-muted small ms-auto" id="listFilterInfo"></span>
  </div>

  <div class="card">
    <?php $topEntries = array_filter($entries, fn($e) => !empty($e['is_top'])); ?>
    <?php if ($topEntries): ?>
    <div class="card border-warning mb-3">
      <div class="card-header border-warning bg-warning bg-opacity-10 py-2 d-flex align-items-center gap-2">
        <i class="bi bi-star-fill text-warning"></i>
        <span class="fw-semibold small text-warning">Top Tickets <span class="badge bg-warning text-dark ms-1"><?= count($topEntries) ?></span></span>
        <span class="text-muted small ms-2">Prioritaet-Fokus fuer diesen Sprint</span>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($topEntries as $e): ?>
        <div class="list-group-item bg-dark border-secondary d-flex align-items-center gap-3 py-2">
          <i class="bi bi-star-fill text-warning flex-shrink-0" style="font-size:.9rem"></i>
          <a href="<?= url('entries/'.$e['id']) ?>" class="text-white text-decoration-none fw-semibold small flex-grow-1">
            <?= e($e['title'] ?: '?') ?>
          </a>
          <span class="badge" style="font-size:.6rem;<?= $priStyle($e['priority']??'Medium') ?>"><?= e($e['priority']??'Medium') ?></span>
          <span class="badge bg-<?= entryStatusColor($e['status']??'new') ?>" style="font-size:.6rem"><?= entryStatuses()[$e['status']??'new']??$e['status'] ?></span>
          <?php if ($e['jira_issue_key']): ?>
          <span class="text-warning small"><i class="bi bi-bug-fill"></i> <?= e($e['jira_issue_key']) ?></span>
          <?php endif; ?>
          <button class="btn btn-outline-warning btn-sm py-0 px-2 top-toggle-btn flex-shrink-0"
                  data-sprint="<?= $sprint['id'] ?>" data-entry="<?= $e['id'] ?>" data-is-top="1"
                  onclick="toggleTop(this)" title="Als Top Ticket entfernen">
            <i class="bi bi-star-fill" style="font-size:.7rem"></i>
          </button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-dark table-hover table-sm mb-0 align-middle">
        <thead>
          <tr>
            <th class="ps-3" style="width:36px">#</th>
            <th>Title</th>
            <th>Type</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Project</th>
            <th>Jira</th>
            <th class="text-center">Points</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="sprintListBody">
          <?php foreach ($entries as $i => $e): ?>
          <tr class="sprint-list-row"
              data-title="<?= strtolower(e($e['title']??'')) ?>"
              data-type="<?= e($e['type_name']??'') ?>"
              data-status="<?= e($e['status']??'') ?>"
              data-priority="<?= e($e['priority']??'Medium') ?>">
            <td class="ps-3 text-muted small sprint-row-num"><?= $i+1 ?></td>
            <td class="text-center">
              <button class="btn btn-sm py-0 px-1 top-toggle-btn <?= !empty($e['is_top']) ? 'btn-warning' : 'btn-outline-secondary' ?>"
                      data-sprint="<?= $sprint['id'] ?>" data-entry="<?= $e['id'] ?>" data-is-top="<?= !empty($e['is_top']) ? 1 : 0 ?>"
                      onclick="toggleTop(this)" title="Als Top Ticket markieren">
                <i class="bi bi-<?= !empty($e['is_top']) ? 'star-fill' : 'star' ?>" style="font-size:.75rem"></i>
              </button>
            </td>
            <td>
              <a href="<?= url('entries/'.$e['id']) ?>" class="text-white text-decoration-none fw-semibold" style="font-size:.85rem">
                <?= e($e['title'] ?: '?') ?>
              </a>
            </td>
            <td>
              <span class="badge" style="background:<?= e($e['type_color']) ?>;cursor:pointer"
                    onclick="setSprintListFilter('type','<?= e(addslashes($e['type_name']??'')) ?>')">
                <?= e($e['type_name']) ?>
              </span>
            </td>
            <td>
              <span class="badge bg-<?= entryStatusColor($e['status']??'new') ?>" style="font-size:.65rem;cursor:pointer"
                    onclick="setSprintListFilter('status','<?= e($e['status']??'new') ?>')">
                <?= entryStatuses()[$e['status']??'new']??$e['status'] ?>
              </span>
            </td>
            <td>
              <?php $pv=$e['priority']??'Medium'; ?>
              <span class="badge" style="font-size:.65rem;cursor:pointer;<?= $priStyle($pv) ?>"
                    onclick="setSprintListFilter('priority','<?= $pv ?>')">
                <?= $pv ?>
              </span>
            </td>
            <td class="small text-muted"><?= e($e['project_name']) ?></td>
            <td class="small">
              <?php if ($e['jira_issue_key']): ?>
              <span class="text-warning"><i class="bi bi-bug-fill"></i> <?= e($e['jira_issue_key']) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <input type="number" class="form-control form-control-sm text-center bg-dark text-white border-secondary story-pts-input"
                     style="width:60px;display:inline-block" min="0" max="99"
                     value="<?= $e['story_points'] !== null ? $e['story_points'] : '' ?>"
                     placeholder="?"
                     data-sprint="<?= $sprint['id'] ?>" data-entry="<?= $e['id'] ?>"
                     title="Story points">
            </td>
            <td>
              <form method="POST" action="<?= url('sprints/'.$sprint['id'].'/entries/'.$e['id'].'/remove') ?>">
                <?= csrfField() ?>
                <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Remove"><i class="bi bi-x-lg" style="font-size:.7rem"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ?? ADD TICKETS ?? -->
<div class="tab-pane fade" id="tabAdd">
  <?php if (!$available): ?>
  <div class="text-center text-muted py-5">All entries are already in this sprint.</div>
  <?php else: ?>
  <form method="POST" action="<?= url('sprints/'.$sprint['id'].'/entries') ?>" id="addTicketsForm">
    <?= csrfField() ?>

    <!-- Filter bar -->
    <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
      <input type="text" id="availSearch" class="form-control form-control-sm" style="max-width:260px"
             placeholder="Filter by title?" oninput="filterAvail()">
      <select id="availProject" class="form-select form-select-sm" style="max-width:180px" onchange="filterAvail()">
        <option value="">All Projects</option>
        <?php foreach ($projects as $proj): ?>
        <option value="<?= e($proj['name']) ?>"><?= e($proj['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="availStatus" class="form-select form-select-sm" style="max-width:160px" onchange="filterAvail()">
        <option value="">All Statuses</option>
        <?php foreach (entryStatuses() as $sv => $sl): ?>
        <option value="<?= $sv ?>"><?= e($sl) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="d-flex align-items-center gap-1 small ms-auto text-muted" style="cursor:pointer">
        <input type="checkbox" id="selectAllAvail" class="form-check-input" onchange="toggleSelectAllAvail(this.checked)">
        Select all visible
      </label>
      <span class="badge bg-primary" id="availSelCount" style="display:none">0 selected</span>
    </div>

    <!-- Ticket list -->
    <div class="card">
      <div class="list-group list-group-flush" id="availList" style="max-height:480px;overflow-y:auto">
        <?php foreach ($available as $ae):
          $priC=['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];
          $pv=$ae['priority']??'Medium'; $pc=$priC[$pv]??'secondary';
          $pstyle=$pc==='orange'?'background:#f97316':'background:var(--bs-'.$pc.')';
        ?>
        <label class="list-group-item list-group-item-action bg-transparent border-secondary avail-row py-2 px-3"
               style="cursor:pointer"
               data-title="<?= strtolower(e($ae['title']??'')) ?>"
               data-project="<?= e($ae['project_name']??'') ?>"
               data-status="<?= e($ae['status']??'') ?>">
          <div class="d-flex align-items-center gap-3">
            <input type="checkbox" class="form-check-input flex-shrink-0 avail-cb" name="entry_ids[]"
                   value="<?= $ae['id'] ?>" onclick="updateSelCount(event)">
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <span class="badge" style="background:<?= e($ae['type_color']) ?>;font-size:.6rem"><?= e($ae['type_name']) ?></span>
                <span class="badge bg-<?= entryStatusColor($ae['status']??'new') ?>" style="font-size:.6rem">
                  <?= entryStatuses()[$ae['status']??'new'] ?? $ae['status'] ?>
                </span>
                <span class="badge" style="font-size:.6rem;<?= $pstyle ?>"><?= $pv ?></span>
                <?php if ($ae['jira_issue_key']): ?>
                <span class="text-warning" style="font-size:.7rem"><i class="bi bi-bug-fill"></i> <?= e($ae['jira_issue_key']) ?></span>
                <?php endif; ?>
                <span class="text-muted ms-auto" style="font-size:.7rem"><?= e($ae['project_name']) ?> ? <?= formatDate($ae['entry_date'],'d.m.Y') ?></span>
              </div>
              <div class="fw-semibold" style="font-size:.85rem"><?= e($ae['title'] ?: '(no title)') ?></div>
            </div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3 align-items-center">
      <button type="submit" class="btn btn-primary" id="addTicketsBtn">
        <i class="bi bi-plus-lg me-1"></i>Add selected tickets to sprint
      </button>
      <span class="text-muted small"><?= count($available) ?> ticket(s) not yet in sprint</span>
    </div>
  </form>
  <?php endif; ?>
</div>

<!-- ?? RETROSPECTIVE + MANAGEMENT ?? -->
<div class="tab-pane fade" id="tabManage">
  <div class="row g-4">
    <!-- Retrospective -->
    <div class="col-md-6">
      <div class="card">
        <div class="card-header border-secondary fw-semibold small"><i class="bi bi-chat-square-text me-1"></i>Sprint Retrospective</div>
        <div class="card-body p-3">
          <form method="POST" action="<?= url('sprints/'.$sprint['id'].'/retro') ?>">
            <?= csrfField() ?>
            <div class="mb-3">
              <label class="form-label small text-muted">Notes ? What went well? What to improve? What to do differently?</label>
              <textarea name="retro_notes" class="form-control" rows="8"
                        placeholder="? What went well&#10;? What could be improved&#10;? Action items"><?= e($sprint['retro_notes'] ?? '') ?></textarea>
            </div>
            <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Retrospective</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Copy incomplete / management -->
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header border-secondary fw-semibold small"><i class="bi bi-arrow-right-square me-1"></i>Copy Incomplete Tickets</div>
        <div class="card-body p-3">
          <p class="text-muted small mb-3">Move tickets that are NOT finished or rejected to another sprint.</p>
          <form method="POST" action="<?= url('sprints/'.$sprint['id'].'/copy-incomplete') ?>">
            <?= csrfField() ?>
            <div class="input-group">
              <select name="target_sprint_id" class="form-select form-select-sm" required>
                <option value="">? Select target sprint ?</option>
                <?php foreach ($otherSprints as $os): ?>
                <option value="<?= $os['id'] ?>"><?= e($os['name']) ?> (<?= $os['status'] ?>)</option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-warning btn-sm"><i class="bi bi-copy me-1"></i>Copy</button>
            </div>
          </form>
          <div class="text-muted mt-2" style="font-size:.75rem">
            <?php $incomplete = count(array_filter($entries, fn($e)=>!in_array($e['status'],['finished','finalized','rejected']))); ?>
            <?= $incomplete ?> ticket(s) would be copied.
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header border-secondary fw-semibold small"><i class="bi bi-bar-chart me-1"></i>Summary</div>
        <div class="card-body p-3">
          <table class="table table-sm table-dark mb-0 small">
            <tr><td class="text-muted">Total tickets</td><td class="fw-semibold"><?= $stats['total'] ?></td></tr>
            <tr><td class="text-muted">Done (finished)</td><td class="fw-semibold text-success"><?= $stats['done'] ?></td></tr>
            <tr><td class="text-muted">Rejected</td><td class="fw-semibold text-danger"><?= $stats['rejected'] ?></td></tr>
            <tr><td class="text-muted">In Progress</td><td class="fw-semibold text-warning"><?= $stats['inprog'] ?></td></tr>
            <tr><td class="text-muted">Not Started</td><td class="fw-semibold"><?= $stats['notStarted'] ?></td></tr>
            <tr><td class="text-muted">Story Points Done</td><td class="fw-semibold"><?= $stats['donePts'] ?> / <?= $stats['totalPts'] ?></td></tr>
            <?php if ($stats['capacity']): ?>
            <tr><td class="text-muted">Team Capacity</td><td class="fw-semibold"><?= $stats['capacity'] ?> pts</td></tr>
            <?php endif; ?>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

</div><!-- /tab-content -->

<script>
const _sprintCsrf = '<?= e(Auth::csrfToken()) ?>';

// ?? Story points auto-save ????????????????????????????????????
document.querySelectorAll('.story-pts-input').forEach(inp => {
  inp.addEventListener('change', function() {
    const pts = this.value.trim() === '' ? '' : parseInt(this.value);
    fetch(`<?= url('sprints/'.$sprint['id'].'/entries/') ?>${this.dataset.entry}/points`, {
      method: 'POST',
      body: new URLSearchParams({ _csrf: _sprintCsrf, story_points: pts })
    }).then(r => r.json()).then(d => {
      if (d.success && typeof showToast==='function') showToast('Points saved', 'success');
    });
  });
});

// ?? Available ticket filter (client-side) ?????????????????????
function filterAvail() {
  const q       = (document.getElementById('availSearch')?.value || '').toLowerCase().trim();
  const project = document.getElementById('availProject')?.value || '';
  const status  = document.getElementById('availStatus')?.value || '';
  document.querySelectorAll('.avail-row').forEach(row => {
    const titleMatch   = !q       || row.dataset.title.includes(q);
    const projectMatch = !project || row.dataset.project === project;
    const statusMatch  = !status  || row.dataset.status  === status;
    row.style.display  = (titleMatch && projectMatch && statusMatch) ? '' : 'none';
  });
  updateSelCount();
}

function toggleSelectAllAvail(checked) {
  document.querySelectorAll('.avail-row:not([style*="none"]) .avail-cb').forEach(cb => cb.checked = checked);
  updateSelCount();
}

function updateSelCount(e) {
  if (e) e.stopPropagation(); // prevent label double-toggle
  const n   = document.querySelectorAll('.avail-cb:checked').length;
  const el  = document.getElementById('availSelCount');
  const btn = document.getElementById('addTicketsBtn');
  if (el) { el.textContent = n + ' selected'; el.style.display = n ? '' : 'none'; }
  if (btn) btn.textContent = n ? `Add ${n} ticket(s) to sprint` : 'Add selected tickets to sprint';
}

// ?? Sprint list filter ????????????????????????????????????????
function filterSprintList() {
  const q       = (document.getElementById('listSearch')?.value || '').toLowerCase().trim();
  const type    = document.getElementById('listTypeFilter')?.value || '';
  const status  = document.getElementById('listStatusFilter')?.value || '';
  const prio    = document.getElementById('listPriFilter')?.value || '';
  let shown = 0, total = 0;
  document.querySelectorAll('.sprint-list-row').forEach(row => {
    total++;
    const match = (!q      || row.dataset.title.includes(q))
               && (!type   || row.dataset.type    === type)
               && (!status || row.dataset.status  === status)
               && (!prio   || row.dataset.priority === prio);
    row.style.display = match ? '' : 'none';
    if (match) shown++;
  });
  // Renumber visible rows
  let n = 1;
  document.querySelectorAll('.sprint-list-row:not([style*="none"]) .sprint-row-num').forEach(el => el.textContent = n++);
  const info = document.getElementById('listFilterInfo');
  const hasFilter = q || type || status || prio;
  if (info) info.textContent = hasFilter ? `${shown} / ${total}` : '';
}

function setSprintListFilter(field, value) {
  const map = { type: 'listTypeFilter', status: 'listStatusFilter', priority: 'listPriFilter' };
  const el = document.getElementById(map[field]);
  if (el) { el.value = value; filterSprintList(); }
}

function clearSprintListFilter() {
  ['listSearch','listTypeFilter','listStatusFilter','listPriFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  filterSprintList();
}

// ?? Switch to any sprint tab by pane ID ??????????????????????
function showSprintTab(paneId) {
  const trigger = document.querySelector(`[href="#${paneId}"]`);
  if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
}

// Restore last active tab from URL hash on page load
var _tabMap = {'#tabBoard':'tabBoard','#tabList':'tabList','#tabAdd':'tabAdd','#tabManage':'tabManage','#retro':'tabManage'};
var _initTab = _tabMap[window.location.hash];
if (_initTab) showSprintTab(_initTab);
// Update hash when tab changes so reload returns to same tab
document.querySelectorAll('#sprintTabs .nav-link').forEach(function(link) {
  link.addEventListener('shown.bs.tab', function() {
    history.replaceState(null, '', link.getAttribute('href'));
  });
});

var _topCsrf = '<?= e(Auth::csrfToken()) ?>';
function toggleTop(btn) {
  var s = btn.dataset.sprint, eid = btn.dataset.entry;
  btn.disabled = true;
  // Detect active tab so we can return to it after reload
  var activeTab = document.querySelector('#sprintTabs .nav-link.active');
  var tabHref = activeTab ? activeTab.getAttribute('href') : '';
  fetch('<?= url('sprints') ?>/' + s + '/entries/' + eid + '/top', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: _topCsrf})
  }).then(function(r) { return r.json(); }).then(function(d) {
    if (d.success) {
      // Reload with hash so the same tab reopens
      var hash = tabHref ? tabHref : '';
      location.href = location.pathname + (hash ? hash : '');
    } else {
      btn.disabled = false;
      alert(d.error || 'Fehler');
    }
  }).catch(function() { btn.disabled = false; });
}
</script>
