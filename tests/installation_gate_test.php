<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/InstallerGate.php';
use BriteScheduler\Installation\InstallerGate;
$root = sys_get_temp_dir() . '/brite-install-gate-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) throw new RuntimeException('Unable to prepare test directory');
try {
    putenv('BRITE_INSTALL_SETUP_TOKEN');
    $state = InstallerGate::inspect($root);
    if ($state['setup_may_begin']) throw new RuntimeException('Setup allowed without token');
    putenv('BRITE_INSTALL_SETUP_TOKEN=' . str_repeat('a', 32));
    if (!InstallerGate::inspect($root)['setup_may_begin']) throw new RuntimeException('Clean authorized setup blocked');
    touch($root . '/.env');
    if (InstallerGate::inspect($root)['setup_may_begin']) throw new RuntimeException('Existing configuration bypass');
    unlink($root . '/.env');
    touch($root . '/.brite-installed.lock');
    if (InstallerGate::inspect($root)['setup_may_begin']) throw new RuntimeException('Installed lock bypass');
    echo "PASS: installation state gate rejects unauthorized and existing installations\n";
} finally {
    @unlink($root . '/.env');
    @unlink($root . '/.brite-installed.lock');
    @rmdir($root);
    putenv('BRITE_INSTALL_SETUP_TOKEN');
}
