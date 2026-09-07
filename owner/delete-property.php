<?php
require_once __DIR__ . '/_auth.php';
require_once dirname(__DIR__) . '/lib/property-store.php';
$owner = owner_require_login();
header('Cache-Control: no-store');

$ref = trim((string)($_REQUEST['reference_id'] ?? ''));
$record = li_load_property($ref);
if (!$record) { http_response_code(404); exit('Property not found'); }
$recordOwnerId = (string)($record['owner']['account_id'] ?? '');
$recordOwnerMobile = normalize_mobile((string)($record['owner']['mobile'] ?? ''));
$mine = ($recordOwnerId !== '' && hash_equals($recordOwnerId, (string)($owner['id'] ?? ''))) || ($recordOwnerId === '' && $recordOwnerMobile !== '' && hash_equals($recordOwnerMobile, normalize_mobile((string)($owner['mobile'] ?? ''))));
if (!$mine) { http_response_code(403); exit('Not allowed'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!owner_csrf_valid($_POST['csrf'] ?? null)) { http_response_code(403); exit('Invalid CSRF token'); }
    $confirm1 = (string)($_POST['confirm1'] ?? '');
    $confirm2 = (string)($_POST['confirm2'] ?? '');
    $confirm3 = strtoupper(trim((string)($_POST['confirm3'] ?? '')));
    $confirm4 = (string)($_POST['confirm4'] ?? '');
    if ($confirm1 !== 'yes' || $confirm2 !== 'yes' || $confirm3 !== 'DELETE' || $confirm4 !== $ref) {
        $error = 'All 4 confirmation layers are required.';
    } else {
        $dataFile = li_property_data_dir() . '/' . $ref . '.json';
        $photoDir = li_property_photo_dir($ref);
        if (is_dir($photoDir)) {
            foreach (glob($photoDir . '/*') ?: [] as $photo) if (is_file($photo)) @unlink($photo);
            @rmdir($photoDir);
        }
        if (is_file($dataFile) && @unlink($dataFile)) {
            header('Location: /owner/dashboard.php?deleted=1'); exit;
        }
        $error = 'Could not delete property. Please contact support.';
    }
}
$p = $record['property'] ?? [];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Delete Property | Leads India</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-950 text-white min-h-screen"><main class="max-w-xl mx-auto px-4 py-10"><a href="/owner/dashboard.php" class="text-emerald-400">← Back to My Properties</a><div class="mt-5 p-6 rounded-2xl bg-slate-900 border border-red-500/30"><h1 class="text-2xl font-black text-red-300">Delete Property</h1><p class="text-slate-400 mt-2">For safety, permanent deletion requires all 4 confirmation layers.</p><div class="mt-4 p-3 bg-white/5 rounded-xl"><b><?=htmlspecialchars($ref)?></b><br><span class="text-sm text-slate-400"><?=htmlspecialchars(($p['configuration']??'Property').' • '.($p['locality']??'').' • '.($p['city']??''))?></span></div><?php if($error): ?><div class="mt-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-200"><?=htmlspecialchars($error)?></div><?php endif; ?><form method="post" class="mt-5 space-y-4"><input type="hidden" name="csrf" value="<?=htmlspecialchars(owner_csrf_token())?>"><input type="hidden" name="reference_id" value="<?=htmlspecialchars($ref)?>"><label class="block p-3 border border-white/10 rounded-xl"><input required type="checkbox" name="confirm1" value="yes" class="mr-2">1. I selected the correct property.</label><label class="block p-3 border border-white/10 rounded-xl"><input required type="checkbox" name="confirm2" value="yes" class="mr-2">2. I understand this removes the listing and its uploaded photos.</label><label class="block"><span class="text-sm font-bold">3. Type DELETE</span><input required name="confirm3" autocomplete="off" class="mt-1 w-full bg-slate-950 border border-white/20 rounded-xl p-3" placeholder="DELETE"></label><label class="block"><span class="text-sm font-bold">4. Type Property ID: <?=htmlspecialchars($ref)?></span><input required name="confirm4" autocomplete="off" class="mt-1 w-full bg-slate-950 border border-white/20 rounded-xl p-3" placeholder="<?=htmlspecialchars($ref)?>"></label><button type="submit" class="w-full py-3 rounded-xl bg-red-600 hover:bg-red-500 font-black" onclick="return confirm('FINAL CONFIRMATION: Permanently delete this property?');">Permanently Delete Property</button></form></div></main></body></html>
