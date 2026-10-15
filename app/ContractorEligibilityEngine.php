<?php
declare(strict_types=1);

require_once __DIR__ . '/ServiceAreaMatcher.php';

/**
 * Read-only contractor eligibility evaluation.
 *
 * This class never assigns contractors or changes database records.
 * Service qualifications are checked against tenant-scoped records.
 */
final class ContractorEligibilityEngine
{
    public static function evaluate(
        PDO $pdo,
        int $tenantId,
        int $locationId,
        string $startsAt,
        string $endsAt,
        ?int $excludeAppointmentId = null,
        ?int $serviceId = null
    ): array {
        if ($tenantId < 1 || $locationId < 1) {
            throw new InvalidArgumentException('Invalid tenant or location');
        }

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $startsAt
        );
        $end = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $endsAt
        );

        if (
            !$start || !$end ||
            $start->format('Y-m-d H:i:s') !== $startsAt ||
            $end->format('Y-m-d H:i:s') !== $endsAt ||
            $end <= $start
        ) {
            throw new InvalidArgumentException('Invalid appointment interval');
        }

        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
            throw new InvalidArgumentException(
                'Contractor appointments cannot cross midnight'
            );
        }

        if ($excludeAppointmentId !== null) {
            if ($excludeAppointmentId < 1) {
                throw new InvalidArgumentException('Invalid appointment ID');
            }

            $check = $pdo->prepare(
                'SELECT id FROM appointments
                 WHERE id=? AND tenant_id=? LIMIT 1'
            );
            $check->execute([$excludeAppointmentId, $tenantId]);

            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('Appointment not found');
            }
        }

        if ($serviceId !== null) {
            if ($serviceId < 1) {
                throw new InvalidArgumentException('Invalid service ID');
            }

            $serviceQuery = $pdo->prepare(
                'SELECT id FROM services
                 WHERE id=? AND tenant_id=? AND active=1
                 LIMIT 1'
            );
            $serviceQuery->execute([$serviceId, $tenantId]);

            if (!$serviceQuery->fetchColumn()) {
                throw new InvalidArgumentException('Service not found or inactive');
            }
        }

        $qualificationQuery = null;

        if ($serviceId !== null) {
            $qualificationQuery = $pdo->prepare(
                'SELECT verification_status
                 FROM contractor_service_qualifications
                 WHERE tenant_id=? AND contractor_id=? AND service_id=?
                 LIMIT 1'
            );
        }

        $matches = ServiceAreaMatcher::matchLocation(
            $pdo,
            $tenantId,
            $locationId
        );

        $results = [];

        foreach ($matches as $match) {
            $contractorId = (int)$match['contractor_id'];

            $available = self::isAvailable(
                $pdo,
                $tenantId,
                $contractorId,
                $start,
                $end
            );

            $conflict = self::hasConflict(
                $pdo,
                $tenantId,
                $contractorId,
                $startsAt,
                $endsAt,
                $excludeAppointmentId
            );

            $qualificationStatus = 'unverified';

            if ($qualificationQuery !== null) {
                $qualificationQuery->execute([
                    $tenantId,
                    $contractorId,
                    $serviceId
                ]);

                $storedStatus = $qualificationQuery->fetchColumn();

                if ($storedStatus !== false) {
                    $qualificationStatus = (string)$storedStatus;
                }
            }

            $serviceQualified = (
                $serviceId !== null &&
                $qualificationStatus === 'verified'
            );

            $results[] = [
                'contractor_id' => $contractorId,
                'first_name' => $match['first_name'],
                'last_name' => $match['last_name'],
                'business_name' => $match['business_name'],
                'matching_areas' => $match['matching_areas'],
                'availability_ok' => $available,
                'conflict_free' => !$conflict,
                'geographic_match' => true,
                'qualification_status' => $qualificationStatus,
                'service_qualified' => $serviceQualified,
                'eligible_for_review' => (
                    $available &&
                    !$conflict &&
                    $qualificationStatus !== 'rejected'
                ),
                'assignment_approved' => false
            ];
        }

        return $results;
    }

    private static function hasConflict(
        PDO $pdo,
        int $tenantId,
        int $contractorId,
        string $startsAt,
        string $endsAt,
        ?int $excludeAppointmentId
    ): bool {
        $sql = "
            SELECT 1
            FROM appointments a
            INNER JOIN appointment_contractors ac
                ON ac.appointment_id=a.id
            WHERE a.tenant_id=?
              AND ac.contractor_id=?
              AND ac.status IN ('assigned','accepted')
              AND a.status IN (
                  'tentative',
                  'scheduled',
                  'confirmed',
                  'in_progress'
              )
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
            $sql .= ' AND a.id<>?';
            $params[] = $excludeAppointmentId;
        }

        $sql .= ' LIMIT 1';

        $q = $pdo->prepare($sql);
        $q->execute($params);

        return (bool)$q->fetchColumn();
    }

    private static function isAvailable(
        PDO $pdo,
        int $tenantId,
        int $contractorId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): bool {
        $date = $start->format('Y-m-d');
        $weekday = (int)$start->format('w');
        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');

        $q = $pdo->prepare(
            'SELECT available_date, weekday, starts_at,
                    ends_at, is_available
             FROM contractor_availability
             WHERE tenant_id=? AND contractor_id=?'
        );
        $q->execute([$tenantId, $contractorId]);
        $rules = $q->fetchAll(PDO::FETCH_ASSOC);

        // Preserve existing backward compatibility.
        if (!$rules) {
            return true;
        }

        $dateAvailable = [];

        foreach ($rules as $rule) {
            if (
                $rule['available_date'] === $date &&
                (int)$rule['is_available'] === 0 &&
                $rule['starts_at'] < $endTime &&
                $rule['ends_at'] > $startTime
            ) {
                return false;
            }

            if (
                $rule['available_date'] === $date &&
                (int)$rule['is_available'] === 1
            ) {
                $dateAvailable[] = $rule;
            }
        }

        if ($dateAvailable) {
            foreach ($dateAvailable as $rule) {
                if (
                    $rule['starts_at'] <= $startTime &&
                    $rule['ends_at'] >= $endTime
                ) {
                    return true;
                }
            }

            return false;
        }

        foreach ($rules as $rule) {
            if (
                $rule['available_date'] === null &&
                (int)$rule['weekday'] === $weekday &&
                (int)$rule['is_available'] === 1 &&
                $rule['starts_at'] <= $startTime &&
                $rule['ends_at'] >= $endTime
            ) {
                return true;
            }
        }

        return false;
    }
}
