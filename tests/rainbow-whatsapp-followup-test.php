<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-whatsapp.php';
require dirname(__DIR__) . '/lib/rainbow-whatsapp-followup.php';

$failures=[];
function f_expect(bool $ok,string $message): void { global $failures; if(!$ok)$failures[]=$message; }
function f_cleanup(string $phone,string $leadId): void {
    @unlink(rainbow_whatsapp_followup_lead_file($phone,$leadId));
    @unlink(rainbow_whatsapp_followup_optout_file($phone));
    foreach(glob(rainbow_whatsapp_private_dir().'/outbox/*.json')?:[] as $file){
        $p=json_decode((string)@file_get_contents($file),true);
        if(is_array($p) && (string)($p['meta']['lead_id']??'')===$leadId) @unlink($file);
    }
}

putenv('WHATSAPP_AUTOMATION_ENABLED=0');
$phone='919111112222'; $leadId='followup-test-1';
f_cleanup($phone,$leadId);
$r=rainbow_whatsapp_followup_register(['phone'=>$phone,'lead_id'=>$leadId,'name'=>'Test Lead']);
f_expect(($r['code']??'')==='registered','First registration must succeed');
$r2=rainbow_whatsapp_followup_register(['phone'=>$phone,'lead_id'=>$leadId,'name'=>'Test Lead']);
f_expect(($r2['code']??'')==='duplicate','Duplicate registration must be suppressed');
$file=rainbow_whatsapp_followup_lead_file($phone,$leadId);
$lead=json_decode((string)file_get_contents($file),true);
$lead['created_at']=gmdate('c',time()-8*86400);
file_put_contents($file,json_encode($lead,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
$scheduled=rainbow_whatsapp_schedule_followups(time(),100);
f_expect(($scheduled['queued']??0)>=3,'Day 1/3/7 follow-ups must queue when overdue');
$scheduled2=rainbow_whatsapp_schedule_followups(time(),100);
f_expect(($scheduled2['queued']??-1)===0,'Already queued follow-ups must not duplicate');

$phone2='919111113333'; $leadId2='followup-test-2';
f_cleanup($phone2,$leadId2);
rainbow_whatsapp_followup_register(['phone'=>$phone2,'lead_id'=>$leadId2,'name'=>'Reply Lead']);
$allow=rainbow_whatsapp_followup_handle_inbound(['from'=>$phone2,'type'=>'text','text'=>['body'=>'Hi']]);
f_expect($allow===true,'Normal reply should allow acknowledgement');
$lead2=json_decode((string)file_get_contents(rainbow_whatsapp_followup_lead_file($phone2,$leadId2)),true);
f_expect(!empty($lead2['replied_at']),'Inbound reply must cancel future follow-ups');
$lead2['created_at']=gmdate('c',time()-8*86400);
file_put_contents(rainbow_whatsapp_followup_lead_file($phone2,$leadId2),json_encode($lead2,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
$afterReply=rainbow_whatsapp_schedule_followups(time(),100);
f_expect(($afterReply['queued']??0)===0,'Replied lead must not receive follow-ups');
