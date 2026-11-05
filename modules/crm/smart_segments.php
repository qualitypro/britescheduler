<?php
declare(strict_types=1);
if(!defined('BRITE_MODULE_DISPATCH')||!in_array((string)($tenant['role']??''),['owner','admin','scheduler'],true)){http_response_code(403);exit;}
require_once dirname(__DIR__,2).'/app/CrmSmartSegments.php';
$settings=CrmSmartSegments::settings($db,$tid);
$smartRules=CrmSmartSegments::rules($settings);
$rule=(string)($_GET['smart']??'repeat');if(!isset($smartRules[$rule]))$rule='repeat';
$matches=CrmSmartSegments::customers($db,$tid,$rule,$settings);
$q=$db->prepare('SELECT id,name,delay_days FROM crm_followup_templates WHERE tenant_id=? ORDER BY name');
$q->execute([$tid]);$campaignTemplates=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$db->prepare('SELECT id,segment_key,recipients,created_at FROM crm_followup_campaigns WHERE tenant_id=? ORDER BY id DESC LIMIT 10');
$q->execute([$tid]);$recentCampaigns=$q->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="crm-panel">
 <h2>Smart customer segments</h2>
 <p class="crm-muted">Live classifications calculated from tenant-owned scheduling, quote and CRM records. No manual membership changes required.</p>
 <nav class="crm-tabs crm-smart-type-tabs" aria-label="Smart segment types">
 <?php foreach($smartRules as $key=>$definition): ?><a href="<?=$esc($base.'&tab=smart&smart='.$key)?>" <?=$key===$rule?'aria-current="page"':''?>><span class="crm-smart-type-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php switch($key):
 case 'repeat': ?><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/><path d="M12 7v5l3 2"/><?php break;
 case 'inactive': ?><circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 3M3 3l4 4"/><?php break;
 case 'high_value': ?><path d="M3 20V4M3 20h18M7 16l5-6 4 3 5-8"/><path d="M17 5h4v4"/><?php break;
 case 'quotes': ?><path d="M4 4h16v12H9l-5 4z"/><path d="M8 9h8M8 12h5"/><?php break;
 case 'new': ?><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M19 8v8M15 12h8"/><?php break;
 endswitch; ?></svg></span><span class="crm-smart-type-label"><?=$esc($definition['label'])?></span></a><?php endforeach; ?>
 </nav>
 <h3><?=$esc($smartRules[$rule]['label'])?> <span class="crm-pill"><?=count($matches)?><?=$matches&&count($matches)===200?'+':''?></span></h3>
 <p class="crm-muted"><?=$esc($smartRules[$rule]['description'])?>. Up to 200 matching customers are shown.</p>
 <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Customer</th><th>Appointments</th><th>Last appointment</th><th>Open opportunity value</th><th>Active quotes</th></tr></thead><tbody>
 <?php foreach($matches as $customer): ?><tr>
 <td><a href="<?=$esc($base.'&tab=customers&client_id='.(int)$customer['id'])?>"><?=$esc(trim($customer['first_name'].' '.$customer['last_name']))?></a><div class="crm-muted"><?=$esc($customer['company_name'])?></div></td>
 <td><?=(int)$customer['appointments']?></td><td><?=$esc($customer['last_appointment']?:'—')?></td><td>$<?=number_format((float)$customer['open_value'],2)?></td><td><?=(int)$customer['active_quotes']?></td>
 </tr><?php endforeach; ?></tbody></table>
 <?php if(!$matches): ?><p class="crm-muted">No customers currently match this rule.</p><?php endif; ?></div>
 <div class="crm-grid">
 <section class="crm-panel"><h3>Smart segment rules</h3><p class="crm-muted">Adjust thresholds for this tenant. These values affect live classifications.</p>
 <form method="post" class="crm-form">
 <input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="smart_settings"><input type="hidden" name="tab" value="smart">
 <label>Repeat customer: minimum appointments<input type="number" name="repeat_min" min="2" max="100" value="<?=(int)$settings['repeat_min']?>" required></label>
 <label>Inactive after days<input type="number" name="inactive_days" min="7" max="730" value="<?=(int)$settings['inactive_days']?>" required></label>
 <label>High-value opportunity threshold ($)<input type="number" name="high_value_min" min="1" max="10000000" value="<?=(int)$settings['high_value_min']?>" required></label>
 <button type="submit">Save segment rules</button></form></section>
 <section class="crm-panel"><h3>Targeted follow-up campaign</h3><p class="crm-muted">Creates internal follow-up tasks for matching customers. Maximum 100 recipients per campaign. No emails or messages are sent.</p>
 <form method="post" class="crm-form">
 <input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="smart_campaign"><input type="hidden" name="tab" value="smart">
 <label>Smart segment<select name="smart_rule"><?php foreach($smartRules as $key=>$definition): ?><option value="<?=$esc($key)?>" <?=$rule===$key?'selected':''?>><?=$esc($definition['label'])?></option><?php endforeach; ?></select></label>
 <label>Follow-up template<select name="template_id" required><option value="">Choose template</option><?php foreach($campaignTemplates as $template): ?><option value="<?=(int)$template['id']?>"><?=$esc($template['name'])?> (+<?=(int)$template['delay_days']?> days)</option><?php endforeach; ?></select></label>
 <button type="submit" onclick="return confirm('Create internal follow-up tasks for up to 100 matching customers?')">Create campaign tasks</button></form>
 <h4>Recent campaigns</h4><?php foreach($recentCampaigns as $campaign): ?><p class="crm-muted">#<?=(int)$campaign['id']?> · <?=$esc($smartRules[$campaign['segment_key']]['label']??$campaign['segment_key'])?> · <?=(int)$campaign['recipients']?> tasks · <?=$esc($campaign['created_at'])?></p><?php endforeach; ?></section>
 </div>
 <p class="crm-muted">Appointment counts include all statuses. Segment matches are evaluated at the time the page loads or a campaign is created.</p>
</section>
