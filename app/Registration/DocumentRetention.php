<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use PDO;
use RuntimeException;

/**
 * CLI-only retention cleanup. Preserve document and audit rows after vault deletion.
 * Never disclose storage keys or decrypted content in operator output.
 */
final class DocumentRetention
{
    public static function purge(PDO $pdo, int $documentId): bool
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('Retention cleanup is CLI only');
        if ($documentId < 1) throw new DomainException('Invalid document reference');

        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare("SELECT d.id, d.application_id, d.storage_key, d.status,
                a.tenant_id
                FROM registration_documents d
                INNER JOIN registration_applications a ON a.id = d.application_id
                WHERE d.id = ? AND d.deleted_at IS NULL
                  AND d.retention_hold = 0
                  AND d.delete_after IS NOT NULL AND d.delete_after <= UTC_TIMESTAMP()
                  AND d.status IN ('pending','verified','rejected')
                FOR UPDATE");
            $query->execute([$documentId]);
            $doc = $query->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                $pdo->rollBack();
                return false;
            }
            // Filesystem deletion is not transactional. A failed database commit can be
            // retried because discard() is idempotent for a missing vault object.
            DocumentVault::discard((string)$doc['storage_key']);
            $query = $pdo->prepare("UPDATE registration_documents
                SET status = 'deleted', deleted_at = UTC_TIMESTAMP()
                WHERE id = ? AND deleted_at IS NULL");
            $query->execute([$documentId]);
            if ($query->rowCount() !== 1) throw new RuntimeException('Document state changed');
            $query = $pdo->prepare("INSERT INTO registration_document_audit
                (document_id, application_id, tenant_id, action, reason)
                VALUES (?, ?, ?, 'deleted', 'Retention deadline reached')");
            $query->execute([$documentId, $doc['application_id'], $doc['tenant_id']]);
            $pdo->commit();
            return true;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
