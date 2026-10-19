<?php

declare(strict_types=1);

require_once __DIR__ . '/AppointmentValidation.php';
require_once __DIR__ . '/ContractorEligibilityEngine.php';

final class ClientBookingAvailability
{
    public const OPEN_MINUTE = 540;
    public const CLOSE_MINUTE = 1020;
    public const INTERVAL_MINUTES = 30;

    public static function isSlotAvailable(
        PDO $pdo,
        int $tenantId,
        int $locationId,
        int $serviceId,
        int $duration,
        string $startsAt
    ): bool {
        if (
            $tenantId < 1 ||
            $locationId < 1 ||
            $serviceId < 1 ||
            $duration < 1 ||
            $duration > 1440
        ) {
            return false;
        }

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $startsAt,
            new DateTimeZone('UTC')
        );

        if (
            !$start ||
            $start->format('Y-m-d H:i:s') !== $startsAt
        ) {
            return false;
        }

        $hour = (int)$start->format('H');
        $minute = (int)$start->format('i');
        $second = (int)$start->format('s');

        $startMinute = $hour * 60 + $minute;

        if (
            $second !== 0 ||
            $startMinute < self::OPEN_MINUTE ||
            $startMinute >= self::CLOSE_MINUTE ||
            $startMinute % self::INTERVAL_MINUTES !== 0 ||
            $startMinute + $duration > self::CLOSE_MINUTE
        ) {
            return false;
        }

        $endsAt = $start
            ->modify("+{$duration} minutes")
            ->format('Y-m-d H:i:s');

        try {
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
        } catch (RuntimeException $e) {
            return false;
        }

        $contractors = ContractorEligibilityEngine::evaluate(
            $pdo,
            $tenantId,
            $locationId,
            $startsAt,
            $endsAt,
            null,
            $serviceId
        );

        foreach ($contractors as $contractor) {
            if (!empty($contractor['eligible_for_review'])) {
                return true;
            }
        }

        return false;
    }

    public static function availableSlots(
        PDO $pdo,
        int $tenantId,
        int $locationId,
        int $serviceId,
        int $duration,
        string $date
    ): array {
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) ||
            $duration < 1 ||
            $duration > 1440
        ) {
            return [];
        }

        $slots = [];

        for (
            $minute = self::OPEN_MINUTE;
            $minute < self::CLOSE_MINUTE;
            $minute += self::INTERVAL_MINUTES
        ) {
            if ($minute + $duration > self::CLOSE_MINUTE) {
                continue;
            }

            $startsAt = sprintf(
                '%s %02d:%02d:00',
                $date,
                intdiv($minute, 60),
                $minute % 60
            );

            if (!self::isSlotAvailable(
                $pdo,
                $tenantId,
                $locationId,
                $serviceId,
                $duration,
                $startsAt
            )) {
                continue;
            }

            $end = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $startsAt,
                new DateTimeZone('UTC')
            );

            $endsAt = $end
                ->modify("+{$duration} minutes")
                ->format('Y-m-d H:i:s');

            $slots[] = [
                'starts_at' => str_replace(' ', 'T', $startsAt),
                'ends_at' => str_replace(' ', 'T', $endsAt),
                'time' => substr($startsAt, 11, 5)
            ];
        }

        return $slots;
    }
}
