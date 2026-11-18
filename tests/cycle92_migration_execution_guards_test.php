<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Installation/SqlStatements.php';
require_once dirname(__DIR__).'/app/Installation/MigrationPreflight.php';
use BriteScheduler\Installation\SqlStatements;
use BriteScheduler\Installation\MigrationPreflight;
$fail=0;
$check=static function(bool $ok,string $name)use(&$fail):void{
 echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;
 if(!$ok)++$fail;
};
$cases=[
 ["CREATE TABLE t (name VARCHAR(30) DEFAULT 'a;b'); SELECT 1;",2],
 ["-- comment;\nSELECT 'x;z'; # comment\nSELECT 2;",2],
 ["/* plain comment */ SELECT 1;",1],
];
foreach($cases as [$sql,$count]){
 $check(count(SqlStatements::split($sql))===$count,'SQL statement split');
}
foreach(["SELECT 'unterminated;","/*!50000 DROP TABLE users */ SELECT 1;","/* unfinished"] as $sql){
 try{SqlStatements::split($sql);$check(false,'Unsafe SQL rejected');}
 catch(DomainException $e){$check(true,'Unsafe SQL rejected');}
}
$dir=sys_get_temp_dir().'/brite-migration-'.bin2hex(random_bytes(8));
if(!mkdir($dir.'/migrations',0700,true))throw new RuntimeException('Fixture failed');
try{
 $path=$dir.'/migrations/001_test.sql';
 file_put_contents($path,"CREATE TABLE sample (id INT);\n");
 $pending=[['version'=>'001_test','sha256'=>hash_file('sha256',$path)]];
 $check(count(MigrationPreflight::inspect($dir,$pending))===1,'Valid migration preflight');
 file_put_contents($path,"DROP TABLE sample;\n");
 try{MigrationPreflight::inspect($dir,$pending);$check(false,'Checksum drift rejected');}
 catch(DomainException $e){$check(true,'Checksum drift rejected');}
 $pending[0]['sha256']=hash_file('sha256',$path);
 file_put_contents($path,"LOAD DATA INFILE 'x' INTO TABLE sample;\n");
 $pending[0]['sha256']=hash_file('sha256',$path);
 try{MigrationPreflight::inspect($dir,$pending);$check(false,'Unsafe migration rejected');}
 catch(DomainException $e){$check(true,'Unsafe migration rejected');}
}finally{
 @unlink($dir.'/migrations/001_test.sql');
 @rmdir($dir.'/migrations');
 @rmdir($dir);
}
exit($fail===0?0:1);
