<?php $csrf = Auth::csrfToken(); ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0"><i class="bi bi-copy me-2 text-warning"></i>Duplicate Detection</h5>
</div>

<!-- Debug Info -->
<?php if (!empty($debug)): ?>
<div class="alert alert-info small py-2 mb-3">
  <strong>Debug:</strong>
  Total entries: <strong><?= $debug['total_entries'] ?></strong> |
  With sync origin: <strong><?= $debug['with_origin'] ?></strong> |
  Origin duplicate groups: <strong><?= $debug['by_origin_groups'] ?></strong> |
  Title duplicate groups: <strong><?= $debug['by_title_groups'] ?></strong> |
  Title (global): <strong><?= $debug['by_title_global'] ?></strong>
</div>
<?php endif; ?>
<!-- Stats -->
<?php
$totalByTitle  = count($byTitle);
$totalByOrigin = count($byOrigin);
$totalGroups   = $totalByTitle + $totalByOrigin;
?>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-secondary text-center py-3">
      <div style="font-size:2rem;font-weight:700;color:<?= $totalGroups>0?'#f59e0b':'#10b981' ?>"><?= $totalGroups ?></div>
      <div class="text-muted small">Duplicate groups found</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-secondary text-center py-3">
      <div style="font-size:2rem;font-weight:700"><?= $totalByTitle ?></div>
      <div class="text-muted small">Same Title</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-secondary text-center py-3">
      <div style="font-size:2rem;font-weight:700"><?= $totalByOrigin ?></div>
      <div class="text-muted small">Same Sync Origin</div>
    </div>
  </div>
</div>

<?php if ($totalGroups === 0): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>No duplicates found!</div>
<?php else: ?>

<!-- Bulk delete buttons -->
<div class="d-flex gap-2 mb-4">
    <?php if (!empty($byTitleGlobal)): ?>
  <form method='POST' action='<?= url("admin/duplicates/delete-all") ?>'
        onsubmit="return confirm('Delete all global title duplicates?')">
    <input type='hidden' name='_csrf' value='<?= $csrf ?>'>
    <input type='hidden' name='mode' value='title_global'>
    <button class='btn btn-danger btn-sm'>
      <i class='bi bi-trash me-1'></i>Delete all global title duplicates
    </button>
  </form>
  <?php endif; ?>
  <?php if ($totalByOrigin > 0): ?>
  <form method="POST" action="<?= url('admin/duplicates/delete-all') ?>"
        onsubmit="return confirm('Delete all <?= $totalByOrigin ?> sync duplicates? The oldest will be kept.')">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="mode" value="origin">
    <button class="btn btn-danger btn-sm">
      <i class="bi bi-trash me-1"></i>Delete all sync duplicates (keep oldest)
    </button>
  </form>
  <?php endif; ?>
  <?php if ($totalByTitle > 0): ?>
  <form method="POST" action="<?= url('admin/duplicates/delete-all') ?>"
        onsubmit="return confirm('Delete all <?= $totalByTitle ?> title duplicates? The oldest will be kept.')">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="mode" value="title">
    <button class="btn btn-warning btn-sm">
      <i class="bi bi-trash me-1"></i>Delete all title duplicates (keep oldest)
    </button>
  </form>
  <?php endif; ?>
</div>

<!-- Sync Origin Duplicates -->
<?php if ($byOrigin): ?>
<div class="card border-warning mb-4">
  <div class="card-header border-warning fw-semibold">
    <i class="bi bi-arrow-repeat me-2 text-warning"></i>Sync Duplicates (same Live-Origin ID)
  </div>
  <div class="card-body p-0">
    <?php foreach ($byOrigin as $group):
      $ids    = explode(',', $group['ids']);
      $titles = explode('||', $group['titles']);
      $dates  = explode('||', $group['dates']);
    ?>
    <div class="p-3 border-bottom border-secondary">
      <div class="text-muted small mb-2">
        Origin ID: <strong><?= e($group['live_origin_id']) ?></strong> —
        <?= $group['cnt'] ?> duplicates
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-dark mb-0">
          <thead><tr><th>ID</th><th>Title</th><th>Created</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($ids as $i => $entryId): ?>
            <tr class="<?= $i===0?'table-success':'' ?>">
              <td><a href="<?= url('entries/'.$entryId) ?>" target="_blank">#<?= e($entryId) ?></a></td>
              <td class="small"><?= e(substr($titles[$i]??'',0,60)) ?></td>
              <td class="small text-muted"><?= e(substr($dates[$i]??'',0,16)) ?></td>
              <td>
                <?php if ($i === 0): ?>
                  <span class="badge bg-success">Keep</span>
                <?php else: ?>
                  <form method="POST" action="<?= url('admin/duplicates/delete') ?>"
                        onsubmit="return confirm('Delete entry #<?= e($entryId) ?>?')" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= e($entryId) ?>">
                    <input type="hidden" name="keep_id" value="<?= e($ids[0]) ?>">
                    <button class="btn btn-danger btn-sm py-0">
                      <i class="bi bi-trash"></i> Delete
                    </button>
                  </form>
                  <form method="POST" action="<?= url('admin/duplicates/delete') ?>"
                        onsubmit="return confirm('Delete entry #<?= e($ids[0]) ?> and keep this one instead?')" class="d-inline ms-1">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= e($ids[0]) ?>">
                    <input type="hidden" name="keep_id" value="<?= e($entryId) ?>">
                    <button class="btn btn-outline-warning btn-sm py-0">
                      <i class="bi bi-star"></i> Keep this one
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Title Duplicates -->
<?php if ($byTitle): ?>
<div class="card border-secondary mb-4">
  <div class="card-header border-secondary fw-semibold">
    <i class="bi bi-fonts me-2"></i>Title Duplicates (same title in the same project)
  </div>
  <div class="card-body p-0">
    <?php foreach ($byTitle as $group):
      $ids     = explode(',', $group['ids']);
      $dates   = explode('||', $group['dates']);
      $authors = explode('||', $group['authors']);
    ?>
    <div class="p-3 border-bottom border-secondary">
      <div class="text-muted small mb-2">
        Project: <strong><?= e($group['project_name']) ?></strong> —
        Title: <strong><?= e(substr($group['title'],0,80)) ?></strong> —
        <?= $group['cnt'] ?> duplicates
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-dark mb-0">
          <thead><tr><th>ID</th><th>Created</th><th>By</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($ids as $i => $entryId): ?>
            <tr class="<?= $i===0?'table-success':'' ?>">
              <td><a href="<?= url('entries/'.$entryId) ?>" target="_blank">#<?= e($entryId) ?></a></td>
              <td class="small text-muted"><?= e(substr($dates[$i]??'',0,16)) ?></td>
              <td class="small"><?= e($authors[$i]??'') ?></td>
              <td>
                <?php if ($i === 0): ?>
                  <span class="badge bg-success">Keep</span>
                <?php else: ?>
                  <form method="POST" action="<?= url('admin/duplicates/delete') ?>"
                        onsubmit="return confirm('Delete entry #<?= e($entryId) ?>?')" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= e($entryId) ?>">
                    <input type="hidden" name="keep_id" value="<?= e($ids[0]) ?>">
                    <button class="btn btn-danger btn-sm py-0">
                      <i class="bi bi-trash"></i> Delete
                    </button>
                  </form>
                  <form method="POST" action="<?= url('admin/duplicates/delete') ?>"
                        onsubmit="return confirm('Delete entry #<?= e($ids[0]) ?> and keep this one instead?')" class="d-inline ms-1">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= e($ids[0]) ?>">
                    <input type="hidden" name="keep_id" value="<?= e($entryId) ?>">
                    <button class="btn btn-outline-warning btn-sm py-0">
                      <i class="bi bi-star"></i> Keep this one
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
