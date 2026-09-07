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

function owner_accounts_dir(): string {
    $dir = dirname(__DIR__) . '/storage/owner-accounts';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    return $dir;
}

function normalize_mobile(string $mobile): string {
    return preg_replace('/\D+/', '', $mobile);
}

function owner_account_path(string $mobile): string {
    return owner_accounts_dir() . '/' . normalize_mobile($mobile) . '.json';
}

function owner_load(string $mobile): ?array {
    $file = owner_account_path($mobile);
    if (!is_file($file)) return null;
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function owner_save(array $account): bool {
    $file = owner_account_path((string)$account['mobile']);
    $json = json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ok = file_put_contents($file, $json, LOCK_EX) !== false;
    if ($ok) @chmod($file, 0640);
    return $ok;
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
