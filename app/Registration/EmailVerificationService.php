<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use PDO;

/**
 * Consume an emailed verification token exactly once.
 * No account provisioning, login, or moderation approval occurs here.
 */
final class EmailVerificationService
{
    public static function consume(PDO $pdo, string $token): int
    {
        $hash = VerificationTokens::hash($token);
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare(
                "SELECT t.id, t.application_id, t.expires_at, t.consumed_at, a.status
                 FROM registration_tokens t
                 INNER JOIN registration_applications a ON a.id = t.application_id
                 WHERE t.token_hash = ? AND t.purpose = 'email_verify' FOR UPDATE"
            );
            $q->execute([$hash]);
            $record = $q->fetch(PDO::FETCH_ASSOC);
            if (!$record || $record['consumed_at'] !== null ||
                strtotime((string)$record['expires_at'] . ' UTC') <= time() ||
                $record['status'] !== 'pending_email') {
                throw new DomainException('Verification link is invalid or expired');
            }
            ApplicationLifecycle::assertTransition('pending_email', 'pending_review');
            $q = $pdo->prepare('UPDATE registration_tokens SET consumed_at = UTC_TIMESTAMP() WHERE id = ? AND consumed_at IS NULL');
            $q->execute([(int)$record['id']]);
            if ($q->rowCount() !== 1) {
                throw new DomainException('Verification link has already been used');
            }
            $q = $pdo->prepare(
                "UPDATE registration_applications SET email_verified_at = UTC_TIMESTAMP(), status = 'pending_review'
                 WHERE id = ? AND status = 'pending_email'"
            );
            $q->execute([(int)$record['application_id']]);
            if ($q->rowCount() !== 1) {
                throw new DomainException('Enrollment state has changed');
            }
            $q = $pdo->prepare(
                "INSERT INTO registration_verifications (application_id, verification_type, status, verified_at)
                 VALUES (?, 'email', 'verified', UTC_TIMESTAMP())"
            );
            $q->execute([(int)$record['application_id']]);
            $q = $pdo->prepare(
                "INSERT INTO registration_reviews (application_id, action) VALUES (?, 'email_verified')"
            );
            $q->execute([(int)$record['application_id']]);
            $pdo->commit();
            return (int)$record['application_id'];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
