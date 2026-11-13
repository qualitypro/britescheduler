<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;

/**
 * Domain transition rules. Authorization, audit writes and persistence belong
 * to the application service, not this pure validator.
 */
final class ApplicationLifecycle
{
    private const TRANSITIONS = [
        'pending_email' => ['pending_review', 'withdrawn'],
        'pending_review' => ['action_required', 'approved', 'rejected', 'withdrawn'],
        'action_required' => ['pending_review', 'rejected', 'withdrawn'],
        'approved' => [],
        'rejected' => [],
        'withdrawn' => [],
    ];

    public static function assertTransition(string $from, string $to): void
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainException('Registration state transition is not allowed');
        }
    }

    public static function assertApproval(
        string $type,
        array $verified,
        bool $manualApproval,
        bool $moderatorAuthorized
    ): void {
        $required = Policy::requirements($type);
        foreach ($required as $requirement) {
            if (!in_array($requirement, $verified, true)) {
                throw new DomainException('Required verification is incomplete');
            }
        }
        if (($type !== 'client' || $manualApproval) && !$moderatorAuthorized) {
            throw new DomainException('Authorized moderator approval is required');
        }
    }
}
