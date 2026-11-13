<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use PDO;
use RuntimeException;

/** DB-backed fixed-window enrollment limiter, keyed with a server secret. */
final class EnrollmentThrottle
{
    public static function check(PDO $pdo, string $remoteAddress, string $email): void
    {
        $secret = getenv('ENROLLMENT_RATE_SECRET') ?: '';
        if (strlen($secret) < 32) {
            throw new RuntimeException('Enrollment throttling is not configured');
        }
        foreach ([
            ['ip:' . $remoteAddress, 10],
            ['email:' . strtolower($email), 5],
        ] as [$identity, $limit]) {
            $key = hash_hmac('sha256', $identity, $secret);
            $q = $pdo->prepare(
                'INSERT INTO enrollment_rate_limits (bucket_key, window_started_at, attempts)
                 VALUES (?, UTC_TIMESTAMP(), 1)
                 ON DUPLICATE KEY UPDATE
                   attempts = IF(window_started_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR, 1, attempts + 1),
                   window_started_at = IF(window_started_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR, UTC_TIMESTAMP(), window_started_at)'
            );
            $q->execute([$key]);
            $q = $pdo->prepare('SELECT attempts FROM enrollment_rate_limits WHERE bucket_key = ?');
            $q->execute([$key]);
            if ((int)$q->fetchColumn() > $limit) {
                throw new \DomainException('Too many enrollment requests. Please try again later.');
            }
        }
    }
}
