<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-ai.php';
require dirname(__DIR__) . '/lib/rainbow-whatsapp.php';
require dirname(__DIR__) . '/lib/rainbow-whatsapp-followup.php';
rainbow_load_private_env();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'code'=>'method_not_allowed']);
    exit;
}

$secret = trim((string)(getenv('YTC_LEAD_WEBHOOK_SECRET') ?: ''));
if (strlen($secret) < 32) {
    http_response_code(503);
    echo json_encode(['ok'=>false,'code'=>'lead_webhook_not_configured']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$signature = trim((string)($_SERVER['HTTP_X_RAINBOW_SIGNATURE'] ?? ''));
$expected = hash_hmac('sha256', $raw, $secret);
if ($signature === '' || !hash_equals($expected, $signature)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'code'=>'invalid_signature']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'code'=>'invalid_json']);
    exit;
}

$name = trim((string)($data['name'] ?? ''));
$mobile = rainbow_whatsapp_normalize_phone((string)($data['mobile'] ?? $data['phone'] ?? ''));
$source = trim((string)($data['source'] ?? 'YTC Education Leads'));
$leadId = trim((string)($data['lead_id'] ?? $data['row_id'] ?? ''));
if ($name === '' || $mobile === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'code'=>'name_mobile_required']);
    exit;
}

$tracking = rainbow_whatsapp_followup_register(['phone'=>$mobile,'lead_id'=>$leadId,'name'=>$name,'source'=>$source]);
if (($tracking['code'] ?? '') === 'duplicate') {
    http_response_code(202);
    echo json_encode(['ok'=>true,'code'=>'duplicate_suppressed'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
if (!($tracking['ok'] ?? false)) {
    http_response_code(($tracking['code'] ?? '') === 'opted_out' ? 202 : 500);
    echo json_encode($tracking, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

$template = trim((string)(getenv('WHATSAPP_YTC_LEAD_TEMPLATE') ?: 'ytc_lead_welcome'));
$language = trim((string)(getenv('WHATSAPP_YTC_LEAD_TEMPLATE_LANGUAGE') ?: 'en')) ?: 'en';
$components = [[
    'type'=>'body',
    'parameters'=>[[
        'type'=>'text',
        'text'=>mb_substr($name, 0, 80),
    ]],
]];

$result = rainbow_whatsapp_queue([
    'to'=>$mobile,
    'type'=>'template',
    'template'=>$template,
    'language'=>$language,
    'components'=>$components,
    'source'=>'ytc_sheet_lead',
    'meta'=>['lead_id'=>$leadId,'name'=>$name,'source'=>$source],
]);
if (!($result['ok'] ?? false) && !empty($tracking['file'])) @unlink((string)$tracking['file']);

rainbow_whatsapp_log(['event'=>'ytc_lead_ingested','lead_id'=>$leadId,'name'=>$name,'to'=>$mobile,'source'=>$source,'queue_ok'=>$result['ok']??false]);
http_response_code(($result['ok'] ?? false) ? 202 : 500);
echo json_encode($result, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
