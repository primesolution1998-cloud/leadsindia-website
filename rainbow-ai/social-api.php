<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-social.php';
rainbow_bootstrap();
rainbow_require_admin_json();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    rainbow_json(['ok'=>true,'summary'=>rainbow_social_summary(),'queue'=>rainbow_social_load_queue()['items']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rainbow_json(['ok'=>false,'code'=>'method_not_allowed','message'=>'Method not allowed.'],405);
}

$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!rainbow_verify_csrf($csrf)) rainbow_json(['ok'=>false,'code'=>'csrf_failed','message'=>'Security token validation failed.'],403);
if (!rainbow_rate_limit(20,60)) rainbow_json(['ok'=>false,'code'=>'rate_limited','message'=>'Too many requests.'],429);

$raw = file_get_contents('php://input');
$input = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($input)) rainbow_json(['ok'=>false,'code'=>'invalid_json','message'=>'Invalid request body.'],400);

$action = strtolower(trim((string)($input['action'] ?? '')));
try {
    if ($action === 'create_draft') {
        $item = rainbow_social_create_draft($input);
        rainbow_json(['ok'=>true,'item'=>$item,'summary'=>rainbow_social_summary()],201);
    }
    if ($action === 'approve') {
        $item = rainbow_social_approve((string)($input['id'] ?? ''));
        rainbow_json(['ok'=>true,'item'=>$item,'summary'=>rainbow_social_summary()]);
    }
    if ($action === 'reject') {
        $item = rainbow_social_reject((string)($input['id'] ?? ''));
        rainbow_json(['ok'=>true,'item'=>$item,'summary'=>rainbow_social_summary()]);
    }
    rainbow_json(['ok'=>false,'code'=>'action_invalid','message'=>'Unsupported social workflow action.'],400);
} catch (InvalidArgumentException $e) {
    rainbow_json(['ok'=>false,'code'=>$e->getMessage(),'message'=>'Validation failed.'],422);
} catch (RuntimeException $e) {
    $code=$e->getMessage();
    $status=$code==='draft_not_found'?404:409;
    rainbow_json(['ok'=>false,'code'=>$code,'message'=>'Social workflow request could not be completed.'],$status);
} catch (Throwable $e) {
    rainbow_social_audit('api_error',['type'=>get_class($e)]);
    rainbow_json(['ok'=>false,'code'=>'internal_error','message'=>'Social workflow failed safely.'],500);
}
