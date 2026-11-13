<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use PDO;

/** Tenant-scoped moderation changes; no account provisioning. */
final class ReviewService
{
    public static function decide(PDO $pdo, int $tenantId, int $reviewerId, int $applicationId, string $action, string $reason): void
    {
        $targets = ['request_info' => 'action_required', 'reject' => 'rejected'];
        if (!isset($targets[$action]) || $tenantId < 1 || $reviewerId < 1 || $applicationId < 1) {
            throw new DomainException('Invalid enrollment decision');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new DomainException('Provide a reason between 5 and 500 characters');
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT status FROM registration_applications WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $q->execute([$applicationId, $tenantId]);
            $current = $q->fetchColumn();
            if (!is_string($current)) {
                throw new DomainException('Enrollment application not found');
            }
            ApplicationLifecycle::assertTransition($current, $targets[$action]);
            $q = $pdo->prepare('UPDATE registration_applications SET status = ?, reviewed_at = UTC_TIMESTAMP(), reviewed_by = ? WHERE id = ? AND tenant_id = ?');
            $q->execute([$targets[$action], $reviewerId, $applicationId, $tenantId]);
            if ($q->rowCount() !== 1) {
                throw new DomainException('Enrollment decision was not applied');
            }
            $q = $pdo->prepare('INSERT INTO registration_reviews (application_id, reviewer_id, action, reason) VALUES (?, ?, ?, ?)');
            $q->execute([$applicationId, $reviewerId, $action === 'reject' ? 'rejected' : 'requested_info', $reason]);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
