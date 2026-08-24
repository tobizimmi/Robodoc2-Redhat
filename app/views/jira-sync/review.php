<?php
$hasError        = !empty($state['error']);
$jiraDesc        = $state['description'] ?? '';
$fieldDiff       = $fieldDiff ?? [];
$parsedFreeText  = $state['parsed_free_text'] ?? '';
$descLocal       = $localDescription;
$descJira        = $fieldDiff ? $parsedFreeText : $jiraDesc;
if (!function_exists('_normText')) {
    function _normText(string $s): string {
        return trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($s, ENT_HTML5|ENT_QUOTES, 'UTF-8'))));
    }
}
$descChanged     = !$hasError && _normText($descJira) !== _normText($descLocal);
$allComments     = $state['comments'] ?? [];
$newComments     = array_values(array_filter($allComments, fn($c) => !in_array($c['id'], $existingCommentIds, true)));
$jiraAttachments    = $state['attachments'] ?? [];
$localAttachments   = $localAttachments ?? [];
$localAttachNames   = array_map(fn($a) => strtolower($a['original_name'] ?? $a['display_name'] ?? ''), $localAttachments);
$newJiraAttachments = array_values(array_filter($jiraAttachments, fn($a) => !in_array(strtolower($a['filename']), $localAttachNames, true)));
$anyFieldChanged    = !empty(array_filter($fieldDiff, fn($f) => $f['changed']));
$anyChange          = $anyFieldChanged || $descChanged || $newComments;

// Serialize new attachments for the accept form (auto-download on accept)
$newAttsJson = $sourceType === 'entry' && $newJiraAttachments
    ? json_encode(array_map(fn($a) => ['content_url' => $a['contentUrl'], 'filename' => $a['filename'], 'mime_type' => $a['mimeType']], $newJiraAttachments))
    : '';
?>

<!-- Header -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= e($backUrl) ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1"><?= e($title) ?></h5>
  <?php if (!$hasError): ?>
  <a href="<?= e($state['jira_url'] ?? '#') ?>/browse/<?= e($state['key'] ?? '') ?>" target="_blank"
     class="btn btn-outline-warning btn-sm">
    <i class="bi bi-box-arrow-up-right me-1"></i><?= e($state['key'] ?? '') ?>
  </a>
  <?php endif; ?>
</div>

<?php if ($hasError): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($state['error']) ?></div>
<?php else: ?>

<!-- Direction chooser -->
<div class="card mb-4 border-secondary">
  <div class="card-body py-3">
    <div class="row g-3 align-items-center">
      <?php if ($sourceType === 'entry'): ?>
      <div class="col-md-5">
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-cloud-upload text-warning fs-5 flex-shrink-0 mt-1"></i>
          <div>
            <div class="fw-semibold small">Push local → Jira</div>
            <div class="text-muted small">Overwrite the Jira issue with your current local entry.</div>
            <form method="POST" action="<?= url('jira-sync/entry/' . $sourceId . '/push') ?>" class="mt-2">
              <?= csrfField() ?>
              <button class="btn btn-warning btn-sm"><i class="bi bi-cloud-upload me-1"></i>Push to Jira</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-2 text-center d-none d-md-block text-muted" style="font-size:1.5rem">⇄</div>
      <?php endif; ?>
      <div class="col-md-5">
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-cloud-download text-info fs-5 flex-shrink-0 mt-1"></i>
          <div>
            <div class="fw-semibold small">Import Jira → local</div>
            <div class="text-muted small">
              <?php if ($anyChange || $newJiraAttachments): ?>
              Select what to import below, then click <strong>Accept &amp; Import</strong>.
              <?php else: ?>
              <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No tracked changes — the Jira update was likely to status, assignee, or sprint. Use <strong>Dismiss</strong> to clear.</span>
              <?php endif; ?>
            </div>
            <div class="text-muted" style="font-size:.75rem;margin-top:4px">Jira last updated: <?= e($state['updated_at']) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php /* ══════════════════════════════════════════════════════════════════
       ACCEPT FORM — no nested forms inside here
       ══════════════════════════════════════════════════════════════════ */ ?>
<form method="POST" action="<?= e($acceptUrl) ?>">
  <?= csrfField() ?>
  <?php if ($newAttsJson): ?>
  <input type="hidden" name="jira_attachments" value="<?= e($newAttsJson) ?>">
  <?php endif; ?>

  <!-- Structured field diff -->
  <?php if ($fieldDiff): ?>
  <div class="card mb-4">
    <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
      <i class="bi bi-table me-1"></i>Structured Fields
      <span class="badge <?= $anyFieldChanged ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1">
        <?= $anyFieldChanged ? 'Changes detected' : 'No changes' ?>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0 small align-middle">
        <thead class="table-dark">
          <tr>
            <th class="ps-3" style="width:18%">Field</th>
            <th style="width:35%">Local (current)</th>
            <th style="width:35%">Jira</th>
            <th class="text-center" style="width:12%">Import?</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fieldDiff as $f): ?>
          <tr class="<?= $f['changed'] ? 'table-warning' : '' ?>">
            <td class="ps-3 fw-semibold text-nowrap"><?= e($f['label']) ?></td>
            <td class="<?= $f['changed'] ? '' : 'text-muted' ?>"><?= e($f['local'] ?: '—') ?></td>
            <td class="<?= $f['changed'] ? 'fw-semibold' : 'text-muted' ?>"><?= e($f['jira'] ?: '—') ?></td>
            <td class="text-center">
              <?php if ($f['changed']): ?>
              <input type="checkbox" class="form-check-input" name="accept_fields[]" value="<?= e($f['jira_label']) ?>" checked>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Description diff -->
  <div class="card mb-4">
    <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
      <i class="bi bi-file-text me-1"></i><?= $fieldDiff ? 'Free-text Description' : 'Description' ?>
      <span class="badge <?= $descChanged ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1">
        <?= $descChanged ? 'Changed' : 'No change' ?>
      </span>
      <?php if ($descChanged): ?>
      <div class="ms-auto form-check mb-0">
        <input type="checkbox" class="form-check-input" name="accept_description" id="acceptDesc" value="1" checked>
        <label class="form-check-label small" for="acceptDesc">Import description</label>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-body p-0">
      <div class="row g-0">
        <div class="col-md-6 border-end border-secondary p-3">
          <div class="text-muted small fw-semibold mb-2">Local (current)</div>
          <pre class="mb-0 small" style="white-space:pre-wrap;font-family:inherit;max-height:300px;overflow-y:auto"><?= e($descLocal ?: '(empty)') ?></pre>
        </div>
        <div class="col-md-6 p-3 <?= $descChanged ? 'bg-warning bg-opacity-10' : '' ?>">
          <div class="text-muted small fw-semibold mb-2">Jira</div>
          <pre class="mb-0 small" style="white-space:pre-wrap;font-family:inherit;max-height:300px;overflow-y:auto"><?= e($descJira ?: '(empty)') ?></pre>
        </div>
      </div>
    </div>
  </div>

  <!-- Comments -->
  <div class="card mb-4">
    <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
      <i class="bi bi-chat-left-text me-1"></i>Jira Comments
      <span class="badge bg-secondary ms-1"><?= count($allComments) ?> total</span>
      <?php if ($newComments): ?>
      <span class="badge bg-warning text-dark"><?= count($newComments) ?> new</span>
      <?php endif; ?>
    </div>
    <?php if (!$allComments): ?>
    <div class="card-body text-muted small">No comments on this Jira issue.</div>
    <?php else: ?>
    <div class="list-group list-group-flush">
      <?php foreach ($allComments as $c): ?>
      <?php $isNew = !in_array($c['id'], $existingCommentIds, true); ?>
      <div class="list-group-item bg-transparent <?= $isNew ? 'border-start border-warning border-3' : '' ?>">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-person-circle text-muted"></i>
          <span class="fw-semibold small"><?= e($c['author']) ?></span>
          <span class="text-muted small"><?= e($c['created_at']) ?></span>
          <?php if ($isNew): ?><span class="badge bg-warning text-dark ms-1">New</span><?php endif; ?>
        </div>
        <pre class="mb-0 small ms-4" style="white-space:pre-wrap;font-family:inherit"><?= e($c['body']) ?></pre>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Accept actions -->
  <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
    <button type="submit" class="btn btn-info">
      <i class="bi bi-cloud-download me-1"></i>Accept &amp; Import from Jira
      <?php if ($newJiraAttachments): ?>
      <span class="badge bg-white text-info ms-1"><?= count($newJiraAttachments) ?> attachment<?= count($newJiraAttachments) !== 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </button>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
  <div class="text-muted small mb-4">
    <?php if ($newJiraAttachments): ?>
    <i class="bi bi-info-circle me-1"></i>Accepting will also download <?= count($newJiraAttachments) ?> new attachment<?= count($newJiraAttachments) !== 1 ? 's' : '' ?> from Jira into the local entry.
    <?php endif; ?>
  </div>

</form><?php /* ← accept form ends here — no download forms were inside it */ ?>

<?php /* ══════════════════════════════════════════════════════════════════
       ATTACHMENTS — completely outside the accept form, own standalone forms
       ══════════════════════════════════════════════════════════════════ */ ?>
<?php if ($jiraAttachments): ?>
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2 flex-wrap">
    <i class="bi bi-paperclip me-1"></i>Attachments in Jira
    <span class="badge bg-secondary ms-1"><?= count($jiraAttachments) ?></span>
    <?php if ($newJiraAttachments): ?>
    <span class="badge bg-warning text-dark"><?= count($newJiraAttachments) ?> not yet local</span>
    <?php if ($sourceType === 'entry'): ?>
    <form method="POST" action="<?= url('jira-sync/entry/' . $sourceId . '/download-attachment') ?>" class="ms-auto">
      <?= csrfField() ?>
      <input type="hidden" name="attachments" value="<?= e($newAttsJson) ?>">
      <button class="btn btn-warning btn-sm py-0 px-2">
        <i class="bi bi-cloud-download me-1"></i>Download All New (<?= count($newJiraAttachments) ?>)
      </button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <div class="list-group list-group-flush">
    <?php foreach ($jiraAttachments as $a):
      $isNew = !in_array(strtolower($a['filename']), $localAttachNames, true);
    ?>
    <div class="list-group-item bg-transparent d-flex align-items-center gap-2 py-2 small <?= $isNew ? 'border-start border-warning border-3' : '' ?>">
      <i class="bi bi-paperclip text-muted flex-shrink-0"></i>
      <span class="flex-grow-1 text-truncate"><?= e($a['filename']) ?></span>
      <span class="text-muted text-nowrap"><?= formatFileSize((int)$a['size']) ?></span>
      <span class="text-muted text-nowrap d-none d-md-inline"><?= e($a['author']) ?></span>
      <span class="text-muted text-nowrap d-none d-md-inline"><?= e(substr($a['created'], 0, 10)) ?></span>
      <?php if ($isNew && $sourceType === 'entry'): ?>
      <form method="POST" action="<?= url('jira-sync/entry/' . $sourceId . '/download-attachment') ?>" class="flex-shrink-0">
        <?= csrfField() ?>
        <input type="hidden" name="content_url" value="<?= e($a['contentUrl']) ?>">
        <input type="hidden" name="filename"    value="<?= e($a['filename']) ?>">
        <input type="hidden" name="mime_type"   value="<?= e($a['mimeType']) ?>">
        <button class="btn btn-outline-warning btn-sm py-0 px-2"><i class="bi bi-cloud-download me-1"></i>Download</button>
      </form>
      <?php elseif ($isNew): ?>
      <span class="badge bg-secondary flex-shrink-0">New in Jira</span>
      <?php else: ?>
      <span class="badge bg-success flex-shrink-0">Local ✓</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Dismiss — also a standalone form, not nested -->
<form method="POST" action="<?= e(str_replace('/accept', '/dismiss', $acceptUrl)) ?>"
      <?= $anyChange ? 'data-confirm="Mark as seen without importing changes?"' : '' ?>>
  <?= csrfField() ?>
  <button class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-eye-slash me-1"></i><?= ($anyChange || $newJiraAttachments) ? 'Dismiss without importing' : 'Mark as seen &amp; clear notification' ?>
  </button>
</form>

<?php endif; ?>
