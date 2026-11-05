<?php
declare(strict_types=1);
if (!defined('BRITE_MODULE_DISPATCH')) { http_response_code(403); exit; }
if (!in_array((string)$tenant['role'], ['owner','admin','scheduler'], true)) {
 http_response_code(403); echo '<section class="card"><h1>Access denied</h1></section>'; return;
}
$db=Database::connection(); $tid=(int)$tenant['tenant_id']; $uid=(int)$user['id'];
$esc=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$tabs=['overview'=>'Overview','leads'=>'Leads','customers'=>'Customers','pipeline'=>'Opportunities','activities'=>'Activities'];
$tab=(string)($_GET['tab']??'overview'); if(!isset($tabs[$tab]))$tab='overview';
$base=app_url('/module.php?module=crm');
$flash=(string)($_GET['notice']??'');
$checkLink=static function(string $table,string $field,int $id)use($db,$tid):bool {
 $q=$db->prepare("SELECT 1 FROM $table WHERE tenant_id=? AND $field=? LIMIT 1");$q->execute([$tid,$id]);return (bool)$q->fetchColumn();
};
try {
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  verify_csrf();
  $action=(string)($_POST['action']??'');
  $postedTab=(string)($_POST['tab']??'overview');if(!isset($tabs[$postedTab]))$postedTab='overview';
  $required=static function(string $key,int $max=190):string {
   $value=trim((string)($_POST[$key]??''));
   if($value===''||strlen($value)>$max)throw new RuntimeException('Please provide a valid '.$key.'.');
   return $value;
  };
  $optional=static function(string $key,int $max=190):?string {
   $value=trim((string)($_POST[$key]??''));if(strlen($value)>$max)throw new RuntimeException('Field too long: '.$key);
   return $value===''?null:$value;
  };
  $link=static function(string $key,string $table)use($checkLink):?int {
   $id=(int)($_POST[$key]??0);
   if($id && !$checkLink($table,'id',$id))throw new RuntimeException('Invalid or inaccessible linked record.');
   return $id?:null;
  };
  if($action==='quote_backfill'){
   require_once dirname(__DIR__,2).'/app/CrmQuoteSync.php';
   $q=$db->prepare("SELECT r.id FROM contractor_quote_requests r LEFT JOIN crm_quote_links l ON l.tenant_id=r.tenant_id AND l.request_id=r.id WHERE r.tenant_id=? AND l.request_id IS NULL ORDER BY r.id LIMIT 100");
   $q->execute([$tid]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
   $db->beginTransaction();
   try{
    foreach($ids as $requestId)CrmQuoteSync::sync($db,$tid,(int)$requestId,$uid);
    $db->commit();
   }catch(Throwable $e){$db->rollBack();throw $e;}
   $flash='Imported '.count($ids).' quote requests.';
  }elseif($action==='lead_create'){
   $name=$required('name');$email=$optional('email');$phone=$optional('phone',40);
   if($email!==null&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid email address.');
   $source=$optional('source',80)??'manual';$notes=$optional('notes',5000);
   $q=$db->prepare('INSERT INTO crm_leads(tenant_id,name,email,phone,source,notes,created_by) VALUES(?,?,?,?,?,?,?)');
   $q->execute([$tid,$name,$email,$phone,$source,$notes,$uid]);
  }elseif($action==='lead_status'){
   $id=(int)($_POST['id']??0);$status=$required('status',30);
   if(!in_array($status,['new','contacted','qualified','converted','lost'],true))throw new RuntimeException('Invalid status.');
   $q=$db->prepare('UPDATE crm_leads SET status=? WHERE id=? AND tenant_id=?');$q->execute([$status,$id,$tid]);
   if(!$q->rowCount()&&!$checkLink('crm_leads','id',$id))throw new RuntimeException('Lead not found.');
  }elseif($action==='lead_convert'){
   $id=(int)($_POST['id']??0);
   if($id<1)throw new RuntimeException('Invalid lead.');
   $db->beginTransaction();
   try {
    $q=$db->prepare('SELECT * FROM crm_leads WHERE tenant_id=? AND id=? FOR UPDATE');
    $q->execute([$tid,$id]);$lead=$q->fetch(PDO::FETCH_ASSOC);
    if(!$lead)throw new RuntimeException('Lead not found.');
    if($lead['client_id']){
     $q=$db->prepare('SELECT 1 FROM clients WHERE tenant_id=? AND id=?');
     $q->execute([$tid,$lead['client_id']]);
     if(!$q->fetchColumn())throw new RuntimeException('Linked customer no longer exists.');
     $clientId=(int)$lead['client_id'];
    }else{
     $name=trim((string)$lead['name']);
     $parts=preg_split('/\\s+/', $name, 2);
     $first=substr((string)($parts[0]??''),0,100);
     $last=substr((string)($parts[1]??''),0,100);
     if($first==='')throw new RuntimeException('Lead name is required.');
     $q=$db->prepare("INSERT INTO clients(tenant_id,first_name,last_name,email,phone,notes,status) VALUES(?,?,?,?,?,?,'active')");
     $q->execute([$tid,$first,$last,$lead['email'],$lead['phone'],$lead['notes']]);
     $clientId=(int)$db->lastInsertId();
    }
    $q=$db->prepare("UPDATE crm_leads SET client_id=?,status='converted' WHERE tenant_id=? AND id=?");
    $q->execute([$clientId,$tid,$id]);
    $q=$db->prepare('UPDATE crm_opportunities SET client_id=? WHERE tenant_id=? AND lead_id=? AND client_id IS NULL');
    $q->execute([$clientId,$tid,$id]);
    $q=$db->prepare('INSERT INTO crm_activities(tenant_id,lead_id,client_id,kind,body,created_by) VALUES(?,?,?,\'note\',\'Lead converted to scheduling customer\',?)');
    $q->execute([$tid,$id,$clientId,$uid]);
    $db->commit();
   }catch(Throwable $e){$db->rollBack();throw $e;}
  }elseif($action==='opportunity_create'){
   $title=$required('title');$lead=$link('lead_id','crm_leads');$client=$link('client_id','clients');
   if(!$lead&&!$client)throw new RuntimeException('Select a lead or customer.');
   $value=(string)($_POST['estimated_value']??'0');
   if(!preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/',$value))throw new RuntimeException('Invalid estimated value.');
   $date=$optional('next_follow_up',10);
   if($date&&(!DateTimeImmutable::createFromFormat('!Y-m-d',$date)||DateTimeImmutable::createFromFormat('!Y-m-d',$date)->format('Y-m-d')!==$date))throw new RuntimeException('Invalid follow-up date.');
   $q=$db->prepare('INSERT INTO crm_opportunities(tenant_id,title,lead_id,client_id,estimated_value,next_follow_up,notes,created_by) VALUES(?,?,?,?,?,?,?,?)');
   $q->execute([$tid,$title,$lead,$client,$value,$date,$optional('notes',5000),$uid]);
  }elseif($action==='opportunity_stage'){
   $id=(int)($_POST['id']??0);$stage=$required('stage',30);
   if(!in_array($stage,['new','contacted','quoted','won','lost'],true))throw new RuntimeException('Invalid stage.');
   $q=$db->prepare('UPDATE crm_opportunities SET stage=? WHERE id=? AND tenant_id=?');$q->execute([$stage,$id,$tid]);
   if(!$q->rowCount()&&!$checkLink('crm_opportunities','id',$id))throw new RuntimeException('Opportunity not found.');
  }elseif($action==='activity_create'){
   $kind=$required('kind',30);if(!in_array($kind,['note','call','email','meeting','follow_up'],true))throw new RuntimeException('Invalid activity type.');
   $lead=$link('lead_id','crm_leads');$client=$link('client_id','clients');$opp=$link('opportunity_id','crm_opportunities');
   if(!$lead&&!$client&&!$opp)throw new RuntimeException('Link an activity to a lead, customer or opportunity.');
   $body=$required('body',5000);$due=$optional('due_at',10);
   if($due&&(!DateTimeImmutable::createFromFormat('!Y-m-d',$due)||DateTimeImmutable::createFromFormat('!Y-m-d',$due)->format('Y-m-d')!==$due))throw new RuntimeException('Invalid due date.');
   $q=$db->prepare('INSERT INTO crm_activities(tenant_id,lead_id,client_id,opportunity_id,kind,body,due_at,created_by) VALUES(?,?,?,?,?,?,?,?)');
   $q->execute([$tid,$lead,$client,$opp,$kind,$body,$due,$uid]);
  }elseif($action==='activity_complete'){
   $id=(int)($_POST['id']??0);$q=$db->prepare('UPDATE crm_activities SET completed_at=NOW() WHERE id=? AND tenant_id=? AND completed_at IS NULL');$q->execute([$id,$tid]);
   if(!$q->rowCount()&&!$checkLink('crm_activities','id',$id))throw new RuntimeException('Activity not found.');
  }else throw new RuntimeException('Unknown action.');
  if($flash==='')$flash='saved';$tab=$postedTab;
 }
} catch(Throwable $e){$flash=$e instanceof PDOException?'CRM storage unavailable. Apply migration 030_crm_module.sql.':$e->getMessage();}
try{
 $q=$db->prepare('SELECT id,name,email,phone,source,status,client_id,notes,updated_at FROM crm_leads WHERE tenant_id=? ORDER BY updated_at DESC LIMIT 150');$q->execute([$tid]);$leads=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$db->prepare('SELECT id,first_name,last_name,company_name,email,phone,status FROM clients WHERE tenant_id=? ORDER BY last_name,first_name LIMIT 150');$q->execute([$tid]);$customers=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$db->prepare('SELECT o.*,l.name lead_name,CONCAT(c.first_name," ",c.last_name) client_name FROM crm_opportunities o LEFT JOIN crm_leads l ON l.id=o.lead_id AND l.tenant_id=o.tenant_id LEFT JOIN clients c ON c.id=o.client_id AND c.tenant_id=o.tenant_id WHERE o.tenant_id=? ORDER BY o.updated_at DESC LIMIT 150');$q->execute([$tid]);$opportunities=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$db->prepare('SELECT a.*,l.name lead_name,CONCAT(c.first_name," ",c.last_name) client_name,o.title opportunity_title FROM crm_activities a LEFT JOIN crm_leads l ON l.id=a.lead_id AND l.tenant_id=a.tenant_id LEFT JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id LEFT JOIN crm_opportunities o ON o.id=a.opportunity_id AND o.tenant_id=a.tenant_id WHERE a.tenant_id=? ORDER BY a.created_at DESC LIMIT 150');$q->execute([$tid]);$activities=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$db->prepare('SELECT COUNT(*) FROM contractor_quote_requests WHERE tenant_id=?');$q->execute([$tid]);$quoteCount=(int)$q->fetchColumn();
 $q=$db->prepare('SELECT COUNT(*) FROM appointments WHERE tenant_id=?');$q->execute([$tid]);$appointmentCount=(int)$q->fetchColumn();
}catch(PDOException $e){echo '<section class="card"><h1>CRM setup required</h1><p>Apply migration <code>030_crm_module.sql</code> before using this module.</p></section>';return;}
$dueFollowUps=array_values(array_filter($activities,static fn($r)=>$r['due_at']!==null && $r['completed_at']===null && $r['due_at']<=date('Y-m-d')));
$token=csrf_token();
$leadOptions=static function(?int $selected=null)use($leads,$esc):void {foreach($leads as $r)echo '<option value="'.(int)$r['id'].'"'.($selected===(int)$r['id']?' selected':'').'>'.$esc($r['name']).'</option>';};
$customerOptions=static function(?int $selected=null)use($customers,$esc):void {foreach($customers as $r)echo '<option value="'.(int)$r['id'].'"'.($selected===(int)$r['id']?' selected':'').'>'.$esc(trim($r['first_name'].' '.$r['last_name'])).'</option>';};
?>
<style>
.crm-shell{display:grid;gap:12px}.crm-tabs{display:flex;gap:4px;overflow-x:auto;border-bottom:1px solid #cbd5e1}.crm-tabs a{white-space:nowrap;text-decoration:none;color:inherit;padding:10px 13px;border-bottom:3px solid transparent}.crm-tabs a[aria-current=page]{font-weight:700;border-bottom-color:#2563eb;background:rgba(37,99,235,.07)}.crm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px}.crm-stat{border:1px solid #cbd5e1;border-radius:8px;padding:12px}.crm-stat strong{font-size:1.55rem;display:block}.crm-grid{display:grid;grid-template-columns:minmax(240px,320px) minmax(0,1fr);gap:12px}.crm-panel{border:1px solid #cbd5e1;border-radius:9px;padding:12px;min-width:0}.crm-form{display:grid;gap:9px}.crm-form label{display:grid;gap:3px;font-size:.88rem;font-weight:600}.crm-form input,.crm-form select,.crm-form textarea{width:100%;min-height:36px;box-sizing:border-box;border-radius:5px!important;padding:7px 9px!important}.crm-form textarea{min-height:72px}.crm-form button{justify-self:start}.crm-table-wrap{overflow-x:auto}.crm-table{width:100%;border-collapse:collapse;font-size:.88rem}.crm-table th,.crm-table td{padding:8px;border-bottom:1px solid #cbd5e1;text-align:left;vertical-align:middle}.crm-inline{display:flex;gap:6px;align-items:center}.crm-inline select{min-width:110px;max-width:160px}.crm-inline button{padding:6px 8px}.crm-muted{font-size:.83rem;color:var(--brite-muted,#64748b)}.crm-pill{display:inline-block;border:1px solid #cbd5e1;border-radius:6px;padding:2px 7px}.crm-shell [hidden]{display:none!important}.crm-customer-row[hidden]{display:none!important}@media(max-width:760px){.crm-grid{grid-template-columns:1fr}.crm-tabs a{padding:9px}}
</style>
<section class="card crm-shell" data-brite-pills="off">
 <div><h1 style="margin:0">CRM</h1><p class="crm-muted" style="margin:4px 0 0">Customers, leads, opportunities and follow-ups in one tenant workspace.</p></div>
 <?php if($flash): ?><p role="status" class="crm-pill"><?=$esc($flash==='saved'?'Changes saved.':$flash)?></p><?php endif; ?>
 <nav class="crm-tabs" aria-label="CRM sections"><?php foreach($tabs as $key=>$label): ?><a href="<?=$esc($base.'&tab='.$key)?>" <?=$tab===$key?'aria-current="page"':''?>><?=$esc($label)?></a><?php endforeach; ?></nav>
 <?php if($tab==='overview'):
 $openLeads=count(array_filter($leads,static fn($r)=>!in_array($r['status'],['converted','lost'],true)));
 $openOpps=array_filter($opportunities,static fn($r)=>!in_array($r['stage'],['won','lost'],true));
 $due=count(array_filter($activities,static fn($r)=>$r['due_at']!==null&&$r['completed_at']===null&&$r['due_at']<=date('Y-m-d')));
 ?>
 <div class="crm-stats">
 <?php foreach([['Customers',count($customers)],['Open leads',$openLeads],['Open opportunities',count($openOpps)],['Follow-ups due',$due],['Quote requests',$quoteCount],['Appointments',$appointmentCount]] as $stat): ?><div class="crm-stat"><span class="crm-muted"><?=$esc($stat[0])?></span><strong><?=$esc($stat[1])?></strong></div><?php endforeach; ?>
 </div>
 <div class="crm-panel"><h2>Pipeline snapshot</h2><div class="crm-stats"><?php foreach(['new','contacted','quoted','won','lost'] as $stage): ?><div class="crm-stat"><span class="crm-muted"><?=ucfirst($stage)?></span><strong><?=count(array_filter($opportunities,static fn($r)=>$r['stage']===$stage))?></strong></div><?php endforeach; ?></div></div>
 <div class="crm-panel"><h2>Quote integration</h2><p class="crm-muted">New quote requests and formal quote updates are automatically linked to CRM opportunities while CRM is active. Import earlier quote requests in batches of up to 100.</p><form method="post"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="quote_backfill"><input type="hidden" name="tab" value="overview"><button type="submit">Import existing quote requests</button></form></div>
 <div class="crm-panel"><h2>Follow-ups requiring attention</h2><?php if(!$dueFollowUps): ?><p class="crm-muted">You're all caught up on logged follow-ups.</p><?php else: ?><div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Due</th><th>Type</th><th>Details</th><th>Action</th></tr></thead><tbody><?php foreach(array_slice($dueFollowUps,0,10) as $item): ?><tr><td><?=$esc($item['due_at'])?></td><td><?=$esc($item['kind'])?></td><td><?=$esc($item['body'])?></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="tab" value="overview"><input type="hidden" name="action" value="activity_complete"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button type="submit">Complete</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
 <p class="crm-muted">Counts for leads, customers, opportunities and activities reflect the most recent 150 records in each list. Quote requests and appointments are tenant-wide totals.</p>
 <?php elseif($tab==='leads'): ?>
 <div class="crm-grid"><div class="crm-panel"><h2>Add lead</h2><form method="post" class="crm-form"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="lead_create"><input type="hidden" name="tab" value="leads"><label>Name<input name="name" maxlength="190" required></label><label>Email<input name="email" type="email" maxlength="190"></label><label>Phone<input name="phone" maxlength="40"></label><label>Source<input name="source" maxlength="80" placeholder="Website, referral, phone…"></label><label>Notes<textarea name="notes" maxlength="5000"></textarea></label><button type="submit">Add lead</button></form></div>
 <div class="crm-panel"><h2>Leads</h2><div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Name</th><th>Contact</th><th>Source</th><th>Status</th></tr></thead><tbody><?php foreach($leads as $r): ?><tr><td><strong><?=$esc($r['name'])?></strong></td><td><?=$esc($r['email'])?><div class="crm-muted"><?=$esc($r['phone'])?></div></td><td><?=$esc($r['source'])?></td><td><form method="post" class="crm-inline"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="tab" value="leads"><input type="hidden" name="action" value="lead_status"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><select name="status" aria-label="Status for <?=$esc($r['name'])?>"><?php foreach(['new','contacted','qualified','converted','lost'] as $v): ?><option value="<?=$v?>" <?=$r['status']===$v?'selected':''?>><?=ucfirst($v)?></option><?php endforeach; ?></select><button type="submit">Save</button></form><?php if($r['status']!=='converted'): ?><form method="post" class="crm-convert-form" style="margin-top:5px" onsubmit="return confirm('Create a scheduling customer from this lead?')"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="lead_convert"><input type="hidden" name="tab" value="leads"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button type="submit">Convert to customer</button></form><?php elseif($r['client_id']): ?><small class="crm-muted">Customer #<?=(int)$r['client_id']?></small><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php if(!$leads): ?><p class="crm-muted">No leads yet.</p><?php endif; ?></div></div></div>
 <?php elseif($tab==='customers'): ?>
 <div class="crm-panel"><h2>Scheduling customers</h2><p class="crm-muted">Select a customer to inspect their complete CRM and service history.</p>
 <label for="crmCustomerSearch">Find customer</label><input id="crmCustomerSearch" type="search" placeholder="Search name, company, email or phone…" style="width:100%;min-height:36px;margin:6px 0 12px">
 <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Customer</th><th>Email</th><th>Phone</th><th>Status</th></tr></thead><tbody>
 <?php foreach($customers as $r): ?><tr class="crm-customer-row" data-search="<?=$esc(strtolower(implode(' ',[$r['first_name'],$r['last_name'],$r['company_name'],$r['email'],$r['phone']])))?>"><td><a href="<?=$esc($base.'&tab=customers&client_id='.(int)$r['id'].'#crmCustomer360')?>"><strong><?=$esc(trim($r['first_name'].' '.$r['last_name']))?></strong></a><div class="crm-muted"><?=$esc($r['company_name'])?></div></td><td><?=$esc($r['email'])?></td><td><?=$esc($r['phone'])?></td><td><?=$esc($r['status'])?></td></tr><?php endforeach; ?>
 </tbody></table></div><?php if(!$customers): ?><p class="crm-muted">No customers yet.</p><?php endif; ?></div>
 <?php if((int)($_GET['client_id']??0)>0)require __DIR__.'/customer360.php'; ?>
 <script>document.getElementById('crmCustomerSearch')?.addEventListener('input',function(){const q=this.value.toLocaleLowerCase().trim();document.querySelectorAll('.crm-customer-row').forEach(row=>{row.hidden=!row.dataset.search.includes(q);});});</script>
 <?php elseif($tab==='pipeline'): ?>
 <div class="crm-grid"><div class="crm-panel"><h2>Add opportunity</h2><form method="post" class="crm-form"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="opportunity_create"><input type="hidden" name="tab" value="pipeline"><label>Title<input name="title" maxlength="190" required></label><label>Related lead<select name="lead_id"><option value="">None</option><?php $leadOptions(); ?></select></label><label>Related customer<select name="client_id"><option value="">None</option><?php $customerOptions(); ?></select></label><label>Estimated value<input type="number" name="estimated_value" min="0" step=".01" value="0" required></label><label>Next follow-up<input type="date" name="next_follow_up"></label><label>Notes<textarea name="notes" maxlength="5000"></textarea></label><button type="submit">Add opportunity</button></form></div><div class="crm-panel"><h2>Opportunities</h2><div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Opportunity</th><th>Value</th><th>Follow-up</th><th>Stage</th></tr></thead><tbody><?php foreach($opportunities as $r): ?><tr><td><strong><?=$esc($r['title'])?></strong><div class="crm-muted"><?=$esc($r['lead_name']??$r['client_name'])?></div></td><td>$<?=number_format((float)$r['estimated_value'],2)?></td><td><?=$esc($r['next_follow_up'])?></td><td><form method="post" class="crm-inline"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="opportunity_stage"><input type="hidden" name="tab" value="pipeline"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><select name="stage" aria-label="Stage for <?=$esc($r['title'])?>"><?php foreach(['new','contacted','quoted','won','lost'] as $v): ?><option value="<?=$v?>" <?=$r['stage']===$v?'selected':''?>><?=ucfirst($v)?></option><?php endforeach; ?></select><button type="submit">Save</button></form></td></tr><?php endforeach; ?></tbody></table><?php if(!$opportunities): ?><p class="crm-muted">No opportunities yet.</p><?php endif; ?></div></div></div>
 <?php elseif($tab==='activities'): ?>
 <div class="crm-grid"><div class="crm-panel"><h2>Log activity or follow-up</h2><form method="post" class="crm-form"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="action" value="activity_create"><input type="hidden" name="tab" value="activities"><label>Type<select name="kind"><?php foreach(['note','call','email','meeting','follow_up'] as $v): ?><option value="<?=$v?>"><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach; ?></select></label><label>Lead<select name="lead_id"><option value="">None</option><?php $leadOptions(); ?></select></label><label>Customer<select name="client_id"><option value="">None</option><?php $customerOptions(); ?></select></label><label>Opportunity<select name="opportunity_id"><option value="">None</option><?php foreach($opportunities as $r): ?><option value="<?=(int)$r['id']?>"><?=$esc($r['title'])?></option><?php endforeach; ?></select></label><label>Details<textarea name="body" maxlength="5000" required></textarea></label><label>Due date<input type="date" name="due_at"></label><button type="submit">Save activity</button></form></div><div class="crm-panel"><h2>Recent activity</h2><div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Activity</th><th>Related to</th><th>Due</th><th>Status</th></tr></thead><tbody><?php foreach($activities as $r): ?><tr><td><strong><?=$esc(ucwords(str_replace('_',' ',$r['kind'])))?></strong><div><?=$esc($r['body'])?></div><small class="crm-muted"><?=$esc($r['created_at'])?></small></td><td><?=$esc($r['lead_name']??$r['client_name']??$r['opportunity_title'])?></td><td><?=$esc($r['due_at'])?></td><td><?php if($r['completed_at']): ?>Done<?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?=$esc($token)?>"><input type="hidden" name="tab" value="activities"><input type="hidden" name="action" value="activity_complete"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button type="submit">Complete</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php if(!$activities): ?><p class="crm-muted">No activity yet.</p><?php endif; ?></div></div></div>
 <?php endif; ?>
</section>
