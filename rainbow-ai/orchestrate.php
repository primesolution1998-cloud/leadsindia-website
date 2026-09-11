<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/rainbow-ai.php';
rainbow_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') rainbow_json(['ok'=>false,'code'=>'method_not_allowed','message'=>'POST required.'],405);
rainbow_require_admin_json();
$contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
if(!str_starts_with($contentType,'application/json')) rainbow_json(['ok'=>false,'code'=>'invalid_content_type','message'=>'application/json required.'],415);
$raw=file_get_contents('php://input');
if($raw===false||strlen($raw)>20000) rainbow_json(['ok'=>false,'code'=>'invalid_request','message'=>'Request is too large or unreadable.'],400);
$body=json_decode($raw,true); if(!is_array($body)) rainbow_json(['ok'=>false,'code'=>'invalid_json','message'=>'Invalid JSON request.'],400);
$csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??($body['csrf']??''));
if(!rainbow_verify_csrf($csrf)) rainbow_json(['ok'=>false,'code'=>'csrf_failed','message'=>'Security token expired. Refresh and try again.'],403);
if(!rainbow_rate_limit()) rainbow_json(['ok'=>false,'code'=>'rate_limited','message'=>'Too many commands. Wait a minute and try again.'],429);
$command=trim((string)($body['command']??''));
$length=function_exists('mb_strlen')?mb_strlen($command,'UTF-8'):strlen($command);
if($length<3||$length>4000) rainbow_json(['ok'=>false,'code'=>'invalid_command','message'=>'Command must be between 3 and 4000 characters.'],422);
if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$command)) rainbow_json(['ok'=>false,'code'=>'invalid_command','message'=>'Command contains unsupported control characters.'],422);

function rainbow_activate_business_owner(array $plan): array
{
    $agents = $plan['required_agents'] ?? [];
    if (!is_array($agents)) $agents = [];
    $agents = array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $agents), static fn(string $v): bool => $v !== '' && strcasecmp($v, 'Business Owner') !== 0));
    array_unshift($agents, 'Business Owner');
    $plan['required_agents'] = $agents;

    $steps = $plan['steps'] ?? [];
    if (!is_array($steps)) $steps = [];
    $ownerStep = [
        'step_number' => 1,
        'agent' => 'Business Owner',
        'action' => 'Interpret the business objective, set priority and risk boundaries, then route the work to the required specialist agents. External execution remains approval-gated.',
        'execution_allowed' => false,
    ];
    $normalised = [$ownerStep];
    $n = 2;
    foreach ($steps as $step) {
        if (!is_array($step)) continue;
        if (strcasecmp((string)($step['agent'] ?? ''), 'Business Owner') === 0) continue;
        $step['step_number'] = $n++;
        $step['execution_allowed'] = false;
        $normalised[] = $step;
    }
    $plan['steps'] = $normalised;
    return $plan;
}

try{
    $result=rainbow_openai_request($command);
    $plan=rainbow_activate_business_owner($result['plan']);
    rainbow_json([
        'ok'=>true,
        'state'=>'success',
        'execution_performed'=>false,
        'orchestrator'=>[
            'name'=>'Business Owner',
            'status'=>'active',
            'mode'=>'approval_first',
            'scope'=>'planning_routing_and_risk_control',
            'external_execution'=>false,
        ],
        'plan'=>$plan,
        'meta'=>['response_id'=>$result['response_id'],'model'=>$result['model']]
    ]);
}catch(RuntimeException $e){
    $code=$e->getMessage();
    $map=[
        'openai_not_configured'=>[503,'OpenAI is not configured on the server.'],
        'curl_unavailable'=>[503,'Server HTTP client is unavailable.'],
        'openai_auth_error'=>[502,'OpenAI rejected the server credentials.'],
        'openai_rate_limited'=>[429,'OpenAI rate limit reached. Try again shortly.'],
        'openai_network_error'=>[504,'Could not reach OpenAI. Try again shortly.'],
        'openai_upstream_error'=>[502,'OpenAI is temporarily unavailable.'],
        'openai_request_error'=>[502,'OpenAI rejected the request.'],
        'openai_invalid_response'=>[502,'OpenAI returned an invalid response.'],
        'openai_incomplete_response'=>[502,'OpenAI response was incomplete. Please retry the command.'],
        'openai_empty_response'=>[502,'OpenAI returned no plan.'],
        'openai_invalid_json'=>[502,'OpenAI returned malformed plan data.'],
        'openai_schema_mismatch'=>[502,'OpenAI returned a plan that failed validation.']
    ];
    [$status,$message]=$map[$code]??[500,'Rainbow AI could not prepare the plan.'];
    rainbow_json(['ok'=>false,'code'=>$code,'state'=>'failure','message'=>$message],$status);
}
