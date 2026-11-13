<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/SqlStatements.php';
use BriteScheduler\Installation\SqlStatements;
$sql = "-- comment ;\nCREATE TABLE demo (name VARCHAR(40) DEFAULT 'a;b');\n/* block ; */ INSERT INTO demo VALUES ('x;y'); # tail ;\n";
$parts = SqlStatements::split($sql);
if (count($parts) !== 2 || !str_contains($parts[0], "'a;b'") ||
    !str_contains($parts[1], "'x;y'")) throw new RuntimeException('SQL split incorrect');
foreach (["SELECT 'unterminated", '/* unclosed', '/*!50000 DROP TABLE users */ SELECT 1;'] as $bad) {
    try { SqlStatements::split($bad); throw new RuntimeException('Unsafe SQL accepted'); }
    catch (DomainException $expected) {}
}
echo "PASS: migration SQL splitter handles quoted semicolons and rejects malformed SQL\n";
