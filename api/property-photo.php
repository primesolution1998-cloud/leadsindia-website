<?php
require_once dirname(__DIR__) . '/owner/_auth.php';
require_once dirname(__DIR__) . '/lib/property-store.php';

$ref = strtoupper(trim((string)($_GET['ref'] ?? '')));
$file = basename((string)($_GET['file'] ?? ''));
if (!preg_match('/^LI-[A-Z0-9-]+$/', $ref) || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
    http_response_code(400); exit;
}
$record = li_load_property($ref);
if (!$record) { http_response_code(404); exit; }

$allowed = (($record['status'] ?? '') === 'LIVE');
$owner = owner_current();
if ($owner && ($record['owner']['account_id'] ?? '') === ($owner['id'] ?? '')) $allowed = true;
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$adminUser = (string)(getenv('LEADSINDIA_ADMIN_USER') ?: '');
if ($adminUser !== '' && isset($_SESSION['li_admin']) && hash_equals($adminUser, (string)$_SESSION['li_admin'])) $allowed = true;
if (!$allowed) { http_response_code(403); exit; }

$path = li_property_photo_dir($ref) . '/' . $file;
if (!is_file($path)) { http_response_code(404); exit; }
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path);
if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($path));
readfile($path);
