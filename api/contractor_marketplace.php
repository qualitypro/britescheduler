<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/TenantClock.php';

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

$paging = null;
if (isset($_GET['page']) || isset($_GET['per_page'])) {
    require_once dirname(__DIR__).'/app/ServerPagination.php';
    try { $paging=ServerPagination::fromQuery($_GET); }
    catch (InvalidArgumentException $e) {
        json_response(['error'=>$e->getMessage()],422);
    }
}
$today=TenantClock::today($pdo,$tid);
$marketWhere="a.tenant_id = ?
      AND a.starts_at >= ?
      AND a.status IN ('tentative','scheduled','confirmed')
      AND NOT EXISTS (
          SELECT 1 FROM appointment_contractors ac
          WHERE ac.appointment_id = a.id
      )";
$marketJoin="INNER JOIN appointment_bid_listings l
      ON l.tenant_id = a.tenant_id
     AND l.appointment_id = a.id
     AND l.status = 'open'";
$total=null;
if ($paging !== null) {
    $count=$pdo->prepare("SELECT COUNT(*) FROM appointments a {$marketJoin} WHERE {$marketWhere}");
    $count->execute([$tid,$today]);
    $total=(int)$count->fetchColumn();
}
$marketLimit=$paging===null?'LIMIT 200':'LIMIT '.$paging['per_page'].' OFFSET '.$paging['offset'];
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
    {$marketJoin}
    LEFT JOIN services s
      ON s.id = a.service_id
     AND s.tenant_id = a.tenant_id
    WHERE {$marketWhere}
    ORDER BY
        a.starts_at ASC,
        a.id ASC
    {$marketLimit}
");

$q->execute([
    $tid,
    $today
]);

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
    'limit' => $paging===null?200:$paging['per_page'],
    'pagination' => $paging===null?null:ServerPagination::metadata($paging,$total)
]);
