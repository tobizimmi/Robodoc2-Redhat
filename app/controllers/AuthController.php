<?php
declare(strict_types=1);

class AuthController
{
    public static function login(): void
    {
        if (Auth::check()) redirect('/dashboard');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $ip       = self::clientIp();

            // ── IP ban check ──────────────────────────────────────────────
            self::checkIpBan($ip);

            // ── Brute-force check ─────────────────────────────────────────
            self::checkBruteForce($email, $ip);

            $user = Database::fetchOne(
                'SELECT id, name, email, password_hash, role, status, totp_enabled, totp_secret
                 FROM users WHERE email = ?',
                [$email]
            );

            if ($user && password_verify($password, $user['password_hash'])) {
                $status = $user['status'] ?? 'active';
                if ($status === 'pending') {
                    flash('error', 'Your account is pending approval by an administrator.');
                    redirect('/login');
                }
                if ($status === 'disabled') {
                    flash('error', 'Your account has been disabled. Please contact an administrator.');
                    redirect('/login');
                }
                self::clearAttempts($email, $ip);

                // ── 2FA check ─────────────────────────────────────────────
                if ($user['totp_enabled']) {
                    // Store pending user in session, redirect to 2FA page
                    session_regenerate_id(true);
                    $_SESSION['2fa_pending_user_id'] = $user['id'];
                    $_SESSION['2fa_pending_time']    = time();
                    Audit::log('login_2fa_required', 'user', (int)$user['id'], $email);
                    redirect('/login/2fa');
                }

                // ── Successful login ──────────────────────────────────────
                self::completeLogin($user, $ip);
            } else {
                self::recordFailedAttempt($email, $ip);
                Audit::log('login_failed', 'user', 0, $email . ' from ' . $ip);
                // Add timing delay to prevent timing attacks
                usleep(random_int(100000, 300000));
                flash('error', 'E-Mail oder Passwort falsch.');
                redirect('/login');
            }
        }

        View::render('auth/login', ['title' => 'Anmelden'], 'auth');
    }

    /** 2FA verification step */
    public static function login2fa(): void
    {
        if (Auth::check()) redirect('/dashboard');
        if (empty($_SESSION['2fa_pending_user_id'])) redirect('/login');
        // Expire pending session after 5 minutes
        if (time() - ($_SESSION['2fa_pending_time'] ?? 0) > 300) {
            unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_time']);
            flash('error', 'Session expired. Please log in again.');
            redirect('/login');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
            $ip   = self::clientIp();

            $user = Database::fetchOne(
                'SELECT id, name, email, password_hash, role, status, totp_secret, totp_enabled
                 FROM users WHERE id=?',
                [$_SESSION['2fa_pending_user_id']]
            );
            if (!$user) redirect('/login');

            // Check backup code
            $usedBackup = false;
            if (strlen($code) === 8) {
                $backups = Database::fetchAll(
                    'SELECT id, code_hash FROM totp_backup_codes WHERE user_id=? AND used_at IS NULL',
                    [$user['id']]
                );
                foreach ($backups as $bk) {
                    if (hash_equals($bk['code_hash'], hash('sha256', $code))) {
                        Database::execute('UPDATE totp_backup_codes SET used_at=NOW() WHERE id=?', [$bk['id']]);
                        $usedBackup = true;
                        break;
                    }
                }
            }

            if ($usedBackup || Totp::verify($user['totp_secret'], $code)) {
                unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_time']);
                if ($usedBackup) {
                    flash('warning', 'Backup code used. Please generate new backup codes in your profile.');
                }
                self::completeLogin($user, $ip);
            } else {
                self::recordFailedAttempt($user['email'], $ip);
                Audit::log('login_2fa_failed', 'user', (int)$user['id'], $user['email']);
                flash('error', 'Invalid authenticator code. Please try again.');
                redirect('/login/2fa');
            }
        }

        View::render('auth/login_2fa', ['title' => '2-Factor Authentication'], 'auth');
    }

    /** Enable 2FA — show QR code */
    public static function setup2fa(): void
    {
        Auth::require();
        $user = Database::fetchOne('SELECT * FROM users WHERE id=?', [Auth::id()]);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $code   = preg_replace('/\D/', '', $_POST['code'] ?? '');
            $secret = $_POST['secret'] ?? '';
            if (Totp::verify($secret, $code)) {
                // Generate 10 backup codes
                $backupCodes = [];
                try {
                    Database::execute('DELETE FROM totp_backup_codes WHERE user_id=?', [Auth::id()]);
                    for ($i = 0; $i < 10; $i++) {
                        $raw  = strtoupper(bin2hex(random_bytes(4)));
                        $backupCodes[] = $raw;
                        Database::execute(
                            'INSERT INTO totp_backup_codes (user_id, code_hash) VALUES (?,?)',
                            [Auth::id(), hash('sha256', $raw)]
                        );
                    }
                } catch (Throwable $e) {
                    // Table may not exist yet — 2FA still works without backup codes
                    $backupCodes = [];
                }
                try {
                    Database::execute(
                        'UPDATE users SET totp_secret=?, totp_enabled=1, totp_verified_at=NOW() WHERE id=?',
                        [$secret, Auth::id()]
                    );
                } catch (Throwable $e) {
                    flash('error', '2FA DB error: ' . $e->getMessage());
                    redirect('/profile/2fa/setup');
                }
                Audit::log('2fa_enabled', 'user', Auth::id());
                flash('success', '2-Factor Authentication enabled! Save your backup codes now.');
                $_SESSION['backup_codes'] = $backupCodes;
                redirect('/profile/2fa/backup-codes');
            } else {
                flash('error', 'Invalid code. Please try again.');
                redirect('/profile/2fa/setup');
            }
        }
        $secret = Totp::generateSecret();
        $uri    = Totp::getUri($secret, $user['email']);
        View::render('auth/setup_2fa', [
            'title'   => 'Enable 2FA',
            'secret'  => $secret,
            'qr_url'  => Totp::getQrUrl($uri),
            'uri'     => $uri,
        ]);
    }

    /** Serve QR code image (proxy to avoid CSP issues) */
    public static function qrCode(): void
    {
        Auth::require();
        $uri = $_GET['uri'] ?? '';
        if (!$uri || !str_starts_with($uri, 'otpauth://')) {
            http_response_code(400); exit;
        }
        $url = 'https://chart.googleapis.com/chart?cht=qr&chs=220x220&chld=M|2&chl=' . rawurlencode($uri);
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $png = @file_get_contents($url, false, $ctx);
        if ($png) {
            header('Content-Type: image/png');
            header('Cache-Control: private, max-age=300');
            echo $png;
        } else {
            http_response_code(503);
        }
        exit;
    }

    /** Show backup codes after enabling 2FA */
    public static function backupCodes(): void
    {
        Auth::require();
        $codes = $_SESSION['backup_codes'] ?? [];
        unset($_SESSION['backup_codes']);
        View::render('auth/backup_codes', ['title' => 'Backup Codes', 'codes' => $codes]);
    }

    /** Disable 2FA */
    public static function disable2fa(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        Database::execute('UPDATE users SET totp_enabled=0, totp_secret=NULL WHERE id=?', [Auth::id()]);
        Database::execute('DELETE FROM totp_backup_codes WHERE user_id=?', [Auth::id()]);
        Audit::log('2fa_disabled', 'user', Auth::id());
        flash('success', '2-Factor Authentication has been disabled.');
        redirect('/profile');
    }

    public static function register(): void
    {
        if (Auth::check()) redirect('/dashboard');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $name     = trim($_POST['name']     ?? '');
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password']      ?? '';
            $confirm  = $_POST['confirm']       ?? '';
            if (!$name || !$email || !$password) { flash('error', 'All fields are required.'); redirect('/register'); }
            // Strong password policy
            $pwErr = self::validatePassword($password);
            if ($pwErr) { flash('error', $pwErr); redirect('/register'); }
            if ($password !== $confirm) { flash('error', 'Passwords do not match.'); redirect('/register'); }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error', 'Invalid email address.'); redirect('/register'); }
            if (Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email])) {
                flash('error', 'An account with this email already exists.'); redirect('/register');
            }
            $newId = Database::insert(
                'INSERT INTO users (name, email, password_hash, role, status, password_changed_at) VALUES (?,?,?,?,?,NOW())',
                [$name, $email, password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]), 'user', 'pending']
            );
            Audit::log('user_registered', 'user', (int)$newId, $email);
            Mailer::notifyAdminsNewRegistration($name, $email);
            flash('success', 'Registration successful! Your account is pending approval.');
            redirect('/login');
        }
        View::render('auth/register', ['title' => 'Register'], 'auth');
    }

    public static function logout(): void
    {
        Auth::verifyCsrf();
        Audit::log('logout', 'user', Auth::id() ?? 0);
        Auth::logout();
        redirect('/login');
    }

    public static function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            // Rate limit: max 3 requests per 15 min per IP
            $ip = self::clientIp();
            $cnt = Database::fetchOne(
                'SELECT COUNT(*) c FROM login_attempts WHERE identifier=? AND failed_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
                [hash('sha256', 'pwreset:'.$ip)]
            );
            if ((int)($cnt['c'] ?? 0) >= 3) {
                flash('error', 'Too many reset requests. Please wait 15 minutes.'); redirect('/forgot-password');
            }
            Database::execute('INSERT INTO login_attempts (identifier) VALUES (?)', [hash('sha256', 'pwreset:'.$ip)]);

            $email = strtolower(trim($_POST['email'] ?? ''));
            $user  = Database::fetchOne('SELECT id, name, email FROM users WHERE email = ?', [$email]);
            if ($user) {
                $token = bin2hex(random_bytes(32));
                Database::execute('DELETE FROM password_reset_tokens WHERE user_id = ?', [$user['id']]);
                Database::insert(
                    'INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)',
                    [$user['id'], $token, date('Y-m-d H:i:s', time() + 3600)]
                );
                Mailer::sendPasswordReset($user, $token);
                Audit::log('password_reset_requested', 'user', (int)$user['id'], $email);
            }
            flash('success', 'If an account with that email exists, a reset link has been sent.');
            redirect('/forgot-password');
        }
        View::render('auth/forgot-password', ['title' => 'Passwort vergessen'], 'auth');
    }

    public static function resetPassword(): void
    {
        $token = $_GET['token'] ?? $_POST['token'] ?? '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm']  ?? '';
            $pwErr = self::validatePassword($password);
            if ($pwErr) { flash('error', $pwErr); redirect('/reset-password?token='.urlencode($token)); }
            if ($password !== $confirm) { flash('error', 'Passwords do not match.'); redirect('/reset-password?token='.urlencode($token)); }
            $row = Database::fetchOne(
                'SELECT * FROM password_reset_tokens WHERE token=? AND expires_at>NOW() AND used_at IS NULL',
                [$token]
            );
            if (!$row) { flash('error', 'Invalid or expired link.'); redirect('/login'); }
            Database::execute(
                'UPDATE users SET password_hash=?, password_changed_at=NOW() WHERE id=?',
                [password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]), $row['user_id']]
            );
            Database::execute('UPDATE password_reset_tokens SET used_at=NOW() WHERE id=?', [$row['id']]);
            Audit::log('password_reset_completed', 'user', (int)$row['user_id']);
            flash('success', 'Password changed successfully. Please sign in.');
            redirect('/login');
        }
        View::render('auth/reset-password', ['title' => 'Reset Password', 'token' => $token], 'auth');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function completeLogin(array $user, string $ip): void
    {
        session_regenerate_id(true); // Prevent session fixation
        try { Database::execute('DELETE FROM password_reset_tokens WHERE user_id=? AND used_at IS NULL', [$user['id']]); } catch (Throwable) {}
        try { Database::execute('UPDATE users SET last_login_at=NOW(), last_login_ip=? WHERE id=?', [$ip, $user['id']]); } catch (Throwable) {}
        Audit::log('login', 'user', (int)$user['id'], $user['email'] . ' from ' . $ip);
        Auth::login($user);
        $nextRaw = $_GET['next'] ?? '';
        $parsed  = parse_url($nextRaw);
        $next    = (!empty($parsed['path']) && empty($parsed['host']) && empty($parsed['scheme']))
                   ? $parsed['path'] : '/dashboard';
        redirect($next);
    }

    private static function validatePassword(string $pw): ?string
    {
        if (strlen($pw) < 10)          return 'Password must be at least 10 characters.';
        if (!preg_match('/[A-Z]/', $pw)) return 'Password must contain at least one uppercase letter.';
        if (!preg_match('/[a-z]/', $pw)) return 'Password must contain at least one lowercase letter.';
        if (!preg_match('/[0-9]/', $pw)) return 'Password must contain at least one digit.';
        if (!preg_match('/[^A-Za-z0-9]/', $pw)) return 'Password must contain at least one special character.';
        return null;
    }

    private static function clientIp(): string
    {
        // Prefer real IP behind reverse proxy
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) return trim(explode(',', $_SERVER[$h])[0]);
        }
        return '0.0.0.0';
    }

    private static function checkIpBan(string $ip): void
    {
        try {
            $ban = Database::fetchOne(
                'SELECT id FROM ip_bans WHERE ip_address=? AND (expires_at IS NULL OR expires_at > NOW())',
                [$ip]
            );
            if ($ban) {
                http_response_code(403);
                die('<h1>403 Forbidden</h1><p>Your IP address has been temporarily blocked due to suspicious activity. Contact your administrator.</p>');
            }
        } catch (Throwable) {}
    }

    private static function ident(string $email, string $ip): string
    {
        return hash('sha256', $ip . ':' . strtolower($email));
    }

    private static function checkBruteForce(string $email, string $ip): void
    {
        try {
            Database::execute("DELETE FROM login_attempts WHERE failed_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $ident = self::ident($email, $ip);
            $count = (int)(Database::fetchOne(
                'SELECT COUNT(*) c FROM login_attempts WHERE identifier=?', [$ident]
            )['c'] ?? 0);
            if ($count >= 10) {
                // Auto-ban IP after 10 failed attempts
                try {
                    Database::execute(
                        'INSERT INTO ip_bans (ip_address, reason, expires_at) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at), reason=VALUES(reason)',
                        [$ip, "Auto-banned: $count failed login attempts for $email", date('Y-m-d H:i:s', time() + 3600)]
                    );
                    // Notify admins
                    try {
                        $admins = Database::fetchAll("SELECT email FROM users WHERE role='admin' AND status='active'");
                        foreach ($admins as $admin) {
                            Mailer::sendSimple($admin['email'],
                                '[RoboDoc Security] IP Auto-Banned: ' . $ip,
                                "IP <strong>$ip</strong> has been auto-banned for 1 hour after $count failed login attempts for account: <strong>$email</strong>.<br><br>You can manage IP bans under Admin → Security."
                            );
                        }
                    } catch (Throwable) {}
                } catch (Throwable) {}
                http_response_code(429);
                flash('error', 'Too many failed login attempts. Your IP has been temporarily blocked. Contact your administrator.');
                redirect('/login');
            }
            if ($count >= 5) {
                http_response_code(429);
                flash('error', 'Too many failed login attempts. Please wait 15 minutes and try again.');
                redirect('/login');
            }
        } catch (Throwable) {}
    }

    private static function recordFailedAttempt(string $email, string $ip): void
    {
        try {
            Database::execute('INSERT INTO login_attempts (identifier) VALUES (?)', [self::ident($email, $ip)]);
        } catch (Throwable) {}
    }

    private static function clearAttempts(string $email, string $ip): void
    {
        try {
            Database::execute('DELETE FROM login_attempts WHERE identifier=?', [self::ident($email, $ip)]);
        } catch (Throwable) {}
    }
}
