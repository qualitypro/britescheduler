<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Registration/EmailEnvelope.php';

use BriteScheduler\Registration\EmailEnvelope;

$body = "Verification token: " . bin2hex(random_bytes(32));
try {
    $ciphertext = EmailEnvelope::encrypt($body);
    if ($ciphertext === $body || str_contains($ciphertext, $body) ||
        EmailEnvelope::decrypt($ciphertext) !== $body) {
        throw new RuntimeException('Email envelope round-trip failed');
    }
    $raw = base64_decode($ciphertext, true);
    $raw[30] = chr(ord($raw[30]) ^ 1);
    try {
        EmailEnvelope::decrypt(base64_encode($raw));
        throw new RuntimeException('Tampered ciphertext was accepted');
    } catch (RuntimeException $expected) {
        if ($expected->getMessage() === 'Tampered ciphertext was accepted') throw $expected;
    }
    echo "PASS: authenticated enrollment email envelope\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL: " . $exception->getMessage() . "\n");
    exit(1);
}
