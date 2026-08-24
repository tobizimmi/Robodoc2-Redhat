<?php
declare(strict_types=1);

class SharepointController {

    public static function connect(): void
    {
        Auth::require();
        [$tenantId, $clientId] = self::resolveAppCreds();
        if (!$tenantId || !$clientId) {
            flash('error', 'SharePoint Tenant ID and Client ID must be configured first.');
            redirect('/profile');
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['sp_oauth_state'] = $state;
        $params = http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'redirect_uri'  => self::callbackUrl(),
            'scope'         => 'Files.ReadWrite.All Sites.ReadWrite.All offline_access User.Read',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
        header('Location: https://login.microsoftonline.com/' . urlencode($tenantId) . '/oauth2/v2.0/authorize?' . $params);
        exit;
    }

    public static function callback(): void
    {
        Auth::require();
        if (isset($_GET['error'])) {
            flash('error', 'Microsoft login failed: ' . e($_GET['error_description'] ?? $_GET['error']));
            redirect('/profile');
        }
        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';
        if (!$code || $state !== ($_SESSION['sp_oauth_state'] ?? '')) {
            flash('error', 'Invalid OAuth2 state. Please try again.');
            redirect('/profile');
        }
        unset($_SESSION['sp_oauth_state']);
        [$tenantId, $clientId, $clientSecret] = self::resolveAppCreds();
        $tokens = self::exchangeCode($tenantId, $clientId, $clientSecret, $code, self::callbackUrl());
        if (!$tokens) {
            flash('error', 'Could not exchange authorization code for tokens.');
            redirect('/profile');
        }
        Database::execute(
            'UPDATE users SET sharepoint_access_token=?, sharepoint_refresh_token=?, sharepoint_token_expires_at=? WHERE id=?',
            [Encryption::encryptIfNeeded($tokens['access_token']), Encryption::encryptIfNeeded($tokens['refresh_token'] ?? ''), time() + (int)($tokens['expires_in'] ?? 3600) - 60, Auth::id()]
        );
        flash('success', 'SharePoint connected successfully!');
        redirect('/profile');
    }

    public static function disconnect(): void
    {
        Auth::require();
        Auth::verifyCsrf();
        Database::execute(
            'UPDATE users SET sharepoint_access_token=NULL, sharepoint_refresh_token=NULL, sharepoint_token_expires_at=NULL WHERE id=?',
            [Auth::id()]
        );
        flash('success', 'SharePoint disconnected.');
        redirect('/profile');
    }

    public static function upload(string $id): void
    {
        Auth::require();
        Auth::verifyCsrf();
        header('Content-Type: application/json');
        @set_time_limit(280);
        @ini_set('max_execution_time', '280');
        $entry = Database::fetchOne(
            "SELECT e.*, p.name project_name, p.sharepoint_folder project_sp_folder
             FROM entries e LEFT JOIN projects p ON p.id = e.project_id WHERE e.id = ?",
            [(int)$id]
        );
        if (!$entry) { echo json_encode(['error' => 'Entry not found']); exit; }
        $folder = trim($_POST['folder'] ?? $entry['project_sp_folder'] ?? '');
        $attIds = array_filter(array_map('intval', (array)($_POST['att_ids'] ?? [])));
        if ($folder === '') { echo json_encode(['error' => 'No target folder specified.']); exit; }
        $siteUrl = self::resolveSiteUrl();
        if (!$siteUrl) { echo json_encode(['error' => 'SharePoint Site URL not configured.']); exit; }
        // Rate limit: max 20 uploads per minute per user
        $rlKey = 'sp_upload_' . Auth::id();
        $rlCount = (int)(Database::fetchOne(
            'SELECT COUNT(*) c FROM audit_log WHERE user_id=? AND action=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)',
            [Auth::id(), 'sharepoint_upload']
        )['c'] ?? 0);
        if ($rlCount >= 20) {
            echo json_encode(['error' => 'Rate limit exceeded. Please wait a moment.']);
            exit;
        }
        Audit::log('sharepoint_upload', 'entry', (int)$id);
        $token = self::getValidUserToken();
        $mode  = 'delegated';
        if (!$token) { $token = self::getAppToken(); $mode = 'app'; }
        if (!$token) { echo json_encode(['error' => 'Not authenticated to SharePoint.']); exit; }
        $wherePart   = $attIds ? 'entry_id=? AND id IN (' . implode(',', array_fill(0, count($attIds), '?')) . ')' : 'entry_id=?';
        $attachments = Database::fetchAll(
            "SELECT id, file_path, mime_type, display_name, original_name FROM entry_attachments WHERE $wherePart ORDER BY created_at",
            $attIds ? array_merge([(int)$id], $attIds) : [(int)$id]
        );
        if (!$attachments) { echo json_encode(['error' => 'No attachments found.']); exit; }
        [$siteId, $siteErr] = self::getSiteId($siteUrl, $token);
        if (!$siteId) { echo json_encode(['error' => "Could not find SharePoint site: $siteErr."]); exit; }
        $results = []; $errors = [];
        foreach ($attachments as $att) {
            if (!file_exists($att['file_path'])) { $errors[] = ($att['display_name'] ?: $att['original_name']) . ': file not found'; continue; }
            $filename  = $att['display_name'] ?: $att['original_name'];
            $itemPath  = trim($folder, '/') . '/' . $filename;
            $localSize = filesize($att['file_path']);
            $simpleUploadLimit = 4 * 1024 * 1024;
            if ($localSize <= $simpleUploadLimit) {
                $uploadUrl = 'https://graph.microsoft.com/v1.0/sites/' . rawurlencode($siteId) . '/drive/root:/' . self::encodePath($itemPath) . ':/content';
                $fh = fopen($att['file_path'], 'rb');
                $ch = curl_init($uploadUrl);
                curl_setopt_array($ch, [CURLOPT_PUT=>true,CURLOPT_INFILE=>$fh,CURLOPT_INFILESIZE=>$localSize,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: '.($att['mime_type']?:'application/octet-stream')],CURLOPT_TIMEOUT=>90]);
                $resp = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fh);
                $data = json_decode($resp, true);
                $ok = ($httpCode === 200 || $httpCode === 201);
                $webUrl = $ok ? ($data['webUrl'] ?? null) : null;
                $errMsg = $ok ? null : ($data['error']['message'] ?? "HTTP $httpCode");
            } else {
                [$webUrl, $errMsg] = self::uploadLargeFile($siteId, $itemPath, $att['file_path'], $localSize, $att['mime_type'] ?: 'application/octet-stream', $token);
                $ok = $webUrl !== null;
            }
            if ($ok) {
                $results[] = ['name' => $filename, 'url' => $webUrl];
                if ($webUrl) { try { Database::execute('INSERT INTO entry_sharepoint_files (entry_id, attachment_id, filename, web_url) VALUES (?,?,?,?)', [(int)$id, $att['id'] ?? null, $filename, $webUrl]); } catch (Throwable) {} }
            } else { $errors[] = $filename . ': ' . $errMsg; }
        }
        if ($results) { $folderUrl = $results[0]['url'] ? dirname($results[0]['url']) : null; if ($folderUrl) Database::execute('UPDATE entries SET sharepoint_folder_url=? WHERE id=?', [$folderUrl, (int)$id]); }
        echo json_encode(['success' => count($results), 'errors' => $errors, 'uploaded' => $results, 'folder' => $folder]);
        exit;
    }

    private static function uploadLargeFile(string $siteId, string $itemPath, string $filePath, int $fileSize, string $mimeType, string $token): array
    {
        $sessionUrl = 'https://graph.microsoft.com/v1.0/sites/' . rawurlencode($siteId) . '/drive/root:/' . self::encodePath($itemPath) . ':/createUploadSession';
        $ch = curl_init($sessionUrl);
        curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['item'=>['@microsoft.graph.conflictBehavior'=>'replace']]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $session = json_decode($resp, true); $uploadUrl = $session['uploadUrl'] ?? null;
        if ($code !== 200 || !$uploadUrl) return [null, 'Could not create upload session: ' . ($session['error']['message'] ?? "HTTP $code")];
        $chunkSize = 10 * 1024 * 1024;
        $fh = fopen($filePath, 'rb');
        if (!$fh) return [null, 'Could not open file'];
        $offset = 0; $finalWebUrl = null; $finalError = null;
        while ($offset < $fileSize) {
            $thisChunkSize = min($chunkSize, $fileSize - $offset);
            $chunkData = fread($fh, $thisChunkSize);
            $rangeEnd  = $offset + $thisChunkSize - 1;
            $ch = curl_init($uploadUrl);
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>$chunkData,CURLOPT_HTTPHEADER=>['Content-Length: '.$thisChunkSize,'Content-Range: bytes '.$offset.'-'.$rangeEnd.'/'.$fileSize],CURLOPT_TIMEOUT=>120]);
            $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($code === 202) { $offset += $thisChunkSize; continue; }
            if ($code === 200 || $code === 201) { $data = json_decode($resp, true); $finalWebUrl = $data['webUrl'] ?? null; $offset += $thisChunkSize; break; }
            $data = json_decode($resp, true); $finalError = 'Chunk failed at byte '.$offset.': '.($data['error']['message'] ?? "HTTP $code"); break;
        }
        fclose($fh);
        return [$finalWebUrl, $finalError ?? ($finalWebUrl === null ? 'Upload ended without response' : null)];
    }

    private static function callbackUrl(): string
    {
        $fwd = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $scheme = $fwd === 'https' ? 'https' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/sharepoint/callback';
    }

    private static function resolveAppCreds(): array
    {
        $u = Database::fetchOne('SELECT sharepoint_tenant_id, sharepoint_client_id, sharepoint_client_secret FROM users WHERE id=?', [Auth::id()]);
        $tenantId     = trim($u['sharepoint_tenant_id']     ?? '') ?: appSetting('sharepoint_tenant_id');
        $clientId     = trim($u['sharepoint_client_id']     ?? '') ?: appSetting('sharepoint_client_id');
        $clientSecret = trim(Encryption::decrypt((string)($u['sharepoint_client_secret'] ?? '')) ?? '') ?: appSetting('sharepoint_client_secret');
        return [$tenantId, $clientId, $clientSecret];
    }

    private static function resolveSiteUrl(): string
    {
        $u   = Database::fetchOne('SELECT sharepoint_site_url FROM users WHERE id=?', [Auth::id()]);
        $url = rtrim($u['sharepoint_site_url'] ?? '', '/') ?: rtrim(appSetting('sharepoint_site_url'), '/');
        // SSRF protection: only allow HTTPS SharePoint URLs
        if ($url && (!str_starts_with($url, 'https://') || !filter_var($url, FILTER_VALIDATE_URL))) {
            return '';
        }
        return $url;
    }

    private static function getValidUserToken(): ?string
    {
        $u = Database::fetchOne('SELECT sharepoint_access_token, sharepoint_refresh_token, sharepoint_token_expires_at FROM users WHERE id=?', [Auth::id()]);
        if (!$u || !($u['sharepoint_refresh_token'] ?? '')) return null;
        if (($u['sharepoint_token_expires_at'] ?? 0) > time()) return Encryption::decrypt((string)($u['sharepoint_access_token'] ?? ''));
        [$tenantId, $clientId, $clientSecret] = self::resolveAppCreds();
        if (!$tenantId || !$clientId || !$clientSecret) return null;
        $tokens = self::refreshToken($tenantId, $clientId, $clientSecret, Encryption::decrypt((string)($u['sharepoint_refresh_token'] ?? '')));
        if (!$tokens) return null;
        Database::execute('UPDATE users SET sharepoint_access_token=?, sharepoint_refresh_token=?, sharepoint_token_expires_at=? WHERE id=?',
            [Encryption::encryptIfNeeded($tokens['access_token']), Encryption::encryptIfNeeded($tokens['refresh_token'] ?? $u['sharepoint_refresh_token']), time() + (int)($tokens['expires_in'] ?? 3600) - 60, Auth::id()]);
        return $tokens['access_token'];
    }

    private static function getAppToken(): ?string
    {
        [$tenantId, $clientId, $clientSecret] = self::resolveAppCreds();
        if (!$tenantId || !$clientId || !$clientSecret) return null;
        return self::fetchToken($tenantId, $clientId, $clientSecret, 'client_credentials');
    }

    private static function exchangeCode(string $tenantId, string $clientId, string $clientSecret, string $code, string $redirectUri): ?array
    {
        return self::tokenRequest($tenantId, ['grant_type'=>'authorization_code','client_id'=>$clientId,'client_secret'=>$clientSecret,'code'=>$code,'redirect_uri'=>$redirectUri]);
    }

    private static function refreshToken(string $tenantId, string $clientId, string $clientSecret, string $refreshToken): ?array
    {
        return self::tokenRequest($tenantId, ['grant_type'=>'refresh_token','client_id'=>$clientId,'client_secret'=>$clientSecret,'refresh_token'=>$refreshToken]);
    }

    private static function fetchToken(string $tenantId, string $clientId, string $clientSecret, string $grantType): ?string
    {
        $data = self::tokenRequest($tenantId, ['grant_type'=>$grantType,'client_id'=>$clientId,'client_secret'=>$clientSecret,'scope'=>'https://graph.microsoft.com/.default']);
        return $data['access_token'] ?? null;
    }

    private static function tokenRequest(string $tenantId, array $params): ?array
    {
        $ch = curl_init('https://login.microsoftonline.com/' . urlencode($tenantId) . '/oauth2/v2.0/token');
        curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>15]);
        $data = json_decode(curl_exec($ch), true); curl_close($ch);
        return isset($data['access_token']) ? $data : null;
    }

    private static function getSiteId(string $siteUrl, string $token): array
    {
        $parsed   = parse_url($siteUrl);
        $hostname = $parsed['host'] ?? '';
        $sitePath = self::encodePath(ltrim($parsed['path'] ?? '', '/'));
        $ch = curl_init('https://graph.microsoft.com/v1.0/sites/' . $hostname . ':/' . $sitePath);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token],CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = json_decode($resp, true);
        if (isset($data['id'])) return [$data['id'], null];
        return [null, $data['error']['message'] ?? "HTTP $httpCode"];
    }

    private static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
