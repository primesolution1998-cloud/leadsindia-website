<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-ai.php';
require dirname(__DIR__) . '/lib/rainbow-whatsapp.php';
require dirname(__DIR__) . '/lib/rainbow-whatsapp-followup.php';
rainbow_load_private_env();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$scheduled = rainbow_whatsapp_schedule_followups();
$dispatch = rainbow_whatsapp_dispatch_pending(50);
echo json_encode(['scheduled'=>$scheduled,'dispatch'=>$dispatch], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit((($scheduled['failed'] ?? 0) + ($dispatch['failed'] ?? 0)) > 0 ? 2 : 0);
