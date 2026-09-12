<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function ytc_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function ytc_load_env(): void {
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

function ytc_header(string $name): string {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function ytc_private_dir(): string {
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4)), '/\\');
    $dir = dirname($docRoot) . '/leadsindia-private/ytc-leads';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        ytc_json(['ok' => false, 'code' => 'storage_unavailable'], 500);
    }
    return $dir;
}

function ytc_text(array $lead, string $key, int $max, bool $required = false): string {
    $value = isset($lead[$key]) ? trim((string)$lead[$key]) : '';
    if ($required && $value === '') ytc_json(['ok' => false, 'code' => 'invalid_lead', 'field' => $key], 422);
    if (strlen($value) > $max) ytc_json(['ok' => false, 'code' => 'invalid_lead_size', 'field' => $key], 422);
    return $value;
}

function ytc_store(array $lead): array {
    $dir = ytc_private_dir();
    $now = gmdate('c');
    $record = ['received_at' => $now, 'lead' => $lead];

    if (class_exists('SQLite3')) {
        $dbPath = dirname($dir) . '/ytc-leads.sqlite';
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $db->busyTimeout(3000);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('CREATE TABLE IF NOT EXISTS ytc_leads (lead_id TEXT PRIMARY KEY, source TEXT NOT NULL, name TEXT NOT NULL, mobile TEXT NOT NULL, email TEXT NOT NULL, course TEXT NOT NULL, job_vertical TEXT NOT NULL, english_goal TEXT NOT NULL, preferred_time TEXT NOT NULL, received_at TEXT NOT NULL, payload_json TEXT NOT NULL)');
        $stmt = $db->prepare('INSERT OR IGNORE INTO ytc_leads (lead_id, source, name, mobile, email, course, job_vertical, english_goal, preferred_time, received_at, payload_json) VALUES (:lead_id,:source,:name,:mobile,:email,:course,:job_vertical,:english_goal,:preferred_time,:received_at,:payload_json)');
        if ($stmt === false) ytc_json(['ok' => false, 'code' => 'database_prepare_failed'], 500);
        foreach (['lead_id','source','name','mobile','email','course','job_vertical','english_goal','preferred_time'] as $field) {
            $stmt->bindValue(':' . $field, (string)$lead[$field], SQLITE3_TEXT);
        }
        $stmt->bindValue(':received_at', $now, SQLITE3_TEXT);
        $stmt->bindValue(':payload_json', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) ytc_json(['ok' => false, 'code' => 'database_write_failed'], 500);
        $result->finalize();
        $inserted = $db->changes() > 0;
        $db->close();
        @chmod($dbPath, 0640);
        return ['storage' => 'sqlite', 'inserted' => $inserted];
    }

    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$lead['lead_id']) ?: hash('sha256', (string)$lead['lead_id']);
    $file = $dir . '/' . $safe . '.json';
    if (is_file($file)) return ['storage' => 'json', 'inserted' => false];
    $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        ytc_json(['ok' => false, 'code' => 'storage_write_failed'], 500);
    }
    @chmod($tmp, 0640);
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        ytc_json(['ok' => false, 'code' => 'storage_commit_failed'], 500);
    }
    return ['storage' => 'json', 'inserted' => true];
}

ytc_load_env();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'HEAD') { http_response_code(204); exit; }
if ($method !== 'POST') ytc_json(['ok' => false, 'code' => 'method_not_allowed'], 405);

$secret = (string)(getenv('YTC_RAINBOW_SECRET') ?: '');
if (strlen($secret) < 32) ytc_json(['ok' => false, 'code' => 'ytc_secret_not_configured'], 503);

$raw = file_get_contents('php://input');
if (!is_string($raw) || $raw === '' || strlen($raw) > 65536) ytc_json(['ok' => false, 'code' => 'invalid_body'], 400);

$timestamp = ytc_header('X-YTC-Timestamp');
$signature = ytc_header('X-YTC-Signature');
if (!ctype_digit($timestamp) || !preg_match('/^[a-f0-9]{64}$/i', $signature)) ytc_json(['ok' => false, 'code' => 'authentication_required'], 401);
if (abs(time() - (int)$timestamp) > 300) ytc_json(['ok' => false, 'code' => 'timestamp_expired'], 401);
$expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
if (!hash_equals($expected, strtolower($signature))) ytc_json(['ok' => false, 'code' => 'invalid_signature'], 401);

$input = json_decode($raw, true);
if (!is_array($input)) ytc_json(['ok' => false, 'code' => 'invalid_json'], 400);
$lead = [
    'lead_id' => ytc_text($input, 'lead_id', 120, true),
    'source' => ytc_text($input, 'source', 80, true),
    'name' => ytc_text($input, 'name', 160, true),
    'mobile' => ytc_text($input, 'mobile', 32, true),
    'email' => ytc_text($input, 'email', 254, true),
    'course' => ytc_text($input, 'course', 160),
    'job_vertical' => ytc_text($input, 'job_vertical', 160),
    'english_goal' => ytc_text($input, 'english_goal', 240),
    'preferred_time' => ytc_text($input, 'preferred_time', 120),
];
if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) ytc_json(['ok' => false, 'code' => 'invalid_lead', 'field' => 'email'], 422);
if (!preg_match('/^[0-9+() -]{7,32}$/', $lead['mobile'])) ytc_json(['ok' => false, 'code' => 'invalid_lead', 'field' => 'mobile'], 422);

$stored = ytc_store($lead);
ytc_json([
    'ok' => true,
    'code' => $stored['inserted'] ? 'accepted' : 'duplicate',
    'lead_id' => $lead['lead_id'],
    'storage' => $stored['storage'],
], $stored['inserted'] ? 202 : 200);
