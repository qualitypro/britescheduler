<?php
declare(strict_types=1);
if(!defined('BRITE_MODULE_DISPATCH')||!in_array((string)($tenant['role']??''),['owner','admin','scheduler'],true)){http_response_code(403);exit;}
$q=$db->prepare('SELECT s.id,s.name,s.description,COUNT(cs.client_id) members FROM crm_segments s LEFT JOIN crm_customer_segments cs ON cs.tenant_id=s.tenant_id AND cs.segment_id=s.id WHERE s.tenant_id=? GROUP BY s.id,s.name,s.description ORDER BY s.name');
$q->execute([$tid]);$segments=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$db->prepare('SELECT t.id,t.name,t.body,t.delay_days FROM crm_followup_templates t WHERE t.tenant_id=? ORDER BY t.name');
$q->execute([$tid]);$templates=$q->fetchAll(PDO::FETCH_ASSOC);
$selectedSegment=(int)($_GET['segment_id']??0);
$memberIds=[];
if($selectedSegment>0){
 $q=$db->prepare('SELECT client_id FROM crm_customer_segments WHERE tenant_id=? AND segment_id=?');
 $q->execute([$tid,$selectedSegment]);$memberIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
}
?>
<div class="crm-grid">
<section class="crm-panel"><h2>Customer segments</h2>
<form method="post" class="crm-form">
<input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="segment_create"><input type="hidden" name="tab" value="segments">
<label>Segment name<input name="name" maxlength="100" required placeholder="Repeat customers"></label>
<label>Description<input name="description" maxlength="500"></label>
<button type="submit">Create segment</button></form>
<h3>Segments</h3><div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Segment</th><th>Members</th><th></th></tr></thead><tbody>
<?php foreach($segments as $segment): ?><tr><td><strong><?=$esc($segment['name'])?></strong><div class="crm-muted"><?=$esc($segment['description'])?></div></td><td><?=(int)$segment['members']?></td><td><a href="<?=$esc($base.'&tab=segments&segment_id='.(int)$segment['id'])?>">Manage</a></td></tr><?php endforeach; ?>
</tbody></table></div>
</section>
<section class="crm-panel"><h2>Segment membership</h2>
<?php if($selectedSegment<1||!in_array($selectedSegment,array_map(static fn($s)=>(int)$s['id'],$segments),true)): ?><p class="crm-muted">Select a segment to manage its customers.</p>
<?php else: ?>
<form method="post" class="crm-form"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="segment_membership"><input type="hidden" name="tab" value="segments"><input type="hidden" name="segment_id" value="<?=$selectedSegment?>">
<label>Customer<select name="client_id" required><option value="">Select customer</option><?php $customerOptions(); ?></select></label>
<label>Membership<select name="membership"><option value="add">Add to segment</option><option value="remove">Remove from segment</option></select></label>
<button type="submit">Update membership</button></form>
<p class="crm-muted"><?=count($memberIds)?> customers assigned to this segment.</p>
<?php foreach($customers as $customer): if(!in_array((int)$customer['id'],$memberIds,true))continue; ?>
<p><?=$esc(trim($customer['first_name'].' '.$customer['last_name']))?> · <a href="<?=$esc($base.'&tab=customers&client_id='.(int)$customer['id'])?>">View profile</a></p>
<?php endforeach; ?>
<?php endif; ?></section>
</div>
<div class="crm-grid">
<section class="crm-panel"><h2>Follow-up templates</h2>
<form method="post" class="crm-form">
<input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="template_create"><input type="hidden" name="tab" value="segments">
<label>Template name<input name="name" maxlength="100" required placeholder="Post-service check-in"></label>
<label>Message<textarea name="body" maxlength="5000" required></textarea></label>
<label>Days until due<input name="delay_days" type="number" min="0" max="365" value="3" required></label>
<button type="submit">Save template</button></form>
</section>
<section class="crm-panel"><h2>Schedule follow-up</h2>
<p class="crm-muted">Create an internal follow-up task from a reusable template. No customer message is sent automatically.</p>
<form method="post" class="crm-form">
<input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="template_schedule"><input type="hidden" name="tab" value="segments">
<label>Template<select name="template_id" required><option value="">Select template</option><?php foreach($templates as $template): ?><option value="<?=(int)$template['id']?>"><?=$esc($template['name'])?> · +<?=(int)$template['delay_days']?> days</option><?php endforeach; ?></select></label>
<label>Customer<select name="client_id" required><option value="">Select customer</option><?php $customerOptions(); ?></select></label>
<button type="submit">Schedule task</button></form>
<h3>Saved templates</h3>
<?php foreach($templates as $template): ?><p><strong><?=$esc($template['name'])?></strong> · +<?=(int)$template['delay_days']?> days<br><span class="crm-muted"><?=$esc($template['body'])?></span></p><?php endforeach; ?>
</section>
</div>
