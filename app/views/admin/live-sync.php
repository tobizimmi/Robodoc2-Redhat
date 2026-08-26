<?php $enabled = ($s['live_sync_enabled'] ?? '0') === '1'; ?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Live-Sync Settings</h5>
  <span class="badge <?= $enabled ? 'bg-success' : 'bg-secondary' ?> ms-2"><?= $enabled ? 'Aktiv' : 'Inaktiv' ?></span>
</div>

<div class="card mb-4" style="max-width:800px">
  <div class="card-body p-3 d-flex align-items-center justify-content-between">
    <div>
      <div class="fw-semibold small">Live-Sync <?= $enabled ? 'ist aktiv' : 'ist ausgeschaltet' ?></div>
      <div class="text-muted small">Ein Klick schaltet Senden, Empfangen und Abholen sofort komplett ab — unabhängig von den übrigen Einstellungen.</div>
    </div>
    <form method="POST" action="<?= url('admin/live-sync/toggle') ?>" class="ms-3">
      <?= csrfField() ?>
      <button type="submit" class="btn <?= $enabled ? 'btn-outline-danger' : 'btn-success' ?> btn-sm">
        <i class="bi <?= $enabled ? 'bi-stop-circle' : 'bi-play-circle' ?> me-1"></i><?= $enabled ? 'Deaktivieren' : 'Aktivieren' ?>
      </button>
    </form>
  </div>
</div>

<div class="alert alert-info small" style="max-width:800px">
  <i class="bi bi-info-circle me-1"></i>
  Überträgt neu erstellte Einträge (inkl. Fotos/Anhänge) automatisch von diesem System an ein anderes
  RoboDoc2-System. Auf beiden Instanzen muss dasselbe Secret hinterlegt sein.
  <strong>Zwei Übertragungswege</strong> — je nachdem, wer wen erreichen kann:
  <ul class="mb-0 mt-1">
    <li><strong>Senden (Push):</strong> dieses System schickt aktiv an ein erreichbares Ziel-System.</li>
    <li><strong>Abholen (Pull):</strong> falls das Ziel-System (z.B. eine OpenShift-Route) von außen nicht
      erreichbar ist, holt es sich die Daten stattdessen selbst per Cron beim Quell-System ab — genau wie
      beim Zentao-Relay reicht "der eine erreicht den anderen" in einer Richtung.</li>
  </ul>
</div>

<form method="POST" action="<?= url('admin/live-sync') ?>">
  <?= csrfField() ?>
  <input type="hidden" name="live_sync_enabled" value="<?= $enabled ? '1' : '0' ?>">

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-cloud-download me-1"></i>Abholen (dieses System holt sich Daten beim Quell-System ab)</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Quell-System URL <span class="text-muted small">(leer lassen = kein Abholen)</span></label>
        <input type="url" name="live_sync_pull_source_url" class="form-control" value="<?= e($s['live_sync_pull_source_url'] ?? '') ?>" placeholder="https://zimmimail.de">
        <div class="form-text">Die Basis-URL des Systems, bei dem abgeholt werden soll (ohne Pfad). Muss dort unter "Empfangen" mit demselben Secret erreichbar sein. Aktiviere zusätzlich den Cron-Job "Live-Sync Retry" unter Admin → Cron Jobs, damit regelmäßig abgeholt wird.</div>
      </div>
    </div>
  </div>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-cloud-upload me-1"></i>Senden (dieses System schickt aktiv an ein Ziel-System)</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Ziel-URL <span class="text-muted small">(leer lassen = kein Senden von diesem System aus)</span></label>
        <input type="url" name="live_sync_target_url" class="form-control" value="<?= e($s['live_sync_target_url'] ?? '') ?>" placeholder="https://<ziel-system>/api/sync/entry">
        <div class="form-text">Nur nutzen, wenn das Ziel-System von hier aus direkt erreichbar ist. Falls nicht (z.B. OpenShift-Route nicht von außen erreichbar), stattdessen "Abholen" auf dem Ziel-System konfigurieren und dieses Feld leer lassen.</div>
      </div>
    </div>
  </div>

  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-inbox me-1"></i>Empfangen (Endpunkte, die dieses System für andere Systeme bereitstellt)</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Ingest-URL dieses Systems <span class="text-muted small">(für "Senden" auf der anderen Instanz)</span></label>
        <input type="text" class="form-control" value="<?= e($ingestUrl) ?>" readonly onclick="this.select()">
      </div>
      <div class="mb-3">
        <label class="form-label">Erlaubter Quell-Host für Anhänge <span class="text-muted small">(leer = keine Prüfung, nicht empfohlen)</span></label>
        <input type="text" name="live_sync_source_host" class="form-control" value="<?= e($s['live_sync_source_host'] ?? '') ?>" placeholder="zimmimail.de">
        <div class="form-text">Beim Abholen/Empfangen von Fotos/Anhängen wird der Host der mitgeschickten Download-URL geprüft — nur dieser Host wird akzeptiert (Schutz gegen Missbrauch mit einem geleakten Secret).</div>
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
    Zum Schutz vor Missbrauch/DoS sind alle Endpunkte pro Absender-IP begrenzt (max. 10 falsche Secret-Versuche
    bzw. 300 Anfragen pro 10/1 Minuten) und jeder Abhol-/Sende-Durchlauf ist auf 50 Einträge gedeckelt.
  </div>

  <div style="max-width:800px">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
  </div>
</form>
