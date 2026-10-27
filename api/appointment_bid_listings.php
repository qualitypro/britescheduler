<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/TenantClock.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin');

$tid = (int)$tenant['tenant_id'];
$uid = (int)$user['id'];
$pdo = Database::connection();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $q = $pdo->prepare("
        SELECT
            a.id AS appointment_id,
            a.title,
            a.starts_at,
            a.ends_at,
            a.status AS appointment_status,
            l.id AS listing_id,
            l.status AS listing_status,
            (
                SELECT COUNT(*)
                FROM appointment_contractors ac
                WHERE ac.appointment_id = a.id
            ) AS assignment_count
        FROM appointments a
        LEFT JOIN appointment_bid_listings l
          ON l.tenant_id = a.tenant_id
         AND l.appointment_id = a.id
        WHERE a.tenant_id = ?
          AND a.starts_at >= ?
          AND a.status IN (
              'tentative', 'scheduled', 'confirmed'
          )
        ORDER BY a.starts_at, a.id
        LIMIT 200
    ");

    $q->execute([$tid, TenantClock::today($pdo, $tid)]);

    json_response([
        'appointments' => $q->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    json_response(['error' => 'Method not allowed'], 405);
}

verify_csrf();

$data = request_data();

if (!is_array($data)) {
    json_response(['error' => 'Invalid request'], 422);
}

$appointmentId = filter_var(
    $data['appointment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$appointmentId) {
    json_response([
        'error' => 'Valid appointment_id required'
    ], 422);
}

try {
    $pdo->beginTransaction();

    /*
     * Lock the appointment row while checking eligibility.
     * Assignment-writing endpoints must also lock this row
     * to prevent concurrent assignment/listing races.
     */
    $q = $pdo->prepare("
        SELECT id, status, starts_at
        FROM appointments
        WHERE tenant_id = ? AND id = ?
        FOR UPDATE
    ");

    $q->execute([$tid, $appointmentId]);
    $appointment = $q->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $pdo->rollBack();
        json_response(['error' => 'Appointment not found'], 404);
    }

    if (!in_array(
        $appointment['status'],
        ['tentative', 'scheduled', 'confirmed'],
        true
    )) {
        $pdo->rollBack();
        json_response([
            'error' => 'Appointment status is not eligible'
        ], 409);
    }

    $tenantNow = TenantClock::now($pdo, $tid);

    if ((string)$appointment['starts_at'] <= $tenantNow) {
        $pdo->rollBack();
        json_response([
            'error' => 'Appointment has already started'
        ], 409);
    }

    $q = $pdo->prepare("
        SELECT COUNT(*)
        FROM appointment_contractors
        WHERE appointment_id = ?
    ");

    $q->execute([$appointmentId]);

    if ((int)$q->fetchColumn() !== 0) {
        $pdo->rollBack();
        json_response([
            'error' => 'Appointment already has assignments'
        ], 409);
    }

    $q = $pdo->prepare("
        SELECT id, status
        FROM appointment_bid_listings
        WHERE tenant_id = ?
          AND appointment_id = ?
        FOR UPDATE
    ");

    $q->execute([$tid, $appointmentId]);
    $existing = $q->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->rollBack();
        json_response([
            'error' => 'Listing already exists',
            'listing_id' => (int)$existing['id'],
            'listing_status' => $existing['status']
        ], 409);
    }

    $q = $pdo->prepare("
        INSERT INTO appointment_bid_listings (
            tenant_id,
            appointment_id,
            status,
            opened_by
        )
        VALUES (?, ?, 'open', ?)
    ");

    $q->execute([$tid, $appointmentId, $uid]);

    $listingId = (int)$pdo->lastInsertId();

    $pdo->commit();

    json_response([
        'ok' => true,
        'listing_id' => $listingId,
        'appointment_id' => (int)$appointmentId,
        'status' => 'open'
    ], 201);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Marketplace listing error: ' . $e->getMessage());

    json_response([
        'error' => 'Could not open marketplace listing'
    ], 500);
}
