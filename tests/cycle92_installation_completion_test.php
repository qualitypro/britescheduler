<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/InstallationCompletion.php';

use BriteScheduler\Installation\InstallationCompletion;

$failures = 0;
$check = static function (bool $condition, string $name) use (&$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$condition) ++$failures;
};
$root = sys_get_temp_dir() . '/brite92g-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
$pdo = new PDO('sqlite::memory:');
try {
    foreach ([
        [false, true, 'Rejects unconfirmed completion'],
        [true, false, 'Rejects missing mutex'],
        [true, true, 'Rejects non-MySQL connection'],
    ] as [$confirmed, $locked, $name]) {
        try {
            InstallationCompletion::finalize($root, $pdo, $confirmed, $locked);
            $check(false, $name);
        } catch (DomainException $e) {
            $check(true, $name);
        }
    }
    $check(!file_exists($root . '/.brite-installed.lock'), 'No completion marker after failed checks');
} finally {
    @rmdir($root);
}
exit($failures ? 1 : 0);
