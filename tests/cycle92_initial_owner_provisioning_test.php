<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/InitialOwnerProvisioner.php';

use BriteScheduler\Installation\InitialOwnerProvisioner;

$failures = 0;
$check = static function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) ++$failures;
};
$fixture = [
    'tenant_name' => 'Example Services',
    'tenant_slug' => 'example-services',
    'owner_email' => 'owner@example.test',
    'owner_first_name' => 'Example',
    'owner_last_name' => 'Owner',
    'owner_password' => 'Long-test-password-12345!',
];
$pdo = new PDO('sqlite::memory:');
foreach ([
    [$fixture, false, true, 'Requires explicit confirmation'],
    [$fixture, true, false, 'Requires installation mutex'],
    [array_merge($fixture, ['owner_email' => 'bad']), true, true, 'Rejects invalid email'],
    [array_merge($fixture, ['tenant_slug' => '../escape']), true, true, 'Rejects invalid slug'],
    [array_merge($fixture, ['owner_password' => 'short']), true, true, 'Rejects weak password'],
    [array_merge($fixture, ['timezone' => 'Not/A_Zone']), true, true, 'Rejects invalid timezone'],
] as [$input, $confirmed, $locked, $label]) {
    try {
        InitialOwnerProvisioner::provision($pdo, $input, $confirmed, $locked);
        $check(false, $label);
    } catch (DomainException $e) {
        $check(true, $label);
    }
}
$check(password_verify('Long-test-password-12345!', password_hash('Long-test-password-12345!', PASSWORD_DEFAULT)), 'Password hashing available');
exit($failures === 0 ? 0 : 1);
