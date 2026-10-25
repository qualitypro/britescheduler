<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin', 'client');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
}

$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];
$role = (string)$tenant['role'];

$contractorId = filter_var(
    $_GET['contractor_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$appointmentId = filter_var(
    $_GET['appointment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$contractorId || !$appointmentId) {
    json_response([
        'error' => 'Valid contractor_id and appointment_id required'
    ], 422);
}

$pdo = Database::connection();

$isManager = in_array($role, ['owner', 'admin'], true);

$clientId = null;

if (!$isManager) {
    $q = $pdo->prepare("
        SELECT id
        FROM clients
        WHERE tenant_id = ?
          AND user_id = ?
          AND status = 'active'
        LIMIT 1
    ");
    $q->execute([$tenantId, $userId]);
    $clientId = (int)($q->fetchColumn() ?: 0);

    if (!$clientId) {
        json_response(['error' => 'Client access required'], 403);
    }
}

/*
 * Authorization is established through an appointment
 * and an associated bid or assignment.
 *
 * Clients may only inspect contractors connected to
 * their own appointments.
 */
$sql = "
    SELECT
        c.id AS contractor_id,
        c.first_name,
        c.last_name,
        c.business_name,
        c.skills,
        c.hourly_rate,
        c.status,
        a.id AS appointment_id,
        a.title AS appointment_title,
        EXISTS (
            SELECT 1
            FROM appointment_contractors ac
            WHERE ac.appointment_id = a.id
              AND ac.contractor_id = c.id
              AND ac.status IN ('assigned', 'accepted', 'completed')
        ) AS is_assigned,
        EXISTS (
            SELECT 1
            FROM appointment_bids b
            JOIN appointment_bid_listings l
              ON l.tenant_id = b.tenant_id
             AND l.id = b.listing_id
            WHERE b.tenant_id = a.tenant_id
              AND l.appointment_id = a.id
              AND b.contractor_id = c.id
              AND b.status IN (
                  'submitted',
                  'shortlisted',
                  'selected',
                  'accepted'
              )
        ) AS has_bid
    FROM appointments a
    JOIN contractors c
      ON c.tenant_id = a.tenant_id
     AND c.id = ?
    WHERE a.tenant_id = ?
      AND a.id = ?
";

$params = [$contractorId, $tenantId, $appointmentId];

if (!$isManager) {
    $sql .= " AND a.client_id = ?";
    $params[] = $clientId;
}

$sql .= " LIMIT 1";

$q = $pdo->prepare($sql);
$q->execute($params);
$profile = $q->fetch(PDO::FETCH_ASSOC);

if (
    !$profile ||
    (
        !(bool)$profile['is_assigned'] &&
        !(bool)$profile['has_bid']
    )
) {
    json_response(['error' => 'Contractor profile not found'], 404);
}

/*
 * Service qualifications are retrieved only after the
 * appointment/contractor authorization check succeeds.
 *
 * Rejected qualifications are not published.
 */
$servicesQuery = $pdo->prepare("
    SELECT
        s.id AS service_id,
        s.name,
        s.description,
        q.verification_status
    FROM contractor_service_qualifications q
    JOIN services s
      ON s.id = q.service_id
     AND s.tenant_id = q.tenant_id
    WHERE q.tenant_id = ?
      AND q.contractor_id = ?
      AND s.active = 1
      AND q.verification_status IN ('verified', 'unverified')
    ORDER BY
        CASE q.verification_status
            WHEN 'verified' THEN 0
            ELSE 1
        END,
        s.name,
        s.id
");

$servicesQuery->execute([
    $tenantId,
    (int)$profile['contractor_id']
]);

$services = [];

foreach ($servicesQuery->fetchAll(PDO::FETCH_ASSOC) as $service) {
    $services[] = [
        'service_id' => (int)$service['service_id'],
        'name' => $service['name'],
        'description' => $service['description'],
        'verification_status' => $service['verification_status']
    ];
}

/*
 * Public-facing profile response.
 * Email and phone are intentionally excluded.
 */
json_response([
    'ok' => true,
    'profile' => [
        'contractor_id' => (int)$profile['contractor_id'],
        'first_name' => $profile['first_name'],
        'last_name' => $profile['last_name'],
        'business_name' => $profile['business_name'],
        'skills' => $profile['skills'],
        'services' => $services,
        'hourly_rate' => $profile['hourly_rate'],
        'status' => $profile['status']
    ],
    'context' => [
        'appointment_id' => (int)$profile['appointment_id'],
        'appointment_title' => $profile['appointment_title'],
        'is_assigned' => (bool)$profile['is_assigned'],
        'has_bid' => (bool)$profile['has_bid']
    ]
]);
