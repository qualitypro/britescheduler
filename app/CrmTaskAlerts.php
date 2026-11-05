<?php
declare(strict_types=1);
final class CrmTaskAlerts {
 public static function send(PDO $db,int $tid,int $uid):int {
  $q=$db->prepare("SELECT 1 FROM tenant_memberships m JOIN users u ON u.id=m.user_id WHERE m.tenant_id=? AND m.user_id=? AND m.status='active' AND u.status='active' AND m.role IN ('owner','admin','scheduler')");
  $q->execute([$tid,$uid]);if(!$q->fetchColumn())throw new RuntimeException('Not eligible for CRM alerts.');
  $q=$db->prepare("SELECT a.id,a.due_at,a.body,CASE WHEN a.due_at<CURDATE() THEN 'overdue' ELSE 'due_soon' END alert_type FROM crm_activities a JOIN crm_task_details d ON d.activity_id=a.id AND d.tenant_id=a.tenant_id WHERE a.tenant_id=? AND d.assignee_user_id=? AND a.kind='follow_up' AND a.completed_at IS NULL AND a.due_at BETWEEN DATE_SUB(CURDATE(),INTERVAL 365 DAY) AND DATE_ADD(CURDATE(),INTERVAL 3 DAY) ORDER BY a.due_at LIMIT 100");
  $q->execute([$tid,$uid]);$tasks=$q->fetchAll(PDO::FETCH_ASSOC);$sent=0;
  foreach($tasks as $task){
   $db->beginTransaction();
   try{
    $insert=$db->prepare('INSERT IGNORE INTO crm_task_alerts(tenant_id,activity_id,user_id,alert_type) VALUES(?,?,?,?)');
    $insert->execute([$tid,(int)$task['id'],$uid,$task['alert_type']]);
    if($insert->rowCount()===1){
     $notify=$db->prepare('INSERT INTO notifications(tenant_id,user_id,appointment_id,type,title,body) VALUES(?,?,NULL,?,?,?)');
     $notify->execute([$tid,$uid,'crm_task',$task['alert_type']==='overdue'?'Overdue CRM follow-up':'CRM follow-up due soon',sprintf('CRM task #%d due %s: %s',(int)$task['id'],$task['due_at'],mb_substr($task['body'],0,160))]);
     $sent++;
    }
    $db->commit();
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  }
  return $sent;
 }
}
