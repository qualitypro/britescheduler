<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Registration/DocumentVault.php';
use BriteScheduler\Registration\DocumentVault;

require_method('POST');
verify_csrf();
header('Cache-Control: no-store');
$id = (int)($_SESSION['enrollment_document_application_id'] ?? 0);
$expires = (int)($_SESSION['enrollment_document_expires_at'] ?? 0);
if ($id < 1 || $expires <= time()) json_response(['error' => 'Upload session expired'], 403);
$type = $_POST['document_type'] ?? '';
$file = $_FILES['document'] ?? null;
if (!is_string($type) || !in_array($type, ['government_id', 'license', 'insurance', 'certification'], true) ||
    !is_array($file) || ($file['error'] ?? -1) !== UPLOAD_ERR_OK ||
    !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null) ||
    !is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > 10 * 1024 * 1024) {
    json_response(['error' => 'Invalid document or upload size'], 422);
}
$pdo = Database::connection();
$storageKey = null;
try {
    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT id, tenant_id FROM registration_applications
        WHERE id = ? AND applicant_type = 'contractor' AND email_verified_at IS NOT NULL
          AND status IN ('pending_review', 'action_required') FOR UPDATE");
    $q->execute([$id]);
    $application = $q->fetch(PDO::FETCH_ASSOC);
    if (!$application) throw new DomainException('Enrollment is not eligible for document upload');
    $q = $pdo->prepare("SELECT COUNT(*) FROM registration_documents
        WHERE application_id = ? AND status <> 'deleted'");
    $q->execute([$id]);
    if ((int)$q->fetchColumn() >= 12) throw new DomainException('Document submission limit reached');
    $storageKey = DocumentVault::store($file['tmp_name'], $file['name']);
    $q = $pdo->prepare("INSERT INTO registration_documents
        (application_id, document_type, storage_key, status)
        VALUES (?, ?, ?, 'pending')");
    $q->execute([$id, $type, $storageKey]);
    $documentId = (int)$pdo->lastInsertId();
    $q = $pdo->prepare("INSERT INTO registration_document_audit
        (document_id, application_id, tenant_id, action, reason)
        VALUES (?, ?, ?, 'uploaded', 'Applicant submitted document')");
    $q->execute([$documentId, $id, $application['tenant_id']]);
    $pdo->commit();
    json_response(['ok' => true, 'message' => 'Document received for manual review'], 201);
} catch (DomainException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($storageKey !== null) DocumentVault::discard($storageKey);
    json_response(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($storageKey !== null) {
        try { DocumentVault::discard($storageKey); } catch (Throwable $ignored) {}
    }
    error_log('Enrollment document upload failed: ' . get_class($error));
    json_response(['error' => 'Document upload unavailable'], 503);
}
