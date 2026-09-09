<?php
require_once __DIR__ . '/lib/property-store.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$ref = strtoupper(trim((string)($_GET['ref'] ?? '')));
$record = li_load_property($ref);
if (!$record || ($record['status'] ?? '') !== 'LIVE') { http_response_code(404); exit('Property not found'); }
$p = $record['property'] ?? [];
$photos = is_array($p['photos'] ?? null) ? $p['photos'] : [];
$title = trim((string)($p['configuration'] ?? 'Property').' for '.(($p['purpose'] ?? '') === 'Rent' ? 'Rent' : 'Sale').' in '.(string)($p['locality'] ?? '').', '.(string)($p['city'] ?? ''));
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> | Leads India</title>
<meta name="description" content="<?=e(mb_substr((string)($p['description'] ?? $title),0,155))?>">
<link rel="canonical" href="https://leadsindia.in/property/<?=rawurlencode($ref)?>">
<script src="https://cdn.tailwindcss.com"></script>
<style>.gallery-img{cursor:zoom-in}.no-scroll{overflow:hidden}</style>
</head>
<body class="bg-slate-50 text-slate-900">
<header class="bg-slate-950 text-white"><div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between"><a href="/" class="font-black text-xl">LEADS <span class="text-emerald-400">INDIA</span></a><a href="/properties" class="text-sm font-bold">← Back to Properties</a></div></header>
<main class="max-w-6xl mx-auto px-4 py-8">
<div class="grid lg:grid-cols-[1.5fr_.8fr] gap-7">
<section>
<?php if($photos): ?>
<div class="grid sm:grid-cols-2 gap-3">
<?php foreach(array_slice($photos,0,20) as $i=>$photo): ?>
<button type="button" class="gallery-img relative text-left" data-index="<?=($i)?>" aria-label="Open photo <?=($i+1)?>">
<img src="<?=e($photo)?>" alt="<?=e($title)?> photo <?=($i+1)?>" class="w-full h-64 object-cover rounded-2xl border border-slate-200 bg-white hover:opacity-95 transition">
<?php if($i===0): ?><span class="absolute bottom-3 left-3 rounded-lg bg-black/70 text-white text-xs font-bold px-3 py-1.5">View all <?=count($photos)?> photos</span><?php endif; ?>
</button>
<?php endforeach; ?>
</div>
<?php else: ?><div class="h-72 rounded-3xl bg-slate-200 flex items-center justify-center text-slate-500 text-5xl">🏠</div><?php endif; ?>
<div class="mt-6 bg-white border border-slate-200 rounded-3xl p-6">
<div class="flex flex-wrap gap-2 text-xs font-black"><span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700">LIVE</span><?php if(!empty($record['verification']['verified'])):?><span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700">VERIFIED</span><?php endif;?></div>
<h1 class="text-3xl font-black mt-4"><?=e($title)?></h1>
<div class="mt-4 grid sm:grid-cols-3 gap-3"><div class="p-4 bg-slate-50 rounded-2xl"><div class="text-xs text-slate-500">Price</div><div class="font-black text-lg">₹<?=e($p['price'] ?? 'On request')?></div></div><div class="p-4 bg-slate-50 rounded-2xl"><div class="text-xs text-slate-500">Area</div><div class="font-black text-lg"><?=e($p['area'] ?? '')?></div></div><div class="p-4 bg-slate-50 rounded-2xl"><div class="text-xs text-slate-500">Type</div><div class="font-black text-lg"><?=e($p['type'] ?? '')?></div></div></div>
<div class="mt-6"><h2 class="font-black text-xl">Property Details</h2><div class="mt-3 grid sm:grid-cols-2 gap-3 text-sm"><div><b>Configuration:</b> <?=e($p['configuration'] ?? '')?></div><div><b>Purpose:</b> <?=e($p['purpose'] ?? '')?></div><div><b>Project/Society:</b> <?=e($p['project_name'] ?? '')?></div><div><b>Location:</b> <?=e(($p['locality'] ?? '').', '.($p['city'] ?? ''))?></div></div></div>
<div class="mt-6"><h2 class="font-black text-xl">Description</h2><p class="mt-2 text-slate-600 leading-7"><?=nl2br(e($p['description'] ?? ''))?></p></div>
</div>
</section>
<aside class="lg:sticky lg:top-6 self-start">
<div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
<div class="text-xs text-slate-500">Listing ID</div><div class="font-mono font-bold mt-1"><?=e($ref)?></div>
<div class="mt-5 p-4 rounded-2xl bg-amber-50 border border-amber-200"><div class="font-black">Owner contact locked</div><p class="text-sm text-slate-600 mt-1">Property details and photos are free. Payment is required only to view the owner's mobile/contact details.</p></div>
<button id="contactBtn" class="mt-4 w-full rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black py-3">🔒 View Owner Contact</button>
<button id="shareBtn" class="mt-3 w-full rounded-xl bg-slate-950 text-white font-black py-3">Share Property</button>
<div id="shareMsg" class="text-xs text-emerald-600 mt-2 text-center min-h-4"></div>
</div>
</aside>
</div>
</main>

<div id="contactModal" class="hidden fixed inset-0 z-[100] bg-black/70 p-4 items-center justify-center">
  <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl relative">
    <button id="closeContact" class="absolute right-4 top-4 w-9 h-9 rounded-full bg-slate-100 font-bold">×</button>
    <div class="w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center text-2xl">🔒</div>
    <h2 class="text-2xl font-black mt-4">Payment required</h2>
    <p class="text-slate-600 mt-2">Owner phone number is protected. Buy contact access to unlock the direct owner details for this property.</p>
    <div class="mt-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 text-sm"><div class="text-slate-500">Property</div><div class="font-black mt-1"><?=e($title)?></div><div class="text-xs text-slate-500 mt-2">Reference: <?=e($ref)?></div></div>
    <button type="button" class="mt-5 w-full rounded-xl bg-emerald-600 text-white font-black py-3" disabled title="Secure payment activation pending">Continue to Payment</button>
    <p class="text-[11px] text-slate-500 text-center mt-2">No owner contact is revealed before successful payment verification.</p>
  </div>
</div>

<?php if($photos): ?>
<div id="galleryModal" class="hidden fixed inset-0 z-[110] bg-black/95 items-center justify-center p-3 sm:p-6">
  <button id="galleryClose" class="absolute right-4 top-4 z-20 w-11 h-11 rounded-full bg-white/15 text-white text-2xl">×</button>
  <button id="galleryPrev" class="absolute left-3 sm:left-6 z-20 w-11 h-11 rounded-full bg-white/15 text-white text-2xl">‹</button>
  <img id="galleryImage" src="" alt="Property photo" class="max-w-full max-h-[86vh] object-contain rounded-xl">
  <button id="galleryNext" class="absolute right-3 sm:right-6 z-20 w-11 h-11 rounded-full bg-white/15 text-white text-2xl">›</button>
  <div id="galleryCount" class="absolute bottom-5 left-1/2 -translate-x-1/2 bg-black/65 text-white text-sm font-bold px-4 py-2 rounded-full"></div>
</div>
<?php endif; ?>

<script>
const shareBtn=document.getElementById('shareBtn');
shareBtn.onclick=async()=>{const u=location.href,t=document.title;try{if(navigator.share){await navigator.share({title:t,url:u});}else{await navigator.clipboard.writeText(u);document.getElementById('shareMsg').textContent='Property link copied';}}catch(e){}};
const contactModal=document.getElementById('contactModal');
const contactBtn=document.getElementById('contactBtn');
const closeContact=document.getElementById('closeContact');
function openContact(){contactModal.classList.remove('hidden');contactModal.classList.add('flex');document.body.classList.add('no-scroll')}
function hideContact(){contactModal.classList.add('hidden');contactModal.classList.remove('flex');document.body.classList.remove('no-scroll')}
contactBtn.onclick=openContact;closeContact.onclick=hideContact;contactModal.addEventListener('click',e=>{if(e.target===contactModal)hideContact()});
<?php if($photos): ?>
const galleryPhotos=<?=json_encode(array_values($photos), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
let galleryIndex=0;
const galleryModal=document.getElementById('galleryModal'),galleryImage=document.getElementById('galleryImage'),galleryCount=document.getElementById('galleryCount');
function showGallery(i){galleryIndex=(i+galleryPhotos.length)%galleryPhotos.length;galleryImage.src=galleryPhotos[galleryIndex];galleryCount.textContent=(galleryIndex+1)+' / '+galleryPhotos.length;galleryModal.classList.remove('hidden');galleryModal.classList.add('flex');document.body.classList.add('no-scroll')}
function hideGallery(){galleryModal.classList.add('hidden');galleryModal.classList.remove('flex');document.body.classList.remove('no-scroll')}
document.querySelectorAll('.gallery-img').forEach(el=>el.onclick=()=>showGallery(Number(el.dataset.index||0)));
document.getElementById('galleryPrev').onclick=()=>showGallery(galleryIndex-1);document.getElementById('galleryNext').onclick=()=>showGallery(galleryIndex+1);document.getElementById('galleryClose').onclick=hideGallery;galleryModal.addEventListener('click',e=>{if(e.target===galleryModal)hideGallery()});
let touchX=0;galleryModal.addEventListener('touchstart',e=>{touchX=e.changedTouches[0].clientX},{passive:true});galleryModal.addEventListener('touchend',e=>{const d=e.changedTouches[0].clientX-touchX;if(Math.abs(d)>45)showGallery(galleryIndex+(d<0?1:-1))},{passive:true});
document.addEventListener('keydown',e=>{if(galleryModal.classList.contains('hidden'))return;if(e.key==='ArrowRight')showGallery(galleryIndex+1);if(e.key==='ArrowLeft')showGallery(galleryIndex-1);if(e.key==='Escape')hideGallery()});
<?php endif; ?>
</script>
</body>
</html>
