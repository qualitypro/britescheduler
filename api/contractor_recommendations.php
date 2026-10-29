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
require_once dirname(__DIR__) . '/app/ContractorRankingEngine.php';

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

    $contractors = ContractorRankingEngine::rank(
        $pdo,
        $tenantId,
        $startsAt,
        $contractors,
        $excludeAppointmentId
    );

    // Cycle 69A: tenant-scoped pricing enrichment; advisory only.
    $offeringsByContractor = [];
    if ($serviceId !== null) {
        $offeringQuery = $pdo->prepare(
            'SELECT contractor_id, offered, pricing_model, rate
             FROM contractor_service_offerings
             WHERE tenant_id=? AND service_id=?'
        );
        $offeringQuery->execute([$tenantId, $serviceId]);
        foreach ($offeringQuery->fetchAll(PDO::FETCH_ASSOC) as $offering) {
            $offeringsByContractor[(int)$offering['contractor_id']] = $offering;
        }
    }

    $reviewCount = 0;
    $verifiedCount = 0;

    foreach ($contractors as &$contractor) {
        $offering = $offeringsByContractor[(int)$contractor['contractor_id']] ?? null;
        $contractor['service_offering'] = [
            'status' => $serviceId === null ? 'not_evaluated'
                : ($offering === null ? 'not_configured'
                : ((int)$offering['offered'] === 1 ? 'offered' : 'not_offered')),
            'pricing_model' => $offering !== null && (int)$offering['offered'] === 1
                ? $offering['pricing_model'] : null,
            'rate' => $offering !== null && (int)$offering['offered'] === 1
                && $offering['rate'] !== null ? (string)$offering['rate'] : null,
        ];
        $reasons = [];
        if ($serviceId !== null) {
            $reasons[] = 'Service offering: ' . str_replace('_', ' ', $contractor['service_offering']['status']);
        }

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

        // Explain the matched ZIP/radius areas without exposing coordinates.
        $areaDescriptions = [];
        $nearestMiles = null;
        foreach ($contractor['matching_areas'] ?? [] as $area) {
            $label = trim((string)($area['label'] ?? ''));
            $type = (string)($area['area_type'] ?? '');
            $distance = $area['distance_miles'] ?? null;
            if ($type === 'radius' && is_numeric($distance)) {
                $miles = round((float)$distance, 1);
                $nearestMiles = $nearestMiles === null ? $miles : min($nearestMiles, $miles);
                $areaDescriptions[] = ($label !== '' ? $label . ': ' : '') . $miles . ' miles from service-area center';
            } elseif ($type === 'zip') {
                $areaDescriptions[] = ($label !== '' ? $label : 'ZIP service area') . ' (ZIP match)';
            }
        }
        $contractor['service_area_explanations'] = array_slice($areaDescriptions, 0, 5);
        $contractor['ranking']['distance_miles'] = $nearestMiles;

        $reviewIssues = [];
        if (!$contractor['availability_ok']) $reviewIssues[] = 'Outside configured availability';
        if (!$contractor['conflict_free']) $reviewIssues[] = 'Overlapping appointment';
        if ($contractor['qualification_status'] === 'rejected') $reviewIssues[] = 'Qualification rejected';
        if ($serviceId !== null && $contractor['qualification_status'] === 'unverified') {
            $reviewIssues[] = 'Qualification not verified';
        }
        if ($serviceId !== null && $contractor['service_offering']['status'] !== 'offered') {
            $reviewIssues[] = 'Contractor has not confirmed this service offering';
        }
        $contractor['dispatch_explanation'] = [
            'status' => !$contractor['eligible_for_review'] ? 'restricted'
                : ($reviewIssues ? 'manual_review' : 'ready_for_review'),
            'issues' => $reviewIssues,
            'note' => 'Advisory only; assignment requires manual confirmation.'
        ];
        $contractor['reasons'] = array_merge($reasons, $areaDescriptions);

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
