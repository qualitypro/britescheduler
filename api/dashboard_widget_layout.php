<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user=Auth::requireUser();
$tenant=Auth::tenant();
$tid=(int)($tenant['tenant_id']??0);
$uid=(int)$user['id'];
if($tid<1||$uid<1)json_response(['error'=>'Tenant context required'],403);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','PUT','DELETE'],true)){
 header('Allow: GET, PUT, DELETE');
 json_response(['error'=>'Method not allowed'],405);
}
$key=(string)($_GET['dashboard']??'');
if(!preg_match('/^[a-z][a-z0-9_-]{1,63}$/D',$key))json_response(['error'=>'Invalid dashboard'],422);
$role=(string)($tenant['role']??'');
$allowed=[
 'operations'=>['owner','admin','scheduler','accounting'],
 'contractor'=>['contractor'],
 'client'=>['client'],
 'intelligence_performance'=>['owner','admin','scheduler','accounting','contractor','client'],
 'intelligence_financial'=>['owner','admin','accounting']
];
if(!isset($allowed[$key])||!in_array($role,$allowed[$key],true)){
 json_response(['error'=>'Dashboard unavailable'],403);
}
if($method!=='GET'){
 $csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(!hash_equals(csrf_token(),$csrf))json_response(['error'=>'Invalid CSRF token'],403);
}
$pdo=Database::connection();
if($method==='GET'){
 $q=$pdo->prepare('SELECT layout_json FROM dashboard_widget_layouts WHERE tenant_id=? AND user_id=? AND dashboard_key=? LIMIT 1');
 $q->execute([$tid,$uid,$key]);
 $json=$q->fetchColumn();
 json_response(['layout'=>$json===false?null:json_decode((string)$json,true)]);
}
if($method==='DELETE'){
 $q=$pdo->prepare('DELETE FROM dashboard_widget_layouts WHERE tenant_id=? AND user_id=? AND dashboard_key=?');
 $q->execute([$tid,$uid,$key]);
 json_response(['ok'=>true]);
}
$raw=file_get_contents('php://input',false,null,0,8193);
if($raw===false||strlen($raw)>8192)json_response(['error'=>'Layout exceeds 8 KiB'],413);
$layout=json_decode($raw,true);
if(!is_array($layout)||array_is_list($layout)||array_diff(array_keys($layout),['order','hidden','wide'])||
   count($layout)!==3)json_response(['error'=>'Invalid layout object'],422);
$widgets=[
 'operations'=>['clients','contractors','appointments_today','appointments_week','receivables','payments_month'],
 'contractor'=>['assigned_appointments','business'],
 'client'=>['appointments','invoices','outstanding_balance'],
 'intelligence_performance'=>['analysis','comparison','series_data'],
 'intelligence_financial'=>['configuration','totals','chart','forecast','series_data']
];
$validKeys=$widgets[$key];
foreach(['order','hidden','wide'] as $field){
 $ids=$layout[$field]??null;
 if(!is_array($ids)||!array_is_list($ids)||count($ids)>count($validKeys)||
    count($ids)!==count(array_unique($ids,SORT_REGULAR)))json_response(['error'=>'Invalid '.$field],422);
 foreach($ids as $id){
  if(!is_string($id)||!in_array($id,$validKeys,true))json_response(['error'=>'Invalid widget'],422);
 }
}
$json=json_encode($layout,JSON_THROW_ON_ERROR);
$q=$pdo->prepare('INSERT INTO dashboard_widget_layouts(tenant_id,user_id,dashboard_key,layout_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE layout_json=VALUES(layout_json),updated_at=CURRENT_TIMESTAMP');
$q->execute([$tid,$uid,$key,$json]);
json_response(['ok'=>true,'layout'=>$layout]);
