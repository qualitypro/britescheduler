<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

$u = Auth::requireUser();
$tenant = Auth::tenant();
$tid = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];
$pdo = Database::connection();

if (!in_array(
    $role,
    ['owner', 'admin', 'scheduler', 'accounting', 'contractor', 'client'],
    true
)) {
    json_response(['error' => 'Forbidden'], 403);
}

$timezoneStatement = $pdo->prepare(
    'SELECT timezone FROM tenants WHERE id = ? LIMIT 1'
);
$timezoneStatement->execute([$tid]);
$timezoneName = $timezoneStatement->fetchColumn();

if (!$timezoneName) {
    json_response(['error' => 'Tenant timezone not configured'], 500);
}

try {
    $tenantTimezone = new DateTimeZone((string)$timezoneName);
} catch (Throwable $e) {
    json_response(['error' => 'Invalid tenant timezone'], 500);
}

// Appointment DATETIME values represent tenant-local wall-clock time.
// Compare them against the current time in that same timezone.
$now = (new DateTimeImmutable('now', $tenantTimezone))
    ->format('Y-m-d H:i:s');

$where = "a.tenant_id = ?
    AND a.starts_at >= ?
    AND a.status NOT IN ('cancelled', 'completed', 'no_show')";
$params = [$tid, $now];

if ($role === 'client') {
    $stmt = $pdo->prepare(
        "SELECT id FROM clients
         WHERE tenant_id = ? AND user_id = ? AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$tid, (int)$u['id']]);
    $clientId = (int)($stmt->fetchColumn() ?: 0);

    if ($clientId <= 0) {
        json_response(['error' => 'Client profile not found'], 403);
    }

    $where .= ' AND a.client_id = ?';
    $params[] = $clientId;
} elseif ($role === 'contractor') {
    $stmt = $pdo->prepare(
        "SELECT id FROM contractors
         WHERE tenant_id = ? AND user_id = ? AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$tid, (int)$u['id']]);
    $contractorId = (int)($stmt->fetchColumn() ?: 0);

    if ($contractorId <= 0) {
        json_response(['error' => 'Contractor profile not found'], 403);
    }

    $where .= " AND EXISTS (
        SELECT 1 FROM appointment_contractors ac
        WHERE ac.appointment_id = a.id
          AND ac.contractor_id = ?
          AND ac.status IN ('assigned', 'accepted')
    )";
    $params[] = $contractorId;
}

$stmt = $pdo->prepare(
    "SELECT a.starts_at
     FROM appointments a
     WHERE {$where}
     ORDER BY a.starts_at ASC, a.id ASC
     LIMIT 1"
);
$stmt->execute($params);
$startsAt = $stmt->fetchColumn();

json_response([
    'first_appointment_date' => $startsAt
        ? substr((string)$startsAt, 0, 10)
        : null
]);
