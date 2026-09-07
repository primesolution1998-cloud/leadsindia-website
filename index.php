<?php
$home = __DIR__ . '/index.html';
if (!is_file($home)) {
    http_response_code(500);
    echo 'Homepage unavailable.';
    exit;
}
$html = file_get_contents($home);
$blockFile = __DIR__ . '/property-owner-campaign-block.php';
ob_start();
if (is_file($blockFile)) {
    include $blockFile;
}
$block = ob_get_clean();
$marker = '<!-- HERO SECTION -->';
if ($block !== '' && strpos($html, $marker) !== false) {
    $html = str_replace($marker, $block . "\n\n    " . $marker, $html, $count);
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo $html;
