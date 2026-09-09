<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Properties | Leads India</title>
<meta name="description" content="Search live properties for sale and rent across India. Explore residential, commercial, plots and projects, or list your property free on Leads India.">
<link rel="canonical" href="https://leadsindia.in/properties">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{ink:'#07111f',panel:'#0d1726',brand:'#10b981',blue:'#2563eb'}}}}
</script>
<style>
*{box-sizing:border-box}body{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hide-scroll::-webkit-scrollbar{display:none}.hide-scroll{scrollbar-width:none}.glass{backdrop-filter:blur(14px)}
</style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
<header class="sticky top-0 z-50 bg-ink/95 glass border-b border-white/10 text-white">
  <div class="max-w-7xl mx-auto px-4 lg:px-6 h-16 flex items-center justify-between gap-4">
    <a href="/" class="flex items-center gap-3 min-w-0"><span class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-400 text-slate-950 font-black flex items-center justify-center">LI</span><span class="font-black tracking-tight">Leads India <span class="hidden sm:inline text-xs text-emerald-400 font-bold">Properties</span></span></a>
    <nav class="hidden lg:flex items-center gap-6 text-sm text-slate-300"><a href="#search" class="hover:text-white">Buy</a><a href="#search" onclick="setMode('Rent')" class="hover:text-white">Rent</a><a href="#projects" class="hover:text-white">New Launch</a><a href="#search" onclick="setMode('Commercial')" class="hover:text-white">Commercial</a><a href="#search" onclick="setMode('Plot')" class="hover:text-white">Plots/Land</a><a href="#projects" class="hover:text-white">Projects</a></nav>
    <div class="flex items-center gap-2"><a href="/owner/login.php" class="hidden sm:inline-flex px-3 py-2 rounded-xl border border-white/15 text-xs font-bold">Owner Login</a><a href="/list-property" class="inline-flex px-3 sm:px-4 py-2 rounded-xl bg-white text-slate-950 text-xs font-black">Post Property <span class="ml-1 text-emerald-600">FREE</span></a></div>
  </div>
</header>

<section class="relative bg-ink text-white overflow-hidden">
  <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(16,185,129,.18),transparent_34%),radial-gradient(circle_at_80%_10%,rgba(37,99,235,.20),transparent_34%)]"></div>
  <div class="relative max-w-7xl mx-auto px-4 lg:px-6 pt-10 pb-16 lg:pt-16 lg:pb-20">
    <div class="max-w-3xl"><span class="inline-flex px-3 py-1 rounded-full border border-emerald-400/30 bg-emerald-400/10 text-emerald-300 text-xs font-black">LIVE PROPERTY MARKETPLACE</span><h1 class="mt-4 text-3xl sm:text-5xl font-black tracking-tight">Find the right property, faster.</h1><p class="mt-3 text-slate-300 max-w-2xl">Buy, rent, explore commercial spaces, plots and projects. Search live listings published through Leads India.</p></div>

    <div id="search" class="mt-8 bg-white rounded-3xl shadow-2xl text-slate-900 p-3 sm:p-4 max-w-6xl">
      <div id="modeTabs" class="flex overflow-x-auto hide-scroll border-b border-slate-200 gap-1 text-sm font-bold">
        <button data-mode="Buy" class="mode-tab px-4 py-3 border-b-2 border-blue-600 text-blue-700">Buy</button>
        <button data-mode="Rent" class="mode-tab px-4 py-3 border-b-2 border-transparent text-slate-500">Rent</button>
        <button data-mode="New Launch" class="mode-tab px-4 py-3 border-b-2 border-transparent text-slate-500">New Launch</button>
        <button data-mode="Commercial" class="mode-tab px-4 py-3 border-b-2 border-transparent text-slate-500">Commercial</button>
        <button data-mode="Plot" class="mode-tab px-4 py-3 border-b-2 border-transparent text-slate-500">Plots/Land</button>
        <button data-mode="Projects" class="mode-tab px-4 py-3 border-b-2 border-transparent text-slate-500">Projects</button>
      </div>
      <div class="grid md:grid-cols-[180px_1fr_auto_auto] gap-2 pt-3">
        <select id="typeFilter" class="rounded-xl border border-slate-200 px-4 py-3 bg-white text-sm"><option value="">All Property Types</option><option>Apartment / Flat</option><option>Independent House</option><option>Commercial Office</option><option>Shop / Showroom</option><option>Warehouse</option><option>Plot / Land</option></select>
        <div class="relative"><input id="q" class="w-full rounded-xl border border-slate-200 px-11 py-3 text-sm outline-none focus:border-blue-500" placeholder="Search city, locality, project or BHK"><span class="absolute left-4 top-3 text-slate-400">⌕</span></div>
        <button id="voiceBtn" title="Voice search" class="rounded-xl border border-slate-200 px-4 py-3 text-lg">🎙️</button>
        <button id="searchBtn" class="rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-black px-6 py-3">Search</button>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-2 mt-3">
        <select id="cityFilter" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">All Cities</option></select>
        <select id="bhkFilter" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">Any BHK</option><option>1 BHK</option><option>2 BHK</option><option>3 BHK</option><option>4 BHK</option></select>
        <select id="budgetFilter" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">Any Budget</option><option value="5000000">Up to ₹50 Lakh</option><option value="10000000">Up to ₹1 Crore</option><option value="20000000">Up to ₹2 Crore</option><option value="999999999">₹2 Crore+</option></select>
        <select id="sortFilter" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="recent">Newest First</option><option value="low">Price Low to High</option><option value="high">Price High to Low</option></select>
        <button id="clearBtn" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold">Clear Filters</button>
      </div>
    </div>
  </div>
</section>

<main class="max-w-7xl mx-auto px-4 lg:px-6 py-10">
  <div class="grid lg:grid-cols-[1fr_310px] gap-8 items-start">
    <section>
      <div class="flex items-end justify-between gap-4 mb-5"><div><p class="text-sm text-slate-500">Continue browsing</p><h2 class="text-2xl font-black mt-1">Recommended Properties</h2></div><div class="text-sm text-slate-500"><span id="resultCount">0</span> results</div></div>
      <div id="loading" class="grid md:grid-cols-2 xl:grid-cols-3 gap-5"><div class="h-80 rounded-3xl bg-white border border-slate-200 animate-pulse"></div><div class="h-80 rounded-3xl bg-white border border-slate-200 animate-pulse"></div><div class="h-80 rounded-3xl bg-white border border-slate-200 animate-pulse"></div></div>
      <div id="grid" class="hidden grid md:grid-cols-2 xl:grid-cols-3 gap-5"></div>
      <div id="empty" class="hidden rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center"><div class="text-4xl">🏙️</div><h3 class="font-black text-xl mt-3">No matching live properties yet</h3><p class="text-slate-500 text-sm mt-2">Try another city/filter or check again after new listings are added.</p><a href="/list-property" class="inline-flex mt-5 px-5 py-3 rounded-xl bg-emerald-600 text-white font-black">List Property Free</a></div>
    </section>

    <aside class="space-y-5 lg:sticky lg:top-24">
      <div class="rounded-3xl bg-white border border-slate-200 p-5 shadow-sm"><div class="flex items-center justify-between"><div><p class="text-xs text-slate-500">Your activity</p><h3 class="font-black">Guest User</h3></div><span class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center">👤</span></div><div class="mt-4 grid grid-cols-2 gap-2 text-center"><div class="rounded-xl bg-slate-50 p-3"><div id="favCount" class="font-black text-xl">0</div><div class="text-xs text-slate-500">Saved</div></div><div class="rounded-xl bg-slate-50 p-3"><div id="recentCount" class="font-black text-xl">0</div><div class="text-xs text-slate-500">Viewed</div></div></div><a href="/owner/login.php" class="mt-4 block text-center rounded-xl bg-blue-600 text-white font-black py-3">LOGIN / REGISTER</a></div>
      <div class="rounded-3xl bg-gradient-to-br from-emerald-600 to-teal-500 text-white p-5 shadow-lg"><span class="text-xs font-black uppercase">Property Owner?</span><h3 class="text-2xl font-black mt-2">Sell or rent faster</h3><p class="text-sm text-emerald-50 mt-2">Create an owner account, upload photos and publish your property live instantly.</p><a href="/list-property" class="mt-4 block text-center rounded-xl bg-white text-emerald-700 font-black py-3">FREE List Your Property</a></div>
    </aside>
  </div>

  <section id="projects" class="mt-14"><div class="flex items-end justify-between"><div><span class="text-xs font-black text-blue-600 uppercase">Explore</span><h2 class="text-2xl font-black mt-1">Projects & New Launches</h2></div></div><div id="projectGrid" class="mt-5 grid md:grid-cols-2 lg:grid-cols-3 gap-5"></div></section>

  <section class="mt-14 grid lg:grid-cols-2 gap-6">
    <div class="rounded-3xl bg-white border border-slate-200 p-6"><span class="text-xs font-black text-emerald-600 uppercase">Price Insights</span><h2 class="text-2xl font-black mt-2">Locality price snapshot</h2><p class="text-slate-500 text-sm mt-2">Calculated from currently live Leads India listings. It updates automatically as inventory grows.</p><div id="insights" class="mt-5 space-y-3"></div></div>
    <div class="rounded-3xl bg-ink text-white p-6"><span class="text-xs font-black text-cyan-400 uppercase">Buyer Tools</span><h2 class="text-2xl font-black mt-2">Shortlist smarter</h2><div class="mt-5 grid sm:grid-cols-2 gap-3 text-sm"><div class="rounded-2xl bg-white/5 border border-white/10 p-4"><div class="text-xl">♡</div><div class="font-bold mt-2">Save favourites</div><p class="text-slate-400 text-xs mt-1">Shortlist properties in your browser.</p></div><div class="rounded-2xl bg-white/5 border border-white/10 p-4"><div class="text-xl">🕘</div><div class="font-bold mt-2">Recent activity</div><p class="text-slate-400 text-xs mt-1">Keep track of properties you viewed.</p></div><div class="rounded-2xl bg-white/5 border border-white/10 p-4"><div class="text-xl">🎙️</div><div class="font-bold mt-2">Voice search</div><p class="text-slate-400 text-xs mt-1">Search by voice on supported browsers.</p></div><div class="rounded-2xl bg-white/5 border border-white/10 p-4"><div class="text-xl">🔒</div><div class="font-bold mt-2">Protected contact</div><p class="text-slate-400 text-xs mt-1">Property details are free; owner contact requires access.</p></div></div></div>
  </section>
</main>

<footer class="mt-10 bg-ink text-slate-400 border-t border-white/10"><div class="max-w-7xl mx-auto px-4 lg:px-6 py-8 flex flex-col sm:flex-row gap-4 justify-between text-sm"><div><div class="text-white font-black">Leads India Properties</div><div class="text-xs mt-1">Direct owner property marketplace • India</div></div><div class="flex flex-wrap gap-4"><a href="/">Home</a><a href="/properties">Properties</a><a href="/search-property">Search</a><a href="/list-property">List Property Free</a></div></div></footer>

<script>
let all=[],mode='Buy';
const $=id=>document.getElementById(id);
const fav=new Set(JSON.parse(localStorage.getItem('li_property_favs')||'[]'));
let recent=JSON.parse(localStorage.getItem('li_property_recent')||'[]');
function numberPrice(v){return Number(String(v||'').replace(/[^0-9]/g,''))||0}
function money(v){const n=numberPrice(v);if(!n)return v||'Price on request';if(n>=10000000)return '₹ '+(n/10000000).toFixed(n%10000000?2:0)+' Cr';if(n>=100000)return '₹ '+(n/100000).toFixed(n%100000?1:0)+' L';return '₹ '+n.toLocaleString('en-IN')}
function modeMatch(p){if(mode==='Rent')return p.purpose==='Rent';if(mode==='Buy')return p.purpose==='Sale';if(mode==='Commercial')return /commercial|office|shop|warehouse/i.test(p.type||'');if(mode==='Plot')return /plot|land/i.test(p.type||'');if(mode==='New Launch'||mode==='Projects')return !!p.project_name;return true}
function setMode(m){mode=m;document.querySelectorAll('.mode-tab').forEach(b=>{const on=b.dataset.mode===m;b.classList.toggle('border-blue-600',on);b.classList.toggle('text-blue-700',on);b.classList.toggle('border-transparent',!on);b.classList.toggle('text-slate-500',!on)});render();document.querySelector('#search')?.scrollIntoView({behavior:'smooth',block:'center'})}
window.setMode=setMode;
document.querySelectorAll('.mode-tab').forEach(b=>b.onclick=()=>setMode(b.dataset.mode));
function filtered(){const q=$('q').value.trim().toLowerCase(),city=$('cityFilter').value,bhk=$('bhkFilter').value,type=$('typeFilter').value,budget=Number($('budgetFilter').value||0);let x=all.filter(p=>{const hay=[p.city,p.locality,p.project_name,p.configuration,p.type,p.description].join(' ').toLowerCase();if(!modeMatch(p))return false;if(q&&!hay.includes(q))return false;if(city&&p.city!==city)return false;if(bhk&&!String(p.configuration||'').includes(bhk))return false;if(type&&p.type!==type)return false;if(budget&&numberPrice(p.price)>budget&&budget!==999999999)return false;if(budget===999999999&&numberPrice(p.price)<=20000000)return false;return true});const sort=$('sortFilter').value;if(sort==='low')x.sort((a,b)=>numberPrice(a.price)-numberPrice(b.price));else if(sort==='high')x.sort((a,b)=>numberPrice(b.price)-numberPrice(a.price));else x.sort((a,b)=>String(b.published_at||'').localeCompare(String(a.published_at||'')));return x}
function card(p){const id=p.reference_id,img=(p.photos&&p.photos[0])||'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=900&auto=format&fit=crop&q=80',saved=fav.has(id),title=p.project_name||`${p.configuration||p.type||'Property'} in ${p.locality||p.city||'India'}`,verified=!!p.verified;return `<article class="rounded-3xl bg-white border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg transition"><a href="/property/${encodeURIComponent(id)}" onclick="rememberViewed('${id}')" class="block relative h-48 bg-slate-100"><img src="${img}" alt="${title.replace(/"/g,'')}" class="w-full h-full object-cover" loading="lazy">${verified?'<span class="absolute top-3 left-3 rounded-full bg-emerald-600 text-white text-[10px] font-black px-2.5 py-1">✓ VERIFIED</span>':'<span class="absolute top-3 left-3 rounded-full bg-slate-900/85 text-white text-[10px] font-black px-2.5 py-1">LIVE</span>'}</a><button onclick="event.preventDefault();event.stopPropagation();toggleFav('${id}')" class="absolute mt-[-180px] ml-[calc(100%-48px)] w-9 h-9 rounded-full bg-white/95 shadow font-black text-lg">${saved?'♥':'♡'}</button><div class="p-5"><div class="text-xs text-slate-500">📍 ${p.locality||''}${p.locality&&p.city?', ':''}${p.city||''}</div><h3 class="font-black mt-1 line-clamp-2">${title}</h3><div class="mt-3 flex items-end justify-between gap-3"><div><div class="text-xl font-black">${money(p.price)}</div><div class="text-xs text-slate-500 mt-1">${p.configuration||p.type||''} ${p.area?'• '+p.area:''}</div></div><span class="text-xs font-bold text-blue-600">${p.purpose||''}</span></div><a href="/property/${encodeURIComponent(id)}" onclick="rememberViewed('${id}')" class="mt-4 block w-full rounded-xl bg-slate-950 text-white py-2.5 text-sm font-black text-center">View Property</a></div></article>`}
function render(){const x=filtered();$('resultCount').textContent=x.length;$('grid').innerHTML=x.map(card).join('');$('grid').classList.toggle('hidden',!x.length);$('empty').classList.toggle('hidden',!!x.length);$('loading').classList.add('hidden');renderProjects();renderInsights();updateActivity()}
function toggleFav(id){fav.has(id)?fav.delete(id):fav.add(id);localStorage.setItem('li_property_favs',JSON.stringify([...fav]));render()}
window.toggleFav=toggleFav;
function rememberViewed(id){recent=[id,...recent.filter(x=>x!==id)].slice(0,20);localStorage.setItem('li_property_recent',JSON.stringify(recent));updateActivity()}
window.rememberViewed=rememberViewed;
function viewProperty(id){rememberViewed(id);window.location.href='/property/'+encodeURIComponent(id)}
window.viewProperty=viewProperty;
function updateActivity(){$('favCount').textContent=fav.size;$('recentCount').textContent=recent.length}
function renderProjects(){const projects=all.filter(p=>p.project_name).slice(0,6);$('projectGrid').innerHTML=projects.length?projects.map(p=>`<div class="rounded-3xl bg-white border border-slate-200 p-5"><div class="flex items-center justify-between"><span class="text-[10px] font-black px-2 py-1 rounded bg-blue-50 text-blue-700">PROJECT</span>${p.verified?'<span class="text-[10px] font-black text-emerald-600">VERIFIED</span>':''}</div><h3 class="font-black text-lg mt-3">${p.project_name}</h3><p class="text-sm text-slate-500 mt-1">${p.locality||''}, ${p.city||''}</p><div class="mt-4 font-black">${money(p.price)}</div><div class="text-xs text-slate-500 mt-1">${p.configuration||p.type||''}</div></div>`).join(''):`<div class="md:col-span-2 lg:col-span-3 rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">Projects will appear here as inventory grows.</div>`}
function renderInsights(){const groups={};all.forEach(p=>{const k=p.locality||p.city;if(!k)return;(groups[k]??=[]).push(numberPrice(p.price))});const rows=Object.entries(groups).filter(([,a])=>a.some(Boolean)).slice(0,5);$('insights').innerHTML=rows.length?rows.map(([k,a])=>{const vals=a.filter(Boolean).sort((x,y)=>x-y),med=vals[Math.floor(vals.length/2)]||0;return `<div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span class="font-bold text-sm">${k}</span><span class="text-sm font-black">Median ${money(med)}</span></div>`}).join(''):'<div class="text-sm text-slate-500">More live listings are needed before locality price insights can be calculated.</div>'}
['q','cityFilter','bhkFilter','budgetFilter','typeFilter','sortFilter'].forEach(id=>$(id).addEventListener(id==='q'?'input':'change',render));$('searchBtn').onclick=render;$('clearBtn').onclick=()=>{$('q').value='';$('cityFilter').value='';$('bhkFilter').value='';$('budgetFilter').value='';$('typeFilter').value='';$('sortFilter').value='recent';setMode('Buy')};
$('voiceBtn').onclick=()=>{const SR=window.SpeechRecognition||window.webkitSpeechRecognition;if(!SR){alert('Voice search is not supported in this browser.');return}const r=new SR();r.lang='en-IN';r.onresult=e=>{$('q').value=e.results[0][0].transcript;render()};r.start()};
fetch('/api/properties-public.php',{headers:{Accept:'application/json'}}).then(r=>r.json()).then(d=>{all=Array.isArray(d.properties)?d.properties:[];const cities=[...new Set(all.map(p=>p.city).filter(Boolean))].sort();$('cityFilter').innerHTML='<option value="">All Cities</option>'+cities.map(c=>`<option>${c}</option>`).join('');render()}).catch(()=>{all=[];render()});
updateActivity();
</script>
</body></html>