<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_method('POST'); verify_csrf();
$d=request_data(); $email=strtolower(trim($d['email']??'')); $password=$d['password']??'';
$q=Database::connection()->prepare("SELECT * FROM users WHERE email=? AND status='active'");
$q->execute([$email]); $u=$q->fetch();
if(!$u || !password_verify($password,$u['password_hash'])) json_response(['error'=>'Invalid email or password'],422);
session_regenerate_id(true); $_SESSION['user_id']=(int)$u['id'];
Database::connection()->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$u['id']]);
$m=Auth::memberships((int)$u['id']); if($m) $_SESSION['tenant_id']=(int)$m[0]['tenant_id'];
json_response(['ok'=>true,'redirect'=>app_url('/dashboard.v2.php')]);
