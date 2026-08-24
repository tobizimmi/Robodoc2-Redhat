<div class="row g-3">
  <div class="col-md-8">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Basic Info</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= e($data['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Location Description</label>
          <textarea name="location_description" class="form-control" rows="3"><?= e($data['location_description'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Surface Types</label>
          <input type="text" name="surface_types" class="form-control" value="<?= e($data['surface_types'] ?? '') ?>"
                 placeholder="e.g. grass, gravel, mulch, slopes">
        </div>
        <div class="mb-3">
          <label class="form-label">Obstacles / Features</label>
          <textarea name="obstacles" class="form-control" rows="2" placeholder="Trees, flower beds, garden furniture, narrow passages…"><?= e($data['obstacles'] ?? '') ?></textarea>
        </div>
        <div class="mb-0">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?= e($data['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Photos</div>
      <div class="card-body">
        <?php if (!empty($photos)): ?>
        <div class="row g-2 mb-3">
          <?php foreach ($photos as $p): ?>
          <div class="col-4">
            <div class="position-relative">
              <img src="<?= url('attachments/' . $p['id'] . '/thumb') ?>" class="img-fluid rounded" style="height:100px;width:100%;object-fit:cover" alt="">
              <?php if (isset($area)): ?>
              <form method="POST" action="<?= url('test-areas/' . $area['id'] . '/photos/' . $p['id'] . '/delete') ?>" class="position-absolute top-0 end-0 m-1">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger btn-sm py-0 px-1" style="font-size:.7rem" onclick="return confirm('Remove?')"><i class="bi bi-x"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <label class="form-label small">Add Photos</label>
        <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
        <div class="form-text">Upload photos showing the test area, terrain, boundary markers etc.</div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Terrain & Dimensions</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label small">Max Slope (%)</label>
          <input type="number" name="slope_max_percent" class="form-control form-control-sm" step="0.1" min="0" max="100"
                 value="<?= e($data['slope_max_percent'] ?? '') ?>" placeholder="e.g. 35">
        </div>
        <div class="mb-3">
          <label class="form-label small">Area (m²)</label>
          <input type="number" name="area_sqm" class="form-control form-control-sm" step="1" min="0"
                 value="<?= e($data['area_sqm'] ?? '') ?>" placeholder="e.g. 500">
        </div>
        <div class="mb-3">
          <label class="form-label small">Boundary Type</label>
          <select name="boundary_type" class="form-select form-select-sm">
            <option value="">—</option>
            <?php foreach (['Wire', 'Virtual', 'None', 'Fence', 'Mixed'] as $bt): ?>
            <option <?= ($data['boundary_type'] ?? '') === $bt ? 'selected' : '' ?>><?= $bt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-0">
          <label class="form-label small">Boundary Length (m)</label>
          <input type="number" name="boundary_length_m" class="form-control form-control-sm" step="0.1" min="0"
                 value="<?= e($data['boundary_length_m'] ?? '') ?>" placeholder="e.g. 120">
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">GPS (optional)</div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label small">Latitude</label>
          <input type="number" name="gps_lat" class="form-control form-control-sm" step="0.00000001"
                 value="<?= e($data['gps_lat'] ?? '') ?>" placeholder="48.137154">
        </div>
        <div class="mb-2">
          <label class="form-label small">Longitude</label>
          <input type="number" name="gps_lon" class="form-control form-control-sm" step="0.00000001"
                 value="<?= e($data['gps_lon'] ?? '') ?>" placeholder="11.576124">
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm w-100"
                onclick="captureGPS('gps_lat','gps_lon',this)">
          <i class="bi bi-geo-alt me-1"></i>Use Current Location
        </button>
      </div>
    </div>

    <div class="d-grid gap-2">
      <button type="submit" class="btn btn-primary">Save Test Area</button>
      <a href="<?= url('test-areas') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </div>
</div>
