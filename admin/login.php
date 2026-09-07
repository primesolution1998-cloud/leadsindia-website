<?php
require __DIR__ . '/_auth.php';
if (!li_admin_ready()) { http_response_code(503); exit('Admin panel is not configured.'); }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $user=trim((string)($_POST['user']??''));
    $pass=(string)($_POST['password']??'');
    if (hash_equals(li_admin_user(),$user) && password_verify($pass,li_admin_hash())) {
        session_regenerate_id(true);
        $_SESSION['li_admin']=$user;
        header('Location: /admin/properties.php'); exit;
    }
    $error='Invalid credentials.';
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LeadsIndia Property Admin</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 text-white grid place-items-center p-4"><form method="post" class="w-full max-w-md bg-slate-900 border border-white/10 rounded-2xl p-6 space-y-4"><h1 class="text-2xl font-black">Property Admin</h1><?php if($error):?><div class="text-red-300 text-sm"><?=htmlspecialchars($error)?></div><?php endif;?><input name="user" required placeholder="Username" class="w-full bg-slate-950 border border-white/10 rounded-xl p-3"><input type="password" name="password" required placeholder="Password" class="w-full bg-slate-950 border border-white/10 rounded-xl p-3"><button class="w-full bg-emerald-600 hover:bg-emerald-500 rounded-xl p-3 font-bold">Sign in</button></form></body></html>