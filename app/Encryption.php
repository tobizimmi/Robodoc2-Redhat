<?php
declare(strict_types=1);

/**
 * AES-256-GCM encryption for sensitive values stored in the database.
 *
 * The encryption key is derived from APP_SECRET (defined in config.local.php).
 * NEVER store the key in the database — it must be in the environment/config file.
 *
 * Format of encrypted value: base64(iv[12] + tag[16] + ciphertext)
 * Prefix "enc:" identifies encrypted values so legacy plaintext values still work.
 */
class Encryption
{
    private const CIPHER    = 'aes-256-gcm';
    private const IV_LEN    = 12;
    private const TAG_LEN   = 16;
    private const PREFIX    = 'enc:';
    private const KEY_INFO  = 'robodoc-field-encryption-v1';

    /** Encrypt a plaintext value. Returns prefixed base64 string. */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') return '';
        $key = self::deriveKey();
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $ct  = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        if ($ct === false) throw new \RuntimeException('Encryption failed');
        return self::PREFIX . base64_encode($iv . $tag . $ct);
    }

    /** Decrypt a value. Returns plaintext. Handles legacy unencrypted values gracefully. */
    public static function decrypt(string $value): string
    {
        if ($value === '') return '';
        // Legacy: not encrypted yet — return as-is
        if (!str_starts_with($value, self::PREFIX)) return $value;
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN + 1) return '';
        $key = self::deriveKey();
        $iv  = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ct  = substr($raw, self::IV_LEN + self::TAG_LEN);
        $pt  = openssl_decrypt($ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $pt !== false ? $pt : '';
    }

    /** Encrypt if not already encrypted */
    public static function encryptIfNeeded(string $value): string
    {
        if ($value === '' || str_starts_with($value, self::PREFIX)) return $value;
        return self::encrypt($value);
    }

    /** Check if a value is encrypted */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Derive a 256-bit key from APP_SECRET using HKDF-SHA256.
     * This ensures the DB encryption key is always derived from,
     * never equal to, the application secret.
     */
    private static function deriveKey(): string
    {
        $secret = defined('APP_SECRET') ? APP_SECRET : throw new \RuntimeException('APP_SECRET not defined');
        // HKDF extract + expand
        $prk = hash_hmac('sha256', $secret, 'robodoc-hkdf-salt-v1', true);
        $okm = hash_hmac('sha256', self::KEY_INFO . chr(1), $prk, true);
        return $okm; // 32 bytes = 256 bits
    }
}
