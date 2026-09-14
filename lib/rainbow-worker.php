<?php
declare(strict_types=1);
require_once __DIR__.'/rainbow-ai.php';

/** One bounded tick; existing browser callers must use the same workflow lock. */
function rainbow_worker_tick(int $limit=1): array
{
    $report=['advanced'=>0,'failed'=>0,'skipped'=>0,'errors'=>[]];
    $allowed=array_filter(array_map('trim',explode(',',(string)(getenv('RAINBOW_WORKER_WORKFLOW_IDS')?:''))));
    $daily=filter_var(getenv('RAINBOW_WORKER_DAILY_CALL_LIMIT'),FILTER_VALIDATE_INT);
    if(!$allowed||$daily===false||$daily<1||$daily>1000) return $report+['code'=>'worker_configuration_required'];
    foreach($allowed as $id) if(!preg_match('/^rw_[a-f0-9]{24}$/',$id)) return $report+['code'=>'worker_configuration_invalid'];
    $root=rainbow_private_root();
    $lock=fopen($root.'/rainbow-worker.lock','c');
    if($lock===false) throw new RuntimeException('worker_lock_unavailable');
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return $report+['busy'=>true];}
    try {
        $ledgerFile=$root.'/rainbow-worker-usage-'.gmdate('Y-m-d').'.json';
        $usage=is_file($ledgerFile)?json_decode((string)file_get_contents($ledgerFile),true):['reserved_calls'=>0];
        if(!is_array($usage)||!is_int($usage['reserved_calls']??null)||$usage['reserved_calls']<0) throw new RuntimeException('usage_ledger_invalid');
        $files=array_map('rainbow_workflow_file',$allowed);
        sort($files);
        foreach($files as $file){
            if($report['advanced']+$report['failed']>=max(1,min(5,$limit))) break;
            if(!is_file($file)){$report['skipped']++;continue;}
            $workflow=json_decode((string)file_get_contents($file),true);
            if(!is_array($workflow)||($workflow['status']??'')!=='running'){$report['skipped']++;continue;}
            $id=(string)($workflow['workflow_id']??'');
            try{
                if(basename($file)!==$id.'.json') throw new RuntimeException('workflow_id_mismatch');
                $context=rainbow_load_project((string)($workflow['project_id']??''));
                if($context===null) throw new RuntimeException('project_not_found');
                if($usage['reserved_calls']>=$daily){$report['code']='daily_call_limit_reached';break;}
                // Reserve before execution; crashes or failures never refund a possible API call.
                $usage['reserved_calls']++;
                rainbow_atomic_json_write($ledgerFile,$usage);
                $result=rainbow_advance_workflow($workflow,$context);
                if(($result['workflow']['status']??'')==='failed') $report['failed']++;
                else $report['advanced']++;
            }catch(Throwable $e){$report['failed']++;$report['errors'][]=['workflow_id'=>$id,'code'=>'workflow_tick_failed'];}
        }
        rainbow_atomic_json_write($root.'/rainbow-worker-status.json',$report+['checked_at'=>gmdate('c')]);
        return $report;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
