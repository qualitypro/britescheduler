<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::tenant();
require_method('GET');
$tid=(int)$tenant['tenant_id'];
$uid=(int)$user['id'];
$type=(string)($_GET['workflow_type']??'');
$id=filter_var($_GET['record_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if($type!=='enrollment'||!$id)json_response(['error'=>'Unsupported workflow or record'],422);
$db=Database::connection();
try {
 $m=$db->prepare("SELECT role FROM tenant_memberships WHERE tenant_id=? AND user_id=? AND status='active' LIMIT 1");
 $m->execute([$tid,$uid]);
 $role=(string)$m->fetchColumn();
 if(!in_array($role,['owner','admin'],true))json_response(['error'=>'Not authorized'],403);
 $q=$db->prepare('SELECT id,full_name,status,created_at,reviewed_at FROM enrollment_applications WHERE tenant_id=? AND id=? LIMIT 1');
 $q->execute([$tid,$id]);
 $a=$q->fetch(PDO::FETCH_ASSOC);
 if(!$a)json_response(['error'=>'Application unavailable'],404);
 $steps=[
  ['key'=>'submitted','label'=>'Application submitted','status'=>'completed','at'=>$a['created_at']],
  ['key'=>'reviewed','label'=>'Application reviewed','status'=>$a['status']==='pending'?'pending':'completed','at'=>$a['reviewed_at']],
  ['key'=>'decision','label'=>'Decision recorded','status'=>$a['status']==='pending'?'pending':($a['status']==='rejected'?'blocked':'completed'),'at'=>$a['reviewed_at']],
  ['key'=>'invited','label'=>'Activation invitation','status'=>'pending','at'=>null],
  ['key'=>'activated','label'=>'Account activated','status'=>'pending','at'=>null]
 ];
 $q=$db->prepare("SELECT created_at,accepted_at,revoked_at,expires_at FROM enrollment_invitations WHERE tenant_id=? AND application_id=? ORDER BY id DESC LIMIT 1");
 $q->execute([$tid,$id]);
 $invite=$q->fetch(PDO::FETCH_ASSOC);
 if($invite){
  $steps[3]['status']=$invite['revoked_at']!==null?'blocked':($invite['accepted_at']!==null?'completed':($invite['expires_at']<=gmdate('Y-m-d H:i:s')?'failed':'completed'));
  $steps[3]['at']=$invite['created_at'];
  if($invite['accepted_at']!==null){$steps[4]['status']='completed';$steps[4]['at']=$invite['accepted_at'];}
 }
 if($a['status']==='rejected'){$steps[3]['status']='blocked';$steps[4]['status']='blocked';}
 $q=$db->prepare("SELECT id,step_key,step_label,status,notification_id,details,created_at FROM activity_workflow_steps WHERE tenant_id=? AND workflow_type='enrollment' AND record_id=? ORDER BY created_at,id");
 $q->execute([$tid,$id]);
 $events=$q->fetchAll(PDO::FETCH_ASSOC);
 $completed=count(array_filter($steps,static fn($s)=>$s['status']==='completed'));
 json_response(['ok'=>true,'workflow_type'=>'enrollment','record_id'=>(int)$id,'title'=>$a['full_name'],'status'=>$a['status'],'completed'=>$completed,'total'=>count($steps),'steps'=>$steps,'events'=>$events,'record_url'=>app_url('/enrollment.php').'?application_id='.$id]);
} catch(Throwable $e){
 error_log('Activity workflow: '.$e->getMessage());
 json_response(['error'=>'Unable to load workflow progress. Confirm migration 042 is applied.'],500);
}
