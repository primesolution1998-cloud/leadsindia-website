<?php

declare(strict_types=1);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

function li_admin_user(): string { return (string)(getenv('LEADSINDIA_ADMIN_USER') ?: ''); }
function li_admin_hash(): string { return (string)(getenv('LEADSINDIA_ADMIN_PASSWORD_HASH') ?: ''); }
function li_admin_ready(): bool { return li_admin_user() !== '' && li_admin_hash() !== ''; }
function li_admin_logged_in(): bool { return isset($_SESSION['li_admin']) && hash_equals(li_admin_user(), (string)$_SESSION['li_admin']); }
function li_csrf(): string {
    if (empty($_SESSION['li_csrf'])) $_SESSION['li_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['li_csrf'];
}
function li_require_admin(): void {
    if (!li_admin_ready()) {
        http_response_code(503);
        exit('Admin panel is not configured. Set LEADSINDIA_ADMIN_USER and LEADSINDIA_ADMIN_PASSWORD_HASH on the server.');
    }
    if (!li_admin_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}
