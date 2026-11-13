<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use PDO;

/**
 * Explicit operator-only stale claim recovery.
 * A crash after mail() accepted the message can cause duplicate delivery.
 * Never call automatically or from a public web endpoint.
 */
final class OutboxRecovery
{
    public static function retryStale(PDO $pdo, int $id): bool
    {
        if (PHP_SAPI !== 'cli' || $id < 1) {
            throw new DomainException('Recovery requires CLI and a positive message ID');
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare("SELECT o.id, o.body_ciphertext, o.claimed_at, o.attempts,
                    t.expires_at, t.consumed_at
                FROM registration_email_outbox o
                INNER JOIN registration_tokens t
                    ON t.application_id = o.application_id AND t.purpose = 'email_verify'
                WHERE o.id = ? AND o.status = 'sending'
                ORDER BY t.id DESC LIMIT 1 FOR UPDATE");
            $q->execute([$id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['body_ciphertext']) || (int)$row['attempts'] >= 5 ||
                $row['consumed_at'] !== null ||
                strtotime((string)$row['expires_at'] . ' UTC') <= time() ||
                strtotime((string)$row['claimed_at'] . ' UTC') > time() - 900) {
                $pdo->rollBack();
                return false;
            }
            $q = $pdo->prepare("UPDATE registration_email_outbox
                SET status = 'failed', available_at = UTC_TIMESTAMP(),
                    claimed_at = NULL, last_error = 'operator_requeued'
                WHERE id = ? AND status = 'sending'");
            $q->execute([$id]);
            $changed = $q->rowCount() === 1;
            $pdo->commit();
            return $changed;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
