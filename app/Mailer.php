<?php
declare(strict_types=1);

class Mailer
{
    public static function sendPasswordReset(array $user, string $token): void
    {
        $appUrl  = rtrim(appSetting('app_url', ''), '/');
        $link    = $appUrl . BASE_URL . '/reset-password?token=' . urlencode($token);
        $from    = appSetting('smtp_from', 'noreply@zimmimail.de');
        $subject = '[RoboDoc] Password Reset';
        $body    = "Hello {$user['name']},\n\n"
                 . "You requested a password reset. Click the link below (valid 1 hour):\n"
                 . $link . "\n\n"
                 . "If you did not request this, ignore this email.\n\n— RoboDoc\n";
        self::send($user['email'], $user['name'], $from, $subject, $body,
            appSetting('smtp_host', 'localhost'), (int)appSetting('smtp_port', '587'),
            appSetting('smtp_user'), appSetting('smtp_pass'));
    }

    public static function notifyAdminsNewRegistration(string $name, string $email): void
    {
        $from    = appSetting('smtp_from', 'noreply@zimmimail.de');
        $appUrl  = rtrim(appSetting('app_url', ''), '/');
        $link    = $appUrl . BASE_URL . '/admin/users';
        $subject = '[RoboDoc] New registration: ' . $name;
        $body    = "A new user registered and awaits approval.\n\nName:  $name\nEmail: $email\n\nApprove at: $link\n\n— RoboDoc\n";
        $admins  = Database::fetchAll("SELECT email, name FROM users WHERE role='admin' AND status='active'");
        foreach ($admins as $a) {
            self::send($a['email'], $a['name'], $from, $subject, $body,
                appSetting('smtp_host', 'localhost'), (int)appSetting('smtp_port', '587'),
                appSetting('smtp_user'), appSetting('smtp_pass'));
        }
    }

    public static function notifyAccountApproved(array $user): void
    {
        $from    = appSetting('smtp_from', 'noreply@zimmimail.de');
        $appUrl  = rtrim(appSetting('app_url', ''), '/');
        $link    = $appUrl . BASE_URL . '/login';
        $subject = '[RoboDoc] Your account has been approved';
        $body    = "Hello {$user['name']},\n\nYour RoboDoc account has been approved. Sign in at:\n$link\n\n— RoboDoc\n";
        self::send($user['email'], $user['name'], $from, $subject, $body,
            appSetting('smtp_host', 'localhost'), (int)appSetting('smtp_port', '587'),
            appSetting('smtp_user'), appSetting('smtp_pass'));
    }

    public static function notifyNewEntry(array $entry): void
    {
        $smtpHost = appSetting('smtp_host');
        $fromAddr = appSetting('smtp_from', appSetting('smtp_user'));
        if (!$smtpHost || !$fromAddr) return;

        $recipients = Database::fetchAll(
            'SELECT email, name FROM users WHERE notify_new_entries=1 AND id != ?',
            [$entry['created_by'] ?? 0]
        );
        if (!$recipients) return;

        $subject = '[RoboDoc] New entry: ' . ($entry['title'] ?: 'Entry #' . $entry['id']);
        $appUrl  = appSetting('app_url', '');
        $link    = $appUrl . BASE_URL . '/entries/' . $entry['id'];

        $body  = "A new entry was created in RoboDoc.\n\n";
        $body .= "Title:    " . ($entry['title'] ?: '—') . "\n";
        $body .= "Type:     " . ($entry['type_name'] ?? '—') . "\n";
        $body .= "Project:  " . ($entry['project_name'] ?? '—') . "\n";
        $body .= "Serial:   " . ($entry['mower_serial'] ?? '—') . "\n";
        $body .= "Date:     " . ($entry['entry_date'] ?? '—') . "\n";
        $body .= "By:       " . ($entry['creator'] ?? '—') . "\n\n";
        if ($entry['description'] ?? '') {
            $body .= wordwrap(strip_tags($entry['description']), 80) . "\n\n";
        }
        $body .= "View entry: " . $link . "\n";

        $smtpPort = (int) appSetting('smtp_port', '587');
        $smtpUser = appSetting('smtp_user');
        $smtpPass = appSetting('smtp_pass');

        foreach ($recipients as $r) {
            self::send($r['email'], $r['name'], $fromAddr, $subject, $body, $smtpHost, $smtpPort, $smtpUser, $smtpPass);
        }
    }

    private static function send(
        string $toEmail, string $toName, string $fromAddr,
        string $subject, string $body,
        string $host, int $port, string $user, string $pass
    ): void {
        try {
            if ($host && $user && $pass) {
                self::smtpSend($toEmail, $toName, $fromAddr, $subject, $body, $host, $port, $user, $pass);
            } else {
                $headers  = "From: RoboDoc <{$fromAddr}>\r\n";
                $headers .= "To: {$toName} <{$toEmail}>\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                mail($toEmail, $subject, $body, $headers);
            }
        } catch (\Throwable) {}
    }

    private static function smtpSend(
        string $toEmail, string $toName, string $fromAddr,
        string $subject, string $body,
        string $host, int $port, string $user, string $pass
    ): void {
        $sock = fsockopen(
            ($port === 465 ? 'ssl://' : '') . $host,
            $port, $errno, $errstr, 10
        );
        if (!$sock) return;

        $read = fn() => fgets($sock, 512);
        $write = fn($s) => fwrite($sock, $s . "\r\n");

        $read();
        $write("EHLO robodoc");
        while ($line = $read()) { if ($line[3] === ' ') break; }

        if ($port !== 465) {
            $write("STARTTLS");
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO robodoc");
            while ($line = $read()) { if ($line[3] === ' ') break; }
        }

        $write("AUTH LOGIN");
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $read();

        $write("MAIL FROM:<{$fromAddr}>");
        $read();
        $write("RCPT TO:<{$toEmail}>");
        $read();
        $write("DATA");
        $read();

        $msg  = "From: RoboDoc <{$fromAddr}>\r\n";
        $msg .= "To: {$toName} <{$toEmail}>\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $msg .= $body . "\r\n.\r\n";
        fwrite($sock, $msg);
        $read();

        $write("QUIT");
        fclose($sock);
    }
}
