<?php $hasError = !empty($state['error']); ?>

<!-- Header -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= e($backUrl) ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 flex-grow-1"><?= e($title) ?></h5>
  <?php if (!$hasError): ?>
  <a href="<?= e($state['bug_url'] ?? '#') ?>" target="_blank" class="btn btn-outline-info btn-sm">
    <i class="bi bi-box-arrow-up-right me-1"></i>Bug #<?= e($state['id'] ?? '') ?>
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
      <div class="col-md-5">
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-cloud-upload text-warning fs-5 flex-shrink-0 mt-1"></i>
          <div>
            <div class="fw-semibold small">Push Entry → Zentao</div>
            <div class="text-muted small">Overwrite the Zentao bug with your current local entry (uses admin templates).</div>
            <form method="POST" action="<?= url('zentao-sync/entry/' . $sourceId . '/push') ?>" class="mt-2">
              <?= csrfField() ?>
              <button class="btn btn-warning btn-sm"><i class="bi bi-cloud-upload me-1"></i>Push to Zentao</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-2 text-center d-none d-md-block text-muted" style="font-size:1.5rem">⇄</div>
      <div class="col-md-5">
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-cloud-download text-info fs-5 flex-shrink-0 mt-1"></i>
          <div>
            <div class="fw-semibold small">Import Zentao → Entry</div>
            <div class="text-muted small">
              <?php if ($anyChange): ?>
              Changes detected in Zentao. Select what to import, then click <strong>Accept &amp; Import</strong>.
              <?php else: ?>
              <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No tracked changes detected.</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Accept form -->
<form method="POST" action="<?= e($acceptUrl) ?>">
  <?= csrfField() ?>

  <!-- Field diff -->
  <?php if ($fieldDiff): ?>
  <div class="card mb-4">
    <div class="card-header border-secondary fw-semibold small d-flex align-items-center gap-2">
      <i class="bi bi-table me-1"></i>Fields
      <span class="badge <?= $anyChange ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1">
        <?= $anyChange ? 'Changes detected' : 'No changes' ?>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0 small align-middle">
        <thead class="table-dark">
          <tr>
            <th class="ps-3" style="width:20%">Field</th>
            <th style="width:35%">Local (current)</th>
            <th style="width:35%">Zentao</th>
            <th class="text-center" style="width:10%">Import?</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fieldDiff as $f): ?>
          <?php $statusOptions = $f['key'] === 'status' ? ($f['options'] ?? []) : []; ?>
          <tr class="<?= $f['changed'] ? 'table-warning' : '' ?>">
            <td class="ps-3 fw-semibold text-nowrap"><?= e($f['label']) ?></td>
            <td class="<?= $f['changed'] ? '' : 'text-muted' ?>"><?= e($f['local'] ?: '—') ?></td>
            <td class="<?= $f['changed'] ? 'fw-semibold' : 'text-muted' ?>"><?= e($f['zentao'] ?: '—') ?></td>
            <td class="text-center">
              <?php if ($f['changed']): ?>
              <input type="checkbox" class="form-check-input" name="accept_<?= e($f['key']) ?>" value="1" checked>
              <?php if (count($statusOptions) > 1): ?>
              <select name="accepted_status" class="form-select form-select-sm mt-1" style="font-size:.75rem;min-width:150px">
                <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= e($opt) ?>"><?= e(entryStatuses()[$opt] ?? $opt) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text mt-0" style="font-size:.65rem">Mehrere Stati für diesen Zentao-Status konfiguriert — Auswahl treffen.</div>
              <?php endif; ?>
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
      <i class="bi bi-file-text me-1"></i>Description / Steps
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
          <pre class="mb-0 small" style="white-space:pre-wrap;font-family:inherit;max-height:300px;overflow-y:auto"><?= e(strip_tags($descLocal) ?: '(empty)') ?></pre>
        </div>
        <div class="col-md-6 p-3 <?= $descChanged ? 'bg-warning bg-opacity-10' : '' ?>">
          <div class="text-muted small fw-semibold mb-2">Zentao</div>
          <pre class="mb-0 small" style="white-space:pre-wrap;font-family:inherit;max-height:300px;overflow-y:auto"><?= e($descZentao ?: '(empty)') ?></pre>
        </div>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
    <button type="submit" class="btn btn-info">
      <i class="bi bi-cloud-download me-1"></i>Accept &amp; Import from Zentao
    </button>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>

<?php $actions = $state['actions'] ?? []; $existingIds = $existingActionIds ?? []; ?>
<?php if ($actions): ?>
<?php $newActions = array_values(array_filter($actions, fn($a) => !in_array($a['id'], $existingIds, true))); ?>
<div class="card mb-4">
  <div class="card-header border-secondary d-flex align-items-center gap-2 py-2"
       style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#zentaoActionsRevPanel">
    <i class="bi bi-clock-history text-info"></i>
    <span class="fw-semibold small">Zentao History &amp; Comments</span>
    <span class="badge bg-secondary ms-1"><?= count($actions) ?> total</span>
    <?php if ($newActions): ?>
    <span class="badge bg-warning text-dark"><?= count($newActions) ?> new</span>
    <?php endif; ?>
    <i class="bi bi-chevron-down ms-auto small text-muted"></i>
  </div>
  <div class="collapse show" id="zentaoActionsRevPanel">
    <div class="list-group list-group-flush">
      <?php foreach ($actions as $a):
        $isNew = !in_array($a['id'], $existingIds, true);
      ?>
      <div class="list-group-item bg-transparent py-2 <?= $isNew ? 'border-start border-warning border-3' : '' ?>">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-person-circle text-muted small"></i>
          <span class="fw-semibold small"><?= e($a['actor'] ?? '') ?></span>
          <span class="text-muted small"><?= e(substr($a['date'] ?? '', 0, 16)) ?></span>
          <?php if ($a['action'] ?? ''): ?>
          <span class="badge bg-secondary small"><?= e($a['action']) ?></span>
          <?php endif; ?>
          <?php if ($isNew): ?><span class="badge bg-warning text-dark ms-1">New</span><?php endif; ?>
        </div>
        <?php if ($a['comment'] ?? ''): ?>
        <pre class="mb-0 small ms-4" style="white-space:pre-wrap;font-family:inherit"><?= e($a['comment']) ?></pre>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Dismiss -->
<form method="POST" action="<?= url('zentao-sync/entry/' . $sourceId . '/dismiss') ?>"
      class="mt-3" <?= $anyChange ? 'data-confirm="Mark as seen without importing?"' : '' ?>>
  <?= csrfField() ?>
  <button class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-eye-slash me-1"></i><?= $anyChange ? 'Dismiss without importing' : 'Mark as seen &amp; clear notification' ?>
  </button>
</form>

<?php endif; ?>
