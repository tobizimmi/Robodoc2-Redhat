<?php
declare(strict_types=1);
/**
 * RoboDoc Upload Import — imports the old uploads/ folder file by file via the browser.
 * Admin only. Delete this file after the migration!
 */
require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    session_write_close();
    $out = static function (int $code, array $data): never {
        http_response_code($code);
        echo json_encode($data);
        exit;
    };

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $out(419, ['error' => 'CSRF token mismatch – Seite neu laden']);
    }

    // Relative path inside uploads/, e.g. "145/abc.jpg"
    $rel = str_replace('\\', '/', (string)($_POST['path'] ?? ''));
    $rel = ltrim($rel, '/');
    if ($rel === '' || str_contains($rel, "\0") || !preg_match('#^[A-Za-z0-9._\- /]+$#', $rel)) {
        $out(400, ['error' => 'Ungültiger Pfad']);
    }
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') $out(400, ['error' => 'Ungültiger Pfad']);
    }
    if (preg_match('/\.(php\d?|phtml|phar|htaccess|user\.ini)$/i', $rel) || basename($rel) === '.user.ini') {
        $out(400, ['error' => 'Dateityp nicht erlaubt']);
    }

    $base = rtrim(UPLOAD_DIR, '/');
    $dest = $base . '/' . $rel;
    $size = (int)($_POST['size'] ?? -1);

    // Resume support: skip files that already exist with identical size
    if (($_POST['check'] ?? '') === '1') {
        $out(200, ['exists' => is_file($dest) && filesize($dest) === $size]);
    }

    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $out(400, ['error' => 'PHP upload error ' . ($f['error'] ?? 'none')]);
    }
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        $out(500, ['error' => 'Ordner konnte nicht angelegt werden: ' . $dir]);
    }
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        $out(500, ['error' => 'Speichern fehlgeschlagen: ' . $dest]);
    }
    $out(200, ['ok' => true, 'size' => filesize($dest)]);
}

$csrf = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>RoboDoc Upload-Import</title>
<style>
body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;padding:2rem;max-width:900px;margin:auto}
.card{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1.25rem;margin-bottom:1rem}
button{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:.5rem 1rem;cursor:pointer}
button:disabled{opacity:.5;cursor:default}
progress{width:100%;height:1.25rem}
#log{background:#0f172a;font:12px monospace;height:300px;overflow:auto;padding:.5rem;border-radius:6px;white-space:pre-wrap}
.err{color:#f87171}.warn{color:#fbbf24}
</style>
</head>
<body>
<h2>RoboDoc Upload-Import</h2>
<p class="warn">Diese Seite nach der Migration löschen! Ziel: <code><?= e(rtrim(UPLOAD_DIR, '/')) ?></code></p>
<div class="card">
  <p>Wähle den lokalen Ordner <b>uploads</b> aus (den Ordner selbst, nicht seinen Inhalt).
     Vorhandene Dateien mit gleicher Größe werden übersprungen – du kannst also jederzeit neu starten.</p>
  <input type="file" id="dir" webkitdirectory directory multiple>
  <button id="start" disabled>Import starten</button>
</div>
<div class="card">
  <div id="stat">Noch keine Dateien gewählt.</div>
  <progress id="bar" value="0" max="1"></progress>
  <div id="log"></div>
</div>
<script>
const CSRF = <?= json_encode($csrf) ?>;
const input = document.getElementById('dir'), btn = document.getElementById('start');
const bar = document.getElementById('bar'), stat = document.getElementById('stat'), logEl = document.getElementById('log');
let files = [];
const log = (msg, cls) => { const d = document.createElement('div'); if (cls) d.className = cls; d.textContent = msg; logEl.appendChild(d); logEl.scrollTop = logEl.scrollHeight; };
const relPath = f => f.webkitRelativePath.split('/').slice(1).join('/'); // strip top folder "uploads/"

input.addEventListener('change', () => {
  files = [...input.files].filter(f => relPath(f) && !f.name.startsWith('.'));
  const mb = files.reduce((s, f) => s + f.size, 0) / 1048576;
  stat.textContent = `${files.length} Dateien, ${mb.toFixed(0)} MB gewählt.`;
  btn.disabled = !files.length;
});

async function post(fd) {
  const r = await fetch(location.pathname, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF }, credentials: 'same-origin', redirect: 'manual' });
  if (r.type === 'opaqueredirect' || r.status === 0) throw new Error('Session abgelaufen – neu einloggen und Seite neu laden');
  let data; try { data = await r.json(); } catch { throw new Error('HTTP ' + r.status); }
  if (!r.ok) throw new Error(data.error || ('HTTP ' + r.status));
  return data;
}

async function one(f) {
  const path = relPath(f);
  const c = new FormData(); c.append('check', '1'); c.append('path', path); c.append('size', f.size);
  if ((await post(c)).exists) return 'skip';
  const fd = new FormData(); fd.append('path', path); fd.append('size', f.size); fd.append('file', f);
  for (let attempt = 1; ; attempt++) {
    try { await post(fd); return 'ok'; }
    catch (e) { if (attempt >= 3 || /Session/.test(e.message)) throw e; await new Promise(r => setTimeout(r, 2000)); }
  }
}

btn.addEventListener('click', async () => {
  btn.disabled = input.disabled = true;
  let ok = 0, skip = 0, err = 0, done = 0, idx = 0, stop = false;
  bar.max = files.length; bar.value = 0;
  const worker = async () => {
    while (!stop && idx < files.length) {
      const f = files[idx++];
      try { const r = await one(f); r === 'skip' ? skip++ : ok++; if (r === 'ok') log('OK    ' + relPath(f)); }
      catch (e) { err++; log('FEHLER ' + relPath(f) + ': ' + e.message, 'err'); if (/Session/.test(e.message)) stop = true; }
      bar.value = ++done;
      stat.textContent = `${done}/${files.length} – hochgeladen: ${ok}, übersprungen: ${skip}, Fehler: ${err}`;
    }
  };
  await Promise.all([worker(), worker(), worker()]);
  log(`Fertig. Hochgeladen: ${ok}, übersprungen: ${skip}, Fehler: ${err}`, err ? 'err' : '');
  btn.disabled = input.disabled = false;
});
</script>
</body>
</html>
