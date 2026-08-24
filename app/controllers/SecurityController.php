<?php
declare(strict_types=1);

class SecurityController
{
    public static function index(): void
    {
        Auth::requireAdmin();
        View::render('admin/security', ['title' => 'Security']);
    }

    public static function ban(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $ip       = trim($_POST['ip'] ?? '');
        $reason   = trim($_POST['reason'] ?? 'Manual ban');
        $duration = (int)($_POST['duration'] ?? 24);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            flash('error', 'Invalid IP address.');
            redirect('/admin/security');
        }
        $expires = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration * 3600) : null;
        Database::execute(
            'INSERT INTO ip_bans (ip_address, reason, expires_at, created_by) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE reason=VALUES(reason), expires_at=VALUES(expires_at), banned_at=NOW()',
            [$ip, $reason, $expires, Auth::id()]
        );
        Audit::log('ip_banned', 'security', 0, $ip . ' — ' . $reason);
        flash('success', "IP $ip has been banned.");
        redirect('/admin/security');
    }

    public static function unban(string $id): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        $ban = Database::fetchOne('SELECT ip_address FROM ip_bans WHERE id=?', [(int)$id]);
        Database::execute('DELETE FROM ip_bans WHERE id=?', [(int)$id]);
        if ($ban) Audit::log('ip_unbanned', 'security', 0, $ban['ip_address']);
        flash('success', 'IP ban removed.');
        redirect('/admin/security');
    }

    public static function nis2(): void
    {
        Auth::requireAdmin();
        View::render('admin/nis2', ['title' => 'NIS2 Compliance']);
    }

    public static function killSession(string $id): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();
        Database::execute('DELETE FROM active_sessions WHERE id=?', [$id]);
        Audit::security('session_terminated', 'Session ' . substr($id, 0, 8) . '... terminated by admin');
        flash('success', 'Session terminated.');
        redirect('/admin/security');
    }
}
