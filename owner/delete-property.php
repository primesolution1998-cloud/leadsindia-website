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
$mine = ($recordOwnerId !== '' && hash_equals($recordOwnerId, (string)($owner['id'] ?? '')))
    || ($recordOwnerId === '' && $recordOwnerMobile !== '' && hash_equals($recordOwnerMobile, normalize_mobile((string)($owner['mobile'] ?? ''))));
if (!$mine) { http_response_code(403); exit('Not allowed'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!owner_csrf_valid($_POST['csrf'] ?? null)) { http_response_code(403); exit('Invalid CSRF token'); }

    // The UI already identifies the exact property and asks for a final browser
    // confirmation. Keep deletion safe without forcing four fragile form fields.
    $postedRef = trim((string)($_POST['reference_id'] ?? ''));
    $confirmed = (string)($_POST['confirm_delete'] ?? '');
    if ($postedRef === '' || !hash_equals($ref, $postedRef) || $confirmed !== 'yes') {
        $error = 'Please confirm property deletion.';
    } else {
        $from = (string)($record['status'] ?? 'PENDING_VERIFICATION');
        $now = gmdate('c');
        $record['status'] = 'OWNER_DELETED';
        $record['updated_at'] = $now;
        $record['owner_deleted_at'] = $now;
        $record['owner_deleted_by'] = (string)($owner['id'] ?? $owner['mobile'] ?? 'OWNER');
        if (!empty($record['published_at'])) $record['unpublished_at'] = $now;
        li_audit($record, $from, 'OWNER_DELETED', 'OWNER', 'Hidden from owner dashboard and public listing; backend record retained.');

        if (li_save_property($record)) {
            header('Location: /owner/dashboard.php?deleted=1');
            exit;
        }
        $error = 'Could not update property. Please contact support.';
    }
}
$p = $record['property'] ?? [];
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Delete Property | Leads India</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 text-white min-h-screen">
<main class="max-w-xl mx-auto px-4 py-10">
<a href="/owner/dashboard.php" class="text-emerald-400">← Back to My Properties</a>
<div class="mt-5 p-6 rounded-2xl bg-slate-900 border border-red-500/30">
<h1 class="text-2xl font-black text-red-300">Delete Property</h1>
<p class="text-slate-400 mt-2">This removes the property from your dashboard and public listing. Backend data and photos are retained securely for records.</p>
<div class="mt-4 p-3 bg-white/5 rounded-xl"><b><?=htmlspecialchars($ref)?></b><br><span class="text-sm text-slate-400"><?=htmlspecialchars(($p['configuration']??'Property').' • '.($p['locality']??'').' • '.($p['city']??''))?></span></div>
<?php if($error): ?><div class="mt-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-200"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post" class="mt-5" onsubmit="return confirm('Delete this property from your dashboard and public listing?');">
<input type="hidden" name="csrf" value="<?=htmlspecialchars(owner_csrf_token())?>">
<input type="hidden" name="reference_id" value="<?=htmlspecialchars($ref)?>">
<input type="hidden" name="confirm_delete" value="yes">
<button type="submit" class="w-full py-3 rounded-xl bg-red-600 hover:bg-red-500 font-black">Delete Property</button>
</form>
</div>
</main>
</body></html>
