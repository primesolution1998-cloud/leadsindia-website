<?php

declare(strict_types=1);

function li_root(): string { return dirname(__DIR__); }
function li_private_root(): string {
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? li_root()), '/\\');
    $parent = dirname($docRoot);
    $dir = $parent . '/leadsindia-private';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = li_root() . '/storage-private';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
    }
    return $dir;
}
function li_property_data_dir(): string {
    $dir = li_private_root() . '/property-submissions';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $legacy = li_root() . '/storage/property-submissions';
    if (is_dir($legacy) && is_dir($dir)) {
        foreach (glob($legacy . '/LI-*.json') ?: [] as $src) {
            $dst = $dir . '/' . basename($src);
            if (!is_file($dst)) @copy($src, $dst);
        }
    }
    return $dir;
}
function li_property_photo_dir(string $reference): string {
    $reference = preg_replace('/[^A-Z0-9-]/', '', strtoupper($reference));
    $dir = li_private_root() . '/property-photos/' . $reference;
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    return $dir;
}
function li_property_photo_url(string $reference, string $filename): string {
    return '/api/property-photo.php?ref=' . rawurlencode($reference) . '&file=' . rawurlencode($filename);
}
function li_allowed_statuses(): array { return ['PENDING_VERIFICATION','UNDER_REVIEW','NEED_CORRECTION','VERIFIED','REJECTED','LIVE','PAUSED','SOLD','RENTED','OWNER_DELETED']; }
function li_load_property(string $reference): ?array {
    if (!preg_match('/^LI-[A-Z0-9-]+$/', $reference)) return null;
    $file = li_property_data_dir() . '/' . $reference . '.json';
    if (!is_file($file)) return null;
    $json = file_get_contents($file); if ($json === false) return null;
    $record = json_decode($json, true); return is_array($record) ? $record : null;
}
function li_save_property(array $record): bool {
    $reference = (string)($record['reference_id'] ?? '');
    if (!preg_match('/^LI-[A-Z0-9-]+$/', $reference)) return false;
    $dir = li_property_data_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return false;
    $file = $dir . '/' . $reference . '.json';
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $ok = file_put_contents($file, $json, LOCK_EX) !== false; if ($ok) @chmod($file, 0640); return $ok;
}
function li_audit(array &$record, string $from, string $to, string $by, string $note = ''): void {
    if (!isset($record['status_history']) || !is_array($record['status_history'])) $record['status_history'] = [];
    $record['status_history'][] = ['from'=>$from,'to'=>$to,'by'=>$by,'note'=>$note,'at'=>gmdate('c')];
}

// Moderation is authoritative. No read/list function is allowed to mutate a property's status.
// Legacy instant-publish helpers are intentionally kept as no-ops for backward compatibility with old cron calls.
function li_publish_legacy_pending_now(): int { return 0; }
function li_auto_publish_unreviewed(int $hours = 24): int { return 0; }

function li_all_properties(): array {
    $dir=li_property_data_dir(); if(!is_dir($dir))return []; $records=[];
    foreach(glob($dir.'/LI-*.json')?:[] as $file){$json=file_get_contents($file);if($json===false)continue;$r=json_decode($json,true);if(is_array($r))$records[]=$r;}
    usort($records,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??''))); return $records;
}
