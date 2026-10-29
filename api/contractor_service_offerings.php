<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
Auth::requireUser();
Auth::requireRole('owner','admin','scheduler');
$tid=(int)Auth::tenantId();
$pdo=Database::connection();
$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method==='GET') {
 $id=filter_var($_GET['contractor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
} elseif ($method==='PUT') {
 verify_csrf();
 Auth::requireRole('owner','admin');
 $d=request_data();
 $id=filter_var($d['contractor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
} else {
 header('Allow: GET, PUT');
 json_response(['error'=>'Method not allowed'],405);
}
if (!$id) json_response(['error'=>'Invalid contractor ID'],422);
$q=$pdo->prepare('SELECT id FROM contractors WHERE tenant_id=? AND id=?');
$q->execute([$tid,$id]);
if (!$q->fetchColumn()) json_response(['error'=>'Contractor not found'],404);
if ($method==='GET') {
 $q=$pdo->prepare("SELECT s.id AS service_id,s.name AS service_name,s.active AS service_active,
 COALESCE(o.offered,0) AS offered,COALESCE(o.pricing_model,'flat') AS pricing_model,
 o.rate,o.notes
 FROM services s LEFT JOIN contractor_service_offerings o
 ON o.tenant_id=s.tenant_id AND o.service_id=s.id AND o.contractor_id=?
 WHERE s.tenant_id=? ORDER BY s.name,s.id");
 $q->execute([$id,$tid]);
 json_response(['contractor_id'=>(int)$id,'offerings'=>$q->fetchAll()]);
}
$serviceId=filter_var($d['service_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$model=$d['pricing_model']??null;
$offered=$d['offered']??null;
$rate=$d['rate']??null;
$notes=$d['notes']??null;
if (!$serviceId || !in_array($model,['hourly','flat'],true) ||
 !in_array((string)$offered,['0','1'],true) ||
 ($rate!==null && $rate!=='' && (!is_scalar($rate) || !preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/',(string)$rate))) ||
 ($notes!==null && (!is_string($notes) || strlen($notes)>500))) {
 json_response(['error'=>'Invalid offering or pricing data'],422);
}
$q=$pdo->prepare('SELECT id FROM services WHERE tenant_id=? AND id=?');
$q->execute([$tid,$serviceId]);
if (!$q->fetchColumn()) json_response(['error'=>'Service not found'],404);
try {
 $pdo->beginTransaction();
 $q=$pdo->prepare('SELECT id FROM contractors WHERE tenant_id=? AND id=? FOR UPDATE');
 $q->execute([$tid,$id]);
 if (!$q->fetchColumn()) { $pdo->rollBack(); json_response(['error'=>'Contractor not found'],404); }
 $q=$pdo->prepare("INSERT INTO contractor_service_offerings
 (tenant_id,contractor_id,service_id,offered,pricing_model,rate,notes)
 VALUES (?,?,?,?,?,?,?)
 ON DUPLICATE KEY UPDATE offered=VALUES(offered),pricing_model=VALUES(pricing_model),
 rate=VALUES(rate),notes=VALUES(notes)");
 $q->execute([$tid,$id,$serviceId,(int)$offered,$model,
 ($rate===''||$rate===null)?null:$rate,$notes===''?null:$notes]);
 $pdo->commit();
} catch (Throwable $e) {
 if ($pdo->inTransaction()) $pdo->rollBack();
 error_log('Contractor offering save failed: '.$e->getMessage());
 json_response(['error'=>'Unable to save service offering'],500);
}
json_response(['ok'=>true,'contractor_id'=>(int)$id,'service_id'=>(int)$serviceId]);
