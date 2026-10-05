<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$u=Auth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $start=$_GET['start']??date('Y-m-01 00:00:00');$end=$_GET['end']??date('Y-m-t 23:59:59');
 $q=$pdo->prepare("SELECT a.*,c.first_name client_first,c.last_name client_last,s.name service_name,
 GROUP_CONCAT(ac.contractor_id) contractor_ids
 FROM appointments a LEFT JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
 LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
 LEFT JOIN appointment_contractors ac ON ac.appointment_id=a.id
 WHERE a.tenant_id=? AND a.starts_at < ? AND a.ends_at > ? GROUP BY a.id ORDER BY a.starts_at");
 $q->execute([$tid,$end,$start]);json_response(['appointments'=>$q->fetchAll()]);
}
verify_csrf();Auth::requireRole('owner','admin','scheduler','contractor');$d=request_data();
if($_SERVER['REQUEST_METHOD']==='POST'){
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare("INSERT INTO appointments(tenant_id,client_id,service_id,title,description,starts_at,ends_at,status,location,is_public,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
  $q->execute([$tid,int_or_null($d['client_id']??null),int_or_null($d['service_id']??null),trim($d['title']??'Appointment'),$d['description']??null,$d['starts_at'],$d['ends_at'],$d['status']??'scheduled',$d['location']??null,!empty($d['is_public'])?1:0,$u['id']]);
  $id=(int)$pdo->lastInsertId();
  foreach(($d['contractor_ids']??[]) as $cid){$x=$pdo->prepare("INSERT INTO appointment_contractors(appointment_id,contractor_id) SELECT ?,id FROM contractors WHERE id=? AND tenant_id=?");$x->execute([$id,(int)$cid,$tid]);}
  $pdo->commit();json_response(['ok'=>true,'id'=>$id],201);
 }catch(Throwable $e){$pdo->rollBack();json_response(['error'=>$e->getMessage()],422);}
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
 $id=(int)($d['id']??0);$q=$pdo->prepare("UPDATE appointments SET client_id=?,service_id=?,title=?,description=?,starts_at=?,ends_at=?,status=?,location=?,is_public=? WHERE id=? AND tenant_id=?");
 $q->execute([int_or_null($d['client_id']??null),int_or_null($d['service_id']??null),$d['title']??'Appointment',$d['description']??null,$d['starts_at'],$d['ends_at'],$d['status']??'scheduled',$d['location']??null,!empty($d['is_public'])?1:0,$id,$tid]);json_response(['ok'=>true]);
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
 $id=(int)($d['id']??0);$q=$pdo->prepare("DELETE FROM appointments WHERE id=? AND tenant_id=?");$q->execute([$id,$tid]);json_response(['ok'=>true]);
}
json_response(['error'=>'Method not allowed'],405);
