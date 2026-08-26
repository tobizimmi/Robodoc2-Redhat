<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Live-Sync Settings</h5>
</div>

<div class="alert alert-info small" style="max-width:800px">
  <i class="bi bi-info-circle me-1"></i>
  Überträgt neu erstellte Einträge (inkl. Fotos/Anhänge) automatisch von diesem System an ein anderes
  RoboDoc2-System. Auf beiden Instanzen muss dasselbe Secret hinterlegt sein — es dient gleichzeitig
  als Sende- und als Empfangs-Passwort.
</div>

<form method="POST" action="<?= url('admin/live-sync') ?>">
  <?= csrfField() ?>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-arrow-left-right me-1"></i>Senden (dieses System → Ziel-System)</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Ziel-URL <span class="text-muted small">(leer lassen = kein Senden von diesem System aus)</span></label>
        <input type="url" name="live_sync_target_url" class="form-control" value="<?= e($s['live_sync_target_url'] ?? '') ?>" placeholder="https://<redhat-route>/api/sync/entry">
        <div class="form-text">Die Ingest-URL des Ziel-Systems (siehe unten "Empfangen" auf der anderen Instanz).</div>
      </div>
    </div>
  </div>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-inbox me-1"></i>Empfangen (von einem anderen System)</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Ingest-URL dieses Systems</label>
        <input type="text" class="form-control" value="<?= e($ingestUrl) ?>" readonly onclick="this.select()">
        <div class="form-text">Diese URL auf dem sendenden System als "Ziel-URL" eintragen.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">Erlaubter Quell-Host für Anhänge <span class="text-muted small">(leer = keine Prüfung, nicht empfohlen)</span></label>
        <input type="text" name="live_sync_source_host" class="form-control" value="<?= e($s['live_sync_source_host'] ?? '') ?>" placeholder="zimmimail.de">
        <div class="form-text">Beim Abholen von Fotos/Anhängen wird der Host der mitgeschickten Download-URL geprüft — nur dieser Host wird akzeptiert.</div>
      </div>
    </div>
  </div>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-key me-1"></i>Secret</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Live-Sync Secret</label>
        <input type="password" name="live_sync_secret" class="form-control" placeholder="<?= $hasSecret ? '•••••••• (unverändert lassen = nicht ändern)' : '' ?>">
        <div class="form-text">Muss auf beiden Systemen identisch sein. Leer lassen, um den gespeicherten Wert zu behalten.</div>
      </div>
    </div>
  </div>

  <div class="alert alert-warning small" style="max-width:800px">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Voraussetzung: Projekte und Eintragstypen müssen auf beiden Systemen gleich benannt sein — die
    Zuordnung erfolgt über den Namen. Unbekannte Projekte/Typen werden abgelehnt, nicht automatisch angelegt.
    Synchronisiert werden nur <strong>neu erstellte</strong> Einträge (kein Rückweg, keine nachträglichen Änderungen).
  </div>

  <div style="max-width:800px">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
  </div>
</form>
