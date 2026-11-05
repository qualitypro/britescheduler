<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/CrmQuoteSync.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('client');
$tid=(int)$tenant['tenant_id'];$pdo=Database::connection();
$q=$pdo->prepare("SELECT id FROM clients WHERE tenant_id=? AND user_id=? AND status='active' LIMIT 1");
$q->execute([$tid,(int)$user['id']]);$clientId=(int)($q->fetchColumn()?:0);
if (!$clientId) json_response(['error'=>'Active client account required'],403);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if ($method==='GET') {
 $q=$pdo->prepare("SELECT r.id,r.contractor_id,r.client_location_id,r.service_id,r.description,r.preferred_date,r.status,r.created_at,COALESCE(NULLIF(c.business_name,''),CONCAT(c.first_name,' ',c.last_name)) AS contractor_name FROM contractor_quote_requests r JOIN contractors c ON c.id=r.contractor_id AND c.tenant_id=r.tenant_id WHERE r.tenant_id=? AND r.client_id=? ORDER BY r.id DESC LIMIT 100");
 $q->execute([$tid,$clientId]);$requests=$q->fetchAll(PDO::FETCH_ASSOC);
 $photos=$pdo->prepare('SELECT id FROM contractor_quote_request_photos WHERE tenant_id=? AND request_id=? ORDER BY id LIMIT 10');
 foreach($requests as &$request){$photos->execute([$tid,(int)$request['id']]);$request['photos']=array_map(static fn($p)=>['id'=>(int)$p['id'],'url'=>app_url('/api/quote_request_photo.php?id='.(int)$p['id'])],$photos->fetchAll(PDO::FETCH_ASSOC));}unset($request);
 json_response(['ok'=>true,'requests'=>$requests]);
}
if ($method!=='POST') {header('Allow: GET, POST');json_response(['error'=>'Method not allowed'],405);}
verify_csrf();
$contractorId=filter_var($_POST['contractor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$locationId=filter_var($_POST['client_location_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$serviceId=($_POST['service_id']??'')===''?null:filter_var($_POST['service_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$description=trim((string)($_POST['description']??''));
$date=trim((string)($_POST['preferred_date']??''));
if (!$contractorId||!$locationId||$serviceId===false||mb_strlen($description,'UTF-8')<20||mb_strlen($description,'UTF-8')>5000) json_response(['error'=>'Select a contractor and location and enter a description of 20–5000 characters'],422);
if ($date!=='') {
 $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
 if (!$dt||$dt->format('Y-m-d')!==$date||$date<(new DateTimeImmutable('today'))->format('Y-m-d')) json_response(['error'=>'Select a valid future date'],422);
}
$q=$pdo->prepare("SELECT id FROM contractors WHERE tenant_id=? AND id=? AND status='active'");
$q->execute([$tid,$contractorId]);if (!$q->fetchColumn()) json_response(['error'=>'Contractor unavailable'],404);
$q=$pdo->prepare('SELECT id FROM client_locations WHERE tenant_id=? AND client_id=? AND id=?');
$q->execute([$tid,$clientId,$locationId]);if (!$q->fetchColumn()) json_response(['error'=>'Select one of your service locations'],422);
if ($serviceId!==null) {
 $q=$pdo->prepare('SELECT id FROM services WHERE tenant_id=? AND id=? AND active=1');
 $q->execute([$tid,$serviceId]);if (!$q->fetchColumn()) json_response(['error'=>'Invalid service'],422);
 $q=$pdo->prepare("SELECT 1 FROM contractor_service_offerings WHERE tenant_id=? AND contractor_id=? AND service_id=? AND offered=1 UNION SELECT 1 FROM contractor_service_qualifications WHERE tenant_id=? AND contractor_id=? AND service_id=? AND verification_status IN ('verified','unverified') LIMIT 1");
 $q->execute([$tid,$contractorId,$serviceId,$tid,$contractorId,$serviceId]);
 if (!$q->fetchColumn()) json_response(['error'=>'This contractor does not list the selected service'],422);
}
$files=$_FILES['photos']??null;
if (!is_array($files)||!isset($files['name'])||!is_array($files['name'])) json_response(['error'=>'No photos reached the server. Check PHP post_max_size and upload_max_filesize limits.'],422);
$count=count($files['name']);
if ($count<2||$count>10) json_response(['error'=>'Attach 2–10 job photographs'],422);
foreach(['tmp_name','error','size'] as $field) if(!isset($files[$field])||!is_array($files[$field])||count($files[$field])!==$count) json_response(['error'=>'Invalid photo upload'],422);
if (!extension_loaded('gd')) json_response(['error'=>'Image processing unavailable'],503);
$dir=getenv('BRITE_PRIVATE_UPLOAD_DIR');$root=$dir&&str_starts_with($dir,'/')?realpath($dir):false;
$webroot=realpath(dirname(__DIR__));
if (!$root||!is_writable($root)||($webroot&&($root===$webroot||str_starts_with($root,$webroot.'/')))) json_response(['error'=>'Private storage unavailable'],503);
$folder=$root.'/quote-requests';
if (!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder)) json_response(['error'=>'Private storage unavailable'],503);
if (!is_writable($folder)) json_response(['error'=>'Private storage unavailable'],503);
$stored=[];
try {
 for($i=0;$i<$count;$i++) {
  if ($files['error'][$i]!==UPLOAD_ERR_OK||!is_uploaded_file($files['tmp_name'][$i])||$files['size'][$i]<1||$files['size'][$i]>5*1024*1024) throw new InvalidArgumentException('Each photo must be 5 MB or smaller');
  $ext=strtolower(pathinfo((string)$files['name'][$i],PATHINFO_EXTENSION));
  if (!in_array($ext,['jpg','jpeg','png'],true)) throw new InvalidArgumentException('Only JPG and PNG photos are accepted');
  $info=@getimagesize($files['tmp_name'][$i]);
  if (!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true)||$info[0]>6000||$info[1]>6000||$info[0]*$info[1]>24000000) throw new InvalidArgumentException('Invalid image dimensions');
  if (($info[2]===IMAGETYPE_PNG&&$ext!=='png')||($info[2]===IMAGETYPE_JPEG&&$ext==='png')) throw new InvalidArgumentException('Image extension mismatch');
  $source=$info[2]===IMAGETYPE_JPEG?@imagecreatefromjpeg($files['tmp_name'][$i]):@imagecreatefrompng($files['tmp_name'][$i]);
  if (!$source) throw new InvalidArgumentException('Unable to decode photo');
  $key=bin2hex(random_bytes(32));$path=$folder.'/'.$key;
  $ok=$info[2]===IMAGETYPE_JPEG?imagejpeg($source,$path,85):imagepng($source,$path,6);
  imagedestroy($source);
  if (!$ok) throw new RuntimeException('Unable to store photo');
  chmod($path,0600);
  $stored[]=['path'=>$path,'key'=>$key,'mime'=>$info[2]===IMAGETYPE_JPEG?'image/jpeg':'image/png'];
 }
 $pdo->beginTransaction();
 $q=$pdo->prepare("INSERT INTO contractor_quote_requests(tenant_id,client_id,contractor_id,client_location_id,service_id,description,preferred_date) VALUES(?,?,?,?,?,?,?)");
 $q->execute([$tid,$clientId,$contractorId,$locationId,$serviceId,$description,$date?:null]);
 $requestId=(int)$pdo->lastInsertId();
 $q=$pdo->prepare('INSERT INTO contractor_quote_request_photos(tenant_id,request_id,storage_key,mime_type) VALUES(?,?,?,?)');
 foreach($stored as $p)$q->execute([$tid,$requestId,$p['key'],$p['mime']]);
 CrmQuoteSync::sync($pdo,$tid,$requestId,(int)$user['id']);
 $pdo->commit();
 json_response(['ok'=>true,'request_id'=>$requestId,'status'=>'submitted'],201);
} catch(Throwable $e) {
 if($pdo->inTransaction())$pdo->rollBack();
 foreach($stored as $p)if(is_file($p['path']))@unlink($p['path']);
 if($e instanceof InvalidArgumentException)json_response(['error'=>$e->getMessage()],422);
 error_log('Quote request create failed: '.get_class($e));
 json_response(['error'=>'Unable to submit quote request'],500);
}
