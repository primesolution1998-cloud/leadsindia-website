<?php

declare(strict_types=1);

// Designed for Hostinger cron / PHP CLI. Refuse normal web execution.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/lib/property-store.php';
$count = li_auto_publish_unreviewed(24);
echo '[' . gmdate('c') . "] auto-published={$count}\n";
