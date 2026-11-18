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
<style>
.enrollment-shell{--en-line:var(--border-color,#d6e0e9);--en-accent:var(--primary,#236b9c);font-size:.94rem}
.enrollment-shell .en-icon{width:1em;height:1em;vertical-align:-.13em;flex:none}
.enrollment-shell .en-heading{display:flex;align-items:center;gap:.5rem;margin:0 0 .35rem}
.enrollment-shell .en-intro{margin:.15rem 0 .8rem}
.enrollment-shell .en-stats{display:flex;gap:.45rem;flex-wrap:wrap;margin:.65rem 0}
.enrollment-shell .en-stat{display:flex;align-items:center;gap:.45rem;border:1px solid var(--en-line);border-radius:8px;padding:.4rem .65rem;min-width:100px}
.enrollment-shell .en-stat strong{font-size:1.1rem}
.enrollment-shell .en-panel{padding:.75rem;margin:.7rem 0;border:1px solid var(--en-line);border-radius:10px}
.enrollment-shell .en-panel>summary{cursor:pointer;font-weight:650;list-style-position:inside}
.enrollment-shell .en-panel>summary .en-icon{margin-right:.35rem}
.enrollment-shell .en-toolbar{display:flex;flex-wrap:wrap;align-items:end;gap:.5rem;margin:.55rem 0}
.enrollment-shell .en-toolbar label{display:grid;gap:.2rem;font-size:.85rem;flex:1 1 130px;min-width:115px}
.enrollment-shell input,.enrollment-shell select,.enrollment-shell textarea{max-width:100%;box-sizing:border-box}
.enrollment-shell .en-toolbar input,.enrollment-shell .en-toolbar select{width:100%}
.enrollment-shell .en-btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;min-height:34px;padding:.35rem .65rem}
.enrollment-shell .en-btn[disabled]{opacity:.5;cursor:not-allowed}
.enrollment-shell .en-card{padding:.75rem;margin:.5rem 0;border:1px solid var(--en-line);border-radius:10px;overflow-wrap:anywhere}
.enrollment-shell .en-card-head{display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap}
.enrollment-shell .en-badges{display:flex;flex-wrap:wrap;gap:.3rem;margin:.35rem 0}
.enrollment-shell .en-badge{font-size:.76rem;border:1px solid var(--en-line);border-radius:20px;padding:.13rem .5rem;display:inline-flex;gap:.25rem;align-items:center}
.enrollment-shell .en-badge.is-blocked{border-color:#b45353;color:#b45353}
.enrollment-shell .en-card-meta{font-size:.82rem;display:flex;flex-wrap:wrap;gap:.6rem}
.enrollment-shell .en-actions{display:flex;flex-wrap:wrap;gap:.4rem;margin:.5rem 0}
.enrollment-shell .en-actions form{margin:0!important}
.enrollment-shell .en-card details{margin:.4rem 0}
.enrollment-shell .en-card details>summary{cursor:pointer}
.enrollment-shell .en-card p{margin:.4rem 0}
.enrollment-shell .en-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.55rem}
.enrollment-shell .en-form-grid label{display:grid;gap:.2rem}
.enrollment-shell .en-form-grid .en-wide{grid-column:1/-1}
.enrollment-shell .en-empty{padding:1rem;text-align:center}
@media(max-width:650px){.enrollment-shell .en-stat{flex:1 1 40%}.enrollment-shell .en-toolbar label{flex:1 1 45%}.enrollment-shell .en-actions button{width:100%}}
</style>
<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true"><symbol id="en-icon-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></symbol><symbol id="en-icon-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></symbol><symbol id="en-icon-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></symbol><symbol id="en-icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></symbol><symbol id="en-icon-filter" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M7 12h10M10 17h4"/></symbol><symbol id="en-icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></symbol><symbol id="en-icon-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></symbol><symbol id="en-icon-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></symbol><symbol id="en-icon-ban" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/></symbol><symbol id="en-icon-unlock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="11" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/></symbol><symbol id="en-icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></symbol><symbol id="en-icon-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 0-14-5L4 8M4 4v4h4M4 13a8 8 0 0 0 14 5l2-2m0 4v-4h-4"/></symbol><symbol id="en-icon-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></symbol><symbol id="en-icon-file" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></symbol><symbol id="en-icon-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 1 1 8 0v4"/></symbol><symbol id="en-icon-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/></symbol><symbol id="en-icon-info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 11v6M12 7h.01"/></symbol><symbol id="en-icon-clipboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4.5V2h6v2.5"/></symbol><symbol id="en-icon-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.6 1.8L7 10a16 16 0 0 0 7 7l1.5-2a2 2 0 0 1 1.8-.6l3 .5a2 2 0 0 1 1.7 2Z"/></symbol></svg>
<section class="card enrollment-shell" data-brite-pills="off">
<h1 class="en-heading"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-users"></use></svg> Enrollment Management</h1>
<p class="muted en-intro">Review applications, invite approved applicants, and manage enrollment restrictions. Approval alone does not create an account.</p>
<?php if($notice!==''): ?><p role="status" style="color:#166534"><?=$esc($notice)?></p><?php endif; ?>
<?php if(isset($invitationUrl)): ?><p><strong>One-time invitation link:</strong> <input readonly aria-label="Invitation link" value="<?=$esc($invitationUrl)?>" style="width:100%;box-sizing:border-box" onclick="this.select()"></p><p class="muted">This link is shown only now. Share it privately; never post it publicly.</p><?php endif; ?>
<?php if($error!==''): ?><p role="alert" class="danger"><?=$esc($error)?></p><?php endif; ?>
<?php if($ready): ?>
<div class="en-stats" aria-label="Application summary">
<?php foreach($counts as $label=>$count): ?><div class="en-stat"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-file"></use></svg><strong><?=$esc($count)?></strong><span><?=$esc(ucfirst($label))?></span></div><?php endforeach; ?>
<?php foreach($invitationCounts as $label=>$count): ?><div class="en-stat"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-mail"></use></svg><strong><?=$esc($count)?></strong><span><?=$esc(ucwords(str_replace('_',' ',$label)))?> invites</span></div><?php endforeach; ?>
</div>
<?php if($canReview): ?>
<details class="en-panel" id="en-restrictions"><summary><svg class="en-icon" aria-hidden="true"><use href="#en-icon-shield"></use></svg> Enrollment restrictions (<?=count($blockedEmails)?> active)</summary>
<p class="muted">Block an email to stop new applications, approvals and invitations in this business. Any unused invitations are revoked. Existing memberships are not suspended. You can also block directly from an application card below.</p>
<form method="post" style="display:flex;gap:9px;flex-wrap:wrap;align-items:end">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="block">
<label>Email <input name="email" type="email" maxlength="190" required></label>
<label>Reason <input name="reason" maxlength="1000" required></label>
<button type="submit" onclick="return confirm('Block this email and revoke unused invitations?')"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-ban"></use></svg> Block applicant</button>
</form>
<?php if(!$blockedEmails): ?><p class="muted">No blocked applicants.</p><?php endif; ?>
<?php foreach($blockedEmails as $blocked): ?>
<div class="row" style="gap:12px;align-items:center;flex-wrap:wrap;margin:10px 0">
<div><strong><?=$esc($blocked['email'])?></strong><div class="muted"><?=$esc($blocked['reason'])?> · <?=$esc($blocked['blocked_at'])?></div></div>
<form method="post">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="unblock">
<input type="hidden" name="email" value="<?=$esc($blocked['email'])?>">
<button type="submit" onclick="return confirm('Unblock this email?')"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-unlock"></use></svg> Unblock</button>
</form></div>
<?php endforeach; ?>
</details>
<?php endif; ?>
<details class="en-panel" id="en-new"><summary><svg class="en-icon" aria-hidden="true"><use href="#en-icon-plus"></use></svg> New application</summary>
<form method="post" class="en-form-grid" style="margin-top:.6rem">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="submit">
<label>Applicant type <select name="applicant_type" required><option value="contractor">Contractor</option><option value="client">Client</option></select></label>
<label>Full name <input name="full_name" maxlength="190" required></label>
<label>Email <input name="email" type="email" maxlength="190" required></label>
<label>Phone <input name="phone" maxlength="40"></label>
<label class="en-wide">Application notes <textarea name="notes" maxlength="5000" rows="2"></textarea></label>
<div class="en-wide"><button type="submit" class="en-btn"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-send"></use></svg> Submit application</button></div>
</form></details>
<section class="en-panel"><h2 class="en-heading"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-clipboard"></use></svg> Review applications</h2>
<form method="get" class="en-toolbar">
<label>Search <input type="search" name="q" maxlength="190" value="<?=$esc($search??'')?>" placeholder="Name, email or phone"></label>
<label>Invitation <select name="invitation"><?php foreach(['all','not_sent','invited','expired','revoked','activated'] as $v): ?><option value="<?=$esc($v)?>" <?=($invitation??'all')===$v?'selected':''?>><?=$esc(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach; ?></select></label>
<label>Status <select name="status"><?php foreach(['all','pending','approved','rejected'] as $v): ?><option value="<?=$esc($v)?>" <?=($status??'all')===$v?'selected':''?>><?=$esc(ucfirst($v))?></option><?php endforeach; ?></select></label>
<label>Type <select name="type"><?php foreach(['all','contractor','client'] as $v): ?><option value="<?=$esc($v)?>" <?=($type??'all')===$v?'selected':''?>><?=$esc(ucfirst($v))?></option><?php endforeach; ?></select></label>
<button type="submit" class="en-btn"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-filter"></use></svg> Apply filters</button>
<a href="enrollment.php" class="en-btn"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-refresh"></use></svg> Reset</a>
</form>
<p class="muted" style="font-size:.8rem">Showing <?=count($rows)?> of up to 100 matching applications. Select an applicant to see available actions.</p>
<?php if(!$rows): ?><p>No applications match these filters.</p><?php endif; ?>
<?php foreach($rows as $item): ?>
<article class="en-card" aria-label="Enrollment application #<?=$esc($item['id'])?>">
<div class="en-card-head"><strong><svg class="en-icon" aria-hidden="true"><use href="#en-icon-user"></use></svg> <?=$esc($item['full_name'])?></strong><small class="muted">#<?=$esc($item['id'])?></small></div>
<div class="en-badges"><span class="en-badge"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-users"></use></svg> <?=$esc(ucfirst($item['applicant_type']))?></span><span class="en-badge"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-file"></use></svg> <?=$esc(ucfirst($item['status']))?></span><?php if($item['invitation_state']): ?><span class="en-badge"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-mail"></use></svg> <?=$esc(ucfirst($item['invitation_state']))?></span><?php endif; ?><?php if($item['block_reason']!==null): ?><span class="en-badge is-blocked"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-ban"></use></svg> Blocked</span><?php endif; ?></div>
<?php if($item['block_reason']!==null): ?><p role="status" class="danger" style="margin:8px 0"><strong>Enrollment blocked</strong> — <?=$esc($item['block_reason'])?>. Approval and invitations are unavailable until unblocked.</p><?php endif; ?>
<div class="en-card-meta"><span><svg class="en-icon" aria-hidden="true"><use href="#en-icon-mail"></use></svg> <a href="mailto:<?=$esc($item['email'])?>"><?=$esc($item['email'])?></a></span><?php if($item['phone']): ?><span><svg class="en-icon" aria-hidden="true"><use href="#en-icon-phone"></use></svg> <?=$esc($item['phone'])?></span><?php endif; ?></div>
<div class="muted en-card-meta"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-clock"></use></svg> Submitted <?=$esc($item['created_at'])?><?php if($item['reviewed_at']): ?> · Reviewed <?=$esc($item['reviewed_at'])?><?php endif; ?></div>
<?php if($item['notes']): ?><details><summary><svg class="en-icon" aria-hidden="true"><use href="#en-icon-info"></use></svg> Applicant notes</summary><p><?=$esc($item['notes'])?></p></details><?php endif; ?>
<?php if($item['decision_note']): ?><p><strong>Review note:</strong> <?=$esc($item['decision_note'])?></p><?php endif; ?>
<?php if($canReview): ?>
<details style="margin:10px 0">
<summary><svg class="en-icon" aria-hidden="true"><use href="#en-icon-shield"></use></svg> <?=$item['block_reason']!==null?'Manage restriction':'Block applicant'?></summary>
<?php if($item['block_reason']!==null): ?>
<p class="muted">Unblocking allows future enrollment actions. It does not restore revoked invitations or change review decisions.</p>
<form method="post"><input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>"><input type="hidden" name="action" value="unblock"><input type="hidden" name="email" value="<?=$esc($item['email'])?>"><button type="submit" onclick="return confirm('Unblock this email for this business?')"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-unlock"></use></svg> Unblock applicant</button></form>
<?php else: ?>
<form method="post" style="display:grid;gap:8px;margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>"><input type="hidden" name="action" value="block"><input type="hidden" name="email" value="<?=$esc($item['email'])?>">
<p class="muted">Blocking <?=$esc($item['email'])?> will revoke unused invitations, but will not suspend an existing account.</p>
<label>Reason for block <input name="reason" maxlength="1000" required placeholder="Explain why enrollment is restricted"></label>
<div><button type="submit" onclick="return confirm('Block this email and revoke unused invitations?')"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-ban"></use></svg> Block applicant</button></div>
</form>
<?php endif; ?>
</details>
<?php endif; ?>
<?php if($canReview && $item['block_reason']===null && $item['status']==='approved' && $item['invitation_state']==='invited'): ?>
<form method="post" style="margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="revoke">
<input type="hidden" name="application_id" value="<?=$esc($item['id'])?>">
<button type="submit" onclick="return confirm('Revoke the current activation invitation?')"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-lock"></use></svg> Revoke active invitation</button>
</form>
<?php endif; ?>
<?php if($canReview && $item['block_reason']===null && $item['status']==='approved'): ?>
<form method="post" style="margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="invite">
<input type="hidden" name="application_id" value="<?=$esc($item['id'])?>">
<button type="submit" onclick="return confirm('Generate a new private activation link? Any previous unused link will be revoked.')"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-send"></use></svg> <?=$item['invitation_state']==='invited'?'Replace invitation':'Send invitation'?></button>
</form>
<?php endif; ?>
<?php if($canReview && $item['status']==='pending'): ?>
<form method="post" style="display:grid;gap:8px;margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="review">
<input type="hidden" name="application_id" value="<?=$esc($item['id'])?>">
<label>Review note <input name="decision_note" maxlength="1000"></label>
<div class="en-actions"><button type="submit" class="en-btn" name="decision" value="approved" <?=$item['block_reason']!==null?'disabled title="Unblock this email before approving"':''?> onclick="return confirm('Approve this application? This does not create an account.')"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-check"></use></svg> Approve application</button><button type="submit" class="en-btn" name="decision" value="rejected" onclick="return confirm('Reject this application? This decision cannot be reversed here.')"><svg class="en-icon" aria-hidden="true"><use href="#en-icon-x"></use></svg> Reject application</button></div>
</form>
<?php endif; ?>
</article>
<?php endforeach; ?>
</section>
<?php endif; ?>
</section>
<?php require __DIR__ . '/partials/app_footer.php'; ?>
