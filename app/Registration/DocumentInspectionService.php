<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use PDO;

/** Tenant-scoped, audited access to private enrollment documents. */
final class DocumentInspectionService
{
    public static function inspect(PDO $pdo, int $tenantId, int $reviewerId, int $documentId): array
    {
        if ($tenantId < 1 || $reviewerId < 1 || $documentId < 1) {
            throw new DomainException('Invalid inspection request');
        }
        $q = $pdo->prepare("SELECT d.id, d.application_id, d.storage_key, d.status
            FROM registration_documents d
            INNER JOIN registration_applications a ON a.id = d.application_id
            WHERE d.id = ? AND a.tenant_id = ? AND a.applicant_type = 'contractor'
              AND d.status IN ('pending', 'verified', 'rejected')
              AND d.deleted_at IS NULL LIMIT 1");
        $q->execute([$documentId, $tenantId]);
        $document = $q->fetch(PDO::FETCH_ASSOC);
        if (!$document) throw new DomainException('Document unavailable');
        // Read and authenticate the ciphertext before recording successful access.
        $bytes = DocumentVault::read((string)$document['storage_key']);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new DomainException('Unsupported document format');
        }
        $q = $pdo->prepare("INSERT INTO registration_document_audit
            (document_id, application_id, tenant_id, actor_user_id, action, reason)
            VALUES (?, ?, ?, ?, 'viewed', 'Authorized moderator inspection')");
        $q->execute([$documentId, $document['application_id'], $tenantId, $reviewerId]);
        return ['content' => $bytes, 'mime' => $mime];
    }
}
