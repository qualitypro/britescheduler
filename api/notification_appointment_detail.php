<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::tenant();
require_method('GET');
$tenantId=(int)$tenant['tenant_id'];
$userId=(int)$user['id'];
$id=filter_var($_GET['appointment_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id)json_response(['error'=>'Invalid appointment'],422);
$db=Database::connection();
$q=$db->prepare("SELECT tm.role FROM tenant_memberships tm JOIN users u ON u.id=tm.user_id WHERE tm.tenant_id=? AND tm.user_id=? AND tm.status='active' AND u.status='active' LIMIT 1");
$q->execute([$tenantId,$userId]);$role=(string)$q->fetchColumn();
if(!in_array($role,['owner','admin','scheduler','accounting'],true))json_response(['error'=>'Appointment detail access requires a management role'],403);
try{
 $q=$db->prepare('SELECT * FROM appointments WHERE tenant_id=? AND id=? LIMIT 1');
 $q->execute([$tenantId,$id]);$record=$q->fetch(PDO::FETCH_ASSOC);
 if(!$record)json_response(['error'=>'Appointment unavailable'],404);
 // Explicit allowlist: never expose internal fields or customer PII through a generic record dump.
 $fields=[];
 foreach(['id'=>'Appointment','status'=>'Status','title'=>'Title','service_name'=>'Service','scheduled_start'=>'Scheduled start','start_at'=>'Start','scheduled_end'=>'Scheduled end','end_at'=>'End','created_at'=>'Created','updated_at'=>'Updated'] as $key=>$label){
  if(isset($record[$key])&&$record[$key]!=='')$fields[]=['label'=>$label,'value'=>(string)$record[$key]];
 }
 json_response(['ok'=>true,'record_type'=>'appointment','record_id'=>$id,'status'=>(string)($record['status']??'Unknown'),'fields'=>$fields,'actions'=>[['label'=>'Open scheduling','href'=>'scheduling.php?appointment_id='.$id]]]);
}catch(Throwable $e){error_log('Notification appointment details: '.$e->getMessage());json_response(['error'=>'Unable to load appointment details'],500);}
