<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use PDO;

/** Authorized tenant owner/admin can preserve or release a document for retention. */
final class DocumentHoldService
{
    public static function set(PDO $pdo, int $tenantId, int $actorId, int $documentId, bool $hold, string $reason): void
    {
        $reason = trim($reason);
        if ($tenantId < 1 || $actorId < 1 || $documentId < 1 ||
            mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new DomainException('A reason of 5–500 characters is required');
        }
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare("SELECT d.id, d.application_id, d.deleted_at, d.retention_hold
                FROM registration_documents d
                JOIN registration_applications a ON a.id = d.application_id
                WHERE d.id = ? AND a.tenant_id = ? AND a.applicant_type = 'contractor'
                FOR UPDATE");
            $query->execute([$documentId, $tenantId]);
            $doc = $query->fetch(PDO::FETCH_ASSOC);
            if (!$doc || $doc['deleted_at'] !== null || (bool)$doc['retention_hold'] === $hold) {
                throw new DomainException('Document hold state cannot be changed');
            }
            $query = $pdo->prepare("UPDATE registration_documents
                SET retention_hold = ?, retention_hold_reason = ?,
                    retention_hold_at = UTC_TIMESTAMP(), retention_hold_by = ?
                WHERE id = ? AND deleted_at IS NULL");
            $query->execute([$hold ? 1 : 0, $reason, $actorId, $documentId]);
            // Keep the event permanently even when the hold is subsequently released.
            $query = $pdo->prepare("INSERT INTO registration_document_audit
                (document_id, application_id, tenant_id, actor_user_id, action, reason)
                VALUES (?, ?, ?, ?, ?, ?)");
            $query->execute([
                $documentId, $doc['application_id'], $tenantId, $actorId,
                $hold ? 'hold_placed' : 'hold_released', $reason,
            ]);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
