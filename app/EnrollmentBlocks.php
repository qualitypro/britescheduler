<?php
declare(strict_types=1);
/** Tenant-scoped enrollment eligibility checks; never use global email blocks. */
final class EnrollmentBlocks
{
    public static function isBlocked(PDO $db, int $tenantId, string $email): bool
    {
        $q = $db->prepare('SELECT 1 FROM enrollment_blocks WHERE tenant_id=? AND email=? AND unblocked_at IS NULL LIMIT 1');
        $q->execute([$tenantId, strtolower(trim($email))]);
        return (bool)$q->fetchColumn();
    }

    public static function assertAllowed(PDO $db, int $tenantId, string $email): void
    {
        if (self::isBlocked($db, $tenantId, $email)) {
            throw new DomainException('Enrollment is blocked for this email. Contact the business administrator.');
        }
    }
}
