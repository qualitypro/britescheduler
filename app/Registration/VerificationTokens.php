<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Stateless token primitives. Persist only hash(), never the plaintext token.
 * Consumption must be an atomic conditional database update in the caller.
 */
final class VerificationTokens
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hash(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new InvalidArgumentException('Invalid verification token format');
        }
        return hash('sha256', $token);
    }

    public static function matches(string $token, string $storedHash): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) ||
            !preg_match('/^[a-f0-9]{64}$/D', $storedHash)) {
            return false;
        }
        return hash_equals($storedHash, self::hash($token));
    }

    public static function isUsable(
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $consumedAt,
        DateTimeImmutable $now
    ): bool {
        return $consumedAt === null && $expiresAt > $now;
    }
}
