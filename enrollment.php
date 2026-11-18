<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
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
} catch (PDOException $e) {
    $ready = false;
    $error = 'Enrollment database setup required. Apply migration 039_enrollment_management.sql.';
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
            $q=$db->prepare('INSERT INTO enrollment_applications (tenant_id,applicant_type,full_name,email,phone,notes,submitted_by) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$tenantId,$type,$name,$email,$phone ?: null,$notes ?: null,$userId]);
            $notice = 'Application submitted for review.';
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
                $q=$db->prepare("UPDATE enrollment_applications SET status=?, reviewed_by=?, reviewed_at=NOW(), decision_note=? WHERE id=? AND tenant_id=? AND status='pending'");
                $q->execute([$decision,$userId,$note ?: null,$id,$tenantId]);
                if ($q->rowCount() !== 1) throw new DomainException('Application already reviewed or unavailable');
                $audit=$db->prepare('INSERT INTO audit_log (tenant_id,user_id,action,entity_type,entity_id,metadata) VALUES (?,?,?,?,?,?)');
                $audit->execute([$tenantId,$userId,'enrollment.review','enrollment_application',$id,json_encode(['decision'=>$decision],JSON_THROW_ON_ERROR)]);
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
$counts=['pending'=>0,'approved'=>0,'rejected'=>0];
if ($ready) {
    $q=$db->prepare('SELECT status,COUNT(*) AS total FROM enrollment_applications WHERE tenant_id=? GROUP BY status');
    $q->execute([$tenantId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $counts[$r['status']] = (int)$r['total'];
    $status=(string)($_GET['status']??'all');
    if (!in_array($status,['all','pending','approved','rejected'],true)) $status='all';
    $type=(string)($_GET['type']??'all');
    if (!in_array($type,['all','contractor','client'],true)) $type='all';
    $sql='SELECT id,applicant_type,full_name,email,phone,notes,status,decision_note,created_at,reviewed_at FROM enrollment_applications WHERE tenant_id=?';
    $params=[$tenantId];
    if ($status!=='all') {$sql.=' AND status=?';$params[]=$status;}
    if ($type!=='all') {$sql.=' AND applicant_type=?';$params[]=$type;}
    $sql.=' ORDER BY created_at DESC,id DESC LIMIT 100';
    $q=$db->prepare($sql);$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
}
$pageTitle='Enrollment Management';
require __DIR__ . '/partials/app_header.php';
?>
<section class="card" data-brite-pills="off">
<h1>Enrollment Management</h1>
<p class="muted">Manage contractor and client applications for your business. Approval records a review decision; account creation and invitations remain separate.</p>
<?php if($notice!==''): ?><p role="status" style="color:#166534"><?=$esc($notice)?></p><?php endif; ?>
<?php if($error!==''): ?><p role="alert" class="danger"><?=$esc($error)?></p><?php endif; ?>
<?php if($ready): ?>
<div class="grid" style="margin:12px 0">
<?php foreach($counts as $label=>$count): ?><div class="card"><strong class="metric"><?=$esc($count)?></strong><div><?=$esc(ucfirst($label))?></div></div><?php endforeach; ?>
</div>
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
<section class="card"><h2>Application queue</h2>
<form method="get" class="row" style="align-items:end">
<label>Status <select name="status"><?php foreach(['all','pending','approved','rejected'] as $v): ?><option value="<?=$esc($v)?>" <?=($status??'all')===$v?'selected':''?>><?=$esc(ucfirst($v))?></option><?php endforeach; ?></select></label>
<label>Type <select name="type"><?php foreach(['all','contractor','client'] as $v): ?><option value="<?=$esc($v)?>" <?=($type??'all')===$v?'selected':''?>><?=$esc(ucfirst($v))?></option><?php endforeach; ?></select></label>
<button type="submit">Filter</button>
</form>
<p class="muted">Showing up to 100 most recent matching applications.</p>
<?php if(!$rows): ?><p>No applications match these filters.</p><?php endif; ?>
<?php foreach($rows as $item): ?>
<article style="border-top:1px solid #cbd5e1;padding:12px 0;overflow-wrap:anywhere">
<strong><?=$esc($item['full_name'])?></strong>
<span class="muted"> · <?=$esc(ucfirst($item['applicant_type']))?> · <?=$esc(ucfirst($item['status']))?></span>
<div><a href="mailto:<?=$esc($item['email'])?>"><?=$esc($item['email'])?></a><?php if($item['phone']): ?> · <?=$esc($item['phone'])?><?php endif; ?></div>
<small class="muted">Submitted <?=$esc($item['created_at'])?></small>
<?php if($item['notes']): ?><p><?=$esc($item['notes'])?></p><?php endif; ?>
<?php if($item['decision_note']): ?><p><strong>Review note:</strong> <?=$esc($item['decision_note'])?></p><?php endif; ?>
<?php if($canReview && $item['status']==='pending'): ?>
<form method="post" style="display:grid;gap:8px;margin-top:8px">
<input type="hidden" name="_csrf" value="<?=$esc(csrf_token())?>">
<input type="hidden" name="action" value="review">
<input type="hidden" name="application_id" value="<?=$esc($item['id'])?>">
<label>Review note <input name="decision_note" maxlength="1000"></label>
<div class="row"><button type="submit" name="decision" value="approved">Approve</button><button type="submit" name="decision" value="rejected">Reject</button></div>
</form>
<?php endif; ?>
</article>
<?php endforeach; ?>
</section></div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/partials/app_footer.php'; ?>
