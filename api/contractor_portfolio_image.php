<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('contractor','owner','admin','client');
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') {header('Allow: GET');http_response_code(405);exit;}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) {http_response_code(404);exit;}
$pdo=Database::connection();$tenantId=(int)$tenant['tenant_id'];$role=(string)$tenant['role'];
$q=$pdo->prepare("SELECT p.storage_key,p.mime_type,p.contractor_id,c.user_id FROM contractor_portfolio_photos p JOIN contractors c ON c.tenant_id=p.tenant_id AND c.id=p.contractor_id AND c.status='active' WHERE p.id=? AND p.tenant_id=?");
$q->execute([$id,$tenantId]);$p=$q->fetch(PDO::FETCH_ASSOC);
if (!$p) {http_response_code(404);exit;}
if ($role==='contractor' && (int)$p['user_id']!==(int)$user['id']) {http_response_code(404);exit;}
if ($role==='client') {
 $q=$pdo->prepare("SELECT id FROM clients WHERE tenant_id=? AND user_id=? AND status='active' LIMIT 1");
 $q->execute([$tenantId,(int)$user['id']]);
 if (!$q->fetchColumn()) {http_response_code(404);exit;}
}
$dir=getenv('BRITE_PRIVATE_UPLOAD_DIR');
$root=$dir && str_starts_with($dir,'/') ? realpath($dir) : false;
$webroot=realpath(dirname(__DIR__));
if (!$root || ($webroot && ($root===$webroot || str_starts_with($root,$webroot.'/')))) {http_response_code(503);exit;}
$file=$root.'/portfolio/'.$p['storage_key'];
if (!is_file($file)) {http_response_code(404);exit;}
header('Content-Type: '.$p['mime_type']);
header('Content-Disposition: inline; filename="portfolio-image"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file);
