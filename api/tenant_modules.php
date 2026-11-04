<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/ModuleRegistry.php';
$user=Auth::requireUser();$tenant=Auth::requireRole('owner','admin');
$tid=(int)$tenant['tenant_id'];$installed=ModuleRegistry::installed();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
 $enabled=ModuleRegistry::enabled($tid);$modules=[];
 foreach($installed as $key=>$module)$modules[]=[
  'key'=>$key,'name'=>$module['name'],'version'=>$module['version'],
  'description'=>$module['description'],'menu_label'=>$module['menu_label'],
  'enabled'=>isset($enabled[$key])
 ];
 json_response(['ok'=>true,'modules'=>$modules]);
}
if($method!=='POST'){header('Allow: GET, POST');json_response(['error'=>'Method not allowed'],405);}
verify_csrf();
$key=(string)($_POST['module_key']??'');
if(!isset($installed[$key]))json_response(['error'=>'Module not installed'],404);
$enabled=(string)($_POST['enabled']??'');
if(!in_array($enabled,['0','1'],true))json_response(['error'=>'Invalid activation state'],422);
$q=Database::connection()->prepare('INSERT INTO tenant_modules(tenant_id,module_key,enabled,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_by=VALUES(updated_by)');
$q->execute([$tid,$key,(int)$enabled,(int)$user['id']]);
json_response(['ok'=>true,'module_key'=>$key,'enabled'=>$enabled==='1']);
