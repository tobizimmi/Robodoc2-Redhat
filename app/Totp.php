<?php
declare(strict_types=1);

/**
 * TOTP (Time-based One-Time Password) — RFC 6238
 * Compatible with Google Authenticator, Microsoft Authenticator, Authy.
 * No external dependencies. Uses manual big-endian pack for compatibility.
 */
class Totp
{
    private const DIGITS  = 6;
    private const PERIOD  = 30;
    private const ALGO    = 'sha1';
    private const BASE32  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        $bytes  = random_bytes(10);
        $result = '';
        $buf    = 0;
        $blen   = 0;
        foreach (str_split($bytes) as $byte) {
            $buf   = ($buf << 8) | ord($byte);
            $blen += 8;
            while ($blen >= 5) {
                $blen  -= 5;
                $result .= self::BASE32[($buf >> $blen) & 31];
            }
        }
        return $result;
    }

    public static function getUri(string $secret, string $email, string $issuer = 'RoboDoc'): string
    {
        return 'otpauth://totp/'
            . rawurlencode($issuer . ':' . $email)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    public static function generateCode(string $secret, int $counter = 0): string
    {
        $key = self::base32Decode($secret);
        if ($counter === 0) $counter = (int)floor(time() / self::PERIOD);
        return self::hotp($key, $counter);
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) return false;
        $key = self::base32Decode($secret);
        if ($key === '') return false;
        $ts  = (int)floor(time() / self::PERIOD);
        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals(self::hotp($key, $ts + $offset), $code)) return true;
        }
        return false;
    }

    public static function getQrUrl(string $uri): string
    {
        return 'https://chart.googleapis.com/chart?cht=qr&chs=200x200&chld=M|0&chl='
            . rawurlencode($uri);
    }

    private static function hotp(string $key, int $counter): string
    {
        // Pack counter as 8-byte big-endian (RFC 4226)
        // Use manual packing for PHP 32-bit compatibility
        $data = chr(0) . chr(0) . chr(0) . chr(0)
            . chr(($counter >> 24) & 0xff)
            . chr(($counter >> 16) & 0xff)
            . chr(($counter >>  8) & 0xff)
            . chr( $counter        & 0xff);

        $hmac = hash_hmac(self::ALGO, $data, $key, true);
        $off  = ord($hmac[19]) & 0xf;
        $code = (
            ((ord($hmac[$off])     & 0x7f) << 24) |
            ((ord($hmac[$off + 1]) & 0xff) << 16) |
            ((ord($hmac[$off + 2]) & 0xff) <<  8) |
             (ord($hmac[$off + 3]) & 0xff)
        ) % (10 ** self::DIGITS);
        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $input): string
    {
        $input  = strtoupper(preg_replace('/[\s=]/', '', $input));
        $buf    = 0;
        $blen   = 0;
        $result = '';
        foreach (str_split($input) as $char) {
            $val = strpos(self::BASE32, $char);
            if ($val === false) continue;
            $buf   = ($buf << 5) | $val;
            $blen += 5;
            if ($blen >= 8) {
                $blen  -= 8;
                $result .= chr(($buf >> $blen) & 0xff);
            }
        }
        return $result;
    }
}
