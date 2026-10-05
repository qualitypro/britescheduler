<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
Auth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();
$q=$pdo->prepare("SELECT id,title,description,starts_at,ends_at,status,is_public FROM appointments WHERE tenant_id=? AND starts_at>=DATE_SUB(NOW(),INTERVAL 1 YEAR) ORDER BY starts_at");
$q->execute([$tid]);$out=[];
foreach($q->fetchAll() as $a){$out[]=['event_id'=>$a['id'],'title'=>$a['title'],'description'=>$a['description'],'user_id'=>'','start'=>$a['starts_at'],'end'=>$a['ends_at'],'repeat_type'=>'0','is_public'=>(bool)$a['is_public'],'is_active'=>$a['status']!=='cancelled'];}
json_response(['events'=>$out]);
