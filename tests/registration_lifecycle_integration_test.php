<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Registration/Policy.php';
require_once dirname(__DIR__) . '/app/Registration/ApplicationLifecycle.php';
use BriteScheduler\Registration\ApplicationLifecycle;

function expectDenied(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException $expected) { return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
ApplicationLifecycle::assertTransition('pending_email', 'pending_review');
ApplicationLifecycle::assertTransition('pending_review', 'approved');
expectDenied(static fn() => ApplicationLifecycle::assertTransition('pending_email', 'approved'), 'unverified email approval');
expectDenied(static fn() => ApplicationLifecycle::assertTransition('rejected', 'approved'), 'rejected application activation');
expectDenied(static fn() => ApplicationLifecycle::assertApproval('contractor', ['email'], true, true), 'contractor email alone');
expectDenied(static fn() => ApplicationLifecycle::assertApproval('contractor', ['email','phone'], true, true), 'contractor missing ID');
expectDenied(static fn() => ApplicationLifecycle::assertApproval('contractor', ['email','government_id'], true, false), 'contractor without moderator');
expectDenied(static fn() => ApplicationLifecycle::assertApproval('client', [], true, true), 'client without email');
echo "PASS: enrollment transitions and contractor trust gates\n";
