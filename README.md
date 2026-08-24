# RoboDoc2 — OpenShift Container Setup

Container- und OpenShift-Setup für RoboDoc2 (Quelle: [robodoc2](https://github.com/tobizimmi/robodoc2)),
für den Betrieb auf Azure Red Hat OpenShift (ARO).

Für die komplette Schritt-für-Schritt-Anleitung (Build, Deployment,
Datenmigration von der bestehenden Live-Instanz) siehe **[DEPLOYMENT.md](DEPLOYMENT.md)**.

## Struktur

- `Dockerfile` — PHP 8.3 + Apache + ffmpeg, für OpenShifts "arbitrary UID"
  Sicherheitsmodell vorbereitet (läuft ohne root, Port 8080 statt 80).
- `app/`, `public/` — RoboDoc2-Anwendungscode (Snapshot aus dem Haupt-Repo).
- `openshift/` — Kubernetes/OpenShift-Manifeste (Build, Secrets-Vorlage,
  optionale MySQL-Instanz, persistenter Speicher, App-Deployment, Route,
  CronJob für die periodischen Sync-Jobs).

## Code-Updates aus dem Haupt-Repo übernehmen

Dieses Repo enthält einen Snapshot von `app/` und `public/` aus
[robodoc2](https://github.com/tobizimmi/robodoc2). Um auf einen neueren Stand
zu aktualisieren, `app/` und `public/` aus dem Haupt-Repo hierher kopieren
(unter Auslassung von `uploads/` und `config.local.php`), committen, pushen —
`oc start-build robodoc2 --follow` baut dann das aktualisierte Image.
