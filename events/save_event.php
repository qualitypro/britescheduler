<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$u=Auth::requireUser();Auth::requireRole('owner','admin','scheduler');verify_csrf();
$tid=Auth::tenantId();$pdo=Database::connection();$d=request_data();
$parse=function($date,$time){$x=DateTime::createFromFormat('m/d/Y H:i',trim($date.' '.$time));return $x?$x->format('Y-m-d H:i:s'):null;};
$start=$parse($d['start_date']??'',$d['start_time']??'00:00');$end=$parse($d['end_date']??'',$d['end_time']??'00:00');
if(!$start||!$end) json_response(['error'=>'Invalid date/time'],422);
$id=(int)($d['event_id']??0);
if($id){$q=$pdo->prepare("UPDATE appointments SET title=?,description=?,starts_at=?,ends_at=?,is_public=?,status=? WHERE id=? AND tenant_id=?");$q->execute([$d['description']??'Appointment',$d['description']??null,$start,$end,($d['is_public']??'false')==='true'?1:0,($d['is_active']??'true')==='true'?'scheduled':'cancelled',$id,$tid]);}
else{$q=$pdo->prepare("INSERT INTO appointments(tenant_id,title,description,starts_at,ends_at,is_public,status,created_by) VALUES(?,?,?,?,?,?,?,?)");$q->execute([$tid,$d['description']??'Appointment',$d['description']??null,$start,$end,($d['is_public']??'false')==='true'?1:0,($d['is_active']??'true')==='true'?'scheduled':'cancelled',$u['id']]);$id=(int)$pdo->lastInsertId();}
json_response(['ok'=>true,'id'=>$id]);
