<?php
declare(strict_types=1);

/**
 * Audit logging — NIS2 Art.21 compliant.
 * All security-relevant events are logged with user, IP, timestamp, and context.
 */
class Audit
{
    public static function log(
        string $action,
        string $resourceType = '',
        int    $resourceId   = 0,
        string $data         = ''
    ): void {
        try {
            $ip        = self::clientIp();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            Database::execute(
                'INSERT INTO audit_log (user_id, action, resource_type, resource_id, data, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    Auth::id(),
                    $action,
                    $resourceType ?: null,
                    $resourceId   ?: null,
                    $data         ?: null,
                    $ip,
                    $userAgent,
                ]
            );
        } catch (Throwable) {}
    }

    /** Log data access (NIS2: traceability of data access) */
    public static function access(string $resource, int $id, string $action = 'view'): void
    {
        self::log("access:{$action}", $resource, $id);
    }

    /** Log security events with high severity */
    public static function security(string $event, string $detail = ''): void
    {
        self::log("security:{$event}", 'system', 0, $detail);
        // Alert admins for critical security events
        if (in_array($event, ['ip_banned','brute_force','2fa_disabled','privilege_escalation','unauthorized_access'])) {
            try {
                $admins = Database::fetchAll("SELECT email FROM users WHERE role='admin' AND status='active'");
                foreach ($admins as $admin) {
                    Mailer::sendSimple(
                        $admin['email'],
                        '[RoboDoc Security Alert] ' . ucfirst(str_replace('_',' ',$event)),
                        'Security event: <strong>' . htmlspecialchars($event) . '</strong><br>'
                        . 'Detail: ' . htmlspecialchars($detail) . '<br>'
                        . 'IP: ' . self::clientIp() . '<br>'
                        . 'Time: ' . date('Y-m-d H:i:s') . '<br>'
                        . 'User: ' . (Auth::id() ? '#'.Auth::id() : 'anonymous')
                    );
                }
            } catch (Throwable) {}
        }
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) return trim(explode(',', $_SERVER[$h])[0]);
        }
        return '0.0.0.0';
    }
}
