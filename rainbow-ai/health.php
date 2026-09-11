<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/rainbow-ai.php';
if (!function_exists('rainbow_whatsapp_probe')) {
    require dirname(__DIR__) . '/lib/rainbow-whatsapp.php';
}
if (!function_exists('li_cmts_probe')) {
    require dirname(__DIR__) . '/lib/cmts-client.php';
}
rainbow_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rainbow_json(['ok' => false, 'code' => 'method_not_allowed'], 405);
}

$adminReady = rainbow_admin_ready();
$authenticated = rainbow_admin_logged_in();
$keyConfigured = rainbow_openai_key() !== '';
$curlAvailable = function_exists('curl_init');
$probe = ['connected' => false, 'code' => 'not_probed'];
$metaProbe = ['connected' => false, 'configured' => false, 'code' => 'not_probed', 'account' => null];
$whatsappProbe = ['connected' => false, 'configured' => false, 'code' => 'not_probed', 'phone' => null];
$crmProbe = ['connected' => false, 'configured' => li_cmts_configured(), 'code' => 'not_probed', 'http' => 0];

if ($adminReady && $authenticated && $keyConfigured && $curlAvailable) {
    $probe = rainbow_probe_openai();
}
if ($adminReady && $authenticated && $curlAvailable) {
    $metaProbe = rainbow_probe_meta();
    $whatsappProbe = rainbow_whatsapp_probe();
    $crmProbe = li_cmts_probe();
}

rainbow_json([
    'ok' => true,
    'phase' => 2,
    'openai' => [
        'configured' => $keyConfigured,
        'model' => rainbow_openai_model(),
        'connected' => (bool) $probe['connected'],
        'probe_code' => (string) $probe['code'],
    ],
    'meta' => [
        'configured' => (bool) $metaProbe['configured'],
        'connected' => (bool) $metaProbe['connected'],
        'probe_code' => (string) $metaProbe['code'],
        'account' => $metaProbe['account'],
        'read_only' => true,
    ],
    'whatsapp' => [
        'configured' => (bool) $whatsappProbe['configured'],
        'connected' => (bool) $whatsappProbe['connected'],
        'probe_code' => (string) $whatsappProbe['code'],
        'phone' => $whatsappProbe['phone'],
        'read_only' => true,
    ],
    'crm' => [
        'provider' => 'CMTS',
        'configured' => (bool) $crmProbe['configured'],
        'connected' => (bool) $crmProbe['connected'],
        'probe_code' => (string) $crmProbe['code'],
        'http' => (int) $crmProbe['http'],
        'read_only' => true,
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
