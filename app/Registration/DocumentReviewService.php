<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use PDO;

/**
 * Review an uploaded identity document without granting verified identity automatically.
 * Caller must authenticate a tenant owner/admin before invoking.
 */
final class DocumentReviewService
{
    public static function decide(PDO $pdo, int $tenantId, int $reviewerId, int $documentId, string $decision, string $reason): void
    {
        if ($tenantId < 1 || $reviewerId < 1 || $documentId < 1 ||
            !in_array($decision, ['verified', 'rejected'], true) ||
            mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 500) {
            throw new DomainException('Invalid document review');
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare("SELECT d.id, d.application_id, d.document_type, d.status, a.status AS application_status
                FROM registration_documents d
                INNER JOIN registration_applications a ON a.id = d.application_id
                WHERE d.id = ? AND a.tenant_id = ? AND a.applicant_type = 'contractor'
                FOR UPDATE");
            $q->execute([$documentId, $tenantId]);
            $doc = $q->fetch(PDO::FETCH_ASSOC);
            if (!$doc || $doc['status'] !== 'pending' ||
                !in_array($doc['application_status'], ['pending_review', 'action_required'], true)) {
                throw new DomainException('Document is not eligible for review');
            }
            $q = $pdo->prepare("UPDATE registration_documents SET status = ? WHERE id = ? AND status = 'pending'");
            $q->execute([$decision, $documentId]);
            if ($q->rowCount() !== 1) throw new DomainException('Document state changed');
            $q = $pdo->prepare("INSERT INTO registration_document_audit
                (document_id, application_id, tenant_id, actor_user_id, action, reason)
                VALUES (?, ?, ?, ?, ?, ?)");
            $q->execute([$documentId, $doc['application_id'], $tenantId, $reviewerId, $decision, trim($reason)]);
            // A document's review status is not equivalent to a trusted identity verification.
            // The registration_verifications table is intentionally not updated here.
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
