<?php
declare(strict_types=1);
if(!defined('BRITE_MODULE_DISPATCH') || !in_array((string)($tenant['role']??''),['owner','admin','scheduler'],true)){http_response_code(403);exit;}
$periods=['30'=>'Last 30 days','90'=>'Last 90 days','365'=>'Last 365 days','all'=>'All time'];
$period=(string)($_GET['period']??'90');if(!isset($periods[$period]))$period='90';
$since=$period==='all'?null:(new DateTimeImmutable('today'))->modify('-'.(int)$period.' days')->format('Y-m-d 00:00:00');
$read=function(string $sql,array $params=[])use($db):array{$q=$db->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);};
$filter=$since?' AND created_at >= ?':'';
$params=$since?[$tid,$since]:[$tid];
$leadTotals=$read('SELECT status,COUNT(*) total FROM crm_leads WHERE tenant_id=?'.$filter.' GROUP BY status',$params);
$sources=$read("SELECT COALESCE(NULLIF(TRIM(source),''),'Unknown') source,COUNT(*) total,SUM(status='converted') converted FROM crm_leads WHERE tenant_id=?".$filter.' GROUP BY source ORDER BY total DESC LIMIT 15',$params);
$pipeline=$read('SELECT stage,COUNT(*) total,COALESCE(SUM(estimated_value),0) amount FROM crm_opportunities WHERE tenant_id=?'.$filter.' GROUP BY stage',$params);
$quoteFilter=$since?' AND r.created_at >= ?':'';
$quotes=$read('SELECT r.status,COUNT(*) total FROM contractor_quote_requests r WHERE r.tenant_id=?'.$quoteFilter.' GROUP BY r.status',$params);
$trend=$read("SELECT DATE_FORMAT(created_at,'%Y-%m') month,COUNT(*) leads,SUM(status='converted') converted FROM crm_leads WHERE tenant_id=?".$filter." GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month DESC LIMIT 12",$params);
$leadCount=array_sum(array_map(static fn($r)=>(int)$r['total'],$leadTotals));
$converted=array_sum(array_map(static fn($r)=>$r['status']==='converted'?(int)$r['total']:0,$leadTotals));
$quoteCount=array_sum(array_map(static fn($r)=>(int)$r['total'],$quotes));
$accepted=array_sum(array_map(static fn($r)=>$r['status']==='accepted'?(int)$r['total']:0,$quotes));
$declined=array_sum(array_map(static fn($r)=>$r['status']==='declined'?(int)$r['total']:0,$quotes));
$decided=$accepted+$declined;
$openValue=array_sum(array_map(static fn($r)=>in_array($r['stage'],['new','contacted','quoted'],true)?(float)$r['amount']:0,$pipeline));
$wonValue=array_sum(array_map(static fn($r)=>$r['stage']==='won'?(float)$r['amount']:0,$pipeline));
$pct=static fn(int $part,int $total)=>$total?number_format(100*$part/$total,1).'%':'—';
?>
<section class="crm-panel">
 <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
 <div><h2 style="margin:0">Sales analytics</h2><p class="crm-muted">Live, tenant-scoped results. Reporting period is based on each record's creation date.</p></div>
 <form method="get" class="crm-inline" aria-label="Reporting period">
  <input type="hidden" name="module" value="crm"><input type="hidden" name="tab" value="reports">
  <label for="crmReportPeriod">Period</label><select id="crmReportPeriod" name="period"><?php foreach($periods as $key=>$name): ?><option value="<?=$esc($key)?>" <?=$period===$key?'selected':''?>><?=$esc($name)?></option><?php endforeach; ?></select><button type="submit">Apply</button>
 </form></div>
 <div class="crm-stats">
 <?php foreach([['Leads',$leadCount],['Lead conversion',$pct($converted,$leadCount)],['Quote requests',$quoteCount],['Quote acceptance',$pct($accepted,$decided)],['Open pipeline','$'.number_format($openValue,2)],['Won pipeline','$'.number_format($wonValue,2)]] as $metric): ?>
 <div class="crm-stat"><span class="crm-muted"><?=$esc($metric[0])?></span><strong><?=$esc($metric[1])?></strong></div>
 <?php endforeach; ?></div>
 <p class="crm-muted">Lead conversion = converted leads / all leads created in the period. Quote acceptance = accepted / (accepted + declined) quote requests created in the period. Open and won pipeline values are estimates, not recognized revenue.</p>
 <div class="crm-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
 <div class="crm-panel"><h3>Sales pipeline</h3><div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Stage</th><th>Opportunities</th><th>Estimated value</th></tr></thead><tbody>
 <?php foreach(['new','contacted','quoted','won','lost'] as $stage): $row=current(array_filter($pipeline,static fn($r)=>$r['stage']===$stage))?:null; ?><tr><td><?=ucfirst($stage)?></td><td><?=(int)($row['total']??0)?></td><td>$<?=number_format((float)($row['amount']??0),2)?></td></tr><?php endforeach; ?>
 </tbody></table></div></div>
 <div class="crm-panel"><h3>Lead sources</h3><div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Source</th><th>Leads</th><th>Converted</th><th>Rate</th></tr></thead><tbody>
 <?php foreach($sources as $row): ?><tr><td><?=$esc($row['source'])?></td><td><?=(int)$row['total']?></td><td><?=(int)$row['converted']?></td><td><?=$pct((int)$row['converted'],(int)$row['total'])?></td></tr><?php endforeach; ?>
 </tbody></table><?php if(!$sources): ?><p class="crm-muted">No leads in this period.</p><?php endif; ?></div></div>
 </div>
 <div class="crm-panel"><h3>Monthly lead trend</h3><div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Month</th><th>New leads</th><th>Converted (current status)</th></tr></thead><tbody>
 <?php foreach($trend as $row): ?><tr><td><?=$esc($row['month'])?></td><td><?=(int)$row['leads']?></td><td><?=(int)$row['converted']?></td></tr><?php endforeach; ?>
 </tbody></table><?php if(!$trend): ?><p class="crm-muted">No trend data for the selected period.</p><?php endif; ?></div></div>
</section>
