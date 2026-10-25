<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::tenant();

$tid = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];
$uid = (int)$user['id'];

$pdo = Database::connection();

Auth::requireRole('owner', 'admin', 'scheduler');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $contractorId = filter_var(
        $_GET['contractor_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($contractorId === false || $contractorId === null) {
        json_response(['error' => 'Valid contractor_id required'], 422);
    }

    $q = $pdo->prepare("
        SELECT id
        FROM contractors
        WHERE tenant_id = ?
          AND id = ?
        LIMIT 1
    ");
    $q->execute([$tid, $contractorId]);

    if (!$q->fetchColumn()) {
        json_response(['error' => 'Contractor not found'], 404);
    }

    $q = $pdo->prepare("
        SELECT
            s.id AS service_id,
            s.name AS service_name,
            s.active AS service_active,
            q.id AS qualification_id,
            COALESCE(
                q.verification_status,
                'unverified'
            ) AS verification_status,
            q.verified_by,
            q.verified_at,
            q.updated_at
        FROM services s
        LEFT JOIN contractor_service_qualifications q
          ON q.tenant_id = s.tenant_id
         AND q.service_id = s.id
         AND q.contractor_id = ?
        WHERE s.tenant_id = ?
        ORDER BY s.name, s.id
    ");

    $q->execute([$contractorId, $tid]);

    json_response([
        'contractor_id' => (int)$contractorId,
        'qualifications' => $q->fetchAll()
    ]);
}

if ($method !== 'PUT') {
    header('Allow: GET, PUT');
    json_response(['error' => 'Method not allowed'], 405);
}

verify_csrf();

Auth::requireRole('owner', 'admin');

$d = request_data();

if (!is_array($d)) {
    json_response(['error' => 'Invalid request body'], 422);
}

$contractorId = filter_var(
    $d['contractor_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$serviceId = filter_var(
    $d['service_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$status = $d['verification_status'] ?? null;

if (
    !$contractorId ||
    !$serviceId ||
    !is_string($status) ||
    !in_array($status, ['unverified', 'verified', 'rejected'], true)
) {
    json_response(['error' => 'Invalid qualification payload'], 422);
}

$q = $pdo->prepare("
    SELECT id
    FROM contractors
    WHERE tenant_id = ?
      AND id = ?
    LIMIT 1
");
$q->execute([$tid, $contractorId]);

if (!$q->fetchColumn()) {
    json_response(['error' => 'Contractor not found'], 404);
}

$q = $pdo->prepare("
    SELECT id
    FROM services
    WHERE tenant_id = ?
      AND id = ?
    LIMIT 1
");
$q->execute([$tid, $serviceId]);

if (!$q->fetchColumn()) {
    json_response(['error' => 'Service not found'], 404);
}

$verifiedBy = $status === 'unverified' ? null : $uid;
$verifiedAt = $status === 'unverified'
    ? null
    : date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    /*
     * Coordinate qualification changes with marketplace
     * awards and appointment assignment operations.
     */
    $lock = $pdo->prepare("
        SELECT id
        FROM contractors
        WHERE tenant_id = ?
          AND id = ?
        FOR UPDATE
    ");
    $lock->execute([$tid, $contractorId]);

    if (!$lock->fetchColumn()) {
        throw new RuntimeException(
            'Contractor not found during qualification update'
        );
    }

$q = $pdo->prepare("
    INSERT INTO contractor_service_qualifications (
        tenant_id,
        contractor_id,
        service_id,
        verification_status,
        verified_by,
        verified_at
    )
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        verification_status = VALUES(verification_status),
        verified_by = VALUES(verified_by),
        verified_at = VALUES(verified_at)
");

$q->execute([
    $tid,
    $contractorId,
    $serviceId,
    $status,
    $verifiedBy,
    $verifiedAt
]);


    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Qualification update error: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Qualification update could not be completed'
    ], 500);
}

json_response([
    'ok' => true,
    'contractor_id' => (int)$contractorId,
    'service_id' => (int)$serviceId,
    'verification_status' => $status,
    'verified_by' => $verifiedBy,
    'verified_at' => $verifiedAt
]);
