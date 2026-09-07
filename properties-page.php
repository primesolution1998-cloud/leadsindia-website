<?php
ob_start();
require __DIR__ . '/properties.php';
$html = ob_get_clean();

$widget = <<<'HTML'
<style>
@keyframes liWaPulse{0%,100%{transform:scale(1);box-shadow:0 12px 30px rgba(37,211,102,.38),0 0 0 0 rgba(37,211,102,.28)}50%{transform:scale(1.06);box-shadow:0 16px 36px rgba(37,211,102,.5),0 0 0 14px rgba(37,211,102,0)}}
@keyframes liWaBob{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
.li-wa-wrap{position:fixed;right:18px;bottom:20px;z-index:9999;display:flex;align-items:center;gap:10px;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.li-wa-label{background:#0f172a;color:#fff;border:1px solid rgba(255,255,255,.12);box-shadow:0 12px 30px rgba(15,23,42,.2);border-radius:14px;padding:9px 12px;font-size:12px;font-weight:800;white-space:nowrap;opacity:0;transform:translateX(10px);pointer-events:none;transition:.22s ease}
.li-wa-btn{width:58px;height:58px;border-radius:999px;background:#25D366;display:grid;place-items:center;box-shadow:0 12px 30px rgba(37,211,102,.38);animation:liWaPulse 2.1s ease-in-out infinite;text-decoration:none;border:3px solid #fff;transition:transform .2s ease}
.li-wa-btn:hover{transform:scale(1.08)}
.li-wa-btn:hover + .li-wa-label,.li-wa-wrap:focus-within .li-wa-label{opacity:1;transform:translateX(0)}
.li-wa-btn svg{width:31px;height:31px;fill:#fff;animation:liWaBob 2.4s ease-in-out infinite}
@media(max-width:640px){.li-wa-wrap{right:14px;bottom:14px}.li-wa-btn{width:54px;height:54px}.li-wa-label{display:none}}
@media(prefers-reduced-motion:reduce){.li-wa-btn,.li-wa-btn svg{animation:none}}
</style>
<div class="li-wa-wrap" aria-label="WhatsApp support">
  <a class="li-wa-btn" href="https://wa.me/919145597951?text=Hi%20Leads%20India%2C%20I%20need%20help%20with%20a%20property." target="_blank" rel="noopener noreferrer" aria-label="Chat with Leads India on WhatsApp" title="WhatsApp Leads India">
    <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M19.11 17.37c-.26-.13-1.54-.76-1.78-.85-.24-.09-.41-.13-.59.13-.17.26-.67.85-.82 1.02-.15.17-.3.2-.56.07-.26-.13-1.08-.4-2.06-1.27-.76-.68-1.27-1.51-1.42-1.77-.15-.26-.02-.4.11-.53.12-.12.26-.3.39-.46.13-.15.17-.26.26-.43.09-.17.04-.33-.02-.46-.07-.13-.59-1.42-.8-1.94-.21-.51-.43-.44-.59-.45h-.5c-.17 0-.46.07-.69.33-.24.26-.91.89-.91 2.18 0 1.29.94 2.53 1.07 2.7.13.17 1.85 2.83 4.49 3.97.63.27 1.12.43 1.5.55.63.2 1.2.17 1.65.1.5-.07 1.54-.63 1.76-1.24.22-.61.22-1.13.15-1.24-.06-.11-.23-.17-.49-.3zM16.03 3.2c-7.06 0-12.8 5.74-12.8 12.8 0 2.25.59 4.45 1.7 6.38L3.12 28.8l6.57-1.72A12.73 12.73 0 0 0 16.02 28h.01c7.06 0 12.8-5.74 12.8-12.8 0-3.42-1.33-6.63-3.75-9.05A12.7 12.7 0 0 0 16.03 3.2zm0 22.64h-.01a10.6 10.6 0 0 1-5.4-1.48l-.39-.23-3.9 1.02 1.04-3.8-.25-.39a10.63 10.63 0 0 1-1.63-5.66c0-5.87 4.78-10.64 10.65-10.64 2.84 0 5.51 1.11 7.52 3.12a10.57 10.57 0 0 1 3.12 7.52c0 5.87-4.78 10.64-10.65 10.64z"/></svg>
  </a>
  <span class="li-wa-label">Chat on WhatsApp</span>
</div>
HTML;

if (stripos($html, '</body>') !== false) {
    $html = preg_replace('/<\/body>/i', $widget . "\n</body>", $html, 1);
} else {
    $html .= $widget;
}

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo $html;
