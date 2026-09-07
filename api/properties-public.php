<?php
require dirname(__DIR__) . '/lib/property-store.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$out=[];
foreach(li_all_properties() as $r){
    if(($r['status']??'')!=='LIVE') continue;
    $p=$r['property']??[];
    $out[]=[
        'reference_id'=>$r['reference_id']??'',
        'verified'=>!empty($r['verification']['verified']),
        'published_at'=>$r['published_at']??null,
        'purpose'=>$p['purpose']??'',
        'type'=>$p['type']??'',
        'configuration'=>$p['configuration']??'',
        'area'=>$p['area']??'',
        'price'=>$p['price']??'',
        'project_name'=>$p['project_name']??'',
        'city'=>$p['city']??'',
        'locality'=>$p['locality']??'',
        'description'=>$p['description']??'',
        'photos'=>$p['photos']??[],
        'detail_url'=>'/property/'.rawurlencode((string)($r['reference_id']??'')),
    ];
}
echo json_encode(['ok'=>true,'count'=>count($out),'properties'=>$out],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
