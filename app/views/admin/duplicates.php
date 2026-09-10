<?php $csrf = Auth::csrfToken(); ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0"><i class="bi bi-copy me-2 text-warning"></i>Duplikat-Erkennung</h5>
</div>

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
      <div class="text-muted small">Duplikat-Gruppen gefunden</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-secondary text-center py-3">
      <div style="font-size:2rem;font-weight:700"><?= $totalByTitle ?></div>
      <div class="text-muted small">Gleicher Titel</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-secondary text-center py-3">
      <div style="font-size:2rem;font-weight:700"><?= $totalByOrigin ?></div>
      <div class="text-muted small">Gleiche Sync-Origin</div>
    </div>
  </div>
</div>

<?php if ($totalGroups === 0): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Keine Duplikate gefunden!</div>
<?php else: ?>

<!-- Bulk delete buttons -->
<div class="d-flex gap-2 mb-4">
  <?php if ($totalByOrigin > 0): ?>
  <form method="POST" action="<?= url('admin/duplicates/delete-all') ?>"
        onsubmit="return confirm('Alle <?= $totalByOrigin ?> Sync-Duplikate löschen? Älteste werden behalten.')">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="mode" value="origin">
    <button class="btn btn-danger btn-sm">
      <i class="bi bi-trash me-1"></i>Alle Sync-Duplikate löschen (älteste behalten)
    </button>
  </form>
  <?php endif; ?>
  <?php if ($totalByTitle > 0): ?>
  <form method="POST" action="<?= url('admin/duplicates/delete-all') ?>"
        onsubmit="return confirm('Alle <?= $totalByTitle ?> Titel-Duplikate löschen? Älteste werden behalten.')">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="mode" value="title">
    <button class="btn btn-warning btn-sm">
      <i class="bi bi-trash me-1"></i>Alle Titel-Duplikate löschen (älteste behalten)
    </button>
  </form>
  <?php endif; ?>
</div>

<!-- Sync-Origin Duplikate -->
<?php if ($byOrigin): ?>
<div class="card border-warning mb-4">
  <div class="card-header border-warning fw-semibold">
    <i class="bi bi-arrow-repeat me-2 text-warning"></i>Sync-Duplikate (gleiche Live-Origin-ID)
  </div>
  <div class="card-body p-0">
    <?php foreach ($byOrigin as $group):
      $ids    = explode(',', $group['ids']);
      $titles = explode('||', $group['titles']);
      $dates  = explode('||', $group['dates']);
    ?>
    <div class="p-3 border-bottom border-secondary">
      <div class="text-muted small mb-2">
        Origin-ID: <strong><?= e($group['live_origin_id']) ?></strong> —
        <?= $group['cnt'] ?> Duplikate
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-dark mb-0">
          <thead><tr><th>ID</th><th>Titel</th><th>Erstellt</th><th>Aktion</th></tr></thead>
          <tbody>
            <?php foreach ($ids as $i => $entryId): ?>
            <tr class="<?= $i===0?'table-success':'' ?>">
              <td><a href="<?= url('entries/'.$entryId) ?>" target="_blank">#<?= e($entryId) ?></a></td>
              <td class="small"><?= e(substr($titles[$i]??'',0,60)) ?></td>
              <td class="small text-muted"><?= e(substr($dates[$i]??'',0,16)) ?></td>
              <td>
                <?php if ($i === 0): ?>
                  <span class="badge bg-success">Behalten</span>
                <?php else: ?>
                  <form method="POST" action="<?= url('admin/duplicates/delete') ?>"
                        onsubmit="return confirm('Eintrag #<?= e($entryId) ?> löschen?')" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= e($entryId) ?>">
                    <input type="hidden" name="keep_id" value="<?= e($ids[0]) ?>">
                    <button class="btn btn-danger btn-sm py-0">
                      <i class="bi bi-trash"></i> Löschen
                    </button>
                  </form>
                  <form method="POST" action="<?= url('admin/duplicates/delete') ?>"
                        onsubmit="return confirm('Eintrag #<?= e($ids[0]) ?> löschen und diesen behalten?')" class="d-inline ms-1">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= e($ids[0]) ?>">
                    <input type="hidden" name="keep_id" value="<?= e($entryId) ?>">
                    <button class="btn btn-outline-warning btn-sm py-0">
                      <i class="bi bi-star"></i> Diesen behalten
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

<!-- Titel Duplikate -->
<?php if ($byTitle): ?>
<div class="card border-secondary mb-4">
  <div class="card-header border-secondary fw-semibold">
    <i class="bi bi-fonts me-2"></i>Titel-Duplikate (gleicher Titel im selben Projekt)
  </div>
  <div class="card-body p-0">
    <?php foreach ($byTitle as $group):
      $ids     = explode(',', $group['ids']);
      $dates   = explode('||', $group['dates']);
      $authors = explode('||', $group['authors']);
    ?>
    <div class="p-3 border-bottom border-secondary">
      <div class="text-muted small mb-2">
        Projekt: <strong><?= e($group['project_name']) ?></strong> —
        Titel: <strong><?= e(substr($group['title'],0,80)) ?></strong> —
        <?= $group['cnt'] ?> Duplikate
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-dark mb-0">
          <thead><tr><th>ID</th><th>Erstellt</th><th>Von</th><th>Aktion</th></tr></thead>
          <tbody>
            <?php foreach ($ids as $i => $entryId): ?>
            <tr class="<?= $i===0?'table-success':'' ?>">
              <td><a href="<?= url('entries/'.$entryId) ?>" target="_blank">#<?= e($entryId) ?></a></td>
              <td class="small text-muted"><?= e(substr($dates[$i]??'',0,16)) ?></td>
              <td class="small"><?= e($authors[$i]??'') ?></td>
              <td>
                <?php if ($i === 0): ?>
                  <span class="badge bg-success">Behalten</span>
                <?php else: ?>
                  <form method="POST" action="<?= url('admin/duplicates/delete') ?>"
                        onsubmit="return confirm('Eintrag #<?= e($entryId) ?> löschen?')" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= e($entryId) ?>">
                    <input type="hidden" name="keep_id" value="<?= e($ids[0]) ?>">
                    <button class="btn btn-danger btn-sm py-0">
                      <i class="bi bi-trash"></i> Löschen
                    </button>
                  </form>
                  <form method="POST" action="<?= url('admin/duplicates/delete') ?>"
                        onsubmit="return confirm('Eintrag #<?= e($ids[0]) ?> löschen und diesen behalten?')" class="d-inline ms-1">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= e($ids[0]) ?>">
                    <input type="hidden" name="keep_id" value="<?= e($entryId) ?>">
                    <button class="btn btn-outline-warning btn-sm py-0">
                      <i class="bi bi-star"></i> Diesen behalten
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
