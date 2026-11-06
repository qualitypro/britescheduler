<?php
declare(strict_types=1);
if (!defined('BRITE_MODULE_DISPATCH') || !in_array((string)($tenant['role']??''),['owner','admin','scheduler'],true)) { http_response_code(403); exit; }
$customerId=(int)($_GET['client_id']??0);
if($customerId<1){echo '<p class="crm-muted">Select a customer to see their complete history.</p>';return;}
$q=$db->prepare('SELECT id,first_name,last_name,company_name,email,phone,notes,status FROM clients WHERE tenant_id=? AND id=?');
$q->execute([$tid,$customerId]);$customer=$q->fetch(PDO::FETCH_ASSOC);
if(!$customer){echo '<p role="alert">Customer unavailable.</p>';return;}
$events=[];
$add=function(string $type,array $rows)use(&$events):void{
 foreach($rows as $row)$events[]=['type'=>$type,'date'=>(string)$row['event_at'],'title'=>(string)$row['title'],'detail'=>(string)($row['detail']??''),'status'=>(string)($row['status']??'')];
};
$q=$db->prepare('SELECT starts_at event_at,title,description detail,status FROM appointments WHERE tenant_id=? AND client_id=? ORDER BY starts_at DESC LIMIT 75');
$q->execute([$tid,$customerId]);$add('Appointment',$q->fetchAll(PDO::FETCH_ASSOC));
$q=$db->prepare('SELECT created_at event_at,CONCAT("Quote request #",id) title,LEFT(description,500) detail,status FROM contractor_quote_requests WHERE tenant_id=? AND client_id=? ORDER BY created_at DESC LIMIT 75');
$q->execute([$tid,$customerId]);$add('Quote request',$q->fetchAll(PDO::FETCH_ASSOC));
$q=$db->prepare('SELECT created_at event_at,CONCAT("CRM ",REPLACE(kind,"_"," ")) title,LEFT(body,500) detail,IF(completed_at IS NULL,"Open","Completed") status FROM crm_activities WHERE tenant_id=? AND (client_id=? OR lead_id IN (SELECT id FROM crm_leads WHERE tenant_id=? AND client_id=?)) ORDER BY created_at DESC LIMIT 75');
$q->execute([$tid,$customerId,$tid,$customerId]);$add('Activity',$q->fetchAll(PDO::FETCH_ASSOC));
$q=$db->prepare('SELECT created_at event_at,title,notes detail,stage status FROM crm_opportunities WHERE tenant_id=? AND (client_id=? OR lead_id IN (SELECT id FROM crm_leads WHERE tenant_id=? AND client_id=?)) ORDER BY created_at DESC LIMIT 75');
$q->execute([$tid,$customerId,$tid,$customerId]);$add('Opportunity',$q->fetchAll(PDO::FETCH_ASSOC));
usort($events,static fn($a,$b)=>strcmp($b['date'],$a['date']));
$events=array_slice($events,0,150);
// Read-only, tenant-scoped customer summary for the CRM workspace.
require_once dirname(__DIR__,2).'/app/TenantClock.php';
$tenantNow=TenantClock::now($db,$tid);
$summary=['appointments'=>0,'upcoming'=>0,'open_quotes'=>0,'open_followups'=>0];
$q=$db->prepare("SELECT COUNT(*) total, COALESCE(SUM(starts_at >= ? AND status IN ('tentative','scheduled','confirmed')),0) upcoming FROM appointments WHERE tenant_id=? AND client_id=?");
$q->execute([$tenantNow,$tid,$customerId]);$counts=$q->fetch(PDO::FETCH_ASSOC);
$summary['appointments']=(int)$counts['total'];$summary['upcoming']=(int)$counts['upcoming'];
$q=$db->prepare("SELECT COUNT(*) FROM contractor_quote_requests WHERE tenant_id=? AND client_id=? AND status NOT IN ('accepted','rejected','cancelled','expired','completed')");
$q->execute([$tid,$customerId]);$summary['open_quotes']=(int)$q->fetchColumn();
$q=$db->prepare("SELECT COUNT(*) FROM crm_activities WHERE tenant_id=? AND kind='follow_up' AND completed_at IS NULL AND (client_id=? OR lead_id IN (SELECT id FROM crm_leads WHERE tenant_id=? AND client_id=?))");
$q->execute([$tid,$customerId,$tid,$customerId]);$summary['open_followups']=(int)$q->fetchColumn();
?>
<section class="crm-panel" id="crmCustomer360" aria-label="Customer 360 profile">
 <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;flex-wrap:wrap">
  <div><h2 style="margin:0">Customer 360°</h2><h3 style="margin:6px 0"><?=$esc(trim($customer['first_name'].' '.$customer['last_name']))?></h3>
  <?php if($customer['company_name']): ?><p class="crm-muted"><?=$esc($customer['company_name'])?></p><?php endif; ?></div>
  <a href="<?=$esc($base.'&tab=customers')?>">Close profile</a>
 </div>
 <div class="crm-stats">
  <div class="crm-stat"><span class="crm-muted">Email</span><div><?=$esc($customer['email']?:'Not provided')?></div></div>
  <div class="crm-stat"><span class="crm-muted">Phone</span><div><?=$esc($customer['phone']?:'Not provided')?></div></div>
  <div class="crm-stat"><span class="crm-muted">Status</span><div><?=$esc(ucfirst($customer['status']))?></div></div>
 </div>
 <div class="crm-stats" aria-label="Customer activity summary">
  <?php foreach(['appointments'=>'Appointments','upcoming'=>'Upcoming','open_quotes'=>'Open quotes','open_followups'=>'Open follow-ups'] as $key=>$label): ?>
  <div class="crm-stat"><span class="crm-muted"><?=$esc($label)?></span><div><strong><?=$summary[$key]?></strong></div></div>
  <?php endforeach; ?>
 </div>
 <?php if($customer['notes']): ?><div class="crm-panel"><h3>Customer notes</h3><p style="white-space:pre-wrap"><?=$esc($customer['notes'])?></p></div><?php endif; ?>
 <h3>Unified activity timeline</h3>
 <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
  <label for="crmTimelineType">Show events</label>
  <select id="crmTimelineType" aria-controls="crmTimelineTable">
   <option value="">All events</option><option value="Appointment">Appointments</option><option value="Quote request">Quote requests</option><option value="Activity">CRM activities</option><option value="Opportunity">Opportunities</option>
  </select>
  <span class="crm-muted" id="crmTimelineCount" role="status" aria-live="polite"></span>
 </div>
 <p class="crm-muted">Appointments, quote requests, CRM activities and opportunities for this tenant. Most recent 150 events shown.</p>
 <?php if(!$events): ?><p>No customer history yet.</p><?php else: ?>
 <div class="crm-table-wrap"><table class="crm-table" id="crmTimelineTable"><thead><tr><th>When</th><th>Type</th><th>Event</th><th>Status</th></tr></thead><tbody>
 <?php foreach($events as $event): ?><tr data-event-type="<?=$esc($event['type'])?>"><td><?=$esc($event['date'])?></td><td><span class="crm-pill"><?=$esc($event['type'])?></span></td><td><strong><?=$esc($event['title'])?></strong><?php if($event['detail']): ?><p class="crm-muted" style="white-space:pre-wrap;margin:4px 0"><?=$esc($event['detail'])?></p><?php endif; ?></td><td><?=$esc($event['status'])?></td></tr><?php endforeach; ?>
 </tbody></table></div><?php endif; ?>
 <script>
 (() => {
   const filter=document.getElementById('crmTimelineType');
   const table=document.getElementById('crmTimelineTable');
   const count=document.getElementById('crmTimelineCount');
   if(!filter||!table||!count)return;
   const rows=Array.from(table.querySelectorAll('tbody tr[data-event-type]'));
   const refresh=()=>{
     let shown=0;
     for(const row of rows){
       row.hidden=!!filter.value && row.dataset.eventType!==filter.value;
       if(!row.hidden)shown++;
     }
     count.textContent=shown+' of '+rows.length+' events';
   };
   filter.addEventListener('change',refresh);
   refresh();
 })();
 </script>
</section>
