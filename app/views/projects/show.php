<div class="d-flex align-items-start justify-content-between mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('projects') ?>"><i class="bi bi-arrow-left"></i></a>
    <span class="rounded-circle" style="width:20px;height:20px;background:<?= e($project['color']) ?>;display:inline-block"></span>
    <div>
      <h5 class="mb-0 fw-bold"><?= e($project['name']) ?></h5>
      <?php if ($project['project_number']): ?>
      <small class="text-muted"><?= e($project['project_number']) ?></small>
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('entries/create?project_id=' . $project['id']) ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Entry
    </a>
    <a href="<?= url('projects/' . $project['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-pencil"></i>
    </a>
  </div>
</div>

<?php if ($project['description']): ?>
<p class="text-muted mb-4"><?= e($project['description']) ?></p>
<?php endif; ?>

<!-- Milestones -->
<?php $miles = ['prototype_date' => 'Prototype', 'ep0_date' => 'EP0', 'ep1_date' => 'EP1', 'ep3_date' => 'EP3'];
$hasMiles = array_filter(array_map(fn($k) => $project[$k] ?? '', array_keys($miles))); ?>
<?php if ($hasMiles): ?>
<div class="card mb-4">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-4">
      <?php foreach ($miles as $field => $label): ?>
      <?php if ($project[$field]): ?>
      <div class="text-center">
        <div class="text-muted small"><?= $label ?></div>
        <div class="fw-semibold"><?= formatDate($project[$field]) ?></div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-4">
  <!-- Recent Entries -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Entries</span>
        <a href="<?= url('entries?project_id=' . $project['id']) ?>" class="btn btn-outline-secondary btn-sm">All</a>
      </div>
      <div class="card-body p-0">
        <?php if (!$entries): ?>
        <p class="text-muted text-center p-4 small">No entries for this project yet.</p>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($entries as $e): ?>
          <a href="<?= url('entries/' . $e['id']) ?>" class="list-group-item list-group-item-action bg-transparent border-secondary entry-row py-2 px-3">
            <div class="d-flex align-items-start gap-2">
              <span class="badge mt-1 flex-shrink-0" style="background:<?= e($e['type_color']) ?>"><?= e($e['type_name']) ?></span>
              <div>
                <div class="fw-semibold" style="font-size:.875rem"><?= e($e['title'] ?: substr($e['description'], 0, 60)) ?></div>
                <div class="text-muted small"><?= formatDate($e['entry_date']) ?></div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Test Plans -->
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Test Plans</span>
        <a href="<?= url('test-plans/create?project_id=' . $project['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">
          <i class="bi bi-plus"></i>
        </a>
      </div>
      <div class="card-body p-0">
        <?php if (!$testPlans): ?>
        <p class="text-muted text-center p-3 small">No test plans.</p>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($testPlans as $tp): ?>
          <a href="<?= url('test-plans/' . $tp['id']) ?>" class="list-group-item list-group-item-action bg-transparent border-secondary py-2 px-3">
            <div class="fw-semibold small"><?= e($tp['name']) ?></div>
            <div class="text-muted" style="font-size:.75rem"><?= $tp['item_count'] ?> items</div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
