<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('contractor');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
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
    json_response([
        'error' => 'Active contractor profile required'
    ], 403);
}

/*
 * An appointment enters marketplace discovery when:
 *
 * 1. It belongs to the authenticated contractor's tenant.
 * 2. Its status permits future scheduling.
 * 3. It has no contractor assignment records.
 *
 * Service area, availability, and qualification
 * are deliberately NOT marketplace visibility filters.
 *
 * This endpoint exposes no client contact details.
 */

$q = $pdo->prepare("
    SELECT
        a.id AS appointment_id,
        l.id AS listing_id,
        a.title,
        a.description,
        a.starts_at,
        a.ends_at,
        a.status,
        a.location,
        a.service_id,
        s.name AS service_name,
        a.created_at
    FROM appointments a
    INNER JOIN appointment_bid_listings l
      ON l.tenant_id = a.tenant_id
     AND l.appointment_id = a.id
     AND l.status = 'open'
    LEFT JOIN services s
      ON s.id = a.service_id
     AND s.tenant_id = a.tenant_id
    WHERE a.tenant_id = ?
      AND a.starts_at >= CURRENT_DATE()
      AND a.status IN (
          'tentative',
          'scheduled',
          'confirmed'
      )
      AND NOT EXISTS (
          SELECT 1
          FROM appointment_contractors ac
          WHERE ac.appointment_id = a.id
      )
    ORDER BY
        a.starts_at ASC,
        a.id ASC
    LIMIT 200
");

$q->execute([$tid]);

$appointments = $q->fetchAll(PDO::FETCH_ASSOC);

foreach ($appointments as &$appointment) {
    $appointment['appointment_id'] =
        (int)$appointment['appointment_id'];

    $appointment['listing_id'] =
        (int)$appointment['listing_id'];

    $appointment['service_id'] =
        $appointment['service_id'] === null
            ? null
            : (int)$appointment['service_id'];
}
unset($appointment);

json_response([
    'contractor_id' => $contractorId,
    'appointments' => $appointments,
    'count' => count($appointments),
    'limit' => 200
]);
