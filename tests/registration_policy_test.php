<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Registration/Policy.php';
use BriteScheduler\Registration\Policy;
function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}
check(Policy::requirements('client') === ['email'], 'client minimum');
check(in_array('government_id', Policy::requirements('contractor'), true), 'contractor ID');
check(Policy::canApprove('client', ['email'], false), 'low risk client eligible');
check(!Policy::canApprove('client', [], false), 'unverified client blocked');
check(!Policy::canApprove('client', ['email'], true), 'manual approval gate');
check(!Policy::canApprove('contractor', ['email','phone','government_id'], false), 'contractor requires human review');
check(!Policy::canTransact('pending_review', true), 'pending cannot transact');
check(Policy::canTransact('approved', true), 'approved verified can transact');
echo "PASS: registration policy checks\n";
