<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/ModuleRegistry.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
 $user=Auth::requireUser();$tenant=Auth::tenant();
 if(!isset(ModuleRegistry::activeFor($tenant)['intelligence'])){http_response_code(403);echo json_encode(['error'=>'Forbidden']);exit;}
 $pdo=Database::connection();$tid=(int)$tenant['tenant_id'];$uid=(int)$user['id'];
 $financialAccess=in_array((string)($tenant['role']??''),['owner','admin','accounting'],true);
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET'){
  $q=$pdo->prepare('SELECT name,config_json,updated_at FROM intelligence_saved_views WHERE tenant_id=? AND user_id=? ORDER BY updated_at DESC LIMIT 40');
  $q->execute([$tid,$uid]);$views=[];
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){if(!$financialAccess&&str_starts_with((string)$row['name'],'Financial · '))continue;$views[]=['name'=>$row['name'],'config'=>json_decode($row['config_json'],true),'updated_at'=>$row['updated_at']];}
  echo json_encode(['views'=>$views],JSON_THROW_ON_ERROR);exit;
 }
 if(!in_array($method,['POST','DELETE'],true)){http_response_code(405);exit;}
 $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
 if(!hash_equals(csrf_token(),$csrf)){http_response_code(403);echo json_encode(['error'=>'Invalid CSRF token']);exit;}
 $raw=file_get_contents('php://input',false,null,0,16385);
 if($raw===false||strlen($raw)>16384)throw new InvalidArgumentException('Request body exceeds 16 KiB');
 $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
 if(!is_array($body))throw new InvalidArgumentException('Invalid request body');
 $name=trim((string)($body['name']??''));
 if($name===''||strlen($name)>100)throw new InvalidArgumentException('View name must be 1–100 characters');
 if(str_starts_with($name,'Financial · ')&&!$financialAccess){http_response_code(403);echo json_encode(['error'=>'Financial views require elevated access']);exit;}
 if($method==='DELETE'){
  $q=$pdo->prepare('DELETE FROM intelligence_saved_views WHERE tenant_id=? AND user_id=? AND name=?');
  $q->execute([$tid,$uid,$name]);echo json_encode(['ok'=>true]);exit;
 }
 $config=$body['config']??null;
 if(!is_array($config))throw new InvalidArgumentException('Invalid view configuration');
 $metrics=$config['metrics']??null;$interval=$config['interval']??null;
 if(!is_array($metrics)||!array_is_list($metrics)||count($metrics)<1||count($metrics)>8||!in_array($interval,['day','week','month'],true))throw new InvalidArgumentException('Invalid series selection');
 if(count(array_unique($metrics,SORT_REGULAR))!==count($metrics))throw new InvalidArgumentException('Duplicate metrics are not allowed');
 if(str_starts_with($name,'Financial · ') && count($metrics)>6)throw new InvalidArgumentException('Financial views support up to six series');
 foreach($metrics as $metric){if(!is_string($metric)||!preg_match('/^[a-z][a-z0-9_.-]{1,100}$/D',$metric))throw new InvalidArgumentException('Invalid metric key');}
 foreach(['from','to'] as $field){$date=(string)($config[$field]??'');$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dt||$dt->format('Y-m-d')!==$date)throw new InvalidArgumentException('Invalid date');}
 if($config['from']>$config['to']||(new DateTimeImmutable($config['from']))->diff(new DateTimeImmutable($config['to']))->days>730)throw new InvalidArgumentException('Date range must be 0–730 days');
 $json=json_encode(['metrics'=>array_values(array_unique($metrics)),'interval'=>$interval,'from'=>$config['from'],'to'=>$config['to']],JSON_THROW_ON_ERROR);
 if(strlen($json)>8192)throw new InvalidArgumentException('Configuration too large');
 $q=$pdo->prepare('INSERT INTO intelligence_saved_views(tenant_id,user_id,name,config_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),updated_at=CURRENT_TIMESTAMP');
 $q->execute([$tid,$uid,$name,$json]);echo json_encode(['ok'=>true]);
} catch(InvalidArgumentException|JsonException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Intelligence views: '.$e->getMessage());http_response_code(500);echo json_encode(['error'=>'Unable to manage saved views. Apply the Intelligence migration first.']);}
