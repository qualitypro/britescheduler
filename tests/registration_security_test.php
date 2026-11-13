<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Registration/Policy.php';
require_once dirname(__DIR__) . '/app/Registration/ApplicationLifecycle.php';
require_once dirname(__DIR__) . '/app/Registration/VerificationTokens.php';

use BriteScheduler\Registration\ApplicationLifecycle;
use BriteScheduler\Registration\VerificationTokens;

function assertTrue(bool $condition, string $description): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $description\n");
        exit(1);
    }
}

function rejects(callable $operation): bool
{
    try {
        $operation();
    } catch (\DomainException|\InvalidArgumentException $exception) {
        return true;
    }
    return false;
}

$token = VerificationTokens::generate();
$other = VerificationTokens::generate();
assertTrue(strlen($token) === 64, 'token length');
assertTrue($token !== $other, 'tokens are unique');
assertTrue(VerificationTokens::matches($token, VerificationTokens::hash($token)), 'valid token');
assertTrue(!VerificationTokens::matches($other, VerificationTokens::hash($token)), 'wrong token rejected');
assertTrue(!VerificationTokens::matches('not-a-token', VerificationTokens::hash($token)), 'invalid token rejected');
$now = new \DateTimeImmutable('2026-11-12T20:00:00+00:00');
assertTrue(VerificationTokens::isUsable($now->modify('+1 hour'), null, $now), 'fresh token');
assertTrue(!VerificationTokens::isUsable($now, null, $now), 'expired token');
assertTrue(!VerificationTokens::isUsable($now->modify('+1 hour'), $now, $now), 'used token');
ApplicationLifecycle::assertTransition('pending_email', 'pending_review');
assertTrue(rejects(fn() => ApplicationLifecycle::assertTransition('pending_email', 'approved')), 'cannot skip email');
assertTrue(rejects(fn() => ApplicationLifecycle::assertTransition('rejected', 'approved')), 'rejection terminal');
ApplicationLifecycle::assertApproval('client', ['email'], false, false);
assertTrue(rejects(fn() => ApplicationLifecycle::assertApproval('client', [], false, true)), 'unverified client denied');
assertTrue(rejects(fn() => ApplicationLifecycle::assertApproval('client', ['email'], true, false)), 'tenant manual review');
assertTrue(rejects(fn() => ApplicationLifecycle::assertApproval('contractor', ['email','phone','government_id'], false, false)), 'contractor moderator required');
ApplicationLifecycle::assertApproval('contractor', ['email','phone','government_id'], false, true);
echo "PASS: registration token and lifecycle checks\n";
