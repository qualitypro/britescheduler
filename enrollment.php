<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/EnrollmentBlocks.php';
$user = Auth::requireUser();
$tenant = Auth::tenant();
$role = (string)$tenant['role'];
if (!in_array($role, ['owner','admin','scheduler','accounting'], true)) {
    http_response_code(403);
    exit('Access denied');
}
$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];
$canReview = in_array($role, ['owner','admin'], true);
$db = Database::connection();
$esc = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$notice = '';
$error = '';
$ready = true;
try {
    $db->query('SELECT id FROM enrollment_applications LIMIT 0');
    $db->query('SELECT id FROM enrollment_invitations LIMIT 0');
    $db->query('SELECT id FROM enrollment_blocks LIMIT 0');
} catch (PDOException $e) {
    $ready = false;
    $error = 'Enrollment database setup required. Apply migrations 039, 040 and 041_enrollment_blocks.sql.';
}
if ($ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
            throw new DomainException('Invalid request token');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'submit') {
            $type = (string)($_POST['applicant_type'] ?? '');
            $name = trim((string)($_POST['full_name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            if (!in_array($type,['contractor','client'],true) || $name === '' || strlen($name)>190 ||
                !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 ||
                strlen($phone)>40 || strlen($notes)>5000) {
                throw new DomainException('Please provide valid applicant details');
            }
            // Serialize intake within the tenant so two concurrent requests cannot
            // both pass the duplicate check before inserting.
            $db->beginTransaction();
            try {
                $lock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
                $lock->execute([$tenantId]);
                if(!$lock->fetch()) throw new DomainException('Tenant unavailable');
            EnrollmentBlocks::assertAllowed($db,$tenantId,$email);
            $q=$db->prepare("SELECT id FROM enrollment_applications WHERE tenant_id=? AND applicant_type=? AND email=? AND status IN ('pending','approved') LIMIT 1");
            $q->execute([$tenantId,$type,$email]);
            if ($q->fetch()) throw new DomainException('An active application already exists for this email and type');
            $q=$db->prepare('INSERT INTO enrollment_applications (tenant_id,applicant_type,full_name,email,phone,notes,submitted_by) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$tenantId,$type,$name,$email,$phone ?: null,$notes ?: null,$userId]);
            $applicationId=(int)$db->lastInsertId();
            $notify=$db->prepare("INSERT INTO notifications (tenant_id,user_id,type,title,body) SELECT ?,m.user_id,'enrollment.submitted','New enrollment application',? FROM tenant_memberships m WHERE m.tenant_id=? AND m.role IN ('owner','admin') AND m.status='active'");
            $notify->execute([$tenantId,ucfirst($type).' application #'.$applicationId.' submitted for review.',$tenantId]);
                $db->commit();
            } catch(Throwable $e) {
                if($db->inTransaction())$db->rollBack();
                throw $e;
            }
            $notice = 'Application submitted for review.';
        } elseif (($action==='block' || $action==='unblock') && $canReview) {
            $email=strtolower(trim((string)($_POST['email']??'')));
            $reason=trim((string)($_POST['reason']??''));
            if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) throw new DomainException('Valid email required');
            if ($action==='block' && ($reason==='' || strlen($reason)>1000)) throw new DomainException('Reason required (up to 1000 characters)');
            $db->beginTransaction();
            try {
                $lock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
                $lock->execute([$tenantId]);
                if (!$lock->fetch()) throw new DomainException('Tenant unavailable');
                if ($action==='block') {
                    $q=$db->prepare('INSERT INTO enrollment_blocks (tenant_id,email,reason,blocked_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),blocked_by=VALUES(blocked_by),blocked_at=UTC_TIMESTAMP(),unblocked_by=NULL,unblocked_at=NULL');
                    $q->execute([$tenantId,$email,$reason,$userId]);
                    $q=$db->prepare('UPDATE enrollment_invitations i JOIN enrollment_applications a ON a.id=i.application_id AND a.tenant_id=i.tenant_id SET i.revoked_at=UTC_TIMESTAMP() WHERE i.tenant_id=? AND a.email=? AND i.accepted_at IS NULL AND i.revoked_at IS NULL');
                    $q->execute([$tenantId,$email]);
                } else {
                    $q=$db->prepare('UPDATE enrollment_blocks SET unblocked_at=UTC_TIMESTAMP(),unblocked_by=? WHERE tenant_id=? AND email=? AND unblocked_at IS NULL');
                    $q->execute([$userId,$tenantId,$email]);
                    if ($q->rowCount()!==1) throw new DomainException('No active block');
                }
                $audit=$db->prepare('INSERT INTO audit_log (tenant_id,user_id,action,entity_type,entity_id,metadata) VALUES (?,?,?,?,?,?)');
                $audit->execute([$tenantId,$userId,'enrollment.'.$action,'enrollment_block',0,json_encode(['email'=>$email,'reason'=>$action==='block'?$reason:null],JSON_THROW_ON_ERROR)]);
                $db->commit();
                $notice=$action==='block'?'Applicant blocked; unused invitations revoked. Existing accounts remain unchanged.':'Applicant unblocked.';
            } catch(Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
        } elseif ($action === 'revoke' && $canReview) {
            $id=filter_var($_POST['application_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if(!$id) throw new DomainException('Invalid application');
            $db->beginTransaction();
            try {
                $q=$db->prepare("SELECT id FROM enrollment_applications WHERE id=? AND tenant_id=? AND status='approved' FOR UPDATE");
                $q->execute([$id,$tenantId]);
                if(!$q->fetch()) throw new DomainException('Application unavailable');
                $q=$db->prepare('UPDATE enrollment_invitations SET revoked_at=UTC_TIMESTAMP() WHERE tenant_id=? AND application_id=? AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()');
                $q->execute([$tenantId,$id]);
                if($q->rowCount()<1) throw new DomainException('No active invitation to revoke');
                $q=$db->prepare('INSERT INTO audit_log (tenant_id,user_id,action,entity_type,entity_id,metadata) VALUES (?,?,?,?,?,?)');
                $q->execute([$tenantId,$userId,'enrollment.revoke','enrollment_application',$id,json_encode(['reason'=>'manual'],JSON_THROW_ON_ERROR)]);
                $db->commit();
                $notice='Active invitation revoked.';
            } catch(Throwable $e) {
                if($db->inTransaction())$db->rollBack();
                throw $e;
            }
        } elseif ($action === 'invite' && $canReview) {
            $id = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if (!$id) throw new DomainException('Invalid application');
            $db->beginTransaction();
            try {
                $q=$db->prepare("SELECT applicant_type,email,status FROM enrollment_applications WHERE id=? AND tenant_id=? FOR UPDATE");
                $q->execute([$id,$tenantId]);
                $application=$q->fetch(PDO::FETCH_ASSOC);
                if (!$application || $application['status'] !== 'approved') {
                    throw new DomainException('Only approved applications can be invited');
                }
                $q=$db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                $q->execute([$application['email']]);
                if ($q->fetch()) throw new DomainException('An account already uses this email; manual membership review required');
                EnrollmentBlocks::assertAllowed($db,$tenantId,(string)$application['email']);
                $q=$db->prepare('UPDATE enrollment_invitations SET revoked_at=UTC_TIMESTAMP() WHERE tenant_id=? AND application_id=? AND accepted_at IS NULL AND revoked_at IS NULL');
                $q->execute([$tenantId,$id]);
                $rawToken=bin2hex(random_bytes(32));
                $q=$db->prepare('INSERT INTO enrollment_invitations (tenant_id,application_id,token_hash,expires_at,created_by) VALUES (?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 48 HOUR),?)');
                $q->execute([$tenantId,$id,hash('sha256',$rawToken),$userId]);
                $audit=$db->prepare('INSERT INTO audit_log (tenant_id,user_id,action,entity_type,entity_id,metadata) VALUES (?,?,?,?,?,?)');
                $audit->execute([$tenantId,$userId,'enrollment.invite','enrollment_application',$id,json_encode(['expires_hours'=>48],JSON_THROW_ON_ERROR)]);
                $db->commit();
                $invitationUrl = app_url('/enrollment-activate.php') . '?token=' . rawurlencode($rawToken);
                $notice = 'Invitation generated. Copy the private activation link below. It expires in 48 hours.';
                if ((getenv('MAIL_TRANSPORT') ?: '') === 'mailpit') {
                    try {
                        require_once __DIR__ . '/app/LocalMail.php';
                        $activationText = "Welcome to Brite Scheduler!\n\n"
                            . "You're invited to join Brite Scheduler. We're glad to have you here.\n\n"
                            . "GET STARTED\n"
                            . "1. Open your secure activation link below.\n"
                            . "2. Create your password.\n"
                            . "3. Sign in and explore your workspace.\n\n"
                            . "Activate your account:\n" . $invitationUrl . "\n\n"
                            . "This invitation expires in 48 hours. If you weren't expecting it, simply ignore this email.\n\n"
                            . "See you soon,\nThe Brite Scheduler Team\n";
                        $safeActivationUrl = $esc($invitationUrl);
                        $activationHtml = <<<'HTML'
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Welcome to Brite Scheduler</title></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#243247;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">You're invited! Activate your Brite Scheduler account in a few easy steps.</div>
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f3f6fb;">
    <tr><td align="center" style="padding:36px 16px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px;background:#ffffff;border:1px solid #e3e9f2;border-radius:14px;overflow:hidden;">
        <tr><td style="background:#123c63;padding:25px 34px;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:.2px;">Brite <span style="color:#91d9f4;">Scheduler</span></td></tr>
        <tr><td style="padding:38px 34px 30px;">
          <div style="color:#2873a8;font-size:12px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;">You're invited</div>
          <h1 style="font-size:28px;line-height:1.25;color:#142b45;margin:12px 0 18px;">Welcome aboard!</h1>
          <p style="font-size:16px;line-height:1.7;margin:0 0 22px;color:#42536b;">You've been invited to join Brite Scheduler. We're excited to help you get connected and make the most of your workspace.</p>
          <p style="font-size:15px;font-weight:700;margin:0 0 12px;color:#243247;">Getting started is easy:</p>
          <p style="font-size:15px;line-height:1.8;margin:0 0 28px;color:#42536b;">1. Activate your account using the button below.<br>2. Create a secure password.<br>3. Sign in and explore your workspace.</p>
          <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#1666a5" style="border-radius:8px;"><a href="__ACTIVATION_URL__" style="display:inline-block;padding:16px 27px;color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;border-radius:8px;">Activate My Account</a></td></tr></table>
          <p style="font-size:13px;line-height:1.6;color:#68788e;margin:22px 0 0;">For your security, this invitation expires in <strong>48 hours</strong>.</p>
          <hr style="border:0;border-top:1px solid #e5eaf2;margin:28px 0 20px;">
          <p style="font-size:13px;line-height:1.6;color:#68788e;margin:0 0 8px;">Button not working? Copy and paste this secure link into your browser:</p>
          <p style="font-size:12px;line-height:1.7;overflow-wrap:anywhere;word-break:break-word;margin:0;"><a href="__ACTIVATION_URL__" style="color:#1666a5;text-decoration:underline;">__ACTIVATION_URL__</a></p>
          <p style="font-size:14px;line-height:1.6;color:#42536b;margin:28px 0 0;">See you soon,<br><strong>The Brite Scheduler Team</strong></p>
        </td></tr>
      </table>
      <p style="max-width:520px;font-size:12px;line-height:1.6;color:#748399;margin:20px auto 0;">If you weren't expecting this invitation, you can safely ignore this email. No account will be activated unless you use the link.</p>
    </td></tr>
  </table>
</body>
</html>
HTML;
                        $activationHtml = str_replace('__ACTIVATION_URL__', $safeActivationUrl, $activationHtml);
                        LocalMail::send((string)$application['email'], 'Your Brite Scheduler activation invitation', $activationText, $activationHtml);
                        $notice = 'Invitation generated and accepted by the local Mailpit SMTP server. Verify it in the local inbox.';
                    } catch (Throwable $mailError) {
                        // Keep the invitation usable even when local SMTP capture is offline.
                        // Never log or expose the token or SMTP exception.
                        $notice = 'Invitation generated, but local Mailpit delivery failed. Copy the private activation link below instead.';
                    }
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
        } elseif ($action === 'review' && $canReview) {
            $id = filter_var($_POST['application_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $decision = (string)($_POST['decision'] ?? '');
            $note = trim((string)($_POST['decision_note'] ?? ''));
            if (!$id || !in_array($decision,['approved','rejected'],true) || strlen($note)>1000) {
                throw new DomainException('Invalid review request');
            }
            // A decision approves the application for onboarding; it never silently
            // creates an authenticated user or grants tenant membership.
            $db->beginTransaction();
            try {
                if ($decision==='approved') {
                    $check=$db->prepare("SELECT email FROM enrollment_applications WHERE id=? AND tenant_id=? AND status='pending' FOR UPDATE");
                    $check->execute([$id,$tenantId]);
                    $reviewEmail=$check->fetchColumn();
                    if (!$reviewEmail) throw new DomainException('Application unavailable');
                    EnrollmentBlocks::assertAllowed($db,$tenantId,(string)$reviewEmail);
                }
                $q=$db->prepare("UPDATE enrollment_applications SET status=?, reviewed_by=?, reviewed_at=NOW(), decision_note=? WHERE id=? AND tenant_id=? AND status='pending'");
                $q->execute([$decision,$userId,$note ?: null,$id,$tenantId]);
                if ($q->rowCount() !== 1) throw new DomainException('Application already reviewed or unavailable');
                $audit=$db->prepare('INSERT INTO audit_log (tenant_id,user_id,action,entity_type,entity_id,metadata) VALUES (?,?,?,?,?,?)');
                $audit->execute([$tenantId,$userId,'enrollment.review','enrollment_application',$id,json_encode(['decision'=>$decision],JSON_THROW_ON_ERROR)]);
                $notify=$db->prepare("INSERT INTO notifications (tenant_id,user_id,type,title,body) SELECT ?,m.user_id,'enrollment.reviewed','Enrollment decision recorded',? FROM tenant_memberships m WHERE m.tenant_id=? AND m.role IN ('owner','admin') AND m.status='active'");
                $notify->execute([$tenantId,'Application #'.$id.' marked '.$decision.'.',$tenantId]);
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
            $notice = 'Application marked ' . $decision . '. Account onboarding remains a separate step.';
        } else {
            throw new DomainException('Action not permitted');
        }
    } catch (DomainException $e) {
        http_response_code($e->getMessage() === 'Invalid request token' ? 403 : 422);
        $error = $e->getMessage();
    } catch (PDOException $e) {
        http_response_code(500);
        $error = 'Unable to save enrollment. Please retry.';
    }
}
$rows=[];
$blockedEmails=[];
$invitationCounts=['not_sent'=>0,'invited'=>0,'expired'=>0,'revoked'=>0,'activated'=>0];
$counts=['pending'=>0,'approved'=>0,'rejected'=>0];
if ($ready) {
    $q=$db->prepare('SELECT email,reason,blocked_at FROM enrollment_blocks WHERE tenant_id=? AND unblocked_at IS NULL ORDER BY blocked_at DESC LIMIT 100');
    $q->execute([$tenantId]);
    $blockedEmails=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$db->prepare('SELECT status,COUNT(*) AS total FROM enrollment_applications WHERE tenant_id=? GROUP BY status');
    $q->execute([$tenantId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $counts[$r['status']] = (int)$r['total'];
    $invitationSql="SELECT CASE WHEN i.id IS NULL THEN 'not_sent' WHEN i.accepted_at IS NOT NULL THEN 'activated' WHEN i.revoked_at IS NOT NULL THEN 'revoked' WHEN i.expires_at<=UTC_TIMESTAMP() THEN 'expired' ELSE 'invited' END AS state,COUNT(*) AS total FROM enrollment_applications a LEFT JOIN enrollment_invitations i ON i.id=(SELECT i2.id FROM enrollment_invitations i2 WHERE i2.application_id=a.id AND i2.tenant_id=a.tenant_id ORDER BY i2.id DESC LIMIT 1) WHERE a.tenant_id=? GROUP BY state";
    $q=$db->prepare($invitationSql);$q->execute([$tenantId]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $invitationCounts[$r['state']]=(int)$r['total'];
    $search=trim((string)($_GET['q']??''));
    if(strlen($search)>190) $search=substr($search,0,190);
    $invitation=(string)($_GET['invitation']??'all');
    if(!in_array($invitation,['all','not_sent','invited','expired','revoked','activated'],true)) $invitation='all';
    $status=(string)($_GET['status']??'all');
    if (!in_array($status,['all','pending','approved','rejected'],true)) $status='all';
    $type=(string)($_GET['type']??'all');
    if (!in_array($type,['all','contractor','client'],true)) $type='all';
    $sql="SELECT a.id,a.applicant_type,a.full_name,a.email,a.phone,a.notes,a.status,a.decision_note,a.created_at,a.reviewed_at,
      (SELECT b.reason FROM enrollment_blocks b WHERE b.tenant_id=a.tenant_id AND b.email=a.email AND b.unblocked_at IS NULL LIMIT 1) AS block_reason,
      (SELECT CASE WHEN i.accepted_at IS NOT NULL THEN 'activated'
                   WHEN i.revoked_at IS NOT NULL THEN 'revoked'
                   WHEN i.expires_at<=UTC_TIMESTAMP() THEN 'expired'
                   ELSE 'invited' END
       FROM enrollment_invitations i WHERE i.application_id=a.id AND i.tenant_id=a.tenant_id
       ORDER BY i.id DESC LIMIT 1) AS invitation_state
       FROM enrollment_applications a WHERE a.tenant_id=?";

    $params=[$tenantId];
    if ($status!=='all') {$sql.=' AND a.status=?';$params[]=$status;}
    if ($type!=='all') {$sql.=' AND a.applicant_type=?';$params[]=$type;}
    if ($search!=='') {
        $sql.=' AND (a.full_name LIKE ? OR a.email LIKE ? OR a.phone LIKE ?)';
        $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
        $sql=str_replace('a.full_name LIKE ? OR a.email LIKE ? OR a.phone LIKE ?', "a.full_name LIKE ? ESCAPE '!' OR a.email LIKE ? ESCAPE '!' OR a.phone LIKE ? ESCAPE '!'", $sql);
        array_push($params,$pattern,$pattern,$pattern);
    }
    if($invitation==='not_sent') $sql.=' AND NOT EXISTS (SELECT 1 FROM enrollment_invitations ix WHERE ix.tenant_id=a.tenant_id AND ix.application_id=a.id)';
    elseif($invitation!=='all') {
        $stateSql=['invited'=>'ix.accepted_at IS NULL AND ix.revoked_at IS NULL AND ix.expires_at>UTC_TIMESTAMP()', 'expired'=>'ix.accepted_at IS NULL AND ix.revoked_at IS NULL AND ix.expires_at<=UTC_TIMESTAMP()', 'revoked'=>'ix.revoked_at IS NOT NULL', 'activated'=>'ix.accepted_at IS NOT NULL'];
        $sql.=' AND EXISTS (SELECT 1 FROM enrollment_invitations ix WHERE ix.tenant_id=a.tenant_id AND ix.application_id=a.id AND NOT EXISTS (SELECT 1 FROM enrollment_invitations iy WHERE iy.tenant_id=ix.tenant_id AND iy.application_id=ix.application_id AND iy.id>ix.id) AND '.$stateSql[$invitation].')';
    }
    $sql.=' ORDER BY a.created_at DESC,a.id DESC LIMIT 100';
    $q=$db->prepare($sql);$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
}
$pageTitle='Enrollment Management';
require __DIR__ . '/partials/app_header.php';
?>
<section class="card" data-brite-pills="off">
<h1>Enrollment Management</h1>
<p class="muted">Manage contractor and client applications for your business. Approval records a review decision; account creation and invitations remain separate.</p>
<?php if($notice!==''): ?><p role="status" style="color:#166534"><?=$esc($notice)?></p><?php endif; ?>
<?php if(isset($invitationUrl)): ?><p><strong>One-time invitation link:</strong> <input readonly aria-label="Invitation link" value="<?=$esc($invitationUrl)?>" style="width:100%;box-sizing:border-box" onclick="this.select()"></p><p class="muted">This link is shown only now. Share it privately; never post it publicly.</p><?php endif; ?>
<?php if($error!==''): ?><p role="alert" class="danger"><?=$esc($error)?></p><?php endif; ?>
<?php if($ready): ?>
<div class="grid" style="margin:12px 0">
<?php foreach($counts as $label=>$count): ?><div class="card"><strong class="metric"><?=$esc($count)?></strong><div><?=$esc(ucfirst($label))?></div></div><?php endforeach; ?>
</div>
<div class="grid" style="margin:12px 0">
<?php foreach($invitationCounts as $label=>$count): ?><div class="card"><strong class="metric"><?=$esc($count)?></strong><div><?=$esc(ucwords(str_replace('_',' ',$label)))?> invitations</div></div><?php endforeach; ?>
</div>
<?php if($canReview): ?>
<section class="card" style="margin:14px 0">
<h2>Enrollment restrictions</h2>
<p class="muted">Block an email to stop new applications, approvals and invitations in this business. Any unused invitations are revoked. Existing memberships are not suspended. You can also block directly from an application card below.</p>
<form method="post" style="display:flex;gap:9px;flex-wrap:wrap;align-items:end">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="block">
<label>Email <input name="email" type="email" maxlength="190" required></label>
<label>Reason <input name="reason" maxlength="1000" required></label>
<button type="submit" onclick="return confirm('Block this email and revoke unused invitations?')">Block applicant</button>
</form>
<?php if(!$blockedEmails): ?><p class="muted">No blocked applicants.</p><?php endif; ?>
<?php foreach($blockedEmails as $blocked): ?>
<div class="row" style="gap:12px;align-items:center;flex-wrap:wrap;margin:10px 0">
<div><strong><?=$esc($blocked['email'])?></strong><div class="muted"><?=$esc($blocked['reason'])?> · <?=$esc($blocked['blocked_at'])?></div></div>
<form method="post">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="unblock">
<input type="hidden" name="email" value="<?=$esc($blocked['email'])?>">
<button type="submit" onclick="return confirm('Unblock this email?')">Unblock</button>
</form></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:16px">
<section class="card"><h2>New application</h2>
<form method="post" style="display:grid;gap:9px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="submit">
<label>Applicant type <select name="applicant_type" required><option value="contractor">Contractor</option><option value="client">Client</option></select></label>
<label>Full name <input name="full_name" maxlength="190" required></label>
<label>Email <input name="email" type="email" maxlength="190" required></label>
<label>Phone <input name="phone" maxlength="40"></label>
<label>Application notes <textarea name="notes" maxlength="5000" rows="3"></textarea></label>
<button type="submit">Submit application</button>
</form></section>
<section class="card"><h2>Review applications</h2>
<form method="get" class="row" style="align-items:end;flex-wrap:wrap">
<label>Search <input type="search" name="q" maxlength="190" value="<?=$esc($search??'')?>" placeholder="Name, email or phone"></label>
<label>Invitation <select name="invitation"><?php foreach(['all','not_sent','invited','expired','revoked','activated'] as $v): ?><option value="<?=$esc($v)?>" <?=($invitation??'all')===$v?'selected':''?>><?=$esc(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach; ?></select></label>
<label>Status <select name="status"><?php foreach(['all','pending','approved','rejected'] as $v): ?><option value="<?=$esc($v)?>" <?=($status??'all')===$v?'selected':''?>><?=$esc(ucfirst($v))?></option><?php endforeach; ?></select></label>
<label>Type <select name="type"><?php foreach(['all','contractor','client'] as $v): ?><option value="<?=$esc($v)?>" <?=($type??'all')===$v?'selected':''?>><?=$esc(ucfirst($v))?></option><?php endforeach; ?></select></label>
<button type="submit">Filter</button>
<a href="enrollment.php">Clear filters</a>
</form>
<p class="muted">Showing up to 100 most recent matching applications.</p>
<?php if(!$rows): ?><p>No applications match these filters.</p><?php endif; ?>
<?php foreach($rows as $item): ?>
<article class="card" style="margin:12px 0;padding:14px;overflow-wrap:anywhere" aria-label="Enrollment application #<?=$esc($item['id'])?>">
<div class="row" style="align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px"><strong><?=$esc($item['full_name'])?></strong><small class="muted">Application #<?=$esc($item['id'])?></small></div>
<span class="muted"> · <?=$esc(ucfirst($item['applicant_type']))?> · <?=$esc(ucfirst($item['status']))?><?php if($item['invitation_state']): ?> · <?= $esc(ucfirst($item['invitation_state'])) ?><?php endif; ?></span>
<?php if($item['block_reason']!==null): ?><p role="status" class="danger" style="margin:8px 0"><strong>Enrollment blocked</strong> — <?=$esc($item['block_reason'])?>. Approval and invitations are unavailable until unblocked.</p><?php endif; ?>
<div><a href="mailto:<?=$esc($item['email'])?>"><?=$esc($item['email'])?></a><?php if($item['phone']): ?> · <?=$esc($item['phone'])?><?php endif; ?></div>
<small class="muted">Submitted <?=$esc($item['created_at'])?><?php if($item['reviewed_at']): ?> · Reviewed <?=$esc($item['reviewed_at'])?><?php endif; ?></small>
<?php if($item['notes']): ?><p><?=$esc($item['notes'])?></p><?php endif; ?>
<?php if($item['decision_note']): ?><p><strong>Review note:</strong> <?=$esc($item['decision_note'])?></p><?php endif; ?>
<?php if($canReview): ?>
<details style="margin:10px 0">
<summary style="cursor:pointer;font-weight:600"><?=$item['block_reason']!==null?'Manage enrollment restriction':'Block this applicant'?></summary>
<?php if($item['block_reason']!==null): ?>
<p class="muted">Unblocking allows future enrollment actions. It does not restore revoked invitations or change review decisions.</p>
<form method="post"><input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>"><input type="hidden" name="action" value="unblock"><input type="hidden" name="email" value="<?=$esc($item['email'])?>"><button type="submit" onclick="return confirm('Unblock this email for this business?')">Unblock applicant</button></form>
<?php else: ?>
<form method="post" style="display:grid;gap:8px;margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>"><input type="hidden" name="action" value="block"><input type="hidden" name="email" value="<?=$esc($item['email'])?>">
<p class="muted">Blocking <?=$esc($item['email'])?> will revoke unused invitations, but will not suspend an existing account.</p>
<label>Reason for block <input name="reason" maxlength="1000" required placeholder="Explain why enrollment is restricted"></label>
<div><button type="submit" onclick="return confirm('Block this email and revoke unused invitations?')">Block applicant</button></div>
</form>
<?php endif; ?>
</details>
<?php endif; ?>
<?php if($canReview && $item['block_reason']===null && $item['status']==='approved' && $item['invitation_state']==='invited'): ?>
<form method="post" style="margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="revoke">
<input type="hidden" name="application_id" value="<?=$esc($item['id'])?>">
<button type="submit" onclick="return confirm('Revoke the current activation invitation?')">Revoke active invitation</button>
</form>
<?php endif; ?>
<?php if($canReview && $item['block_reason']===null && $item['status']==='approved'): ?>
<form method="post" style="margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="invite">
<input type="hidden" name="application_id" value="<?=$esc($item['id'])?>">
<button type="submit" onclick="return confirm('Generate a new private activation link? Any previous unused link will be revoked.')"><?=$item['invitation_state']==='invited'?'Replace active invitation':'Generate activation invitation'?></button>
</form>
<?php endif; ?>
<?php if($canReview && $item['status']==='pending'): ?>
<form method="post" style="display:grid;gap:8px;margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="review">
<input type="hidden" name="application_id" value="<?=$esc($item['id'])?>">
<label>Review note <input name="decision_note" maxlength="1000"></label>
<div class="row" style="gap:8px;flex-wrap:wrap"><button type="submit" name="decision" value="approved" <?=$item['block_reason']!==null?'disabled title="Unblock this email before approving"':''?> onclick="return confirm('Approve this application? This does not create an account.')">Approve application</button><button type="submit" name="decision" value="rejected" onclick="return confirm('Reject this application? This decision cannot be reversed here.')">Reject application</button></div>
</form>
<?php endif; ?>
</article>
<?php endforeach; ?>
</section></div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/partials/app_footer.php'; ?>
