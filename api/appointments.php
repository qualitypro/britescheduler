<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

$u   = Auth::requireUser();
$tid = Auth::tenantId();
$pdo = Database::connection();

function appointment_datetime(string $value, string $field): string
{
    $value = trim($value);

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
        DateTimeInterface::ATOM
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);

        if ($dt && $dt->format($format) === $value) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    try {
        $dt = new DateTime($value);
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        throw new RuntimeException("Invalid {$field}");
    }
}

function validate_appointment_status(string $status): string
{
    $allowed = [
        'tentative',
        'scheduled',
        'confirmed',
        'in_progress',
        'completed',
        'cancelled',
        'no_show'
    ];

    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException('Invalid appointment status');
    }

    return $status;
}

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

function check_contractor_conflicts(
    PDO $pdo,
    int $tenantId,
    array $contractorIds,
    string $startsAt,
    string $endsAt,
    ?int $excludeAppointmentId = null
): void {

    foreach ($contractorIds as $contractorId) {

        $sql = "
            SELECT
                a.id,
                a.title,
                a.starts_at,
                a.ends_at
            FROM appointments a
            INNER JOIN appointment_contractors ac
                ON ac.appointment_id=a.id
            WHERE
                a.tenant_id=?
                AND ac.contractor_id=?
                AND a.status NOT IN ('cancelled','no_show')
                AND a.starts_at < ?
                AND a.ends_at > ?
        ";

        $params = [
            $tenantId,
            $contractorId,
            $endsAt,
            $startsAt
        ];

        if ($excludeAppointmentId !== null) {
            $sql .= " AND a.id<>?";
            $params[] = $excludeAppointmentId;
        }

        $sql .= " LIMIT 1";

        $q = $pdo->prepare($sql);
        $q->execute($params);

        $conflict = $q->fetch();

        if ($conflict) {
            throw new RuntimeException(
                'Contractor scheduling conflict with "' .
                $conflict['title'] .
                '" (' .
                $conflict['starts_at'] .
                ' - ' .
                $conflict['ends_at'] .
                ')'
            );
        }
    }
}

function check_contractor_availability(
    PDO $pdo,
    int $tenantId,
    array $contractorIds,
    string $startsAt,
    string $endsAt
): void {

    $start = new DateTime($startsAt);
    $end   = new DateTime($endsAt);

    /*
     * Availability rows are single-day rules.
     * Do not allow an appointment to cross midnight.
     */
    if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
        throw new RuntimeException(
            'Appointments assigned to contractors cannot cross midnight'
        );
    }

    $date      = $start->format('Y-m-d');
    $startTime = $start->format('H:i:s');
    $endTime   = $end->format('H:i:s');

    // PHP: Sunday=0 through Saturday=6.
    $weekday = (int)$start->format('w');

    foreach ($contractorIds as $contractorId) {

        /*
         * Determine whether this contractor has availability
         * configured at all. Contractors with no rules remain
         * unrestricted for backward compatibility.
         */
        $configured = $pdo->prepare("
            SELECT COUNT(*)
            FROM contractor_availability
            WHERE tenant_id=?
              AND contractor_id=?
        ");

        $configured->execute([
            $tenantId,
            $contractorId
        ]);

        if ((int)$configured->fetchColumn() === 0) {
            continue;
        }

        /*
         * Date-specific UNAVAILABLE rules take highest precedence.
         * Any overlap blocks the appointment.
         */
        $blocked = $pdo->prepare("
            SELECT id
            FROM contractor_availability
            WHERE tenant_id=?
              AND contractor_id=?
              AND available_date=?
              AND is_available=0
              AND starts_at < ?
              AND ends_at > ?
            LIMIT 1
        ");

        $blocked->execute([
            $tenantId,
            $contractorId,
            $date,
            $endTime,
            $startTime
        ]);

        if ($blocked->fetchColumn()) {
            throw new RuntimeException(
                'Contractor is unavailable during the requested time'
            );
        }

        /*
         * If date-specific AVAILABLE rules exist for this date,
         * they override the normal weekly schedule.
         */
        $dateRules = $pdo->prepare("
            SELECT COUNT(*)
            FROM contractor_availability
            WHERE tenant_id=?
              AND contractor_id=?
              AND available_date=?
              AND is_available=1
        ");

        $dateRules->execute([
            $tenantId,
            $contractorId,
            $date
        ]);

        if ((int)$dateRules->fetchColumn() > 0) {

            $allowed = $pdo->prepare("
                SELECT id
                FROM contractor_availability
                WHERE tenant_id=?
                  AND contractor_id=?
                  AND available_date=?
                  AND is_available=1
                  AND starts_at <= ?
                  AND ends_at >= ?
                LIMIT 1
            ");

            $allowed->execute([
                $tenantId,
                $contractorId,
                $date,
                $startTime,
                $endTime
            ]);

            if (!$allowed->fetchColumn()) {
                throw new RuntimeException(
                    'Appointment is outside contractor availability'
                );
            }

            continue;
        }

        /*
         * Otherwise require the appointment to fit completely
         * inside a recurring weekly AVAILABLE rule.
         */
        $weekly = $pdo->prepare("
            SELECT id
            FROM contractor_availability
            WHERE tenant_id=?
              AND contractor_id=?
              AND available_date IS NULL
              AND weekday=?
              AND is_available=1
              AND starts_at <= ?
              AND ends_at >= ?
            LIMIT 1
        ");

        $weekly->execute([
            $tenantId,
            $contractorId,
            $weekday,
            $startTime,
            $endTime
        ]);

        if (!$weekly->fetchColumn()) {
            throw new RuntimeException(
                'Appointment is outside contractor availability'
            );
        }
    }
}

function validate_times(string $startsAt, string $endsAt): void
{
    if (strtotime($endsAt) <= strtotime($startsAt)) {
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

    $start = $_GET['start'] ?? date('Y-m-01 00:00:00');
    $end   = $_GET['end']   ?? date('Y-m-t 23:59:59');

    $q = $pdo->prepare("
        SELECT
            a.*,
            c.first_name client_first,
            c.last_name client_last,
            s.name service_name,
            s.price service_price,
            s.duration_minutes,
            GROUP_CONCAT(DISTINCT ac.contractor_id) contractor_ids,
            GROUP_CONCAT(DISTINCT ct.color) contractor_colors
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

        WHERE
            a.tenant_id=?
            AND a.starts_at < ?
            AND a.ends_at > ?

        GROUP BY a.id
        ORDER BY a.starts_at
    ");

    $q->execute([$tid, $end, $start]);

    json_response([
        'appointments' => $q->fetchAll()
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
    'scheduler',
    'contractor'
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

        $status = validate_appointment_status(
            $d['status'] ?? 'scheduled'
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
            VALUES(?,?,?,?,?,?,?,?,?,?,?)
        ");

        $q->execute([
            $tid,
            $clientId,
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
            'id' => $id
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
            SELECT id
            FROM appointments
            WHERE id=? AND tenant_id=?
        ");

        $owned->execute([$id, $tid]);

        if (!$owned->fetchColumn()) {
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

        $status = validate_appointment_status(
            $d['status'] ?? 'scheduled'
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

        $pdo->prepare("
            DELETE FROM appointment_contractors
            WHERE appointment_id=?
        ")->execute([$id]);

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

        json_response(['ok' => true]);

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

    $q = $pdo->prepare("
        DELETE FROM appointments
        WHERE id=? AND tenant_id=?
    ");

    $q->execute([$id, $tid]);

    if (!$q->rowCount()) {
        json_response([
            'error' => 'Appointment not found'
        ], 404);
    }

    json_response(['ok' => true]);
}

json_response([
    'error' => 'Method not allowed'
], 405);
