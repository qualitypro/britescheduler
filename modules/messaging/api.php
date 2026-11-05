<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/ModuleRegistry.php';
$user=Auth::requireUser();
$tenant=Auth::tenant();
$tid=(int)$tenant['tenant_id'];$uid=(int)$user['id'];
if(!isset(ModuleRegistry::activeFor($tenant)['messaging']))json_response(['error'=>'Module unavailable'],403);
$db=Database::connection();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method!=='GET'&&$method!=='POST'){header('Allow: GET, POST');json_response(['error'=>'Method not allowed'],405);}
if($method==='POST')verify_csrf();
$action=(string)($_REQUEST['action']??'list');
function threadAccess(PDO $db,int $tid,int $uid,int $thread): void {
 $q=$db->prepare('SELECT 1 FROM module_message_threads t JOIN module_message_participants p ON p.thread_id=t.id AND p.tenant_id=t.tenant_id WHERE t.id=? AND t.tenant_id=? AND p.user_id=?');
 $q->execute([$thread,$tid,$uid]);
 if(!$q->fetchColumn())json_response(['error'=>'Conversation unavailable'],404);
}
function activeRecipient(PDO $db,int $tid,int $uid): bool {
 $q=$db->prepare("SELECT 1 FROM tenant_memberships m JOIN users u ON u.id=m.user_id WHERE m.tenant_id=? AND m.user_id=? AND m.status='active' AND u.status='active' LIMIT 1");
 $q->execute([$tid,$uid]);return (bool)$q->fetchColumn();
}

// Client/contractor messaging is permitted only with a tenant owner or a
// client/contractor connected by an actual quote request or assignment.
// Re-evaluate on POST: a forged recipient_id must never bypass the UI filter.
function messagingPairAllowed(PDO $db,int $tid,int $sender,int $recipient,string $senderRole): bool {
 if(!in_array($senderRole,['client','contractor'],true))return true;
 $q=$db->prepare("SELECT role FROM tenant_memberships WHERE tenant_id=? AND user_id=? AND status='active' LIMIT 1");
 $q->execute([$tid,$recipient]);$recipientRole=(string)($q->fetchColumn()?:'');
 if($recipientRole==='owner')return true;
 if(!in_array($recipientRole,['client','contractor'],true)||$recipientRole===$senderRole)return false;
 $clientUser=$senderRole==='client'?$sender:$recipient;
 $contractorUser=$senderRole==='contractor'?$sender:$recipient;
 $q=$db->prepare("SELECT 1 FROM contractor_quote_requests r
  JOIN clients c ON c.id=r.client_id AND c.tenant_id=r.tenant_id AND c.status='active'
  JOIN contractors k ON k.id=r.contractor_id AND k.tenant_id=r.tenant_id AND k.status='active'
  WHERE r.tenant_id=? AND c.user_id=? AND k.user_id=? LIMIT 1");
 $q->execute([$tid,$clientUser,$contractorUser]);if($q->fetchColumn())return true;
 $q=$db->prepare("SELECT 1 FROM appointments a
  JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id AND c.status='active'
  JOIN appointment_contractors ac ON ac.appointment_id=a.id
  JOIN contractors k ON k.id=ac.contractor_id AND k.tenant_id=a.tenant_id AND k.status='active'
  WHERE a.tenant_id=? AND c.user_id=? AND k.user_id=? AND a.status<>'cancelled' LIMIT 1");
 $q->execute([$tid,$clientUser,$contractorUser]);return (bool)$q->fetchColumn();
}
if($method==='GET'&&$action==='notifications'){
 $q=$db->prepare('SELECT COALESCE(SUM((SELECT COUNT(*) FROM module_messages m WHERE m.tenant_id=p.tenant_id AND m.thread_id=p.thread_id AND m.sender_id<>? AND m.id>COALESCE(p.last_read_message_id,0))),0) unread_count FROM module_message_participants p JOIN module_message_threads t ON t.id=p.thread_id AND t.tenant_id=p.tenant_id WHERE p.tenant_id=? AND p.user_id=?');
 $q->execute([$uid,$tid,$uid]);$count=(int)$q->fetchColumn();
 $q=$db->prepare("SELECT t.id,t.subject,(SELECT CONCAT(u.first_name,' ',u.last_name) FROM module_messages m JOIN users u ON u.id=m.sender_id WHERE m.tenant_id=t.tenant_id AND m.thread_id=t.id AND m.sender_id<>? AND m.id>COALESCE(p.last_read_message_id,0) ORDER BY m.id DESC LIMIT 1) sender, (SELECT m.created_at FROM module_messages m WHERE m.tenant_id=t.tenant_id AND m.thread_id=t.id AND m.sender_id<>? AND m.id>COALESCE(p.last_read_message_id,0) ORDER BY m.id DESC LIMIT 1) latest_at FROM module_message_participants p JOIN module_message_threads t ON t.id=p.thread_id AND t.tenant_id=p.tenant_id WHERE p.tenant_id=? AND p.user_id=? AND EXISTS(SELECT 1 FROM module_messages m WHERE m.tenant_id=p.tenant_id AND m.thread_id=p.thread_id AND m.sender_id<>? AND m.id>COALESCE(p.last_read_message_id,0)) ORDER BY t.updated_at DESC LIMIT 5");
 $q->execute([$uid,$uid,$tid,$uid,$uid]);
 json_response(['ok'=>true,'unread_count'=>$count,'threads'=>$q->fetchAll()]);
}
if($method==='GET'&&$action==='list'){
 $q=$db->prepare("SELECT t.id,t.subject,t.updated_at,(SELECT COUNT(*) FROM module_messages m WHERE m.thread_id=t.id AND m.tenant_id=t.tenant_id AND m.sender_id<>? AND m.id>COALESCE(p.last_read_message_id,0)) unread, (SELECT m.body FROM module_messages m WHERE m.tenant_id=t.tenant_id AND m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) last_body, (SELECT GROUP_CONCAT(CONCAT(u.first_name,' ',u.last_name) ORDER BY u.first_name SEPARATOR ', ') FROM module_message_participants otherp JOIN users u ON u.id=otherp.user_id WHERE otherp.thread_id=t.id AND otherp.tenant_id=t.tenant_id AND otherp.user_id<>?) other_names FROM module_message_threads t JOIN module_message_participants p ON p.thread_id=t.id AND p.tenant_id=t.tenant_id WHERE t.tenant_id=? AND p.user_id=? ORDER BY t.updated_at DESC,t.id DESC LIMIT 100");
 $q->execute([$uid,$uid,$tid,$uid]);
 json_response(['ok'=>true,'threads'=>$q->fetchAll()]);
}
if($method==='GET'&&$action==='recipients'){
 $q=$db->prepare("SELECT DISTINCT u.id,u.first_name,u.last_name,m.role FROM tenant_memberships m JOIN users u ON u.id=m.user_id JOIN tenant_module_roles r ON r.tenant_id=m.tenant_id AND r.module_key='messaging' AND r.role=m.role AND r.allowed=1 WHERE m.tenant_id=? AND m.user_id<>? AND m.status='active' AND u.status='active' ORDER BY u.first_name,u.last_name LIMIT 200");
 $q->execute([$tid,$uid]);$recipients=array_values(array_filter($q->fetchAll(),static function($r)use($db,$tid,$uid,$tenant){return messagingPairAllowed($db,$tid,$uid,(int)$r['id'],(string)$tenant['role']);}));json_response(['ok'=>true,'recipients'=>$recipients]);
}
if($method==='GET'&&$action==='thread'){
 $thread=filter_var($_GET['thread_id']??null,FILTER_VALIDATE_INT);
 if(!$thread||$thread<1)json_response(['error'=>'Invalid conversation'],422);
 threadAccess($db,$tid,$uid,$thread);
 $q=$db->prepare('SELECT m.id,m.sender_id,m.body,m.created_at,u.first_name,u.last_name, (m.sender_id=?) mine FROM module_messages m JOIN users u ON u.id=m.sender_id WHERE m.tenant_id=? AND m.thread_id=? ORDER BY m.id DESC LIMIT 100');
 $q->execute([$uid,$tid,$thread]);$messages=array_reverse($q->fetchAll());
 $last=$messages?(int)end($messages)['id']:0;
 if($last){$q=$db->prepare('UPDATE module_message_participants SET last_read_message_id=GREATEST(COALESCE(last_read_message_id,0),?) WHERE tenant_id=? AND thread_id=? AND user_id=?');$q->execute([$last,$tid,$thread,$uid]);}
 json_response(['ok'=>true,'messages'=>$messages]);
}
if($method==='GET'&&$action==='preferences'){
 $q=$db->prepare('SELECT in_app_enabled FROM module_message_preferences WHERE tenant_id=? AND user_id=?');$q->execute([$tid,$uid]);
 $v=$q->fetchColumn();json_response(['ok'=>true,'in_app_enabled'=>$v===false?true:(bool)$v]);
}
if($method==='POST'&&$action==='preferences'){
 $v=(string)($_POST['in_app_enabled']??'');
 if(!in_array($v,['0','1'],true))json_response(['error'=>'Invalid preference'],422);
 $q=$db->prepare('INSERT INTO module_message_preferences(tenant_id,user_id,in_app_enabled) VALUES(?,?,?) ON DUPLICATE KEY UPDATE in_app_enabled=VALUES(in_app_enabled)');
 $q->execute([$tid,$uid,(int)$v]);json_response(['ok'=>true,'in_app_enabled'=>$v==='1']);
}
if($method==='POST'&&$action==='create'){
 $recipient=filter_var($_POST['recipient_id']??null,FILTER_VALIDATE_INT);
 $subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body']??''));
 if(!$recipient||$recipient===$uid||!activeRecipient($db,$tid,$recipient))json_response(['error'=>'Invalid recipient'],422);
 if(!messagingPairAllowed($db,$tid,$uid,$recipient,(string)$tenant['role']))json_response(['error'=>'Messaging is available to your tenant owner or a client/contractor connected through a quote request or scheduled assignment'],403);
 if($subject===''||mb_strlen($subject)>180||$body===''||mb_strlen($body)>5000)json_response(['error'=>'Subject or message length invalid'],422);
 // A recipient must currently have this module granted in the same tenant.
 $q=$db->prepare("SELECT 1 FROM tenant_memberships m JOIN tenant_module_roles r ON r.tenant_id=m.tenant_id AND r.module_key='messaging' AND r.role=m.role AND r.allowed=1 WHERE m.tenant_id=? AND m.user_id=? AND m.status='active' LIMIT 1");
 $q->execute([$tid,$recipient]);if(!$q->fetchColumn())json_response(['error'=>'Recipient cannot access messaging'],422);
 try{
  $db->beginTransaction();
  $q=$db->prepare('INSERT INTO module_message_threads(tenant_id,subject,created_by) VALUES(?,?,?)');$q->execute([$tid,$subject,$uid]);$thread=(int)$db->lastInsertId();
  $q=$db->prepare('INSERT INTO module_message_participants(tenant_id,thread_id,user_id) VALUES(?,?,?)');
  $q->execute([$tid,$thread,$uid]);$q->execute([$tid,$thread,$recipient]);
  $q=$db->prepare('INSERT INTO module_messages(tenant_id,thread_id,sender_id,body) VALUES(?,?,?,?)');$q->execute([$tid,$thread,$uid,$body]);
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Messaging create: '.$e->getMessage());json_response(['error'=>'Unable to create conversation'],500);}
 json_response(['ok'=>true,'thread_id'=>$thread],201);
}
if($method==='POST'&&$action==='reply'){
 $thread=filter_var($_POST['thread_id']??null,FILTER_VALIDATE_INT);$body=trim((string)($_POST['body']??''));
 if(!$thread||$thread<1||$body===''||mb_strlen($body)>5000)json_response(['error'=>'Invalid reply'],422);
 threadAccess($db,$tid,$uid,$thread);
 $q=$db->prepare('SELECT user_id FROM module_message_participants WHERE tenant_id=? AND thread_id=? AND user_id<>?');
 $q->execute([$tid,$thread,$uid]);
 foreach($q->fetchAll(PDO::FETCH_COLUMN) as $other){
  if(!activeRecipient($db,$tid,(int)$other)||!messagingPairAllowed($db,$tid,$uid,(int)$other,(string)$tenant['role']))
   json_response(['error'=>'This conversation is no longer eligible for new messages'],403);
 }
 $q=$db->prepare('INSERT INTO module_messages(tenant_id,thread_id,sender_id,body) VALUES(?,?,?,?)');$q->execute([$tid,$thread,$uid,$body]);
 $q=$db->prepare('UPDATE module_message_threads SET updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?');$q->execute([$thread,$tid]);
 json_response(['ok'=>true],201);
}
json_response(['error'=>'Unsupported action'],404);
