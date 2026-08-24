<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" value="<?= e($data['name'] ?? '') ?>" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Project</label>
    <select name="project_id" class="form-select">
      <option value="">No Project</option>
      <?php foreach ($projects as $p): ?>
      <option value="<?= $p['id'] ?>" <?= ($data['project_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Serial Number</label>
    <input type="text" name="serial_number" class="form-control" value="<?= e($data['serial_number'] ?? '') ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">Firmware</label>
    <input type="text" name="firmware_version" class="form-control" value="<?= e($data['firmware_version'] ?? '') ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      <?php foreach (['available'=>'Available','in_use'=>'In Use','maintenance'=>'Maintenance','retired'=>'Retired'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= ($data['status'] ?? 'available') === $v ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label">Location</label>
    <input type="text" name="location" class="form-control" value="<?= e($data['location'] ?? '') ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Purchase Date</label>
    <input type="date" name="purchased_at" class="form-control" value="<?= e($data['purchased_at'] ?? '') ?>">
  </div>
  <div class="col-12">
    <label class="form-label">Comment</label>
    <textarea name="comment" class="form-control" rows="3"><?= e($data['comment'] ?? '') ?></textarea>
  </div>
</div>
