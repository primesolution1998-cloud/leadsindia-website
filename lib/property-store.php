<?php

declare(strict_types=1);

function li_root(): string { return dirname(__DIR__); }
function li_property_data_dir(): string { return li_root() . '/storage/property-submissions'; }

function li_allowed_statuses(): array {
    return ['PENDING_VERIFICATION','UNDER_REVIEW','NEED_CORRECTION','VERIFIED','REJECTED','LIVE','PAUSED','SOLD','RENTED'];
}

function li_load_property(string $reference): ?array {
    if (!preg_match('/^LI-[A-Z0-9-]+$/', $reference)) return null;
    $file = li_property_data_dir() . '/' . $reference . '.json';
    if (!is_file($file)) return null;
    $json = file_get_contents($file);
    if ($json === false) return null;
    $record = json_decode($json, true);
    return is_array($record) ? $record : null;
}

function li_save_property(array $record): bool {
    $reference = (string)($record['reference_id'] ?? '');
    if (!preg_match('/^LI-[A-Z0-9-]+$/', $reference)) return false;
    $dir = li_property_data_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return false;
    $file = $dir . '/' . $reference . '.json';
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $ok = file_put_contents($file, $json, LOCK_EX) !== false;
    if ($ok) @chmod($file, 0640);
    return $ok;
}

function li_all_properties(): array {
    $dir = li_property_data_dir();
    if (!is_dir($dir)) return [];
    $records = [];
    foreach (glob($dir . '/LI-*.json') ?: [] as $file) {
        $json = file_get_contents($file);
        if ($json === false) continue;
        $record = json_decode($json, true);
        if (is_array($record)) $records[] = $record;
    }
    usort($records, fn($a,$b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $records;
}

function li_audit(array &$record, string $from, string $to, string $by, string $note = ''): void {
    if (!isset($record['status_history']) || !is_array($record['status_history'])) $record['status_history'] = [];
    $record['status_history'][] = [
        'from' => $from,
        'to' => $to,
        'by' => $by,
        'note' => $note,
        'at' => gmdate('c'),
    ];
}
