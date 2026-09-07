<?php
require_once __DIR__ . '/_auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $mobile = normalize_mobile((string)($_POST['mobile'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($name === '' || !preg_match('/^[6-9][0-9]{9}$/', $mobile) || strlen($password) < 8) {
        $error = 'Name, valid 10-digit mobile and minimum 8-character password are required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (owner_load($mobile)) {
        $error = 'An account already exists with this mobile number.';
    } else {
        $account = [
            'id' => 'OWN-' . strtoupper(bin2hex(random_bytes(4))),
            'name' => mb_substr($name, 0, 100),
            'mobile' => $mobile,
            'email' => mb_substr($email, 0, 150),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'ACTIVE',
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];
        if (!owner_save($account)) {
            $error = 'Could not create account. Please try again.';
        } else {
            owner_login($account);
            header('Location: /owner/dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create Property Owner Account | Leads India</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-950 text-white min-h-screen flex items-center justify-center p-4"><div class="w-full max-w-md bg-slate-900 border border-white/10 rounded-3xl p-6"><h1 class="text-2xl font-black">Create Owner Account</h1><p class="text-slate-400 text-sm mt-1 mb-5">Create your login before listing a property.</p><?php if($error): ?><div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-sm"><?=htmlspecialchars($error)?></div><?php endif; ?><form method="post" class="space-y-4"><input required name="name" placeholder="Full name" class="w-full rounded-xl bg-slate-950 border border-white/10 px-4 py-3"><input required name="mobile" inputmode="numeric" maxlength="10" placeholder="10-digit mobile" class="w-full rounded-xl bg-slate-950 border border-white/10 px-4 py-3"><input name="email" type="email" placeholder="Email (optional)" class="w-full rounded-xl bg-slate-950 border border-white/10 px-4 py-3"><input required name="password" type="password" minlength="8" placeholder="Password (min 8 characters)" class="w-full rounded-xl bg-slate-950 border border-white/10 px-4 py-3"><button class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-500 py-3 font-black">Create Account</button></form><p class="text-sm text-slate-400 text-center mt-5">Already have an account? <a class="text-emerald-400 font-bold" href="/owner/login.php">Login</a></p></div></body></html>
