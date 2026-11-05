<?php
declare(strict_types=1);
if(!defined('BRITE_MODULE_DISPATCH')||!in_array((string)($tenant['role']??''),['owner','admin','scheduler'],true)){http_response_code(403);exit;}
/** Smart segments are live, read-only classifications, not stored membership. */
$smartRules=[
 'repeat'=>['label'=>'Repeat customers','description'=>'At least two appointments','where'=>'COALESCE(a.appointments,0)>=2'],
 'inactive'=>['label'=>'Inactive customers','description'=>'Previously booked, but no appointments in the last 90 days','where'=>'COALESCE(a.appointments,0)>0 AND a.last_appointment < DATE_SUB(CURDATE(),INTERVAL 90 DAY)'],
 'high_value'=>['label'=>'High-value opportunities','description'=>'At least $1,000 in open estimated opportunity value','where'=>'COALESCE(o.open_value,0)>=1000'],
 'quotes'=>['label'=>'Active quote customers','description'=>'At least one submitted or quoted request','where'=>'COALESCE(q.active_quotes,0)>0'],
 'new'=>['label'=>'New customers','description'=>'No appointment history yet','where'=>'COALESCE(a.appointments,0)=0']
];
$rule=(string)($_GET['smart']??'repeat');if(!isset($smartRules[$rule]))$rule='repeat';
$sql="SELECT c.id,c.first_name,c.last_name,c.company_name,c.email,
 COALESCE(a.appointments,0) appointments,a.last_appointment,
 COALESCE(o.open_value,0) open_value,COALESCE(q.active_quotes,0) active_quotes
 FROM clients c
 LEFT JOIN (SELECT tenant_id,client_id,COUNT(*) appointments,MAX(starts_at) last_appointment FROM appointments WHERE tenant_id=? GROUP BY tenant_id,client_id) a ON a.tenant_id=c.tenant_id AND a.client_id=c.id
 LEFT JOIN (SELECT tenant_id,client_id,SUM(CASE WHEN stage IN ('new','contacted','quoted') THEN estimated_value ELSE 0 END) open_value FROM crm_opportunities WHERE tenant_id=? GROUP BY tenant_id,client_id) o ON o.tenant_id=c.tenant_id AND o.client_id=c.id
 LEFT JOIN (SELECT tenant_id,client_id,SUM(CASE WHEN status IN ('submitted','more_info_requested','quoted') THEN 1 ELSE 0 END) active_quotes FROM contractor_quote_requests WHERE tenant_id=? GROUP BY tenant_id,client_id) q ON q.tenant_id=c.tenant_id AND q.client_id=c.id
 WHERE c.tenant_id=? AND ".$smartRules[$rule]['where']." ORDER BY c.id DESC LIMIT 200";
$q=$db->prepare($sql);$q->execute([$tid,$tid,$tid,$tid]);$matches=$q->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="crm-panel">
 <h2>Smart customer segments</h2>
 <p class="crm-muted">Live classifications calculated from tenant-owned scheduling, quote and CRM records. No manual membership changes required.</p>
 <nav class="crm-tabs" aria-label="Smart segment types">
 <?php foreach($smartRules as $key=>$definition): ?><a href="<?=$esc($base.'&tab=smart&smart='.$key)?>" <?=$key===$rule?'aria-current="page"':''?>><?=$esc($definition['label'])?></a><?php endforeach; ?>
 </nav>
 <h3><?=$esc($smartRules[$rule]['label'])?> <span class="crm-pill"><?=count($matches)?><?=$matches&&count($matches)===200?'+':''?></span></h3>
 <p class="crm-muted"><?=$esc($smartRules[$rule]['description'])?>. Up to 200 matching customers are shown.</p>
 <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Customer</th><th>Appointments</th><th>Last appointment</th><th>Open opportunity value</th><th>Active quotes</th></tr></thead><tbody>
 <?php foreach($matches as $customer): ?><tr>
 <td><a href="<?=$esc($base.'&tab=customers&client_id='.(int)$customer['id'])?>"><?=$esc(trim($customer['first_name'].' '.$customer['last_name']))?></a><div class="crm-muted"><?=$esc($customer['company_name'])?></div></td>
 <td><?=(int)$customer['appointments']?></td><td><?=$esc($customer['last_appointment']?:'—')?></td><td>$<?=number_format((float)$customer['open_value'],2)?></td><td><?=(int)$customer['active_quotes']?></td>
 </tr><?php endforeach; ?></tbody></table>
 <?php if(!$matches): ?><p class="crm-muted">No customers currently match this rule.</p><?php endif; ?></div>
 <p class="crm-muted">High-value threshold: $1,000. Inactivity threshold: 90 days. Appointment counts include all appointment statuses.</p>
</section>
