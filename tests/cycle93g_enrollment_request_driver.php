<?php
declare(strict_types=1);
// CLI-only request driver for a disposable DB. Not accessible as an HTTP endpoint.
if (PHP_SAPI !== 'cli' || !preg_match('/^brite_cycle93e_test_[a-z0-9_]+$/D',(string)getenv('DB_NAME'))) {
    fwrite(STDERR,"REFUSED: CLI + disposable DB required\n");exit(2);
}
if ($argc < 5) {fwrite(STDERR,"Usage: driver user_id tenant_id method action [application_id] [decision]\n");exit(2);}
$_SERVER['REQUEST_METHOD']=$argv[3];
$_SERVER['REQUEST_URI']='/dashboard/enrollment.php';
require dirname(__DIR__).'/app/bootstrap.php';
$_SESSION['user_id']=(int)$argv[1];
$_SESSION['tenant_id']=(int)$argv[2];
$_SESSION['_csrf']=str_repeat('a',64);
$_POST=['_csrf'=>$_SESSION['_csrf'],'action'=>$argv[4]];
if ($argv[4]==='submit') {
    $_POST+=['applicant_type'=>'client','full_name'=>'CLI Test Applicant','email'=>'cli93g_'.getmypid().'@example.invalid','notes'=>'93G test'];
}
if (isset($argv[5]))$_POST['application_id']=$argv[5];
if (isset($argv[6]))$_POST['decision']=$argv[6];
if (getenv('CYCLE93_BAD_CSRF') === '1') $_POST['_csrf']='invalid';
include dirname(__DIR__).'/enrollment.php';
