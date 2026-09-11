<?php

declare(strict_types=1);

function rainbow_whatsapp_access_token(): string
{
    return trim((string)(getenv('WHATSAPP_ACCESS_TOKEN') ?: ''));
}

function rainbow_whatsapp_phone_number_id(): string
{
    return trim((string)(getenv('WHATSAPP_PHONE_NUMBER_ID') ?: ''));
}

function rainbow_whatsapp_waba_id(): string
{
    return trim((string)(getenv('WHATSAPP_WABA_ID') ?: ''));
}

function rainbow_whatsapp_probe(): array
{
    $token = rainbow_whatsapp_access_token();
    $phoneId = rainbow_whatsapp_phone_number_id();
    $wabaId = rainbow_whatsapp_waba_id();

    if ($token === '' || $phoneId === '' || $wabaId === '') {
        return ['connected'=>false,'configured'=>false,'code'=>'whatsapp_not_configured','phone'=>null];
    }
    if (!function_exists('curl_init')) {
        return ['connected'=>false,'configured'=>true,'code'=>'curl_unavailable','phone'=>null];
    }

    $url = 'https://graph.facebook.com/v23.0/' . rawurlencode($phoneId) . '?fields=id,display_phone_number,verified_name,quality_rating';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $errno !== 0) {
        return ['connected'=>false,'configured'=>true,'code'=>'network_error','phone'=>null];
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data) || isset($data['error']) || $http < 200 || $http >= 300) {
        return ['connected'=>false,'configured'=>true,'code'=>'probe_failed','phone'=>null];
    }
    if ((string)($data['id'] ?? '') !== $phoneId) {
        return ['connected'=>false,'configured'=>true,'code'=>'phone_id_mismatch','phone'=>null];
    }

    return [
        'connected'=>true,
        'configured'=>true,
        'code'=>'connected',
        'phone'=>[
            'id'=>$phoneId,
            'display_phone_number'=>(string)($data['display_phone_number'] ?? ''),
            'verified_name'=>(string)($data['verified_name'] ?? ''),
            'quality_rating'=>(string)($data['quality_rating'] ?? ''),
        ],
    ];
}
