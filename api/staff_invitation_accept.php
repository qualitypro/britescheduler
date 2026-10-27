<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_method('POST');
verify_csrf();
$d=request_data();
$token=(string)($d['token']??'');
if(!preg_match('/^[a-f0-9]{64}$/D',$token))json_response(['error'=>'Invalid invitation'],422);
$pdo=Database::connection();
$pdo->beginTransaction();
try{
 $q=$pdo->prepare("SELECT i.* FROM staff_invitations i JOIN tenants t ON t.id=i.tenant_id AND t.status='active' WHERE i.token_hash=? AND i.status='pending' AND i.expires_at>UTC_TIMESTAMP() FOR UPDATE");
 $q->execute([hash('sha256',$token)]);
 $invite=$q->fetch(PDO::FETCH_ASSOC);
 if(!$invite){$pdo->rollBack();json_response(['error'=>'Invitation expired, revoked or already used'],410);}
 $q=$pdo->prepare("SELECT id,status FROM users WHERE email=? LIMIT 1 FOR UPDATE");
 $q->execute([$invite['email']]);$existing=$q->fetch(PDO::FETCH_ASSOC);
 if($existing){
  $signedIn=Auth::user();
  if(!$signedIn || (int)$signedIn['id']!==(int)$existing['id']){
   $pdo->rollBack();
   json_response(['error'=>'This email already has an account. Sign in to that account before accepting the invitation.'],403);
  }
  if($existing['status']!=='active'){$pdo->rollBack();json_response(['error'=>'Account is not active'],403);}
  $uid=(int)$existing['id'];
 }else{
  // Do not create a second identity while another account is signed in.
  if(Auth::user()){
   $pdo->rollBack();
   json_response(['error'=>'Sign out of your current account before creating a new staff account'],403);
  }
  $first=(string)$invite['first_name'];$last=(string)$invite['last_name'];
  $password=(string)($d['password']??'');
  $confirm=(string)($d['password_confirm']??'');
  if(!hash_equals($password,$confirm)){$pdo->rollBack();json_response(['error'=>'Passwords do not match'],422);}
  if(strlen($password)<12||strlen($password)>1024){$pdo->rollBack();json_response(['error'=>'Password must contain at least 12 characters'],422);}
  $q=$pdo->prepare("INSERT INTO users (email,password_hash,first_name,last_name,status) VALUES (?,?,?,?,'active')");
  $q->execute([$invite['email'],password_hash($password,PASSWORD_DEFAULT),$first,$last]);
  $uid=(int)$pdo->lastInsertId();
 }
 $q=$pdo->prepare('SELECT user_id FROM tenant_memberships WHERE tenant_id=? AND user_id=?');
 $q->execute([(int)$invite['tenant_id'],$uid]);
 if($q->fetchColumn()){$pdo->rollBack();json_response(['error'=>'Already a member of this tenant'],409);}
 $q=$pdo->prepare("INSERT INTO tenant_memberships (tenant_id,user_id,role,status) VALUES (?,?,?,'active')");
 $q->execute([(int)$invite['tenant_id'],$uid,$invite['role']]);
 $q=$pdo->prepare("UPDATE staff_invitations SET status='accepted',accepted_at=UTC_TIMESTAMP() WHERE id=? AND status='pending'");
 $q->execute([(int)$invite['id']]);
 $pdo->commit();
 json_response(['ok'=>true,'message'=>'Invitation accepted. Sign in to access your staff dashboard.']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
