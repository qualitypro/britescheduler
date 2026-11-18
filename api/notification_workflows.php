<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/EnrollmentBlocks.php';
$user=Auth::requireUser();$tenant=Auth::tenant();
if(!in_array((string)$tenant['role'],['owner','admin'],true))json_response(['error'=>'Not authorized to review enrollment'],403);
$tid=(int)$tenant['tenant_id'];$uid=(int)$user['id'];$db=Database::connection();
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
 $id=filter_var($_GET['application_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$id)json_response(['error'=>'Invalid application'],422);
 $q=$db->prepare("SELECT a.id,a.applicant_type,a.full_name,a.email,a.phone,a.notes,a.status,a.decision_note,a.created_at,a.reviewed_at,EXISTS(SELECT 1 FROM enrollment_blocks b WHERE b.tenant_id=a.tenant_id AND b.email=a.email AND b.unblocked_at IS NULL) blocked FROM enrollment_applications a WHERE a.tenant_id=? AND a.id=?");
 $q->execute([$tid,$id]);$a=$q->fetch(PDO::FETCH_ASSOC);
 if(!$a)json_response(['error'=>'Application unavailable'],404);
 $actions=[];
 if($a['status']==='pending')$actions=['approved','rejected'];
 json_response(['ok'=>true,'application'=>$a,'actions'=>$actions,'next_step'=>$a['status']==='pending'?'Review and decide on this application':($a['status']==='approved'?'Approved — continue onboarding in Enrollment':'Application rejected')]);
}
require_method('POST');verify_csrf();$data=request_data();
$id=filter_var($data['application_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$decision=(string)($data['decision']??'');$note=trim((string)($data['decision_note']??''));
if(!$id||!in_array($decision,['approved','rejected'],true)||strlen($note)>1000)json_response(['error'=>'Invalid review request'],422);
try{
 $db->beginTransaction();
 $q=$db->prepare("SELECT email,status FROM enrollment_applications WHERE tenant_id=? AND id=? FOR UPDATE");
 $q->execute([$tid,$id]);$a=$q->fetch(PDO::FETCH_ASSOC);
 if(!$a||$a['status']!=='pending')throw new DomainException('Application is no longer pending');
 if($decision==='approved')EnrollmentBlocks::assertAllowed($db,$tid,(string)$a['email']);
 $q=$db->prepare("UPDATE enrollment_applications SET status=?,reviewed_by=?,reviewed_at=NOW(),decision_note=? WHERE tenant_id=? AND id=? AND status='pending'");
 $q->execute([$decision,$uid,$note?:null,$tid,$id]);
 if($q->rowCount()!==1)throw new DomainException('Application already reviewed');
 $q=$db->prepare('INSERT INTO audit_log (tenant_id,user_id,action,entity_type,entity_id,metadata) VALUES (?,?,?,?,?,?)');
 $q->execute([$tid,$uid,'enrollment.review','enrollment_application',$id,json_encode(['decision'=>$decision,'source'=>'notification_workflows'],JSON_THROW_ON_ERROR)]);
 $q=$db->prepare("INSERT INTO notifications (tenant_id,user_id,type,title,body) SELECT ?,m.user_id,'enrollment.reviewed','Enrollment decision recorded',? FROM tenant_memberships m WHERE m.tenant_id=? AND m.role IN ('owner','admin') AND m.status='active'");
 $q->execute([$tid,'Application #'.$id.' marked '.$decision.'.',$tid]);
 $step=$db->prepare("INSERT INTO activity_workflow_steps (tenant_id,workflow_type,record_id,step_key,step_label,step_order,status,details) VALUES (?,'enrollment',?,'decision','Decision recorded',3,?,?)");
 $step->execute([$tid,$id,$decision==='rejected'?'blocked':'completed','Application '.$decision]);
 $db->commit();json_response(['ok'=>true,'decision'=>$decision,'next_step'=>$decision==='approved'?'Generate an activation invitation in Enrollment':'Review completed']);
}catch(DomainException $e){if($db->inTransaction())$db->rollBack();json_response(['error'=>$e->getMessage()],422);}
catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Notification workflow enrollment review: '.$e->getMessage());json_response(['error'=>'Unable to save review'],500);}
