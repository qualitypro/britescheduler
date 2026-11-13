<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Registration/DocumentHoldService.php';

use BriteScheduler\Registration\DocumentHoldService;

require_method('POST');
$user = Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin');
verify_csrf();
header('Cache-Control: no-store');
$data = request_data();
$id = filter_var($data['document_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = $data['action'] ?? null;
$reason = $data['reason'] ?? null;
if (!$id || !in_array($action, ['hold', 'release'], true) || !is_string($reason)) {
    json_response(['error' => 'Invalid hold request'], 422);
}
try {
    DocumentHoldService::set(Database::connection(), (int)$tenant['tenant_id'],
        (int)$user['id'], (int)$id, $action === 'hold', $reason);
    json_response(['ok' => true]);
} catch (DomainException $error) {
    json_response(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Document hold unavailable: ' . get_class($error));
    json_response(['error' => 'Document hold unavailable'], 503);
}
