<?php

declare(strict_types=1);

function rainbow_whatsapp_access_token(): string { return trim((string)(getenv('WHATSAPP_ACCESS_TOKEN') ?: '')); }
function rainbow_whatsapp_phone_number_id(): string { return trim((string)(getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '')); }
function rainbow_whatsapp_waba_id(): string { return trim((string)(getenv('WHATSAPP_WABA_ID') ?: '')); }
function rainbow_whatsapp_enabled(): bool { return in_array(strtolower(trim((string)(getenv('WHATSAPP_AUTOMATION_ENABLED') ?: '0'))), ['1','true','yes','on'], true); }

function rainbow_whatsapp_private_dir(): string
{
    $dir = dirname(dirname(__DIR__)) . '/leadsindia-private/rainbow-whatsapp';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    foreach (['outbox','inbox','logs','dead-letter'] as $child) {
        $path = $dir . '/' . $child;
        if (!is_dir($path)) @mkdir($path, 0750, true);
    }
    return $dir;
}

function rainbow_whatsapp_log(array $entry): void
{
    $entry['logged_at'] = gmdate('c');
    $file = rainbow_whatsapp_private_dir() . '/logs/' . gmdate('Y-m-d') . '.jsonl';
    @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND|LOCK_EX);
    @chmod($file, 0640);
}

function rainbow_whatsapp_normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') return '';
    if (strlen($digits) === 10) $digits = '91' . $digits;
    return (strlen($digits) >= 10 && strlen($digits) <= 15) ? $digits : '';
}

function rainbow_whatsapp_probe(): array
{
    $token = rainbow_whatsapp_access_token(); $phoneId = rainbow_whatsapp_phone_number_id(); $wabaId = rainbow_whatsapp_waba_id();
    if ($token === '' || $phoneId === '' || $wabaId === '') return ['connected'=>false,'configured'=>false,'code'=>'whatsapp_not_configured','phone'=>null];
    if (!function_exists('curl_init')) return ['connected'=>false,'configured'=>true,'code'=>'curl_unavailable','phone'=>null];
    $url = 'https://graph.facebook.com/v23.0/' . rawurlencode($phoneId) . '?fields=id,display_phone_number,verified_name,quality_rating';
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json']]);
    $raw = curl_exec($ch); $errno = curl_errno($ch); $http = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($raw === false || $errno !== 0) return ['connected'=>false,'configured'=>true,'code'=>'network_error','phone'=>null];
    $data = json_decode((string)$raw,true);
    if (!is_array($data) || isset($data['error']) || $http < 200 || $http >= 300) return ['connected'=>false,'configured'=>true,'code'=>'probe_failed','phone'=>null];
    if ((string)($data['id'] ?? '') !== $phoneId) return ['connected'=>false,'configured'=>true,'code'=>'phone_id_mismatch','phone'=>null];
    return ['connected'=>true,'configured'=>true,'code'=>'connected','automation_enabled'=>rainbow_whatsapp_enabled(),'phone'=>['id'=>$phoneId,'display_phone_number'=>(string)($data['display_phone_number']??''),'verified_name'=>(string)($data['verified_name']??''),'quality_rating'=>(string)($data['quality_rating']??'')]];
}

function rainbow_whatsapp_queue(array $message): array
{
    $to = rainbow_whatsapp_normalize_phone((string)($message['to'] ?? ''));
    if ($to === '') return ['ok'=>false,'code'=>'invalid_phone'];
    $type = (string)($message['type'] ?? 'template');
    if (!in_array($type,['template','text'],true)) return ['ok'=>false,'code'=>'invalid_type'];
    $id = 'wa-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(6));
    $payload = ['id'=>$id,'to'=>$to,'type'=>$type,'template'=>(string)($message['template']??''),'language'=>(string)($message['language']??'en'),'components'=>is_array($message['components']??null)?$message['components']:[],'text'=>(string)($message['text']??''),'source'=>(string)($message['source']??'rainbow'),'meta'=>is_array($message['meta']??null)?$message['meta']:[],'attempts'=>0,'created_at'=>gmdate('c')];
    if ($type === 'template' && $payload['template'] === '') return ['ok'=>false,'code'=>'template_required'];
    if ($type === 'text' && trim($payload['text']) === '') return ['ok'=>false,'code'=>'text_required'];
    $file = rainbow_whatsapp_private_dir().'/outbox/'.$id.'.json';
    if (@file_put_contents($file,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX)===false) return ['ok'=>false,'code'=>'queue_write_failed'];
    @chmod($file,0640); rainbow_whatsapp_log(['event'=>'queued','id'=>$id,'to'=>$to,'type'=>$type,'source'=>$payload['source']]);
    return ['ok'=>true,'code'=>'queued','id'=>$id];
}

function rainbow_whatsapp_api_send(array $payload): array
{
    if (!rainbow_whatsapp_enabled()) return ['ok'=>false,'code'=>'automation_disabled','http'=>0];
    if (!function_exists('curl_init')) return ['ok'=>false,'code'=>'curl_unavailable','http'=>0];
    $token = rainbow_whatsapp_access_token(); $phoneId = rainbow_whatsapp_phone_number_id();
    if ($token === '' || $phoneId === '') return ['ok'=>false,'code'=>'whatsapp_not_configured','http'=>0];
    $body = ['messaging_product'=>'whatsapp','to'=>$payload['to']];
    if (($payload['type']??'') === 'template') {
        $body['type']='template'; $body['template']=['name'=>$payload['template'],'language'=>['code'=>$payload['language'] ?: 'en']];
        if (!empty($payload['components'])) $body['template']['components']=$payload['components'];
    } else { $body['type']='text'; $body['text']=['preview_url'=>false,'body'=>$payload['text']]; }
    $ch = curl_init('https://graph.facebook.com/v23.0/'.rawurlencode($phoneId).'/messages');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json']]);
    $raw=curl_exec($ch); $errno=curl_errno($ch); $http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch);
    $data=is_string($raw)?json_decode($raw,true):null;
    if ($errno===0 && $http>=200 && $http<300 && is_array($data) && !isset($data['error'])) return ['ok'=>true,'code'=>'sent','http'=>$http,'message_id'=>(string)($data['messages'][0]['id']??'')];
    return ['ok'=>false,'code'=>$errno!==0?'network_error':'api_error','http'=>$http,'error'=>$err!==''?$err:(string)($data['error']['message']??'send_failed')];
}

function rainbow_whatsapp_dispatch_pending(int $limit=20): array
{
    $files=glob(rainbow_whatsapp_private_dir().'/outbox/*.json')?:[]; sort($files); $sent=0;$failed=0;$skipped=0;
    foreach(array_slice($files,0,max(1,min($limit,100))) as $file){
        $payload=json_decode((string)@file_get_contents($file),true); if(!is_array($payload)){@rename($file,rainbow_whatsapp_private_dir().'/dead-letter/'.basename($file));$failed++;continue;}
        $res=rainbow_whatsapp_api_send($payload);
        if($res['ok']){@unlink($file);$sent++;rainbow_whatsapp_log(['event'=>'sent','id'=>$payload['id']??'','to'=>$payload['to']??'','message_id'=>$res['message_id']??'','http'=>$res['http']??0]);continue;}
        if(($res['code']??'')==='automation_disabled'){$skipped++;break;}
        $payload['attempts']=(int)($payload['attempts']??0)+1;$payload['last_error']=$res['error']??$res['code'];$payload['last_attempt_at']=gmdate('c');
        if($payload['attempts']>=5){@file_put_contents($file,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX);@rename($file,rainbow_whatsapp_private_dir().'/dead-letter/'.basename($file));}
        else @file_put_contents($file,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX);
        $failed++; rainbow_whatsapp_log(['event'=>'send_failed','id'=>$payload['id']??'','to'=>$payload['to']??'','code'=>$res['code']??'','http'=>$res['http']??0]);
    }
    return ['sent'=>$sent,'failed'=>$failed,'skipped'=>$skipped,'remaining'=>count(glob(rainbow_whatsapp_private_dir().'/outbox/*.json')?:[])];
}

function rainbow_whatsapp_store_webhook(array $event): void
{
    $id='in-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(5));
    @file_put_contents(rainbow_whatsapp_private_dir().'/inbox/'.$id.'.json',json_encode(['received_at'=>gmdate('c'),'event'=>$event],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX);
}

function rainbow_whatsapp_process_webhook(array $event): array
{
    rainbow_whatsapp_store_webhook($event); $queued=0;$statuses=0;
    foreach(($event['entry']??[]) as $entry) foreach(($entry['changes']??[]) as $change){$value=$change['value']??[];
        foreach(($value['statuses']??[]) as $status){$statuses++;rainbow_whatsapp_log(['event'=>'delivery_status','message_id'=>(string)($status['id']??''),'status'=>(string)($status['status']??''),'recipient'=>(string)($status['recipient_id']??''),'timestamp'=>(string)($status['timestamp']??'')]);}
        foreach(($value['messages']??[]) as $message){$from=rainbow_whatsapp_normalize_phone((string)($message['from']??''));$messageId=(string)($message['id']??'');$type=(string)($message['type']??'');rainbow_whatsapp_log(['event'=>'inbound','message_id'=>$messageId,'from'=>$from,'type'=>$type]);
            $allowAutoReply=true;if(function_exists('rainbow_whatsapp_followup_handle_inbound')){$allowAutoReply=rainbow_whatsapp_followup_handle_inbound($message);}
            if($from!=='' && $allowAutoReply && rainbow_whatsapp_enabled()){$reply=trim((string)(getenv('WHATSAPP_AUTO_REPLY_TEXT')?:'Thanks for contacting YTC Education. Our team will assist you shortly.'));$q=rainbow_whatsapp_queue(['to'=>$from,'type'=>'text','text'=>$reply,'source'=>'inbound_auto_reply','meta'=>['in_reply_to'=>$messageId]]);if($q['ok'])$queued++;}
        }
    }
    return ['ok'=>true,'queued_replies'=>$queued,'statuses'=>$statuses];
}
