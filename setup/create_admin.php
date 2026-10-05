<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
if ($argc < 6) { exit("Usage: php setup/create_admin.php tenant-slug \"Tenant Name\" email first last\n"); }
[$script,$slug,$tenantName,$email,$first,$last]=$argv;
$password=getenv('BRITE_ADMIN_PASSWORD'); if(!$password) exit("Set BRITE_ADMIN_PASSWORD first.\n");
$pdo=Database::connection();$pdo->beginTransaction();
try{
 $q=$pdo->prepare("INSERT INTO tenants(name,slug) VALUES(?,?)");$q->execute([$tenantName,$slug]);$tid=(int)$pdo->lastInsertId();
 $q=$pdo->prepare("INSERT INTO users(email,password_hash,first_name,last_name) VALUES(?,?,?,?)");$q->execute([strtolower($email),password_hash($password,PASSWORD_DEFAULT),$first,$last]);$uid=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO tenant_memberships(tenant_id,user_id,role) VALUES(?,?,'owner')")->execute([$tid,$uid]);
 $pdo->commit();echo "Created tenant {$tenantName} and owner {$email}\n";
}catch(Throwable $e){$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
