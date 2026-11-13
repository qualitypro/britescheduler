<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/MigrationPreflight.php';
use BriteScheduler\Installation\MigrationPreflight;

$root = sys_get_temp_dir() . '/brite-preflight-' . bin2hex(random_bytes(8));
mkdir($root . '/migrations', 0700, true);
try {
    $path = $root . '/migrations/001_test.sql';
    file_put_contents($path, 'CREATE TABLE test_table (id INT);');
    $entry = ['version' => '001_test', 'sha256' => hash_file('sha256', $path)];
    $result = MigrationPreflight::inspect($root, [$entry]);
    if (count($result) !== 1 || $result[0]['bytes'] < 1) throw new RuntimeException('Valid file rejected');
    file_put_contents($path, 'CREATE TABLE changed_table (id INT);');
    try {
        MigrationPreflight::inspect($root, [$entry]);
        throw new RuntimeException('Checksum drift was accepted');
    } catch (DomainException $expected) {}
    file_put_contents($path, 'LOAD DATA LOCAL INFILE "test" INTO TABLE x;');
    $entry['sha256'] = hash_file('sha256', $path);
    try {
        MigrationPreflight::inspect($root, [$entry]);
        throw new RuntimeException('Unsupported SQL was accepted');
    } catch (DomainException $expected) {}
    echo "PASS: migration preflight integrity and unsupported SQL checks\n";
} finally {
    foreach (glob($root . '/migrations/*') ?: [] as $file) unlink($file);
    rmdir($root . '/migrations');
    rmdir($root);
}
