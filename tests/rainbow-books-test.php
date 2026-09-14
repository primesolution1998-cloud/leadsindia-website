<?php
declare(strict_types=1);
putenv('RAINBOW_TEST_MODE=1');
$root=sys_get_temp_dir().'/rainbow-book-test-'.bin2hex(random_bytes(6));
mkdir($root.'/public',0700,true);$_SERVER['DOCUMENT_ROOT']=$root.'/public';
require dirname(__DIR__).'/lib/rainbow-books.php';
function ok(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
$spec=['title'=>'Everyday English','subtitle'=>'Practice lessons','audience'=>'Adult beginners','language'=>'en',
    'mrp_paise'=>39900,'price_paise'=>19900,'chapter_titles'=>['Meeting people','Daily routines','Asking for help']];
$job=rainbow_create_book_job($spec);
ok(count($job['tasks'])===6,'incorrect stages');
ok(rainbow_create_book_job($spec)['workflow_id']===$job['workflow_id'],'duplicate spec created another job');
$other=$spec;$other['title']='Workplace English';
ok(rainbow_create_book_job($other)['project_id']!==$job['project_id'],'book context not isolated');
try{rainbow_export_book_manuscript($job['workflow_id']);throw new Exception('incomplete exported');}
catch(RuntimeException $e){ok($e->getMessage()==='book_not_completed','wrong incomplete error');}
$GLOBALS['rainbow_test_executor']=static function($ctx,$agent,$task,$prior):array{
    preg_match('/"chapter_title":"([^"]+)"/',$task,$match);
    return ['output'=>json_encode(['title'=>$match[1],'paragraphs'=>['Test content only.'],'exercises'=>[]]),'response_id'=>'mock'];
};
$ctx=rainbow_load_project($job['project_id']);
for($i=0;$i<6;$i++)rainbow_advance_workflow($job,$ctx);
$manuscript=rainbow_export_book_manuscript($job['workflow_id']);
ok(count($manuscript['chapters'])===3,'missing chapter');
ok($manuscript['chapters'][2]['title']==='Asking for help','chapter order wrong');
$bad=$spec;$bad['chapter_titles'][1]=$bad['chapter_titles'][0];
try{rainbow_book_spec($bad);throw new Exception('duplicate accepted');}
catch(RuntimeException $e){ok($e->getMessage()==='book_duplicate_chapter','wrong duplicate error');}
echo "PASS: book spec, idempotent create, isolation, writer/editor handoff, ordered export, incomplete rejection.\n";
