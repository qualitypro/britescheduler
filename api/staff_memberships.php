<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('owner');
$tid=(int)$tenant['tenant_id'];
$pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){
    $q=$pdo->prepare("SELECT u.id,u.first_name,u.last_name,u.email,m.role,m.status
        FROM tenant_memberships m JOIN users u ON u.id=m.user_id
        WHERE m.tenant_id=? AND m.role IN ('owner','admin','scheduler','accounting')
        ORDER BY FIELD(m.role,'owner','admin','scheduler','accounting'),u.last_name,u.first_name");
    $q->execute([$tid]);
    json_response(['members'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $d=request_data();
    $email=strtolower(trim((string)($d['email']??'')));
    $role=(string)($d['role']??'');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||
       !in_array($role,['admin','scheduler','accounting'],true))
        json_response(['error'=>'Valid existing account email and staff role required'],422);
    $pdo->beginTransaction();
    try{
        $q=$pdo->prepare('SELECT id FROM users WHERE email=? AND status=? LIMIT 1 FOR UPDATE');
        $q->execute([$email,'active']);
        $target=$q->fetchColumn();
        if(!$target){
            $pdo->rollBack();
            json_response(['error'=>'No active account with that email. Ask the user to register first.'],404);
        }
        $q=$pdo->prepare('SELECT role,status FROM tenant_memberships WHERE tenant_id=? AND user_id=? FOR UPDATE');
        $q->execute([$tid,(int)$target]);
        if($q->fetch()){
            $pdo->rollBack();
            json_response(['error'=>'This account already belongs to the tenant'],409);
        }
        $q=$pdo->prepare("INSERT INTO tenant_memberships (tenant_id,user_id,role,status) VALUES (?,?,?,'active')");
        $q->execute([$tid,(int)$target,$role]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    json_response(['ok'=>true],201);
}
if($_SERVER['REQUEST_METHOD']!=='PUT')json_response(['error'=>'Method not allowed'],405);
verify_csrf();
$d=request_data();
$id=filter_var($d['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$role=(string)($d['role']??'');
$status=(string)($d['status']??'');
if(!$id||!in_array($role,['admin','scheduler','accounting'],true)||
    !in_array($status,['active','disabled'],true))
    json_response(['error'=>'Invalid staff role or status'],422);
if((int)$id===(int)$user['id'])json_response(['error'=>'Use My Profile to edit your own account'],403);
$pdo->beginTransaction();
try{
    $q=$pdo->prepare('SELECT role,status FROM tenant_memberships WHERE tenant_id=? AND user_id=? FOR UPDATE');
    $q->execute([$tid,$id]);
    $current=$q->fetch(PDO::FETCH_ASSOC);
    if(!$current||!in_array($current['role'],['admin','scheduler','accounting'],true)){
        $pdo->rollBack();
        json_response(['error'=>'Only existing non-owner staff memberships may be edited'],403);
    }
    $q=$pdo->prepare('UPDATE tenant_memberships SET role=?,status=? WHERE tenant_id=? AND user_id=?');
    $q->execute([$role,$status,$tid,$id]);
    $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
json_response(['ok'=>true]);
