<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require dirname(__DIR__).'/lib/rainbow-worker.php';
rainbow_load_private_env();
if(getenv('RAINBOW_WORKER_ENABLED')!=='1'){
    echo json_encode(['enabled'=>false,'code'=>'worker_disabled']).PHP_EOL;exit(0);
}
try{
    $report=rainbow_worker_tick(1);
    echo json_encode($report,JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($report['failed']>0?2:0);
}catch(Throwable $e){fwrite(STDERR,"worker_failed\n");exit(2);}
