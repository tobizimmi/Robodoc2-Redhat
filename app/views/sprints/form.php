<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('sprints') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0"><?= $sprint ? 'Edit Sprint' : 'New Sprint' ?></h5>
</div>

<div class="card" style="max-width:640px">
  <div class="card-body p-4">
    <form method="POST">
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label fw-semibold">Sprint Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required
               value="<?= e($sprint['name'] ?? '') ?>" placeholder="e.g. Sprint 2026-W22">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Sprint Goal</label>
        <textarea name="goal" class="form-control" rows="2" placeholder="What do we want to achieve this sprint?"><?= e($sprint['goal'] ?? '') ?></textarea>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Start Date</label>
          <input type="date" name="start_date" class="form-control" value="<?= e($sprint['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">End Date</label>
          <input type="date" name="end_date" class="form-control" value="<?= e($sprint['end_date'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold">Team Velocity / Capacity <span class="text-muted fw-normal">(story points)</span></label>
        <input type="number" name="velocity_points" class="form-control" min="0" max="999"
               value="<?= e($sprint['velocity_points'] ?? '') ?>" placeholder="e.g. 40">
        <div class="form-text">Optional. Used to track how many points the team can handle this sprint.</div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $sprint ? 'Save Changes' : 'Create Sprint' ?></button>
        <a href="<?= url($sprint ? 'sprints/'.$sprint['id'] : 'sprints') ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
