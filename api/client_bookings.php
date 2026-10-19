<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/AppointmentValidation.php';
require_once dirname(__DIR__) . '/app/ClientBookingAvailability.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('client');

require_method('POST');
verify_csrf();

$pdo = Database::connection();
$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];

$data = request_data();

$serviceId = filter_var(
    $data['service_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$locationId = filter_var(
    $data['client_location_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$requestKey = strtolower(trim(
    (string)($data['request_key'] ?? '')
));

if (
    !$serviceId ||
    !$locationId ||
    !preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
        $requestKey
    )
) {
    json_response([
        'error' => 'Invalid booking request'
    ], 422);
}

try {
    $startsAt = appointment_datetime(
        (string)($data['starts_at'] ?? ''),
        'starts_at'
    );
} catch (RuntimeException $e) {
    json_response(['error' => $e->getMessage()], 422);
}

try {
    $pdo->beginTransaction();

    // Serialize booking requests for this authenticated client.
    // The lock remains held until commit or rollback.
    $q = $pdo->prepare(
        'SELECT id, status
         FROM clients
         WHERE tenant_id = ? AND user_id = ?
         FOR UPDATE'
    );
    $q->execute([$tenantId, $userId]);
    $client = $q->fetch(PDO::FETCH_ASSOC);

    if (!$client || $client['status'] !== 'active') {
        throw new DomainException('Active client profile required');
    }

    $clientId = (int)$client['id'];

    // Return the original appointment for repeated submissions.
    $q = $pdo->prepare(
        'SELECT appointment_id
         FROM client_booking_requests
         WHERE tenant_id = ?
           AND user_id = ?
           AND request_key = ?'
    );
    $q->execute([$tenantId, $userId, $requestKey]);

    $existingId = $q->fetchColumn();

    if ($existingId !== false) {
        $pdo->commit();

        json_response([
            'ok' => true,
            'id' => (int)$existingId,
            'duplicate' => true
        ]);
    }

    // Resolve the service from the authenticated tenant.
    $q = $pdo->prepare(
        'SELECT id, name, duration_minutes
         FROM services
         WHERE id = ?
           AND tenant_id = ?
           AND active = 1'
    );
    $q->execute([$serviceId, $tenantId]);
    $service = $q->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        throw new DomainException('Service is not available');
    }

    $duration = (int)$service['duration_minutes'];

    if ($duration < 1 || $duration > 1440) {
        throw new DomainException('Invalid service duration');
    }

    // Resolve the location from the authenticated client.
    $q = $pdo->prepare(
        'SELECT id, label, address1, address2,
                city, state, postal_code
         FROM client_locations
         WHERE id = ?
           AND tenant_id = ?
           AND client_id = ?'
    );
    $q->execute([$locationId, $tenantId, $clientId]);
    $location = $q->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        throw new DomainException(
            'Service location does not belong to this client'
        );
    }

    // Perform arithmetic on local wall-clock fields, then validate
    // both endpoints against the tenant's configured timezone.
    $start = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $startsAt,
        new DateTimeZone('UTC')
    );

    if (!$start) {
        throw new DomainException('Invalid start time');
    }

    $endsAt = $start
        ->modify('+' . $duration . ' minutes')
        ->format('Y-m-d H:i:s');

    if ($endsAt <= $startsAt) {
        throw new DomainException('Invalid appointment duration');
    }

    validate_appointment_dst(
        $pdo,
        $tenantId,
        $startsAt,
        $endsAt
    );

    validate_future_appointment_start(
        $pdo,
        $tenantId,
        $startsAt
    );

    // Match the client availability endpoint's 31-day window.
    // Compare tenant-local calendar dates, not browser-local dates.
    $timezoneQuery = $pdo->prepare(
        'SELECT timezone FROM tenants WHERE id = ? LIMIT 1'
    );
    $timezoneQuery->execute([$tenantId]);

    $timezoneName = $timezoneQuery->fetchColumn();

    if (!$timezoneName) {
        throw new DomainException('Tenant timezone unavailable');
    }

    $timezone = new DateTimeZone((string)$timezoneName);
    $today = (new DateTimeImmutable('now', $timezone))
        ->setTime(0, 0);

    $lastDay = $today->modify('+31 days');

    $bookingDay = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        substr($startsAt, 0, 10),
        $timezone
    );

    if (
        !$bookingDay ||
        $bookingDay < $today ||
        $bookingDay > $lastDay
    ) {
        throw new DomainException(
            'Date outside booking window'
        );
    }

    // Recheck availability immediately before creating the
    // tentative appointment. This does not reserve a contractor.
    if (!ClientBookingAvailability::isSlotAvailable(
        $pdo,
        $tenantId,
        $locationId,
        $serviceId,
        $duration,
        $startsAt
    )) {
        throw new DomainException(
            'The selected appointment time is no longer available'
        );
    }

    $address = implode(', ', array_filter([
        trim((string)$location['address1']),
        trim((string)($location['address2'] ?? '')),
        trim((string)$location['city']),
        trim((string)$location['state']),
        trim((string)$location['postal_code'])
    ], static fn($part) => $part !== ''));

    $title = mb_substr(
        (string)$service['name'],
        0,
        190
    );

    // The client requests an appointment; staff confirms it later.
    $q = $pdo->prepare(
        'INSERT INTO appointments (
            tenant_id,
            client_id,
            client_location_id,
            service_id,
            title,
            description,
            starts_at,
            ends_at,
            status,
            location,
            is_public,
            created_by
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $q->execute([
        $tenantId,
        $clientId,
        $locationId,
        $serviceId,
        $title,
        null,
        $startsAt,
        $endsAt,
        'tentative',
        $address,
        0,
        $userId
    ]);

    $appointmentId = (int)$pdo->lastInsertId();

    // Persist the idempotency key in the same transaction.
    $q = $pdo->prepare(
        'INSERT INTO client_booking_requests (
            tenant_id,
            user_id,
            request_key,
            appointment_id
         ) VALUES (?, ?, ?, ?)'
    );

    $q->execute([
        $tenantId,
        $userId,
        $requestKey,
        $appointmentId
    ]);

    // Notify every active owner of the current tenant.
    $q = $pdo->prepare(
        "SELECT tm.user_id
         FROM tenant_memberships tm
         INNER JOIN users u ON u.id = tm.user_id
         WHERE tm.tenant_id = ?
           AND tm.role = 'owner'
           AND tm.status = 'active'
           AND u.status = 'active'"
    );
    $q->execute([$tenantId]);
    $ownerIds = $q->fetchAll(PDO::FETCH_COLUMN);

    if (!$ownerIds) {
        throw new DomainException(
            'No active account owner available for notification'
        );
    }

    $notification = $pdo->prepare(
        'INSERT INTO notifications (
            tenant_id,
            user_id,
            appointment_id,
            type,
            title,
            body
         ) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $notificationTitle = 'New client booking request';

    $notificationBody = sprintf(
        'Appointment #%d: %s requested for %s. Pending confirmation.',
        $appointmentId,
        $title,
        $startsAt
    );

    foreach ($ownerIds as $ownerId) {
        $notification->execute([
            $tenantId,
            (int)$ownerId,
            $appointmentId,
            'appointment',
            $notificationTitle,
            $notificationBody
        ]);
    }

    $pdo->commit();

    json_response([
        'ok' => true,
        'id' => $appointmentId,
        'status' => 'tentative',
        'duplicate' => false
    ], 201);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof DomainException ||
        $e instanceof RuntimeException) {
        json_response([
            'error' => $e->getMessage()
        ], 422);
    }

    error_log('Client booking error: ' . $e->getMessage());

    json_response([
        'error' => 'Unable to create booking'
    ], 500);
}
