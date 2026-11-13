<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Registration/ApplicationLifecycle.php';
require_once dirname(__DIR__) . '/app/Registration/Policy.php';
require_once dirname(__DIR__) . '/app/Registration/ReviewService.php';

use BriteScheduler\Registration\ReviewService;

require_method('POST');
$user = Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin');
verify_csrf();
$data = request_data();
$applicationId = filter_var($data['application_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = $data['action'] ?? null;
$reason = $data['reason'] ?? null;
if (!$applicationId || !is_string($action) || !is_string($reason)) {
    json_response(['error' => 'Invalid enrollment request'], 422);
}
try {
    ReviewService::decide(Database::connection(), (int)$tenant['tenant_id'], (int)$user['id'], $applicationId, $action, $reason);
    json_response(['ok' => true]);
} catch (\DomainException $exception) {
    json_response(['error' => $exception->getMessage()], 422);
} catch (\Throwable $exception) {
    error_log('Enrollment decision failed: ' . $exception->getCode());
    json_response(['error' => 'Unable to process enrollment decision'], 500);
}
