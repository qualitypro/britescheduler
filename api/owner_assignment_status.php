<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

Auth::requireUser();
$tenant = Auth::requireRole('owner');
require_method('GET');

$tenantId = (int)$tenant['tenant_id'];

$appointmentId = filter_var(
    $_GET['appointment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$appointmentId) {
    json_response(['error' => 'Invalid appointment ID'], 422);
}

$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $appointmentQuery = $pdo->prepare("
        SELECT
            a.id,
            a.status,
            a.starts_at,
            a.ends_at,
            EXISTS (
                SELECT 1
                FROM appointment_bid_listings l
                WHERE l.tenant_id = a.tenant_id
                  AND l.appointment_id = a.id
            ) AS has_marketplace_listing
        FROM appointments a
        WHERE a.id = ?
          AND a.tenant_id = ?
        LIMIT 1
    ");

    $appointmentQuery->execute([$appointmentId, $tenantId]);
    $appointment = $appointmentQuery->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        json_response(['error' => 'Appointment not found'], 404);
    }

    $assignmentQuery = $pdo->prepare("
        SELECT
            ac.contractor_id,
            ac.status,
            ac.pay_amount,
            c.first_name,
            c.last_name,
            c.business_name
        FROM appointment_contractors ac
        JOIN contractors c
          ON c.id = ac.contractor_id
         AND c.tenant_id = ?
        WHERE ac.appointment_id = ?
        ORDER BY ac.contractor_id
    ");

    $assignmentQuery->execute([$tenantId, $appointmentId]);

    $assignments = $assignmentQuery->fetchAll(PDO::FETCH_ASSOC);

    $auditQuery = $pdo->prepare("
        SELECT
            id,
            metadata,
            created_at
        FROM audit_log
        WHERE tenant_id = ?
          AND entity_type = 'appointment_assignment'
          AND entity_id = ?
          AND action = 'assignment.reassigned'
        ORDER BY id ASC
    ");

    $auditQuery->execute([$tenantId, $appointmentId]);

    $reassignments = [];

    foreach ($auditQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $metadata = json_decode(
            (string)$row['metadata'],
            true
        );

        if (!is_array($metadata)) {
            continue;
        }

        $declinedId = filter_var(
            $metadata['declined_contractor_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $replacementId = filter_var(
            $metadata['replacement_contractor_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$declinedId || !$replacementId) {
            continue;
        }

        $reassignments[] = [
            'audit_id' => (int)$row['id'],
            'declined_contractor_id' => (int)$declinedId,
            'replacement_contractor_id' => (int)$replacementId,
            'created_at' => $row['created_at']
        ];
    }

    $handledDeclines = array_fill_keys(
        array_column($reassignments, 'declined_contractor_id'),
        true
    );

    $hasListing = (bool)$appointment['has_marketplace_listing'];

    $isFuture = (
        (string)$appointment['starts_at'] >
        (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')
    );

    $eligibleAppointment =
        !$hasListing &&
        $isFuture &&
        in_array(
            $appointment['status'],
            ['tentative', 'scheduled', 'confirmed'],
            true
        );

    $resultAssignments = [];

    foreach ($assignments as $assignment) {
        $contractorId = (int)$assignment['contractor_id'];
        $status = (string)$assignment['status'];

        $resultAssignments[] = [
            'contractor_id' => $contractorId,
            'status' => $status,
            'pay_amount' => $assignment['pay_amount'],
            'first_name' => $assignment['first_name'],
            'last_name' => $assignment['last_name'],
            'business_name' => $assignment['business_name'],
            'reassignment_handled' =>
                isset($handledDeclines[$contractorId]),
            'can_reassign' =>
                $eligibleAppointment &&
                $status === 'declined' &&
                !isset($handledDeclines[$contractorId])
        ];
    }

    json_response([
        'ok' => true,
        'appointment' => [
            'id' => (int)$appointment['id'],
            'status' => $appointment['status'],
            'starts_at' => $appointment['starts_at'],
            'ends_at' => $appointment['ends_at'],
            'has_marketplace_listing' => $hasListing,
            'eligible_for_reassignment' => $eligibleAppointment
        ],
        'assignments' => $resultAssignments,
        'reassignments' => $reassignments
    ]);

} catch (Throwable $e) {
    error_log(
        'Owner assignment status: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Unable to load assignment status'
    ], 500);
}
