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
