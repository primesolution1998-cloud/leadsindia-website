<?php

declare(strict_types=1);

function li_cmts_private_dir(): string {
    $root = function_exists('li_private_root') ? li_private_root() : dirname(__DIR__) . '/storage-private';
    $dir = rtrim($root, '/\\') . '/cmts-outbox';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    return $dir;
}

function li_cmts_configured(): bool {
    return trim((string)getenv('LEADSINDIA_CMTS_URL')) !== '' && strlen((string)getenv('LEADSINDIA_CMTS_SECRET')) >= 32;
}

function li_cmts_probe(): array {
    $configured = li_cmts_configured();
    if (!$configured) return ['configured'=>false,'connected'=>false,'code'=>'cmts_not_configured','http'=>0];
    if (!function_exists('curl_init')) return ['configured'=>true,'connected'=>false,'code'=>'curl_unavailable','http'=>0];

    $base = trim((string)getenv('LEADSINDIA_CMTS_URL'));
    $parts = parse_url($base);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['https','http'], true) || empty($parts['host'])) {
        return ['configured'=>true,'connected'=>false,'code'=>'cmts_url_invalid','http'=>0];
    }

    $url = rtrim($base, '/') . '/api/v1/leadsindia/approval/';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>4,
        CURLOPT_TIMEOUT=>8,
        CURLOPT_HTTPHEADER=>['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false || $errno !== 0 || $http === 0) {
        return ['configured'=>true,'connected'=>false,'code'=>'network_error','http'=>$http];
    }

    return ['configured'=>true,'connected'=>true,'code'=>'reachable','http'=>$http];
}

function li_cmts_build_event(array $record): array {
    $owner = is_array($record['owner'] ?? null) ? $record['owner'] : [];
    $property = is_array($record['property'] ?? null) ? $record['property'] : [];
    $ref = (string)($record['reference_id'] ?? '');
    return [
        'event_id' => 'property-live-' . $ref . '-' . substr(hash('sha256', (string)($record['updated_at'] ?? gmdate('c'))), 0, 12),
        'property_reference' => $ref,
        'status' => 'LIVE',
        'owner' => [
            'name' => (string)($owner['name'] ?? ''),
            'phone' => (string)($owner['mobile'] ?? $owner['phone'] ?? ''),
            'email' => (string)($owner['email'] ?? ''),
        ],
        'property' => [
            'purpose' => (string)($property['purpose'] ?? ''),
            'type' => (string)($property['type'] ?? ''),
            'city' => (string)($property['city'] ?? ''),
            'locality' => (string)($property['locality'] ?? ''),
            'price' => (string)($property['price'] ?? ''),
        ],
        'approved_at' => gmdate('c'),
    ];
}

function li_cmts_enqueue_live(array $record): ?string {
    if (($record['status'] ?? '') !== 'LIVE') return null;
    $event = li_cmts_build_event($record);
    if (($event['property_reference'] ?? '') === '') return null;
    $dir = li_cmts_private_dir();
    if (!is_dir($dir) || !is_writable($dir)) return null;
    $file = $dir . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $event['event_id']) . '.json';
    $payload = ['event' => $event, 'attempts' => 0, 'last_error' => null, 'created_at' => gmdate('c')];
    if (file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX) === false) return null;
    @chmod($file, 0640);
    return $file;
}

function li_cmts_dispatch_file(string $file): array {
    if (!is_file($file)) return ['ok'=>false,'error'=>'outbox file missing'];
    $wrapper = json_decode((string)file_get_contents($file), true);
    $event = is_array($wrapper['event'] ?? null) ? $wrapper['event'] : null;
    if (!$event) return ['ok'=>false,'error'=>'invalid outbox payload'];
    if (!li_cmts_configured()) return ['ok'=>false,'error'=>'CMTS not configured'];

    $body = json_encode($event, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $ts = (string)time();
    $secret = (string)getenv('LEADSINDIA_CMTS_SECRET');
    $sig = hash_hmac('sha256', $ts . '.' . $body, $secret);
    $url = rtrim((string)getenv('LEADSINDIA_CMTS_URL'), '/') . '/api/v1/leadsindia/approval/';

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-LeadsIndia-Timestamp: '.$ts,'X-LeadsIndia-Signature: '.$sig]]);
    $response = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $http >= 200 && $http < 300) {
        @unlink($file);
        return ['ok'=>true,'http'=>$http];
    }
    $wrapper['attempts'] = (int)($wrapper['attempts'] ?? 0) + 1;
    $wrapper['last_error'] = $err !== '' ? $err : ('HTTP '.$http);
    $wrapper['last_attempt_at'] = gmdate('c');
    file_put_contents($file, json_encode($wrapper, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    return ['ok'=>false,'http'=>$http,'error'=>$wrapper['last_error']];
}

function li_cmts_dispatch_pending(int $limit = 50): array {
    $files = glob(li_cmts_private_dir() . '/*.json') ?: [];
    sort($files);
    $ok=0; $failed=0;
    foreach (array_slice($files, 0, max(1, min($limit, 200))) as $file) {
        $res = li_cmts_dispatch_file($file);
        $res['ok'] ? $ok++ : $failed++;
    }
    return ['sent'=>$ok,'failed'=>$failed,'remaining'=>count(glob(li_cmts_private_dir().'/*.json') ?: [])];
}
