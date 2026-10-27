<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/TenantClock.php';
require_once dirname(__DIR__) . '/app/ContractorScheduleValidator.php';
require_once dirname(__DIR__) . '/app/ContractorAssignmentValidator.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('owner');

require_method('POST');
verify_csrf();

$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];

$data = request_data();

if (!is_array($data)) {
    json_response(['error' => 'Invalid request body'], 422);
}

$appointmentId = filter_var(
    $data['appointment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$declinedContractorId = filter_var(
    $data['declined_contractor_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$replacementContractorId = filter_var(
    $data['replacement_contractor_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (
    !$appointmentId ||
    !$declinedContractorId ||
    !$replacementContractorId ||
    $declinedContractorId === $replacementContractorId
) {
    json_response(['error' => 'Invalid reassignment request'], 422);
}

$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    /*
     * Serialize with appointment edits and marketplace awards.
     */
    $q = $pdo->prepare("
        SELECT
            id,
            status,
            starts_at,
            ends_at,
            service_id,
            client_location_id,
            (starts_at > ?) AS is_future
        FROM appointments
        WHERE id = ?
          AND tenant_id = ?
        FOR UPDATE
    ");

    $q->execute([
        TenantClock::now($pdo, $tenantId),
        $appointmentId,
        $tenantId
    ]);
    $appointment = $q->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new DomainException('Appointment not found', 404);
    }

    if (
        !in_array(
            $appointment['status'],
            ['tentative', 'scheduled', 'confirmed'],
            true
        ) ||
        (int)$appointment['is_future'] !== 1
    ) {
        throw new DomainException(
            'Appointment is not eligible for reassignment',
            409
        );
    }

    /*
     * Any marketplace listing requires a separate amendment workflow.
     */
    $q = $pdo->prepare("
        SELECT id
        FROM appointment_bid_listings
        WHERE tenant_id = ?
          AND appointment_id = ?
        FOR UPDATE
    ");

    $q->execute([$tenantId, $appointmentId]);

    if ($q->fetchColumn()) {
        throw new DomainException(
            'Marketplace appointments cannot be reassigned here',
            409
        );
    }

    /*
     * Lock all existing assignments.
     */
    $q = $pdo->prepare("
        SELECT contractor_id, status
        FROM appointment_contractors
        WHERE appointment_id = ?
        ORDER BY contractor_id
        FOR UPDATE
    ");

    $q->execute([$appointmentId]);
    $assignments = $q->fetchAll(PDO::FETCH_ASSOC);

    $declinedFound = false;

    foreach ($assignments as $assignment) {
        $contractorId = (int)$assignment['contractor_id'];

        if ($contractorId === $replacementContractorId) {
            throw new DomainException(
                'Replacement contractor is already assigned',
                409
            );
        }

        if ($contractorId === $declinedContractorId) {
            $declinedFound = $assignment['status'] === 'declined';
        }
    }

    if (!$declinedFound) {
        throw new DomainException(
            'Declined assignment not found',
            409
        );
    }

    /*
     * Cycle 45F: prevent multiple replacements for one decline.
     *
     * The appointment row is already locked FOR UPDATE above,
     * serializing competing reassignment requests.
     *
     * JSON metadata identifies the original declined contractor.
     */
    $previousReassignment = $pdo->prepare("
        SELECT id
        FROM audit_log
        WHERE tenant_id = ?
          AND entity_type = 'appointment_assignment'
          AND entity_id = ?
          AND action = 'assignment.reassigned'
          AND JSON_VALID(metadata)
          AND JSON_UNQUOTE(
              JSON_EXTRACT(metadata, '$.declined_contractor_id')
          ) = ?
        LIMIT 1
    ");

    $previousReassignment->execute([
        $tenantId,
        $appointmentId,
        (string)$declinedContractorId
    ]);

    if ($previousReassignment->fetchColumn()) {
        throw new DomainException(
            'This declined assignment has already been reassigned',
            409
        );
    }
    /*
     * Lock the replacement contractor and validate active membership.
     */
    $q = $pdo->prepare("
        SELECT c.id, c.user_id
        FROM contractors c
        JOIN users u
          ON u.id = c.user_id
         AND u.status = 'active'
        JOIN tenant_memberships tm
          ON tm.tenant_id = c.tenant_id
         AND tm.user_id = c.user_id
         AND tm.role = 'contractor'
         AND tm.status = 'active'
        WHERE c.tenant_id = ?
          AND c.id = ?
          AND c.status = 'active'
        FOR UPDATE
    ");

    $q->execute([
        $tenantId,
        $replacementContractorId
    ]);

    $replacement = $q->fetch(PDO::FETCH_ASSOC);

    if (!$replacement) {
        throw new DomainException(
            'Active replacement contractor required',
            422
        );
    }

    /*
     * Shared eligibility, availability and conflict policies.
     */
    $eligibility = ContractorAssignmentValidator::evaluate(
        $pdo,
        $tenantId,
        $replacementContractorId,
        $appointment['service_id'] === null
            ? null
            : (int)$appointment['service_id'],
        $appointment['client_location_id'] === null
            ? null
            : (int)$appointment['client_location_id']
    );

    if (!$eligibility['allowed']) {
        throw new DomainException(
            'Replacement contractor is not eligible: ' .
            implode('; ', $eligibility['errors']),
            422
        );
    }

    check_contractor_availability(
        $pdo,
        $tenantId,
        [$replacementContractorId],
        (string)$appointment['starts_at'],
        (string)$appointment['ends_at']
    );

    check_contractor_conflicts(
        $pdo,
        $tenantId,
        [$replacementContractorId],
        (string)$appointment['starts_at'],
        (string)$appointment['ends_at'],
        $appointmentId
    );

    /*
     * Preserve declined assignment and create a new assignment.
     */
    $q = $pdo->prepare("
        INSERT INTO appointment_contractors (
            appointment_id,
            contractor_id,
            status
        )
        VALUES (?, ?, 'assigned')
    ");

    $q->execute([
        $appointmentId,
        $replacementContractorId
    ]);

    /*
     * Record an immutable reassignment event.
     */
    $metadata = json_encode([
        'appointment_id' => (int)$appointmentId,
        'declined_contractor_id' => (int)$declinedContractorId,
        'replacement_contractor_id' => (int)$replacementContractorId,
        'previous_status' => 'declined',
        'new_status' => 'assigned',
        'source' => 'owner_assignment_reassign',
        'eligibility_warnings' => $eligibility['warnings']
    ], JSON_THROW_ON_ERROR);

    $q = $pdo->prepare("
        INSERT INTO audit_log (
            tenant_id,
            user_id,
            action,
            entity_type,
            entity_id,
            metadata
        )
        VALUES (?, ?, 'assignment.reassigned',
                'appointment_assignment', ?, ?)
    ");

    $q->execute([
        $tenantId,
        $userId,
        $appointmentId,
        $metadata
    ]);

    /*
     * Notify the replacement contractor.
     */
    $q = $pdo->prepare("
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

    $q->execute([
        $tenantId,
        (int)$replacement['user_id'],
        $appointmentId,
        'contractor_assignment_created',
        'New appointment assignment',
        sprintf(
            'You have been assigned to appointment #%d.',
            $appointmentId
        )
    ]);

    $pdo->commit();

    json_response([
        'ok' => true,
        'appointment_id' => (int)$appointmentId,
        'declined_contractor_id' => (int)$declinedContractorId,
        'replacement_contractor_id' => (int)$replacementContractorId,
        'replacement_status' => 'assigned',
        'eligibility_warnings' => $eligibility['warnings']
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof DomainException) {
        $code = (int)$e->getCode();

        json_response([
            'error' => $e->getMessage()
        ], in_array($code, [404, 409, 422], true) ? $code : 422);
    }

    error_log('Owner reassignment: ' . $e->getMessage());

    json_response([
        'error' => 'Reassignment could not be completed'
    ], 500);
}
