<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/MigrationInventory.php';
require_once dirname(__DIR__) . '/app/Installation/InstallationStatus.php';

use BriteScheduler\Installation\InstallationStatus;

function assertStatus(bool $value, string $message): void
{
    if (!$value) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: PDO SQLite unavailable; database status test requires sqlite\n";
    exit(0);
}
$root = sys_get_temp_dir() . '/brite-install-status-' . bin2hex(random_bytes(8));
mkdir($root . '/migrations', 0700, true);
try {
    file_put_contents($root . '/migrations/001_first.sql', 'SELECT 1;');
    file_put_contents($root . '/migrations/002_second.sql', 'SELECT 2;');
    $pdo = new PDO('sqlite::memory:');
    // InstallationStatus targets MySQL information_schema; no SQLite integration assertions.
    $inventory = \BriteScheduler\Installation\MigrationInventory::list($root);
    assertStatus(count($inventory) === 2, 'migration inventory count');
    assertStatus($inventory[0]['version'] === '001_first', 'ordered migration inventory');
    assertStatus(strlen($inventory[0]['sha256']) === 64, 'SHA-256 inventory');
    echo "PASS: migration inventory checks\n";
} finally {
    foreach (glob($root . '/migrations/*') ?: [] as $file) unlink($file);
    rmdir($root . '/migrations');
    rmdir($root);
}
