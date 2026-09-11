<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-ai.php';
rainbow_load_private_env();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options', 'nosniff');

$verifyToken = trim((string)(getenv('WHATSAPP_VERIFY_TOKEN') ?: ''));

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = (string)($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = (string)($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = (string)($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

    if ($verifyToken !== '' && $mode === 'subscribe' && hash_equals($verifyToken, $token)) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Verification failed';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appSecret = trim((string)(getenv('META_APP_SECRET') ?: ''));

    if ($appSecret === '') {
        http_response_code(503);
        echo 'Webhook signature verification not configured';
        exit;
    }

    $raw = file_get_contents('php://input') ?: '';
    $signature = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
    $expected = 'sha256=' . hash_hmac('sha256', $raw, $appSecret);

    if ($signature === '' || !hash_equals($expected, $signature)) {
        http_response_code(403);
        echo 'Invalid signature';
        exit;
    }

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}

http_response_code(405);
echo 'Method not allowed';
