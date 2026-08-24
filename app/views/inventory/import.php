<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('inventory') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">CSV Import — Inventory</h5>
</div>

<div class="row g-4" style="max-width:800px">
  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small"><i class="bi bi-upload me-1"></i>Datei hochladen</div>
      <div class="card-body p-4">
        <form method="POST" action="<?= url('inventory/import') ?>" enctype="multipart/form-data">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Projekt (für alle importierten Geräte)</label>
            <select name="project_id" class="form-select" style="max-width:300px">
              <option value="">— Unassigned —</option>
              <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>" <?= (isset($_GET['project_id']) && $_GET['project_id'] == $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label">CSV Datei</label>
            <input type="file" name="csv" class="form-control" accept=".csv,.txt" required style="max-width:400px">
            <div class="form-text">Trennzeichen: Komma oder Semikolon. UTF-8 Encoding.</div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Import starten</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header border-secondary fw-semibold small"><i class="bi bi-info-circle me-1"></i>CSV Format</div>
      <div class="card-body p-4">
        <p class="text-muted small mb-3">Die erste Zeile muss die Spaltennamen enthalten. Folgende Spalten werden unterstützt:</p>
        <table class="table table-sm small">
          <thead class="table-dark">
            <tr><th>Spalte</th><th>Pflicht</th><th>Mögliche Werte</th></tr>
          </thead>
          <tbody>
            <tr><td class="fw-semibold font-monospace">name</td><td><span class="badge bg-danger">Ja</span></td><td>Gerätename</td></tr>
            <tr><td class="fw-semibold font-monospace">serial_number</td><td class="text-muted">—</td><td>Seriennummer</td></tr>
            <tr><td class="fw-semibold font-monospace">firmware_version</td><td class="text-muted">—</td><td>Firmware-Version</td></tr>
            <tr><td class="fw-semibold font-monospace">location</td><td class="text-muted">—</td><td>Lagerort</td></tr>
            <tr><td class="fw-semibold font-monospace">comment</td><td class="text-muted">—</td><td>Anmerkung</td></tr>
            <tr><td class="fw-semibold font-monospace">status</td><td class="text-muted">—</td><td><code>available</code>, <code>in_use</code>, <code>maintenance</code>, <code>retired</code></td></tr>
            <tr><td class="fw-semibold font-monospace">purchased_at</td><td class="text-muted">—</td><td>Datum (YYYY-MM-DD)</td></tr>
          </tbody>
        </table>
        <p class="text-muted small mb-2 mt-3">Beispiel (Komma):</p>
        <pre class="bg-dark rounded p-3 small text-light mb-0">name,serial_number,firmware_version,status
Automower 450X,SN-001,3.4.2,available
Automower 310,SN-002,2.1.0,in_use</pre>
      </div>
    </div>
  </div>
</div>
