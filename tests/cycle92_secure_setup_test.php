<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Installation/InstallerGate.php';
require_once dirname(__DIR__).'/app/Installation/SetupAuthorization.php';
require_once dirname(__DIR__).'/app/Installation/InstallationMutex.php';
require_once dirname(__DIR__).'/app/Installation/DatabaseConnectionPreflight.php';
use BriteScheduler\Installation\SetupAuthorization;
use BriteScheduler\Installation\InstallationMutex;
use BriteScheduler\Installation\DatabaseConnectionPreflight;
$fail=0;
$check=static function(bool $ok,string $name)use(&$fail):void{
 echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok)++$fail;
};
$dir=sys_get_temp_dir().'/brite-setup-auth-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700,true))throw new RuntimeException('Cannot create fixture');
$prior=getenv('BRITE_INSTALL_SETUP_TOKEN');
try {
 putenv('BRITE_INSTALL_SETUP_TOKEN='.str_repeat('t',40));
 $check(!SetupAuthorization::authorize($dir,'wrong'),'Invalid setup token denied');
 $check(SetupAuthorization::authorize($dir,str_repeat('t',40)),'Correct token accepted for fresh fixture');
 file_put_contents($dir.'/.env','fixture');
 $check(!SetupAuthorization::authorize($dir,str_repeat('t',40)),'Existing configuration denies token');
 unlink($dir.'/.env');
 $check(!SetupAuthorization::isAuthorized($dir),'No session denies setup');
 $mutex=new InstallationMutex();
 $mutex->acquire($dir);
 $check(is_file($dir.'/.brite-install.mutex'),'Mutex created');
 $second=new InstallationMutex();
 try{$second->acquire($dir);$check(false,'Concurrent mutex refused');}
 catch(RuntimeException $e){$check(true,'Concurrent mutex refused');}
 $mutex->release();
 $second->acquire($dir);
 $second->release();
 $check(true,'Mutex can be reacquired');
 $valid=['host'=>'localhost','port'=>'3306','database'=>'brite_test','username'=>'test','password'=>''];
 $check(DatabaseConnectionPreflight::validate($valid)===$valid,'Valid configuration accepted');
 foreach([
  array_merge($valid,['port'=>'0']),
  array_merge($valid,['database'=>'bad-name']),
  array_merge($valid,['host'=>'localhost;evil']),
  array_merge($valid,['password'=>"line\nbreak"]),
 ] as $invalid){
  try{DatabaseConnectionPreflight::validate($invalid);$check(false,'Invalid configuration rejected');}
  catch(DomainException $e){$check(true,'Invalid configuration rejected');}
 }
} finally {
 if($prior===false)putenv('BRITE_INSTALL_SETUP_TOKEN');else putenv('BRITE_INSTALL_SETUP_TOKEN='.$prior);
 @unlink($dir.'/.brite-install.mutex');
 @rmdir($dir);
}
exit($fail===0?0:1);
