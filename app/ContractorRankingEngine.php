<?php
declare(strict_types=1);

/**
 * Brite Scheduler V2 — Explainable Contractor Ranking.
 *
 * Advisory scoring only. Never assigns contractors.
 */
final class ContractorRankingEngine
{
    public static function rank(
        PDO $pdo,
        int $tenantId,
        string $startsAt,
        array $contractors,
        ?int $excludeAppointmentId = null
    ): array {
        if ($tenantId < 1) {
            throw new InvalidArgumentException('Invalid tenant ID');
        }

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $startsAt
        );

        if (!$start || $start->format('Y-m-d H:i:s') !== $startsAt) {
            throw new InvalidArgumentException('Invalid start time');
        }

        if ($excludeAppointmentId !== null && $excludeAppointmentId < 1) {
            throw new InvalidArgumentException('Invalid appointment ID');
        }

        $windowEnd = $start->modify('+7 days')->format('Y-m-d H:i:s');

        $sql = "
            SELECT
                COUNT(DISTINCT a.id) AS job_count,
                COALESCE(
                    SUM(
                        TIMESTAMPDIFF(
                            MINUTE,
                            a.starts_at,
                            a.ends_at
                        )
                    ),
                    0
                ) AS workload_minutes
            FROM appointments a
            INNER JOIN appointment_contractors ac
                ON ac.appointment_id = a.id
            WHERE a.tenant_id = ?
              AND ac.contractor_id = ?
              AND ac.status IN ('assigned', 'accepted')
              AND a.status IN (
                  'tentative',
                  'scheduled',
                  'confirmed',
                  'in_progress'
              )
              AND a.starts_at >= ?
              AND a.starts_at < ?
        ";

        if ($excludeAppointmentId !== null) {
            $sql .= " AND a.id <> ?";
        }

        $query = $pdo->prepare($sql);

        foreach ($contractors as &$contractor) {
            $contractorId = (int)($contractor['contractor_id'] ?? 0);

            if ($contractorId < 1) {
                throw new InvalidArgumentException('Invalid contractor ID');
            }

            $params = [
                $tenantId,
                $contractorId,
                $startsAt,
                $windowEnd
            ];

            if ($excludeAppointmentId !== null) {
                $params[] = $excludeAppointmentId;
            }

            $query->execute($params);
            $workload = $query->fetch(PDO::FETCH_ASSOC);

            $jobs = (int)($workload['job_count'] ?? 0);
            $minutes = (int)($workload['workload_minutes'] ?? 0);

            $qualificationPoints =
                !empty($contractor['service_qualified']) ? 35 : 0;

            $availabilityPoints =
                !empty($contractor['availability_ok']) &&
                !empty($contractor['conflict_free'])
                    ? 30 : 0;

            $geographicPoints =
                !empty($contractor['geographic_match']) ? 20 : 0;

            // Workload decreases smoothly with scheduled hours.
            $hours = max(0.0, $minutes / 60.0);
            $workloadPoints = round(15 / (1 + $hours / 10), 2);

            $eligible = !empty($contractor['eligible_for_review']);

            $score = round(
                $qualificationPoints +
                $availabilityPoints +
                $geographicPoints +
                $workloadPoints,
                2
            );

            $contractor['ranking'] = [
                'score' => $score,
                'eligible_for_review' => $eligible,
                'components' => [
                    'qualification' => $qualificationPoints,
                    'availability' => $availabilityPoints,
                    'geography' => $geographicPoints,
                    'workload' => $workloadPoints
                ],
                'workload' => [
                    'window_start' => $startsAt,
                    'window_end' => $windowEnd,
                    'jobs' => $jobs,
                    'hours' => round($hours, 2)
                ],
                'distance_miles' => null,
                'explanations' => [
                    'Service qualification: ' .
                        ($qualificationPoints > 0 ? 'verified' : 'not verified'),
                    'Scheduling: ' .
                        ($availabilityPoints > 0 ? 'available' : 'restricted'),
                    'Geographic coverage: ' .
                        ($geographicPoints > 0 ? 'matched' : 'not matched'),
                    'Workload: ' . round($hours, 2) .
                        ' scheduled hours in the ranking window'
                ],
                'automatic_assignment' => false
            ];
        }

        unset($contractor);

        usort(
            $contractors,
            static function (array $a, array $b): int {
                $aEligible = !empty($a['ranking']['eligible_for_review']);
                $bEligible = !empty($b['ranking']['eligible_for_review']);

                if ($aEligible !== $bEligible) {
                    return $aEligible ? -1 : 1;
                }

                $scoreComparison =
                    $b['ranking']['score'] <=> $a['ranking']['score'];

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                return (int)$a['contractor_id'] <=>
                    (int)$b['contractor_id'];
            }
        );

        return $contractors;
    }
}
