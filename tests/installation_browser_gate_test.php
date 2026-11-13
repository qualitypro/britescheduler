<?php
declare(strict_types=1);
$root = dirname(__DIR__);
foreach ([
    'InstallerGate','SetupAuthorization','InstallationMutex','ConfigurationWriter',
    'MigrationInventory','InstallationStatus','MigrationPlan','MigrationPreflight',
    'SqlStatements','MigrationRunner','FirstRunInstaller','BrowserInstaller',
] as $name) require_once $root . '/app/Installation/' . $name . '.php';
use BriteScheduler\Installation\BrowserInstaller;
try {
    BrowserInstaller::complete($root, [], []);
    throw new RuntimeException('CLI bypassed browser installer authorization');
} catch (DomainException $expected) {}
echo "PASS: browser installer refuses CLI and missing session authorization\n";
