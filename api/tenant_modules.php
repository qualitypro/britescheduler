<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/ModuleRegistry.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('owner','admin');
$tid=(int)$tenant['tenant_id'];
$installed=ModuleRegistry::installed();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
 $enabled=ModuleRegistry::enabled($tid);$modules=[];
 foreach($installed as $key=>$module)$modules[]=[
  'key'=>$key,'name'=>$module['name'],'version'=>$module['version'],
  'description'=>$module['description'],'menu_label'=>$module['menu_label'],
  'supported_roles'=>$module['roles'],
  'allowed_roles'=>ModuleRegistry::allowedRoles($tid,$key),
  'enabled'=>isset($enabled[$key])
 ];
 json_response(['ok'=>true,'modules'=>$modules]);
}
if($method!=='POST'){header('Allow: GET, POST');json_response(['error'=>'Method not allowed'],405);}
verify_csrf();
$key=(string)($_POST['module_key']??'');
if(!isset($installed[$key]))json_response(['error'=>'Module not installed'],404);
$db=Database::connection();
$mode=(string)($_POST['action']??'activation');
if($mode==='permissions'){
 $roles=$_POST['roles']??[];
 if(!is_array($roles)||count($roles)>6)json_response(['error'=>'Invalid role selection'],422);
 $roles=array_values(array_unique($roles));
 foreach($roles as $role){
  if(!is_string($role)||!in_array($role,$installed[$key]['roles'],true))
   json_response(['error'=>'Role not supported by module'],422);
 }
 try{
  $db->beginTransaction();
  $lock=$db->prepare('SELECT module_key FROM tenant_modules WHERE tenant_id=? AND module_key=? FOR UPDATE');
  $lock->execute([$tid,$key]);
  if(!$lock->fetchColumn()){
   $db->rollBack();
   json_response(['error'=>'Activate the module before configuring roles'],409);
  }
  $delete=$db->prepare('DELETE FROM tenant_module_roles WHERE tenant_id=? AND module_key=?');
  $delete->execute([$tid,$key]);
  $insert=$db->prepare('INSERT INTO tenant_module_roles(tenant_id,module_key,role,allowed) VALUES(?,?,?,1)');
  foreach($roles as $role)$insert->execute([$tid,$key,$role]);
  $db->commit();
 }catch(Throwable $e){
  if($db->inTransaction())$db->rollBack();
  error_log('Module permissions update failed: '.$e->getMessage());
  json_response(['error'=>'Unable to save role permissions'],500);
 }
 json_response(['ok'=>true,'module_key'=>$key,'allowed_roles'=>$roles]);
}
if($mode!=='activation')json_response(['error'=>'Unknown action'],422);
$enabled=(string)($_POST['enabled']??'');
if(!in_array($enabled,['0','1'],true))json_response(['error'=>'Invalid activation state'],422);
try{
 $db->beginTransaction();
 $existing=$db->prepare('SELECT enabled FROM tenant_modules WHERE tenant_id=? AND module_key=? FOR UPDATE');
 $existing->execute([$tid,$key]);
 $wasPresent=$existing->fetchColumn()!==false;
 $q=$db->prepare('INSERT INTO tenant_modules(tenant_id,module_key,enabled,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_by=VALUES(updated_by)');
 $q->execute([$tid,$key,(int)$enabled,(int)$user['id']]);
 if(!$wasPresent&&$enabled==='1'&&in_array('owner',$installed[$key]['roles'],true)){
  $grant=$db->prepare('INSERT INTO tenant_module_roles(tenant_id,module_key,role,allowed) VALUES(?,?,?,1)');
  $grant->execute([$tid,$key,'owner']);
 }
 $db->commit();
}catch(Throwable $e){
 if($db->inTransaction())$db->rollBack();
 error_log('Module activation update failed: '.$e->getMessage());
 json_response(['error'=>'Unable to update module'],500);
}
json_response(['ok'=>true,'module_key'=>$key,'enabled'=>$enabled==='1']);
