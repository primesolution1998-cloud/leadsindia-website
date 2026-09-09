<?php
require dirname(__DIR__) . '/lib/property-store.php';
require dirname(__DIR__) . '/lib/cmts-client.php';

// CLI/cron only. Prevent accidental browser execution.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$result = li_cmts_dispatch_pending(100);
echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(($result['failed'] ?? 0) > 0 ? 2 : 0);
