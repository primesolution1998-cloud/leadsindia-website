<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
require_once dirname(__DIR__) . '/owner/_auth.php';
require_once dirname(__DIR__) . '/lib/property-store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

function fail($code, $message) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

$ownerAccount = owner_current();
if (!$ownerAccount || ($ownerAccount['status'] ?? '') !== 'ACTIVE') {
    fail(401, 'Please login to your property owner account before submitting.');
}

function clean($key, $max = 255) {
    $value = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

$required = ['purpose','property_type','area','price','city','locality','address','description','declaration'];
foreach ($required as $field) {
    if (empty($_POST[$field])) fail(422, 'Please complete all required fields.');
}

$purpose = clean('purpose', 20);
if (!in_array($purpose, ['Sale','Rent'], true)) fail(422, 'Invalid purpose.');

$reference = 'LI-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$createdAt = gmdate('c');
$dataDir = li_property_data_dir();
$uploadDir = li_property_photo_dir($reference);

if (!is_dir($dataDir) || !is_writable($dataDir)) fail(500, 'Storage unavailable.');
if (!is_dir($uploadDir) || !is_writable($uploadDir)) fail(500, 'Upload storage unavailable.');

$photos = [];
if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $count = min(count($_FILES['photos']['name']), 10);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) fail(422, 'One of the photos could not be uploaded.');
        if ($_FILES['photos']['size'][$i] > 5 * 1024 * 1024) fail(422, 'Each photo must be 5 MB or smaller.');
        $tmp = $_FILES['photos']['tmp_name'][$i];
        $mime = $finfo->file($tmp);
        if (!isset($allowed[$mime])) fail(422, 'Only JPG, PNG and WebP photos are allowed.');
        $filename = sprintf('%02d-%s.%s', $i + 1, bin2hex(random_bytes(4)), $allowed[$mime]);
        $target = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $target)) fail(500, 'Could not save uploaded photo.');
        @chmod($target, 0640);
        $photos[] = li_property_photo_url($reference, $filename);
    }
}

$record = [
    'reference_id' => $reference,
    'status' => 'LIVE',
    'created_at' => $createdAt,
    'updated_at' => $createdAt,
    'published_at' => $createdAt,
    'owner' => [
        'account_id' => $ownerAccount['id'],
        'name' => $ownerAccount['name'],
        'mobile' => $ownerAccount['mobile'],
        'email' => $ownerAccount['email'] ?? '',
    ],
    'property' => [
        'purpose' => $purpose,
        'type' => clean('property_type', 80),
        'configuration' => clean('configuration', 50),
        'area' => clean('area', 50),
        'price' => clean('price', 14),
        'project_name' => clean('project_name', 120),
        'city' => clean('city', 80),
        'locality' => clean('locality', 120),
        'address' => clean('address', 500),
        'description' => clean('description', 1500),
        'photos' => $photos,
    ],
    'verification' => [
        'verified' => false,
        'verified_at' => null,
        'verified_by' => null,
        'notes' => 'Owner self-published; backend verification pending.',
    ],
];
li_audit($record, 'NEW', 'LIVE', 'OWNER_SELF_PUBLISH', 'Published immediately after owner onboarding; contact remains hidden.');
if (!li_save_property($record)) fail(500, 'Could not save submission.');

http_response_code(201);
echo json_encode([
    'ok' => true,
    'reference_id' => $reference,
    'status' => 'LIVE',
    'marketplace_url' => '/properties',
    'property_url' => '/property/' . rawurlencode($reference),
    'dashboard_url' => '/owner/dashboard.php'
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
