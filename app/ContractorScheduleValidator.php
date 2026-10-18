<?php
declare(strict_types=1);

/**
 * Shared contractor scheduling validation.
 * Extracted from api/appointments.php.
 */

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
