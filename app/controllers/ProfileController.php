<?php
declare(strict_types=1);

class ProfileController {
    public static function index(): void {
        Auth::require();
        $user = Database::fetchOne('SELECT * FROM users WHERE id=?', [Auth::id()]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $action = $_POST['action'] ?? 'profile';

            if ($action === 'profile') {
                $name = trim($_POST['name'] ?? '');
                if (!$name) { flash('error', 'Name cannot be empty.'); redirect('/profile'); }
                Database::execute('UPDATE users SET name=? WHERE id=?', [$name, Auth::id()]);
                Auth::refreshUser();
                flash('success', 'Profile updated.');
            }

            if ($action === 'password') {
                $current = $_POST['current_password'] ?? '';
                $new     = $_POST['new_password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';
                if (!password_verify($current, $user['password_hash'])) {
                    flash('error', 'Current password is incorrect.');
                } elseif (strlen($new) < 8) {
                    flash('error', 'New password must be at least 8 characters.');
                } elseif ($new !== $confirm) {
                    flash('error', 'Passwords do not match.');
                } else {
                    Database::execute('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_BCRYPT), Auth::id()]);
                    flash('success', 'Password changed.');
                }
            }

            if ($action === 'jira') {
                Database::execute(
                    'UPDATE users SET jira_email=?, jira_api_key=? WHERE id=?',
                    [trim($_POST['jira_email'] ?? ''), trim($_POST['jira_api_key'] ?? ''), Auth::id()]
                );
                flash('success', 'Jira credentials saved.');
            }

            if ($action === 'jira_template') {
                Database::execute(
                    'UPDATE users SET jira_title_template=?, jira_desc_template=? WHERE id=?',
                    [trim($_POST['jira_title_template'] ?? '') ?: null,
                     trim($_POST['jira_desc_template']  ?? '') ?: null,
                     Auth::id()]
                );
                flash('success', 'Jira default template saved.');
            }

            if ($action === 'confluence') {
                Database::execute(
                    'UPDATE users SET confluence_email=?, confluence_token=? WHERE id=?',
                    [trim($_POST['confluence_email'] ?? ''), Encryption::encryptIfNeeded(trim($_POST['confluence_token'] ?? '')), Auth::id()]
                );
                flash('success', 'Confluence credentials saved.');
            }

            if ($action === 'preferences') {
                Database::execute(
                    'UPDATE users SET jira_auto_create=?, notify_new_entries=? WHERE id=?',
                    [!empty($_POST['jira_auto_create']) ? 1 : 0, !empty($_POST['notify_new_entries']) ? 1 : 0, Auth::id()]
                );
                flash('success', 'Preferences saved.');
            }

            if ($action === 'sharepoint') {
                Database::execute(
                    'UPDATE users SET sharepoint_site_url=?, sharepoint_tenant_id=?, sharepoint_client_id=?, sharepoint_client_secret=?, sharepoint_path_template=? WHERE id=?',
                    [
                        trim($_POST['sharepoint_site_url']      ?? ''),
                        trim($_POST['sharepoint_tenant_id']     ?? ''),
                        trim($_POST['sharepoint_client_id']     ?? ''),
                        trim($_POST['sharepoint_client_secret'] ?? '') ?: ($user['sharepoint_client_secret'] ?? ''),
                        trim($_POST['sharepoint_path_template'] ?? ''),
                        Auth::id(),
                    ]
                );
                flash('success', 'SharePoint settings saved.');
            }

            redirect('/profile');
        }

        // Load full user data including 2FA status
        $currentUser = Database::fetchOne('SELECT * FROM users WHERE id=?', [Auth::id()]) ?? $currentUser;
        View::render('profile/index', ['user' => $user, 'currentUser' => $user, 'title' => 'Profile']);
    }
}
