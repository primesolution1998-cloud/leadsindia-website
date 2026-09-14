<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/rainbow-ai.php';
require dirname(__DIR__) . '/lib/rainbow-whatsapp.php';
rainbow_bootstrap();
if (!rainbow_admin_logged_in()) { header('Location: /rainbow-ai/login.php'); exit; }

function wa_setup_env_path(): string {
    return dirname(dirname(__DIR__)) . '/leadsindia-private/.rainbow-ai.env';
}
function wa_setup_current(string $key): bool {
    return trim((string)(getenv($key) ?: '')) !== '';
}
function wa_setup_write(array $updates): bool {
    $path = wa_setup_env_path(); $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return false;
    $lines = is_readable($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
    $map=[]; $order=[];
    foreach($lines as $line){
        if(!str_contains($line,'=')){ $order[]=['raw',$line]; continue; }
        [$k,$v]=explode('=',$line,2); $k=trim($k);
        if(!preg_match('/^[A-Z0-9_]+$/',$k)){ $order[]=['raw',$line]; continue; }
        $map[$k]=$v; $order[]=['key',$k];
    }
    foreach($updates as $k=>$v){ $map[$k]=$v; putenv($k.'='.$v); $_ENV[$k]=$v; }
    $seen=[]; $out=[];
    foreach($order as [$type,$value]){
        if($type==='raw'){ $out[]=$value; continue; }
        if(isset($seen[$value])) continue; $seen[$value]=true;
        $out[]=$value.'='.($map[$value]??'');
    }
    foreach($map as $k=>$v){ if(!isset($seen[$k])) $out[]=$k.'='.$v; }
    $tmp=$path.'.tmp.'.bin2hex(random_bytes(4));
    if(@file_put_contents($tmp,implode("\n",$out)."\n",LOCK_EX)===false) return false;
    @chmod($tmp,0640); if(!@rename($tmp,$path)){ @unlink($tmp); return false; }
    @chmod($path,0640); return true;
}

$message=''; $probe=null; $newVerify='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $csrf=(string)($_POST['csrf']??'');
    if(!rainbow_verify_csrf($csrf)){ $message='Invalid CSRF token.'; }
    else {
        $allowed=['WHATSAPP_ACCESS_TOKEN','WHATSAPP_PHONE_NUMBER_ID','WHATSAPP_WABA_ID','META_APP_SECRET','WHATSAPP_YTC_LEAD_TEMPLATE','WHATSAPP_YTC_LEAD_TEMPLATE_LANGUAGE'];
        $updates=[];
        foreach($allowed as $k){ $v=trim((string)($_POST[$k]??'')); if($v!=='') $updates[$k]=$v; }
        if(!wa_setup_current('WHATSAPP_VERIFY_TOKEN')){ $newVerify=bin2hex(random_bytes(24)); $updates['WHATSAPP_VERIFY_TOKEN']=$newVerify; }
        if(!wa_setup_current('YTC_LEAD_WEBHOOK_SECRET')) $updates['YTC_LEAD_WEBHOOK_SECRET']=bin2hex(random_bytes(32));
        $updates['WHATSAPP_AUTOMATION_ENABLED']='0';
        if(wa_setup_write($updates)){
            $probe=rainbow_whatsapp_probe();
            $message=($probe['connected']??false)?'Saved. WhatsApp connection probe passed; automation remains OFF.':'Saved. Probe did not connect; automation remains OFF.';
        } else $message='Could not write private environment file.';
    }
}
$csrf=rainbow_csrf_token();
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rainbow WhatsApp Setup</title>
<style>body{font-family:Arial,sans-serif;max-width:760px;margin:40px auto;padding:0 18px;background:#0b1020;color:#eef2ff}form{background:#151c33;padding:22px;border-radius:14px}label{display:block;margin:14px 0 6px}input{width:100%;box-sizing:border-box;padding:12px;border-radius:8px;border:1px solid #45506f;background:#0f1629;color:#fff}button{margin-top:20px;padding:12px 18px;border:0;border-radius:9px;font-weight:700}.ok{padding:12px;background:#173b2b;border-radius:9px;margin-bottom:15px}.status{font-size:14px;opacity:.9}</style></head><body>
<h1>Rainbow WhatsApp Production Setup</h1>
<p class="status">Secrets are saved only to the private server env file. Existing values are never displayed. Automation stays OFF after save.</p>
<?php if($message!==''): ?><div class="ok"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<?php if($newVerify!==''): ?><div class="ok"><strong>Meta Verify Token (copy now):</strong><br><code><?=htmlspecialchars($newVerify,ENT_QUOTES,'UTF-8')?></code></div><?php endif; ?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>">
<label>WhatsApp Access Token <?=wa_setup_current('WHATSAPP_ACCESS_TOKEN')?'✓ configured':''?></label><input type="password" name="WHATSAPP_ACCESS_TOKEN">
<label>Phone Number ID <?=wa_setup_current('WHATSAPP_PHONE_NUMBER_ID')?'✓ configured':''?></label><input name="WHATSAPP_PHONE_NUMBER_ID">
<label>WABA ID <?=wa_setup_current('WHATSAPP_WABA_ID')?'✓ configured':''?></label><input name="WHATSAPP_WABA_ID">
<label>Meta App Secret <?=wa_setup_current('META_APP_SECRET')?'✓ configured':''?></label><input type="password" name="META_APP_SECRET">
<label>Lead Template</label><input name="WHATSAPP_YTC_LEAD_TEMPLATE" value="<?=htmlspecialchars((string)(getenv('WHATSAPP_YTC_LEAD_TEMPLATE')?:'ytc_lead_welcome'),ENT_QUOTES,'UTF-8')?>">
<label>Template Language</label><input name="WHATSAPP_YTC_LEAD_TEMPLATE_LANGUAGE" value="<?=htmlspecialchars((string)(getenv('WHATSAPP_YTC_LEAD_TEMPLATE_LANGUAGE')?:'en'),ENT_QUOTES,'UTF-8')?>">
<button type="submit">Save & Probe (Automation OFF)</button></form>
<p class="status">Webhook URL: <code>https://leadsindia.in/rainbow-ai/whatsapp-webhook.php</code></p>
<p class="status"><a href="/rainbow-ai/" style="color:#b9c8ff">Back to Rainbow AI</a></p>
</body></html>
