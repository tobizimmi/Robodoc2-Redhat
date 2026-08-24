<?php
declare(strict_types=1);

/**
 * DSGVO Art. 20 — Datenportabilität
 * Nutzer können alle ihre gespeicherten Daten exportieren.
 */
class GdprController
{
    public static function export(): void
    {
        Auth::require();
        $userId = Auth::id();
        $user   = Database::fetchOne('SELECT id, name, email, role, created_at, last_login_at, last_login_ip FROM users WHERE id=?', [$userId]);

        // Sammle alle personenbezogenen Daten
        $data = [
            'export_info' => [
                'generated_at'  => date('Y-m-d H:i:s'),
                'generated_for' => $user['email'],
                'legal_basis'   => 'DSGVO Art. 20 — Recht auf Datenübertragbarkeit',
            ],
            'account' => [
                'id'            => $user['id'],
                'name'          => $user['name'],
                'email'         => $user['email'],
                'role'          => $user['role'],
                'member_since'  => $user['created_at'],
                'last_login'    => $user['last_login_at'],
                '2fa_enabled'   => (bool)($user['totp_enabled'] ?? false),
            ],
            'entries'        => self::getEntries($userId),
            'comments'       => self::getComments($userId),
            'feedback'       => self::getFeedback($userId),
            'audit_log'      => self::getAuditLog($userId),
            'active_sessions'=> self::getSessions($userId),
        ];

        Audit::log('gdpr_export', 'user', $userId, 'Data export requested');

        $format = $_GET['format'] ?? 'json';

        if ($format === 'csv') {
            self::downloadCsv($data, $user['email']);
        } else {
            self::downloadJson($data, $user['email']);
        }
    }

    private static function getEntries(int $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT e.id, e.title, e.description, e.status, e.priority, e.created_at, e.updated_at,
                    p.name project_name
             FROM entries e
             LEFT JOIN projects p ON p.id = e.project_id
             WHERE e.created_by = ?
             ORDER BY e.created_at DESC
             LIMIT 1000',
            [$userId]
        );
        return $rows ?: [];
    }

    private static function getComments(int $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT c.id, c.body, c.created_at, e.title entry_title
             FROM entry_comments c
             LEFT JOIN entries e ON e.id = c.entry_id
             WHERE c.user_id = ?
             ORDER BY c.created_at DESC
             LIMIT 500',
            [$userId]
        );
        return $rows ?: [];
    }

    private static function getFeedback(int $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT id, type, title, message, status, created_at
             FROM user_feedback
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$userId]
        );
        return $rows ?: [];
    }

    private static function getAuditLog(int $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT action, resource_type, data, ip_address, created_at
             FROM audit_log
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 500',
            [$userId]
        );
        return $rows ?: [];
    }

    private static function getSessions(int $userId): array
    {
        try {
            $rows = Database::fetchAll(
                'SELECT ip_address, user_agent, created_at, last_activity
                 FROM active_sessions WHERE user_id = ?',
                [$userId]
            );
            return $rows ?: [];
        } catch (Throwable) { return []; }
    }

    private static function downloadJson(array $data, string $email): void
    {
        $filename = 'robodoc-daten-' . preg_replace('/[^a-z0-9]/i', '_', $email) . '-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function downloadCsv(array $data, string $email): void
    {
        $filename = 'robodoc-daten-' . preg_replace('/[^a-z0-9]/i', '_', $email) . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');
        $out = fopen('php://output', 'w');
        // BOM für Excel
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        // Account
        fputcsv($out, ['=== KONTO ===']);
        fputcsv($out, array_keys($data['account']));
        fputcsv($out, array_values($data['account']));
        fputcsv($out, []);
        // Entries
        fputcsv($out, ['=== EINTRÄGE ===']);
        if ($data['entries']) {
            fputcsv($out, array_keys($data['entries'][0]));
            foreach ($data['entries'] as $row) fputcsv($out, array_values($row));
        }
        fputcsv($out, []);
        // Comments
        fputcsv($out, ['=== KOMMENTARE ===']);
        if ($data['comments']) {
            fputcsv($out, array_keys($data['comments'][0]));
            foreach ($data['comments'] as $row) fputcsv($out, array_values($row));
        }
        fputcsv($out, []);
        // Feedback
        fputcsv($out, ['=== FEEDBACK ===']);
        if ($data['feedback']) {
            fputcsv($out, array_keys($data['feedback'][0]));
            foreach ($data['feedback'] as $row) fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }
}
