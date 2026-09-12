<?php

declare(strict_types=1);
putenv('RAINBOW_TEST_MODE=1');
$_SERVER['DOCUMENT_ROOT']=dirname(__DIR__);
require dirname(__DIR__).'/lib/rainbow-ai.php';
if(session_status()!==PHP_SESSION_ACTIVE) session_start();

function expect_true(bool $value,string $message): void {if(!$value) throw new RuntimeException($message);}

$GLOBALS['rainbow_test_executor']=static function(array $context,string $agent,string $task,array $prior): array {
    $handoff=count($prior)>0?' handoff='.($prior[array_key_last($prior)]['execution_id']??'missing'):'';
    return ['output'=>'DELIVERABLE ['.$context['project_name'].'] ['.$agent.']'.$handoff,'response_id'=>'test_response'];
};

$context=rainbow_build_context('YTC Library project. Create a project brief for a 30-page Basic English Speaking book. Do not publish.');
expect_true($context['project_name']==='YTC Library','Context isolation failed');
expect_true($context['business']==='YTC Education','Business isolation failed');
expect_true(rainbow_external_approval_reason($context['objective'])===null,'Negated publish was incorrectly blocked');

$pm=rainbow_run_agent($context,'Project Manager','Produce the completed project brief now.',[]);
expect_true($pm['status']==='completed'&&str_contains((string)$pm['output'],'YTC Library'),'Project Manager execution failed');
$writer=rainbow_run_agent($context,'Content Writer','Write the first sample chapter.',[['execution_id'=>$pm['execution_id'],'agent'=>$pm['agent'],'task'=>$pm['task'],'output'=>$pm['output']]]);
expect_true($writer['status']==='completed'&&str_contains((string)$writer['output'],(string)$pm['execution_id']),'Writer handoff failed');
$editor=rainbow_run_agent($context,'Editor / Proofreader','Revise the sample chapter.',[['execution_id'=>$writer['execution_id'],'agent'=>$writer['agent'],'task'=>$writer['task'],'output'=>$writer['output']]]);
expect_true($editor['status']==='completed'&&str_contains((string)$editor['output'],(string)$writer['execution_id']),'Editor handoff failed');

$blocked=rainbow_run_agent($context,'Project Manager','Publish a Meta campaign and spend INR 500.',[]);
expect_true($blocked['status']==='blocked_for_approval'&&$blocked['approval_required']===true&&$blocked['output']===null,'Safety gate failed');

foreach([$pm,$writer,$editor,$blocked] as $run){
    $stored=json_decode((string)file_get_contents(rainbow_execution_dir().'/'.$run['execution_id'].'.json'),true);
    expect_true(is_array($stored)&&$stored['status']===$run['status'],'Persistence check failed');
}
echo "PASS context_isolation\nPASS real_execution\nPASS multi_agent_handoff\nPASS safety_gate\nPASS persistence\n";
