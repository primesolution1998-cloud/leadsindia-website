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
$body=json_decode($raw,true);
if(!is_array($body)) rainbow_json(['ok'=>false,'code'=>'invalid_json','message'=>'Invalid JSON request.'],400);
$csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??($body['csrf']??''));
if(!rainbow_verify_csrf($csrf)) rainbow_json(['ok'=>false,'code'=>'csrf_failed','message'=>'Security token expired. Refresh and try again.'],403);
if(!rainbow_rate_limit()) rainbow_json(['ok'=>false,'code'=>'rate_limited','message'=>'Too many commands. Wait a minute and try again.'],429);
$command=trim((string)($body['command']??''));
$length=function_exists('mb_strlen')?mb_strlen($command,'UTF-8'):strlen($command);
if($length<3||$length>4000) rainbow_json(['ok'=>false,'code'=>'invalid_command','message'=>'Command must be between 3 and 4000 characters.'],422);
if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$command)) rainbow_json(['ok'=>false,'code'=>'invalid_command','message'=>'Command contains unsupported control characters.'],422);

try{
    $context=rainbow_build_context($command);
    $plannerInput=json_encode(['project_context'=>$context,'user_command'=>$command],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $result=rainbow_openai_request((string)$plannerInput);
    $plan=$result['plan'];
    $prior=rainbow_recent_project_outputs((string)$context['project_id']);
    $executions=[];$steps=[];$globalApproval=rainbow_external_approval_reason($command);

    foreach(($plan['steps']??[]) as $step){
        if(!is_array($step)) continue;
        $agent=rainbow_allowed_agent((string)($step['agent']??''));
        $task=trim((string)($step['action']??''));
        if($task===''||$agent==='Business Owner') continue;
        $steps[]=['step_number'=>count($steps)+1,'agent'=>$agent,'action'=>$task,'execution_allowed'=>$globalApproval===null&&(bool)($step['execution_allowed']??false)];
    }
    foreach($steps as $step){
        if(!rainbow_task_respects_context($context,(string)$step['action'])){
            $steps=[['step_number'=>1,'agent'=>'Project Manager','action'=>$command,'execution_allowed'=>$globalApproval===null]];
            break;
        }
    }

    $forceExecute=(bool)preg_match('/\b(execute|produce(?: the)? deliverable|completed|create|write|design|revise|proofread|do not create another plan|now)\b/i',$command);
    if($forceExecute&&$globalApproval===null&&count($steps)===0){
        $agent='Project Manager';
        if(preg_match('/\bcontent writer\b/i',$command))$agent='Content Writer';
        elseif(preg_match('/\beditor|proofreader\b/i',$command))$agent='Editor / Proofreader';
        elseif(preg_match('/\bcurriculum designer\b/i',$command))$agent='Curriculum Designer';
        elseif(preg_match('/\bcover designer|creative planner\b/i',$command))$agent='Cover Designer / Creative Planner';
        elseif(preg_match('/\blayout|publishing specialist\b/i',$command))$agent='Layout / Publishing Specialist';
        $steps[]=['step_number'=>1,'agent'=>$agent,'action'=>$command,'execution_allowed'=>true];
    }

    if($globalApproval!==null){
        $blocked=rainbow_run_agent($context,'Project Manager',$command,$prior);
        $executions[]=$blocked;
        $steps=[['step_number'=>1,'agent'=>$blocked['agent'],'action'=>$command,'execution_allowed'=>false]];
    }else{
        // One specialist per web request keeps execution within shared-hosting timeouts.
        // Completed output is persisted and becomes the next command's handoff context.
        foreach(array_slice($steps,0,1) as $step){
            if(!$forceExecute&&empty($step['execution_allowed'])) continue;
            $execution=rainbow_run_agent($context,$step['agent'],$step['action'],$prior);
            $executions[]=$execution;
            if($execution['status']==='completed')$prior[]=['execution_id'=>$execution['execution_id'],'agent'=>$execution['agent'],'task'=>$execution['task'],'output'=>$execution['output']];
            if($execution['status']!=='completed') break;
        }
    }

    $plan['steps']=$steps;
    $plan['required_agents']=array_values(array_unique(array_merge(['Business Owner'],array_column($steps,'agent'))));
    $plan['approvals_required']=$globalApproval!==null?[['type'=>$globalApproval,'reason'=>'External or sensitive action is approval-gated and was not executed.']]:[];
    $statuses=array_column($executions,'status');
    $state=in_array('failed',$statuses,true)?'failed':(in_array('blocked_for_approval',$statuses,true)?'blocked_for_approval':(count($executions)>0&&count(array_filter($statuses,static fn($s)=>$s==='completed'))===count($executions)?'completed':'planned'));
    rainbow_json([
        'ok'=>true,'state'=>$state,'execution_performed'=>in_array('completed',$statuses,true),
        'context'=>$context,'plan'=>$plan,'executions'=>$executions,
        'orchestrator'=>['name'=>'Business Owner','status'=>'completed','mode'=>'planner','external_execution'=>false],
        'specialists'=>array_map(static fn(array $e):array=>['name'=>$e['agent'],'status'=>$e['status'],'execution_id'=>$e['execution_id']],$executions),
        'meta'=>['response_id'=>$result['response_id'],'model'=>$result['model']]
    ]);
}catch(RuntimeException $e){
    $code=$e->getMessage();
    $status=in_array($code,['openai_rate_limited'],true)?429:(str_contains($code,'storage')||str_contains($code,'persist')?503:502);
    $safe=['openai_not_configured'=>'OpenAI is not configured on the server.','openai_auth_error'=>'OpenAI rejected the server credentials.','openai_rate_limited'=>'OpenAI rate limit reached. Try again shortly.','openai_network_error'=>'Could not reach OpenAI. Try again shortly.','openai_upstream_error'=>'OpenAI is temporarily unavailable.','openai_request_error'=>'OpenAI rejected the request.','openai_invalid_response'=>'OpenAI returned an invalid response.','openai_incomplete_response'=>'OpenAI response was incomplete. Please retry.','openai_empty_response'=>'OpenAI returned no output.','openai_invalid_json'=>'OpenAI returned malformed planning data.','openai_schema_mismatch'=>'OpenAI planning data failed validation.','execution_storage_unavailable'=>'Secure execution storage is unavailable.','execution_persist_failed'=>'Execution output could not be stored.'];
    rainbow_json(['ok'=>false,'code'=>$code,'state'=>'failed','message'=>$safe[$code]??'Rainbow AI execution failed safely.'],$status);
}
