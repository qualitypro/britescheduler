<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('contractor');
$tenantId=(int)$tenant['tenant_id'];
$pdo=Database::connection();
$q=$pdo->prepare("SELECT id FROM contractors WHERE tenant_id=? AND user_id=? AND status='active' LIMIT 1");
$q->execute([$tenantId,(int)$user['id']]);
$contractorId=(int)($q->fetchColumn() ?: 0);
if (!$contractorId) json_response(['error'=>'Active contractor account required'],403);
$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method,['GET','POST','DELETE'],true)) {header('Allow: GET, POST, DELETE');json_response(['error'=>'Method not allowed'],405);}
if ($method!=='GET') verify_csrf();
if ($method==='GET') {
 $q=$pdo->prepare('SELECT id,sort_order,created_at FROM contractor_portfolio_photos WHERE tenant_id=? AND contractor_id=? ORDER BY sort_order,id');
 $q->execute([$tenantId,$contractorId]);
 json_response(['ok'=>true,'photos'=>array_map(static function($p) {
  $p['url']=app_url('/api/contractor_portfolio_image.php?id='.(int)$p['id']);
  return $p;
 },$q->fetchAll(PDO::FETCH_ASSOC)),'limit'=>20]);
}
if ($method==='DELETE') {
 $d=request_data();$id=filter_var($d['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if (!$id) json_response(['error'=>'Valid photo ID required'],422);
 $q=$pdo->prepare('SELECT storage_key FROM contractor_portfolio_photos WHERE id=? AND tenant_id=? AND contractor_id=?');
 $q->execute([$id,$tenantId,$contractorId]);$key=$q->fetchColumn();
 if (!$key) json_response(['error'=>'Photo not found'],404);
 $dir=getenv('BRITE_PRIVATE_UPLOAD_DIR');
 if (!$dir || !str_starts_with($dir,'/') || realpath($dir)===false) json_response(['error'=>'Private upload storage not configured'],503);
 $q=$pdo->prepare('DELETE FROM contractor_portfolio_photos WHERE id=? AND tenant_id=? AND contractor_id=?');
 $q->execute([$id,$tenantId,$contractorId]);
 $file=rtrim($dir,'/').'/portfolio/'.$key;
 if (is_file($file) && !unlink($file)) error_log('Portfolio orphan file: '.$key);
 json_response(['ok'=>true]);
}
if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) json_response(['error'=>'Choose a JPEG or PNG image'],422);
$f=$_FILES['photo'];
if (($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']??'')) json_response(['error'=>'Upload failed'],422);
if (($f['size']??0)<1 || $f['size']>5*1024*1024) json_response(['error'=>'Image must be 5 MB or smaller'],422);
$ext=strtolower(pathinfo((string)($f['name']??''),PATHINFO_EXTENSION));
if (!in_array($ext,['jpg','jpeg','png'],true)) json_response(['error'=>'Only JPG, JPEG and PNG files are supported'],422);
$info=@getimagesize($f['tmp_name']);
if (!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true) || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>24000000) json_response(['error'=>'Invalid or oversized image dimensions'],422);
if (($info[2]===IMAGETYPE_PNG && $ext!=='png') || ($info[2]===IMAGETYPE_JPEG && $ext==='png')) json_response(['error'=>'Image format does not match extension'],422);
if (!extension_loaded('gd')) json_response(['error'=>'Server image processing unavailable'],503);
$source=$info[2]===IMAGETYPE_JPEG ? @imagecreatefromjpeg($f['tmp_name']) : @imagecreatefrompng($f['tmp_name']);
if (!$source) json_response(['error'=>'Cannot decode image'],422);
$dir=getenv('BRITE_PRIVATE_UPLOAD_DIR');
if (!$dir || !str_starts_with($dir,'/') || !is_dir($dir) || !is_writable($dir)) {imagedestroy($source);json_response(['error'=>'Configure BRITE_PRIVATE_UPLOAD_DIR outside the web root'],503);}
$root=realpath($dir);$webroot=realpath(dirname(__DIR__));
if (!$root || ($webroot && ($root===$webroot || str_starts_with($root,$webroot.'/')))) {imagedestroy($source);json_response(['error'=>'Private upload directory must be outside the web root'],503);}
$folder=$root.'/portfolio';
if (!is_dir($folder) && !mkdir($folder,0700,true) && !is_dir($folder)) {imagedestroy($source);json_response(['error'=>'Cannot create private storage'],503);}
$key=bin2hex(random_bytes(32));
$path=$folder.'/'.$key;
$ok=$info[2]===IMAGETYPE_JPEG ? imagejpeg($source,$path,85) : imagepng($source,$path,6);
imagedestroy($source);
if (!$ok) json_response(['error'=>'Unable to store image'],500);
chmod($path,0600);
try {
 $pdo->beginTransaction();
 $lock=$pdo->prepare('SELECT id FROM contractors WHERE tenant_id=? AND id=? FOR UPDATE');
 $lock->execute([$tenantId,$contractorId]);
 $count=$pdo->prepare('SELECT COUNT(*) FROM contractor_portfolio_photos WHERE tenant_id=? AND contractor_id=?');
 $count->execute([$tenantId,$contractorId]);
 $n=(int)$count->fetchColumn();
 if ($n>=20) {$pdo->rollBack();unlink($path);json_response(['error'=>'Portfolio limit is 20 photos'],422);}
 $q=$pdo->prepare('INSERT INTO contractor_portfolio_photos(tenant_id,contractor_id,storage_key,mime_type,sort_order) VALUES(?,?,?,?,?)');
 $q->execute([$tenantId,$contractorId,$key,$info[2]===IMAGETYPE_JPEG?'image/jpeg':'image/png',$n]);
 $id=(int)$pdo->lastInsertId();$pdo->commit();
 json_response(['ok'=>true,'photo'=>['id'=>$id,'url'=>app_url('/api/contractor_portfolio_image.php?id='.$id)]]);
} catch(Throwable $e) {
 if ($pdo->inTransaction()) $pdo->rollBack();
 if (is_file($path)) unlink($path);
 error_log('Portfolio upload failed: '.$e->getMessage());
 json_response(['error'=>'Unable to save photo; verify migration 021'],500);
}
