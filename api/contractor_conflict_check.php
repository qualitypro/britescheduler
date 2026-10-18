<?php
declare(strict_types=1);

/**
 * Read-only contractor scheduling conflict check.
 *
 * Advisory result only. Appointment POST/PUT remains authoritative.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ContractorScheduleValidator.php';

Auth::requireUser();

$tenant = Auth::tenant();
$tenantId = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];

if (!in_array($role, ['owner', 'admin', 'scheduler'], true)) {
    json_response(['error' => 'Forbidden'], 403);
}

require_method('GET');

function conflict_check_id(mixed $value): ?int
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

function conflict_check_datetime(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    foreach ([
        '!Y-m-d H:i:s',
        '!Y-m-d\TH:i:s',
        '!Y-m-d\TH:i'
    ] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);

        if ($date && $date->format(substr($format, 1)) === $value) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    return null;
}

$contractorId = conflict_check_id(
    $_GET['contractor_id'] ?? null
);

$startsAt = conflict_check_datetime(
    $_GET['starts_at'] ?? null
);

$endsAt = conflict_check_datetime(
    $_GET['ends_at'] ?? null
);

$excludeAppointmentId = null;

if (array_key_exists('exclude_appointment_id', $_GET)
    && $_GET['exclude_appointment_id'] !== '') {

    $excludeAppointmentId = conflict_check_id(
        $_GET['exclude_appointment_id']
    );

    if ($excludeAppointmentId === null) {
        json_response(['error' => 'Invalid appointment ID'], 422);
    }
}

if ($contractorId === null) {
    json_response(['error' => 'Valid contractor ID required'], 422);
}

if ($startsAt === null || $endsAt === null || $endsAt <= $startsAt) {
    json_response(['error' => 'Valid appointment time range required'], 422);
}

try {
    $pdo = Database::connection();

    $contractor = $pdo->prepare(
        'SELECT id FROM contractors
         WHERE id=? AND tenant_id=?
         LIMIT 1'
    );

    $contractor->execute([$contractorId, $tenantId]);

    if (!$contractor->fetchColumn()) {
        json_response(['error' => 'Contractor not found'], 404);
    }

    if ($excludeAppointmentId !== null) {
        $appointment = $pdo->prepare(
            'SELECT id FROM appointments
             WHERE id=? AND tenant_id=?
             LIMIT 1'
        );

        $appointment->execute([
            $excludeAppointmentId,
            $tenantId
        ]);

        if (!$appointment->fetchColumn()) {
            json_response(['error' => 'Appointment not found'], 404);
        }
    }

    try {
        check_contractor_availability(
            $pdo,
            $tenantId,
            [$contractorId],
            $startsAt,
            $endsAt
        );

        check_contractor_conflicts(
            $pdo,
            $tenantId,
            [$contractorId],
            $startsAt,
            $endsAt,
            $excludeAppointmentId
        );

    } catch (RuntimeException $e) {
        json_response([
            'ok' => true,
            'available' => false,
            'reason' => $e->getMessage()
        ]);
    }

    json_response([
        'ok' => true,
        'available' => true,
        'reason' => null
    ]);

} catch (Throwable $e) {
    error_log('Contractor conflict check failed: ' . $e->getMessage());

    json_response([
        'error' => 'Unable to check contractor availability'
    ], 500);
}
