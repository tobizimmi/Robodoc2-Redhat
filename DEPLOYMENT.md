# RoboDoc2 auf Azure Red Hat OpenShift (ARO) — Schritt-für-Schritt

Dieses Repo enthält alles, um RoboDoc2 als Container auf OpenShift zu bauen und
zu betreiben, plus die Migration der bestehenden Live-Datenbank und Anhänge.

```
Dockerfile                       Container-Image (Apache + PHP 8.3 + ffmpeg)
openshift/
  01-imagestream-buildconfig.yaml  Baut das Image aus diesem Git-Repo (im Cluster)
  02-secret-app.yaml                Vorlage für DB-Zugangsdaten + App-Keys
  03-mysql-optional.yaml            Optional: MySQL im Cluster (statt Azure-DB)
  04-uploads-pvc.yaml                Persistenter Speicher für Anhänge/Fotos
  05-deployment-service-route.yaml   Die eigentliche App (Web)
  06-cronjob.yaml                    Ersetzt den `* * * * *` Cron-Job vom alten Server
```

## 0. Voraussetzungen

- Zugriff auf einen ARO-Cluster + die `oc` CLI installiert und eingeloggt
  (`oc login --server=https://api.<cluster>.<region>.aroapp.io:6443 ...` —
  die genaue Login-URL/Token bekommst du im OpenShift-Webconsole unter
  deinem Account-Menü → "Copy Login Command").
- Dieses Repository liegt auf GitHub unter
  `https://github.com/tobizimmi/Robodoc2-Redhat` (siehe Abschnitt
  ["Repo selbst auf GitHub bekommen"](#repo-selbst-auf-github-bekommen) unten,
  falls das noch nicht passiert ist).
- SSH-Zugriff auf den bisherigen Live-Server (für die Datenmigration).

## 1. Projekt (Namespace) anlegen

```bash
oc new-project robodoc2 --display-name="RoboDoc2"
```

Alle folgenden Befehle gehen davon aus, dass du in diesem Projekt arbeitest
(`oc project robodoc2`, falls du zwischendurch wechselst).

## 2. Image im Cluster bauen

Statt lokal zu bauen und in eine externe Registry (z.B. Azure Container
Registry) zu pushen, lässt du OpenShift das Image direkt aus diesem Git-Repo
bauen und in der eingebauten Cluster-Registry speichern — kein zusätzliches
Azure-Setup nötig.

```bash
oc apply -f openshift/01-imagestream-buildconfig.yaml
oc start-build robodoc2 --follow
```

`--follow` zeigt den Build-Log live an. Am Ende solltest du `Push successful`
sehen. Das Image liegt danach unter
`image-registry.openshift-image-registry.svc:5000/robodoc2/robodoc2:latest`.

> Falls der Build fehlschlägt: `oc logs -f bc/robodoc2` zeigt den letzten Build
> erneut an. Häufigste Ursache wäre ein Netzwerk-/Proxy-Problem beim
> `apt-get install` im Dockerfile — der Build-Pod braucht ausgehenden
> Internetzugang.

## 3. Datenbank bereitstellen

Zwei Optionen — wähle eine:

### Option A: MySQL im Cluster (schnellster Start)

```bash
# DB_USER/DB_PASS/MYSQL_ROOT_PASSWORD müssen dafür schon im Secret stehen,
# siehe Schritt 4 zuerst.
oc apply -f openshift/03-mysql-optional.yaml
oc rollout status deploy/robodoc2-mysql
```

`DB_HOST` im Secret (Schritt 4) muss dann auf `robodoc2-mysql` gesetzt werden
(= der Service-Name).

### Option B: Azure Database for MySQL Flexible Server (empfohlen für Produktion)

```bash
az mysql flexible-server create \
  --resource-group <deine-resource-group> \
  --name robodoc2-mysql \
  --location <region, z.B. germanywestcentral> \
  --admin-user robodoc2admin \
  --admin-password "<starkes-passwort>" \
  --sku-name Standard_B2s \
  --tier Burstable \
  --storage-size 32 \
  --version 8.0.21 \
  --public-access 0.0.0.0-255.255.255.255   # später auf ARO-VNet einschränken

az mysql flexible-server db create \
  --resource-group <deine-resource-group> \
  --server-name robodoc2-mysql \
  --database-name robodoc2
```

`--public-access 0.0.0.0-255.255.255.255` ist nur ein Startpunkt, damit du von
überall testen kannst — schränke die Firewall-Regel danach unbedingt auf den
Adressbereich deines ARO-VNets ein (`az network vnet show` für die Adressen,
dann `az mysql flexible-server firewall-rule create`). `DB_HOST` im Secret
(Schritt 4) ist dann der volle Servername, z.B.
`robodoc2-mysql.mysql.database.azure.com`.

## 4. Secrets anlegen

**Nicht** die YAML-Datei mit echten Werten ausfüllen und committen — leg das
Secret imperativ an, damit nichts im Git-Verlauf landet:

```bash
oc create secret generic robodoc2-db \
  --from-literal=DB_HOST="robodoc2-mysql" \
  --from-literal=DB_PORT="3306" \
  --from-literal=DB_NAME="robodoc2" \
  --from-literal=DB_USER="robodoc2" \
  --from-literal=DB_PASS="<starkes-passwort>" \
  --from-literal=MYSQL_ROOT_PASSWORD="<anderes-starkes-passwort>"

oc create secret generic robodoc2-app \
  --from-literal=APP_KEY="$(openssl rand -hex 32)" \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)"
```

(`openshift/02-secret-app.yaml` bleibt als Referenz/Vorlage im Repo, wird aber
nicht direkt angewendet.)

## 5. Speicher, App und Cron ausrollen

Trag zuerst in `openshift/05-deployment-service-route.yaml` und
`openshift/06-cronjob.yaml` bei `image:` deinen echten Projektnamen statt
`YOUR-PROJECT` ein (Zeile mit `image-registry.openshift-image-registry.svc:5000/...`)
— oder ersetze es inline beim Anwenden:

```bash
oc apply -f openshift/04-uploads-pvc.yaml

sed "s/YOUR-PROJECT/robodoc2/" openshift/05-deployment-service-route.yaml | oc apply -f -
sed "s/YOUR-PROJECT/robodoc2/" openshift/06-cronjob.yaml | oc apply -f -

oc rollout status deploy/robodoc2
```

Route-URL herausfinden:

```bash
oc get route robodoc2 -o jsonpath='{.spec.host}'
```

Ruf diese URL im Browser auf — du solltest die RoboDoc2-Login-Seite sehen
(die App legt ihr Datenbankschema beim ersten Request automatisch an, siehe
`app/bootstrap.php` → `runMigrations()`). Ohne importierte Daten (nächster
Schritt) ist die Datenbank aber leer — es gibt noch keinen Benutzer zum
Einloggen.

## 6. Daten von Live migrieren

### 6.1 Backup auf dem alten Server erstellen

Auf `zimmimail.de` (oder wo auch immer die Live-Instanz läuft):

```bash
mysqldump --single-transaction --routines --triggers \
  -u robodoc2 -p robodoc2 | gzip > database.sql.gz

tar -czf uploads.tar.gz -C /var/www/vhosts/zimmimail.de/httpdocs/RoboDoc uploads
```

(Das entspricht genau dem, was `deploy.sh`/`backup_offsite.sh` im
robodoc2-Repo bereits automatisiert erzeugen — falls dort schon ein aktuelles
Backup in `~/robodoc_backups/<timestamp>/` liegt, kannst du dessen
`database.sql.gz` und `uploads.tar.gz` direkt verwenden.)

### 6.2 Beide Dateien zu dir lokal holen

```bash
scp root@zimmimail.de:~/database.sql.gz .
scp root@zimmimail.de:~/uploads.tar.gz .
```

### 6.3 Datenbank importieren

**Bei Option A (MySQL im Cluster):**

```bash
oc port-forward svc/robodoc2-mysql 3306:3306 &
gunzip -c database.sql.gz | mysql -h 127.0.0.1 -P 3306 -u robodoc2 -p robodoc2
kill %1   # port-forward wieder beenden
```

**Bei Option B (Azure Database for MySQL):**

```bash
gunzip -c database.sql.gz | mysql -h robodoc2-mysql.mysql.database.azure.com \
  -u robodoc2admin -p --ssl-mode=REQUIRED robodoc2
```

(Falls dein Client die Verbindung von außen ablehnt: entweder die
Firewall-Regel aus Schritt 3B kurzzeitig weiter öffnen, oder den Import statt
von deinem Rechner aus dem laufenden App-Pod heraus starten — `oc rsh` in den
Pod und die Datei vorher mit `oc cp` dorthin kopieren, analog zu 6.4.)

### 6.4 Uploads (Anhänge/Fotos) importieren

Der App-Pod hat die PVC bereits unter `/var/www/html/uploads` gemountet:

```bash
POD=$(oc get pod -l app=robodoc2 -o jsonpath='{.items[0].metadata.name}')
oc cp uploads.tar.gz "$POD":/tmp/uploads.tar.gz
oc rsh "$POD" tar -xzf /tmp/uploads.tar.gz -C /var/www/html
oc rsh "$POD" rm /tmp/uploads.tar.gz
```

Das Tar-Archiv enthält bereits einen `uploads/`-Ordner als oberste Ebene
(genau wie es `backup_offsite.sh` erzeugt) — daher wird es nach
`/var/www/html` entpackt, nicht nach `/var/www/html/uploads`.

### 6.5 Prüfen

- Route-URL neu laden, mit einem bestehenden Live-Account einloggen.
- Einen Eintrag mit Anhang öffnen und prüfen, ob das Bild/die Datei angezeigt
  wird.
- Unter Admin-Bereich prüfen, ob Projekte/Einstellungen wie erwartet
  vorhanden sind.

## 7. Danach

- **Cutover:** Solange du testest, zeig niemandem die neue URL als "die"
  RoboDoc-Instanz — erst wenn 6.5 sauber durchgetestet ist, DNS/Links
  umstellen.
- **Wiederholte Migration:** Schritt 6 ist beliebig oft wiederholbar (z.B.
  read-only Testphase mit aktuellen Daten kurz vor dem eigentlichen Cutover
  erneut ausführen) — der Import überschreibt einfach die Tabellen erneut.
- **Backups auf OpenShift:** `oc create cronjob` mit demselben
  `mysqldump`/`tar`-Ansatz wie oben, das Ergebnis z.B. in einen Azure Blob
  Storage hochladen — aktuell noch nicht Teil dieses Repos.
- **Mehrere Replicas:** `openshift/04-uploads-pvc.yaml` nutzt `ReadWriteOnce`
  — das reicht für 1 Web-Replica. Für mehr Replicas eine
  ReadWriteMany-fähige StorageClass verwenden (z.B. Azure Files statt Azure
  Disk), sonst können Web-Pod und CronJob-Pod auf verschiedene Nodes
  geplant werden und die PVC nicht gleichzeitig mounten.
- **Sessions:** Läuft aktuell mit PHP-Datei-Sessions im Container
  (funktioniert bei 1 Replica problemlos). Bei >1 Replica bräuchte es
  DB-basierte Sessions oder Sticky Sessions auf der Route.

## Repo selbst auf GitHub bekommen

Falls `https://github.com/tobizimmi/Robodoc2-Redhat` noch nicht existiert:

1. Auf github.com ein neues, leeres Repository `Robodoc2-Redhat` unter dem
   Account `tobizimmi` anlegen (ohne README/'.gitignore' — leer).
2. Lokal in diesem Ordner:
   ```bash
   git init
   git add -A
   git commit -m "Initial container setup for OpenShift"
   git branch -M main
   git remote add origin https://github.com/tobizimmi/Robodoc2-Redhat.git
   git push -u origin main
   ```
   (Beim Push nach einem GitHub-Login/Personal-Access-Token gefragt, falls
   noch nicht hinterlegt.)
