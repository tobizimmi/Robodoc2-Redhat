<div class="mb-4 d-flex align-items-center gap-2">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('requirements') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">New Requirement</h5>
</div>
<div class="card" style="max-width:600px"><div class="card-body p-4">
  <form method="POST" action="<?= url('requirements/create') ?>">
    <?= csrfField() ?>
    <div class="mb-3"><label class="form-label">Projekt</label>
      <select name="project_id" class="form-select">
        <option value="">Kein Projekt</option>
        <?php foreach ($projects as $p): ?>
        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="4"></textarea></div>
    <div class="row g-3 mb-3">
      <div class="col-md-6"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['planning'=>'Planung','approved'=>'Genehmigt','in_progress'=>'In Arbeit','completed'=>'Abgeschlossen','cancelled'=>'Abgebrochen'] as $v=>$l): ?>
          <option value="<?= $v ?>"><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label">Priority</label>
        <select name="priority" class="form-select">
          <?php foreach (['low'=>'Niedrig','medium'=>'Mittel','high'=>'Hoch','critical'=>'Kritisch'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $v==='medium'?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">Create</button>
      <a href="<?= url('requirements') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div></div>
