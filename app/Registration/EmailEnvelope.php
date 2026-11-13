<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use RuntimeException;

final class EmailEnvelope
{
    private static function key(): string
    {
        $encoded = getenv('ENROLLMENT_OUTBOX_KEY') ?: '';
        $key = base64_decode($encoded, true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('Enrollment outbox encryption key is not configured');
        }
        return $key;
    }

    public static function encrypt(string $body): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($body, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) throw new RuntimeException('Unable to encrypt enrollment email');
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('Invalid email envelope');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) throw new RuntimeException('Invalid email envelope authentication');
        return $plain;
    }
}
