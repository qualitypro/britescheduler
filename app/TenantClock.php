<?php
declare(strict_types=1);

/**
 * Tenant-local wall-clock values for appointment DATETIME comparisons.
 */
final class TenantClock
{
    public static function now(PDO $pdo, int $tenantId): string
    {
        $q = $pdo->prepare('SELECT timezone FROM tenants WHERE id = ? LIMIT 1');
        $q->execute([$tenantId]);
        $name = $q->fetchColumn();

        if (!is_string($name) || $name === '') {
            throw new RuntimeException('Tenant timezone not configured');
        }

        try {
            $timezone = new DateTimeZone($name);
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid tenant timezone', 0, $e);
        }

        return (new DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
    }

    public static function today(PDO $pdo, int $tenantId): string
    {
        return substr(self::now($pdo, $tenantId), 0, 10);
    }
}
