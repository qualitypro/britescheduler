<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::tenant();

$tid = (int)$tenant['tenant_id'];
$uid = (int)$user['id'];
$role = (string)$tenant['role'];

$pdo = Database::connection();

$isManager = in_array(
    $role,
    ['owner', 'admin', 'scheduler'],
    true
);

if (!$isManager && $role !== 'contractor') {
    json_response(['error' => 'Forbidden'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'PUT'], true)) {
    header('Allow: GET, PUT');
    json_response(['error' => 'Method not allowed'], 405);
}

if ($method !== 'GET') {
    verify_csrf();
}

$data = $method === 'GET' ? $_GET : request_data();

if (!is_array($data)) {
    json_response(['error' => 'Invalid request'], 422);
}

if ($role === 'contractor') {
    // Contractors may access only their own profile.
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

    if ($contractorId === 0) {
        json_response(['error' => 'Contractor not found'], 403);
    }
} else {
    $contractorId = filter_var(
        $data['contractor_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$contractorId) {
        json_response(['error' => 'Contractor ID required'], 422);
    }

    $q = $pdo->prepare("
        SELECT id
        FROM contractors
        WHERE tenant_id = ? AND id = ?
        LIMIT 1
    ");
    $q->execute([$tid, $contractorId]);

    if (!$q->fetchColumn()) {
        json_response(['error' => 'Contractor not found'], 404);
    }
}

if ($method === 'GET') {
    $q = $pdo->prepare("
        SELECT
            s.id AS service_id,
            s.name AS service_name,
            s.active AS service_active,
            q.verification_status,
            p.pricing_type,
            p.amount,
            p.active AS pricing_active
        FROM services s
        LEFT JOIN contractor_service_qualifications q
          ON q.tenant_id = s.tenant_id
         AND q.service_id = s.id
         AND q.contractor_id = ?
        LEFT JOIN contractor_service_pricing p
          ON p.tenant_id = s.tenant_id
         AND p.service_id = s.id
         AND p.contractor_id = ?
        WHERE s.tenant_id = ?
        ORDER BY s.name, s.id
    ");

    $q->execute([$contractorId, $contractorId, $tid]);

    json_response([
        'contractor_id' => $contractorId,
        'services' => $q->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// Only owners/admins and the contractor themselves can edit prices.
if ($isManager && !in_array($role, ['owner', 'admin'], true)) {
    json_response(['error' => 'Forbidden'], 403);
}

$serviceId = filter_var(
    $data['service_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$type = $data['pricing_type'] ?? null;
$amount = $data['amount'] ?? null;
$active = $data['active'] ?? 1;

if (!$serviceId ||
    !is_string($type) ||
    !in_array($type, ['hourly', 'flat'], true) ||
    !is_scalar($amount) ||
    is_bool($amount) ||
    !preg_match(
        '/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',
        (string)$amount
    ) ||
    !in_array($active, [0, 1, '0', '1'], true)
) {
    json_response(['error' => 'Invalid pricing details'], 422);
}

$amount = (string)$amount;

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

// Pricing does not grant a qualification.
// A contractor must have an existing qualification record
// before configuring a service-specific price.
$q = $pdo->prepare("
    SELECT verification_status
    FROM contractor_service_qualifications
    WHERE tenant_id = ?
      AND contractor_id = ?
      AND service_id = ?
    LIMIT 1
");

$q->execute([$tid, $contractorId, $serviceId]);
$qualification = $q->fetchColumn();

if ($qualification !== 'verified') {
    json_response([
        'error' => 'An eligible service qualification is required'
    ], 403);
}

$q = $pdo->prepare("
    INSERT INTO contractor_service_pricing (
        tenant_id,
        contractor_id,
        service_id,
        pricing_type,
        amount,
        active
    )
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        pricing_type = VALUES(pricing_type),
        amount = VALUES(amount),
        active = VALUES(active)
");

$q->execute([
    $tid,
    $contractorId,
    $serviceId,
    $type,
    $amount,
    (int)$active
]);

json_response([
    'ok' => true,
    'contractor_id' => $contractorId,
    'service_id' => (int)$serviceId,
    'pricing_type' => $type,
    'amount' => number_format((float)$amount, 2, '.', ''),
    'active' => (int)$active,
    'verification_status' => $qualification
]);
