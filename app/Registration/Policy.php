<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

/** Pure, conservative eligibility rules; never grants permissions by itself. */
final class Policy
{
    public static function requirements(string $type): array
    {
        return match ($type) {
            'client' => ['email'],
            'contractor' => ['email', 'phone', 'government_id'],
            'business' => ['email', 'phone', 'authorized_representative_id', 'business_details'],
            default => throw new \InvalidArgumentException('Unsupported registration type'),
        };
    }

    public static function canApprove(string $type, array $verified, bool $manualApproval): bool
    {
        foreach (self::requirements($type) as $requirement) {
            if (!in_array($requirement, $verified, true)) {
                return false;
            }
        }
        // Contractor/business approval always requires a moderator.
        return $type === 'client' && !$manualApproval;
    }

    public static function canTransact(string $status, bool $emailVerified): bool
    {
        return $status === 'approved' && $emailVerified;
    }
}
