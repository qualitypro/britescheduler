<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/ConfigurationWriter.php';
use BriteScheduler\Installation\ConfigurationWriter;
$root = sys_get_temp_dir() . '/brite-config-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) throw new RuntimeException('Cannot create config test root');
copy(dirname(__DIR__) . '/.env.example', $root . '/.env.example');
$valid = [
    'APP_URL'=>'https://scheduler.example.test',
    'DB_HOST'=>'127.0.0.1', 'DB_PORT'=>'3306', 'DB_NAME'=>'brite_test',
    'DB_USER'=>'brite_test', 'DB_PASS'=>'random-secret-123'
];
try {
    try {
        ConfigurationWriter::write($root, array_merge($valid, ['DB_PASS'=>"bad\nAPP_ENV=production"]));
        throw new RuntimeException('Newline injection accepted');
    } catch (DomainException $expected) {}
    if (file_exists($root . '/.env')) throw new RuntimeException('Rejected config was written');
    try {
        ConfigurationWriter::write($root, array_merge($valid, ['DB_HOST'=>'127.0.0.1;dbname=other']));
        throw new RuntimeException('DSN delimiter injection accepted');
    } catch (DomainException $expected) {}
    ConfigurationWriter::write($root, $valid);
    $mode = fileperms($root . '/.env') & 0777;
    if ($mode !== 0600) throw new RuntimeException('Configuration permissions not private');
    $saved = file_get_contents($root . '/.env');
    if (!str_contains($saved, 'DB_PASS=random-secret-123')) throw new RuntimeException('Configuration value missing');
    try {
        ConfigurationWriter::write($root, $valid);
        throw new RuntimeException('Existing configuration overwritten');
    } catch (RuntimeException $expected) {}
    if (file_get_contents($root . '/.env') !== $saved) throw new RuntimeException('Existing config changed');
    echo "PASS: atomic configuration create, input rejection, private permissions and no overwrite\n";
} finally {
    @unlink($root . '/.env');
    @unlink($root . '/.env.example');
    @rmdir($root);
}
