<?php $mergedEntries = $mergedEntries ?? []; $mergedInto = $mergedInto ?? null; ?>
<?php $parentEntry = $parentEntry ?? null; $subTickets = $subTickets ?? []; $entryEpic = $entryEpic ?? null; ?>
<?php if ($entryEpic): ?>
<div class="alert d-flex align-items-center gap-3 mb-3 py-2" style="background:<?= e($entryEpic['color']) ?>18;border:1px solid <?= e($entryEpic['color']) ?>40">
  <i class="bi bi-lightning-fill" style="color:<?= e($entryEpic['color']) ?>;font-size:1.1rem"></i>
  <div class="small flex-grow-1">
    <strong>Epic:</strong>
    <a href="<?= url('entries?epic_id='.$entryEpic['id']) ?>" class="ms-1 text-decoration-none fw-semibold" style="color:<?= e($entryEpic['color']) ?>">
      <?= e($entryEpic['title']) ?>
    </a>
  </div>
  <?php if (Auth::canEdit('entries')): ?>
  <button class="btn btn-sm py-0 px-2" style="border-color:<?= e($entryEpic['color']) ?>;color:<?= e($entryEpic['color']) ?>;font-size:.72rem"
          onclick="unsetEpic('<?= e(Auth::csrfToken()) ?>')" title="Epic-Zuordnung aufheben">
    <i class="bi bi-x-lg"></i>
  </button>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($parentEntry): ?>
<div class="alert alert-secondary d-flex align-items-center gap-3 mb-3 py-2">
  <i class="bi bi-diagram-2 fs-5 flex-shrink-0"></i>
  <div class="small">
    <strong>Sub-Ticket von:</strong>
    <a href="<?= url('entries/'.$parentEntry['id']) ?>" class="alert-link ms-1">
      #<?= $parentEntry['id'] ?> <?= e(mb_substr($parentEntry['title'],0,60)) ?>
    </a>
    <?php if (Auth::canEdit('entries')): ?>
    <button class="btn btn-sm btn-outline-secondary ms-2 py-0 px-2" style="font-size:.72rem"
            onclick="unsetParent('<?= e(Auth::csrfToken()) ?>')" title="Verknüpfung aufheben">
      <i class="bi bi-x-lg"></i> Trennen
    </button>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php if (!empty($entry['is_merged'])): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-3 py-2" role="alert">
  <i class="bi bi-arrow-right-circle-fill fs-5"></i>
  <div>
    <strong>Dieses Ticket wurde zusammengefuhrt</strong> und ist archiviert.
    <?php if ($mergedInto): ?>
    Es wurde in <a href="<?= url('entries/'.$mergedInto['id']) ?>" class="alert-link">
      #<?= $mergedInto['id'] ?> <?= e(mb_substr($mergedInto['title'],0,50)) ?></a> eingebunden.
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<div class="d-flex align-items-start justify-content-between mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('entries') ?>"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5 class="mb-0 fw-bold"><?= e($entry['title'] ?: 'Entry #' . $entry['id']) ?></h5>
      <div class="text-muted small">
        <?= formatDate($entry['entry_date']) ?> <?= substr($entry['entry_time'], 0, 5) ?>
        &middot; by <?= e($entry['creator'] ?? '?') ?>
        <!-- Tags -->
<?php $entryTags = $entryTags ?? []; $allTags = $allTags ?? []; ?>
<div class="d-flex align-items-center gap-2 flex-wrap mb-2" id="entryTagsRow">
  <?php foreach ($entryTags as $tag): ?>
  <span class="badge d-flex align-items-center gap-1" style="background:<?= e($tag['color']) ?>;font-size:.72rem">
    <i class="bi bi-tag-fill me-1" style="font-size:.6rem"></i><?= e($tag['name']) ?>
    <?php if (Auth::canEdit('tags')): ?>
    <button type="button" onclick="removeTag(<?= $tag['id'] ?>)" class="btn-close btn-close-white p-0 ms-1" style="font-size:.5rem"></button>
    <?php endif; ?>
  </span>
  <?php endforeach; ?>
  <?php if (Auth::canEdit('tags')): ?>
  <div style="position:relative;display:inline-block">
    <button type="button" class="badge bg-secondary border-0 d-flex align-items-center gap-1"
            style="font-size:.72rem;cursor:pointer"
            onclick="document.getElementById('tagPicker').style.display=document.getElementById('tagPicker').style.display==='none'?'block':'none'">
      <i class="bi bi-plus-lg"></i> Tag
    </button>
    <div id="tagPicker" style="display:none;position:absolute;top:100%;left:0;z-index:9999;background:#1e293b;border:1px solid #475569;border-radius:6px;padding:8px;min-width:200px;max-height:250px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.5)">
      <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." oninput="filterTagDropdown(this.value)" style="background:#0f172a;border-color:#475569;color:#fff">
      <?php if (!$allTags): ?>
      <div style="color:#94a3b8;font-size:.78rem;padding:4px 2px">No tags yet. <a href="<?= url('tags/manage') ?>" style="color:#facc15">Create tags</a></div>
      <?php endif; ?>
      <?php foreach ($allTags as $t): ?>
      <?php $isActive = in_array($t['id'], array_column($entryTags, 'id')); ?>
      <div class="d-flex align-items-center gap-2 px-1 py-1 rounded tag-option"
           style="cursor:pointer;font-size:.82rem;<?= $isActive ? 'opacity:.5' : '' ?>"
           data-name="<?= e(strtolower($t['name'])) ?>"
           onclick="toggleEntryTag(<?= $t['id'] ?>)">
        <div style="width:10px;height:10px;border-radius:50%;background:<?= e($t['color']) ?>;flex-shrink:0"></div>
        <span class="flex-grow-1" style="color:#fff"><?= e($t['name']) ?></span>
        <i class="bi bi-check-lg text-warning <?= $isActive ? '' : 'invisible' ?>" id="tck-<?= $t['id'] ?>"></i>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($entry['jira_issue_key'])): ?>
        &middot; <a href="<?= e($entry['jira_issue_url']) ?>" target="_blank" class="text-warning text-decoration-none">
          <i class="bi bi-bug-fill me-1"></i><sup style="font-size:.6em;font-weight:700">J</sup><?= e($entry['jira_issue_key']) ?>
        </a>
        <?php endif; ?>
        <?php if (!empty($entry['zentao_bug_id'])): ?>
        &middot; <a href="<?= e($entry['zentao_bug_url'] ?? '#') ?>" target="_blank" class="text-info text-decoration-none">
          <i class="bi bi-bug me-1"></i><sup style="font-size:.6em;font-weight:700">Z</sup>Bug #<?= e($entry['zentao_bug_id']) ?>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button type="button" id="todoBtn" onclick="toggleTodo(this)"
            class="btn btn-sm <?= $isTodo ? 'btn-warning' : 'btn-outline-secondary' ?>"
            title="<?= $isTodo ? 'Remove from Todo list' : 'Add to Todo list' ?>">
      <i class="bi bi-bookmark<?= $isTodo ? '-fill' : '' ?>"></i>
      <span class="ms-1 d-none d-md-inline" style="font-size:.75rem"><?= $isTodo ? 'Bookmarked' : 'Todo' ?></span>
    </button>
    <form method="POST" action="<?= url('entries/' . $entry['id'] . '/duplicate') ?>">
      <?= csrfField() ?>
      <button class="btn btn-outline-secondary btn-sm" title="Duplicate"><i class="bi bi-copy"></i></button>
    </form>
    <?php if (!empty($settings['jira_url'])): ?>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#wizardModal" title="Run standard creation wizard">
      <i class="bi bi-magic me-1"></i>Wizard
    </button>
    <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#jiraModal">
      <i class="bi bi-bug me-1"></i>Jira
    </button>
    <?php endif; ?>
    <?php if (!empty($settings['zentao_url'])): ?>
    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#zentaoModal">
      <i class="bi bi-bug me-1"></i>Zentao<?php if (!empty($entry['zentao_has_changes'])): ?> <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem">!</span><?php endif; ?>
    </button>
    <?php endif; ?>
    <?php if (!empty($settings['sharepoint_tenant_id']) && !empty($settings['sharepoint_client_id'])): ?>
    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#spModal">
      <i class="bi bi-cloud-arrow-up me-1"></i>SharePoint
    </button>
    <?php endif; ?>
    <form method="POST" action="<?= url('entries/'.$entry['id'].'/toggle-report-relevant') ?>" class="d-inline">
      <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
      <button type="submit" class="btn btn-sm <?= !empty($entry['is_report_relevant']) ? 'btn-success' : 'btn-outline-secondary' ?>"
              title="<?= !empty($entry['is_report_relevant']) ? 'Relevant for Reporting — klicken zum Entfernen' : 'Nicht relevant — klicken zum Markieren' ?>">
        <i class="bi bi-bar-chart-fill me-1"></i><?= !empty($entry['is_report_relevant']) ? 'Report ✓' : 'Report' ?>
      </button>
    </form>
    <button class="btn btn-outline-info btn-sm" onclick="openExportWizard(<?= $entry['id'] ?>)">
      <i class="bi bi-file-earmark-arrow-down me-1"></i>Export
    </button>
    <a href="<?= url('entries/' . $entry['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <?php if (Auth::canEdit('entries') && empty($entry['is_merged'])): ?>
    <button class="btn btn-outline-warning btn-sm" onclick="openMergeModal()" title="Tickets zusammenfuhren">
      <i class="bi bi-arrow-right-circle me-1"></i>Merge
    </button>
    <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#subTicketModal"
            title="Als Sub-Ticket verknupfen">
      <i class="bi bi-diagram-2 me-1"></i>Sub-Ticket
    </button>
    <?php if (Auth::canEdit('entries') || Auth::canOwn('entries')): ?>
    <a href="<?= url('entries/create') ?>?parent_id=<?= $entry['id'] ?>&project_id=<?= $entry['project_id'] ?>"
       class="btn btn-outline-success btn-sm"
       title="Neuen Sub-Entry zu diesem Eintrag erstellen">
      <i class="bi bi-plus-circle me-1"></i>Sub-Entry erstellen
    </a>
    <?php endif; ?>
    <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#epicModal"
            title="Epic zuordnen">
      <i class="bi bi-lightning-fill me-1"></i><?= $entryEpic ? e(mb_substr($entryEpic['title'],0,15)) : 'Epic' ?>
    </button>
    <?php endif; ?>
    <?php if (Auth::canEdit('eight_d') || Auth::canOwn('eight_d') || Auth::isAdmin()): ?>
    <?php if ($linkedEightD): ?>
    <?php foreach ($linkedEightD as $ed): ?>
    <a href="<?= url('8d/' . $ed['id']) ?>" class="btn btn-outline-warning btn-sm" title="8D-Bericht öffnen">
      <i class="bi bi-diagram-3 me-1"></i><?= e($ed['reference']) ?>
    </a>
    <?php endforeach; ?>
    <?php else: ?>
    <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#eightDModal"
            title="8D-Bericht zu diesem Eintrag erstellen">
      <i class="bi bi-diagram-3 me-1"></i>8D-Bericht
    </button>
    <?php endif; ?>
    <?php endif; ?>
    <form method="POST" action="<?= url('entries/' . $entry['id'] . '/delete') ?>" data-confirm="Really delete this entry?">
      <?= csrfField() ?>
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
    </form>
  </div>
</div>

<?php if (!empty($entry['jira_has_changes']) && !empty($entry['jira_issue_key'])): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 py-2 mb-3">
  <i class="bi bi-arrow-repeat fs-5 flex-shrink-0"></i>
  <div class="flex-grow-1 small">
    <strong>Jira has new changes</strong> on
    <a href="<?= e($entry['jira_issue_url']) ?>" target="_blank" class="alert-link"><?= e($entry['jira_issue_key']) ?></a>
    since this entry was last synced.
  </div>
  <a href="<?= url('jira-sync/entry/' . $entry['id']) ?>" class="btn btn-warning btn-sm text-nowrap">
    <i class="bi bi-eye me-1"></i>Review Changes
  </a>
</div>
<?php endif; ?>

<?php if (!empty($entry['zentao_has_changes']) && !empty($entry['zentao_bug_id'])): ?>
<div class="alert alert-info d-flex align-items-center gap-3 py-2 mb-3">
  <i class="bi bi-arrow-repeat fs-5 flex-shrink-0"></i>
  <div class="flex-grow-1 small">
    <strong>Zentao has new changes</strong> on
    <a href="<?= e($entry['zentao_bug_url']) ?>" target="_blank" class="alert-link">Bug #<?= e($entry['zentao_bug_id']) ?></a>
    since this entry was last synced.
  </div>
  <a href="<?= url('zentao-sync/entry/' . $entry['id']) ?>" class="btn btn-info btn-sm text-nowrap">
    <i class="bi bi-eye me-1"></i>Review Changes
  </a>
</div>
<?php endif; ?>

<div class="row g-4">
  <!-- Main content -->
  <div class="col-lg-8">
    <!-- Meta badges -->
    <div class="d-flex flex-wrap gap-2 mb-3">
      <span class="badge fs-6" style="background:<?= e($entry['type_color']) ?>"><?= e($entry['type_name']) ?></span>
      <?php if ($entry['cat_name']): ?>
      <span class="badge fs-6" style="background:<?= e($entry['cat_color']) ?>"><?= e($entry['cat_name']) ?></span>
      <?php endif; ?>
      <?php $statusLabel = entryStatuses()[$entry['status'] ?? 'new'] ?? ucfirst($entry['status'] ?? 'new'); ?>
      <span class="badge bg-<?= entryStatusColor($entry['status'] ?? 'new') ?>">
        <?= e($statusLabel) ?>
      </span>
      <?php
        $priColors = ['Low'=>'secondary','Medium'=>'info','High'=>'warning','Highest'=>'orange','Blocker'=>'danger'];
        $priColor  = $priColors[$entry['priority'] ?? 'Medium'] ?? 'secondary';
      ?>
      <span class="badge" style="<?= $priColor === 'orange' ? 'background:#f97316' : "background:var(--bs-$priColor)" ?>">
        <i class="bi bi-flag-fill me-1"></i><?= e($entry['priority'] ?? 'Medium') ?>
      </span>
      <?php if ($entry['is_private']): ?>
      <span class="badge bg-warning text-dark"><i class="bi bi-lock-fill me-1"></i>Private</span>
      <?php if (!empty($entry['is_report_relevant'])): ?>
      <span class="badge bg-success"><i class="bi bi-bar-chart-fill me-1"></i>Relevant for Reporting</span>
      <?php else: ?>
      <span class="badge bg-secondary"><i class="bi bi-bar-chart me-1"></i>Not relevant for Reporting</span>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Description -->
    <div class="card mb-4">
      <div class="card-body">
        <p class="mb-0" style="white-space:pre-wrap"><?= e($entry['description']) ?></p>
      </div>
    </div>

    <!-- Attachments gallery -->
    <?php if ($attachments): ?>


    <div class="card mb-4">
      <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Attachments (<?= count($attachments) ?>)</span>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2"
                onclick="document.getElementById('attachUploadCard').scrollIntoView({behavior:'smooth'});document.getElementById('showFileInput').click()">
          <i class="bi bi-plus"></i>
        </button>
      </div>
      <div class="card-body p-2">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:8px">
          <?php foreach ($attachments as $att): ?>
          <?php $isCover = ($entry['cover_attachment_id'] ?? 0) == $att['id']; ?>
          <div class="text-center">
            <?php if (isImage($att['mime_type'])): ?>
            <img src="<?= url('attachments/' . $att['id'] . '/thumb') ?>"
                 style="width:100%;height:80px;object-fit:cover;border-radius:.375rem;cursor:pointer;border:2px solid <?= $isCover ? '#f59e0b' : 'transparent' ?>"
                 onclick="openLightbox('<?= url('attachments/' . $att['id']) ?>')"
                 loading="lazy" alt="">
            <?php elseif (isVideo($att['mime_type'])): ?>
            <div style="width:100%;height:80px;border-radius:.375rem;cursor:pointer;background:#374151;display:flex;align-items:center;justify-content:center"
                 onclick="openVideoPlayer(<?= $att['id'] ?>, '<?= url('attachments/' . $att['id']) ?>')">
              <i class="bi bi-play-circle fs-3 text-white"></i>
            </div>
            <?php elseif (isPdf($att['mime_type'])): ?>
            <a href="<?= url('attachments/' . $att['id']) ?>" target="_blank" class="text-decoration-none">
              <div style="width:100%;height:80px;border-radius:.375rem;background:#374151;display:flex;align-items:center;justify-content:center">
                <i class="bi bi-file-pdf fs-3 text-danger"></i>
              </div>
            </a>
            <?php else: ?>
            <a href="<?= url('attachments/' . $att['id']) ?>" download class="text-decoration-none">
              <div style="width:100%;height:80px;border-radius:.375rem;background:#374151;display:flex;align-items:center;justify-content:center">
                <i class="bi bi-file-earmark fs-3 text-muted"></i>
              </div>
            </a>
            <?php endif; ?>
            <small class="text-muted text-truncate d-block mt-1" style="font-size:.65rem;max-width:100%"
                   title="<?= e($att['original_name']) ?>">
              <?= e($att['display_name'] ?: $att['original_name']) ?>
            </small>
            <?php if ($att['comment']): ?>
            <small class="text-info d-block text-truncate" style="font-size:.6rem" title="<?= e($att['comment']) ?>"><?= e($att['comment']) ?></small>
            <?php endif; ?>
            <small class="text-muted d-block" style="font-size:.65rem"><?= formatFileSize($att['file_size']) ?></small>
            <div class="d-flex justify-content-center gap-1 mt-1">
              <button class="btn btn-link btn-sm p-0 text-secondary" style="font-size:.7rem" title="Rename / caption"
                      onclick="openAttEdit(<?= $att['id'] ?>, '<?= e(addslashes($att['display_name'] ?: $att['original_name'])) ?>', '<?= e(addslashes($att['comment'] ?? '')) ?>')">
                <i class="bi bi-pencil"></i>
              </button>
              <?php if (isImage($att['mime_type'])): ?>
              <button class="btn btn-link btn-sm p-0 text-warning" style="font-size:.7rem" title="Annotate photo"
                      onclick="openAnnotate(<?= $att['id'] ?>, '<?= url('attachments/' . $att['id']) ?>')">
                <i class="bi bi-pen"></i>
              </button>
              <?php endif; ?>
              <form method="POST" action="<?= url('attachments/' . $att['id'] . '/delete') ?>" data-confirm="Delete attachment?">
                <?= csrfField() ?>
                <button class="btn btn-link btn-sm text-danger p-0" style="font-size:.7rem"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Upload new attachment (AJAX) -->
    <div class="card mb-4" id="attachUploadCard">
      <div class="card-header border-secondary fw-semibold small">Add Attachment</div>
      <div class="card-body">
        <div class="d-flex gap-2 flex-wrap align-items-center">
          <!-- Input inside label = iOS always triggers THIS input, no overlap confusion -->
          <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer">
            <i class="bi bi-folder2-open me-1"></i>Add Files / Photos
            <input type="file" id="showFileInput" multiple
                   style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px"
                   onchange="onFilesSelected(this)">
          </label>
          <label class="btn btn-outline-info btn-sm mb-0" style="cursor:pointer">
            <i class="bi bi-camera me-1"></i>Camera
            <input type="file" id="showCameraInput" accept="image/*,video/*" capture="environment"
                   style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px"
                   onchange="onCameraSelected(this)">
          </label>
          <button type="button" id="showUploadBtn" class="btn btn-primary btn-sm" style="display:none" onclick="doUpload()">
            <i class="bi bi-cloud-upload me-1"></i>Upload <span id="showUploadCount"></span>
          </button>
        </div>
        <div id="showPendingFiles" class="mt-2"></div>
        <div id="showUploadProgress" class="mt-2" style="display:none">
          <div class="progress" style="height:4px"><div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div></div>
          <small class="text-muted">Uploading?</small>
        </div>
      </div>
    </div>

    <!-- Sub-Tickets -->
<?php if ($subTickets): ?>
<div class="card mb-4">
  <div class="card-header border-secondary d-flex align-items-center gap-2">
    <i class="bi bi-diagram-2 text-info me-1"></i>
    <span class="fw-semibold small">Sub-Tickets <span class="badge bg-secondary ms-1"><?= count($subTickets) ?></span></span>
  </div>
    <?php if (Auth::canEdit('entries') || Auth::canOwn('entries')): ?>
    <a href="<?= url('entries/create') ?>?parent_id=<?= $entry['id'] ?>&project_id=<?= $entry['project_id'] ?>"
       class="btn btn-outline-success btn-sm ms-2 py-0 px-2" style="font-size:.75rem">
      <i class="bi bi-plus-lg me-1"></i>Sub-Entry
    </a>
    <?php endif; ?>
  <div class="list-group list-group-flush">
    <?php foreach ($subTickets as $st):
      $stPrio = match($st['priority']??'Medium') { 'Blocker','Highest'=>'danger','High'=>'warning','Low'=>'secondary',default=>'info' };
      $stStatus = match($st['status']??'open') { 'resolved','finished'=>'success','in_progress'=>'info','closed'=>'dark',default=>'secondary' };
    ?>
    <a href="<?= url('entries/'.$st['id']) ?>" class="list-group-item list-group-item-action bg-dark border-secondary d-flex align-items-center gap-3 py-2">
      <i class="bi bi-diagram-3 text-muted flex-shrink-0"></i>
      <span class="text-muted small">#<?= $st['id'] ?></span>
      <span class="flex-grow-1 small fw-semibold"><?= e($st['title']) ?></span>
      <span class="badge bg-<?= $stPrio ?>" style="font-size:.6rem"><?= e($st['priority']??'Medium') ?></span>
      <span class="badge bg-<?= $stStatus ?>" style="font-size:.6rem"><?= e($st['status']??'open') ?></span>
      <?php if ($st['jira_issue_key']): ?>
      <span class="badge bg-dark border border-warning text-warning" style="font-size:.6rem"><?= e($st['jira_issue_key']) ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Merged Tickets -->
<?php if ($mergedEntries): ?>
<div class="card mb-4">
  <div class="card-header border-secondary d-flex align-items-center gap-2">
    <i class="bi bi-arrow-right-circle text-warning me-1"></i>
    <span class="fw-semibold small">Zusammengefuhrte Tickets <span class="badge bg-secondary ms-1"><?= count($mergedEntries) ?></span></span>
  </div>
  <div class="list-group list-group-flush">
    <?php foreach ($mergedEntries as $me): ?>
    <a href="<?= url('entries/'.$me['id']) ?>" class="list-group-item list-group-item-action bg-dark border-secondary d-flex align-items-center gap-3 py-2">
      <i class="bi bi-archive text-muted"></i>
      <span class="text-muted small">#<?= $me['id'] ?></span>
      <span class="flex-grow-1 small"><?= e($me['title']) ?></span>
      <?php if ($me['jira_issue_key']): ?>
      <span class="badge bg-dark border border-warning text-warning" style="font-size:.65rem"><?= e($me['jira_issue_key']) ?></span>
      <?php endif; ?>
      <span class="text-muted small"><?= formatDate($me['merged_at'],'d.m.Y') ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Test Result Sub-Results -->
<?php if (!empty($isTestEntry)): ?>
<?php
  $outcomes = $testOutcomes ?? ['Passed','Failed','Blocked','Partial','Not Run'];
  $resultColors = ['passed'=>'success','failed'=>'danger','blocked'=>'warning','partial'=>'info','not run'=>'secondary'];
?>
<div class="card mb-3 border-info">
  <div class="card-header border-info d-flex align-items-center justify-content-between">
    <span class="text-info fw-semibold small">
      <i class="bi bi-clipboard2-check me-1"></i>Test Result Details
    </span>
    <?php if (!empty($testCycleLinked)): ?>
    <span class="small text-muted">
      <i class="bi bi-link-45deg me-1"></i>
      <a href="<?= url('test-cycles/'.$testCycleLinked['id']) ?>" class="text-decoration-none">
        <?= e($testCycleLinked['plan_name'] ? $testCycleLinked['plan_name'].' › ' : '') ?><?= e($testCycleLinked['name']) ?>
      </a>
      <?php if (!empty($testCaseLinked)): ?>
      &rsaquo; <?= e($testCaseLinked['title'] ?? 'Test Case #'.$testCaseLinked['id']) ?>
      <?php endif; ?>
    </span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if (empty($testResults)): ?>
    <p class="text-muted text-center py-4 small mb-0">No partial results documented yet.</p>
    <?php else: ?>
    <?php foreach ($testResults as $ri => $tr): ?>
    <?php /* DEBUG */ ?>

    <div class="px-3 py-3 <?= $ri > 0 ? 'border-top border-secondary' : '' ?>">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="fw-semibold small">Partial Result <?= $ri+1 ?></span>
        <?php if ($tr['test_result']): ?>
        <?php $rc = $resultColors[strtolower($tr['test_result'])] ?? 'secondary'; ?>
        <span class="badge bg-<?= $rc ?>"><?= e($tr['test_result']) ?></span>
        <?php endif; ?>
        <?php if ($tr['mower_serial']): ?>
        <span class="text-muted small ms-auto"><i class="bi bi-cpu me-1"></i><?= e($tr['mower_serial']) ?></span>
        <?php endif; ?>
      </div>
      <div class="row g-2 small">
        <?php if ($tr['test_setup']): ?>
        <div class="col-md-6">
          <div class="text-muted mb-1 fw-semibold" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.3px">Test Setup</div>
          <div style="white-space:pre-wrap"><?= e($tr['test_setup']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($tr['test_doc']): ?>
        <div class="col-md-6">
          <div class="text-muted mb-1 fw-semibold" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.3px">Documentation</div>
          <div style="white-space:pre-wrap"><?= e($tr['test_doc']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($tr['notes']): ?>
        <div class="col-12">
          <div class="text-muted mb-1 fw-semibold" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.3px">Notes</div>
          <div class="text-muted"><?= e($tr['notes']) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($tr['attachments'])): ?>
        <div class="col-12 mt-2">
          <div class="text-muted mb-1 fw-semibold" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.3px">Attachments</div>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($tr['attachments'] as $att): ?>
            <?php $attName = $att['display_name'] ?? $att['original_name'] ?? $att['filename'] ?? 'file'; ?>
            <?php $attFile = $att['filename'] ?? $att['file_path'] ?? ''; ?>
            <a href="<?= url('attachments/'.$att['id']) ?>" target="_blank"
               class="btn btn-outline-secondary btn-sm py-0 px-2 d-flex align-items-center gap-1" style="font-size:12px">
              <i class="bi bi-paperclip me-1"></i><?= e(mb_substr($attName,0,30)) ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php if (Auth::canEdit('entries')): ?>
  <div class="card-footer border-info py-2">
    <a href="<?= url('entries/'.$entry['id'].'/edit') ?>"
       class="btn btn-outline-info btn-sm">
      <i class="bi bi-pencil me-1"></i>Edit Partial Results
    </a>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Comments -->
    <div class="card mb-4" id="comments">
      <div class="card-header border-secondary fw-semibold small">Comments (<?= count($comments) ?>)</div>
      <div class="card-body">
        <?php if ($comments): ?>
        <div class="timeline comments-list mb-3">
          <?php foreach ($comments as $comment): ?>
          <?php include __DIR__ . '/_comment.php'; ?>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted small mb-3">No comments yet.</p>
        <?php endif; ?>

        <form method="POST" action="<?= url('entries/' . $entry['id'] . '/comments') ?>" class="comment-form">
          <?= csrfField() ?>
          <div class="d-flex gap-2">
            <textarea name="body" class="form-control form-control-sm" rows="2" placeholder="Write a comment?"></textarea>
            <button type="submit" class="btn btn-primary btn-sm text-nowrap">Send</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Linked Test Items -->
    <?php if ($testItems): ?>
    <div class="card mb-4">
      <div class="card-header border-secondary fw-semibold small">Linked Test Plan Items</div>
      <div class="card-body p-0">
        <div class="list-group list-group-flush">
          <?php foreach ($testItems as $ti): ?>
          <div class="list-group-item bg-transparent border-secondary py-2 px-3">
            <div class="fw-semibold small"><?= e($ti['title']) ?></div>
            <div class="text-muted small"><?= e($ti['plan_name']) ?> &middot; <?= e($ti['project_name']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- History -->
    <?php if ($history): ?>
    <div class="card mb-4">
      <div class="card-header border-secondary fw-semibold small">Change History</div>
      <div class="card-body">
        <div class="timeline">
          <?php foreach ($history as $h): ?>
          <div class="timeline-item mb-3">
            <div class="timeline-dot" style="background:#94a3b8"></div>
            <div class="text-muted small">
              <strong><?= e($h['user_name'] ?? 'System') ?></strong>
              changed <em><?= e($h['field_name']) ?></em> ?
              <?= formatDateTime($h['changed_at']) ?>
            </div>
            <div class="small mt-1">
              <span class="text-danger"><?= e($h['old_value']) ?></span>
              <i class="bi bi-arrow-right mx-1 text-muted"></i>
              <span class="text-success"><?= e($h['new_value']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar -->
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Details</div>
      <div class="card-body p-0">
        <table class="table table-dark table-sm mb-0">
          <tbody>
            <tr><th class="text-muted fw-normal small border-secondary" style="width:45%">Project</th>
                <td class="border-secondary small">
                  <span class="color-dot" style="background:<?= e($entry['project_color']) ?>"></span>
                  <a href="<?= url('projects/' . $entry['project_id']) ?>" class="text-white"><?= e($entry['project_name']) ?></a>
                </td></tr>
            <?php if ($entry['firmware_version']): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Firmware</th>
                <td class="border-secondary small"><?= e($entry['firmware_version']) ?></td></tr>
            <?php endif; ?>
            <?php if ($entry['app_version']): ?>
            <tr><th class="text-muted fw-normal small border-secondary">App</th>
                <td class="border-secondary small"><?= e($entry['app_version']) ?></td></tr>
            <?php endif; ?>
            <?php if ($entry['mower_serial']): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Serial No.</th>
                <td class="border-secondary small"><?= e($entry['mower_serial']) ?></td></tr>
            <?php endif; ?>
            <?php if ($entry['project_status_robot']): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Robot Status</th>
                <td class="border-secondary small"><?= e($entry['project_status_robot']) ?></td></tr>
            <?php endif; ?>
            <?php if ($entry['env_name']): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Environment</th>
                <td class="border-secondary small"><?= e($entry['env_name']) ?></td></tr>
            <?php endif; ?>
            <?php
            $entryTestArea = !empty($entry['test_area_id'])
                ? Database::fetchOne("SELECT id, name FROM test_areas WHERE id=?", [(int)$entry['test_area_id']])
                : null;
            $entrySession = !empty($entry['session_id'])
                ? Database::fetchOne("SELECT id, title FROM test_sessions WHERE id=?", [(int)$entry['session_id']])
                : null;
            ?>
            <?php if ($entryTestArea): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Test Area</th>
                <td class="border-secondary small">
                  <a href="<?= url('test-areas/' . $entryTestArea['id']) ?>" class="text-info text-decoration-none">
                    <i class="bi bi-map me-1"></i><?= e($entryTestArea['name']) ?>
                  </a>
                </td></tr>
            <?php endif; ?>
            <?php if ($entrySession): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Session</th>
                <td class="border-secondary small">
                  <a href="<?= url('test-sessions/' . $entrySession['id']) ?>" class="text-info text-decoration-none">
                    <i class="bi bi-play-circle me-1"></i><?= e($entrySession['title']) ?>
                  </a>
                </td></tr>
            <?php endif; ?>
            <?php if ($entry['weather_condition'] || $entry['temperature'] !== null): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Weather</th>
                <td class="border-secondary small">
                  <i class="bi bi-cloud me-1"></i>
                  <?= e($entry['weather_condition'] ?? '') ?>
                  <?= $entry['temperature'] !== null ? ' ? ' . e($entry['temperature']) . ' ?C' : '' ?>
                </td></tr>
            <?php endif; ?>
            <?php if (!empty($entry['jira_issue_key']) && !empty($settings['jira_url'])): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Jira Status</th>
                <td class="border-secondary small">
                  <?php if ($entry['jira_status']): ?>
                  <span class="badge bg-secondary me-1"><?= e($entry['jira_status']) ?></span>
                  <?php endif; ?>
                  <button type="button" class="btn btn-outline-warning btn-sm py-0 px-1"
                          style="font-size:.7rem" onclick="syncJiraStatus(<?= $entry['id'] ?>, this, 'quick')"
                          title="Quick Sync: checks status &amp; priority">
                    <i class="bi bi-lightning me-1"></i>Quick Sync
                  </button>
                </td></tr>
            <tr><th class="text-muted fw-normal small border-secondary">Jira Changes</th>
                <td class="border-secondary small">
                  <?php if (!empty($entry['jira_has_changes'])): ?>
                  <span class="badge bg-warning text-dark me-1">Changes detected</span>
                  <a href="<?= url('jira-sync/entry/' . $entry['id']) ?>" class="btn btn-warning btn-sm py-0 px-1" style="font-size:.7rem">Review</a>
                  <?php else: ?>
                  <span class="text-muted" id="jiraSyncStatus" style="font-size:.75rem">
                    <?= $entry['jira_synced_at'] ? 'Last checked ' . formatDateTime($entry['jira_synced_at']) : 'Not yet checked' ?>
                  </span>
                  <?php endif; ?>
                  <button type="button" class="btn btn-outline-info btn-sm py-0 px-1 ms-1"
                          style="font-size:.7rem" onclick="syncJiraStatus(<?= $entry['id'] ?>, this, 'full')"
                          title="Full Sync: checks all configured fields">
                    <i class="bi bi-arrow-repeat me-1"></i>Full Sync
                  </button>
                </td></tr>
            <?php endif; ?>
            <?php if (!empty($entry['zentao_bug_id']) && !empty($settings['zentao_url'])): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Zentao Bug</th>
                <td class="border-secondary small">
                  <a href="<?= e($entry['zentao_bug_url']) ?>" target="_blank" class="text-info text-decoration-none fw-semibold">
                    <i class="bi bi-bug me-1"></i>Bug #<?= e($entry['zentao_bug_id']) ?>
                  </a>
                  <button type="button" class="btn btn-outline-warning btn-sm py-0 px-1 ms-2"
                          style="font-size:.7rem" onclick="syncZentaoStatus(<?= $entry['id'] ?>, this, 'quick')"
                          title="Quick Sync: checks status &amp; priority">
                    <i class="bi bi-lightning me-1"></i>Quick Sync
                  </button>
                </td></tr>
            <tr><th class="text-muted fw-normal small border-secondary">Zentao Changes</th>
                <td class="border-secondary small">
                  <?php if (!empty($entry['zentao_has_changes'])): ?>
                  <span class="badge bg-warning text-dark me-1">Changes detected</span>
                  <a href="<?= url('zentao-sync/entry/' . $entry['id']) ?>" class="btn btn-warning btn-sm py-0 px-1" style="font-size:.7rem">Review</a>
                  <?php else: ?>
                  <span class="text-muted" id="zentaoSyncStatus" style="font-size:.75rem">
                    <?= ($entry['zentao_synced_at'] ?? null) ? 'Last checked ' . formatDateTime($entry['zentao_synced_at']) : 'Not yet checked' ?>
                  </span>
                  <?php endif; ?>
                  <button type="button" class="btn btn-outline-info btn-sm py-0 px-1 ms-1"
                          style="font-size:.7rem" onclick="syncZentaoStatus(<?= $entry['id'] ?>, this, 'full')"
                          title="Full Sync: checks all configured fields">
                    <i class="bi bi-arrow-repeat me-1"></i>Full Sync
                  </button>
                </td></tr>
            <?php elseif (!empty($settings['zentao_url'])): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Zentao</th>
                <td class="border-secondary small">
                  <button type="button" class="btn btn-outline-info btn-sm py-0 px-2" style="font-size:.75rem"
                          data-bs-toggle="modal" data-bs-target="#zentaoModal">
                    <i class="bi bi-bug me-1"></i>Create Zentao Bug
                  </button>
                </td></tr>
            <?php endif; ?>
            <?php if ($entry['assigned_name']): ?>
            <tr><th class="text-muted fw-normal small border-secondary">Assigned To</th>
                <td class="border-secondary small"><i class="bi bi-person me-1"></i><?= e($entry['assigned_name']) ?></td></tr>
            <?php endif; ?>
            <?php if ($entry['gps_lat'] && $entry['gps_lon']): ?>
            <tr><th class="text-muted fw-normal small border-secondary">GPS</th>
                <td class="border-secondary small">
                  <a href="https://maps.google.com/?q=<?= e((float)$entry['gps_lat']) ?>,<?= e((float)$entry['gps_lon']) ?>" target="_blank" class="text-info small">
                    <?= e(round((float)$entry['gps_lat'], 5)) ?>, <?= e(round((float)$entry['gps_lon'], 5)) ?>
                  </a>
                </td></tr>
            <?php endif; ?>
            <?php if (!empty($entry['sharepoint_folder_url'])): ?>
            <tr><th class="text-muted fw-normal small border-secondary">SharePoint</th>
                <td class="border-secondary small">
                  <a href="<?= e($entry['sharepoint_folder_url']) ?>" target="_blank" class="text-info">
                    <i class="bi bi-cloud-check me-1"></i>Open folder
                  </a>
                </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Task Details (shown when entry is bookmarked as todo) -->
    <div class="card mb-3" id="taskDetailsCard" <?= $isTodo ? '' : 'style="display:none"' ?>>
      <div class="card-header border-secondary fw-semibold small">
        <i class="bi bi-bookmark-fill me-1 text-warning"></i>Task Details
      </div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label small mb-1">Due Date</label>
          <input type="date" id="taskDueDate" class="form-control form-control-sm"
                 value="<?= e($todoData['due_date'] ?? '') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Priority</label>
          <select id="taskPriority" class="form-select form-select-sm">
            <option value="">? None ?</option>
            <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= ($todoData['priority'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small mb-1">Notes</label>
          <textarea id="taskNotes" class="form-control form-control-sm" rows="2"
                    placeholder="Task notes?"><?= e($todoData['notes'] ?? '') ?></textarea>
        </div>
        <button type="button" class="btn btn-sm btn-outline-warning w-100" onclick="saveTaskDetails()">
          <i class="bi bi-check-lg me-1"></i>Save Task Details
        </button>
      </div>
    </div>

    <!-- Linked Entries -->
    <div class="card mb-3" id="linkedEntriesCard">
      <div class="card-header border-secondary fw-semibold small d-flex align-items-center justify-content-between">
        <span>Linked Entries</span>
        <button class="btn btn-link btn-sm p-0 text-secondary" onclick="document.getElementById('linkSearchRow').style.display=''">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="card-body p-0">
        <div id="linkSearchRow" class="p-2 border-bottom border-secondary" style="display:none">
          <div class="input-group input-group-sm">
            <input type="text" id="linkSearch" class="form-control" placeholder="Search entries?" oninput="searchEntriesToLink(this.value)">
          </div>
          <div id="linkResults" class="list-group mt-1" style="max-height:160px;overflow-y:auto"></div>
        </div>
        <div id="linkedList">
          <?php if ($linkedEntries): ?>
          <?php foreach ($linkedEntries as $le): ?>
          <div class="d-flex align-items-center gap-2 px-2 py-2 border-bottom border-secondary" id="link-<?= $le['link_id'] ?>">
            <span class="badge" style="background:<?= e($le['type_color']) ?>;font-size:.65rem"><?= e($le['type_name']) ?></span>
            <a href="<?= url('entries/' . $le['id']) ?>" class="text-white small text-decoration-none flex-grow-1 text-truncate">
              <?= e($le['title'] ?: 'Entry #' . $le['id']) ?>
            </a>
            <span class="text-muted" style="font-size:.65rem"><?= e($le['entry_date']) ?></span>
            <button class="btn btn-link btn-sm p-0 text-danger" onclick="removeLink(<?= $le['link_id'] ?>)"><i class="bi bi-x"></i></button>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <p class="text-muted small m-2 mb-1" id="noLinksMsg">No linked entries.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Custom field values -->
    <?php if ($customFields && array_filter($customMap)): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Custom Fields</div>
      <div class="card-body p-0">
        <table class="table table-dark table-sm mb-0">
          <tbody>
            <?php foreach ($customFields as $cf): ?>
            <?php $v = $customMap[$cf['id']] ?? ''; ?>
            <?php if ($v === '' || $v === null) continue; ?>
            <tr>
              <th class="text-muted fw-normal small border-secondary" style="width:50%"><?= e($cf['name']) ?></th>
              <td class="border-secondary small"><?= e($v) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($entry['jira_issue_key']) && !empty($settings['jira_url'])): ?>
<div class="card mb-3 mt-4">
  <div class="card-header border-secondary d-flex align-items-center gap-2 py-2"
       style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#jiraCommentsPanel">
    <i class="bi bi-chat-left-text text-warning"></i>
    <span class="fw-semibold small">Jira Comments</span>
    <span class="badge bg-secondary ms-1" id="jiraCommentCount"><?= count($jiraComments) ?></span>
    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 ms-auto" style="font-size:.7rem"
            onclick="event.stopPropagation(); syncJiraComments(<?= $entry['id'] ?>, this)">
      <i class="bi bi-arrow-repeat me-1"></i>Sync
    </button>
    <i class="bi bi-chevron-down small text-muted"></i>
  </div>
  <div class="collapse" id="jiraCommentsPanel">
    <div class="list-group list-group-flush" id="jiraCommentsList">
      <?php foreach ($jiraComments as $jc): ?>
      <div class="list-group-item bg-transparent py-2">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-person-circle text-muted small"></i>
          <span class="fw-semibold small"><?= e($jc['author_name']) ?></span>
          <span class="text-muted small"><?= e(substr($jc['jira_created_at'] ?? '', 0, 16)) ?></span>
        </div>
        <pre class="mb-0 small ms-4" style="white-space:pre-wrap;font-family:inherit"><?= e($jc['body']) ?></pre>
      </div>
      <?php endforeach; ?>
      <?php if (!$jiraComments): ?>
      <div class="list-group-item bg-transparent py-3 text-muted small text-center" id="jiraNoComments">
        No Jira comments synced yet ? click <strong>Sync</strong> to import.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($entry['zentao_bug_id']) && !empty($settings['zentao_url'])): ?>
<div class="card mb-3">
  <div class="card-header border-secondary d-flex align-items-center gap-2 py-2"
       style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#zentaoActionsPanel">
    <i class="bi bi-chat-left-text text-info"></i>
    <span class="fw-semibold small">Zentao History &amp; Comments</span>
    <span class="badge bg-secondary ms-1" id="zentaoActionCount"><?= count($zentaoActions) ?></span>
    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 ms-auto" style="font-size:.7rem"
            onclick="event.stopPropagation(); syncZentaoComments(<?= $entry['id'] ?>, this)">
      <i class="bi bi-arrow-repeat me-1"></i>Sync
    </button>
    <i class="bi bi-chevron-down small text-muted"></i>
  </div>
  <div class="collapse" id="zentaoActionsPanel">
    <div class="list-group list-group-flush" id="zentaoActionsList">
      <?php foreach ($zentaoActions as $za): ?>
      <div class="list-group-item bg-transparent py-2">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-person-circle text-muted small"></i>
          <span class="fw-semibold small"><?= e($za['author_name']) ?></span>
          <span class="text-muted small"><?= e(substr($za['jira_created_at'] ?? '', 0, 16)) ?></span>
        </div>
        <pre class="mb-0 small ms-4" style="white-space:pre-wrap;font-family:inherit"><?= e($za['body']) ?></pre>
      </div>
      <?php endforeach; ?>
      <?php if (!$zentaoActions): ?>
      <div class="list-group-item bg-transparent py-3 text-muted small text-center" id="zentaoNoActions">
        No Zentao activity synced yet ? click <strong>Sync</strong> to import.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function syncJiraComments(entryId, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  fetch('<?= url('entries/' . $entry['id'] . '/jira/sync-comments') ?>', {
    method: 'POST', body: new URLSearchParams({ _csrf: csrf })
  })
  .then(r => r.json())
  .then(d => {
    btn.disabled = false; btn.innerHTML = orig;
    if (d.error) { showToast(d.error, 'danger'); return; }
    showToast('Synced ' + (d.count ?? 0) + ' comment(s) from Jira.', 'success');
    if (d.count > 0) setTimeout(() => location.reload(), 800);
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = orig; });
}

function syncZentaoComments(entryId, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  fetch('<?= url('entries/' . $entry['id'] . '/zentao/sync-comments') ?>', {
    method: 'POST', body: new URLSearchParams({ _csrf: csrf })
  })
  .then(r => r.json())
  .then(d => {
    btn.disabled = false; btn.innerHTML = orig;
    if (d.error) { showToast(d.error, 'danger'); return; }
    showToast('Synced ' + (d.count ?? 0) + ' action(s) from Zentao.', 'success');
    if (d.count > 0) setTimeout(() => location.reload(), 800);
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = orig; });
}

function toggleTodo(btn) {
  fetch('<?= url('entries/' . $entry['id'] . '/todo') ?>', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: '<?= e(Auth::csrfToken()) ?>' })
  }).then(r => r.json()).then(data => {
    const label = btn.querySelector('span');
    const taskCard = document.getElementById('taskDetailsCard');
    if (data.done) {
      btn.classList.replace('btn-outline-secondary', 'btn-warning');
      btn.querySelector('i').className = 'bi bi-bookmark-fill';
      btn.title = 'Remove from Todo list';
      if (label) label.textContent = 'Bookmarked';
      if (taskCard) taskCard.style.display = '';
      if (typeof showToast === 'function') showToast('Added to Todo list', 'success');
    } else {
      btn.classList.replace('btn-warning', 'btn-outline-secondary');
      btn.querySelector('i').className = 'bi bi-bookmark';
      btn.title = 'Add to Todo list';
      if (label) label.textContent = 'Todo';
      if (taskCard) taskCard.style.display = 'none';
      if (typeof showToast === 'function') showToast('Removed from Todo list', 'info');
    }
  });
}

function saveTaskDetails() {
  const csrf = '<?= e(Auth::csrfToken()) ?>';
  const params = new URLSearchParams({
    _csrf:    csrf,
    due_date: document.getElementById('taskDueDate')?.value || '',
    priority: document.getElementById('taskPriority')?.value || '',
    notes:    document.getElementById('taskNotes')?.value || '',
  });
  fetch('<?= url('entries/' . $entry['id'] . '/todo/details') ?>', { method: 'POST', body: params })
    .then(r => r.json())
    .then(data => {
      if (data.ok && typeof showToast === 'function') showToast('Task details saved', 'success');
      else if (data.error && typeof showToast === 'function') showToast(data.error, 'danger');
    });
}

// AJAX file upload for show page
const _showCsrf = '<?= e(Auth::csrfToken()) ?>';
const _uploadUrl = '<?= url('entries/' . $entry['id'] . '/upload') ?>';
let _pendingFiles = [];

function formatBytes(n) {
  return n >= 1048576 ? (n/1048576).toFixed(1)+' MB' : Math.round(n/1024)+' KB';
}

function renderPendingFiles() {
  const list  = document.getElementById('showPendingFiles');
  const btn   = document.getElementById('showUploadBtn');
  const count = document.getElementById('showUploadCount');
  if (!list) return;
  list.innerHTML = '';
  _pendingFiles.forEach((f, i) => {
    const row = document.createElement('div');
    row.className = 'd-flex align-items-center gap-2 py-1 border-bottom border-secondary';
    row.innerHTML = `<i class="bi bi-file-earmark text-muted"></i>
      <span class="text-truncate small flex-grow-1" style="max-width:260px">${f.name}</span>
      <span class="text-muted small text-nowrap">${formatBytes(f.size)}</span>
      <button type="button" class="btn-close" style="font-size:.7rem" aria-label="Remove" onclick="removePendingFile(${i})"></button>`;
    list.appendChild(row);
  });
  if (btn) btn.style.display = _pendingFiles.length ? '' : 'none';
  if (count) count.textContent = _pendingFiles.length > 0 ? '(' + _pendingFiles.length + ')' : '';
}

function removePendingFile(idx) {
  _pendingFiles.splice(idx, 1);
  renderPendingFiles();
}

// Image compression ? canvas-based resize + JPEG re-encode
const _IMG_THRESHOLD = 2 * 1024 * 1024;
const _IMG_MAX_DIM = 2048;

async function maybeCompressImage(file) {
  if (!file.type.startsWith('image/') || file.size <= _IMG_THRESHOLD) return file;
  return new Promise(resolve => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      URL.revokeObjectURL(url);
      let w = img.naturalWidth, h = img.naturalHeight;
      if (w > _IMG_MAX_DIM || h > _IMG_MAX_DIM) {
        const scale = Math.min(_IMG_MAX_DIM / w, _IMG_MAX_DIM / h);
        w = Math.round(w * scale); h = Math.round(h * scale);
      }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      canvas.toBlob(blob => {
        if (!blob || blob.size >= file.size) { resolve(file); return; }
        resolve(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
      }, 'image/jpeg', 0.85);
    };
    img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
    img.src = url;
  });
}

async function onFilesSelected(inputEl) {
  // Dedup by name+size to avoid double-adding when picker reopened
  const existing = new Set(_pendingFiles.map(f => f.name + '|' + f.size));
  for (const f of inputEl.files) {
    const out = f.type.startsWith('image/') ? await maybeCompressImage(f) : f;
    const key = out.name + '|' + out.size;
    if (!existing.has(key)) { _pendingFiles.push(out); existing.add(key); }
  }
  // Do NOT clear inputEl.value ? clearing invalidates File objects on iOS Safari
  renderPendingFiles();
}

// Camera: auto-upload immediately so photos aren't lost if user navigates away
async function onCameraSelected(inputEl) {
  await onFilesSelected(inputEl);
  if (_pendingFiles.length) doUpload();
}

// Warn before leaving if files are queued but not yet uploaded
window.addEventListener('beforeunload', e => {
  if (_pendingFiles.length > 0) {
    e.preventDefault();
    return (e.returnValue = 'You have ' + _pendingFiles.length + ' file(s) not yet uploaded. Leave anyway?');
  }
});

function doUpload() {
  if (!_pendingFiles.length) return;
  const progress = document.getElementById('showUploadProgress');
  const btn = document.getElementById('showUploadBtn');
  if (progress) progress.style.display = '';
  if (btn) btn.disabled = true;
  const fd = new FormData();
  fd.append('_csrf', _showCsrf);
  for (const f of _pendingFiles) fd.append('files[]', f);
  fetch(_uploadUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': _showCsrf } })
    .then(async r => {
      if (progress) progress.style.display = 'none';
      if (btn) btn.disabled = false;
      let data;
      try { data = await r.json(); } catch { data = { error: 'Server error ' + r.status }; }
      if (data.attachments && data.attachments.length) {
        _pendingFiles = [];
        renderPendingFiles();
        if (typeof showToast === 'function') showToast(data.success + ' file(s) uploaded', 'success');
        setTimeout(() => location.reload(), 1000);
      } else if (data.error) {
        if (typeof showToast === 'function') showToast(data.error, 'danger');
      } else if (data.errors && data.errors.length) {
        if (typeof showToast === 'function') showToast('Upload failed:<br>' + data.errors.join('<br>'), 'danger');
      } else {
        if (typeof showToast === 'function') showToast('No files were saved', 'warning');
      }
    })
    .catch(err => {
      if (progress) progress.style.display = 'none';
      if (btn) btn.disabled = false;
      if (typeof showToast === 'function') showToast('Upload error: ' + err.message, 'danger');
    });
}

// change events handled by inline onchange on each input

// ?? Entry Links ????????????????????????????????????????????????
const _linkCsrf = '<?= e(Auth::csrfToken()) ?>';
const _entryId  = <?= (int)$entry['id'] ?>;

let _linkTimer = null;
function searchEntriesToLink(q) {
  clearTimeout(_linkTimer);
  const box = document.getElementById('linkResults');
  if (q.length < 2) { box.innerHTML = ''; return; }
  _linkTimer = setTimeout(() => {
    fetch('<?= url('api/entries/search') ?>?q=' + encodeURIComponent(q) + '&exclude=' + _entryId)
      .then(r => r.json()).then(list => {
        box.innerHTML = list.map(e =>
          `<button type="button" class="list-group-item list-group-item-action list-group-item-dark py-1 px-2 small"
              onclick="addLink(${e.id}, this)">
            #${e.id} ${e.title || '(no title)'} <span class="text-muted">${e.entry_date}</span>
          </button>`
        ).join('') || '<div class="list-group-item list-group-item-dark small text-muted py-1">No results</div>';
      });
  }, 300);
}

function addLink(toId, btn) {
  btn.disabled = true;
  fetch('<?= url('entries/' . $entry['id'] . '/links') ?>', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _linkCsrf, to_entry_id: toId })
  }).then(r => r.json()).then(data => {
    if (data.error) { showToast(data.error, 'danger'); btn.disabled = false; return; }
    const e = data.entry;
    const noMsg = document.getElementById('noLinksMsg');
    if (noMsg) noMsg.remove();
    const row = document.createElement('div');
    row.id = 'link-' + e.link_id;
    row.className = 'd-flex align-items-center gap-2 px-2 py-2 border-bottom border-secondary';
    row.innerHTML = `<span class="badge" style="background:${e.type_color};font-size:.65rem">${e.type_name}</span>
      <a href="<?= url('entries/') ?>${e.id}" class="text-white small text-decoration-none flex-grow-1 text-truncate">${e.title || 'Entry #'+e.id}</a>
      <span class="text-muted" style="font-size:.65rem">${e.entry_date}</span>
      <button class="btn btn-link btn-sm p-0 text-danger" onclick="removeLink(${e.link_id})"><i class="bi bi-x"></i></button>`;
    document.getElementById('linkedList').appendChild(row);
    document.getElementById('linkSearch').value = '';
    document.getElementById('linkResults').innerHTML = '';
    document.getElementById('linkSearchRow').style.display = 'none';
    showToast('Entry linked', 'success');
  });
}

function removeLink(linkId) {
  if (!confirm('Remove this link?')) return;
  fetch('<?= url('entries/' . $entry['id'] . '/links/') ?>' + linkId + '/delete', {
    method: 'POST', body: new URLSearchParams({ _csrf: _linkCsrf })
  }).then(() => {
    document.getElementById('link-' + linkId)?.remove();
    showToast('Link removed', 'info');
  });
}

// ?? Attachment Edit (rename / caption) ?????????????????????????
let _attEditId = null;
function openAttEdit(id, name, caption) {
  _attEditId = id;
  document.getElementById('attEditName').value    = name;
  document.getElementById('attEditCaption').value = caption;
  new bootstrap.Modal(document.getElementById('attEditModal')).show();
}
function saveAttEdit() {
  const body = new URLSearchParams({
    _csrf:        _showCsrf,
    display_name: document.getElementById('attEditName').value,
    comment:      document.getElementById('attEditCaption').value,
  });
  fetch('<?= url('attachments/') ?>' + _attEditId + '/update', { method: 'POST', body,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(r => r.json()).then(() => {
    bootstrap.Modal.getInstance(document.getElementById('attEditModal'))?.hide();
    showToast('Saved', 'success');
    setTimeout(() => location.reload(), 600);
  });
}

// ?? Photo Annotation Canvas ?????????????????????????????????????
let _annotateId = null, _annotCanvas = null, _annotCtx = null;
let _annotTool = 'pen', _annotColor = '#FF0000', _annotSize = 3;
let _annotDrawing = false, _annotStart = {x:0,y:0};
let _annotHistory = [], _annotStep = -1;
let _annotListenersAttached = false;
let _annotTextSize = 18;

function openAnnotate(id, url) {
  _annotateId = id;
  _annotCtx   = null; // block draws until image is ready
  new bootstrap.Modal(document.getElementById('annotateModal')).show();
  // annotCanvas exists in DOM now (user has triggered this, full page parsed)
  if (!_annotListenersAttached) {
    _annotListenersAttached = true;
    annotSetup();
  }
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = () => {
    _annotCanvas = document.getElementById('annotCanvas');
    _annotCtx    = _annotCanvas.getContext('2d');
    const maxW = Math.min(img.naturalWidth, 900);
    const scale = maxW / img.naturalWidth;
    _annotCanvas.width  = img.naturalWidth  * scale;
    _annotCanvas.height = img.naturalHeight * scale;
    _annotCtx.drawImage(img, 0, 0, _annotCanvas.width, _annotCanvas.height);
    _annotHistory = [_annotCtx.getImageData(0, 0, _annotCanvas.width, _annotCanvas.height)];
    _annotStep = 0;
  };
  img.src = url + '?t=' + Date.now();
}

function annotGetPos(e) {
  const r = _annotCanvas.getBoundingClientRect();
  const sc = _annotCanvas.width / r.width;
  const src = e.touches ? e.touches[0] : e;
  return { x: (src.clientX - r.left) * sc, y: (src.clientY - r.top) * sc };
}

function annotSetup() {
  const c = document.getElementById('annotCanvas');
  if (!c) return;
  const down = e => { if (!_annotCtx) return; e.preventDefault(); _annotDrawing = true; _annotStart = annotGetPos(e); _annotCtx.beginPath(); _annotCtx.moveTo(_annotStart.x, _annotStart.y); };
  const move = e => {
    if (!_annotDrawing || !_annotCtx) return; e.preventDefault();
    const p = annotGetPos(e);
    _annotCtx.strokeStyle = _annotColor; _annotCtx.lineWidth = _annotSize; _annotCtx.lineCap = 'round';
    if (_annotTool === 'pen') {
      _annotCtx.lineTo(p.x, p.y); _annotCtx.stroke();
    } else if (_annotTool === 'arrow') {
      _annotCtx.putImageData(_annotHistory[_annotStep], 0, 0);
      drawArrow(_annotStart.x, _annotStart.y, p.x, p.y);
    } else if (_annotTool === 'rect') {
      _annotCtx.putImageData(_annotHistory[_annotStep], 0, 0);
      _annotCtx.strokeRect(_annotStart.x, _annotStart.y, p.x - _annotStart.x, p.y - _annotStart.y);
    } else if (_annotTool === 'circle') {
      _annotCtx.putImageData(_annotHistory[_annotStep], 0, 0);
      const rx = Math.abs(p.x - _annotStart.x)/2, ry = Math.abs(p.y - _annotStart.y)/2;
      _annotCtx.beginPath(); _annotCtx.ellipse(_annotStart.x + (p.x-_annotStart.x)/2, _annotStart.y + (p.y-_annotStart.y)/2, rx, ry, 0, 0, 2*Math.PI); _annotCtx.stroke();
    }
  };
  const up = e => {
    if (!_annotDrawing || !_annotCtx) return; e.preventDefault();
    _annotDrawing = false;
    _annotHistory = _annotHistory.slice(0, _annotStep + 1);
    _annotHistory.push(_annotCtx.getImageData(0, 0, _annotCanvas.width, _annotCanvas.height));
    _annotStep++;
  };
  c.addEventListener('mousedown', down); c.addEventListener('mousemove', move); c.addEventListener('mouseup', up);
  c.addEventListener('touchstart', down, {passive:false}); c.addEventListener('touchmove', move, {passive:false}); c.addEventListener('touchend', up, {passive:false});
}

function drawArrow(x1,y1,x2,y2) {
  const angle = Math.atan2(y2-y1, x2-x1);
  const len = 15;
  _annotCtx.beginPath(); _annotCtx.moveTo(x1,y1); _annotCtx.lineTo(x2,y2); _annotCtx.stroke();
  _annotCtx.beginPath();
  _annotCtx.moveTo(x2,y2);
  _annotCtx.lineTo(x2-len*Math.cos(angle-Math.PI/6), y2-len*Math.sin(angle-Math.PI/6));
  _annotCtx.lineTo(x2-len*Math.cos(angle+Math.PI/6), y2-len*Math.sin(angle+Math.PI/6));
  _annotCtx.closePath(); _annotCtx.fill();
}

function annotUndo() {
  if (_annotStep > 0) { _annotStep--; _annotCtx.putImageData(_annotHistory[_annotStep], 0, 0); }
}

function addAnnotText() {
  const txt = prompt('Enter text:'); if (!txt) return;
  _annotCtx.font = _annotTextSize + 'px Arial'; _annotCtx.fillStyle = _annotColor;
  _annotCtx.fillText(txt, _annotStart.x || 20, _annotStart.y || 30);
  _annotHistory.push(_annotCtx.getImageData(0,0,_annotCanvas.width,_annotCanvas.height)); _annotStep++;
}

function saveAnnotation() {
  const btn = document.getElementById('annotSaveBtn');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving?';
  const data = _annotCanvas.toDataURL('image/png');
  fetch('<?= url('attachments/') ?>' + _annotateId + '/annotate', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _showCsrf, image_data: data })
  }).then(r => r.json()).then(res => {
    bootstrap.Modal.getInstance(document.getElementById('annotateModal'))?.hide();
    showToast('Annotated photo saved', 'success');
    setTimeout(() => location.reload(), 800);
  }).catch(() => { btn.disabled=false; btn.innerHTML='Save'; showToast('Save failed','danger'); });
}

// annotSetup() is called inside openAnnotate() once the user opens the modal
// (at that point the full DOM is loaded and annotCanvas exists)

// ?? Video Player with Markers ?????????????????????????????????
let _vidAttId = null;

function openVideoPlayer(attId, url) {
  _vidAttId = attId;
  const video = document.getElementById('markerVideo');
  video.src = url;
  video.load();
  document.getElementById('markerList').innerHTML = '<div class="text-muted small p-2">Loading markers?</div>';
  new bootstrap.Modal(document.getElementById('videoModal')).show();
  loadMarkers();
}

function loadMarkers() {
  fetch('<?= url('attachments/') ?>' + _vidAttId + '/markers')
    .then(r => r.json()).then(renderMarkers);
}

function renderMarkers(markers) {
  const list = document.getElementById('markerList');
  if (!markers.length) { list.innerHTML = '<div class="text-muted small p-2">No markers yet.</div>'; return; }
  list.innerHTML = markers.map(m => `
    <div class="d-flex align-items-center gap-2 px-2 py-1 border-bottom border-secondary" id="marker-${m.id}">
      <button class="btn btn-link btn-sm p-0 text-info" style="font-size:.75rem;min-width:50px"
              onclick="document.getElementById('markerVideo').currentTime=${m.time_seconds}">${fmtTime(m.time_seconds)}</button>
      <span class="small flex-grow-1">${m.label || '?'}</span>
      <small class="text-muted">${m.user_name||''}</small>
      <button class="btn btn-link btn-sm p-0 text-danger" onclick="deleteMarker(${m.id})"><i class="bi bi-x"></i></button>
    </div>`).join('');
}

function fmtTime(s) {
  const m = Math.floor(s/60), sec = Math.floor(s%60);
  return m + ':' + String(sec).padStart(2,'0');
}

function addMarker() {
  const video = document.getElementById('markerVideo');
  const label = document.getElementById('markerLabel').value.trim();
  fetch('<?= url('attachments/') ?>' + _vidAttId + '/markers', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _showCsrf, time_seconds: video.currentTime, label })
  }).then(r => r.json()).then(() => { document.getElementById('markerLabel').value=''; loadMarkers(); showToast('Marker added','success'); });
}

function deleteMarker(mid) {
  fetch('<?= url('attachments/') ?>' + _vidAttId + '/markers/' + mid + '/delete', {
    method: 'POST', body: new URLSearchParams({ _csrf: _showCsrf })
  }).then(() => { document.getElementById('marker-'+mid)?.remove(); });
}
</script>

<!-- Attachment Edit Modal -->
<div class="modal fade" id="attEditModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-pencil me-2"></i>Edit Attachment</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Display Name</label>
          <input type="text" id="attEditName" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label small">Caption / Info</label>
          <textarea id="attEditCaption" class="form-control" rows="3" placeholder="Describe what this photo shows?"></textarea>
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="saveAttEdit()">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Photo Annotation Modal -->
<div class="modal fade" id="annotateModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-pen me-2 text-warning"></i>Annotate Photo</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <!-- Toolbar -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-light" onclick="_annotTool='pen'" title="Pen"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-outline-light" onclick="_annotTool='arrow'" title="Arrow"><i class="bi bi-arrow-up-right"></i></button>
            <button class="btn btn-outline-light" onclick="_annotTool='rect'" title="Rectangle"><i class="bi bi-square"></i></button>
            <button class="btn btn-outline-light" onclick="_annotTool='circle'" title="Circle"><i class="bi bi-circle"></i></button>
            <button class="btn btn-outline-light" onclick="addAnnotText()" title="Text"><i class="bi bi-fonts"></i></button>
          </div>
          <input type="color" id="annotColor" value="#FF0000" class="form-control form-control-sm" style="width:40px;height:32px;padding:2px"
                 onchange="_annotColor=this.value" title="Color">
          <select class="form-select form-select-sm" style="width:80px" onchange="_annotSize=+this.value" title="Line width">
            <option value="2">Thin</option>
            <option value="4" selected>Medium</option>
            <option value="8">Thick</option>
          </select>
          <select class="form-select form-select-sm" style="width:75px" onchange="_annotTextSize=+this.value" title="Text size">
            <option value="12">12 px</option>
            <option value="18" selected>18 px</option>
            <option value="24">24 px</option>
            <option value="36">36 px</option>
            <option value="48">48 px</option>
          </select>
          <button class="btn btn-outline-secondary btn-sm" onclick="annotUndo()" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
        </div>
        <div style="overflow:auto;max-height:60vh;text-align:center">
          <canvas id="annotCanvas" style="max-width:100%;cursor:crosshair;touch-action:none"></canvas>
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <small class="text-muted me-auto">Saved as a new attachment (original preserved)</small>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning btn-sm" id="annotSaveBtn" onclick="saveAnnotation()">
          <i class="bi bi-check-lg me-1"></i>Save Annotated Copy
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Video Player Modal with Markers -->
<div class="modal fade" id="videoModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-play-circle me-2"></i>Video</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="display:grid;grid-template-columns:1fr 280px">
        <video id="markerVideo" controls style="width:100%;max-height:70vh;background:#000"></video>
        <div class="border-start border-secondary d-flex flex-column">
          <div class="p-2 border-bottom border-secondary">
            <div class="input-group input-group-sm">
              <input type="text" id="markerLabel" class="form-control" placeholder="Marker label?">
              <button class="btn btn-outline-warning btn-sm" onclick="addMarker()"><i class="bi bi-flag me-1"></i>Add</button>
            </div>
            <small class="text-muted">at current position</small>
          </div>
          <div id="markerList" style="overflow-y:auto;flex:1"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div id="lightbox">
  <button id="lb-close" class="btn btn-outline-light position-fixed top-0 end-0 m-3">
    <i class="bi bi-x-lg"></i>
  </button>
  <img id="lb-img" src="" alt="" style="display:none">
  <video id="lb-vid" controls style="display:none"></video>
</div>

<?php if (!empty($settings['jira_url'])): ?>
<!-- Jira Modal -->
<div class="modal fade" id="jiraModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h6 class="modal-title mb-0"><i class="bi bi-bug me-2 text-warning"></i>Jira</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (!empty($entry['jira_issue_key'])): ?>
        <div class="alert alert-warning py-2 d-flex align-items-center gap-2 mb-3">
          <i class="bi bi-link-45deg"></i>
          <span>Linked issue: <a href="<?= e($entry['jira_issue_url']) ?>" target="_blank" class="alert-link fw-bold"><?= e($entry['jira_issue_key']) ?></a></span>
          <span class="text-muted small ms-auto">Sync pushes description + new attachments</span>
        </div>
        <?php endif; ?>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small">Jira Project <span class="text-danger">*</span></label>
            <?php $jiraConfigs = $jiraConfigs ?? []; ?>
            <?php if (count($jiraConfigs) > 1): ?>
            <select id="jiraProjectKey" class="form-select" onchange="syncJiraIssueType(this.value)">
              <?php foreach ($jiraConfigs as $jc): ?>
              <option value="<?= e($jc['jira_project_key']) ?>" data-issue-type="<?= e($jc['issue_type']) ?>">
                <?= e($jc['label'] ?: $jc['jira_project_key']) ?> (<?= e($jc['jira_project_key']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <?php elseif (count($jiraConfigs) === 1): ?>
            <input type="text" id="jiraProjectKey" class="form-control"
                   value="<?= e($jiraConfigs[0]['jira_project_key']) ?>"
                   placeholder="e.g. GRSPT" readonly>
            <div class="form-text"><?= e($jiraConfigs[0]['label'] ?: $jiraConfigs[0]['jira_project_key']) ?></div>
            <?php else: ?>
            <input type="text" id="jiraProjectKey" class="form-control"
                   value="<?= e($settings['jira_default_project'] ?? '') ?>"
                   placeholder="e.g. GRSPT">
            <div class="form-text text-muted">No project-level Jira destinations configured ? using global default.</div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Issue Type</label>
            <select id="jiraIssueType" class="form-select">
              <option>Bug</option>
              <option>Task</option>
              <option>Story</option>
              <option>Epic</option>
              <option>Improvement</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Priority</label>
            <select id="jiraPriority" class="form-select">
              <?php foreach (['Highest','High','Medium','Low','Lowest','Blocker'] as $_jp): ?>
              <option <?= ($entry['priority'] ?? 'Medium') === $_jp ? 'selected' : '' ?>><?= $_jp ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small">Labels <span class="text-muted">(comma-separated)</span></label>
            <input type="text" id="jiraLabels" class="form-control" placeholder="e.g. robodoc, qa">
          </div>
          <?php
            $defaultJiraTitle = appSetting('jira_default_title_template') ?: '[{{type}}] {{title}}';
            $defaultJiraDesc  = appSetting('jira_default_desc_template')  ?: "*Type:* {{type}}\n*Category:* {{category}}\n*Project:* {{project}}\n*Project Status:* {{project_status}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*App Version:* {{app_version}}\n*Environment:* {{environment}}\n*Test Area:* {{test_area}}\n*Date:* {{date}} {{time}}\n*Creator:* {{creator}}\n\n{{description}}";
            $jiraTitleTpl = ($currentUser['jira_title_template'] ?? '') ?: $defaultJiraTitle;
            $jiraDescTpl  = ($currentUser['jira_desc_template']  ?? '') ?: $defaultJiraDesc;
          ?>
          <div class="col-12">
            <label class="form-label small">Summary Template</label>
            <input type="text" id="jiraTitleTpl" class="form-control font-monospace" value="<?= e($jiraTitleTpl) ?>">
          </div>
          <div class="col-12">
            <label class="form-label small">Description Template</label>
            <textarea id="jiraDescTpl" class="form-control font-monospace" rows="10"><?= e($jiraDescTpl) ?></textarea>
            <div class="mt-1 d-flex justify-content-between align-items-start flex-wrap gap-1">
              <small class="text-muted" style="font-size:.7rem">
                <strong>Variables:</strong>
                {{id}} {{type}} {{title}} {{serial}} {{firmware}} {{app_version}} {{project}} {{project_status}}
                {{category}} {{environment}} {{test_area}} {{status}} {{date}} {{time}} {{creator}}
                {{temperature}} {{weather}} {{sharepoint}} {{description}}
              </small>
              <a href="<?= url('profile') ?>" target="_blank" class="text-muted small text-decoration-none" style="font-size:.7rem; white-space:nowrap">
                <i class="bi bi-sliders me-1"></i>Set default in Profile
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-secondary d-flex align-items-center flex-wrap gap-2">
        <div id="jiraResult" class="me-auto small w-100"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <?php if (!empty($entry['jira_issue_key'])): ?>
        <button type="button" class="btn btn-outline-info btn-sm" onclick="updateJira()">
          <i class="bi bi-arrow-repeat me-1"></i>Sync to <?= e($entry['jira_issue_key']) ?>
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-warning btn-sm" id="jiraCreateBtn" onclick="submitJira()">
          <i class="bi bi-send me-1"></i><?= !empty($entry['jira_issue_key']) ? 'Create New Issue' : 'Create Issue' ?>
        </button>
      </div>
    </div>
  </div>
</div>
<script>
const _jiraCsrf = '<?= e(Auth::csrfToken()) ?>';

function syncJiraIssueType(projectKey) {
  var sel = document.getElementById('jiraProjectKey');
  if (!sel) return;
  var opt = sel.querySelector('option[value="' + projectKey + '"]');
  var typeField = document.getElementById('jiraIssueType');
  if (opt && typeField && opt.dataset.issueType) {
    typeField.value = opt.dataset.issueType;
  }
}
// Auto-sync on load
document.addEventListener('DOMContentLoaded', function() {
  var sel = document.getElementById('jiraProjectKey');
  if (sel && sel.tagName === 'SELECT') syncJiraIssueType(sel.value);
});

function jiraCommonBody() {
  return new URLSearchParams({
    _csrf:                _jiraCsrf,
    project_key:          document.getElementById('jiraProjectKey')?.value ?? '',
    issue_type:           document.getElementById('jiraIssueType')?.value ?? '',
    priority:             document.getElementById('jiraPriority')?.value ?? '',
    labels:               document.getElementById('jiraLabels')?.value ?? '',
    title_template:       document.getElementById('jiraTitleTpl')?.value ?? '',
    description_template: document.getElementById('jiraDescTpl')?.value ?? '',
  });
}

function jiraHandleResult(result, data, btn, originalLabel) {
  if (data.success) {
    let msg = 'Done: <a href="' + data.url + '" target="_blank" class="text-success fw-bold">' + data.key + '</a>';
    if (data.attachments > 0) msg += ' &mdash; ' + data.attachments + ' file(s) uploaded';
    if (data.transition) {
      const tColor = data.transition.startsWith('transitioned') ? 'text-success' : 'text-warning';
      msg += '<br><small class="' + tColor + '"><i class="bi bi-arrow-repeat me-1"></i>Status: ' + data.transition + '</small>';
    }
    if (data.priority) {
      const pColor = data.priority.startsWith('set to') ? 'text-success' : 'text-warning';
      msg += '<br><small class="' + pColor + '"><i class="bi bi-flag me-1"></i>Priority: ' + data.priority + '</small>';
    }
    result.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' + msg + '</span>';
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Done';
    setTimeout(() => location.reload(), 1800);
  } else {
    result.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (data.error || 'Unknown error') + '</span>';
    btn.disabled = false;
    btn.innerHTML = originalLabel;
  }
}

function submitJira() {
  const btn    = document.getElementById('jiraCreateBtn');
  const result = document.getElementById('jiraResult');
  const label  = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating?';
  result.innerHTML = '';
  fetch('<?= url('entries/' . $entry['id'] . '/jira') ?>', { method: 'POST', body: jiraCommonBody() })
    .then(r => r.json())
    .then(data => jiraHandleResult(result, data, btn, label))
    .catch(() => jiraHandleResult(result, {error: 'Network error'}, btn, label));
}

function updateJira() {
  const btn    = document.querySelector('#jiraModal button[onclick="updateJira()"]');
  const result = document.getElementById('jiraResult');
  const label  = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing?';
  result.innerHTML = '';
  fetch('<?= url('entries/' . $entry['id'] . '/jira/update') ?>', { method: 'POST', body: jiraCommonBody() })
    .then(r => r.json())
    .then(data => jiraHandleResult(result, data, btn, label))
    .catch(() => jiraHandleResult(result, {error: 'Network error'}, btn, label));
}

function syncJiraStatus(entryId, btn, mode) {
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  fetch('<?= url('') ?>entries/' + entryId + '/jira/sync-status', {
    method: 'POST', body: new URLSearchParams({ _csrf: csrf, sync_mode: mode || 'quick' })
  })
  .then(r => r.json())
  .then(d => {
    btn.innerHTML = orig; btn.disabled = false;
    if (d.error) { showToast(d.error, 'danger'); return; }
    const diffs = [];
    if (d.status_differs)   diffs.push('Status: Jira "' + d.jira_status + '" ? ' + d.mapped_status);
    if (d.priority_differs) diffs.push('Priority: Jira "' + d.jira_priority + '" vs local "' + d.local_priority + '"');
    if (diffs.length) {
      showToast(diffs.join(' ? ') + '. Review changes.', 'warning');
      location.reload();
    } else {
      showToast((mode === 'full' ? 'Full' : 'Quick') + ' Sync: all checked fields in sync.', 'success');
    }
  })
  .catch(() => { btn.innerHTML = orig; btn.disabled = false; });
}

function checkJiraChanges(sourceType, sourceId, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking?';
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  fetch('<?= url('api/jira-sync/check-record') ?>', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: csrf, source_type: sourceType, source_id: sourceId })
  })
  .then(r => r.json())
  .then(d => {
    btn.disabled = false;
    btn.innerHTML = orig;
    if (d.error) { showToast('Jira error: ' + d.error, 'danger'); return; }
    if (d.has_changes) {
      location.reload();
    } else {
      const statusEl = document.getElementById('jiraSyncStatus');
      if (statusEl) statusEl.textContent = 'Up to date (checked just now)';
      showToast('Jira is up to date ? no changes detected.', 'success');
    }
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = orig; showToast('Network error', 'danger'); });
}
</script>
<?php endif; ?>

<?php if (!empty($settings['zentao_url'])): ?>
<?php
$_zentaoTitleTpl = appSetting('zentao_title_template') ?: '{{title}}';
$_zentaoDescTpl  = appSetting('zentao_desc_template')  ?: "*Type:* {{type}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*Date:* {{date}}\n\n{{description}}";
?>
<!-- Zentao Bug Modal -->
<div class="modal fade" id="zentaoModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-bug me-2"></i>
          <?php if (!empty($entry['zentao_bug_id'])): ?>Sync to Bug #<?= e($entry['zentao_bug_id']) ?><?php else: ?>Create Zentao Bug<?php endif; ?>
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (!empty($entry['zentao_bug_id'])): ?>
        <div class="alert alert-info py-2 small">
          Linked: <a href="<?= e($entry['zentao_bug_url']) ?>" target="_blank" class="alert-link fw-bold">Bug #<?= e($entry['zentao_bug_id']) ?></a>
          <span class="text-muted ms-2">Sync pushes title + description to Zentao.</span>
        </div>
        <?php endif; ?>
        <div class="row g-3">
          <?php if (empty($entry['zentao_bug_id'])): ?>
          <div class="col-md-6">
            <label class="form-label small">Bug Type</label>
            <select id="zentaoBugType" class="form-select form-select-sm">
              <?php foreach (['codeerror'=>'Code Error','config'=>'Config','install'=>'Install','security'=>'Security','performance'=>'Performance','standard'=>'Standard','others'=>'Others'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= (appSetting('zentao_default_type') ?: 'codeerror') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Priority</label>
            <?php
              $_zpMap      = json_decode(appSetting('zentao_priority_map') ?? '{}', true) ?: [];
              $_zpDefaults = ['Low'=>4,'Medium'=>3,'High'=>2,'Highest'=>1,'Blocker'=>1];
              $_zpPri      = isset($_zpMap[$entry['priority'] ?? 'Medium']['pri'])
                             ? (int)$_zpMap[$entry['priority']]['pri']
                             : ($_zpDefaults[$entry['priority'] ?? 'Medium'] ?? 3);
            ?>
            <select id="zentaoPri" class="form-select form-select-sm">
              <?php foreach ([1=>'1 ? Highest',2=>'2 ? High',3=>'3 ? Medium',4=>'4 ? Low'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $_zpPri === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <label class="form-label small">Title Template</label>
            <input type="text" id="zentaoTitleTpl" class="form-control form-control-sm font-monospace" value="<?= e($_zentaoTitleTpl) ?>">
          </div>
          <div class="col-12">
            <label class="form-label small">Description Template</label>
            <textarea id="zentaoDescTpl" class="form-control form-control-sm font-monospace" rows="8"><?= e($_zentaoDescTpl) ?></textarea>
          </div>
        </div>
        <div id="zentaoResult" class="mt-3"></div>

        <!-- Link existing bug -->
        <?php if (empty($entry['zentao_bug_id'])): ?>
        <hr class="border-secondary my-3">
        <div class="small fw-semibold text-muted mb-2"><i class="bi bi-link-45deg me-1"></i>Or link an existing Zentao bug</div>
        <div class="input-group input-group-sm">
          <input type="text" id="zentaoSearchQ" class="form-control" placeholder="Search by title or bug ID?" oninput="zentaoSearchDebounce()">
          <button class="btn btn-outline-secondary" onclick="zentaoSearch()"><i class="bi bi-search"></i></button>
        </div>
        <div id="zentaoSearchResults" class="mt-2" style="max-height:200px;overflow-y:auto"></div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-secondary py-2">
        <?php if (!empty($entry['zentao_bug_id'])): ?>
        <button class="btn btn-info btn-sm" onclick="updateZentao()">
          <i class="bi bi-arrow-repeat me-1"></i>Sync to Zentao
        </button>
        <?php else: ?>
        <button class="btn btn-info btn-sm" id="zentaoCreateBtn" onclick="createZentaoBug()">
          <i class="bi bi-bug me-1"></i>Create Bug
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
const _zentaoCsrf = '<?= e(Auth::csrfToken()) ?>';

function zentaoBody() {
  return new URLSearchParams({
    _csrf:                _zentaoCsrf,
    title_template:       document.getElementById('zentaoTitleTpl')?.value ?? '',
    description_template: document.getElementById('zentaoDescTpl')?.value ?? '',
    issue_type:           document.getElementById('zentaoBugType')?.value ?? '',
    pri:                  document.getElementById('zentaoPri')?.value ?? '3',
  });
}

function createZentaoBug() {
  const btn = document.getElementById('zentaoCreateBtn');
  const res = document.getElementById('zentaoResult');
  const lbl = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating?'; res.innerHTML = '';
  fetch('<?= url('entries/' . $entry['id'] . '/zentao') ?>', { method: 'POST', body: zentaoBody() })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        res.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Bug created: <a href="' + d.url + '" target="_blank" class="text-success fw-bold">Bug #' + d.bug_id + '</a></span>';
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Done';
        setTimeout(() => location.reload(), 1800);
      } else {
        res.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (d.error || 'Unknown error') + '</span>';
        btn.disabled = false; btn.innerHTML = lbl;
      }
    })
    .catch(() => { res.innerHTML = '<span class="text-danger">Network error</span>'; btn.disabled = false; btn.innerHTML = lbl; });
}

function updateZentao() {
  const btn = document.querySelector('#zentaoModal button[onclick="updateZentao()"]');
  const res = document.getElementById('zentaoResult');
  const lbl = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing?'; res.innerHTML = '';
  fetch('<?= url('entries/' . $entry['id'] . '/zentao/update') ?>', { method: 'POST', body: zentaoBody() })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        res.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Synced to <a href="' + d.url + '" target="_blank" class="text-success">Bug #' + d.bug_id + '</a></span>';
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Done';
        setTimeout(() => location.reload(), 1800);
      } else {
        res.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (d.error || 'Unknown error') + '</span>';
        btn.disabled = false; btn.innerHTML = lbl;
      }
    })
    .catch(() => { res.innerHTML = '<span class="text-danger">Network error</span>'; btn.disabled = false; btn.innerHTML = lbl; });
}

function syncZentaoStatus(entryId, btn, mode) {
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  fetch('<?= url('') ?>entries/' + entryId + '/zentao/sync-status', {
    method: 'POST', body: new URLSearchParams({ _csrf: _zentaoCsrf, sync_mode: mode || 'quick' })
  })
  .then(r => r.json())
  .then(d => {
    btn.innerHTML = orig; btn.disabled = false;
    if (d.error) { showToast(d.error, 'danger'); return; }
    const diffs = [];
    if (d.status_differs)   diffs.push('Status: Zentao "' + d.zentao_status + '" ? ' + d.mapped);
    if (d.priority_differs) diffs.push('Priority: Zentao pri ' + d.zentao_pri + ' vs local "' + d.local_priority + '"');
    if (diffs.length) {
      showToast(diffs.join(' ? ') + '. Review changes.', 'warning');
      location.reload();
    } else {
      showToast((mode === 'full' ? 'Full' : 'Quick') + ' Sync: all checked fields in sync.', 'success');
    }
  })
  .catch(() => { btn.innerHTML = orig; btn.disabled = false; });
}

function checkZentaoChanges(entryId, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking?';
  fetch('<?= url('api/zentao-sync/check-record') ?>', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _zentaoCsrf, source_id: entryId })
  })
  .then(r => r.json())
  .then(d => {
    btn.disabled = false; btn.innerHTML = orig;
    if (d.error) { showToast('Zentao: ' + d.error, 'danger'); return; }
    if (d.has_changes) { location.reload(); }
    else {
      const statusEl = document.getElementById('zentaoSyncStatus');
      if (statusEl) statusEl.textContent = d.baseline_set ? 'Baseline set ? checking from now on.' : 'Up to date ? no changes detected.';
      showToast(d.baseline_set ? 'Zentao baseline established.' : 'Zentao is up to date ? no changes detected.', 'success');
    }
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = orig; showToast('Network error', 'danger'); });
}

// ?? Link existing Zentao bug ??????????????????????????????
let _zentaoSearchTimer = null;
function zentaoSearchDebounce() {
  clearTimeout(_zentaoSearchTimer);
  _zentaoSearchTimer = setTimeout(zentaoSearch, 350);
}
function zentaoSearch() {
  const q = document.getElementById('zentaoSearchQ')?.value.trim() ?? '';
  const res = document.getElementById('zentaoSearchResults');
  if (!res) return;
  res.innerHTML = '<span class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Searching?</span>';
  fetch('<?= url('api/zentao/bugs/search') ?>?q=' + encodeURIComponent(q))
    .then(r => r.json())
    .then(d => {
      if (d.error) { res.innerHTML = '<span class="text-danger small">' + d.error + '</span>'; return; }
      const bugs = d.bugs || [];
      if (!bugs.length) { res.innerHTML = '<span class="text-muted small">No bugs found.</span>'; return; }
      res.innerHTML = bugs.map(b =>
        `<div class="d-flex align-items-center gap-2 py-1 border-bottom border-secondary small">
          <span class="text-muted text-nowrap">#${b.id}</span>
          <span class="flex-grow-1 text-truncate">${b.title}</span>
          <span class="badge bg-secondary text-nowrap">${b.status || ''}</span>
          <button class="btn btn-outline-info btn-sm py-0 px-2 flex-shrink-0"
                  onclick="zentaoLinkBug(${b.id}, this)">Link</button>
        </div>`
      ).join('');
    })
    .catch(() => { res.innerHTML = '<span class="text-danger small">Search failed.</span>'; });
}
function zentaoLinkBug(bugId, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  fetch('<?= url('entries/' . $entry['id'] . '/zentao/link') ?>', {
    method: 'POST',
    body: new URLSearchParams({ _csrf: _zentaoCsrf, bug_id: bugId })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      document.getElementById('zentaoSearchResults').innerHTML =
        '<span class="text-success small"><i class="bi bi-check-circle me-1"></i>Linked to Bug #' + d.bug_id + '</span>';
      setTimeout(() => location.reload(), 1200);
    } else {
      btn.disabled = false; btn.innerHTML = orig;
      showToast(d.error || 'Link failed', 'danger');
    }
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = orig; showToast('Network error', 'danger'); });
}
</script>
<?php endif; ?>

<?php if (!empty($settings['sharepoint_tenant_id']) && !empty($settings['sharepoint_client_id'])): ?>
<?php
$project  = Database::fetchOne('SELECT sharepoint_folder FROM projects WHERE id = ?', [$entry['project_id']]);
$spUser   = Database::fetchOne('SELECT sharepoint_path_template FROM users WHERE id=?', [Auth::id()]);
$spTpl    = trim($spUser['sharepoint_path_template'] ?? '');

if ($spTpl !== '') {
    // Sanitize value for use inside a folder path segment
    $sanitize = fn(string $v): string => trim(preg_replace('/[\/\\\\:*?"<>|]/', '_', $v));
    $titleClean = $sanitize(preg_replace('/\s+/', '_', $entry['title'] ?? ''));
    $defaultSpFolder = str_replace(
        ['{{jira_key}}','{{title}}','{{project}}','{{serial}}','{{firmware}}','{{type}}','{{category}}','{{date}}','{{id}}','{{status}}'],
        [
            $sanitize($entry['jira_issue_key']    ?? ''),
            $titleClean,
            $sanitize($entry['project_name']      ?? ''),
            $sanitize($entry['mower_serial']      ?? ''),
            $sanitize($entry['firmware_version']  ?? ''),
            $sanitize($entry['type_name']         ?? ''),
            $sanitize($entry['cat_name']          ?? ''),
            $entry['entry_date'] ?? '',
            (string)$entry['id'],
            $sanitize($entry['status']            ?? ''),
        ],
        $spTpl
    );
} else {
    $defaultSpFolder = $project['sharepoint_folder'] ?? '';
}
?>
<div class="modal fade" id="spModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-cloud-arrow-up me-2 text-info"></i>Upload to SharePoint</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Target Folder <span class="text-danger">*</span></label>
          <input type="text" id="spFolder" class="form-control" value="<?= e($defaultSpFolder) ?>"
                 placeholder="e.g. RoboDoc/ProjectName/Attachments">
          <div class="form-text">Path relative to the SharePoint drive root. Folders are created automatically.</div>
        </div>
        <?php if ($attachments): ?>
        <div class="mb-3">
          <label class="form-label small">Select files to upload</label>
          <div class="card border-secondary" style="max-height:200px;overflow-y:auto">
            <?php foreach ($attachments as $att): ?>
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom border-secondary">
              <input type="checkbox" class="form-check-input sp-att-check" value="<?= $att['id'] ?>" checked>
              <span class="small"><?= e($att['display_name'] ?: $att['original_name']) ?></span>
              <span class="text-muted small ms-auto"><?= $att['mime_type'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="alert alert-secondary small">This entry has no attachments to upload.</div>
        <?php endif; ?>
        <div id="spResult"></div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <?php if ($attachments): ?>
        <button type="button" class="btn btn-info btn-sm" id="spUploadBtn" onclick="submitSharePoint()">
          <i class="bi bi-cloud-arrow-up me-1"></i>Upload
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
function submitSharePoint() {
  const btn    = document.getElementById('spUploadBtn');
  const result = document.getElementById('spResult');
  const folder = document.getElementById('spFolder').value.trim();
  if (!folder) { result.innerHTML = '<div class="alert alert-warning small py-2">Please enter a target folder.</div>'; return; }

  const attIds = [...document.querySelectorAll('.sp-att-check:checked')].map(c => c.value);
  if (!attIds.length) { result.innerHTML = '<div class="alert alert-warning small py-2">No files selected.</div>'; return; }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading?';
  result.innerHTML = '';

  const body = new URLSearchParams({ _csrf: '<?= e(Auth::csrfToken()) ?>', folder });
  attIds.forEach(id => body.append('att_ids[]', id));

  fetch('<?= url('entries/' . $entry['id'] . '/sharepoint') ?>', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        result.innerHTML = '<div class="alert alert-danger small py-2"><i class="bi bi-x-circle me-1"></i>' + data.error + '</div>';
      } else {
        let html = '<div class="alert alert-success small py-2"><i class="bi bi-check-circle me-1"></i>' + data.success + ' file(s) uploaded to <strong>' + data.folder + '</strong>';
        if (data.uploaded?.length) {
          html += '<ul class="mb-0 mt-1">';
          data.uploaded.forEach(f => {
            html += '<li>' + f.name + (f.skipped ? ' <span class="badge bg-secondary" style="font-size:.6rem">already on SharePoint - skipped</span>' : '') +
              (f.url ? ' &middot; <a href="' + f.url + '" target="_blank" class="alert-link">open</a>' : '') + '</li>';
          });
          html += '</ul>';
        }
        if (data.errors?.length) {
          html += '<hr class="my-1"><strong>Errors:</strong><ul class="mb-0">';
          data.errors.forEach(e => { html += '<li class="text-danger">' + e + '</li>'; });
          html += '</ul>';
        }
        html += '</div>';
        result.innerHTML = html;
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Upload';
    })
    .catch(() => {
      result.innerHTML = '<div class="alert alert-danger small py-2">Network error</div>';
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Upload';
    });
}
</script>
<?php endif; ?>
<script>
// Tag functions
var _tagCsrf   = '<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES) ?>';
var _tagEntry  = <?= (int)$entry['id'] ?>;
var _activeTags = new Set([<?= implode(',', array_column($entryTags ?? [], 'id')) ?>]);

function filterTagDropdown(q) {
  document.querySelectorAll('.tag-option').forEach(function(el) {
    el.style.display = !q || el.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
  });
}
function toggleEntryTag(tagId) {
  var id = Number(tagId);
  if (_activeTags.has(id)) _activeTags.delete(id);
  else _activeTags.add(id);
  var chk = document.getElementById('tck-' + tagId);
  if (chk) chk.classList.toggle('invisible', !_activeTags.has(id));
  _saveEntryTags();
}
function removeTag(tagId) {
  _activeTags.delete(Number(tagId));
  _saveEntryTags();
}
function _saveEntryTags() {
  var body = new URLSearchParams({_csrf: _tagCsrf});
  _activeTags.forEach(function(id) { body.append('tag_ids[]', id); });
  fetch('<?= url('entries/') ?>' + _tagEntry + '/tags', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: body
  }).then(function(r){return r.json();}).then(function(d){
    if (d.success) location.reload();
    else alert('Error: ' + (d.error||'Unknown'));
  }).catch(function(e){ alert('Network error: '+e); });
}
</script>

<!-- Standard Creation Wizard -->
<?php if (Auth::canEdit('entries')): ?>
<div class="modal fade" id="wizardModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-magic me-2 text-success"></i>Standard Creation Wizard</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="text-muted small mb-3">
        Runs the standard steps in sequence: Jira creation &rarr; SharePoint upload &rarr; Zentao creation &rarr; Jira update.
        Uses the same fields and templates as the individual Jira/Zentao/SharePoint buttons &mdash; nothing is changed about how those work, this just runs them one after another.
      </p>
      <?php $jiraConfigs = $jiraConfigs ?? []; ?>
      <?php if (count($jiraConfigs) > 1): ?>
      <div class="mb-3">
        <label class="form-label small"><i class="bi bi-bug-fill text-warning me-1"></i>Jira Project Destination</label>
        <select id="wizardJiraProject" class="form-select form-select-sm" onchange="syncWizardIssueType(this.value)">
          <?php foreach ($jiraConfigs as $jc): ?>
          <option value="<?= e($jc['jira_project_key']) ?>" data-issue-type="<?= e($jc['issue_type']) ?>">
            <?= e($jc['label'] ?: $jc['jira_project_key']) ?> (<?= e($jc['jira_project_key']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Which Jira project to create the issue in.</div>
      </div>
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label small">SharePoint target folder</label>
        <input type="text" id="wizardSpFolder" class="form-control form-control-sm" value="<?= e($defaultSpFolder ?? '') ?>">
        <div class="form-text">Only used if this entry has attachments. Same default as the SharePoint button uses.</div>
      </div>
      <div id="wizardSteps" class="d-flex flex-column gap-2">
        <div class="wizard-step d-flex align-items-center gap-3 p-2 rounded border border-secondary" data-step="jira">
          <span class="wizard-step-icon"><i class="bi bi-circle text-muted"></i></span>
          <span class="flex-grow-1"><i class="bi bi-bug-fill text-warning me-1"></i>1. Create Jira issue</span>
          <span class="wizard-step-status text-muted small">Pending</span>
        </div>
        <div class="wizard-step d-flex align-items-center gap-3 p-2 rounded border border-secondary" data-step="sharepoint">
          <span class="wizard-step-icon"><i class="bi bi-circle text-muted"></i></span>
          <span class="flex-grow-1"><i class="bi bi-cloud-arrow-up text-info me-1"></i>2. Upload attachments to SharePoint</span>
          <span class="wizard-step-status text-muted small">Pending</span>
        </div>
        <div class="wizard-step d-flex align-items-center gap-3 p-2 rounded border border-secondary" data-step="zentao">
          <span class="wizard-step-icon"><i class="bi bi-circle text-muted"></i></span>
          <span class="flex-grow-1"><i class="bi bi-bug text-primary me-1"></i>3. Create Zentao bug</span>
          <span class="wizard-step-status text-muted small">Pending</span>
        </div>
        <div class="wizard-step d-flex align-items-center gap-3 p-2 rounded border border-secondary" data-step="jira-update">
          <span class="wizard-step-icon"><i class="bi bi-circle text-muted"></i></span>
          <span class="flex-grow-1"><i class="bi bi-arrow-repeat text-warning me-1"></i>4. Update Jira issue (with SharePoint link)</span>
          <span class="wizard-step-status text-muted small">Pending</span>
        </div>
      </div>
      <div id="wizardLog" class="mt-3 small" style="max-height:140px;overflow-y:auto"></div>
    </div>
    <div class="modal-footer border-secondary">
      <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="wizardCloseBtn">Close</button>
      <button type="button" class="btn btn-success btn-sm" id="wizardStartBtn" onclick="runWizard()">
        <i class="bi bi-play-fill me-1"></i>Run Wizard
      </button>
    </div>
  </div></div>
</div>

<script>
function _wizardSetStep(step, state, msg) {
  // state: 'running' | 'done' | 'skip' | 'error'
  console.log('[wizard]', step, '->', state, msg || '');
  var el = document.querySelector('.wizard-step[data-step="' + step + '"]');
  if (!el) { console.warn('[wizard] step element not found:', step); return; }
  var icon = el.querySelector('.wizard-step-icon i');
  var status = el.querySelector('.wizard-step-status');
  var map = {
    running: ['bi-arrow-repeat text-info', 'Running...'],
    done:    ['bi-check-circle-fill text-success', 'Done'],
    skip:    ['bi-dash-circle text-muted', 'Skipped'],
    error:   ['bi-x-circle-fill text-danger', 'Failed'],
  };
  icon.className = map[state][0];
  status.textContent = msg || map[state][1];
  status.className = 'small ' + (state === 'error' ? 'text-danger' : (state === 'done' ? 'text-success' : 'text-muted'));
}
function _wizardLog(msg, isError) {
  var log = document.getElementById('wizardLog');
  var line = document.createElement('div');
  line.className = isError ? 'text-danger' : 'text-muted';
  line.innerHTML = (isError ? '<i class="bi bi-exclamation-triangle me-1"></i>' : '<i class="bi bi-check-lg me-1"></i>') + msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}

function syncWizardIssueType(projectKey) {
  var sel = document.getElementById('wizardJiraProject');
  if (!sel) return;
  var opt = sel.querySelector('option[value="' + projectKey + '"]');
  // Also sync the hidden Jira modal field so jiraCommonBody() picks it up
  var pkField = document.getElementById('jiraProjectKey');
  if (pkField) pkField.value = projectKey;
  var typeField = document.getElementById('jiraIssueType');
  if (typeField && opt && opt.dataset.issueType) typeField.value = opt.dataset.issueType;
}
var _wizardRunning = false;
async function runWizard() {
  if (_wizardRunning) { return; } // prevent double-invocation (double-click, etc.)
  _wizardRunning = true;
  var startBtn = document.getElementById('wizardStartBtn');
  var closeBtn = document.getElementById('wizardCloseBtn');
  startBtn.disabled = true;
  closeBtn.disabled = true;
  startBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Running...';
  document.getElementById('wizardLog').innerHTML = '';

  var entryHasJira    = <?= !empty($entry['jira_issue_key']) ? 'true' : 'false' ?>;
  var entryHasZentao  = <?= !empty($entry['zentao_bug_id']) ? 'true' : 'false' ?>;
  var hasAttachments  = <?= !empty($attachments) ? 'true' : 'false' ?>;

  // ?? Step 1: Create Jira (reuses the exact same body builder as the Jira button) ??
  if (entryHasJira) {
    _wizardSetStep('jira', 'skip', 'Already linked to Jira');
    _wizardLog('Jira already linked ? skipped.');
  } else {
    _wizardSetStep('jira', 'running');
    try {
      // If the wizard has its own project selector (multiple configs), override the modal's value
      var wizardSel = document.getElementById('wizardJiraProject');
      if (wizardSel) {
        var pkField = document.getElementById('jiraProjectKey');
        if (pkField) pkField.value = wizardSel.value;
        // Also sync issue type
        var selOpt = wizardSel.options[wizardSel.selectedIndex];
        var typeField = document.getElementById('jiraIssueType');
        if (typeField && selOpt && selOpt.dataset.issueType) typeField.value = selOpt.dataset.issueType;
      }
      var jiraResp = await fetch('<?= url('entries/' . $entry['id'] . '/jira') ?>', { method: 'POST', body: jiraCommonBody() });
      var jiraRespText = await jiraResp.text();
      var jiraData;
      try { jiraData = JSON.parse(jiraRespText); }
      catch (parseErr) {
        _wizardSetStep('jira', 'error', 'HTTP ' + jiraResp.status);
        _wizardLog('Jira step returned non-JSON response (HTTP ' + jiraResp.status + '). Raw: ' + jiraRespText.substring(0, 200), true);
        jiraData = null;
      }
      if (jiraData) {
        if (jiraData.success) {
          _wizardSetStep('jira', 'done', jiraData.key);
          _wizardLog('Jira issue created: ' + jiraData.key);
        } else {
          _wizardSetStep('jira', 'error', jiraData.error || 'Failed');
          _wizardLog('Jira step failed: ' + (jiraData.error || 'Unknown error'), true);
        }
      }
    } catch (e) {
      _wizardSetStep('jira', 'error', e.message || 'Network error');
      _wizardLog('Jira step error: ' + (e.message || 'Network error'), true);
    }
  }

  // ?? Step 2: SharePoint upload (reuses same endpoint + same attachment IDs as the SharePoint button) ??
  if (!hasAttachments) {
    _wizardSetStep('sharepoint', 'skip', 'No attachments');
    _wizardLog('No attachments to upload ? skipped.');
  } else {
    _wizardSetStep('sharepoint', 'running');
    try {
      var folder = document.getElementById('wizardSpFolder').value.trim() || 'Entry-<?= $entry['id'] ?>';
      var spBody = new URLSearchParams({ _csrf: '<?= e(Auth::csrfToken()) ?>', folder: folder });
      <?php foreach ($attachments as $att): ?>
      spBody.append('att_ids[]', '<?= $att['id'] ?>');
      <?php endforeach; ?>
      // Large videos can take a while to upload to SharePoint via Graph API.
      // Use an AbortController so the wizard doesn't hang forever if the server
      // times out without ever sending a response.
      var spController = new AbortController();
      var spTimeoutId = setTimeout(function() { spController.abort(); }, 240000); // 4 min
      _wizardLog('Uploading to SharePoint - this can take a while for large files...');
      var spResp = await fetch('<?= url('entries/' . $entry['id'] . '/sharepoint') ?>', {
        method: 'POST', body: spBody, signal: spController.signal
      });
      clearTimeout(spTimeoutId);
      _wizardLog('SharePoint server responded with HTTP ' + spResp.status + '. Reading response body...');
      // fetch() resolves once headers arrive; reading the body can still hang if the
      // server keeps the connection open without finishing the stream. Race it against
      // its own short timeout so the wizard never gets stuck here either.
      var spRespText = await Promise.race([
        spResp.text(),
        new Promise(function(_, reject) {
          setTimeout(function() { reject(new Error('TIMEOUT_READING_BODY')); }, 60000);
        })
      ]);
      var spData = null;
      try {
        spData = JSON.parse(spRespText);
      } catch (parseErr) {
        _wizardSetStep('sharepoint', 'error', 'HTTP ' + spResp.status + ' (non-JSON response)');
        _wizardLog('SharePoint returned a non-JSON response (HTTP ' + spResp.status + '). This usually means a PHP error on the server. Raw response: ' + spRespText.substring(0, 300), true);
      }
      if (spData && spData.error) {
        _wizardSetStep('sharepoint', 'error', spData.error);
        _wizardLog('SharePoint step failed: ' + spData.error, true);
      } else if (spData) {
        var skippedCount = (spData.uploaded || []).filter(function(f) { return f.skipped; }).length;
        var newCount = (spData.success || 0) - skippedCount;
        var summary = newCount + ' uploaded' + (skippedCount ? ', ' + skippedCount + ' already present (skipped)' : '');
        _wizardSetStep('sharepoint', 'done', summary);
        _wizardLog('SharePoint: ' + summary + ' in "' + spData.folder + '".');
        if (spData.errors && spData.errors.length) {
          spData.errors.forEach(function(e) { _wizardLog('SharePoint file error: ' + e, true); });
        }
      }
    } catch (e) {
      if (e.name === 'AbortError') {
        _wizardSetStep('sharepoint', 'error', 'Timed out after 4 min');
        _wizardLog('SharePoint step timed out (likely a large file). Upload may still finish on the server - check the entry afterwards.', true);
      } else if (e.message === 'TIMEOUT_READING_BODY') {
        _wizardSetStep('sharepoint', 'error', 'Response body never arrived');
        _wizardLog('SharePoint server sent HTTP 200 headers but the response body never finished arriving within 60s. The upload may have actually succeeded server-side - check the entry attachments / SharePoint folder directly.', true);
      } else {
        _wizardSetStep('sharepoint', 'error', e.message || 'Network error');
        _wizardLog('SharePoint step error: ' + (e.message || 'Network error'), true);
      }
    }
  }

  // ?? Step 3: Create Zentao (reuses the exact same body builder as the Zentao button) ??
  if (entryHasZentao) {
    _wizardSetStep('zentao', 'skip', 'Already linked to Zentao');
    _wizardLog('Zentao already linked ? skipped.');
  } else {
    _wizardSetStep('zentao', 'running');
    try {
      var zentaoResp = await fetch('<?= url('entries/' . $entry['id'] . '/zentao') ?>', { method: 'POST', body: zentaoBody() });
      var zentaoData = await zentaoResp.json();
      if (zentaoData.success) {
        _wizardSetStep('zentao', 'done', 'Bug #' + zentaoData.bug_id);
        _wizardLog('Zentao bug created: #' + zentaoData.bug_id);
      } else {
        _wizardSetStep('zentao', 'error', zentaoData.error || 'Failed');
        _wizardLog('Zentao step failed: ' + (zentaoData.error || 'Unknown error'), true);
      }
    } catch (e) {
      _wizardSetStep('zentao', 'error', 'Network error');
      _wizardLog('Zentao step network error.', true);
    }
  }

  // ?? Step 4: Update Jira again (reuses the exact same body builder as the "Sync to Jira" button) ??
  // Only makes sense if a Jira issue actually exists now (either pre-existing or just created in step 1)
  // and we actually uploaded something to SharePoint, so the description template's {{sharepoint}}/{{attachments}} has fresh data.
  var jiraNowLinked = entryHasJira || document.querySelector('.wizard-step[data-step="jira"] .wizard-step-status')?.textContent?.match(/^[A-Z]+-\d+/);
  if (!jiraNowLinked) {
    _wizardSetStep('jira-update', 'skip', 'No Jira issue to update');
    _wizardLog('No Jira issue linked ? update step skipped.');
  } else if (!hasAttachments) {
    _wizardSetStep('jira-update', 'skip', 'No SharePoint link to add');
    _wizardLog('No attachments were uploaded ? update step skipped.');
  } else {
    _wizardSetStep('jira-update', 'running');
    try {
      // Keep the same project key for the update step
      var wizardSel2 = document.getElementById('wizardJiraProject');
      if (wizardSel2) {
        var pkField2 = document.getElementById('jiraProjectKey');
        if (pkField2) pkField2.value = wizardSel2.value;
      }
      var updResp = await fetch('<?= url('entries/' . $entry['id'] . '/jira/update') ?>', { method: 'POST', body: jiraCommonBody() });
      var updData = await updResp.json();
      if (updData.success) {
        _wizardSetStep('jira-update', 'done');
        _wizardLog('Jira issue updated with SharePoint link.');
      } else {
        _wizardSetStep('jira-update', 'error', updData.error || 'Failed');
        _wizardLog('Jira update step failed: ' + (updData.error || 'Unknown error'), true);
      }
    } catch (e) {
      _wizardSetStep('jira-update', 'error', 'Network error');
      _wizardLog('Jira update step network error.', true);
    }
  }

  startBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Wizard complete';
  closeBtn.disabled = false;
  _wizardRunning = false;
  _wizardLog('Wizard finished. Reloading page in 2 seconds...');
  setTimeout(function() { location.reload(); }, 2000);
}
</script>
<?php endif; ?>

<!-- Epic Modal -->
<div class="modal fade" id="epicModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-lightning-fill text-warning me-2"></i>Epic zuordnen</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="text-muted small mb-3">Ordne diesen Eintrag einem Epic zu, um ihn in der Entries-Liste unter dem Epic zu gruppieren.</p>
      <?php
      $allEpics = Database::fetchAll(
          'SELECT ep.id, ep.title, ep.color, p.name project_name FROM epics ep LEFT JOIN projects p ON p.id=ep.project_id ORDER BY ep.project_id, ep.title'
      );
      ?>
      <?php if (!$allEpics): ?>
      <div class="text-muted small">Noch keine Epics vorhanden. <a href="<?= url('epics') ?>">Jetzt erstellen</a></div>
      <?php else: ?>
      <div class="d-flex flex-column gap-2">
        <?php foreach ($allEpics as $ep): ?>
        <button class="btn btn-outline-secondary text-start d-flex align-items-center gap-2 <?= ($entryEpic && $entryEpic['id']==$ep['id']) ? 'border-warning' : '' ?>"
                onclick="setEpic(<?= $ep['id'] ?>, '<?= e(Auth::csrfToken()) ?>')" type="button">
          <span style="width:12px;height:12px;border-radius:50%;background:<?= e($ep['color']) ?>;flex-shrink:0"></span>
          <span class="fw-semibold flex-grow-1"><?= e($ep['title']) ?></span>
          <?php if ($ep['project_name']): ?><span class="text-muted small"><?= e($ep['project_name']) ?></span><?php endif; ?>
          <?php if ($entryEpic && $entryEpic['id']==$ep['id']): ?><i class="bi bi-check-lg text-warning ms-auto"></i><?php endif; ?>
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="modal-footer border-secondary justify-content-between">
      <?php if ($entryEpic): ?>
      <button class="btn btn-outline-danger btn-sm" onclick="unsetEpic('<?= e(Auth::csrfToken()) ?>')">
        <i class="bi bi-x-lg me-1"></i>Epic entfernen
      </button>
      <?php else: ?><span></span><?php endif; ?>
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button>
    </div>
  </div></div>
</div>

<script>
function setEpic(epicId, csrf) {
  fetch('<?= url('entries/'.(int)$entry['id'].'/set-epic') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, epic_id: epicId})
  }).then(function(r){return r.json();}).then(function(d){
    if (d.success) location.reload();
    else alert(d.error || 'Fehler');
  });
}
function unsetEpic(csrf) {
  if (!confirm('Epic-Zuordnung aufheben?')) return;
  fetch('<?= url('entries/'.(int)$entry['id'].'/unset-epic') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf})
  }).then(function(r){return r.json();}).then(function(d){
    if (d.success) location.reload();
    else alert(d.error || 'Fehler');
  });
}
</script>

<!-- Sub-Ticket Modal -->
<div class="modal fade" id="subTicketModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-diagram-2 me-2 text-info"></i>Als Sub-Ticket verknupfen</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="text-muted small mb-3">
        Verknupfe diesen Eintrag als Sub-Ticket unter einem anderen Eintrag.
        Der Eintrag bleibt vollstandig erhalten und sichtbar ? er wird lediglich
        hierarchisch einem Hauptticket zugeordnet.
      </p>
      <?php if ($parentEntry): ?>
      <div class="alert alert-warning small py-2">
        Aktuell Sub-Ticket von <strong>#<?= $parentEntry['id'] ?> <?= e(mb_substr($parentEntry['title'],0,50)) ?></strong>.
        Eine neue Verknupfung uberschreibt die bestehende.
      </div>
      <?php endif; ?>
      <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Tickets die bereits Sub-Tickets sind können nicht als Hauptticket gewählt werden.
        Tickets mit vorhandenen Sub-Tickets werden entsprechend markiert.
      </div>
      <label class="form-label small">Hauptticket suchen</label>
      <input type="text" id="subTicketSearch" class="form-control mb-2"
             placeholder="Titel, Jira-Key oder ID..." oninput="searchSubTicketParent(this.value)">
      <div id="subTicketResults" style="max-height:220px;overflow-y:auto"></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    </div>
  </div></div>
</div>

<div class="modal fade" id="eightDModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <form method="POST" action="<?= url('8d/create') ?>">
      <?= csrfField() ?>
      <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
      <input type="hidden" name="project_id" value="<?= e($entry['project_id']) ?>">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-diagram-3 me-2 text-warning"></i>8D-Bericht erstellen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
          Erstellt einen neuen 8D-Bericht, verknüpft mit diesem Eintrag.
        </p>
        <label class="form-label small">Titel</label>
        <input type="text" name="title" class="form-control" required
               value="<?= e($entry['title'] ?: 'Entry #' . $entry['id']) ?>">
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-plus-lg me-1"></i>Erstellen</button>
      </div>
    </form>
  </div></div>
</div>

<script>
// Reset sub-ticket search when modal opens
document.addEventListener('DOMContentLoaded', function() {
  var stModal = document.getElementById('subTicketModal');
  if (stModal) {
    stModal.addEventListener('show.bs.modal', function() {
      document.getElementById('subTicketSearch').value = '';
      document.getElementById('subTicketResults').innerHTML = '';
    });
  }
});
function openSubTicketModal() {
  var el = document.getElementById('subTicketModal');
  if (!el) { console.error('subTicketModal not found'); return; }
  document.getElementById('subTicketSearch').value = '';
  document.getElementById('subTicketResults').innerHTML = '';
  new bootstrap.Modal(el).show();
}

var _stTimer;
var _stCsrf = '<?= e(Auth::csrfToken()) ?>';

function setParentById(parentId) {
  setParent(parentId, _stCsrf);
}
function searchSubTicketParent(q) {
  clearTimeout(_stTimer);
  var res = document.getElementById('subTicketResults');
  if (!q.trim()) { res.innerHTML = ''; return; }
  _stTimer = setTimeout(function() {
    var url = '<?= url('entries/' . (int)$entry['id'] . '/merge-preview') ?>?q=' + encodeURIComponent(q);
    fetch(url, {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(function(r) { return r.json(); }).then(function(results) {
      if (!results.length) {
        res.innerHTML = '<div class="text-muted small p-2">Keine Eintraege gefunden.</div>';
        return;
      }
      res.innerHTML = '<div class="list-group">' + results.map(function(e) {
        var isSub    = !!e.parent_id;
        var hasSubs  = e.sub_count > 0;
        var disabled = isSub ? 'disabled title="Dieses Ticket ist bereits ein Sub-Ticket und kann nicht als Hauptticket gewählt werden"' : '';
        var rowClass = isSub
          ? 'list-group-item list-group-item-action bg-dark border-secondary py-2 text-start d-flex align-items-center gap-2 opacity-50 text-decoration-line-through'
          : 'list-group-item list-group-item-action bg-dark border-secondary py-2 text-start d-flex align-items-center gap-2';
        var icon = isSub
          ? '<i class="bi bi-slash-circle text-danger flex-shrink-0" title="Bereits Sub-Ticket"></i>'
          : (hasSubs ? '<i class="bi bi-diagram-2-fill text-info flex-shrink-0" title="Hat ' + e.sub_count + ' Sub-Ticket(s)"></i>'
                     : '<i class="bi bi-diagram-2 text-secondary flex-shrink-0"></i>');
        var subBadge = isSub
          ? '<span class="badge bg-danger ms-1">Sub-Ticket</span>'
          : (hasSubs ? '<span class="badge bg-info text-dark ms-1">' + e.sub_count + ' Sub-Ticket(s)</span>' : '');
        var onclick = isSub ? '' : 'onclick="setParentById(' + e.id + ')"';
        return '<button class="' + rowClass + '" ' + onclick + ' ' + disabled + '>' +
          icon +
          '<span class="text-muted me-1 small">#' + e.id + '</span>' +
          '<span class="fw-semibold small flex-grow-1">' + (e.title||'').substring(0,60) + '</span>' +
          subBadge +
          (e.jira_issue_key ? '<span class="badge bg-dark border border-warning text-warning" style="font-size:.6rem">' + e.jira_issue_key + '</span>' : '') +
          '</button>';
      }).join('') + '</div>';
    });
  }, 300);
}

function setParent(parentId, csrf) {
  if (!confirm('Diesen Eintrag als Sub-Ticket von #' + parentId + ' festlegen?')) return;
  fetch('<?= url('entries/' . (int)$entry['id'] . '/set-parent') ?>', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, parent_id: parentId})
  }).then(function(r) { return r.json(); }).then(function(d) {
    if (d.success) {
      bootstrap.Modal.getInstance(document.getElementById('subTicketModal')).hide();
      location.reload();
    } else {
      alert(d.error || 'Fehler');
    }
  });
}

function unsetParent(csrf) {
  if (!confirm('Verknupfung mit dem Hauptticket aufheben?')) return;
  fetch('<?= url('entries/' . (int)$entry['id'] . '/unset-parent') ?>', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf})
  }).then(function(r) { return r.json(); }).then(function(d) {
    if (d.success) location.reload();
    else alert(d.error || 'Fehler');
  });
}
</script>

<!-- Merge Modal -->
<?php if (Auth::canEdit('entries') && !$entry['is_merged']): ?>
<div class="modal fade" id="mergeModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-2 text-warning"></i>Tickets zusammenfuhren</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="alert alert-warning py-2 small mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Das sekundare Ticket wird archiviert und bleibt uber einen Link erreichbar. Inhalte, Anhange und Kommentare werden ubernommen.
      </div>
      <!-- Step 1: Search -->
      <div id="mergeStep1">
        <label class="form-label fw-semibold">Ticket suchen zum Zusammenfuhren</label>
        <input type="text" id="mergeSearch" class="form-control mb-2" placeholder="Titel, Jira-Key oder ID..." oninput="searchMerge(this.value)">
        <div id="mergeResults" style="max-height:200px;overflow-y:auto"></div>
      </div>
      <!-- Step 2: Configure -->
      <div id="mergeStep2" style="display:none">
        <div class="row g-3 mb-4">
          <!-- Primary entry -->
          <div class="col-md-6">
            <div class="card border-success">
              <div class="card-header border-success bg-success bg-opacity-10 py-2 d-flex align-items-center gap-2">
                <i class="bi bi-star-fill text-success"></i>
                <span class="fw-semibold small">Hauptticket (bleibt aktiv)</span>
              </div>
              <div class="card-body py-2">
                <div id="primaryEntry" class="small fw-semibold"></div>
                <button class="btn btn-outline-secondary btn-sm mt-2 w-100" onclick="swapPrimary()">
                  <i class="bi bi-arrow-left-right me-1"></i>Tauschen
                </button>
              </div>
            </div>
          </div>
          <!-- Secondary entry -->
          <div class="col-md-6">
            <div class="card border-warning">
              <div class="card-header border-warning bg-warning bg-opacity-10 py-2 d-flex align-items-center gap-2">
                <i class="bi bi-archive text-warning"></i>
                <span class="fw-semibold small">Sekundares Ticket (wird archiviert)</span>
              </div>
              <div class="card-body py-2">
                <div id="secondaryEntry" class="small fw-semibold"></div>
              </div>
            </div>
          </div>
        </div>
        <!-- Field selection -->
        <div class="card mb-3">
          <div class="card-header border-secondary py-2">
            <span class="fw-semibold small">Felder vom sekundaren Ticket ubernehmen</span>
            <small class="text-muted ms-2">(leer lassen = Felder des Haupttickets behalten)</small>
          </div>
          <div class="card-body" id="mergeFieldsBody">
            <!-- Filled by JS -->
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-warning btn-sm" id="mergeConfirmBtn" style="display:none" onclick="executeMerge('<?= e(Auth::csrfToken()) ?>')">
        <i class="bi bi-arrow-right-circle me-1"></i>Zusammenfuhren
      </button>
    </div>
  </div></div>
</div>

<script>
var _mergeSourceId = <?= (int)$entry['id'] ?>;
var _mergeTargetId = null;
var _mergePrimaryId = <?= (int)$entry['id'] ?>;
var _mergeSecondaryId = null;
var _mergeTargetData = null;
var _mergeSourceData = <?= json_encode(['id' => (int)$entry['id'], 'title' => $entry['title'] ?? '', 'jira_issue_key' => $entry['jira_issue_key'] ?? '']) ?>;
var _mergeFieldGroups = [
  { group: 'Allgemein', fields: [
    {key:'title',             label:'Titel',            appendable:false},
    {key:'description',       label:'Beschreibung',     appendable:true},
    {key:'priority',          label:'Prioritat',        appendable:false},
    {key:'status',            label:'Status',           appendable:false},
    {key:'assigned_to',       label:'Zuweisung',        appendable:false},
    {key:'entry_type_id',     label:'Eintragstyp',      appendable:false},
    {key:'error_category_id', label:'Kategorie',        appendable:false},
  ]},
  { group: 'Beschreibung', fields: [
    {key:'summary',           label:'Summary',          appendable:true},
    {key:'steps_to_reproduce',label:'Steps to Reproduce', appendable:true},
    {key:'expected_result',   label:'Expected Result',  appendable:true},
    {key:'actual_result',     label:'Actual Result',    appendable:true},
  ]},
  { group: 'Gerat & Umgebung', fields: [
    {key:'mower_serial',      label:'Maher Serial',     appendable:false},
    {key:'firmware_version',  label:'Firmware',         appendable:false},
    {key:'app_version',       label:'App Version',      appendable:false},
    {key:'environment_id',    label:'Umgebung',         appendable:false},
    {key:'test_area_id',      label:'Testarea',         appendable:false},
    {key:'weather_condition', label:'Wetter',           appendable:false},
    {key:'temperature',       label:'Temperatur',       appendable:false},
    {key:'gps_lat',           label:'GPS Lat',          appendable:false},
    {key:'gps_lon',           label:'GPS Lon',          appendable:false},
  ]},
  { group: 'Anhange', fields: [
    {key:'attachments', label:'Anhange (werden hinzugefugt)', appendable:false, special:true},
  ]},
];
// Flat list for compatibility
var _mergeFields = _mergeFieldGroups.reduce(function(a,g){return a.concat(g.fields);}, []);

function openMergeModal() {
  _mergeTargetId = null;
  _mergePrimaryId = _mergeSourceId;
  document.getElementById('mergeStep2').style.display = 'none';
  document.getElementById('mergeStep1').style.display = '';
  document.getElementById('mergeConfirmBtn').style.display = 'none';
  document.getElementById('mergeSearch').value = '';
  document.getElementById('mergeResults').innerHTML = '';
  new bootstrap.Modal(document.getElementById('mergeModal')).show();
}

var _mergeTimer;
function searchMerge(q) {
  clearTimeout(_mergeTimer);
  if (!q.trim()) { document.getElementById('mergeResults').innerHTML = ''; return; }
  _mergeTimer = setTimeout(function() {
    fetch('<?= url('entries/'.(int)$entry['id'].'/merge-preview') ?>?q=' + encodeURIComponent(q), {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(function(r) { return r.json(); })
    .then(function(results) {
      var res = document.getElementById('mergeResults');
      if (!results.length) { res.innerHTML = '<div class="text-muted small p-2">Keine Tickets gefunden.</div>'; return; }
      // Store results for lookup by id
      window._mergeResultsMap = {};
      results.forEach(function(e) { window._mergeResultsMap[e.id] = e; });
      res.innerHTML = '<div class="list-group">' + results.map(function(e) {
        var title = (e.title||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').substring(0,60);
        var jira  = e.jira_issue_key ? '<span class="badge bg-dark border border-warning text-warning ms-2" style="font-size:.6rem">' + e.jira_issue_key + '</span>' : '';
        return '<button class="list-group-item list-group-item-action bg-dark border-secondary py-2 text-start" onclick="selectMergeById(' + e.id + ')">' +
          '<span class="text-muted me-2 small">#' + e.id + '</span>' +
          '<span class="fw-semibold small">' + title + '</span>' + jira + '</button>';
      }).join('') + '</div>';
    });
  }, 300);
}

function selectMergeById(id) {
  var target = window._mergeResultsMap && window._mergeResultsMap[id];
  if (!target) return;
  selectMergeTarget(target);
}

function selectMergeTarget(target) {
  _mergeTargetId = target.id;
  _mergeTargetData = target;
  _mergePrimaryId = _mergeSourceId;
  _mergeSecondaryId = _mergeTargetId;
  document.getElementById('mergeStep1').style.display = 'none';
  document.getElementById('mergeStep2').style.display = '';
  document.getElementById('mergeConfirmBtn').style.display = '';
  renderMergeEntries();
  renderMergeFields();
}

function renderMergeEntries() {
  var primary = _mergePrimaryId === _mergeSourceId ? _mergeSourceData : _mergeTargetData;
  var secondary = _mergePrimaryId === _mergeSourceId ? _mergeTargetData : _mergeSourceData;
  function _esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  document.getElementById('primaryEntry').innerHTML = '#' + primary.id + ' ' + _esc((primary.title||'').substring(0,50)) +
    (primary.jira_issue_key ? '<br><span class="badge bg-dark border border-warning text-warning mt-1" style="font-size:.6rem">' + _esc(primary.jira_issue_key) + '</span>' : '');
  document.getElementById('secondaryEntry').innerHTML = '#' + secondary.id + ' ' + _esc((secondary.title||'').substring(0,50)) +
    (secondary.jira_issue_key ? '<br><span class="badge bg-dark border border-warning text-warning mt-1" style="font-size:.6rem">' + _esc(secondary.jira_issue_key) + '</span>' : '');
}

function swapPrimary() {
  _mergePrimaryId = _mergePrimaryId === _mergeSourceId ? _mergeTargetId : _mergeSourceId;
  _mergeSecondaryId = _mergePrimaryId === _mergeSourceId ? _mergeTargetId : _mergeSourceId;
  renderMergeEntries();
}

function renderMergeFields() {
  var container = document.getElementById('mergeFieldsBody');
  container.innerHTML = '';
  _mergeFieldGroups.forEach(function(grp) {
    // Group header
    var hdr = document.createElement('div');
    hdr.className = 'fw-semibold small text-muted text-uppercase mt-3 mb-1';
    hdr.style.letterSpacing = '.05em';
    hdr.textContent = grp.group;
    container.appendChild(hdr);
    grp.fields.forEach(function(f) {
      var row = document.createElement('div');
      row.className = 'd-flex align-items-center gap-3 py-1 border-bottom border-secondary';
      // Checkbox
      var cb = document.createElement('input');
      cb.type = 'checkbox'; cb.className = 'form-check-input merge-field-cb';
      cb.id = 'mf_' + f.key; cb.value = f.key; cb.name = 'merge_fields';
      var lbl = document.createElement('label');
      lbl.className = 'form-check-label small'; lbl.htmlFor = cb.id;
      lbl.textContent = f.label;
      if (f.special) lbl.style.fontStyle = 'italic';
      var cbWrap = document.createElement('div');
      cbWrap.className = 'form-check mb-0 flex-grow-1';
      cbWrap.appendChild(cb); cbWrap.appendChild(lbl);
      row.appendChild(cbWrap);
      if (f.appendable) {
        var modeDiv = document.createElement('div');
        modeDiv.id = 'mode_' + f.key; modeDiv.style.display = 'none';
        modeDiv.innerHTML =
          '<div class="btn-group btn-group-sm">' +
          '<input type="radio" class="btn-check" name="mode_' + f.key + '" id="mr_' + f.key + '" value="replace" checked>' +
          '<label class="btn btn-outline-secondary" for="mr_' + f.key + '"><i class="bi bi-arrow-repeat me-1"></i>Ersetzen</label>' +
          '<input type="radio" class="btn-check" name="mode_' + f.key + '" id="ma_' + f.key + '" value="append">' +
          '<label class="btn btn-outline-info" for="ma_' + f.key + '"><i class="bi bi-text-paragraph me-1"></i>Anhangen</label>' +
          '</div>';
        cb.addEventListener('change', (function(md) {
          return function() { md.style.display = this.checked ? '' : 'none'; };
        })(modeDiv));
        row.appendChild(modeDiv);
      } else if (!f.special) {
        var hint = document.createElement('span');
        hint.className = 'text-muted small'; hint.textContent = 'wird ersetzt';
        row.appendChild(hint);
      }
      container.appendChild(row);
    });
  });
  // Select all / None buttons
  var btns = document.createElement('div');
  btns.className = 'd-flex gap-2 mt-3';
  btns.innerHTML = '<button class="btn btn-outline-secondary btn-sm" onclick="toggleAllMergeFields(true)">Alle ausw.</button>' +
    '<button class="btn btn-outline-secondary btn-sm" onclick="toggleAllMergeFields(false)">Keine</button>';
  container.appendChild(btns);
}

function toggleAllMergeFields(check) {
  document.querySelectorAll('.merge-field-cb').forEach(function(cb) {
    cb.checked = check;
    cb.dispatchEvent(new Event('change'));
  });
}

function executeMerge(csrf) {
  if (!confirm('Ticket #' + _mergeSecondaryId + ' wird archiviert und kann nicht ruckgangig gemacht werden. Fortfahren?')) return;
  var body = new URLSearchParams({_csrf: csrf, target_id: _mergeTargetId, primary_id: _mergePrimaryId});
  document.querySelectorAll('input[name="merge_fields"]:checked').forEach(function(cb) {
    body.append('fields[]', cb.value);
    // Send mode (replace/append) for each field
    var modeEl = document.querySelector('input[name="mode_' + cb.value + '"]:checked');
    if (modeEl) body.append('field_mode[' + cb.value + ']', modeEl.value);
  });
  fetch('<?= url('entries/'.(int)$entry['id'].'/merge') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: body
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.success) {
      bootstrap.Modal.getInstance(document.getElementById('mergeModal')).hide();
      location.href = '<?= url('entries/') ?>' + d.primary_id;
    } else {
      alert(d.error || 'Fehler beim Zusammenfuhren');
    }
  });
}
</script>
<?php endif; ?>

<!-- Export Wizard Modal -->
<div class="modal fade" id="exportWizardModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-file-earmark-arrow-down me-2 text-info"></i>Export Entry</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Step 1: Template — loaded via PHP directly -->
        <div id="ewStep1">
          <h6 class="mb-3">Step 1 — Choose Template</h6>
          <div class="row g-2 mb-3" id="ewTemplateList">
            <?php
            $ewTemplates = Database::fetchAll('SELECT id, name, description, primary_color, accent_color, is_default FROM entry_export_templates ORDER BY is_default DESC, name');
            foreach ($ewTemplates as $ewTpl):
            ?>
            <div class="col-md-4">
              <div class="card border-secondary tpl-card h-100" data-id="<?= $ewTpl['id'] ?>"
                   style="cursor:pointer;transition:border-color .15s"
                   onclick="ewSelectTemplate(<?= $ewTpl['id'] ?>, this)">
                <div class="card-body py-2 px-3">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="rounded-circle flex-shrink-0" style="width:14px;height:14px;background:<?= e($ewTpl['primary_color']) ?>;display:inline-block"></span>
                    <span class="fw-semibold"><?= e($ewTpl['name']) ?></span>
                    <?php if ($ewTpl['is_default']): ?><span class="badge bg-success ms-auto" style="font-size:.65rem">Default</span><?php endif; ?>
                  </div>
                  <?php if (!empty($ewTpl['description'])): ?>
                  <div class="text-muted" style="font-size:.75rem"><?= e($ewTpl['description']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <a href="<?= url('admin/export-templates') ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="bi bi-gear me-1"></i>Manage Templates
          </a>
        </div>
        <!-- Step 2: Fields -->
        <div id="ewStep2" style="display:none">
          <h6 class="mb-3">Step 2 — Select Fields to Include</h6>
          <div class="row g-2" id="ewFieldList">
            <?php
            $ewFields = [
              'metadata'    =>['Metadata','bi-info-circle',true],
              'description' =>['Description','bi-text-paragraph',true],
              'attachments' =>['Attachments','bi-paperclip',true],
              'images'      =>['Embedded Images','bi-image',true],
              'comments'    =>['Comments','bi-chat',true],
              'test_results'=>['Test Results / Partial Results','bi-clipboard2-check',true],
              'jira_info'   =>['Jira Information','bi-link-45deg',true],
              'history'     =>['Change History','bi-clock-history',false],
              'sub_entries' =>['Sub-Entries','bi-diagram-3',false],
            ];
            foreach ($ewFields as $key => [$label, $icon, $default]):
            ?>
            <div class="col-md-4">
              <div class="form-check">
                <input type="checkbox" class="form-check-input ew-field" id="ewf_<?= $key ?>"
                       data-key="<?= $key ?>" <?= $default?'checked':'' ?>>
                <label class="form-check-label" for="ewf_<?= $key ?>">
                  <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-3" id="ewImageSizeWrap">
            <label for="ewImageSize" class="form-label small text-muted mb-1">
              <i class="bi bi-arrows-angle-expand me-1"></i>Image Size
            </label>
            <select id="ewImageSize" class="form-select form-select-sm" style="max-width:220px">
              <option value="small">Small (compact grid)</option>
              <option value="medium" selected>Medium (default)</option>
              <option value="large">Large</option>
              <option value="full">Full width (one per row)</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <button class="btn btn-outline-secondary" id="ewBack" style="display:none" onclick="ewGoBack()">
          <i class="bi bi-arrow-left me-1"></i>Back
        </button>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-info text-white" id="ewNext" onclick="ewNext()">
          Next <i class="bi bi-arrow-right ms-1"></i>
        </button>
        <button class="btn btn-primary" id="ewExport" style="display:none" onclick="ewDoExport()">
          <i class="bi bi-file-earmark-arrow-down me-1"></i>Open Export
        </button>
      </div>
    </div>
  </div>
</div>
<script>
var _ewEntryId = null, _ewTemplateId = null;
function openExportWizard(entryId) {
  _ewEntryId    = entryId;
  _ewTemplateId = null;
  // Reset to step 1
  document.getElementById('ewStep1').style.display = '';
  document.getElementById('ewStep2').style.display = 'none';
  document.getElementById('ewNext').style.display   = '';
  document.getElementById('ewExport').style.display = 'none';
  document.getElementById('ewBack').style.display   = 'none';
  // Auto-select default template
  var def = document.querySelector('.tpl-card');
  if (def) ewSelectTemplate(parseInt(def.dataset.id), def);
  new bootstrap.Modal(document.getElementById('exportWizardModal')).show();
}
function ewSelectTemplate(id, el) {
  _ewTemplateId = id;
  document.querySelectorAll('.tpl-card').forEach(function(c) {
    c.classList.remove('border-info');
    c.style.borderColor = '';
    c.style.boxShadow = '';
  });
  var card = el.closest ? el.closest('.tpl-card') : el;
  card.classList.add('border-info');
  card.style.borderColor = '#0dcaf0';
  card.style.boxShadow = '0 0 0 2px rgba(13,202,240,.4)';
}
function ewNext() {
  if (!_ewTemplateId) { alert('Please select a template.'); return; }
  document.getElementById('ewStep1').style.display = 'none';
  document.getElementById('ewStep2').style.display = '';
  document.getElementById('ewNext').style.display   = 'none';
  document.getElementById('ewExport').style.display = '';
  document.getElementById('ewBack').style.display   = '';
}
function ewGoBack() {
  document.getElementById('ewStep1').style.display = '';
  document.getElementById('ewStep2').style.display = 'none';
  document.getElementById('ewNext').style.display   = '';
  document.getElementById('ewExport').style.display = 'none';
  document.getElementById('ewBack').style.display   = 'none';
}
function ewDoExport() {
  var params = ['template=' + _ewTemplateId];
  document.querySelectorAll('.ew-field').forEach(function(cb) {
    params.push('f_' + cb.dataset.key + '=' + (cb.checked ? '1' : '0'));
  });
  params.push('img_size=' + document.getElementById('ewImageSize').value);
  var url = '<?= url('entries/') ?>' + _ewEntryId + '/export?' + params.join('&');
  window.open(url, '_blank');
  bootstrap.Modal.getInstance(document.getElementById('exportWizardModal'))?.hide();
}
</script>

