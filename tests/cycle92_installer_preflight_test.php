<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Installation/InstallerGate.php';
require_once dirname(__DIR__).'/app/Installation/MigrationInventory.php';
use BriteScheduler\Installation\InstallerGate;
use BriteScheduler\Installation\MigrationInventory;
$failures=0;
$check=static function(bool $ok,string $label)use(&$failures):void {
 echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL;
 if(!$ok)++$failures;
};
$dir=sys_get_temp_dir().'/brite-install-gate-'.bin2hex(random_bytes(8));
if(!mkdir($dir.'/migrations',0700,true))throw new RuntimeException('Unable to create temporary fixture');
$prior=getenv('BRITE_INSTALL_SETUP_TOKEN');
try {
 putenv('BRITE_INSTALL_SETUP_TOKEN');
 $state=InstallerGate::inspect($dir);
 $check(!$state['setup_may_begin'],'Missing token denies setup');
 putenv('BRITE_INSTALL_SETUP_TOKEN='.str_repeat('x',32));
 $state=InstallerGate::inspect($dir);
 $check($state['setup_may_begin'],'Fresh fixture with token permits preflight');
 file_put_contents($dir.'/.env','fixture');
 $check(!InstallerGate::inspect($dir)['setup_may_begin'],'Existing configuration blocks setup');
 unlink($dir.'/.env');
 file_put_contents($dir.'/.brite-installed.lock','fixture');
 $check(!InstallerGate::inspect($dir)['setup_may_begin'],'Installed lock blocks setup');
 unlink($dir.'/.brite-installed.lock');
 putenv('BRITE_INSTALL_SETUP_TOKEN=short');
 $check(!InstallerGate::inspect($dir)['setup_may_begin'],'Short token blocks setup');
 file_put_contents($dir.'/migrations/001_first.sql','SELECT 1;');
 file_put_contents($dir.'/migrations/002_second.sql','SELECT 2;');
 $inventory=MigrationInventory::list($dir);
 $check(count($inventory)===2 && $inventory[0]['version']==='001_first' && $inventory[1]['version']==='002_second','Migration inventory sorted');
 $check($inventory[0]['sha256']===hash('sha256','SELECT 1;'),'Migration checksum matches');
 symlink($dir.'/migrations/001_first.sql',$dir.'/migrations/003_symlink.sql');
 $check(count(MigrationInventory::list($dir))===2,'Symlink migrations excluded');
 unlink($dir.'/migrations/003_symlink.sql');
} finally {
 if($prior===false)putenv('BRITE_INSTALL_SETUP_TOKEN');else putenv('BRITE_INSTALL_SETUP_TOKEN='.$prior);
 foreach(['001_first.sql','002_second.sql'] as $file)@unlink($dir.'/migrations/'.$file);
 @rmdir($dir.'/migrations');
 @rmdir($dir);
}
exit($failures===0?0:1);
