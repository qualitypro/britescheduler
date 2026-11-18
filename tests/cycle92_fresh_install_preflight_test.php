<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/FreshInstallInput.php';
use BriteScheduler\Installation\FreshInstallInput;
$root = sys_get_temp_dir() . '/brite92g-preflight-' . bin2hex(random_bytes(8));
mkdir($root,0700);
file_put_contents($root.'/.env.example', "APP_URL=\nDB_HOST=\nDB_PORT=\nDB_NAME=\nDB_USER=\nDB_PASS=\n");
$config = ['APP_URL'=>'https://example.test','DB_HOST'=>'localhost','DB_PORT'=>'3306','DB_NAME'=>'brite_fixture','DB_USER'=>'root','DB_PASS'=>''];
$owner = ['tenant_name'=>'Fixture','tenant_slug'=>'fixture','owner_email'=>'owner@example.test','owner_first_name'=>'First','owner_last_name'=>'Last','owner_password'=>str_repeat('x',16),'timezone'=>'America/New_York'];
$failed=0;
$check=static function(bool $ok,string $name) use (&$failed):void {echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok)++$failed;};
try {
    FreshInstallInput::validate($root,$config,$owner,'brite_fixture');
    $check(true,'Valid input accepted');
    foreach ([
        ['DB_PASS whitespace',array_merge($config,['DB_PASS'=>'bad password']),$owner,'brite_fixture'],
        ['DB_PASS newline',array_merge($config,['DB_PASS'=>"bad\npass"]),$owner,'brite_fixture'],
        ['Database mismatch',$config,$owner,'other_database'],
        ['Invalid timezone',$config,array_merge($owner,['timezone'=>'Bad/Zone']),'brite_fixture'],
        ['Oversized tenant',$config,array_merge($owner,['tenant_name'=>str_repeat('x',151)]),'brite_fixture'],
        ['Weak password',$config,array_merge($owner,['owner_password'=>'short']),'brite_fixture'],
    ] as [$name,$c,$o,$database]) {
        try {FreshInstallInput::validate($root,$c,$o,$database);$check(false,$name.' rejected');}
        catch(DomainException $e) {$check(true,$name.' rejected');}
    }
} finally {
    @unlink($root.'/.env.example');
    @rmdir($root);
}
exit($failed?1:0);
