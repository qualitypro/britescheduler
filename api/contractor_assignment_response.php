<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/TenantClock.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('contractor');

require_method('POST');
verify_csrf();

$tid = (int)$tenant['tenant_id'];
$uid = (int)$user['id'];

$data = request_data();

if (!is_array($data)) {
    json_response(['error' => 'Invalid request body'], 422);
}

$appointmentId = filter_var(
    $data['appointment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$action = (string)($data['action'] ?? '');

if (!$appointmentId) {
    json_response(['error' => 'Valid appointment_id required'], 422);
}

if (!in_array($action, ['accept', 'decline'], true)) {
    json_response(['error' => 'Invalid assignment action'], 422);
}

$targetStatus = $action === 'accept'
    ? 'accepted'
    : 'declined';

$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    /*
     * Resolve the authenticated contractor's active profile.
     */
    $q = $pdo->prepare("
        SELECT c.id
        FROM contractors c
        JOIN tenant_memberships tm
          ON tm.tenant_id = c.tenant_id
         AND tm.user_id = c.user_id
         AND tm.role = 'contractor'
         AND tm.status = 'active'
        JOIN users u
          ON u.id = c.user_id
         AND u.status = 'active'
        WHERE c.tenant_id = ?
          AND c.user_id = ?
          AND c.status = 'active'
        LIMIT 1
    ");

    $q->execute([$tid, $uid]);

    $contractorId = (int)($q->fetchColumn() ?: 0);

    if (!$contractorId) {
        $pdo->rollBack();
        json_response([
            'error' => 'Active contractor profile required'
        ], 403);
    }

    /*
     * Lock the assignment and verify tenant ownership.
     */
    $q = $pdo->prepare("
        SELECT
            ac.status,
            a.status AS appointment_status,
            a.starts_at,
            (a.starts_at > ?) AS is_future
        FROM appointment_contractors ac
        JOIN appointments a
          ON a.id = ac.appointment_id
         AND a.tenant_id = ?
        WHERE ac.appointment_id = ?
          AND ac.contractor_id = ?
        FOR UPDATE
    ");

    $q->execute([
        TenantClock::now($pdo, $tid),
        $tid,
        $appointmentId,
        $contractorId
    ]);

    $assignment = $q->fetch(PDO::FETCH_ASSOC);

    if ($assignment === false) {
        $pdo->rollBack();
        json_response([
            'error' => 'Assignment not found'
        ], 404);
    }

    /*
     * Repeating the same completed decision is idempotent.
     */
    $currentStatus = (string)$assignment['status'];

    if ($currentStatus === $targetStatus) {
        $pdo->commit();

        json_response([
            'ok' => true,
            'appointment_id' => (int)$appointmentId,
            'contractor_id' => $contractorId,
            'status' => $targetStatus,
            'changed' => false
        ]);
    }

    /*
     * Only a newly assigned appointment can be accepted
     * or declined.
     */
    if ($currentStatus !== 'assigned') {
        $pdo->rollBack();

        json_response([
            'error' => 'Assignment cannot be changed in its current state',
            'status' => $currentStatus
        ], 409);
    }

    /*
     * Eligibility applies only to new decisions.
     * Previously recorded decisions remain idempotent.
     */
    $appointmentStatus = (string)$assignment['appointment_status'];

    if (
        !in_array(
            $appointmentStatus,
            ['tentative', 'scheduled', 'confirmed'],
            true
        ) ||
        (int)$assignment['is_future'] !== 1
    ) {
        $pdo->rollBack();

        json_response([
            'error' => 'Appointment is not eligible for assignment response',
            'appointment_status' => $appointmentStatus
        ], 409);
    }

    $q = $pdo->prepare("
        UPDATE appointment_contractors
        SET status = ?
        WHERE appointment_id = ?
          AND contractor_id = ?
          AND status = 'assigned'
    ");

    $q->execute([
        $targetStatus,
        $appointmentId,
        $contractorId
    ]);

    if ($q->rowCount() !== 1) {
        throw new RuntimeException(
            'Assignment state changed during update'
        );
    }

    /*
     * Notify active Owners of the contractor's decision.
     */
    $q = $pdo->prepare("
        SELECT tm.user_id
        FROM tenant_memberships tm
        JOIN users u
          ON u.id = tm.user_id
        WHERE tm.tenant_id = ?
          AND tm.role = 'owner'
          AND tm.status = 'active'
          AND u.status = 'active'
    ");

    $q->execute([$tid]);

    $ownerIds = $q->fetchAll(PDO::FETCH_COLUMN);

    $notification = $pdo->prepare("
        INSERT INTO notifications (
            tenant_id,
            user_id,
            appointment_id,
            type,
            title,
            body
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $notificationType = $action === 'accept'
        ? 'contractor_assignment_accepted'
        : 'contractor_assignment_declined';

    $notificationTitle = $action === 'accept'
        ? 'Contractor accepted assignment'
        : 'Contractor declined assignment';

    $notificationBody = sprintf(
        'Contractor #%d %s appointment #%d.',
        $contractorId,
        $action === 'accept' ? 'accepted' : 'declined',
        $appointmentId
    );

    foreach ($ownerIds as $ownerId) {
        $notification->execute([
            $tid,
            (int)$ownerId,
            $appointmentId,
            $notificationType,
            $notificationTitle,
            $notificationBody
        ]);
    }

    /*
     * Record the contractor decision in the tenant audit log.
     *
     * This insert is part of the same transaction as the
     * assignment status change and Owner notifications.
     */
    $audit = $pdo->prepare("
        INSERT INTO audit_log (
            tenant_id,
            user_id,
            action,
            entity_type,
            entity_id,
            metadata
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $auditAction = $action === 'accept'
        ? 'assignment.accepted'
        : 'assignment.declined';

    $auditMetadata = json_encode([
        'appointment_id' => (int)$appointmentId,
        'contractor_id' => $contractorId,
        'previous_status' => 'assigned',
        'new_status' => $targetStatus,
        'source' => 'contractor_assignment_response'
    ], JSON_THROW_ON_ERROR);

    $audit->execute([
        $tid,
        $uid,
        $auditAction,
        'appointment_assignment',
        (int)$appointmentId,
        $auditMetadata
    ]);

    $pdo->commit();

    json_response([
        'ok' => true,
        'appointment_id' => (int)$appointmentId,
        'contractor_id' => $contractorId,
        'previous_status' => 'assigned',
        'status' => $targetStatus,
        'changed' => true
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Contractor assignment response: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Assignment response could not be completed'
    ], 500);
}
