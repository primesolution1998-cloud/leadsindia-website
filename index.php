<?php
$home = __DIR__ . '/index.html';
if (!is_file($home)) {
    http_response_code(500);
    echo 'Homepage unavailable.';
    exit;
}

$html = file_get_contents($home);
if ($html === false) {
    http_response_code(500);
    echo 'Homepage unavailable.';
    exit;
}

$blockFile = __DIR__ . '/property-owner-campaign-block.php';
ob_start();
if (is_file($blockFile)) {
    include $blockFile;
}
$block = ob_get_clean();

// Keep the existing site header/footer and business sections, but use the
// four-service campaign selector as the actual first hero on the homepage.
$marker = '<!-- HERO SECTION -->';
$legacyHeroStart = '<section class="relative pt-12 pb-24 lg:pt-20 lg:pb-32 overflow-hidden">';

if ($block !== '' && strpos($html, $marker) !== false) {
    $html = str_replace($marker, $block . "\n\n    " . $marker, $html, $count);
}

// The old agency hero was appearing directly below the campaign selector and
// making the homepage look like two different landing pages. Hide only that
// exact legacy hero section; all downstream sections remain available.
if (strpos($html, $legacyHeroStart) !== false) {
    $html = str_replace(
        $legacyHeroStart,
        '<section class="relative pt-12 pb-24 lg:pt-20 lg:pb-32 overflow-hidden" style="display:none" aria-hidden="true">',
        $html,
        $heroCount
    );
}

// Normalize old public .html references and make the brand logo return to the
// clean root URL instead of creating a trailing # in the browser address bar.
$html = strtr($html, [
    'https://leadsindia.in/properties.html' => 'https://leadsindia.in/properties',
    'https://leadsindia.in/loans.html' => 'https://leadsindia.in/loans',
    'https://leadsindia.in/software.html' => 'https://leadsindia.in/software',
    'href="properties.html"' => 'href="/properties"',
    'href="loans.html"' => 'href="/loans"',
    'href="software.html"' => 'href="/software"',
    'href="#" class="flex items-center gap-3 group"' => 'href="/" class="flex items-center gap-3 group"',
]);

// Remove a lone trailing # left by older cached homepage links without
// interfering with real section anchors such as #contact or #software-hub.
$html = str_replace(
    '</body>',
    '<script>if(location.pathname==="/" && location.hash==="#"){history.replaceState(null,"","/");}</script></body>',
    $html
);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo $html;
