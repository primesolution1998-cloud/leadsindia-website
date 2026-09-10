<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/rainbow-ai.php';
rainbow_bootstrap();

if (rainbow_admin_logged_in()) {
    header('Location: /rainbow-ai/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if (!rainbow_verify_csrf($csrf)) {
        $error = 'Security token expired. Refresh and try again.';
    } elseif (!rainbow_admin_ready()) {
        $error = 'Rainbow AI admin security is not configured.';
    } elseif (!rainbow_rate_limit(5, 60)) {
        $error = 'Too many login attempts. Wait one minute and try again.';
    } else {
        $expectedUser = (string)(getenv('LEADSINDIA_ADMIN_USER') ?: '');
        $expectedHash = (string)(getenv('LEADSINDIA_ADMIN_PASSWORD_HASH') ?: '');
        if ($expectedUser !== '' && hash_equals($expectedUser, $user) && $expectedHash !== '' && password_verify($pass, $expectedHash)) {
            session_regenerate_id(true);
            $_SESSION['li_admin'] = $expectedUser;
            header('Location: /rainbow-ai/');
            exit;
        }
        $error = 'Invalid username or password.';
    }
}

$csrf = rainbow_csrf_token();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#07111f">
<title>Rainbow AI Login | LeadsIndia</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:radial-gradient(circle at 75% 0,#182548 0,transparent 34%),#07111f;color:#edf5ff;font-family:Inter,system-ui,sans-serif}.card{width:min(100%,420px);background:#0d1b2c;border:1px solid #24364c;border-radius:20px;padding:24px;box-shadow:0 25px 70px #0007}.brand{font-size:22px;font-weight:900}.spectrum{height:4px;border-radius:99px;margin:10px 0 24px;background:linear-gradient(90deg,#fb7185,#fbbf24,#34d399,#60a5fa,#a78bfa)}h1{font-size:24px;margin:0 0 8px}p{color:#91a3b7;margin:0 0 20px;line-height:1.5}.error{background:#2d1119;border:1px solid #71303d;color:#ffb2be;border-radius:10px;padding:10px 12px;margin-bottom:14px}label{display:block;font-size:13px;font-weight:800;margin:12px 0 7px}input{width:100%;border:1px solid #355276;background:#0a1625;color:#fff;border-radius:11px;padding:13px 14px;font-size:16px;outline:none}input:focus{border-color:#60a5fa}button{width:100%;margin-top:18px;border:0;border-radius:11px;padding:13px 16px;font-weight:900;color:#fff;background:linear-gradient(90deg,#2563eb,#7c3aed);cursor:pointer}.note{font-size:12px;color:#71869c;margin-top:14px;text-align:center}
</style>
</head>
<body>
<form class="card" method="post" action="/rainbow-ai/login.php" autocomplete="on">
<div class="brand">Rainbow AI</div><div class="spectrum"></div>
<h1>Secure Admin Login</h1>
<p>Sign in to unlock OpenAI verification and command planning.</p>
<?php if ($error !== ''): ?><div class="error"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8')?>">
<label for="user">Username</label><input id="user" name="user" required autocomplete="username" autofocus>
<label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password">
<button type="submit">Sign in to Rainbow AI</button>
<div class="note">Phase 1 only prepares plans. It does not publish, spend, send bulk messages, or modify CRM data.</div>
</form>
</body>
</html>
