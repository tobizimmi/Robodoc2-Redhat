<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Update Assistant</h5>
</div>

<div class="card mb-4" style="max-width:680px">
  <div class="card-header border-secondary fw-semibold small">
    <i class="bi bi-database-gear me-2 text-primary"></i>Schema Updates
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">
      Runs all pending database schema changes (new columns, new tables) required by the current
      version of RoboDoc. Safe to run multiple times — all statements use
      <code>IF NOT EXISTS</code> and will skip anything already applied. No data is deleted.
    </p>
    <form method="POST" action="<?= url('migrate') ?>">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="run">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-play-circle me-2"></i>Run Schema Updates
      </button>
    </form>
  </div>
</div>

<?php if ($result !== null): ?>
<div class="card" style="max-width:680px">
  <div class="card-header border-secondary fw-semibold small">Results</div>
  <div class="card-body p-0">
    <table class="table table-dark table-sm mb-0">
      <thead>
        <tr>
          <th class="ps-3">Statement</th>
          <th style="width:80px" class="text-center">Status</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result as $r): ?>
        <tr>
          <td class="ps-3 small font-monospace text-muted"><?= e($r['label']) ?></td>
          <td class="text-center">
            <?php if ($r['ok']): ?>
            <i class="bi bi-check-circle-fill text-success"></i>
            <?php else: ?>
            <i class="bi bi-x-circle-fill text-danger"></i>
            <?php endif; ?>
          </td>
          <td class="small <?= $r['ok'] ? 'text-muted' : 'text-danger' ?>">
            <?= $r['ok'] ? 'Applied / already exists' : e($r['error'] ?? '') ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer border-secondary small text-muted">
    <?= count(array_filter($result, fn($r) => $r['ok'])) ?> applied &mdash;
    <?= count(array_filter($result, fn($r) => !$r['ok'])) ?> errors
  </div>
</div>
<?php endif; ?>
