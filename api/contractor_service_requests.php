<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('contractor');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['error' => 'Method not allowed'], 405);
}

verify_csrf();

$data = request_data();

if (!is_array($data)) {
    json_response(['error' => 'Invalid request'], 422);
}

$serviceId = filter_var(
    $data['service_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$serviceId) {
    json_response(['error' => 'Valid service_id required'], 422);
}

$tid = (int)$tenant['tenant_id'];
$uid = (int)$user['id'];
$pdo = Database::connection();

$q = $pdo->prepare("
    SELECT id
    FROM contractors
    WHERE tenant_id = ?
      AND user_id = ?
      AND status = 'active'
    LIMIT 1
");
$q->execute([$tid, $uid]);

$contractorId = (int)($q->fetchColumn() ?: 0);

if (!$contractorId) {
    json_response(['error' => 'Contractor not found'], 403);
}

$q = $pdo->prepare("
    SELECT id
    FROM services
    WHERE tenant_id = ?
      AND id = ?
      AND active = 1
    LIMIT 1
");
$q->execute([$tid, $serviceId]);

if (!$q->fetchColumn()) {
    json_response(['error' => 'Active service not found'], 404);
}

// The tenant-scoped unique key prevents duplicate requests.
// Existing verified or rejected decisions are never overwritten.
$q = $pdo->prepare("
    INSERT IGNORE INTO contractor_service_qualifications (
        tenant_id,
        contractor_id,
        service_id,
        verification_status
    )
    VALUES (?, ?, ?, 'unverified')
");

$q->execute([$tid, $contractorId, $serviceId]);

$created = $q->rowCount() === 1;

$q = $pdo->prepare("
    SELECT verification_status
    FROM contractor_service_qualifications
    WHERE tenant_id = ?
      AND contractor_id = ?
      AND service_id = ?
    LIMIT 1
");
$q->execute([$tid, $contractorId, $serviceId]);

$status = $q->fetchColumn();

if ($status === false) {
    json_response(['error' => 'Request could not be recorded'], 500);
}

json_response([
    'ok' => true,
    'created' => $created,
    'service_id' => (int)$serviceId,
    'verification_status' => $status
], $created ? 201 : 200);
