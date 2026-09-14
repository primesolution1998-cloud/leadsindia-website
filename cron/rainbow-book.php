<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require dirname(__DIR__).'/lib/rainbow-books.php';
rainbow_load_private_env();
try{
    $command=$argv[1]??'';
    if($command==='create'){
        $file=$argv[2]??'';
        if(!is_file($file)||filesize($file)>50000) throw new RuntimeException('spec_file_invalid');
        $spec=json_decode((string)file_get_contents($file),true);
        if(!is_array($spec)) throw new RuntimeException('spec_file_invalid');
        $job=rainbow_create_book_job($spec);
        echo json_encode(['workflow_id'=>$job['workflow_id'],'status'=>$job['status'],'stages'=>count($job['tasks']),'enrolled'=>false]).PHP_EOL;
    }elseif($command==='export'){
        echo json_encode(rainbow_export_book_manuscript($argv[2]??''),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
    }else{throw new RuntimeException('usage_create_spec_or_export_workflow');}
}catch(Throwable $e){fwrite(STDERR,$e instanceof RuntimeException?$e->getMessage().PHP_EOL:"book_command_failed\n");exit(2);}
