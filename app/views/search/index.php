<div class="mb-4" style="max-width:600px">
  <form method="GET" class="d-flex gap-2">
    <input type="text" name="q" id="search-input" class="form-control" value="<?= e($q) ?>" placeholder="Suchbegriff…" autofocus>
    <button class="btn btn-primary">Searchn</button>
  </form>
</div>

<?php if ($q && strlen($q) < 2): ?>
<div class="alert alert-warning">Mindestens 2 Zeichen eingeben.</div>
<?php elseif ($q): ?>

<!-- Entries -->
<?php if (!empty($results['entries'])): ?>
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small">Entries (<?= count($results['entries']) ?>)</div>
  <div class="list-group list-group-flush">
    <?php foreach ($results['entries'] as $e): ?>
    <a href="<?= url('entries/' . $e['id']) ?>" class="list-group-item list-group-item-action bg-transparent border-secondary py-2 px-3">
      <div class="d-flex align-items-start gap-2">
        <span class="badge mt-1 flex-shrink-0" style="background:<?= e($e['type_color']) ?>"><?= e($e['type_name']) ?></span>
        <div>
          <div class="fw-semibold small"><?= e($e['title'] ?: substr($e['description'], 0, 80)) ?></div>
          <div class="text-muted small"><?= e($e['project_name']) ?> &middot; <?= formatDate($e['entry_date']) ?></div>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Projects -->
<?php if (!empty($results['projects'])): ?>
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small">Projekte</div>
  <div class="list-group list-group-flush">
    <?php foreach ($results['projects'] as $p): ?>
    <a href="<?= url('projects/' . $p['id']) ?>" class="list-group-item list-group-item-action bg-transparent border-secondary py-2 px-3">
      <span class="fw-semibold small"><?= e($p['name']) ?></span>
      <span class="badge bg-secondary ms-2"><?= e($p['status']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Inventory -->
<?php if (!empty($results['inventory'])): ?>
<div class="card mb-4">
  <div class="card-header border-secondary fw-semibold small">Inventar</div>
  <div class="list-group list-group-flush">
    <?php foreach ($results['inventory'] as $i): ?>
    <a href="<?= url('inventory/' . $i['id']) ?>" class="list-group-item list-group-item-action bg-transparent border-secondary py-2 px-3">
      <div class="fw-semibold small"><?= e($i['name']) ?></div>
      <?php if ($i['serial_number']): ?><div class="text-muted small"><?= e($i['serial_number']) ?></div><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (empty($results['entries']) && empty($results['projects']) && empty($results['inventory'])): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-search display-4 mb-3 d-block"></i>
  <p>Keine Results for «<?= e($q) ?>».</p>
</div>
<?php endif; ?>

<?php endif; ?>
