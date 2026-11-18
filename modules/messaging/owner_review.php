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
 $page=max(1,min(100000,(int)($_GET['page']??1)));
 $size=(int)($_GET['page_size']??25);
 if(!in_array($size,[10,25,50,100],true))$size=25;
 $search=trim((string)($_GET['search']??''));
 if(mb_strlen($search)>150)$search=mb_substr($search,0,150);
 $sort=(string)($_GET['sort']??'recent');
 $orders=[
  'recent'=>'t.updated_at DESC,t.id DESC',
  'oldest'=>'t.updated_at ASC,t.id ASC',
  'subject'=>'t.subject ASC,t.id DESC',
  'participants'=>'participants ASC,t.id DESC',
  'count'=>'message_count DESC,t.updated_at DESC,t.id DESC'
 ];
 if(!isset($orders[$sort]))$sort='recent';
 $where='t.tenant_id=?';
 $args=[$tid];
 if($search!==''){
  $where.=" AND (t.subject LIKE ? OR EXISTS (SELECT 1 FROM module_message_participants sp JOIN users su ON su.id=sp.user_id WHERE sp.thread_id=t.id AND sp.tenant_id=t.tenant_id AND CONCAT_WS(' ',su.first_name,su.last_name) LIKE ?))";
  $pattern='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
  $args[]=$pattern;$args[]=$pattern;
 }
 $count=$db->prepare('SELECT COUNT(*) FROM module_message_threads t WHERE '.$where);
 $count->execute($args);$total=(int)$count->fetchColumn();
 $pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);$offset=($page-1)*$size;
 $sql="SELECT t.id,t.subject,t.updated_at,DATE_FORMAT(t.updated_at,'%b %e, %Y %l:%i %p') updated_at_display,
 (SELECT GROUP_CONCAT(CONCAT(u.first_name,' ',u.last_name,' (',COALESCE(m.role,'member'),')') ORDER BY u.first_name SEPARATOR ', ')
 FROM module_message_participants p JOIN users u ON u.id=p.user_id
 LEFT JOIN tenant_memberships m ON m.user_id=p.user_id AND m.tenant_id=p.tenant_id
 WHERE p.thread_id=t.id AND p.tenant_id=t.tenant_id) participants,
 (SELECT COUNT(*) FROM module_messages msg WHERE msg.tenant_id=t.tenant_id AND msg.thread_id=t.id) message_count
 FROM module_message_threads t WHERE ".$where." ORDER BY ".$orders[$sort]." LIMIT ".$size." OFFSET ".$offset;
 $q=$db->prepare($sql);$q->execute($args);
 json_response(['ok'=>true,'threads'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'page_size'=>$size,'pages'=>$pages]);
}
if($action==='thread'){
 $thread=filter_var($_GET['thread_id']??null,FILTER_VALIDATE_INT);
 if(!$thread||$thread<1)json_response(['error'=>'Invalid conversation'],422);
 $q=$db->prepare('SELECT subject FROM module_message_threads WHERE tenant_id=? AND id=?');
 $q->execute([$tid,$thread]);$subject=$q->fetchColumn();
 if($subject===false)json_response(['error'=>'Conversation unavailable'],404);
 $q=$db->prepare("SELECT msg.id,msg.sender_id,msg.body,msg.created_at,DATE_FORMAT(msg.created_at,'%b %e, %Y %l:%i %p') created_at_display,u.first_name,u.last_name FROM module_messages msg JOIN users u ON u.id=msg.sender_id WHERE msg.tenant_id=? AND msg.thread_id=? ORDER BY msg.id DESC LIMIT 200");
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
