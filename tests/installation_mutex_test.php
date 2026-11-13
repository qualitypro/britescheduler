<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/InstallationMutex.php';
use BriteScheduler\Installation\InstallationMutex;
$root = sys_get_temp_dir() . '/brite-mutex-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) throw new RuntimeException('Unable to prepare mutex test');
try {
    $first = new InstallationMutex();
    $second = new InstallationMutex();
    $first->acquire($root);
    $blocked = false;
    try { $second->acquire($root); } catch (RuntimeException $expected) { $blocked = true; }
    if (!$blocked) throw new RuntimeException('Concurrent installation lock accepted');
    $first->release();
    $second->acquire($root);
    $second->release();
    echo "PASS: installation mutex blocks concurrent operations and releases lock\n";
} finally {
    @unlink($root . '/.brite-install.mutex');
    @rmdir($root);
}
