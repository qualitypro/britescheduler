<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Installation/MigrationPlan.php';
use BriteScheduler\Installation\MigrationPlan;
$items=[
 ['version'=>'001_core','sha256'=>str_repeat('a',64)],
 ['version'=>'002_add','sha256'=>str_repeat('b',64)],
 ['version'=>'003_more','sha256'=>str_repeat('c',64)],
];
$fail=0;
$check=static function(bool $ok,string $label)use(&$fail):void{
 echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL;
 if(!$ok)++$fail;
};
$p=MigrationPlan::fromInventory($items,['001_core']);
$check($p['applied_count']===1 && count($p['pending'])===2,'Pending migrations follow applied prefix');
$p=MigrationPlan::fromInventory($items,[],true);
$check(count($p['pending'])===3 && $p['requires_bootstrap'],'Fresh install plan');
$p=MigrationPlan::fromInventory($items,['001_core','002_add','003_more']);
$check(count($p['pending'])===0,'Fully applied plan');
foreach([
 ['002_add'],
 ['001_core','003_more'],
 ['001_core','999_unknown'],
 ['001_core','001_core'],
] as $invalid){
 try{MigrationPlan::fromInventory($items,$invalid);$check(false,'Reject invalid history '.implode(',',$invalid));}
 catch(DomainException $e){$check(true,'Reject invalid history '.implode(',',$invalid));}
}
exit($fail===0?0:1);
