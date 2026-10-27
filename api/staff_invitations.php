<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('owner');
$tid=(int)$tenant['tenant_id'];
$pdo=Database::connection();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
 $q=$pdo->prepare("SELECT id,email,first_name,last_name,role,status,expires_at,created_at FROM staff_invitations WHERE tenant_id=? ORDER BY id DESC LIMIT 100");
 $q->execute([$tid]);
 json_response(['invitations'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
}
if(!in_array($method,['POST','DELETE'],true))json_response(['error'=>'Method not allowed'],405);
verify_csrf();
$d=request_data();
if($method==='DELETE'){
 $id=filter_var($d['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$id)json_response(['error'=>'Invalid invitation'],422);
 $q=$pdo->prepare("UPDATE staff_invitations SET status='revoked' WHERE id=? AND tenant_id=? AND status='pending'");
 $q->execute([$id,$tid]);
 json_response(['ok'=>true,'changed'=>$q->rowCount()>0]);
}
$email=strtolower(trim((string)($d['email']??'')));
$first=trim((string)($d['first_name']??''));
$last=trim((string)($d['last_name']??''));
$role=(string)($d['role']??'');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||
 $first===''||strlen($first)>100||$last===''||strlen($last)>100||
 !in_array($role,['admin','scheduler','accounting'],true))
 json_response(['error'=>'Valid name, email and staff role required'],422);
$pdo->beginTransaction();
try{
 $q=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
 $q->execute([$email]);$existing=$q->fetchColumn();
 if($existing){
  $q=$pdo->prepare('SELECT user_id FROM tenant_memberships WHERE tenant_id=? AND user_id=?');
  $q->execute([$tid,(int)$existing]);
  if($q->fetchColumn()){$pdo->rollBack();json_response(['error'=>'Account already has a membership in this tenant'],409);}
 }
 $q=$pdo->prepare("SELECT id FROM staff_invitations WHERE tenant_id=? AND email=? AND status='pending' AND expires_at>UTC_TIMESTAMP() LIMIT 1 FOR UPDATE");
 $q->execute([$tid,$email]);
 if($q->fetchColumn()){$pdo->rollBack();json_response(['error'=>'An active invitation already exists for this email'],409);}
 $token=bin2hex(random_bytes(32));
 $q=$pdo->prepare("INSERT INTO staff_invitations (tenant_id,email,first_name,last_name,role,token_hash,expires_at,created_by) VALUES (?,?,?,?,?, ?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY),?)");
 $q->execute([$tid,$email,$first,$last,$role,hash('sha256',$token),(int)$user['id']]);
 $id=(int)$pdo->lastInsertId();$pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
json_response(['ok'=>true,'id'=>$id,'invitation_url'=>app_url('/staff-accept.php?token='.$token)],201);
