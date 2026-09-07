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
/** Make legacy untouched pending owner submissions live on the next read. Admin-reviewed/corrected/rejected records are never overridden. */
function li_publish_legacy_pending_now(): int {
    $dir = li_property_data_dir(); if (!is_dir($dir)) return 0; $published=0;
    foreach (glob($dir . '/LI-*.json') ?: [] as $file) {
        $json=file_get_contents($file); if($json===false)continue; $r=json_decode($json,true); if(!is_array($r))continue;
        if(($r['status']??'')!=='PENDING_VERIFICATION')continue;
        $history=$r['status_history']??[]; if(is_array($history)&&count($history)>0)continue;
        $now=gmdate('c'); $r['status']='LIVE'; $r['updated_at']=$now; $r['published_at']=$r['published_at']??$now;
        $r['verification']=$r['verification']??[]; $r['verification']['verified']=false; $r['verification']['notes']='Owner listing is live; backend verification pending.';
        li_audit($r,'PENDING_VERIFICATION','LIVE','SYSTEM_INSTANT_PUBLISH','Legacy untouched owner listing promoted under instant-live policy.');
        if(li_save_property($r))$published++;
    }
    return $published;
}
function li_auto_publish_unreviewed(int $hours = 24): int { return li_publish_legacy_pending_now(); }
function li_all_properties(): array {
    li_publish_legacy_pending_now();
    $dir=li_property_data_dir(); if(!is_dir($dir))return []; $records=[];
    foreach(glob($dir.'/LI-*.json')?:[] as $file){$json=file_get_contents($file);if($json===false)continue;$r=json_decode($json,true);if(is_array($r))$records[]=$r;}
    usort($records,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??''))); return $records;
}
