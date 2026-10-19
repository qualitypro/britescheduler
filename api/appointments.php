<?php

require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/AppointmentValidation.php';
require_once dirname(__DIR__).'/app/ContractorScheduleValidator.php';
require_once dirname(__DIR__).'/app/ContractorAssignmentValidator.php';

$u      = Auth::requireUser();
$tenant = Auth::tenant();
$tid    = (int)$tenant['tenant_id'];
$role   = (string)$tenant['role'];
$pdo    = Database::connection();

function tenant_reference(
    PDO $pdo,
    string $table,
    int $tenantId,
    $id,
    string $label
): ?int {
    $id = int_or_null($id);

    if ($id === null) {
        return null;
    }

    $allowedTables = ['clients', 'services', 'contractors'];

    if (!in_array($table, $allowedTables, true)) {
        throw new RuntimeException('Invalid reference table');
    }

    $q = $pdo->prepare(
        "SELECT id FROM {$table}
         WHERE id=? AND tenant_id=?"
    );

    $q->execute([$id, $tenantId]);

    if (!$q->fetchColumn()) {
        throw new RuntimeException("Invalid {$label}");
    }

    return $id;
}

function appointment_client_location(
    PDO $pdo,
    int $tenantId,
    ?int $clientId,
    $locationId
): ?int {
    $locationId = int_or_null($locationId);

    if ($locationId === null) {
        return null;
    }

    if ($clientId === null) {
        throw new RuntimeException(
            'Select a client before selecting a service location'
        );
    }

    $q = $pdo->prepare(
        'SELECT id FROM client_locations
         WHERE id=? AND tenant_id=? AND client_id=?'
    );
    $q->execute([$locationId, $tenantId, $clientId]);

    if (!$q->fetchColumn()) {
        throw new RuntimeException(
            'Service location does not belong to the selected client'
        );
    }

    return $locationId;
}

function validate_contractors(
    PDO $pdo,
    int $tenantId,
    array $contractorIds
): array {
    $result = [];

    foreach ($contractorIds as $contractorId) {

        $contractorId = (int)$contractorId;

        if ($contractorId <= 0) {
            continue;
        }

        tenant_reference(
            $pdo,
            'contractors',
            $tenantId,
            $contractorId,
            'contractor'
        );

        $result[$contractorId] = $contractorId;
    }

    return array_values($result);
}

/**
 * Validate eligibility for newly added contractor assignments.
 *
 * Existing assignments may be excluded by passing their IDs.
 * Availability and scheduling conflicts remain separately enforced.
 *
 * Returns advisory warnings keyed by contractor ID.
 */
function validate_assignment_eligibility(
    PDO $pdo,
    int $tenantId,
    array $contractorIds,
    ?int $serviceId,
    ?int $locationId,
    array $existingContractorIds = []
): array {
    $existing = array_fill_keys(
        array_map('intval', $existingContractorIds),
        true
    );

    $warnings = [];

    foreach ($contractorIds as $contractorId) {
        $contractorId = (int)$contractorId;

        if (isset($existing[$contractorId])) {
            continue;
        }

        $result = ContractorAssignmentValidator::evaluate(
            $pdo,
            $tenantId,
            $contractorId,
            $serviceId,
            $locationId
        );

        if (!$result['allowed']) {
            throw new RuntimeException(
                'Contractor ' . $contractorId .
                ' is not eligible: ' .
                implode('; ', $result['errors'])
            );
        }

        if ($result['warnings']) {
            $warnings[$contractorId] = $result['warnings'];
        }
    }

    return $warnings;
}

function validate_times(string $startsAt, string $endsAt): void
{
    // Both values have already been normalized by
    // appointment_datetime() to YYYY-MM-DD HH:MM:SS.
    // Lexical ordering preserves tenant-local wall-clock ordering
    // without depending on the PHP server timezone.
    if ($endsAt <= $startsAt) {
        throw new RuntimeException(
            'Appointment end time must be after start time'
        );
    }
}

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // FullCalendar uses UTC as a representation of tenant-local
    // wall-clock time. Do not convert these boundaries to the
    // server's timezone before comparing with DATETIME columns.
    $normalizeCalendarBoundary = static function (
        string $value
    ): string {
        $value = trim($value);

        $formats = [
            '!Y-m-d\TH:i:s\Z',
            '!Y-m-d\TH:i:s',
            '!Y-m-d H:i:s'
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat(
                $format,
                $value,
                new DateTimeZone('UTC')
            );

            $errors = DateTimeImmutable::getLastErrors();

            if (!$date ||
                ($errors !== false &&
                 ($errors['warning_count'] || $errors['error_count']))) {
                continue;
            }

            if ($date->format(ltrim($format, '!')) !== $value ||
                (int)$date->format('Y') < 2000) {
                continue;
            }

            return $date->format('Y-m-d H:i:s');
        }

        throw new RuntimeException(
            'Invalid calendar date-range boundary'
        );
    };

    $start = $normalizeCalendarBoundary(
        (string)($_GET['start'] ?? date('Y-m-01 00:00:00'))
    );

    $end = $normalizeCalendarBoundary(
        (string)($_GET['end'] ?? date('Y-m-t 23:59:59'))
    );

    if ($start >= $end) {
        throw new RuntimeException(
            'Calendar date-range start must precede end'
        );
    }


    /*
     * Base tenant filter.
     *
     * Calendar requests use a date range.
     * Single-appointment requests use an ID instead.
     * Both retain tenant and role restrictions.
     */
    $where = "
        a.tenant_id=?
    ";

    $params = [$tid];

    if (!array_key_exists('appointment_id', $_GET)) {
        $where .= "
            AND a.starts_at < ?
            AND a.ends_at > ?
        ";

        $params[] = $end;
        $params[] = $start;
    }


    // Optional single-appointment lookup for authenticated deep links.
    // Preserve tenant and role filters below.
    if (array_key_exists('appointment_id', $_GET)) {
        $rawAppointmentId = (string)$_GET['appointment_id'];

        if (
            !ctype_digit($rawAppointmentId) ||
            $rawAppointmentId === '0' ||
            strlen($rawAppointmentId) > 18
        ) {
            json_response([
                'error' => 'Invalid appointment ID'
            ], 422);
        }

        $where .= "
            AND a.id=?
        ";

        $params[] = (int)$rawAppointmentId;
    }


    /*
     * CLIENT
     *
     * Resolve clients.id from authenticated
     * users.id. Never trust client_id supplied
     * by the browser.
     */
    if ($role === 'client') {

        $cq=$pdo->prepare("
            SELECT id
            FROM clients
            WHERE tenant_id=?
              AND user_id=?
              AND status='active'
            LIMIT 1
        ");

        $cq->execute([
            $tid,
            (int)$u['id']
        ]);

        $clientId=
            (int)($cq->fetchColumn() ?: 0);

        if ($clientId <= 0) {
            json_response([
                'error'=>'Client profile not found'
            ],403);
        }

        $where .= "
            AND a.client_id=?
        ";

        $params[]=$clientId;
    }


    /*
     * CONTRACTOR
     *
     * Resolve contractors.id from the
     * authenticated user and require an
     * appointment_contractors assignment.
     */
    elseif ($role === 'contractor') {

        $cq=$pdo->prepare("
            SELECT id
            FROM contractors
            WHERE tenant_id=?
              AND user_id=?
              AND status='active'
            LIMIT 1
        ");

        $cq->execute([
            $tid,
            (int)$u['id']
        ]);

        $contractorId=
            (int)($cq->fetchColumn() ?: 0);

        if ($contractorId <= 0) {
            json_response([
                'error'=>'Contractor profile not found'
            ],403);
        }

        $where .= "
            AND EXISTS (
                SELECT 1
                FROM appointment_contractors own_ac
                WHERE
                    own_ac.appointment_id=a.id
                    AND own_ac.contractor_id=?
            )
        ";

        $params[]=$contractorId;
    }


    /*
     * Only approved management roles may
     * perform tenant-wide appointment reads.
     */
    elseif (
        !in_array(
            $role,
            [
                'owner',
                'admin',
                'scheduler',
                'accounting'
            ],
            true
        )
    ) {

        json_response([
            'error'=>'Forbidden'
        ],403);
    }


    $sql="
        SELECT
            a.*,

            c.first_name client_first,
            c.last_name client_last,

            s.name service_name,
            s.price service_price,
            s.duration_minutes,

            GROUP_CONCAT(
                DISTINCT ac.contractor_id
            ) contractor_ids,

            GROUP_CONCAT(
                DISTINCT ct.color
            ) contractor_colors

        FROM appointments a

        LEFT JOIN clients c
            ON c.id=a.client_id
            AND c.tenant_id=a.tenant_id

        LEFT JOIN services s
            ON s.id=a.service_id
            AND s.tenant_id=a.tenant_id

        LEFT JOIN appointment_contractors ac
            ON ac.appointment_id=a.id

        LEFT JOIN contractors ct
            ON ct.id=ac.contractor_id
            AND ct.tenant_id=a.tenant_id

        WHERE {$where}

        GROUP BY a.id

        ORDER BY a.starts_at
    ";

    $q=$pdo->prepare($sql);
    $q->execute($params);

    json_response([
        'appointments'=>$q->fetchAll()
    ]);
}

/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();

Auth::requireRole(
    'owner',
    'admin',
    'scheduler'
);

$d = request_data();

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $clientId = tenant_reference(
            $pdo,
            'clients',
            $tid,
            $d['client_id'] ?? null,
            'client'
        );

        $clientLocationId = appointment_client_location(
            $pdo,
            $tid,
            $clientId,
            $d['client_location_id'] ?? null
        );

        $serviceId = tenant_reference(
            $pdo,
            'services',
            $tid,
            $d['service_id'] ?? null,
            'service'
        );

        $contractorIds = validate_contractors(
            $pdo,
            $tid,
            $d['contractor_ids'] ?? []
        );

        $startsAt = appointment_datetime(
            $d['starts_at'] ?? '',
            'start time'
        );

        $endsAt = appointment_datetime(
            $d['ends_at'] ?? '',
            'end time'
        );

        validate_times($startsAt, $endsAt);
        validate_appointment_dst(
            $pdo,
            $tid,
            $startsAt,
            $endsAt
        );
        validate_future_appointment_start(
            $pdo,
            $tid,
            $startsAt
        );

        $status = validate_appointment_status(
            $d['status'] ?? 'scheduled'
        );

        $eligibilityWarnings = validate_assignment_eligibility(
            $pdo,
            $tid,
            $contractorIds,
            $serviceId,
            $clientLocationId
        );

        check_contractor_availability(
            $pdo,
            $tid,
            $contractorIds,
            $startsAt,
            $endsAt
        );

        check_contractor_conflicts(
            $pdo,
            $tid,
            $contractorIds,
            $startsAt,
            $endsAt
        );

        $pdo->beginTransaction();

        $q = $pdo->prepare("
            INSERT INTO appointments(
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
            )
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $q->execute([
            $tid,
            $clientId,
            $clientLocationId,
            $serviceId,
            trim($d['title'] ?? 'Appointment'),
            $d['description'] ?? null,
            $startsAt,
            $endsAt,
            $status,
            $d['location'] ?? null,
            !empty($d['is_public']) ? 1 : 0,
            $u['id']
        ]);

        $id = (int)$pdo->lastInsertId();

        foreach ($contractorIds as $contractorId) {

            $x = $pdo->prepare("
                INSERT INTO appointment_contractors(
                    appointment_id,
                    contractor_id
                )
                VALUES(?,?)
            ");

            $x->execute([
                $id,
                $contractorId
            ]);
        }

        $pdo->commit();

        json_response([
            'ok' => true,
            'id' => $id,
            'eligibility_warnings' => $eligibilityWarnings
        ], 201);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'error' => $e->getMessage()
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| PUT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

    $id = (int)($d['id'] ?? 0);

    try {

        $owned = $pdo->prepare("
            SELECT starts_at, ends_at
            FROM appointments
            WHERE id=? AND tenant_id=?
        ");

        $owned->execute([$id, $tid]);

        $existingAppointment = $owned->fetch(PDO::FETCH_ASSOC);

        if (!$existingAppointment) {
            throw new RuntimeException(
                'Appointment not found'
            );
        }

        $clientId = tenant_reference(
            $pdo,
            'clients',
            $tid,
            $d['client_id'] ?? null,
            'client'
        );

        $clientLocationId = appointment_client_location(
            $pdo,
            $tid,
            $clientId,
            $d['client_location_id'] ?? null
        );

        $serviceId = tenant_reference(
            $pdo,
            'services',
            $tid,
            $d['service_id'] ?? null,
            'service'
        );

        $contractorIds = validate_contractors(
            $pdo,
            $tid,
            $d['contractor_ids'] ?? []
        );

        $startsAt = appointment_datetime(
            $d['starts_at'] ?? '',
            'start time'
        );

        $endsAt = appointment_datetime(
            $d['ends_at'] ?? '',
            'end time'
        );

        validate_times($startsAt, $endsAt);
        validate_appointment_dst(
            $pdo,
            $tid,
            $startsAt,
            $endsAt,
            (string)$existingAppointment['starts_at'],
            (string)$existingAppointment['ends_at']
        );
        validate_future_appointment_start(
            $pdo,
            $tid,
            $startsAt,
            (string)$existingAppointment['starts_at']
        );

        $status = validate_appointment_status(
            $d['status'] ?? 'scheduled'
        );

        $existingAssignmentQuery = $pdo->prepare("
            SELECT ac.contractor_id
            FROM appointment_contractors ac
            INNER JOIN appointments a
                ON a.id = ac.appointment_id
            WHERE a.id=?
              AND a.tenant_id=?
        ");

        $existingAssignmentQuery->execute([$id, $tid]);

        $existingAssignmentIds = array_map(
            'intval',
            $existingAssignmentQuery->fetchAll(PDO::FETCH_COLUMN)
        );

        $eligibilityWarnings = validate_assignment_eligibility(
            $pdo,
            $tid,
            $contractorIds,
            $serviceId,
            $clientLocationId,
            $existingAssignmentIds
        );

        check_contractor_availability(
            $pdo,
            $tid,
            $contractorIds,
            $startsAt,
            $endsAt
        );

        check_contractor_conflicts(
            $pdo,
            $tid,
            $contractorIds,
            $startsAt,
            $endsAt,
            $id
        );

        $pdo->beginTransaction();

        $q = $pdo->prepare("
            UPDATE appointments
            SET
                client_id=?,
                client_location_id=?,
                service_id=?,
                title=?,
                description=?,
                starts_at=?,
                ends_at=?,
                status=?,
                location=?,
                is_public=?
            WHERE id=? AND tenant_id=?
        ");

        $q->execute([
            $clientId,
            $clientLocationId,
            $serviceId,
            $d['title'] ?? 'Appointment',
            $d['description'] ?? null,
            $startsAt,
            $endsAt,
            $status,
            $d['location'] ?? null,
            !empty($d['is_public']) ? 1 : 0,
            $id,
            $tid
        ]);

        /*
         * Preserve existing contractor assignment lifecycle data.
         *
         * Unchanged rows retain status and pay_amount.
         * Removed contractors are deleted individually.
         * Newly selected contractors receive the default
         * assignment status.
         */
        $existingQuery = $pdo->prepare("
            SELECT contractor_id
            FROM appointment_contractors
            WHERE appointment_id=?
        ");

        $existingQuery->execute([$id]);

        $existingIds = array_map(
            'intval',
            $existingQuery->fetchAll(PDO::FETCH_COLUMN)
        );

        $removedIds = array_diff(
            $existingIds,
            $contractorIds
        );

        $addedIds = array_diff(
            $contractorIds,
            $existingIds
        );

        $removeQuery = $pdo->prepare("
            DELETE FROM appointment_contractors
            WHERE appointment_id=?
              AND contractor_id=?
        ");

        foreach ($removedIds as $contractorId) {
            $removeQuery->execute([
                $id,
                $contractorId
            ]);
        }

        $insertQuery = $pdo->prepare("
            INSERT INTO appointment_contractors(
                appointment_id,
                contractor_id
            )
            VALUES(?,?)
        ");

        foreach ($addedIds as $contractorId) {
            $insertQuery->execute([
                $id,
                $contractorId
            ]);
        }

        $pdo->commit();

        json_response([
            'ok' => true,
            'eligibility_warnings' => $eligibilityWarnings
        ]);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'error' => $e->getMessage()
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $id = (int)($d['id'] ?? 0);

    try {
        $pdo->beginTransaction();

        // Preserve notification history when an appointment is deleted.
        // Tenant-scoped updates retain cross-tenant isolation.
        $clear = $pdo->prepare("
            UPDATE notifications
            SET appointment_id = NULL
            WHERE tenant_id = ?
              AND appointment_id = ?
        ");
        $clear->execute([$tid, $id]);

        $q = $pdo->prepare("
            DELETE FROM appointments
            WHERE id = ? AND tenant_id = ?
        ");
        $q->execute([$id, $tid]);

        if (!$q->rowCount()) {
            $pdo->rollBack();
            json_response([
                'error' => 'Appointment not found'
            ], 404);
        }

        $pdo->commit();

        json_response(['ok' => true]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Appointment deletion failed: ' . $e->getMessage());

        json_response([
            'error' => 'Unable to delete appointment'
        ], 500);
    }
}

json_response([
    'error' => 'Method not allowed'
], 405);
