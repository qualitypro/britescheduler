<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Installation/ConfigurationWriter.php';
use BriteScheduler\Installation\ConfigurationWriter;
$failures=0;
$check=static function(bool $ok,string $name)use(&$failures):void{
 echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;
 if(!$ok)++$failures;
};
$root=sys_get_temp_dir().'/brite-config-writer-'.bin2hex(random_bytes(8));
if(!mkdir($root,0700,true))throw new RuntimeException('Fixture creation failed');
$template="APP_URL=\nDB_HOST=\nDB_PORT=\nDB_NAME=\nDB_USER=\nDB_PASS=\n";
$config=[
 'APP_URL'=>'https://example.test',
 'DB_HOST'=>'localhost',
 'DB_PORT'=>'3306',
 'DB_NAME'=>'brite_fresh',
 'DB_USER'=>'brite_user',
 'DB_PASS'=>'test-secret-123',
];
try{
 file_put_contents($root.'/.env.example',$template);
 ConfigurationWriter::write($root,$config);
 $env=file_get_contents($root.'/.env');
 $check(is_string($env) && str_contains($env,'DB_NAME=brite_fresh'),'Creates populated configuration');
 $check((fileperms($root.'/.env') & 0777)===0600,'Configuration permissions are owner-only');
 try{ConfigurationWriter::write($root,$config);$check(false,'Refuses overwrite');}
 catch(RuntimeException $e){$check(true,'Refuses overwrite');}
 unlink($root.'/.env');
 file_put_contents($root.'/.brite-installed.lock',"installed\n");
 try{ConfigurationWriter::write($root,$config);$check(false,'Refuses installed directory');}
 catch(RuntimeException $e){$check(true,'Refuses installed directory');}
 unlink($root.'/.brite-installed.lock');
 foreach([
  array_merge($config,['DB_HOST'=>'localhost;bad']),
  array_merge($config,['DB_PORT'=>'0']),
  array_merge($config,['DB_PASS'=>"line\nbreak"]),
  array_merge($config,['UNSUPPORTED_KEY'=>'value']),
 ] as $invalid){
  try{ConfigurationWriter::write($root,$invalid);$check(false,'Rejects invalid configuration');}
  catch(DomainException $e){$check(true,'Rejects invalid configuration');}
  $check(!file_exists($root.'/.env'),'Invalid configuration leaves no .env');
 }
}finally{
 foreach(glob($root.'/.env*')?:[] as $file)@unlink($file);
 @unlink($root.'/.brite-installed.lock');
 @rmdir($root);
}
exit($failures===0?0:1);
