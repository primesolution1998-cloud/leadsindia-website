<?php

declare(strict_types=1);

require_once __DIR__ . '/rainbow-ai.php';

function rainbow_social_root(): string
{
    $dir = rainbow_private_root() . '/rainbow-social';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('social_storage_unavailable');
    }
    return $dir;
}

function rainbow_social_queue_file(): string
{
    return rainbow_social_root() . '/queue.json';
}

function rainbow_social_audit_file(): string
{
    return rainbow_social_root() . '/audit.ndjson';
}

function rainbow_social_atomic_write(string $path, array $data): void
{
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('social_storage_write_failed');
    if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('social_storage_commit_failed'); }
    @chmod($path, 0640);
}

function rainbow_social_load_queue(): array
{
    $file = rainbow_social_queue_file();
    if (!is_file($file)) return ['version'=>1, 'items'=>[]];
    $raw = file_get_contents($file);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) && isset($decoded['items']) && is_array($decoded['items']) ? $decoded : ['version'=>1, 'items'=>[]];
}

function rainbow_social_save_queue(array $queue): void
{
    $queue['version'] = 1;
    rainbow_social_atomic_write(rainbow_social_queue_file(), $queue);
}

function rainbow_social_audit(string $event, array $context=[]): void
{
    $row = [
        'at'=>gmdate('c'),
        'event'=>$event,
        'actor'=>rainbow_admin_logged_in() ? 'admin' : 'system',
        'context'=>$context,
    ];
    @file_put_contents(rainbow_social_audit_file(), json_encode($row, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND|LOCK_EX);
}

function rainbow_social_valid_platforms(): array
{
    return ['facebook','instagram','youtube','linkedin','x','google_business'];
}

function rainbow_social_normalize_platforms(array $platforms): array
{
    $allowed = array_flip(rainbow_social_valid_platforms());
    $out=[];
    foreach ($platforms as $platform) {
        $p = strtolower(trim((string)$platform));
        if ($p !== '' && isset($allowed[$p])) $out[$p] = true;
    }
    return array_keys($out);
}

function rainbow_social_create_draft(array $input): array
{
    $brand = trim((string)($input['brand'] ?? ''));
    $caption = trim((string)($input['caption'] ?? ''));
    $platforms = rainbow_social_normalize_platforms(is_array($input['platforms'] ?? null) ? $input['platforms'] : []);
    $scheduledAt = trim((string)($input['scheduled_at'] ?? ''));

    if ($brand === '' || mb_strlen($brand) > 120) throw new InvalidArgumentException('brand_invalid');
    if ($caption === '' || mb_strlen($caption) > 8000) throw new InvalidArgumentException('caption_invalid');
    if (!$platforms) throw new InvalidArgumentException('platforms_required');

    if ($scheduledAt !== '') {
        $ts = strtotime($scheduledAt);
        if ($ts === false || $ts < (time() - 60)) throw new InvalidArgumentException('scheduled_at_invalid');
        $scheduledAt = gmdate('c', $ts);
    }

    $queue = rainbow_social_load_queue();
    $item = [
        'id'=>'rsp_' . bin2hex(random_bytes(12)),
        'brand'=>$brand,
        'caption'=>$caption,
        'platforms'=>$platforms,
        'media'=>[],
        'status'=>'draft',
        'approval'=>'pending',
        'scheduled_at'=>$scheduledAt !== '' ? $scheduledAt : null,
        'attempts'=>0,
        'last_error'=>null,
        'created_at'=>gmdate('c'),
        'updated_at'=>gmdate('c'),
    ];
    $queue['items'][] = $item;
    rainbow_social_save_queue($queue);
    rainbow_social_audit('draft_created', ['id'=>$item['id'],'brand'=>$brand,'platforms'=>$platforms]);
    return $item;
}

function rainbow_social_find_index(array $queue, string $id): int
{
    foreach ($queue['items'] as $i=>$item) if (($item['id'] ?? '') === $id) return (int)$i;
    return -1;
}

function rainbow_social_approve(string $id): array
{
    if (!preg_match('/^rsp_[a-f0-9]{24}$/', $id)) throw new InvalidArgumentException('id_invalid');
    $queue = rainbow_social_load_queue();
    $i = rainbow_social_find_index($queue, $id);
    if ($i < 0) throw new RuntimeException('draft_not_found');
    if (($queue['items'][$i]['status'] ?? '') !== 'draft') throw new RuntimeException('draft_not_approvable');
    $queue['items'][$i]['approval'] = 'approved';
    $queue['items'][$i]['status'] = $queue['items'][$i]['scheduled_at'] ? 'scheduled' : 'approved';
    $queue['items'][$i]['approved_at'] = gmdate('c');
    $queue['items'][$i]['updated_at'] = gmdate('c');
    rainbow_social_save_queue($queue);
    rainbow_social_audit('draft_approved', ['id'=>$id]);
    return $queue['items'][$i];
}

function rainbow_social_reject(string $id): array
{
    if (!preg_match('/^rsp_[a-f0-9]{24}$/', $id)) throw new InvalidArgumentException('id_invalid');
    $queue = rainbow_social_load_queue();
    $i = rainbow_social_find_index($queue, $id);
    if ($i < 0) throw new RuntimeException('draft_not_found');
    $queue['items'][$i]['approval'] = 'rejected';
    $queue['items'][$i]['status'] = 'rejected';
    $queue['items'][$i]['updated_at'] = gmdate('c');
    rainbow_social_save_queue($queue);
    rainbow_social_audit('draft_rejected', ['id'=>$id]);
    return $queue['items'][$i];
}

function rainbow_social_connector_status(): array
{
    return [
        'facebook'=>['configured'=>rainbow_meta_token() !== '', 'publish_enabled'=>false],
        'instagram'=>['configured'=>rainbow_meta_token() !== '', 'publish_enabled'=>false],
        'youtube'=>['configured'=>trim((string)(getenv('YOUTUBE_ACCESS_TOKEN') ?: '')) !== '', 'publish_enabled'=>false],
        'linkedin'=>['configured'=>trim((string)(getenv('LINKEDIN_ACCESS_TOKEN') ?: '')) !== '', 'publish_enabled'=>false],
        'x'=>['configured'=>trim((string)(getenv('X_ACCESS_TOKEN') ?: '')) !== '', 'publish_enabled'=>false],
        'google_business'=>['configured'=>trim((string)(getenv('GOOGLE_BUSINESS_ACCESS_TOKEN') ?: '')) !== '', 'publish_enabled'=>false],
    ];
}

function rainbow_social_summary(): array
{
    $queue = rainbow_social_load_queue();
    $counts = ['draft'=>0,'approved'=>0,'scheduled'=>0,'publishing'=>0,'published'=>0,'failed'=>0,'rejected'=>0];
    foreach ($queue['items'] as $item) {
        $status = (string)($item['status'] ?? 'draft');
        if (array_key_exists($status, $counts)) $counts[$status]++;
    }
    return ['counts'=>$counts,'total'=>count($queue['items']),'connectors'=>rainbow_social_connector_status()];
}
