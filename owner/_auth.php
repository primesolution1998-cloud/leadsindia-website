<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function owner_private_root(): string {
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)), '/\\');
    $parent = dirname($docRoot);
    $dir = $parent . '/leadsindia-private';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = dirname(__DIR__) . '/storage-private';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
    }
    return $dir;
}

function owner_accounts_dir(): string {
    $dir = owner_private_root() . '/owner-accounts';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    return $dir;
}

function owner_legacy_accounts_dir(): string {
    return dirname(__DIR__) . '/storage/owner-accounts';
}

function normalize_mobile(string $mobile): string {
    return preg_replace('/\D+/', '', $mobile);
}

function owner_account_path(string $mobile): string {
    return owner_accounts_dir() . '/' . normalize_mobile($mobile) . '.json';
}

function owner_legacy_account_path(string $mobile): string {
    return owner_legacy_accounts_dir() . '/' . normalize_mobile($mobile) . '.json';
}

function owner_load(string $mobile): ?array {
    $mobile = normalize_mobile($mobile);
    $file = owner_account_path($mobile);
    if (!is_file($file)) {
        $legacy = owner_legacy_account_path($mobile);
        if (is_file($legacy)) {
            $legacyData = json_decode((string)file_get_contents($legacy), true);
            if (is_array($legacyData)) {
                owner_save($legacyData);
                $file = owner_account_path($mobile);
            }
        }
    }
    if (!is_file($file)) return null;
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function owner_save(array $account): bool {
    if (empty($account['mobile'])) return false;
    $dir = owner_accounts_dir();
    if (!is_dir($dir) || !is_writable($dir)) return false;
    $file = owner_account_path((string)$account['mobile']);
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    @chmod($tmp, 0640);
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    @chmod($file, 0640);
    return true;
}

function owner_login(array $account): void {
    session_regenerate_id(true);
    $_SESSION['owner_mobile'] = $account['mobile'];
    $_SESSION['owner_name'] = $account['name'];
    $_SESSION['owner_logged_in_at'] = time();
}

function owner_current(): ?array {
    if (empty($_SESSION['owner_mobile'])) return null;
    return owner_load((string)$_SESSION['owner_mobile']);
}

function owner_require_login(): array {
    $owner = owner_current();
    if (!$owner) {
        header('Location: /owner/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/list-property'));
        exit;
    }
    return $owner;
}

function owner_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function owner_csrf_token(): string {
    if (empty($_SESSION['owner_csrf'])) $_SESSION['owner_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['owner_csrf'];
}

function owner_csrf_valid(?string $token): bool {
    return is_string($token) && !empty($_SESSION['owner_csrf']) && hash_equals((string)$_SESSION['owner_csrf'], $token);
}
