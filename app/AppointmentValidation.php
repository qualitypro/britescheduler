<?php
declare(strict_types=1);

function appointment_datetime(string $value, string $field): string
{
    $value = trim($value);

    // Appointment DATETIME values are tenant-local wall times.
    // Reject offset-bearing input rather than silently discarding
    // its timezone offset.
    $formats = [
        '!Y-m-d H:i:s',
        '!Y-m-d\TH:i:s',
        '!Y-m-d\TH:i'
    ];

    foreach ($formats as $format) {
        // Parse wall-clock fields independently of the PHP host
        // timezone; tenant-local DST validation happens later.
        $dt = DateTimeImmutable::createFromFormat(
            $format,
            $value,
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (!$dt ||
            ($errors !== false &&
             ($errors['warning_count'] || $errors['error_count']))) {
            continue;
        }

        $comparisonFormat = ltrim($format, '!');

        if ($dt->format($comparisonFormat) !== $value) {
            continue;
        }

        $year = (int)$dt->format('Y');

        if ($year < 2000 || $year > 9999) {
            continue;
        }

        return $dt->format('Y-m-d H:i:s');
    }

    throw new RuntimeException("Invalid {$field}");
}

function validate_appointment_dst(
    PDO $pdo,
    int $tenantId,
    string $startsAt,
    string $endsAt,
    ?string $existingStart = null,
    ?string $existingEnd = null
): void {
    $q = $pdo->prepare(
        'SELECT timezone FROM tenants WHERE id=?'
    );
    $q->execute([$tenantId]);

    $timezoneName = $q->fetchColumn();

    if (!$timezoneName) {
        throw new RuntimeException('Tenant timezone not configured');
    }

    try {
        $timezone = new DateTimeZone((string)$timezoneName);
    } catch (Throwable $e) {
        throw new RuntimeException('Invalid tenant timezone');
    }

    if (!(
        $existingStart !== null &&
        $existingEnd !== null &&
        $startsAt === $existingStart &&
        $endsAt === $existingEnd
    )) {
        $startDate = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $startsAt,
            $timezone
        );

        $endDate = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $endsAt,
            $timezone
        );

        if ($startDate && $endDate &&
            $startDate->format('Y-m-d H:i:s') === $startsAt &&
            $endDate->format('Y-m-d H:i:s') === $endsAt &&
            $startDate->getOffset() !== $endDate->getOffset()) {
            throw new RuntimeException(
                'Appointments crossing a daylight-saving ' .
                'transition are not supported'
            );
        }
    }

    foreach ([
        ['start', $startsAt, $existingStart],
        ['end', $endsAt, $existingEnd]
    ] as [$field, $value, $existing]) {
        // Preserve unchanged historical appointment timestamps.
        if ($existing !== null && $value === $existing) {
            continue;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            $timezone
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (!$date ||
            ($errors !== false &&
             ($errors['warning_count'] || $errors['error_count'])) ||
            $date->format('Y-m-d H:i:s') !== $value) {
            throw new RuntimeException(
                "Invalid appointment {$field} date or time"
            );
        }

        // Interpret the local wall-clock fields as UTC solely to
        // calculate their numeric position around DST transitions.
        $wall = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new DateTimeZone('UTC')
        );

        $wallTimestamp = $wall->getTimestamp();

        $transitions = $timezone->getTransitions(
            $wallTimestamp - 3 * 86400,
            $wallTimestamp + 3 * 86400
        );

        if (!is_array($transitions)) {
            continue;
        }

        $previousOffset = null;

        foreach ($transitions as $transition) {
            $newOffset = (int)$transition['offset'];

            if ($previousOffset !== null &&
                $newOffset < $previousOffset) {
                $repeatedStart =
                    $transition['ts'] + $newOffset;
                $repeatedEnd =
                    $transition['ts'] + $previousOffset;

                if ($wallTimestamp >= $repeatedStart &&
                    $wallTimestamp < $repeatedEnd) {
                    throw new RuntimeException(
                        "Ambiguous appointment {$field} time " .
                        'during daylight-saving transition'
                    );
                }
            }

            $previousOffset = $newOffset;
        }
    }
}

function validate_future_appointment_start(
    PDO $pdo,
    int $tenantId,
    string $startsAt,
    ?string $existingStart = null
): void {
    // Existing appointments may retain their original historical start.
    if ($existingStart !== null && $startsAt === $existingStart) {
        return;
    }

    $q = $pdo->prepare(
        'SELECT timezone FROM tenants WHERE id=?'
    );
    $q->execute([$tenantId]);

    $timezoneName = $q->fetchColumn();

    if (!$timezoneName) {
        throw new RuntimeException('Tenant timezone not configured');
    }

    try {
        $timezone = new DateTimeZone((string)$timezoneName);

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $startsAt,
            $timezone
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (!$start ||
            ($errors !== false &&
             ($errors['warning_count'] || $errors['error_count'])) ||
            $start->format('Y-m-d H:i:s') !== $startsAt ||
            (int)$start->format('Y') < 2000) {
            throw new RuntimeException(
                'Invalid appointment start date or time'
            );
        }

        $now = new DateTimeImmutable('now', $timezone);

        if ($start <= $now) {
            throw new RuntimeException(
                'Appointment start time must be in the future'
            );
        }
    } catch (RuntimeException $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new RuntimeException(
            'Invalid appointment timezone or start time'
        );
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
