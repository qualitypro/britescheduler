<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
$user = Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin');
$tenantId = (int)$tenant['tenant_id'];
$pageTitle = 'Enrollment';

$filter = (string)($_GET['status'] ?? 'pending_review');
$allowed = ['pending_email', 'pending_review', 'action_required', 'approved', 'rejected', 'withdrawn', 'all'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'pending_review';
}
$rows = [];
$counts = [];
$unavailable = false;
try {
    $pdo = Database::connection();
    $countQuery = $pdo->prepare(
        'SELECT status, COUNT(*) AS total FROM registration_applications WHERE tenant_id = ? GROUP BY status'
    );
    $countQuery->execute([$tenantId]);
    foreach ($countQuery->fetchAll() as $count) {
        $counts[$count['status']] = (int)$count['total'];
    }
    if ($filter === 'all') {
        $query = $pdo->prepare(
            'SELECT id, applicant_type, first_name, last_name, organization_name, email, status, email_verified_at, submitted_at
             FROM registration_applications WHERE tenant_id = ? ORDER BY submitted_at DESC, id DESC LIMIT 100'
        );
        $query->execute([$tenantId]);
    } else {
        $query = $pdo->prepare(
            'SELECT id, applicant_type, first_name, last_name, organization_name, email, status, email_verified_at, submitted_at
             FROM registration_applications WHERE tenant_id = ? AND status = ? ORDER BY submitted_at DESC, id DESC LIMIT 100'
        );
        $query->execute([$tenantId, $filter]);
    }
    $rows = $query->fetchAll();
} catch (PDOException $exception) {
    // Schema may not yet have been applied. Do not expose SQL or credentials.
    error_log('Enrollment query unavailable: ' . $exception->getCode());
    $unavailable = true;
}
$escape = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require __DIR__ . '/partials/app_header.php';
?>
  <div class="wrap">
    <header><h1>Enrollment</h1><p class="muted">Review new user registrations and verification status for your business.</p></header>
    <?php if ($unavailable): ?>
      <div class="card" role="alert"><strong>Enrollment is not available yet.</strong>
      <p>Apply migration 038 in the local test environment before using the queue. No registrations were changed.</p></div>
    <?php else: ?>
      <div class="grid" aria-label="Enrollment summary">
        <div class="card"><strong>Awaiting review</strong><div class="metric"><?= (int)($counts['pending_review'] ?? 0) ?></div></div>
        <div class="card"><strong>Verification pending</strong><div class="metric"><?= (int)($counts['pending_email'] ?? 0) ?></div></div>
        <div class="card"><strong>Action required</strong><div class="metric"><?= (int)($counts['action_required'] ?? 0) ?></div></div>
      </div>
      <form method="get" class="row" style="margin-top:20px">
        <label for="enrollment-status">Status</label>
        <select name="status" id="enrollment-status">
          <?php foreach ($allowed as $option): ?>
            <option value="<?= $escape($option) ?>" <?= $option === $filter ? 'selected' : '' ?>><?= $escape(ucwords(str_replace('_', ' ', $option))) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
      </form>
      <div class="card" style="overflow-x:auto">
        <table>
          <thead><tr><th>Applicant</th><th>Type</th><th>Email</th><th>Verification</th><th>Status</th><th>Submitted</th><th>Review</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $application): ?>
              <tr>
                <td><?= $escape(trim($application['first_name'] . ' ' . $application['last_name'])) ?><?php if (!empty($application['organization_name'])): ?><small class="muted"> · <?= $escape($application['organization_name']) ?></small><?php endif; ?></td>
                <td><?= $escape(ucfirst($application['applicant_type'])) ?></td>
                <td><?= $escape($application['email']) ?></td>
                <td><?= $application['email_verified_at'] ? 'Email verified' : 'Email pending' ?></td>
                <td><?= $escape(ucwords(str_replace('_', ' ', $application['status']))) ?></td>
                <td><?= $escape($application['submitted_at']) ?></td>
                <td><?php if ($application['status'] === 'pending_review'): ?>
                  <form class="enrollment-decision" method="post" action="<?= $escape(app_url('/api/enrollment_decision.php')) ?>">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="application_id" value="<?= (int)$application['id'] ?>">
                    <label>Reason <input name="reason" minlength="5" maxlength="500" required placeholder="Reason for decision" aria-label="Review reason for application <?= (int)$application['id'] ?>"></label>
                    <button type="submit" name="action" value="request_info">Request info</button>
                    <button type="submit" name="action" value="reject">Reject</button>
                  </form>
                <?php else: ?>—<?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7">No enrollment applications match this filter.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <p class="muted">Request-info and rejection decisions are recorded in the audit trail. Notifications, approval, verification email delivery, and provisioning are not enabled yet.</p>
    <?php endif; ?>
  </div>
<script>
document.querySelectorAll('.enrollment-decision').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const action = event.submitter?.value;
    if (!['request_info', 'reject'].includes(action)) return;
    if (!form.reportValidity()) return;
    if (!confirm(action === 'reject' ? 'Reject this enrollment application?' : 'Request additional information? No email will be sent automatically.')) return;
    const payload = new FormData(form);
    payload.set('action', action);
    const buttons = form.querySelectorAll('button');
    buttons.forEach(button => button.disabled = true);
    try {
      const response = await fetch(form.action, {method:'POST', credentials:'same-origin', body:payload, headers:{Accept:'application/json'}});
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Decision failed');
      location.reload();
    } catch (error) {
      alert(error.message || 'Unable to process decision');
      buttons.forEach(button => button.disabled = false);
    }
  });
});
</script>
<?php require __DIR__ . '/partials/app_footer.php'; ?>
