<?php
declare(strict_types=1);
if(!defined('BRITE_MODULE_DISPATCH')||!in_array((string)($tenant['role']??''),['owner','admin','scheduler'],true)){http_response_code(403);exit;}
$q=$db->prepare("SELECT m.user_id,u.first_name,u.last_name,m.role FROM tenant_memberships m JOIN users u ON u.id=m.user_id WHERE m.tenant_id=? AND m.status='active' AND u.status='active' AND m.role IN ('owner','admin','scheduler') ORDER BY u.last_name,u.first_name");
$q->execute([$tid]);$staff=$q->fetchAll(PDO::FETCH_ASSOC);
$filter=(string)($_GET['task_filter']??'open');if(!in_array($filter,['open','overdue','completed','all'],true))$filter='open';
$assignee=(int)($_GET['assignee']??0);
$where="a.tenant_id=? AND a.kind='follow_up'";
$params=[$tid];
if($filter==='open')$where.=' AND a.completed_at IS NULL';
if($filter==='overdue')$where.=' AND a.completed_at IS NULL AND a.due_at < CURDATE()';
if($filter==='completed')$where.=' AND a.completed_at IS NOT NULL';
if($assignee>0){$where.=' AND d.assignee_user_id=?';$params[]=$assignee;}
$sql="SELECT a.id,a.client_id,a.body,a.due_at,a.completed_at,a.created_at,d.assignee_user_id,r.repeat_days,COALESCE(d.priority,'normal') priority,CONCAT(c.first_name,' ',c.last_name) customer,CONCAT(u.first_name,' ',u.last_name) assignee_name FROM crm_activities a LEFT JOIN crm_task_details d ON d.tenant_id=a.tenant_id AND d.activity_id=a.id LEFT JOIN crm_task_recurring r ON r.tenant_id=a.tenant_id AND r.activity_id=a.id LEFT JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id LEFT JOIN users u ON u.id=d.assignee_user_id WHERE ".$where." ORDER BY (a.completed_at IS NOT NULL), (a.due_at IS NULL),a.due_at,a.id DESC LIMIT 150";
$q=$db->prepare($sql);$q->execute($params);$tasks=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$db->prepare("SELECT COUNT(*) total,SUM(a.completed_at IS NOT NULL) completed,SUM(a.completed_at IS NULL AND a.due_at < CURDATE()) overdue FROM crm_campaign_tasks ct JOIN crm_activities a ON a.id=ct.activity_id AND a.tenant_id=ct.tenant_id WHERE ct.tenant_id=?");
$q->execute([$tid]);$summary=$q->fetch(PDO::FETCH_ASSOC);
$q=$db->prepare("SELECT c.id,c.segment_key,c.created_at,c.recipients,COUNT(ct.activity_id) tracked,SUM(a.completed_at IS NOT NULL) completed,SUM(a.completed_at IS NULL AND a.due_at < CURDATE()) overdue FROM crm_followup_campaigns c LEFT JOIN crm_campaign_tasks ct ON ct.campaign_id=c.id AND ct.tenant_id=c.tenant_id LEFT JOIN crm_activities a ON a.id=ct.activity_id AND a.tenant_id=ct.tenant_id WHERE c.tenant_id=? GROUP BY c.id,c.segment_key,c.created_at,c.recipients ORDER BY c.id DESC LIMIT 30");
$q->execute([$tid]);$campaigns=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$db->prepare("SELECT m.user_id,CONCAT(u.first_name,' ',u.last_name) staff_name,COUNT(a.id) open_tasks,SUM(CASE WHEN a.due_at<CURDATE() THEN 1 ELSE 0 END) overdue_tasks FROM tenant_memberships m JOIN users u ON u.id=m.user_id LEFT JOIN crm_task_details d ON d.tenant_id=m.tenant_id AND d.assignee_user_id=m.user_id LEFT JOIN crm_activities a ON a.id=d.activity_id AND a.tenant_id=d.tenant_id AND a.kind='follow_up' AND a.completed_at IS NULL WHERE m.tenant_id=? AND m.status='active' AND m.role IN ('owner','admin','scheduler') GROUP BY m.user_id,u.first_name,u.last_name ORDER BY open_tasks DESC");
$q->execute([$tid]);$workload=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$db->prepare("SELECT COUNT(*) FROM crm_activities WHERE tenant_id=? AND kind='follow_up' AND completed_at IS NULL AND due_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 3 DAY)");
$q->execute([$tid]);$dueSoon=(int)$q->fetchColumn();
?>
<section class="crm-panel"><h3>Staff workload & reminders</h3><p class="crm-muted"><?=$dueSoon?> open follow-up tasks due in the next three days. Reminders are displayed here; no external notifications are sent.</p>
<div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Staff</th><th>Open tasks</th><th>Overdue</th></tr></thead><tbody><?php foreach($workload as $person): ?><tr><td><?=$esc($person['staff_name'])?></td><td><?=(int)$person['open_tasks']?></td><td><?=(int)$person['overdue_tasks']?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="crm-panel crm-task-manager"><div class="crm-task-heading"><div><h2>Follow-up task management</h2><p class="crm-muted">Review assignments, priorities and upcoming follow-ups.</p></div></div>
<div class="crm-stats"><?php foreach([['Campaign tasks',(int)($summary['total']??0)],['Completed',(int)($summary['completed']??0)],['Overdue',(int)($summary['overdue']??0)]] as $metric): ?><div class="crm-stat"><span class="crm-muted"><?=$esc($metric[0])?></span><strong><?=$esc($metric[1])?></strong></div><?php endforeach; ?></div>
<form method="get" class="crm-inline crm-task-filters"><input type="hidden" name="module" value="crm"><input type="hidden" name="tab" value="tasks"><label>Show <select name="task_filter"><?php foreach(['open'=>'Open','overdue'=>'Overdue','completed'=>'Completed','all'=>'All'] as $key=>$name): ?><option value="<?=$key?>" <?=$filter===$key?'selected':''?>><?=$name?></option><?php endforeach; ?></select></label>
<label>Assigned to <select name="assignee"><option value="0">Everyone</option><?php foreach($staff as $person): ?><option value="<?=(int)$person['user_id']?>" <?=$assignee===(int)$person['user_id']?'selected':''?>><?=$esc(trim($person['first_name'].' '.$person['last_name']))?></option><?php endforeach; ?></select></label><button type="submit">Filter</button></form>
<div class="crm-table-wrap crm-task-list-wrap"><table class="crm-table crm-task-list"><thead><tr><th>Customer / Task</th><th>Due</th><th>Status</th><th>Assignment / Priority</th></tr></thead><tbody>
<?php foreach($tasks as $task): ?><tr><td class="crm-task-description"><strong><?=$esc($task['customer']?:'Unlinked task')?></strong><div><?=$esc($task['body'])?></div></td><td><?=$esc($task['due_at']?:'Unscheduled')?></td><td><?=$task['completed_at']?'Completed':($task['due_at']&&$task['due_at']<date('Y-m-d')?'Overdue':'Open')?></td>
<td class="crm-task-actions"><div class="crm-task-action-stack"><form method="post" class="crm-form crm-task-edit"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="task_update"><input type="hidden" name="tab" value="tasks"><input type="hidden" name="activity_id" value="<?=(int)$task['id']?>">
<label>Assignee<select name="assignee_user_id"><option value="0">Unassigned</option><?php foreach($staff as $person): ?><option value="<?=(int)$person['user_id']?>" <?=(int)$task['assignee_user_id']===(int)$person['user_id']?'selected':''?>><?=$esc(trim($person['first_name'].' '.$person['last_name']))?></option><?php endforeach; ?></select></label>
<label>Priority<select name="priority"><?php foreach(['low','normal','high','urgent'] as $priority): ?><option value="<?=$priority?>" <?=$task['priority']===$priority?'selected':''?>><?=ucfirst($priority)?></option><?php endforeach; ?></select></label><button type="submit">Save changes</button></form>
<?php if(!$task['completed_at']): ?>
<form method="post" class="crm-inline crm-task-repeat"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="task_recurrence"><input type="hidden" name="tab" value="tasks"><input type="hidden" name="activity_id" value="<?=(int)$task['id']?>">
<label>Repeat every <input type="number" name="repeat_days" min="0" max="365" value="<?=(int)($task['repeat_days']??0)?>" style="max-width:80px"> days</label><button type="submit">Set repeat</button></form>
<form method="post" class="crm-task-complete"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="task_complete"><input type="hidden" name="tab" value="tasks"><input type="hidden" name="activity_id" value="<?=(int)$task['id']?>"><button type="submit">Complete task</button></form>
<?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table><?php if(!$tasks): ?><p class="crm-muted">No tasks match this filter.</p><?php endif; ?></div>
</section>
<section class="crm-panel"><h2>Campaign performance</h2><p class="crm-muted">Completion and overdue counts are based on linked CRM tasks, not customer responses or revenue.</p>
<div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Campaign</th><th>Created</th><th>Tasks</th><th>Completed</th><th>Completion rate</th><th>Overdue</th></tr></thead><tbody>
<?php foreach($campaigns as $campaign): $total=(int)$campaign['tracked'];$done=(int)$campaign['completed']; ?><tr><td>#<?=(int)$campaign['id']?> · <?=$esc($campaign['segment_key'])?></td><td><?=$esc($campaign['created_at'])?></td><td><?=$total?></td><td><?=$done?></td><td><?=$total?number_format($done*100/$total,1).'%':'—'?></td><td><?=(int)$campaign['overdue']?></td></tr><?php endforeach; ?></tbody></table><?php if(!$campaigns): ?><p class="crm-muted">No campaigns have been created yet.</p><?php endif; ?></div>
</section>
