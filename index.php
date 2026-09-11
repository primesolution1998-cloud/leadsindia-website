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
