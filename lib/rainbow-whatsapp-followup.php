<?php

declare(strict_types=1);

function rainbow_whatsapp_followup_dir(): string
{
    $dir = rainbow_whatsapp_private_dir() . '/followups';
    foreach ([$dir, $dir . '/leads', $dir . '/optout'] as $path) {
        if (!is_dir($path)) @mkdir($path, 0750, true);
    }
    return $dir;
}

function rainbow_whatsapp_followup_key(string $phone, string $leadId=''): string
{
    $phone = rainbow_whatsapp_normalize_phone($phone);
    $seed = $leadId !== '' ? $leadId . '|' . $phone : $phone;
    return hash('sha256', $seed);
}

function rainbow_whatsapp_followup_lead_file(string $phone, string $leadId=''): string
{
    return rainbow_whatsapp_followup_dir() . '/leads/' . rainbow_whatsapp_followup_key($phone, $leadId) . '.json';
}

function rainbow_whatsapp_followup_optout_file(string $phone): string
{
    $phone = rainbow_whatsapp_normalize_phone($phone);
    return rainbow_whatsapp_followup_dir() . '/optout/' . hash('sha256', $phone) . '.json';
}

function rainbow_whatsapp_followup_is_opted_out(string $phone): bool
{
    return is_file(rainbow_whatsapp_followup_optout_file($phone));
}

function rainbow_whatsapp_followup_mark_optout(string $phone, string $reason='user_stop'): bool
{
    $phone = rainbow_whatsapp_normalize_phone($phone);
    if ($phone === '') return false;
    $payload = ['phone'=>$phone,'reason'=>$reason,'opted_out_at'=>gmdate('c')];
    $ok = @file_put_contents(rainbow_whatsapp_followup_optout_file($phone), json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    if ($ok) rainbow_whatsapp_log(['event'=>'opt_out','to'=>$phone,'reason'=>$reason]);
    return $ok;
}

function rainbow_whatsapp_followup_register(array $lead): array
{
    $phone = rainbow_whatsapp_normalize_phone((string)($lead['phone'] ?? $lead['mobile'] ?? ''));
    if ($phone === '') return ['ok'=>false,'code'=>'invalid_phone'];
    $leadId = trim((string)($lead['lead_id'] ?? ''));
    $file = rainbow_whatsapp_followup_lead_file($phone, $leadId);
    if (rainbow_whatsapp_followup_is_opted_out($phone)) return ['ok'=>false,'code'=>'opted_out'];
    $handle = @fopen($file, 'x');
    if ($handle === false) return ['ok'=>true,'code'=>'duplicate','file'=>$file];

    $now = gmdate('c');
    $payload = [
        'phone'=>$phone,
        'lead_id'=>$leadId,
        'name'=>trim((string)($lead['name'] ?? '')),
        'source'=>trim((string)($lead['source'] ?? 'ytc_sheet_lead')),
        'created_at'=>$now,
        'replied_at'=>null,
        'opted_out_at'=>null,
        'followups'=>[],
    ];
    $encoded = json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $ok = is_string($encoded) && @fwrite($handle, $encoded) !== false;
    @fclose($handle);
    if (!$ok) { @unlink($file); return ['ok'=>false,'code'=>'state_write_failed']; }
    @chmod($file, 0640);
    rainbow_whatsapp_log(['event'=>'followup_registered','to'=>$phone,'lead_id'=>$leadId]);
    return ['ok'=>true,'code'=>'registered','file'=>$file];
}

function rainbow_whatsapp_followup_mark_replied(string $phone): void
{
    $phone = rainbow_whatsapp_normalize_phone($phone);
    if ($phone === '') return;
    foreach (glob(rainbow_whatsapp_followup_dir() . '/leads/*.json') ?: [] as $file) {
        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data) || (string)($data['phone'] ?? '') !== $phone) continue;
        if (($data['replied_at'] ?? null) === null) {
            $data['replied_at'] = gmdate('c');
            @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    }
    rainbow_whatsapp_log(['event'=>'followup_cancelled_on_reply','to'=>$phone]);
}

function rainbow_whatsapp_followup_handle_inbound(array $message): bool
{
    $from = rainbow_whatsapp_normalize_phone((string)($message['from'] ?? ''));
    if ($from === '') return false;
    $body = strtoupper(trim((string)($message['text']['body'] ?? '')));
    if (in_array($body, ['STOP','UNSUBSCRIBE','CANCEL','END','QUIT'], true)) {
        rainbow_whatsapp_followup_mark_optout($from, 'user_stop');
        rainbow_whatsapp_followup_mark_replied($from);
        return false;
    }
    rainbow_whatsapp_followup_mark_replied($from);
    return true;
}

function rainbow_whatsapp_followup_steps(): array
{
    return [
        1 => trim((string)(getenv('WHATSAPP_YTC_FOLLOWUP_DAY1_TEMPLATE') ?: 'ytc_followup_day1')),
        3 => trim((string)(getenv('WHATSAPP_YTC_FOLLOWUP_DAY3_TEMPLATE') ?: 'ytc_followup_day3')),
        7 => trim((string)(getenv('WHATSAPP_YTC_FOLLOWUP_DAY7_TEMPLATE') ?: 'ytc_followup_day7')),
    ];
}

function rainbow_whatsapp_followup_due(array $lead, int $day, int $now): bool
{
    if (!empty($lead['replied_at']) || !empty($lead['opted_out_at'])) return false;
    $created = strtotime((string)($lead['created_at'] ?? ''));
    if ($created === false) return false;
    if ($now < $created + ($day * 86400)) return false;
    return empty($lead['followups'][(string)$day]);
}

function rainbow_whatsapp_schedule_followups(?int $now=null, int $limit=100): array
{
    $now = $now ?? time();
    $queued = 0; $skipped = 0; $failed = 0;
    $files = glob(rainbow_whatsapp_followup_dir() . '/leads/*.json') ?: [];
    sort($files);
    foreach (array_slice($files, 0, max(1, min($limit, 500))) as $file) {
        $lead = json_decode((string)@file_get_contents($file), true);
        if (!is_array($lead)) { $failed++; continue; }
        $phone = rainbow_whatsapp_normalize_phone((string)($lead['phone'] ?? ''));
        if ($phone === '' || rainbow_whatsapp_followup_is_opted_out($phone)) { $skipped++; continue; }
        foreach (rainbow_whatsapp_followup_steps() as $day=>$template) {
            if (!rainbow_whatsapp_followup_due($lead, (int)$day, $now)) continue;
            $result = rainbow_whatsapp_queue([
                'to'=>$phone,
                'type'=>'template',
                'template'=>$template,
                'language'=>trim((string)(getenv('WHATSAPP_YTC_FOLLOWUP_LANGUAGE') ?: 'en')) ?: 'en',
                'source'=>'ytc_followup_day'.$day,
                'meta'=>['lead_id'=>$lead['lead_id'] ?? '', 'day'=>$day],
            ]);
            if (!($result['ok'] ?? false)) { $failed++; continue; }
            $lead['followups'][(string)$day] = ['queued_at'=>gmdate('c', $now), 'queue_id'=>$result['id'] ?? ''];
            @file_put_contents($file, json_encode($lead, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX);
            rainbow_whatsapp_log(['event'=>'followup_queued','to'=>$phone,'lead_id'=>$lead['lead_id'] ?? '','day'=>$day,'queue_id'=>$result['id'] ?? '']);
            $queued++;
        }
    }
    return ['queued'=>$queued,'skipped'=>$skipped,'failed'=>$failed,'tracked'=>count($files)];
}
