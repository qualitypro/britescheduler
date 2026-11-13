<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/MigrationPlan.php';
use BriteScheduler\Installation\MigrationPlan;
$inventory = [
 ['version' => '001_core', 'sha256' => str_repeat('a', 64)],
 ['version' => '002_roles', 'sha256' => str_repeat('b', 64)],
 ['version' => '003_profiles', 'sha256' => str_repeat('c', 64)],
];
$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
};
$rejects = static function (callable $callback): bool {
    try { $callback(); } catch (DomainException $expected) { return true; }
    return false;
};
$plan = MigrationPlan::fromInventory($inventory, ['001_core']);
$assert($plan['applied_count'] === 1 && count($plan['pending']) === 2, 'ordered pending migrations');
$assert($plan['pending'][0]['version'] === '002_roles', 'first pending version');
$assert($rejects(static fn() => MigrationPlan::fromInventory($inventory, ['001_core', '003_profiles'])), 'history gaps rejected');
$assert($rejects(static fn() => MigrationPlan::fromInventory($inventory, ['999_unknown'])), 'unknown migrations rejected');
$assert($rejects(static fn() => MigrationPlan::fromInventory([$inventory[0], $inventory[0]], [])), 'duplicates rejected');
echo "PASS: migration history planning safeguards\n";
