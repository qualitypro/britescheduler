<?php
declare(strict_types=1);

/**
 * Shared, side-effect-free validation for Smart Dispatch query parameters.
 * Authorization and tenant ownership checks remain in the API endpoints.
 */
final class DispatchRequest
{
    public static function positiveId(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    public static function localDatetime(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        foreach (['!Y-m-d H:i:s', '!Y-m-d\\TH:i:s', '!Y-m-d\\TH:i'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date && $date->format(substr($format, 1)) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    public static function validInterval(?string $start, ?string $end): bool
    {
        if ($start === null || $end === null || $end <= $start) return false;
        return substr($start, 0, 10) === substr($end, 0, 10);
    }
}
