<?php
declare(strict_types=1);

/**
 * "Sign in with Microsoft" (Entra ID / Azure AD) via OpenID Connect authorization
 * code flow. Additive to the existing password login — never auto-provisions an
 * account: the Microsoft account's email must already match an existing user.
 */
class MicrosoftAuthController
{
    // ── Start: redirect to Microsoft's login page ─────────────────────────
    public static function redirect(): void
    {
        if (Auth::check()) redirect('/dashboard');

        $tenantId = appSetting('ms_tenant_id');
        $clientId = appSetting('ms_client_id');
        if (appSetting('ms_sso_enabled') !== '1' || !$tenantId || !$clientId) {
            flash('error', 'Microsoft-Anmeldung ist nicht konfiguriert.');
            redirect('/login');
        }

        $state = bin2hex(random_bytes(32));
        $_SESSION['ms_sso_state'] = $state;

        $params = http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'redirect_uri'  => self::callbackUrl(),
            'response_mode' => 'query',
            'scope'         => 'openid profile email',
            'state'         => $state,
        ]);
        header('Location: https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/authorize?' . $params);
        exit;
    }

    // ── Callback: exchange code, validate token, log in ───────────────────
    public static function callback(): void
    {
        if (Auth::check()) redirect('/dashboard');

        $tenantId     = appSetting('ms_tenant_id');
        $clientId     = appSetting('ms_client_id');
        $clientSecret = Encryption::decrypt((string)appSetting('ms_client_secret'));
        if (appSetting('ms_sso_enabled') !== '1' || !$tenantId || !$clientId || !$clientSecret) {
            flash('error', 'Microsoft-Anmeldung ist nicht konfiguriert.');
            redirect('/login');
        }

        if (!empty($_GET['error'])) {
            flash('error', 'Microsoft-Anmeldung abgebrochen: ' . ($_GET['error_description'] ?? $_GET['error']));
            redirect('/login');
        }

        $state = $_GET['state'] ?? '';
        $expected = $_SESSION['ms_sso_state'] ?? '';
        unset($_SESSION['ms_sso_state']);
        if (!$state || !$expected || !hash_equals($expected, $state)) {
            flash('error', 'Ungültige Anmeldeanfrage. Bitte erneut versuchen.');
            redirect('/login');
        }

        $code = $_GET['code'] ?? '';
        if (!$code) { flash('error', 'Microsoft-Anmeldung fehlgeschlagen.'); redirect('/login'); }

        $ch = curl_init("https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => self::callbackUrl(),
                'scope'         => 'openid profile email',
            ]),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp ?: '{}', true);
        if ($httpCode !== 200 || empty($data['id_token'])) {
            Audit::log('login_sso_failed', 'user', 0, 'Microsoft token exchange failed: HTTP ' . $httpCode);
            flash('error', 'Microsoft-Anmeldung fehlgeschlagen.');
            redirect('/login');
        }

        try {
            $claims = self::validateIdToken((string)$data['id_token'], $tenantId, $clientId);
        } catch (\Throwable $e) {
            Audit::log('login_sso_failed', 'user', 0, 'ID token validation failed: ' . $e->getMessage());
            flash('error', 'Microsoft-Anmeldung fehlgeschlagen (ungültiges Token).');
            redirect('/login');
        }

        $email = strtolower(trim((string)($claims['email'] ?? $claims['preferred_username'] ?? '')));
        if (!$email) {
            flash('error', 'Im Microsoft-Konto wurde keine E-Mail-Adresse gefunden.');
            redirect('/login');
        }

        $user = Database::fetchOne('SELECT id, name, email, role, status FROM users WHERE email = ?', [$email]);
        if (!$user) {
            Audit::log('login_sso_no_account', 'user', 0, $email);
            flash('error', "Kein RoboDoc2-Konto für $email gefunden. Bitte einen Administrator kontaktieren.");
            redirect('/login');
        }
        $status = $user['status'] ?? 'active';
        if ($status === 'pending') {
            flash('error', 'Dein Account wartet noch auf Freischaltung durch einen Administrator.');
            redirect('/login');
        }
        if ($status === 'disabled') {
            flash('error', 'Dein Account wurde deaktiviert. Bitte einen Administrator kontaktieren.');
            redirect('/login');
        }

        AuthController::completeLogin($user, AuthController::clientIp());
    }

    private static function callbackUrl(): string
    {
        $fwd    = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $scheme = $fwd === 'https' ? 'https' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/auth/microsoft/callback';
    }

    // ── ID token validation (OIDC, RS256) ──────────────────────────────────
    // No external JWT/JOSE library is used anywhere in this codebase, so this
    // verifies the signature by hand: fetch Microsoft's published RSA public
    // keys (JWKS), match by "kid", rebuild a PEM key from the raw modulus/
    // exponent, and verify with openssl. Only RS256 is accepted (rejecting
    // "alg: none" and any other algorithm is essential - otherwise a forged,
    // unsigned token could be accepted as valid).
    private static function validateIdToken(string $idToken, string $tenantId, string $clientId): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) throw new \RuntimeException('Malformed ID token.');
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header  = json_decode(self::base64UrlDecode($headerB64), true);
        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        $sig     = self::base64UrlDecode($sigB64);
        if (!is_array($header) || !is_array($payload)) throw new \RuntimeException('Malformed ID token.');
        if (($header['alg'] ?? '') !== 'RS256') throw new \RuntimeException('Unexpected token algorithm.');

        $jwks = self::fetchJwks($tenantId);
        $kid  = (string)($header['kid'] ?? '');
        $jwk  = null;
        foreach (($jwks['keys'] ?? []) as $k) {
            if (($k['kid'] ?? '') === $kid) { $jwk = $k; break; }
        }
        if (!$jwk || empty($jwk['n']) || empty($jwk['e'])) throw new \RuntimeException('Signing key not found.');

        $pem    = self::jwkToPem((string)$jwk['n'], (string)$jwk['e']);
        $pubKey = openssl_pkey_get_public($pem);
        if ($pubKey === false) throw new \RuntimeException('Invalid signing key.');

        $ok = openssl_verify($headerB64 . '.' . $payloadB64, $sig, $pubKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) throw new \RuntimeException('Token signature invalid.');

        if (($payload['aud'] ?? '') !== $clientId) throw new \RuntimeException('Token audience mismatch.');
        if (($payload['iss'] ?? '') !== "https://login.microsoftonline.com/$tenantId/v2.0") {
            throw new \RuntimeException('Token issuer mismatch.');
        }
        if ((int)($payload['exp'] ?? 0) < time()) throw new \RuntimeException('Token expired.');
        if (isset($payload['nbf']) && (int)$payload['nbf'] > time()) throw new \RuntimeException('Token not yet valid.');

        return $payload;
    }

    private static function fetchJwks(string $tenantId): array
    {
        $ch = curl_init("https://login.microsoftonline.com/$tenantId/discovery/v2.0/keys");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    private static function jwkToPem(string $nB64Url, string $eB64Url): string
    {
        $modulus  = self::derInteger(self::base64UrlDecode($nB64Url));
        $exponent = self::derInteger(self::base64UrlDecode($eB64Url));
        $rsaPublicKeySeq = self::derSequence($modulus . $exponent);

        // rsaEncryption OID (1.2.840.113549.1.1.1) + NULL params
        $algorithmIdentifier = self::derSequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $bitString    = "\x00" . $rsaPublicKeySeq; // leading byte = number of unused bits
        $bitStringDer = "\x03" . self::derLength(strlen($bitString)) . $bitString;
        $spki         = self::derSequence($algorithmIdentifier . $bitStringDer);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derLength(int $len): string
    {
        if ($len < 128) return chr($len);
        $bytes = ltrim(pack('N', $len), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') $bytes = "\x00";
        if (ord($bytes[0]) > 0x7f) $bytes = "\x00" . $bytes; // keep it a positive INTEGER
        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $contents): string
    {
        return "\x30" . self::derLength(strlen($contents)) . $contents;
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) $data .= str_repeat('=', 4 - $remainder);
        return (string)base64_decode(strtr($data, '-_', '+/'));
    }
}
