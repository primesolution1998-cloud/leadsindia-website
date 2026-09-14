<?php
declare(strict_types=1);
require_once __DIR__.'/rainbow-ai.php';

/** One bounded tick; existing browser callers must use the same workflow lock. */
function rainbow_worker_tick(int $limit=1): array
{
    $report=['advanced'=>0,'failed'=>0,'skipped'=>0,'errors'=>[]];
    $root=rainbow_private_root();
    $lock=fopen($root.'/rainbow-worker.lock','c');
    if($lock===false) throw new RuntimeException('worker_lock_unavailable');
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return $report+['busy'=>true];}
    try {
        $files=glob(rainbow_workflow_dir().'/rw_*.json')?:[];
        sort($files);
        foreach($files as $file){
            if($report['advanced']+$report['failed']>=max(1,min(5,$limit))) break;
            $workflow=json_decode((string)file_get_contents($file),true);
            if(!is_array($workflow)||($workflow['status']??'')!=='running'){$report['skipped']++;continue;}
            $id=(string)($workflow['workflow_id']??'');
            try{
                if(basename($file)!==$id.'.json') throw new RuntimeException('workflow_id_mismatch');
                $context=rainbow_load_project((string)($workflow['project_id']??''));
                if($context===null) throw new RuntimeException('project_not_found');
                $result=rainbow_advance_workflow($workflow,$context);
                if(($result['workflow']['status']??'')==='failed') $report['failed']++;
                else $report['advanced']++;
            }catch(Throwable $e){$report['failed']++;$report['errors'][]=['workflow_id'=>$id,'code'=>'workflow_tick_failed'];}
        }
        rainbow_atomic_json_write($root.'/rainbow-worker-status.json',$report+['checked_at'=>gmdate('c')]);
        return $report;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
