<?php
declare(strict_types=1);
$root = dirname(__DIR__);
foreach ([
    'InstallationMutex','MigrationInventory','InstallationStatus','MigrationPlan',
    'MigrationPreflight','SqlStatements','MigrationRunner','FirstRunInstaller',
] as $name) {
    require_once $root . '/app/Installation/' . $name . '.php';
}
use BriteScheduler\Installation\FirstRunInstaller;
$env = $root . '/.env';
if (file_exists($env) || is_link($env)) throw new RuntimeException('Integration test refuses existing .env');
$host = getenv('BRITE_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('BRITE_TEST_DB_PORT') ?: '3306';
$db = getenv('BRITE_TEST_DB_NAME') ?: 'brite_install_test';
$user = getenv('BRITE_TEST_DB_USER') ?: 'root';
$pass = getenv('BRITE_TEST_DB_PASS') ?: '';
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
if ($tables !== 0) throw new RuntimeException('Integration test requires an empty disposable database');
file_put_contents($env, "APP_ENV=test\nDB_HOST=$host\nDB_PORT=$port\nDB_NAME=$db\nDB_USER=$user\nDB_PASS=$pass\n");
chmod($env, 0600);
try {
    file_put_contents($root . '/.brite-migration-recovery-required', "test-marker\n");
    $blocked = false;
    try {
        FirstRunInstaller::install($root, $pdo, [
            'email'=>'initial-owner@example.test', 'first_name'=>'Initial','last_name'=>'Owner',
            'password'=>'long-test-only-password','tenant_name'=>'Test Tenant','tenant_slug'=>'test-tenant',
        ], true);
    } catch (RuntimeException $expected) { $blocked = true; }
    if (!$blocked) throw new RuntimeException('Recovery marker was ignored');
    unlink($root . '/.brite-migration-recovery-required');
    $result = FirstRunInstaller::install($root, $pdo, [
        'email'=>'initial-owner@example.test',
        'first_name'=>'Initial',
        'last_name'=>'Owner',
        'password'=>'long-test-only-password',
        'tenant_name'=>'Test Tenant',
        'tenant_slug'=>'test-tenant',
    ], true);
    if (!$result['installed'] || !is_file($root . '/.brite-installed.lock')) {
        throw new RuntimeException('Installer did not complete');
    }
    $storedHash = $pdo->query("SELECT password_hash FROM users WHERE email='initial-owner@example.test'")->fetchColumn();
    if (!is_string($storedHash) || !password_verify('long-test-only-password', $storedHash)) {
        throw new RuntimeException('Initial owner password was not hashed correctly');
    }
    if ((int)$pdo->query("SELECT COUNT(*) FROM tenant_memberships WHERE role='owner'")->fetchColumn() !== 1) {
        throw new RuntimeException('Initial owner membership missing');
    }
    try {
        FirstRunInstaller::install($root, $pdo, [
            'email'=>'other@example.test', 'first_name'=>'Other','last_name'=>'Owner',
            'password'=>'long-test-only-password','tenant_name'=>'Other','tenant_slug'=>'other',
        ], true);
        throw new RuntimeException('Reinstallation unexpectedly succeeded');
    } catch (DomainException $expected) {}
    echo "PASS: disposable MariaDB full migration, owner provisioning, lock and reinstall refusal\n";
} finally {
    @unlink($env);
    @unlink($root . '/.brite-installed.lock');
    @unlink($root . '/.brite-install.mutex');
    // Recovery marker intentionally retained if a migration failed.
}
