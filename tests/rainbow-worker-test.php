<?php
declare(strict_types=1);
putenv('RAINBOW_TEST_MODE=1');
$root=sys_get_temp_dir().'/rainbow-worker-test-'.bin2hex(random_bytes(6));
mkdir($root.'/public',0700,true);
$_SERVER['DOCUMENT_ROOT']=$root.'/public';
require dirname(__DIR__).'/lib/rainbow-worker.php';
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$calls=0;
$GLOBALS['rainbow_test_executor']=static function()use(&$calls):array{$calls++;return ['output'=>'Complete test content','response_id'=>'mock'];};
$ctx=rainbow_build_context('YTC Library project. Write a book.');
$flow=rainbow_start_book_workflow($ctx,'Complete internal manuscript.');
$r=rainbow_worker_tick();
check($r['advanced']===1&&$calls===1,'worker must execute one stage');
check(rainbow_load_workflow($flow['workflow_id'])['cursor']===1,'cursor persists');
$r=rainbow_advance_workflow($flow,$ctx);
check($r['workflow']['cursor']===2,'stale caller must reload persisted cursor');
$lock=fopen(rainbow_workflow_file($flow['workflow_id']).'.lock','c');
flock($lock,LOCK_EX);
try{rainbow_advance_workflow($flow,$ctx);throw new Exception('lock bypass');}
catch(RuntimeException $e){check($e->getMessage()==='workflow_busy','wrong lock error');}
flock($lock,LOCK_UN);fclose($lock);
try{rainbow_advance_workflow($flow,['project_id'=>'another-project']);throw new Exception('isolation bypass');}
catch(RuntimeException $e){check($e->getMessage()==='workflow_project_mismatch','wrong context error');}
for($i=0;$i<6;$i++)rainbow_worker_tick();
check(rainbow_load_workflow($flow['workflow_id'])['status']==='completed','workflow did not finish');
check($calls===8,'unexpected extra API executions');
rainbow_worker_tick();check($calls===8,'completed task rerun');
$flow=rainbow_start_book_workflow($ctx,'Second manuscript.');
$GLOBALS['rainbow_test_executor']=static function():array{throw new RuntimeException('openai_incomplete_response');};
$r=rainbow_worker_tick();check($r['failed']===1,'failure not recorded');
check(rainbow_load_workflow($flow['workflow_id'])['cursor']===0,'failed stage advanced');
rainbow_worker_tick();check($calls===8,'failed job retried without review');
$lock=fopen(rainbow_private_root().'/rainbow-worker.lock','c');flock($lock,LOCK_EX);
check((rainbow_worker_tick()['busy']??false)===true,'scheduler overlap allowed');
flock($lock,LOCK_UN);fclose($lock);
echo "PASS: bounded execution, persistence, stale reads, locks, context isolation, completion, failure stop.\n";
