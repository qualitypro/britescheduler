<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
$esc=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$token=(string)($_POST['token']??$_GET['token']??'');
$validToken=(bool)preg_match('/^[a-f0-9]{64}$/D',$token);
$error='';
$success=false;
$invite=null;
$db=Database::connection();
if($validToken){
    $q=$db->prepare("SELECT i.id,i.tenant_id,i.application_id,a.applicant_type,a.full_name,a.email,a.phone
        FROM enrollment_invitations i JOIN enrollment_applications a ON a.id=i.application_id AND a.tenant_id=i.tenant_id
        WHERE i.token_hash=? AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>UTC_TIMESTAMP() AND a.status='approved' LIMIT 1");
    $q->execute([hash('sha256',$token)]);
    $invite=$q->fetch(PDO::FETCH_ASSOC) ?: null;
}
if(!$invite) $error='Invitation invalid, expired, or already used.';
if($invite && ($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        if(!hash_equals(csrf_token(),(string)($_POST['_csrf']??'')))throw new DomainException('Invalid request token');
        $first=trim((string)($_POST['first_name']??''));
        $last=trim((string)($_POST['last_name']??''));
        $password=(string)($_POST['password']??'');
        if(!$first||!$last||strlen($first)>100||strlen($last)>100||strlen($password)<12||strlen($password)>256)throw new DomainException('Enter your first and last name and a password of at least 12 characters');
        $db->beginTransaction();
        // Lock the tenant to serialize activation against other enrollment writes.
        $lock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
        $lock->execute([(int)$invite['tenant_id']]);
        if(!$lock->fetch())throw new DomainException('Tenant unavailable');
        $q=$db->prepare("SELECT i.id FROM enrollment_invitations i JOIN enrollment_applications a ON a.id=i.application_id AND a.tenant_id=i.tenant_id WHERE i.id=? AND i.tenant_id=? AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>UTC_TIMESTAMP() AND a.status='approved' FOR UPDATE");
        $q->execute([(int)$invite['id'],(int)$invite['tenant_id']]);
        if(!$q->fetch())throw new DomainException('Invitation no longer available');
        $q=$db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $q->execute([$invite['email']]);
        if($q->fetch())throw new DomainException('Account already exists; contact the administrator');
        $q=$db->prepare("INSERT INTO users (email,password_hash,first_name,last_name,phone,status) VALUES (?,?,?,?,?,'active')");
        $q->execute([$invite['email'],password_hash($password,PASSWORD_DEFAULT),$first,$last,$invite['phone']]);
        $userId=(int)$db->lastInsertId();
        $q=$db->prepare("INSERT INTO tenant_memberships (tenant_id,user_id,role,status) VALUES (?,?,?,'active')");
        $q->execute([(int)$invite['tenant_id'],$userId,$invite['applicant_type']]);
        if($invite['applicant_type']==='client'){
            $q=$db->prepare("INSERT INTO clients (tenant_id,user_id,first_name,last_name,email,phone,status) VALUES (?,?,?,?,?,?,'active')");
        }else{
            $q=$db->prepare("INSERT INTO contractors (tenant_id,user_id,first_name,last_name,email,phone,status) VALUES (?,?,?,?,?,?,'active')");
        }
        $q->execute([(int)$invite['tenant_id'],$userId,$first,$last,$invite['email'],$invite['phone']]);
        $q=$db->prepare('UPDATE enrollment_invitations SET accepted_at=UTC_TIMESTAMP() WHERE id=? AND tenant_id=?');
        $q->execute([(int)$invite['id'],(int)$invite['tenant_id']]);
        $q=$db->prepare('INSERT INTO audit_log (tenant_id,user_id,action,entity_type,entity_id,metadata) VALUES (?,?,?,?,?,?)');
        $q->execute([(int)$invite['tenant_id'],$userId,'enrollment.activate','enrollment_application',(int)$invite['application_id'],json_encode(['role'=>$invite['applicant_type']],JSON_THROW_ON_ERROR)]);
        $db->commit();
        $success=true;
        $invite=null;
    }catch(DomainException $e){
        if($db->inTransaction())$db->rollBack();
        $error=$e->getMessage();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        error_log('Enrollment activation failed');
        $error='Unable to activate account. Please contact the administrator.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enrollment activation</title><style>body{font:16px system-ui;background:#f5f7fb;color:#172033;margin:0;padding:30px}main{max-width:480px;margin:auto;background:white;padding:25px;border:1px solid #cbd5e1;border-radius:12px}label{display:block;margin:14px 0}input{display:block;box-sizing:border-box;width:100%;padding:11px;margin-top:5px}button{padding:12px 20px;cursor:pointer}</style></head><body><main><h1>Activate your enrollment</h1>
<?php if($success): ?><p role="status">Your account is ready. <a href="<?=$esc(app_url('/sign-in.v2.php'))?>">Sign in</a>.</p>
<?php elseif($invite): ?><p>Complete your <?=$esc($invite['applicant_type'])?> account for <?=$esc($invite['email'])?>.</p>
<?php if($error): ?><p role="alert"><?=$esc($error)?></p><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="token" value="<?=$esc($token)?>">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<label>First name<input name="first_name" maxlength="100" required></label>
<label>Last name<input name="last_name" maxlength="100" required></label>
<label>Password (12 characters minimum)<input type="password" name="password" minlength="12" maxlength="256" autocomplete="new-password" required></label>
<button type="submit">Activate account</button></form>
<?php else: ?><p role="alert"><?=$esc($error)?></p><?php endif; ?>
</main></body></html>
