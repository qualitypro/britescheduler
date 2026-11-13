<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Registration/DocumentReviewService.php';

use BriteScheduler\Registration\DocumentReviewService;

require_method('POST');
$user = Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin');
verify_csrf();
header('Cache-Control: no-store');
$data = request_data();
$id = filter_var($data['document_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$decision = $data['decision'] ?? '';
$reason = $data['reason'] ?? '';
if (!$id || !is_string($decision) || !is_string($reason)) {
    json_response(['error' => 'Invalid document review request'], 422);
}
try {
    DocumentReviewService::decide(
        Database::connection(), (int)$tenant['tenant_id'], (int)$user['id'],
        (int)$id, $decision, $reason
    );
    json_response(['ok' => true]);
} catch (DomainException $error) {
    json_response(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Document review unavailable: ' . get_class($error));
    json_response(['error' => 'Document review is unavailable'], 503);
}
