<?php
require_once __DIR__ . '/_auth.php';
$error='';
$next=(string)($_GET['next'] ?? $_POST['next'] ?? '/owner/dashboard.php');
if (!str_starts_with($next,'/')) $next='/owner/dashboard.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $mobile=normalize_mobile((string)($_POST['mobile']??''));
  $password=(string)($_POST['password']??'');
  $account=owner_load($mobile);
  if (!$account || !password_verify($password,(string)($account['password_hash']??'')) || ($account['status']??'')!=='ACTIVE') {
    usleep(250000); $error='Invalid mobile number or password.';
  } else { owner_login($account); header('Location: '.$next); exit; }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Property Owner Login | Leads India</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-950 text-white min-h-screen flex items-center justify-center p-4"><div class="w-full max-w-md bg-slate-900 border border-white/10 rounded-3xl p-6"><h1 class="text-2xl font-black">Property Owner Login</h1><p class="text-slate-400 text-sm mt-1 mb-5">Manage your property submissions and status.</p><?php if($error): ?><div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-sm"><?=htmlspecialchars($error)?></div><?php endif; ?><form method="post" class="space-y-4"><input type="hidden" name="next" value="<?=htmlspecialchars($next)?>"><input required name="mobile" inputmode="numeric" maxlength="10" placeholder="10-digit mobile" class="w-full rounded-xl bg-slate-950 border border-white/10 px-4 py-3"><input required name="password" type="password" placeholder="Password" class="w-full rounded-xl bg-slate-950 border border-white/10 px-4 py-3"><button class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-500 py-3 font-black">Login</button></form><p class="text-sm text-slate-400 text-center mt-5">New owner? <a class="text-emerald-400 font-bold" href="/owner/register.php">Create Account</a></p></div></body></html>
