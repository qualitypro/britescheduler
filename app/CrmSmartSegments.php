<?php
declare(strict_types=1);
final class CrmSmartSegments {
 public static function settings(PDO $db,int $tid):array {
  $defaults=['repeat_min'=>2,'inactive_days'=>90,'high_value_min'=>1000];
  $q=$db->prepare('SELECT setting_key,setting_value FROM crm_smart_settings WHERE tenant_id=?');
  $q->execute([$tid]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)if(array_key_exists($row['setting_key'],$defaults))$defaults[$row['setting_key']]=(int)$row['setting_value'];
  return $defaults;
 }
 public static function rules(array $s):array {
  return [
   'repeat'=>['label'=>'Repeat customers','description'=>'At least '.$s['repeat_min'].' appointments','where'=>'COALESCE(a.appointments,0)>='.(int)$s['repeat_min']],
   'inactive'=>['label'=>'Inactive customers','description'=>'Previously booked, no appointment within '.$s['inactive_days'].' days','where'=>'COALESCE(a.appointments,0)>0 AND a.last_appointment < DATE_SUB(CURDATE(),INTERVAL '.(int)$s['inactive_days'].' DAY)'],
   'high_value'=>['label'=>'High-value opportunities','description'=>'At least $'.number_format($s['high_value_min']).' in open opportunity value','where'=>'COALESCE(o.open_value,0)>='.(int)$s['high_value_min']],
   'quotes'=>['label'=>'Active quote customers','description'=>'At least one outstanding quote request','where'=>'COALESCE(q.active_quotes,0)>0'],
   'new'=>['label'=>'New customers','description'=>'No appointment history yet','where'=>'COALESCE(a.appointments,0)=0']
  ];
 }
 public static function customers(PDO $db,int $tid,string $rule,array $settings,int $limit=200):array {
  $rules=self::rules($settings);if(!isset($rules[$rule]))throw new InvalidArgumentException('Invalid segment');
  $limit=max(1,min(500,$limit));
  $sql="SELECT c.id,c.first_name,c.last_name,c.company_name,c.email,COALESCE(a.appointments,0) appointments,a.last_appointment,COALESCE(o.open_value,0) open_value,COALESCE(q.active_quotes,0) active_quotes
  FROM clients c
  LEFT JOIN (SELECT tenant_id,client_id,COUNT(*) appointments,MAX(starts_at) last_appointment FROM appointments WHERE tenant_id=? GROUP BY tenant_id,client_id) a ON a.tenant_id=c.tenant_id AND a.client_id=c.id
  LEFT JOIN (SELECT tenant_id,client_id,SUM(CASE WHEN stage IN ('new','contacted','quoted') THEN estimated_value ELSE 0 END) open_value FROM crm_opportunities WHERE tenant_id=? GROUP BY tenant_id,client_id) o ON o.tenant_id=c.tenant_id AND o.client_id=c.id
  LEFT JOIN (SELECT tenant_id,client_id,SUM(CASE WHEN status IN ('submitted','more_info_requested','quoted') THEN 1 ELSE 0 END) active_quotes FROM contractor_quote_requests WHERE tenant_id=? GROUP BY tenant_id,client_id) q ON q.tenant_id=c.tenant_id AND q.client_id=c.id
  WHERE c.tenant_id=? AND c.status='active' AND ".$rules[$rule]['where']." ORDER BY c.id DESC LIMIT ".$limit;
  $q=$db->prepare($sql);$q->execute([$tid,$tid,$tid,$tid]);return $q->fetchAll(PDO::FETCH_ASSOC);
 }
}
