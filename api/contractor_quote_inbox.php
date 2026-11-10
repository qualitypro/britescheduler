<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('contractor');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');json_response(['error'=>'Method not allowed'],405);}
$tid=(int)$tenant['tenant_id'];$pdo=Database::connection();
$q=$pdo->prepare("SELECT id FROM contractors WHERE tenant_id=? AND user_id=? AND status='active' LIMIT 1");
$q->execute([$tid,(int)$user['id']]);$cid=(int)($q->fetchColumn()?:0);
if(!$cid)json_response(['error'=>'Active contractor account required'],403);
$paging = null;
if (isset($_GET['page']) || isset($_GET['per_page'])) {
    require_once dirname(__DIR__).'/app/ServerPagination.php';
    try { $paging=ServerPagination::fromQuery($_GET); }
    catch (InvalidArgumentException $e) {
        json_response(['error'=>$e->getMessage()],422);
    }
}

$total=null;
if($paging!==null){
 $count=$pdo->prepare('SELECT COUNT(*) FROM contractor_quote_requests WHERE tenant_id=? AND contractor_id=?');
 $count->execute([$tid,$cid]);$total=(int)$count->fetchColumn();
}
$limitSql=$paging===null?'LIMIT 100':'LIMIT '.$paging['per_page'].' OFFSET '.$paging['offset'];
$q=$pdo->prepare("SELECT r.id,r.status,r.description,r.preferred_date,r.created_at,r.service_id,s.name AS service_name,cl.label AS location_label,cl.address1,cl.address2,cl.city,cl.state,cl.postal_code,COALESCE(NULLIF(TRIM(CONCAT(c.first_name,' ',c.last_name)),''),'Client') AS client_name FROM contractor_quote_requests r JOIN clients c ON c.id=r.client_id AND c.tenant_id=r.tenant_id JOIN client_locations cl ON cl.id=r.client_location_id AND cl.tenant_id=r.tenant_id AND cl.client_id=r.client_id LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id WHERE r.tenant_id=? AND r.contractor_id=? ORDER BY r.created_at DESC,r.id DESC {$limitSql}");
$q->execute([$tid,$cid]);$requests=$q->fetchAll(PDO::FETCH_ASSOC);
$p=$pdo->prepare("SELECT id FROM contractor_quote_request_photos WHERE tenant_id=? AND request_id=? ORDER BY id LIMIT 10");
foreach($requests as &$request){
 $p->execute([$tid,(int)$request['id']]);
 $request['photos']=array_map(static fn($photo)=>['id'=>(int)$photo['id'],'url'=>app_url('/api/quote_request_photo.php?id='.(int)$photo['id'])],$p->fetchAll(PDO::FETCH_ASSOC));
}unset($request);
json_response(['ok'=>true,'requests'=>$requests,'pagination'=>$paging===null?null:ServerPagination::metadata($paging,$total)]);
