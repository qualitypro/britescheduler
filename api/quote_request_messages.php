<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('client','contractor');
$role=(string)$tenant['role'];$tid=(int)$tenant['tenant_id'];$uid=(int)$user['id'];
$pdo=Database::connection();
$id=filter_var($_GET['request_id']??$_POST['request_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id)json_response(['error'=>'Valid request ID required'],422);
$q=$pdo->prepare("SELECT r.id,r.status FROM contractor_quote_requests r JOIN ".($role==='client'?'clients':'contractors')." actor ON actor.id=r.".($role==='client'?'client_id':'contractor_id')." AND actor.tenant_id=r.tenant_id WHERE r.id=? AND r.tenant_id=? AND actor.user_id=? AND actor.status='active' LIMIT 1");
$q->execute([$id,$tid,$uid]);$request=$q->fetch(PDO::FETCH_ASSOC);
if(!$request)json_response(['error'=>'Request not found'],404);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
 $q=$pdo->prepare("SELECT id,sender_role,message_type,body,created_at FROM contractor_quote_messages WHERE tenant_id=? AND request_id=? ORDER BY id ASC LIMIT 500");
 $q->execute([$tid,$id]);$messages=$q->fetchAll(PDO::FETCH_ASSOC);
 $photos=$pdo->prepare("SELECT id FROM contractor_quote_message_photos WHERE tenant_id=? AND message_id=? ORDER BY id");
 foreach($messages as &$message){
  $photos->execute([$tid,(int)$message['id']]);
  $message['photos']=array_map(static fn($p)=>['id'=>(int)$p['id'],'url'=>app_url('/api/quote_message_photo.php?id='.(int)$p['id'])],$photos->fetchAll(PDO::FETCH_ASSOC));
 }unset($message);
 json_response(['ok'=>true,'request_status'=>$request['status'],'messages'=>$messages]);
}
if($method!=='POST'){header('Allow: GET, POST');json_response(['error'=>'Method not allowed'],405);}
verify_csrf();
if(in_array($request['status'],['accepted','declined','cancelled'],true))json_response(['error'=>'Conversation is closed'],409);
$messageType=(string)($_POST['message_type']??'reply');
if(!in_array($messageType,['reply','request_photos','request_data'],true))json_response(['error'=>'Invalid message type'],422);
if($role!=='contractor'&&$messageType!=='reply')json_response(['error'=>'Only contractors may request additional information'],403);
$body=trim((string)($_POST['body']??''));
if(mb_strlen($body,'UTF-8')<1||mb_strlen($body,'UTF-8')>3000)json_response(['error'=>'Message must contain 1–3000 characters'],422);
$files=$_FILES['photos']??null;$count=0;
if($files!==null){
 if(!is_array($files)||!isset($files['name'])||!is_array($files['name']))json_response(['error'=>'Invalid photo upload'],422);
 $count=count($files['name']);
 if($count>5)json_response(['error'=>'Maximum 5 additional photographs per message'],422);
 foreach(['tmp_name','error','size'] as $field)if(!isset($files[$field])||!is_array($files[$field])||count($files[$field])!==$count)json_response(['error'=>'Invalid photo upload'],422);
}
$folder=null;
if($count){
 if(!extension_loaded('gd'))json_response(['error'=>'Image processing unavailable'],503);
 $dir=getenv('BRITE_PRIVATE_UPLOAD_DIR');$root=$dir&&str_starts_with($dir,'/')?realpath($dir):false;
 $webroot=realpath(dirname(__DIR__));
 if(!$root||!is_writable($root)||($webroot&&($root===$webroot||str_starts_with($root,$webroot.'/'))))json_response(['error'=>'Private storage unavailable'],503);
 $folder=$root.'/quote-messages';
 if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder))json_response(['error'=>'Private storage unavailable'],503);
 if(!is_writable($folder))json_response(['error'=>'Private storage unavailable'],503);
}
$stored=[];
try{
 for($i=0;$i<$count;$i++){
  if($files['error'][$i]!==UPLOAD_ERR_OK||!is_uploaded_file($files['tmp_name'][$i])||$files['size'][$i]<1||$files['size'][$i]>5*1024*1024)throw new InvalidArgumentException('Each photograph must be 5 MB or smaller');
  $ext=strtolower(pathinfo((string)$files['name'][$i],PATHINFO_EXTENSION));
  if(!in_array($ext,['jpg','jpeg','png'],true))throw new InvalidArgumentException('Only JPG and PNG photographs are accepted');
  $info=@getimagesize($files['tmp_name'][$i]);
  if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true)||$info[0]>6000||$info[1]>6000||$info[0]*$info[1]>24000000)throw new InvalidArgumentException('Invalid photograph dimensions');
  if(($info[2]===IMAGETYPE_PNG&&$ext!=='png')||($info[2]===IMAGETYPE_JPEG&&$ext==='png'))throw new InvalidArgumentException('Image extension mismatch');
  $source=$info[2]===IMAGETYPE_JPEG?@imagecreatefromjpeg($files['tmp_name'][$i]):@imagecreatefrompng($files['tmp_name'][$i]);
  if(!$source)throw new InvalidArgumentException('Unable to decode photograph');
  $key=bin2hex(random_bytes(32));$path=$folder.'/'.$key;
  $ok=$info[2]===IMAGETYPE_JPEG?imagejpeg($source,$path,85):imagepng($source,$path,6);
  imagedestroy($source);
  if(!$ok)throw new RuntimeException('Unable to store photograph');
  chmod($path,0600);$stored[]=['path'=>$path,'key'=>$key,'mime'=>$info[2]===IMAGETYPE_JPEG?'image/jpeg':'image/png'];
 }
 $pdo->beginTransaction();
 $q=$pdo->prepare("SELECT status FROM contractor_quote_requests WHERE tenant_id=? AND id=? FOR UPDATE");
 $q->execute([$tid,$id]);$current=$q->fetchColumn();
 if($current===false||in_array($current,['accepted','declined','cancelled'],true))throw new DomainException('Conversation is closed');
 $q=$pdo->prepare("INSERT INTO contractor_quote_messages(tenant_id,request_id,sender_role,sender_user_id,message_type,body) VALUES(?,?,?,?,?,?)");
 $q->execute([$tid,$id,$role,$uid,$messageType,$body]);$messageId=(int)$pdo->lastInsertId();
 $q=$pdo->prepare("INSERT INTO contractor_quote_message_photos(tenant_id,message_id,storage_key,mime_type) VALUES(?,?,?,?)");
 foreach($stored as $photo)$q->execute([$tid,$messageId,$photo['key'],$photo['mime']]);
 if($role==='contractor'&&$messageType!=='reply'&&$current==='submitted'){
  $q=$pdo->prepare("UPDATE contractor_quote_requests SET status='more_info_requested' WHERE id=? AND tenant_id=?");$q->execute([$id,$tid]);
 }
 if($role==='client'&&$current==='more_info_requested'){
  $q=$pdo->prepare("UPDATE contractor_quote_requests SET status='submitted' WHERE id=? AND tenant_id=?");$q->execute([$id,$tid]);
 }
 $pdo->commit();
 json_response(['ok'=>true,'message_id'=>$messageId],201);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 foreach($stored as $photo)if(is_file($photo['path']))@unlink($photo['path']);
 if($e instanceof InvalidArgumentException)json_response(['error'=>$e->getMessage()],422);
 if($e instanceof DomainException)json_response(['error'=>$e->getMessage()],409);
 error_log('Quote message create failed: '.get_class($e));
 json_response(['error'=>'Unable to save message'],500);
}
