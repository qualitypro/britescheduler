<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::tenant();
require_method('POST');
verify_csrf();
$data=request_data();
$id=filter_var($data['notification_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id)json_response(['error'=>'Invalid notification ID'],422);
$tid=(int)$tenant['tenant_id'];$uid=(int)$user['id'];
$db=Database::connection();
try{
 $db->beginTransaction();
 $q=$db->prepare("SELECT tm.role FROM tenant_memberships tm JOIN users u ON u.id=tm.user_id WHERE tm.tenant_id=? AND tm.user_id=? AND tm.status='active' AND u.status='active' LIMIT 1");
 $q->execute([$tid,$uid]);$role=$q->fetchColumn();
 if(!$role){$db->rollBack();json_response(['error'=>'Active tenant membership required'],403);}
 $q=$db->prepare('SELECT id,appointment_id,type,title,body,dismissed_at FROM notifications WHERE tenant_id=? AND user_id=? AND id=? FOR UPDATE');
 $q->execute([$tid,$uid,$id]);$n=$q->fetch(PDO::FETCH_ASSOC);
 if(!$n){$db->rollBack();json_response(['error'=>'Notification unavailable'],404);}
 $type=(string)$n['type'];$body=(string)$n['body'];$reason=null;
 $enrollment=null;
 if(in_array($type,['enrollment.submitted','enrollment.reviewed'],true)){
  $pattern=$type==='enrollment.reviewed'?'/\\bApplication #([1-9][0-9]*) marked (?:approved|rejected)\\b/i':'/(?:contractor|client) application #([1-9][0-9]*) submitted for review\\./i';
  if(preg_match($pattern,$body,$m))$enrollment=(int)$m[1];
  else $reason='Enrollment record could not be verified';
 }
 if($enrollment){
  $q=$db->prepare('SELECT status FROM enrollment_applications WHERE tenant_id=? AND id=?');
  $q->execute([$tid,$enrollment]);$status=$q->fetchColumn();
  if($status===false)$reason='Enrollment record unavailable';
  elseif($status==='pending')$reason='Application review and decision are outstanding';
  elseif($status==='approved'){
   $q=$db->prepare('SELECT accepted_at,revoked_at,expires_at FROM enrollment_invitations WHERE tenant_id=? AND application_id=? ORDER BY id DESC LIMIT 1');
   $q->execute([$tid,$enrollment]);$inv=$q->fetch(PDO::FETCH_ASSOC);
   if(!$inv)$reason='Activation invitation has not been sent';
   elseif($inv['accepted_at']===null)$reason='Account activation is outstanding';
  }
  elseif($status!=='rejected')$reason='Enrollment completion cannot be verified';
 }
 // Appointment-related action alerts are held until the appointment reaches a terminal state.
 $appointment=(int)($n['appointment_id']??0);
 if(!$reason&&$appointment>0&&preg_match('/(request|assignment|bid|accept|declin|confirm|approv|action.required)/i',$type.' '.(string)$n['title'])){
  $q=$db->prepare('SELECT status FROM appointments WHERE tenant_id=? AND id=?');
  $q->execute([$tid,$appointment]);$status=$q->fetchColumn();
  if($status===false)$reason='Linked appointment is unavailable';
  elseif(!in_array(strtolower((string)$status),['completed','complete','cancelled','canceled','closed','void'],true))
   $reason='Linked appointment still has outstanding work; open Scheduling to review';
 }
 if($reason!==null){$db->rollBack();json_response(['ok'=>false,'blocked'=>true,'error'=>$reason],409);}
 $q=$db->prepare('UPDATE notifications SET dismissed_at=UTC_TIMESTAMP() WHERE tenant_id=? AND user_id=? AND id=? AND dismissed_at IS NULL');
 $q->execute([$tid,$uid,$id]);
 $db->commit();
 json_response(['ok'=>true,'notification_id'=>(int)$id,'already_dismissed'=>$n['dismissed_at']!==null]);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Notification dismissal: '.$e->getMessage());json_response(['error'=>'Unable to dismiss notification'],500);}
