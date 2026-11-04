<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('client','contractor');
$role=(string)$tenant['role'];
$tid=(int)$tenant['tenant_id'];$uid=(int)$user['id'];
$id=filter_var($_GET['request_id']??$_POST['request_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id)json_response(['error'=>'Valid request ID required'],422);
$pdo=Database::connection();
$actorTable=$role==='client'?'clients':'contractors';
$actorField=$role==='client'?'client_id':'contractor_id';
$check=$pdo->prepare("SELECT r.id,r.status FROM contractor_quote_requests r JOIN {$actorTable} actor ON actor.id=r.{$actorField} AND actor.tenant_id=r.tenant_id WHERE r.id=? AND r.tenant_id=? AND actor.user_id=? AND actor.status='active' LIMIT 1");
$check->execute([$id,$tid,$uid]);$request=$check->fetch(PDO::FETCH_ASSOC);
if(!$request)json_response(['error'=>'Quote request not found'],404);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
 $q=$pdo->prepare('SELECT id,revision,amount,pricing_type,scope_of_work,terms,estimated_hours,valid_until,created_at FROM contractor_quote_offers WHERE tenant_id=? AND request_id=? ORDER BY revision DESC LIMIT 20');
 $q->execute([$tid,$id]);json_response(['ok'=>true,'request_status'=>$request['status'],'offers'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
}
if($method!=='POST'){header('Allow: GET, POST');json_response(['error'=>'Method not allowed'],405);}
verify_csrf();
$action=(string)($_POST['action']??'');
if(!in_array($action,['submit','accept','decline'],true))json_response(['error'=>'Invalid action'],422);
if(($action==='submit'&&$role!=='contractor')||($action!=='submit'&&$role!=='client'))json_response(['error'=>'Action not permitted'],403);
$amount=null;$pricing=null;$scope=null;$terms=null;$hours=null;$valid=null;
if($action==='submit'){
 $raw=trim((string)($_POST['amount']??''));
 if(!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',$raw)|| (float)$raw<=0 || (float)$raw>9999999999.99)json_response(['error'=>'Enter a valid positive amount'],422);
 $amount=number_format((float)$raw,2,'.','');
 $pricing=(string)($_POST['pricing_type']??'');
 if(!in_array($pricing,['flat','hourly'],true))json_response(['error'=>'Choose flat rate or hourly'],422);
 $scope=trim((string)($_POST['scope_of_work']??''));
 $terms=trim((string)($_POST['terms']??''));
 if(mb_strlen($scope)<20||mb_strlen($scope)>5000||mb_strlen($terms)>3000)json_response(['error'=>'Scope must be 20–5000 characters; terms at most 3000'],422);
 $rawHours=trim((string)($_POST['estimated_hours']??''));
 if($rawHours!==''){
  if($pricing!=='hourly'||!preg_match('/^[0-9]{1,5}(?:\.[0-9]{1,2})?$/D',$rawHours)||(float)$rawHours<=0)json_response(['error'=>'Invalid estimated hours'],422);
  $hours=$rawHours;
 }
 $valid=trim((string)($_POST['valid_until']??''));
 if($valid!==''){
  $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$valid);
  if(!$dt||$dt->format('Y-m-d')!==$valid||$valid<(new DateTimeImmutable('today'))->format('Y-m-d'))json_response(['error'=>'Choose a future validity date'],422);
 }else $valid=null;
}
try{
 $pdo->beginTransaction();
 $lock=$pdo->prepare('SELECT status FROM contractor_quote_requests WHERE tenant_id=? AND id=? FOR UPDATE');
 $lock->execute([$tid,$id]);$current=$lock->fetchColumn();
 if($current===false)throw new DomainException('Quote request not found');
 if($action==='submit'){
  if(!in_array($current,['submitted','more_info_requested','quoted'],true))throw new DomainException('This request cannot receive another quote');
  $q=$pdo->prepare('SELECT COALESCE(MAX(revision),0)+1 FROM contractor_quote_offers WHERE tenant_id=? AND request_id=?');
  $q->execute([$tid,$id]);$revision=(int)$q->fetchColumn();
  $q=$pdo->prepare('INSERT INTO contractor_quote_offers(tenant_id,request_id,revision,amount,pricing_type,scope_of_work,terms,estimated_hours,valid_until) VALUES(?,?,?,?,?,?,?,?,?)');
  $q->execute([$tid,$id,$revision,$amount,$pricing,$scope,$terms?:null,$hours,$valid]);
  $q=$pdo->prepare("UPDATE contractor_quote_requests SET status='quoted' WHERE tenant_id=? AND id=?");
  $q->execute([$tid,$id]);
 }else{
  if($current!=='quoted')throw new DomainException('Only an outstanding quote can be accepted or declined');
  $q=$pdo->prepare('SELECT valid_until FROM contractor_quote_offers WHERE tenant_id=? AND request_id=? ORDER BY revision DESC LIMIT 1');
  $q->execute([$tid,$id]);$latest=$q->fetch(PDO::FETCH_ASSOC);
  if(!$latest)throw new DomainException('No formal quote is available');
  if($action==='accept'&&$latest['valid_until']!==null&&$latest['valid_until']<(new DateTimeImmutable('today'))->format('Y-m-d'))throw new DomainException('Quote expired; request a revised quote');
  $next=$action==='accept'?'accepted':'declined';
  $q=$pdo->prepare('UPDATE contractor_quote_requests SET status=? WHERE tenant_id=? AND id=?');
  $q->execute([$next,$tid,$id]);
 }
 $pdo->commit();
 json_response(['ok'=>true,'request_status'=>$action==='submit'?'quoted':($action==='accept'?'accepted':'declined')]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 if($e instanceof DomainException)json_response(['error'=>$e->getMessage()],409);
 error_log('Formal quote action failed: '.get_class($e));
 json_response(['error'=>'Unable to update formal quote'],500);
}
