<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function cmts_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function cmts_load_env(): void {
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4)), '/\\');
    $path = dirname($docRoot) . '/leadsindia-private/.rainbow-ai.env';
    if (!is_readable($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z0-9_]+$/', $name) || getenv($name) !== false) continue;
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

function cmts_private_dir(): string {
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4)), '/\\');
    $dir = dirname($docRoot) . '/leadsindia-private/cmts-inbox';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        cmts_json(['ok' => false, 'code' => 'storage_unavailable'], 500);
    }
    return $dir;
}

function cmts_header(string $name): string {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function cmts_store(array $event): array {
    $dir = cmts_private_dir();
    $eventId = (string)$event['event_id'];
    $reference = (string)$event['property_reference'];
    $now = gmdate('c');
    $record = [
        'event_id' => $eventId,
        'property_reference' => $reference,
        'status' => (string)$event['status'],
        'received_at' => $now,
        'event' => $event,
    ];

    if (class_exists('SQLite3')) {
        $dbPath = dirname($dir) . '/cmts.sqlite';
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $db->busyTimeout(3000);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('CREATE TABLE IF NOT EXISTS leadsindia_approvals (event_id TEXT PRIMARY KEY, property_reference TEXT NOT NULL, status TEXT NOT NULL, received_at TEXT NOT NULL, payload_json TEXT NOT NULL)');
        $stmt = $db->prepare('INSERT OR REPLACE INTO leadsindia_approvals (event_id, property_reference, status, received_at, payload_json) VALUES (:event_id, :property_reference, :status, :received_at, :payload_json)');
        if ($stmt === false) cmts_json(['ok' => false, 'code' => 'database_prepare_failed'], 500);
        $stmt->bindValue(':event_id', $eventId, SQLITE3_TEXT);
        $stmt->bindValue(':property_reference', $reference, SQLITE3_TEXT);
        $stmt->bindValue(':status', (string)$event['status'], SQLITE3_TEXT);
        $stmt->bindValue(':received_at', $now, SQLITE3_TEXT);
        $stmt->bindValue(':payload_json', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) cmts_json(['ok' => false, 'code' => 'database_write_failed'], 500);
        $result->finalize();
        $db->close();
        @chmod($dbPath, 0640);
        return ['storage' => 'sqlite'];
    }

    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $eventId) ?: hash('sha256', $eventId);
    $file = $dir . '/' . $safe . '.json';
    $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        cmts_json(['ok' => false, 'code' => 'storage_write_failed'], 500);
    }
    @chmod($tmp, 0640);
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        cmts_json(['ok' => false, 'code' => 'storage_commit_failed'], 500);
    }
    return ['storage' => 'json'];
}

cmts_load_env();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'HEAD') {
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    cmts_json(['ok' => false, 'code' => 'method_not_allowed'], 405);
}

$secret = (string)(getenv('LEADSINDIA_CMTS_SECRET') ?: '');
if (strlen($secret) < 32) {
    cmts_json(['ok' => false, 'code' => 'cmts_secret_not_configured'], 503);
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || $raw === '' || strlen($raw) > 262144) {
    cmts_json(['ok' => false, 'code' => 'invalid_body'], 400);
}

$timestamp = cmts_header('X-LeadsIndia-Timestamp');
$signature = cmts_header('X-LeadsIndia-Signature');
if (!ctype_digit($timestamp) || $signature === '' || !preg_match('/^[a-f0-9]{64}$/i', $signature)) {
    cmts_json(['ok' => false, 'code' => 'authentication_required'], 401);
}

$ts = (int)$timestamp;
if (abs(time() - $ts) > 300) {
    cmts_json(['ok' => false, 'code' => 'timestamp_expired'], 401);
}

$expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
if (!hash_equals($expected, strtolower($signature))) {
    cmts_json(['ok' => false, 'code' => 'invalid_signature'], 401);
}

$event = json_decode($raw, true);
if (!is_array($event)) {
    cmts_json(['ok' => false, 'code' => 'invalid_json'], 400);
}

foreach (['event_id', 'property_reference', 'status'] as $field) {
    if (!isset($event[$field]) || !is_string($event[$field]) || trim($event[$field]) === '') {
        cmts_json(['ok' => false, 'code' => 'invalid_event', 'field' => $field], 422);
    }
}

if (strlen($event['event_id']) > 180 || strlen($event['property_reference']) > 120 || strlen($event['status']) > 40) {
    cmts_json(['ok' => false, 'code' => 'invalid_event_size'], 422);
}

$stored = cmts_store($event);
cmts_json([
    'ok' => true,
    'code' => 'accepted',
    'event_id' => $event['event_id'],
    'property_reference' => $event['property_reference'],
    'storage' => $stored['storage'],
], 202);
