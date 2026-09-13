<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-whatsapp.php';

$failures = [];
function wa_expect(bool $condition, string $message): void { global $failures; if (!$condition) $failures[] = $message; }

putenv('WHATSAPP_AUTOMATION_ENABLED=0');
wa_expect(rainbow_whatsapp_normalize_phone('98765 43210') === '919876543210', 'Indian 10-digit phone must normalize to 91 country code');
wa_expect(rainbow_whatsapp_normalize_phone('+91 98765-43210') === '919876543210', 'E.164-like India phone must normalize');
wa_expect(rainbow_whatsapp_normalize_phone('123') === '', 'Invalid short phone must be rejected');

$bad = rainbow_whatsapp_queue(['to'=>'123','type'=>'template','template'=>'test']);
wa_expect(($bad['ok'] ?? true) === false && ($bad['code'] ?? '') === 'invalid_phone', 'Invalid phone must not queue');

$queued = rainbow_whatsapp_queue(['to'=>'9876543210','type'=>'template','template'=>'ytc_lead_welcome','language'=>'en','source'=>'test']);
wa_expect(($queued['ok'] ?? false) === true, 'Valid template must queue');
wa_expect(is_file(rainbow_whatsapp_private_dir().'/outbox/'.($queued['id'] ?? 'missing').'.json'), 'Queued message file must exist');

$dispatch = rainbow_whatsapp_dispatch_pending(5);
wa_expect(($dispatch['sent'] ?? -1) === 0, 'Disabled automation must not send');
wa_expect(($dispatch['skipped'] ?? 0) >= 1, 'Disabled automation must report skipped queue');

$event = ['entry'=>[['changes'=>[['value'=>['statuses'=>[['id'=>'wamid.test','status'=>'delivered','recipient_id'=>'919876543210','timestamp'=>'1']],'messages'=>[['from'=>'919876543210','id'=>'wamid.in','type'=>'text','text'=>['body'=>'Hi']]]]]]]]]];
$processed = rainbow_whatsapp_process_webhook($event);
wa_expect(($processed['ok'] ?? false) === true, 'Webhook event must process');
wa_expect(($processed['statuses'] ?? 0) === 1, 'Delivery status must be counted');
wa_expect(($processed['queued_replies'] ?? -1) === 0, 'Disabled automation must not auto-reply');

if ($failures) { fwrite(STDERR, "Rainbow WhatsApp tests failed:\n- ".implode("\n- ",$failures)."\n"); exit(1); }
echo "Rainbow WhatsApp tests passed\n";
