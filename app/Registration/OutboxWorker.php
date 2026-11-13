<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use PDO;
use RuntimeException;

/** CLI-only mail delivery. A failed attempt is retried with bounded backoff. */
final class OutboxWorker
{
    public static function run(PDO $pdo, int $limit = 20): array
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('Outbox worker requires CLI');
        $from = getenv('ENROLLMENT_MAIL_FROM') ?: '';
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Sender email is not configured');
        $sent = 0;
        $failed = 0;
        for ($i = 0; $i < max(1, min(100, $limit)); $i++) {
            $pdo->beginTransaction();
            try {
                $q = $pdo->query("SELECT id, recipient_email, subject, body_ciphertext, attempts
                    FROM registration_email_outbox WHERE status IN ('pending','failed')
                    AND available_at <= UTC_TIMESTAMP() AND attempts < 5
                    ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $pdo->commit();
                    break;
                }
                $q = $pdo->prepare("UPDATE registration_email_outbox SET status='sending', attempts=attempts+1, claimed_at=UTC_TIMESTAMP() WHERE id=?");
                $q->execute([$row['id']]);
                $pdo->commit();
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            $ok = false;
            try {
                $body = EmailEnvelope::decrypt($row['body_ciphertext']);
                $ok = mail($row['recipient_email'], $row['subject'], $body, [
                    'From' => $from,
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            } catch (\Throwable $error) {
                $ok = false;
            }
            $q = $pdo->prepare($ok
                ? "UPDATE registration_email_outbox SET status='sent', sent_at=UTC_TIMESTAMP(), body_ciphertext='', last_error=NULL WHERE id=? AND status='sending'"
                : "UPDATE registration_email_outbox SET status='failed', available_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE), last_error='transport_failed' WHERE id=? AND status='sending'");
            if ($ok) {
                $q->execute([$row['id']]);
                $sent++;
            } else {
                $delay = min(240, 5 * (2 ** min(5, (int)$row['attempts'])));
                $q->execute([$delay, $row['id']]);
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }
}
