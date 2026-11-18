<?php
declare(strict_types=1);
// Run ONLY against a freshly created disposable database. Never against production.
$name=(string)(getenv('DB_NAME')?:'');
if(!preg_match('/^brite_cycle93e_test_[a-z0-9_]+$/D',$name)){
    fwrite(STDERR,"REFUSED: DB_NAME must start brite_cycle93e_test_\n");exit(2);
}
$host=getenv('DB_HOST')?:'127.0.0.1';
$port=getenv('DB_PORT')?:'3306';
$db=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",getenv('DB_USER')?:'britescheduler',getenv('DB_PASS')?:'',[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false
]);
$db->exec("SET time_zone='+00:00'");
$tables=(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
if($tables!==0){fwrite(STDERR,"REFUSED: disposable database must be empty\n");exit(2);}
foreach(['001_core.sql','039_enrollment_management.sql','040_enrollment_invitations.sql'] as $file){
 $sql=(string)file_get_contents(dirname(__DIR__).'/migrations/'.$file);
 foreach(explode(';',$sql) as $statement){
  $statement=trim(preg_replace('/^\s*--[^\n]*\n/m','',$statement));
  if($statement!=='')$db->exec($statement);
 }
 echo "PASS migration $file\n";
}
function check(bool $condition,string $name):void{
 if(!$condition)throw new RuntimeException("FAIL $name");
 echo "PASS $name\n";
}
$db->exec("INSERT INTO tenants (name,slug) VALUES ('Enrollment A','cycle93e-a'),('Enrollment B','cycle93e-b')");
$db->exec("INSERT INTO users (email,password_hash,first_name,last_name) VALUES ('admin93e@example.invalid','test-hash','Test','Admin')");
$admin=(int)$db->lastInsertId();
$db->prepare("INSERT INTO tenant_memberships (tenant_id,user_id,role) VALUES (1,?,'owner'),(2,?,'owner')")->execute([$admin,$admin]);
$application=$db->prepare("INSERT INTO enrollment_applications (tenant_id,applicant_type,full_name,email,submitted_by) VALUES (?,?,?,?,?)");
$application->execute([1,'client','Client A','client93e@example.invalid',$admin]);
$clientApp=(int)$db->lastInsertId();
$application->execute([2,'contractor','Contractor B','contractor93e@example.invalid',$admin]);
$contractorApp=(int)$db->lastInsertId();
$review=$db->prepare("UPDATE enrollment_applications SET status='approved',reviewed_by=? WHERE id=? AND tenant_id=? AND status='pending'");
$review->execute([$admin,$clientApp,2]);
check($review->rowCount()===0,'cross-tenant review blocked');
$review->execute([$admin,$clientApp,1]);
check($review->rowCount()===1,'same-tenant review succeeds');
$review->execute([$admin,$clientApp,1]);
check($review->rowCount()===0,'duplicate review blocked');
$token=bin2hex(random_bytes(32));
$hash=hash('sha256',$token);
$insert=$db->prepare("INSERT INTO enrollment_invitations (tenant_id,application_id,token_hash,expires_at,created_by) VALUES (?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 48 HOUR),?)");
$insert->execute([1,$clientApp,$hash,$admin]);
$inviteId=(int)$db->lastInsertId();
$lookup=$db->prepare("SELECT i.id FROM enrollment_invitations i JOIN enrollment_applications a ON a.id=i.application_id AND a.tenant_id=i.tenant_id WHERE i.token_hash=? AND i.tenant_id=? AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>UTC_TIMESTAMP() AND a.status='approved'");
$lookup->execute([$hash,2]);check(!$lookup->fetch(),'cross-tenant invitation inaccessible');
$lookup->execute([$hash,1]);check((bool)$lookup->fetch(),'valid invitation accessible');
$db->prepare("UPDATE enrollment_invitations SET accepted_at=UTC_TIMESTAMP() WHERE id=?")->execute([$inviteId]);
$lookup->execute([$hash,1]);check(!$lookup->fetch(),'accepted invitation cannot be reused');
$insert->execute([1,$clientApp,hash('sha256',bin2hex(random_bytes(32))),$admin]);
$second=(int)$db->lastInsertId();
$db->prepare("UPDATE enrollment_invitations SET revoked_at=UTC_TIMESTAMP() WHERE id=?")->execute([$second]);
check((int)$db->query("SELECT COUNT(*) FROM enrollment_invitations WHERE revoked_at IS NOT NULL")->fetchColumn()===1,'revocation persisted');
$db->prepare("INSERT INTO users (email,password_hash,first_name,last_name) VALUES (?,?,?,?)")->execute(['client93e@example.invalid',password_hash('cycle93e-only-test',PASSWORD_DEFAULT),'Client','A']);
$clientUser=(int)$db->lastInsertId();
$db->prepare("INSERT INTO tenant_memberships (tenant_id,user_id,role) VALUES (?,?,'client')")->execute([1,$clientUser]);
$db->prepare("INSERT INTO clients (tenant_id,user_id,first_name,last_name,email) VALUES (?,?,?,?,?)")->execute([1,$clientUser,'Client','A','client93e@example.invalid']);
check((int)$db->query("SELECT COUNT(*) FROM clients WHERE tenant_id=1 AND user_id=$clientUser")->fetchColumn()===1,'client profile and membership created');
$db->prepare("INSERT INTO users (email,password_hash,first_name,last_name) VALUES (?,?,?,?)")->execute(['contractor93e@example.invalid',password_hash('cycle93e-only-test',PASSWORD_DEFAULT),'Contractor','B']);
$contractorUser=(int)$db->lastInsertId();
$db->prepare("INSERT INTO tenant_memberships (tenant_id,user_id,role) VALUES (?,?,'contractor')")->execute([2,$contractorUser]);
$db->prepare("INSERT INTO contractors (tenant_id,user_id,first_name,last_name,email) VALUES (?,?,?,?,?)")->execute([2,$contractorUser,'Contractor','B','contractor93e@example.invalid']);
check((int)$db->query("SELECT COUNT(*) FROM contractors WHERE tenant_id=2 AND user_id=$contractorUser")->fetchColumn()===1,'contractor profile and membership created');
echo "PASS disposable schema integration smoke tests\n";
