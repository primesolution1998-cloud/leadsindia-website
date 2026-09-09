<?php
$marketplaceHome = __DIR__ . '/home-marketplace.php';
if (is_file($marketplaceHome)) {
    require $marketplaceHome;
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
