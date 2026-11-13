<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Registration/DocumentVault.php';

use BriteScheduler\Registration\DocumentVault;

$checks = 0;
$expectFailure = static function (callable $operation) use (&$checks): void {
    try {
        $operation();
    } catch (\DomainException|\RuntimeException $expected) {
        $checks++;
        return;
    }
    throw new \RuntimeException('Expected vault rejection');
};
$expectFailure(static fn() => DocumentVault::read('../etc/passwd'));
$expectFailure(static fn() => DocumentVault::read(str_repeat('a', 48)));
$expectFailure(static fn() => DocumentVault::store('/etc/hosts', 'not-uploaded.pdf'));
if ($checks !== 3) throw new \RuntimeException('Vault negative tests incomplete');
echo "PASS: document vault rejects invalid references and non-upload files\n";
