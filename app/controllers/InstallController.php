<?php
declare(strict_types=1);

class InstallController {
    public static function index(): void {
        // Block access unconditionally if already installed — no ?force bypass allowed
        if (file_exists(__DIR__ . '/../../config.local.php')) {
            redirect('/login');
        }

        $error   = null;
        $success = false;
        $mode    = $_POST['mode'] ?? 'fresh'; // 'fresh' or 'restore'

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF check for install form
            $token = $_POST['_csrf'] ?? '';
            if (!$token || $token !== ($_SESSION['install_csrf'] ?? '')) {
                $error = 'Invalid request token. Please reload and try again.';
                goto renderForm;
            }
            $dbHost = trim($_POST['db_host'] ?? 'localhost');
            $dbPort = trim($_POST['db_port'] ?? '3306');
            $dbName = trim($_POST['db_name'] ?? '');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';

            // Test DB connection
            try {
                $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (\Exception $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
                goto renderForm;
            }

            if ($mode === 'restore') {
                // ── RESTORE FROM BACKUP ────────────────────────────────
                if (empty($_FILES['backup_zip']['tmp_name']) || $_FILES['backup_zip']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'No backup file uploaded or upload error (check upload_max_filesize in PHP config).';
                    goto renderForm;
                }
                if (!class_exists('ZipArchive')) {
                    $error = 'ZipArchive PHP extension is not available on this server.';
                    goto renderForm;
                }

                $zip = new \ZipArchive();
                if ($zip->open($_FILES['backup_zip']['tmp_name']) !== true) {
                    $error = 'Cannot open backup ZIP file. Is it a valid RoboDoc backup?';
                    goto renderForm;
                }

                // Must contain database.sql
                $dbSql = $zip->getFromName('database.sql');
                if ($dbSql === false) {
                    $zip->close();
                    $error = 'Invalid backup: database.sql not found inside the ZIP.';
                    goto renderForm;
                }

                // Import database
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                $stmts = self::splitSql($dbSql);
                $importErrors = 0;
                foreach ($stmts as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '' || str_starts_with(ltrim($stmt), '--')) continue;
                    try { $pdo->exec($stmt); } catch (\Throwable) { $importErrors++; }
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

                // Restore uploads/ directory
                $uploadDir = rtrim(UPLOAD_DIR, '/');
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                $restored = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    // Accept both 'uploads/...' and 'uploads\...'
                    $name = str_replace('\\', '/', $name);
                    if (!str_starts_with($name, 'uploads/')) continue;
                    $rel = substr($name, strlen('uploads/'));
                    if ($rel === '' || str_ends_with($rel, '/')) continue; // skip dir entries
                    $dest = $uploadDir . '/' . $rel;
                    $destDir = dirname($dest);
                    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) continue;
                    $src = $zip->getStream($name);
                    if (!$src) continue;
                    $fh = @fopen($dest, 'wb');
                    if ($fh) { stream_copy_to_stream($src, $fh); fclose($fh); $restored++; }
                    fclose($src);
                }
                $zip->close();

                // Write config
                $appSecret = bin2hex(random_bytes(32));
                $config = "<?php\n"
                    . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                    . "define('DB_PORT', " . var_export($dbPort, true) . ");\n"
                    . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                    . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                    . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                    . "define('APP_SECRET', " . var_export($appSecret, true) . ");\n";
                file_put_contents(__DIR__ . '/../../config.local.php', $config);

                $success = 'restore';

            } else {
                // ── FRESH INSTALL ──────────────────────────────────────
                $name  = trim($_POST['admin_name']  ?? '');
                $email = trim($_POST['admin_email'] ?? '');
                $pw    = $_POST['admin_password']   ?? '';

                // Run schema
                $schema = file_get_contents(__DIR__ . '/../../install/schema.sql');
                foreach (array_filter(explode(';', $schema)) as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt) { try { $pdo->exec($stmt); } catch (\Throwable) {} }
                }

                // Create admin user
                $existing = $pdo->prepare('SELECT id FROM users WHERE email=?');
                $existing->execute([$email]);
                if (!$existing->fetch()) {
                    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
                    $stmt->execute([$name, $email, password_hash($pw, PASSWORD_BCRYPT), 'admin']);
                }

                // Write config
                $secret = bin2hex(random_bytes(32));
                $config = "<?php\n"
                    . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                    . "define('DB_PORT', " . var_export($dbPort, true) . ");\n"
                    . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                    . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                    . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                    . "define('APP_SECRET', " . var_export($secret, true) . ");\n";
                file_put_contents(__DIR__ . '/../../config.local.php', $config);

                $success = 'fresh';
            }
        }

        renderForm:
        $uploadLimit = ini_get('upload_max_filesize');
        $postLimit   = ini_get('post_max_size');
        ?><!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title>RoboDoc 2 – Installation</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center min-vh-100 py-4">
<div style="max-width:540px;width:100%" class="p-3">
  <div class="text-center mb-4">
    <i class="bi bi-robot text-primary" style="font-size:3rem"></i>
    <h2 class="mt-2 mb-0">RoboDoc 2.0</h2>
    <div class="text-muted small">Installation Assistant</div>
  </div>

  <?php if ($success === 'fresh'): ?>
  <div class="alert alert-success">
    <h5><i class="bi bi-check-circle-fill me-2"></i>Installation successful!</h5>
    <p class="mb-0">Database set up and admin account created.</p>
    <a href="<?= BASE_URL ?>/login" class="btn btn-success mt-3 w-100">Go to Login</a>
  </div>

  <?php elseif ($success === 'restore'): ?>
  <div class="alert alert-success">
    <h5><i class="bi bi-check-circle-fill me-2"></i>Restore successful!</h5>
    <p class="mb-0">Database and uploads have been restored from your backup.</p>
    <p class="small mt-2 mb-0 text-success-emphasis">Log in with the credentials from your backup.</p>
    <a href="<?= BASE_URL ?>/login" class="btn btn-success mt-3 w-100">Go to Login</a>
  </div>

  <?php else: ?>
  <?php if ($error): ?>
  <div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['install_csrf'] = $_SESSION['install_csrf'] ?? bin2hex(random_bytes(16));
  ?>

  <!-- Mode tabs -->
  <div class="mb-3">
    <div class="btn-group w-100" role="group">
      <button type="button" class="btn btn-outline-primary mode-btn <?= $mode === 'fresh' ? 'active' : '' ?>"
              onclick="setMode('fresh')">
        <i class="bi bi-plus-circle me-1"></i>New Installation
      </button>
      <button type="button" class="btn btn-outline-success mode-btn <?= $mode === 'restore' ? 'active' : '' ?>"
              onclick="setMode('restore')">
        <i class="bi bi-archive me-1"></i>Restore from Backup
      </button>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-4">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['install_csrf']) ?>">
        <input type="hidden" name="mode" id="modeInput" value="<?= htmlspecialchars($mode) ?>">

        <!-- DB Credentials (shared) -->
        <h6 class="fw-semibold mb-3"><i class="bi bi-database me-1 text-info"></i>Database Connection</h6>
        <div class="row g-2 mb-4">
          <div class="col-8">
            <label class="form-label small">Host</label>
            <input type="text" name="db_host" class="form-control form-control-sm" value="localhost" required>
          </div>
          <div class="col-4">
            <label class="form-label small">Port</label>
            <input type="text" name="db_port" class="form-control form-control-sm" value="3306" required>
          </div>
          <div class="col-12">
            <label class="form-label small">Database Name</label>
            <input type="text" name="db_name" class="form-control form-control-sm" value="robodoc2" required>
          </div>
          <div class="col-6">
            <label class="form-label small">Username</label>
            <input type="text" name="db_user" class="form-control form-control-sm" required>
          </div>
          <div class="col-6">
            <label class="form-label small">Password</label>
            <input type="password" name="db_pass" class="form-control form-control-sm">
          </div>
        </div>

        <!-- FRESH INSTALL: Admin account -->
        <div id="freshSection" <?= $mode !== 'fresh' ? 'style="display:none"' : '' ?>>
          <h6 class="fw-semibold mb-3"><i class="bi bi-person-badge me-1 text-primary"></i>Admin Account</h6>
          <div class="mb-2">
            <label class="form-label small">Name</label>
            <input type="text" name="admin_name" class="form-control form-control-sm"
                   <?= $mode === 'fresh' ? 'required' : '' ?> id="adminName">
          </div>
          <div class="mb-2">
            <label class="form-label small">E-Mail</label>
            <input type="email" name="admin_email" class="form-control form-control-sm"
                   <?= $mode === 'fresh' ? 'required' : '' ?> id="adminEmail">
          </div>
          <div class="mb-4">
            <label class="form-label small">Password</label>
            <input type="password" name="admin_password" class="form-control form-control-sm"
                   <?= $mode === 'fresh' ? 'required' : '' ?> id="adminPassword" minlength="8">
          </div>
        </div>

        <!-- RESTORE: Backup file upload -->
        <div id="restoreSection" <?= $mode !== 'restore' ? 'style="display:none"' : '' ?>>
          <h6 class="fw-semibold mb-3"><i class="bi bi-file-zip me-1 text-success"></i>Backup File</h6>
          <div class="mb-3">
            <label class="form-label small">Upload Backup ZIP <span class="text-danger">*</span></label>
            <input type="file" name="backup_zip" class="form-control form-control-sm"
                   accept=".zip" id="backupZip">
            <div class="form-text">
              Select a <code>robodoc_backup_*.zip</code> file.
              Server upload limit: <strong><?= htmlspecialchars($uploadLimit) ?></strong> / <strong><?= htmlspecialchars($postLimit) ?></strong>
              — if your backup is larger, increase <code>upload_max_filesize</code> and <code>post_max_size</code> in <code>php.ini</code>.
            </div>
          </div>
          <div class="alert alert-info py-2 small mb-4">
            <i class="bi bi-info-circle me-1"></i>
            The database and all uploaded files will be restored from the backup.
            Log in afterwards with your backed-up credentials — no new admin account is needed.
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
          <i class="bi bi-check-lg me-1"></i>
          <span id="submitLabel"><?= $mode === 'fresh' ? 'Install' : 'Restore' ?></span>
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function setMode(m) {
  document.getElementById('modeInput').value = m;
  document.getElementById('freshSection').style.display   = m === 'fresh'   ? '' : 'none';
  document.getElementById('restoreSection').style.display = m === 'restore' ? '' : 'none';
  document.getElementById('submitLabel').textContent = m === 'fresh' ? 'Install' : 'Restore';
  // Toggle required on admin fields
  ['adminName','adminEmail','adminPassword'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.required = (m === 'fresh');
  });
  const bz = document.getElementById('backupZip');
  if (bz) bz.required = (m === 'restore');
  document.querySelectorAll('.mode-btn').forEach(btn => {
    btn.classList.toggle('active', btn.textContent.includes(m === 'fresh' ? 'New' : 'Restore'));
  });
}
// Init required state
setMode(document.getElementById('modeInput')?.value || 'fresh');
</script>
</body></html>
<?php exit;
    }

    // Split SQL dump into individual statements, respecting quoted strings
    private static function splitSql(string $sql): array
    {
        $stmts   = [];
        $current = '';
        $len     = strlen($sql);
        $i       = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            // Skip line comments
            if ($ch === '-' && isset($sql[$i+1]) && $sql[$i+1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }
            // Skip block comments
            if ($ch === '/' && isset($sql[$i+1]) && $sql[$i+1] === '*') {
                $i += 2;
                while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i+1] === '/')) $i++;
                $i += 2;
                continue;
            }
            // Quoted string — pass through verbatim
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $q = $ch;
                $current .= $ch;
                $i++;
                while ($i < $len) {
                    $c2 = $sql[$i];
                    $current .= $c2;
                    if ($c2 === '\\') { $i++; if ($i < $len) { $current .= $sql[$i]; } }
                    elseif ($c2 === $q) break;
                    $i++;
                }
                $i++;
                continue;
            }
            // Statement delimiter
            if ($ch === ';') {
                $s = trim($current);
                if ($s !== '') $stmts[] = $s;
                $current = '';
                $i++;
                continue;
            }
            $current .= $ch;
            $i++;
        }
        $s = trim($current);
        if ($s !== '') $stmts[] = $s;
        return $stmts;
    }
}
