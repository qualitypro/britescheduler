<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/InstallationMutex.php';
require_once dirname(__DIR__) . '/app/Installation/FreshInstallOrchestrator.php';

use BriteScheduler\Installation\FreshInstallOrchestrator;

$failed = 0;
$check = static function(bool $ok, string $name) use (&$failed): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) ++$failed;
};
$root = sys_get_temp_dir() . '/brite92g-orchestrator-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
$pdo = new PDO('sqlite::memory:');
try {
    foreach ([
        [false, 'Requires explicit confirmation'],
        [true, 'Rejects non-MySQL connection'],
    ] as [$confirmed, $name]) {
        try {
            FreshInstallOrchestrator::install($root, $pdo, [], [], $confirmed);
            $check(false, $name);
        } catch (DomainException $e) {
            $check(true, $name);
        }
    }
    $check(!file_exists($root . '/.env'), 'Does not create configuration on guard failure');
    $check(!file_exists($root . '/.brite-installed.lock'), 'Does not finalize on guard failure');
} finally {
    @rmdir($root);
}
exit($failed ? 1 : 0);
