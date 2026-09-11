<?php
$marketplaceHome = __DIR__ . '/home-marketplace.php';
if (is_file($marketplaceHome)) {
    ob_start();
    require $marketplaceHome;
    $html = (string) ob_get_clean();

    $navNeedle = '<a href="/loans">Home Loans</a>';
    $navReplacement = $navNeedle . '<a href="/rainbow-ai/">Rainbow AI</a>';
    if (strpos($html, '/rainbow-ai/') === false && strpos($html, $navNeedle) !== false) {
        $html = str_replace($navNeedle, $navReplacement, $html);
    }

    $rainbowBlock = '<section style="padding:48px 0;background:linear-gradient(135deg,#0b1220,#12233f);color:#fff"><div class="wrap"><div style="border:1px solid #334b6b;border-radius:22px;padding:30px;background:linear-gradient(135deg,#10243a,#172554)"><span class="eyebrow" style="background:#dcfce7;color:#166534">RAINBOW AI</span><h2 style="font-size:32px;margin:14px 0 10px">AI Business Command Center for Leads India</h2><p style="max-width:760px;color:#c5d4e8;line-height:1.65">Plan campaigns, inspect connected business systems and manage approval-first automation from one command center. OpenAI, Meta Ads and WhatsApp connectivity are integrated with safety controls.</p><a class="btn primary" style="margin-top:8px;background:#4f46e5;border-color:#4f46e5" href="/rainbow-ai/">Open Rainbow AI</a></div></div></section>';
    if (strpos($html, 'AI Business Command Center for Leads India') === false) {
        $html = str_replace('</main>', $rainbowBlock . '</main>', $html);
    }

    $rainbowPopup = <<<'HTML'
<style>
#rainbowAIPopup{position:fixed;right:22px;bottom:22px;z-index:9999;width:min(350px,calc(100vw - 28px));opacity:0;transform:translateY(24px) scale(.96);pointer-events:none;transition:opacity .45s ease,transform .45s cubic-bezier(.2,.8,.2,1);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
#rainbowAIPopup.show{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}
.rainbow-pop-card{position:relative;overflow:hidden;cursor:pointer;border-radius:22px;padding:1px;background:linear-gradient(120deg,#22d3ee,#6366f1,#a855f7,#22c55e,#22d3ee);background-size:300% 300%;animation:rainbowBorder 5s linear infinite;box-shadow:0 20px 60px rgba(15,23,42,.34),0 0 0 1px rgba(255,255,255,.15)}
.rainbow-pop-inner{position:relative;border-radius:21px;padding:18px 18px 17px;background:linear-gradient(145deg,rgba(7,17,31,.98),rgba(20,31,65,.98));color:#fff;overflow:hidden}
.rainbow-pop-glow{position:absolute;width:140px;height:140px;border-radius:50%;right:-45px;top:-55px;background:radial-gradient(circle,rgba(96,165,250,.5),rgba(168,85,247,.18) 45%,transparent 72%);animation:rainbowGlow 2.8s ease-in-out infinite}
.rainbow-pop-top{display:flex;align-items:center;gap:9px;margin-bottom:10px}.rainbow-live{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;background:rgba(16,185,129,.14);border:1px solid rgba(52,211,153,.3);font-size:10px;font-weight:900;letter-spacing:.08em;color:#86efac}.rainbow-live i{width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.7);animation:rainbowPulse 1.6s infinite}.rainbow-mini{font-size:11px;color:#a7b8cf;font-weight:700}.rainbow-pop-title{font-size:21px;line-height:1.12;font-weight:900;margin:0 32px 7px 0;letter-spacing:-.3px}.rainbow-pop-text{font-size:12.5px;line-height:1.5;color:#c6d3e5;margin:0 0 13px}.rainbow-pop-cta{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:900;color:#fff;background:linear-gradient(90deg,#2563eb,#7c3aed);padding:9px 13px;border-radius:10px;box-shadow:0 8px 24px rgba(79,70,229,.28)}.rainbow-pop-arrow{transition:transform .2s ease}.rainbow-pop-card:hover .rainbow-pop-arrow{transform:translateX(4px)}.rainbow-pop-card:hover{transform:translateY(-2px)}.rainbow-close{position:absolute;right:10px;top:9px;z-index:3;width:28px;height:28px;border-radius:50%;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.08);color:#dbeafe;display:grid;place-items:center;font-size:16px;cursor:pointer}.rainbow-close:hover{background:rgba(255,255,255,.16)}
@keyframes rainbowBorder{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}@keyframes rainbowGlow{0%,100%{transform:scale(.9);opacity:.7}50%{transform:scale(1.12);opacity:1}}@keyframes rainbowPulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.7)}70%{box-shadow:0 0 0 8px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
@media(max-width:620px){#rainbowAIPopup{right:14px;bottom:76px;width:calc(100vw - 28px)}.rainbow-pop-inner{padding:15px}.rainbow-pop-title{font-size:19px}.rainbow-pop-text{font-size:12px}}
@media(prefers-reduced-motion:reduce){#rainbowAIPopup,.rainbow-pop-card,.rainbow-pop-glow,.rainbow-live i{animation:none!important;transition:none!important}}
</style>
<div id="rainbowAIPopup" aria-label="Open Rainbow AI">
  <div class="rainbow-pop-card" id="rainbowAICard" role="link" tabindex="0">
    <div class="rainbow-pop-inner">
      <div class="rainbow-pop-glow"></div>
      <button class="rainbow-close" id="rainbowAIClose" type="button" aria-label="Close Rainbow AI popup">×</button>
      <div class="rainbow-pop-top"><span class="rainbow-live"><i></i> AI CONNECTED</span><span class="rainbow-mini">Leads India Command Center</span></div>
      <h3 class="rainbow-pop-title">Meet Rainbow AI</h3>
      <p class="rainbow-pop-text">Open your AI business command center for campaigns, Meta, WhatsApp and approval-first automation.</p>
      <span class="rainbow-pop-cta">Open Rainbow AI <span class="rainbow-pop-arrow">→</span></span>
    </div>
  </div>
</div>
<script>
(function(){
  var popup=document.getElementById('rainbowAIPopup');
  var card=document.getElementById('rainbowAICard');
  var close=document.getElementById('rainbowAIClose');
  if(!popup||!card||!close)return;
  if(sessionStorage.getItem('rainbow_ai_popup_closed')!=='1')setTimeout(function(){popup.classList.add('show')},900);
  function openRainbow(){window.location.href='/rainbow-ai/'}
  card.addEventListener('click',openRainbow);
  card.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();openRainbow()}});
  close.addEventListener('click',function(e){e.stopPropagation();popup.classList.remove('show');sessionStorage.setItem('rainbow_ai_popup_closed','1')});
})();
</script>
HTML;
    if (strpos($html, 'id="rainbowAIPopup"') === false) {
        $html = str_replace('</body>', $rainbowPopup . '</body>', $html);
    }

    echo $html;
    exit;
}

// Safe fallback: preserve the previous homepage if the new marketplace shell is unavailable.
$home = __DIR__ . '/index.html';
if (!is_file($home)) {
    http_response_code(500);
    echo 'Homepage unavailable.';
    exit;
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($home);
