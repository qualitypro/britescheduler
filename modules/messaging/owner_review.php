<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/ModuleRegistry.php';
$user=Auth::requireUser();
$tenant=Auth::tenant();
if($tenant['role']!=='owner')json_response(['error'=>'Forbidden'],403);
if(!isset(ModuleRegistry::activeFor($tenant)['messaging']))json_response(['error'=>'Module unavailable'],403);
$tid=(int)$tenant['tenant_id'];$uid=(int)$user['id'];$db=Database::connection();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');json_response(['error'=>'Method not allowed'],405);}
$action=(string)($_GET['action']??'list');
if($action==='list'){
 $q=$db->prepare("SELECT t.id,t.subject,t.updated_at,
 (SELECT GROUP_CONCAT(CONCAT(u.first_name,' ',u.last_name,' (',m.role,')') ORDER BY u.first_name SEPARATOR ', ')
 FROM module_message_participants p JOIN users u ON u.id=p.user_id
 LEFT JOIN tenant_memberships m ON m.user_id=p.user_id AND m.tenant_id=p.tenant_id
 WHERE p.thread_id=t.id AND p.tenant_id=t.tenant_id) participants,
 (SELECT COUNT(*) FROM module_messages msg WHERE msg.tenant_id=t.tenant_id AND msg.thread_id=t.id) message_count
 FROM module_message_threads t WHERE t.tenant_id=? ORDER BY t.updated_at DESC,t.id DESC LIMIT 200");
 $q->execute([$tid]);json_response(['ok'=>true,'threads'=>$q->fetchAll()]);
}
if($action==='thread'){
 $thread=filter_var($_GET['thread_id']??null,FILTER_VALIDATE_INT);
 if(!$thread||$thread<1)json_response(['error'=>'Invalid conversation'],422);
 $q=$db->prepare('SELECT subject FROM module_message_threads WHERE tenant_id=? AND id=?');
 $q->execute([$tid,$thread]);$subject=$q->fetchColumn();
 if($subject===false)json_response(['error'=>'Conversation unavailable'],404);
 $q=$db->prepare('SELECT msg.id,msg.sender_id,msg.body,msg.created_at,u.first_name,u.last_name FROM module_messages msg JOIN users u ON u.id=msg.sender_id WHERE msg.tenant_id=? AND msg.thread_id=? ORDER BY msg.id DESC LIMIT 200');
 $q->execute([$tid,$thread]);$messages=array_reverse($q->fetchAll());
 try{
  $q=$db->prepare('INSERT INTO module_message_owner_audit(tenant_id,owner_user_id,thread_id) VALUES(?,?,?)');
  $q->execute([$tid,$uid,$thread]);
 }catch(Throwable $e){
  error_log('Owner messaging audit failed: '.$e->getMessage());
  json_response(['error'=>'Unable to record review'],500);
 }
 // Read-only owner review never changes participant read receipts.
 json_response(['ok'=>true,'subject'=>$subject,'messages'=>$messages]);
}
json_response(['error'=>'Unsupported action'],404);
