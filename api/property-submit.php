<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

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

function clean($key, $max = 255) {
    $value = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

$required = ['owner_name','owner_mobile','purpose','property_type','area','price','city','locality','address','description','declaration'];
foreach ($required as $field) {
    if (empty($_POST[$field])) fail(422, 'Please complete all required fields.');
}

$mobile = preg_replace('/\D+/', '', clean('owner_mobile', 10));
if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) fail(422, 'Enter a valid 10-digit Indian mobile number.');

$email = clean('owner_email', 150);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid email address.');

$purpose = clean('purpose', 20);
if (!in_array($purpose, ['Sale','Rent'], true)) fail(422, 'Invalid purpose.');

$reference = 'LI-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$createdAt = gmdate('c');

$root = dirname(__DIR__);
$dataDir = $root . '/storage/property-submissions';
$uploadDir = $root . '/uploads/properties/' . $reference;

if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true) && !is_dir($dataDir)) fail(500, 'Storage unavailable.');
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) fail(500, 'Upload storage unavailable.');

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
        $photos[] = '/uploads/properties/' . rawurlencode($reference) . '/' . rawurlencode($filename);
    }
}

$record = [
    'reference_id' => $reference,
    'status' => 'PENDING_VERIFICATION',
    'created_at' => $createdAt,
    'updated_at' => $createdAt,
    'owner' => [
        'name' => clean('owner_name', 100),
        'mobile' => $mobile,
        'email' => $email,
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
        'notes' => null,
    ],
];

$file = $dataDir . '/' . $reference . '.json';
$json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (file_put_contents($file, $json, LOCK_EX) === false) fail(500, 'Could not save submission.');
@chmod($file, 0640);

http_response_code(201);
echo json_encode(['ok' => true, 'reference_id' => $reference, 'status' => 'PENDING_VERIFICATION']);
