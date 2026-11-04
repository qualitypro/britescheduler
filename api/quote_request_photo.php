<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();$tenant=Auth::requireRole('client','contractor','owner','admin');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');http_response_code(405);exit;}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){http_response_code(404);exit;}
$pdo=Database::connection();$tid=(int)$tenant['tenant_id'];$role=(string)$tenant['role'];
$q=$pdo->prepare("SELECT p.storage_key,p.mime_type,r.client_id,r.contractor_id FROM contractor_quote_request_photos p JOIN contractor_quote_requests r ON r.id=p.request_id AND r.tenant_id=p.tenant_id WHERE p.id=? AND p.tenant_id=? LIMIT 1");
$q->execute([$id,$tid]);$photo=$q->fetch(PDO::FETCH_ASSOC);
if(!$photo){http_response_code(404);exit;}
if($role==='client'){
 $q=$pdo->prepare("SELECT id FROM clients WHERE tenant_id=? AND id=? AND user_id=? AND status='active'");
 $q->execute([$tid,$photo['client_id'],(int)$user['id']]);
 if(!$q->fetchColumn()){http_response_code(404);exit;}
}elseif($role==='contractor'){
 $q=$pdo->prepare("SELECT id FROM contractors WHERE tenant_id=? AND id=? AND user_id=? AND status='active'");
 $q->execute([$tid,$photo['contractor_id'],(int)$user['id']]);
 if(!$q->fetchColumn()){http_response_code(404);exit;}
}
$dir=getenv('BRITE_PRIVATE_UPLOAD_DIR');$root=$dir&&str_starts_with($dir,'/')?realpath($dir):false;
$webroot=realpath(dirname(__DIR__));
if(!$root||($webroot&&($root===$webroot||str_starts_with($root,$webroot.'/')))){http_response_code(503);exit;}
$key=(string)$photo['storage_key'];
if(!preg_match('/^[a-f0-9]{64}$/D',$key)){http_response_code(404);exit;}
$file=$root.'/quote-requests/'.$key;
if(!is_file($file)){http_response_code(404);exit;}
header('Content-Type: '.($photo['mime_type']==='image/png'?'image/png':'image/jpeg'));
header('Content-Disposition: inline; filename="job-photo"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file);
