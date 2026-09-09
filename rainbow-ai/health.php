<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/rainbow-ai.php';
rainbow_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rainbow_json(['ok' => false, 'code' => 'method_not_allowed'], 405);
}

$adminReady = rainbow_admin_ready();
$authenticated = rainbow_admin_logged_in();
$keyConfigured = rainbow_openai_key() !== '';
$curlAvailable = function_exists('curl_init');
$probe = ['connected' => false, 'code' => 'not_probed'];

if ($adminReady && $authenticated && $keyConfigured && $curlAvailable) {
    $probe = rainbow_probe_openai();
}

rainbow_json([
    'ok' => true,
    'phase' => 1,
    'openai' => [
        'configured' => $keyConfigured,
        'model' => rainbow_openai_model(),
        'connected' => (bool) $probe['connected'],
        'probe_code' => (string) $probe['code'],
    ],
    'security' => [
        'admin_configured' => $adminReady,
        'authenticated' => $authenticated,
        'csrf_enabled' => true,
        'rate_limit_enabled' => true,
    ],
    'runtime' => [
        'curl_available' => $curlAvailable,
        'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
    ],
]);
