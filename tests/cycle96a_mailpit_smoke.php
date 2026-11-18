<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/LocalMail.php';

if (PHP_SAPI !== 'cli') exit(1);
if ((getenv('MAIL_TRANSPORT') ?: '') !== 'mailpit') {
    fwrite(STDERR, "SKIP: set MAIL_TRANSPORT=mailpit in a development environment.\n");
    exit(2);
}
$recipient = 'cycle96a-' . bin2hex(random_bytes(4)) . '@example.test';
LocalMail::send(
    $recipient,
    'Brite Scheduler Cycle 96A local capture',
    "This is a local-only Mailpit smoke test. No real invitation tokens are included.\n",
    '<h1>Cycle 96A local capture</h1><p>Mailpit SMTP test only.</p>'
);
echo "PASS: Mailpit accepted test message for " . $recipient . "\n";
echo "Inspect the local inbox at http://127.0.0.1:8025\n";
