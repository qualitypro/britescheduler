<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('client');
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') {
 header('Allow: GET');json_response(['error'=>'Method not allowed'],405);
}
$tid=(int)$tenant['tenant_id'];
$pdo=Database::connection();
$check=$pdo->prepare("SELECT id FROM clients WHERE tenant_id=? AND user_id=? AND status='active' LIMIT 1");
$check->execute([$tid,(int)$user['id']]);
if (!$check->fetchColumn()) json_response(['error'=>'Active client account required'],403);
$id=filter_var($_GET['contractor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (isset($_GET['contractor_id']) && !$id) json_response(['error'=>'Invalid contractor ID'],422);
$service=filter_var($_GET['service_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (isset($_GET['service_id']) && $_GET['service_id']!=='' && !$service) json_response(['error'=>'Invalid service ID'],422);
$zip=trim((string)($_GET['postal_code']??''));
if ($zip!=='' && !preg_match('/^[0-9]{5}$/D',$zip)) json_response(['error'=>'Enter a five-digit ZIP code'],422);
$search=trim((string)($_GET['q']??''));
if (mb_strlen($search,'UTF-8')>100) json_response(['error'=>'Search is too long'],422);
$conditions=["c.tenant_id=?","c.status='active'"];
$args=[$tid];
if ($id) {$conditions[]='c.id=?';$args[]=$id;}
if ($search!=='') {
 $conditions[]="(c.business_name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR p.headline LIKE ? OR p.specialties LIKE ? OR c.skills LIKE ?)";
 $like='%'.$search.'%';
 for($i=0;$i<6;$i++) $args[]=$like;
}
if ($service) {
 $conditions[]="(EXISTS(SELECT 1 FROM contractor_service_offerings o JOIN services s ON s.tenant_id=o.tenant_id AND s.id=o.service_id AND s.active=1 WHERE o.tenant_id=c.tenant_id AND o.contractor_id=c.id AND o.service_id=? AND o.offered=1) OR EXISTS(SELECT 1 FROM contractor_service_qualifications q JOIN services s ON s.tenant_id=q.tenant_id AND s.id=q.service_id AND s.active=1 WHERE q.tenant_id=c.tenant_id AND q.contractor_id=c.id AND q.service_id=? AND q.verification_status IN ('verified','unverified')))";
 $args[]=$service;$args[]=$service;
}
if ($zip!=='') {
 // ZIP filter matches declared active ZIP service areas; radius coverage requires geocoded coordinates.
 $conditions[]="EXISTS(SELECT 1 FROM contractor_service_areas a WHERE a.tenant_id=c.tenant_id AND a.contractor_id=c.id AND a.active=1 AND a.area_type='zip' AND a.postal_code=?)";
 $args[]=$zip;
}
$sql="SELECT c.id,c.first_name,c.last_name,c.business_name,c.skills,c.hourly_rate,p.headline,p.biography,p.specialties,p.years_experience,p.portfolio_url
 FROM contractors c LEFT JOIN contractor_profiles p ON p.tenant_id=c.tenant_id AND p.contractor_id=c.id
 WHERE ".implode(' AND ',$conditions)." ORDER BY COALESCE(NULLIF(c.business_name,''),c.last_name),c.id LIMIT 100";
$q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
$servicesQ=$pdo->prepare("SELECT s.id,s.name,o.pricing_model,o.rate FROM contractor_service_offerings o JOIN services s ON s.tenant_id=o.tenant_id AND s.id=o.service_id WHERE o.tenant_id=? AND o.contractor_id=? AND o.offered=1 AND s.active=1 ORDER BY s.name LIMIT 100");
$qualQ=$pdo->prepare("SELECT s.id,s.name,q.verification_status FROM contractor_service_qualifications q JOIN services s ON s.tenant_id=q.tenant_id AND s.id=q.service_id WHERE q.tenant_id=? AND q.contractor_id=? AND q.verification_status IN ('verified','unverified') AND s.active=1 ORDER BY s.name LIMIT 100");
$areasQ=$pdo->prepare("SELECT area_type,label,state_code,postal_code,radius_miles FROM contractor_service_areas WHERE tenant_id=? AND contractor_id=? AND active=1 ORDER BY id LIMIT 50");
$photosQ=$pdo->prepare("SELECT id FROM contractor_portfolio_photos WHERE tenant_id=? AND contractor_id=? ORDER BY sort_order,id LIMIT 20");
foreach($rows as &$r) {
 $cid=(int)$r['id'];
 $servicesQ->execute([$tid,$cid]);$services=$servicesQ->fetchAll(PDO::FETCH_ASSOC);
 $qualQ->execute([$tid,$cid]);
 $known=array_map('intval',array_column($services,'id'));
 foreach($qualQ->fetchAll(PDO::FETCH_ASSOC) as $s) {
  if (!in_array((int)$s['id'],$known,true)) {$services[]=['id'=>$s['id'],'name'=>$s['name'],'pricing_model'=>null,'rate'=>null,'verification_status'=>$s['verification_status']];$known[]=(int)$s['id'];}
 }
 $areasQ->execute([$tid,$cid]);
 $photosQ->execute([$tid,$cid]);
 $r['contractor_id']=$cid;unset($r['id']);
 $r['services']=$services;
 $r['service_areas']=$areasQ->fetchAll(PDO::FETCH_ASSOC);
 $r['photos']=array_map(static fn($p)=>['id'=>(int)$p['id'],'url'=>app_url('/api/contractor_portfolio_image.php?id='.(int)$p['id'])],$photosQ->fetchAll(PDO::FETCH_ASSOC));
 // Never expose contact information or internal user IDs.
}
unset($r);
$serviceOptions=$pdo->prepare("SELECT id,name FROM services WHERE tenant_id=? AND active=1 ORDER BY name");
$serviceOptions->execute([$tid]);
json_response(['ok'=>true,'contractors'=>$rows,'services'=>$serviceOptions->fetchAll(PDO::FETCH_ASSOC),'limit'=>100,'zip_filter'=>'exact_declared_zip_only']);
