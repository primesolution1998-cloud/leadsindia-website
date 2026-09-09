<?php
require __DIR__ . '/_auth.php';
require dirname(__DIR__) . '/lib/property-store.php';
require dirname(__DIR__) . '/lib/cmts-client.php';
li_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
$csrf=(string)($_POST['csrf']??'');
if ($csrf==='' || !hash_equals(li_csrf(),$csrf)) { http_response_code(403); exit('Invalid CSRF token'); }
$ref=trim((string)($_POST['reference_id']??''));
$status=strtoupper(trim((string)($_POST['status']??'')));
$note=trim((string)($_POST['note']??''));
if (!in_array($status,li_allowed_statuses(),true)) { http_response_code(422); exit('Invalid status'); }

$record=li_load_property($ref);
if(!$record){http_response_code(404);exit('Property not found');}
$from=(string)($record['status']??'PENDING_VERIFICATION');
$record['status']=$status;
$record['updated_at']=gmdate('c');
$record['verification']=$record['verification']??[];

if(in_array($status,['VERIFIED','LIVE'],true)){
    $record['verification']['verified']=true;
    $record['verification']['verified_at']=$record['verification']['verified_at']??gmdate('c');
    $record['verification']['verified_by']=li_admin_user();
}
if($status==='LIVE' && empty($record['published_at'])) $record['published_at']=gmdate('c');
if($status!=='LIVE' && isset($record['published_at']) && in_array($status,['REJECTED','PAUSED','SOLD','RENTED'],true)) $record['unpublished_at']=gmdate('c');
if($note!=='') $record['verification']['notes']=mb_substr($note,0,500);
li_audit($record,$from,$status,li_admin_user(),mb_substr($note,0,500));
if(!li_save_property($record)){http_response_code(500);exit('Could not save property');}

// Approval is authoritative. Messaging is downstream and must never roll back a valid moderation decision.
// Only the transition into LIVE creates a durable, signed CMTS event. Failures remain in the private outbox for retry.
if($status==='LIVE' && $from!=='LIVE'){
    $outbox=li_cmts_enqueue_live($record);
    if($outbox) li_cmts_dispatch_file($outbox);
}

header('Location: /admin/properties.php?status='.rawurlencode($status));
