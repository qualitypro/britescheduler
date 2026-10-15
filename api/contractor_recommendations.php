<?php
declare(strict_types=1);

/**
 * Brite Scheduler V2 — Contractor Recommendations
 *
 * Read-only, tenant-scoped recommendations.
 * Does not approve, reserve, or assign contractors.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ContractorEligibilityEngine.php';

Auth::requireUser();
$tenant = Auth::tenant();

$tenantId = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];

if (!in_array($role, ['owner', 'admin', 'scheduler'], true)) {
    json_response(['error' => 'Forbidden'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
}

/**
 * Strict positive integer query parameter.
 */
function recommendation_id(mixed $value): ?int
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

/**
 * Accept a local datetime without ambiguous timezone conversion.
 */
function recommendation_datetime(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    foreach (['!Y-m-d H:i:s', '!Y-m-d\TH:i:s', '!Y-m-d\TH:i'] as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);

        if ($dt && $dt->format(substr($format, 1)) === $value) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    return null;
}

$locationId = recommendation_id($_GET['location_id'] ?? null);
$startsAt = recommendation_datetime($_GET['starts_at'] ?? null);
$endsAt = recommendation_datetime($_GET['ends_at'] ?? null);

$serviceId = null;

if (array_key_exists('service_id', $_GET)) {
    $serviceId = recommendation_id($_GET['service_id']);

    if ($serviceId === null) {
        json_response(['error' => 'Invalid service ID'], 422);
    }
}

$excludeAppointmentId = null;

if (array_key_exists('exclude_appointment_id', $_GET)) {
    $excludeAppointmentId = recommendation_id(
        $_GET['exclude_appointment_id']
    );

    if ($excludeAppointmentId === null) {
        json_response(['error' => 'Invalid appointment ID'], 422);
    }
}

if ($locationId === null) {
    json_response(['error' => 'Valid location ID required'], 422);
}

if ($startsAt === null || $endsAt === null) {
    json_response(['error' => 'Valid appointment times required'], 422);
}

$pdo = Database::connection();

try {
    // Verify location ownership without exposing another tenant's data.
    $locationQuery = $pdo->prepare(
        'SELECT id
         FROM client_locations
         WHERE id=? AND tenant_id=?
         LIMIT 1'
    );

    $locationQuery->execute([$locationId, $tenantId]);

    if (!$locationQuery->fetchColumn()) {
        json_response(['error' => 'Location not found'], 404);
    }

    if ($excludeAppointmentId !== null) {
        $appointmentQuery = $pdo->prepare(
            'SELECT id
             FROM appointments
             WHERE id=? AND tenant_id=?
             LIMIT 1'
        );

        $appointmentQuery->execute([
            $excludeAppointmentId,
            $tenantId
        ]);

        if (!$appointmentQuery->fetchColumn()) {
            json_response(['error' => 'Appointment not found'], 404);
        }
    }

    $contractors = ContractorEligibilityEngine::evaluate(
        $pdo,
        $tenantId,
        $locationId,
        $startsAt,
        $endsAt,
        $excludeAppointmentId,
        $serviceId
    );

    $reviewCount = 0;
    $verifiedCount = 0;

    foreach ($contractors as &$contractor) {
        $reasons = [];

        if ($contractor['geographic_match']) {
            $reasons[] = 'Service location is covered';
        }

        if (!$contractor['availability_ok']) {
            $reasons[] = 'Outside contractor availability';
        }

        if (!$contractor['conflict_free']) {
            $reasons[] = 'Conflicting appointment exists';
        }

        if ($serviceId === null) {
            $reasons[] = 'No service selected; qualifications not evaluated';
        } elseif ($contractor['qualification_status'] === 'verified') {
            $reasons[] = 'Service qualification verified';
        } elseif ($contractor['qualification_status'] === 'rejected') {
            $reasons[] = 'Service qualification rejected';
        } else {
            $reasons[] = 'Service qualification unverified or missing';
        }

        $contractor['reasons'] = $reasons;

        if ($contractor['eligible_for_review']) {
            ++$reviewCount;
        }

        if ($contractor['service_qualified']) {
            ++$verifiedCount;
        }
    }
    unset($contractor);

    json_response([
        'ok' => true,
        'location_id' => $locationId,
        'service_id' => $serviceId,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'exclude_appointment_id' => $excludeAppointmentId,
        'contractors' => $contractors,
        'count' => count($contractors),
        'eligible_for_review_count' => $reviewCount,
        'service_qualified_count' => $verifiedCount,
        'qualifications_verified' => (
            $serviceId !== null &&
            $verifiedCount > 0
        ),
        'automatic_assignment' => false,
        'note' => 'Recommendations require manual review and fresh validation before assignment.'
    ]);

} catch (InvalidArgumentException $exception) {
    json_response(['error' => 'Invalid recommendation request'], 422);

} catch (PDOException $exception) {
    error_log(
        'Contractor recommendations database error: ' .
        $exception->getMessage()
    );

    json_response(
        ['error' => 'Unable to retrieve contractor recommendations'],
        500
    );
}
