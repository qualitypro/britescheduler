<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/AppointmentValidation.php';
require_once dirname(__DIR__) . '/app/ClientBookingAvailability.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('client');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
}

$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];

function slot_positive_id(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $id = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $id === false ? null : $id;
}

$locationId = slot_positive_id(
    $_GET['client_location_id'] ?? null
);

$serviceId = slot_positive_id(
    $_GET['service_id'] ?? null
);

$dateValue = $_GET['date'] ?? null;

if (!$locationId || !$serviceId ||
    !is_string($dateValue) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dateValue)) {
    json_response(['error' => 'Invalid availability request'], 422);
}

try {
    $pdo = Database::connection();

    $q = $pdo->prepare(
        'SELECT timezone FROM tenants WHERE id=? LIMIT 1'
    );
    $q->execute([$tenantId]);
    $timezoneName = $q->fetchColumn();

    if (!$timezoneName) {
        throw new RuntimeException('Tenant timezone unavailable');
    }

    $timezone = new DateTimeZone((string)$timezoneName);

    $day = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $dateValue,
        $timezone
    );

    $errors = DateTimeImmutable::getLastErrors();

    if (!$day ||
        ($errors !== false &&
         ($errors['warning_count'] || $errors['error_count'])) ||
        $day->format('Y-m-d') !== $dateValue) {
        json_response(['error' => 'Invalid date'], 422);
    }

    $now = new DateTimeImmutable('now', $timezone);

    // Limit expensive eligibility evaluation to a 31-day horizon.
    $today = $now->setTime(0, 0);
    $lastDay = $today->modify('+31 days');

    if ($day < $today || $day > $lastDay) {
        json_response([
            'error' => 'Date outside booking window'
        ], 422);
    }

    $q = $pdo->prepare(
        'SELECT cl.id
         FROM client_locations cl
         INNER JOIN clients c
           ON c.id=cl.client_id
          AND c.tenant_id=cl.tenant_id
         WHERE cl.id=?
           AND cl.tenant_id=?
           AND c.user_id=?
           AND c.status=?
         LIMIT 1'
    );

    $q->execute([
        $locationId,
        $tenantId,
        $userId,
        'active'
    ]);

    if (!$q->fetchColumn()) {
        json_response(['error' => 'Location not found'], 404);
    }

    $q = $pdo->prepare(
        'SELECT duration_minutes
         FROM services
         WHERE id=? AND tenant_id=? AND active=1
         LIMIT 1'
    );

    $q->execute([$serviceId, $tenantId]);

    $durationValue = $q->fetchColumn();

    if ($durationValue === false) {
        json_response(['error' => 'Service not found'], 404);
    }

    $duration = (int)$durationValue;

    if ($duration < 1 || $duration > 1440) {
        throw new RuntimeException('Invalid service duration');
    }

    $slots = ClientBookingAvailability::availableSlots(
        $pdo,
        $tenantId,
        $locationId,
        $serviceId,
        $duration,
        $dateValue
    );

    json_response([
        'ok' => true,
        'date' => $dateValue,
        'service_id' => $serviceId,
        'client_location_id' => $locationId,
        'duration_minutes' => $duration,
        'interval_minutes' => 30,
        'timezone' => $timezone->getName(),
        'slots' => $slots,
        'count' => count($slots),
        'requires_confirmation' => true,
        'note' => 'Times are provisional and require owner confirmation.'
    ]);

} catch (Throwable $e) {
    error_log(
        'Client availability slots error: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Unable to load appointment availability'
    ], 500);
}
