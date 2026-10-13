<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Geocoding.php';

$user = Auth::requireUser();
$tenant = Auth::tenant();

$tid = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];
$pdo = Database::connection();

if (!in_array(
    $role,
    ['owner', 'admin', 'scheduler', 'client'],
    true
)) {
    json_response(['error' => 'Forbidden'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

verify_csrf();

$data = request_data();

$clientId = (int)($data['client_id'] ?? 0);
$id = (int)($data['id'] ?? 0);

if ($role === 'client') {
    $q = $pdo->prepare(
        'SELECT id FROM clients
         WHERE tenant_id=? AND user_id=? LIMIT 1'
    );
    $q->execute([$tid, (int)$user['id']]);
    $clientId = (int)($q->fetchColumn() ?: 0);
}

if ($clientId < 1 || $id < 1) {
    json_response(['error' => 'Invalid location'], 422);
}


// Execute while holding the location-specific advisory lock.
function executeLocationRetry(
    PDO $pdo,
    int $id,
    int $tid,
    int $clientId
): array {
$q = $pdo->prepare(
    'SELECT id,address1,address2,city,state,postal_code,
            latitude,longitude,geocoding_status,geocoding_attempted_at
     FROM client_locations
     WHERE id=? AND tenant_id=? AND client_id=?
     LIMIT 1'
);

$q->execute([$id, $tid, $clientId]);
$location = $q->fetch(PDO::FETCH_ASSOC);

if (!$location) {
    return [['error' => 'Location not found'], 404];
}

if (
    $location['latitude'] !== null ||
    $location['longitude'] !== null
) {
    return [['error' => 'Location already has coordinates'], 409];
}

if (!in_array(
    $location['geocoding_status'],
    ['pending', 'unavailable', 'not_found'],
    true
)) {
    return [['error' => 'Correct the address before retrying'], 409];
}

if ($location['geocoding_status'] === 'not_found') {
    $attemptedAt = $location['geocoding_attempted_at'];

    if ($attemptedAt === null) {
        return [['error' => 'Retry timestamp unavailable; correct the address'], 409];
    }

    $q = $pdo->prepare(
        'SELECT UTC_TIMESTAMP(6) >=
                CAST(? AS DATETIME(6)) + INTERVAL 24 HOUR'
    );
    $q->execute([$attemptedAt]);

    if ((int)$q->fetchColumn() !== 1) {
        return [[
            'error' => 'Address not found. Retry is available 24 hours after the previous attempt.'
        ], 409];
    }
}

$address = [
    'address1' => $location['address1'],
    'address2' => $location['address2'],
    'city' => $location['city'],
    'state' => $location['state'],
    'postal_code' => $location['postal_code']
];

try {
    $result = Geocoding::resolve($pdo, $address);
} catch (Throwable $e) {
    error_log(
        'Location retry geocoding failed: ' . get_class($e)
    );
    $result = ['status' => 'unavailable'];
}

$status = (string)($result['status'] ?? 'unavailable');

if (!in_array(
    $status,
    ['resolved', 'rejected', 'not_found', 'unavailable'],
    true
)) {
    $status = 'unavailable';
}

if (
    $status === 'resolved' &&
    (!isset($result['latitude'], $result['longitude']))
) {
    $status = 'unavailable';
}

if ($status === 'resolved') {
        $q = $pdo->prepare(
            'UPDATE client_locations
             SET latitude=?,
                 longitude=?,
                 geocoding_status=?,
                 geocoding_attempted_at=UTC_TIMESTAMP(6),
                 geocoding_resolved_at=UTC_TIMESTAMP(6)
             WHERE id=? AND tenant_id=? AND client_id=?
               AND address1=? AND address2 <=> ?
               AND city=? AND state=? AND postal_code=?
               AND latitude IS NULL AND longitude IS NULL
               AND geocoding_status IN (?,?,?)
               AND geocoding_attempted_at <=> ?'
        );

        $q->execute([
            $result['latitude'],
            $result['longitude'],
            'resolved',
            $id,
            $tid,
            $clientId,
            $address['address1'],
            $address['address2'],
            $address['city'],
            $address['state'],
            $address['postal_code'],
            'pending',
            'unavailable',
            'not_found',
            $location['geocoding_attempted_at']
        ]);
    } else {
        $q = $pdo->prepare(
            'UPDATE client_locations
             SET geocoding_status=?,
                 geocoding_attempted_at=UTC_TIMESTAMP(6),
                 geocoding_resolved_at=NULL
             WHERE id=? AND tenant_id=? AND client_id=?
               AND address1=? AND address2 <=> ?
               AND city=? AND state=? AND postal_code=?
               AND latitude IS NULL AND longitude IS NULL
               AND geocoding_status IN (?,?,?)
               AND geocoding_attempted_at <=> ?'
        );

        $q->execute([
            $status,
            $id,
            $tid,
            $clientId,
            $address['address1'],
            $address['address2'],
            $address['city'],
            $address['state'],
            $address['postal_code'],
            'pending',
            'unavailable',
            'not_found',
            $location['geocoding_attempted_at']
        ]);
    }

    if ($q->rowCount() !== 1) {
        return [['error' => 'Location changed during geocoding; refresh and retry'], 409];
    }

    return [['ok' => true, 'id' => $id, 'geocoding_status' => $status], 200];
}

$lockName = 'brite_geo_' . $tid . '_' . $id;
$response = ['error' => 'Unable to retry geocoding'];
$responseCode = 500;
$locked = false;

try {
    $q = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $q->execute([$lockName]);
    $locked = ((int)$q->fetchColumn() === 1);

    if (!$locked) {
        $response = [
            'error' => 'Geocoding already in progress; try again shortly'
        ];
        $responseCode = 409;
    } else {
        [$response, $responseCode] =
            executeLocationRetry($pdo, $id, $tid, $clientId);
    }
} catch (Throwable $e) {
    error_log('Location retry failed: ' . get_class($e));
    $response = ['error' => 'Unable to update geocoding status'];
    $responseCode = 500;
} finally {
    if ($locked) {
        try {
            $q = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $q->execute([$lockName]);

            if ((int)$q->fetchColumn() !== 1) {
                throw new RuntimeException('Lock release not confirmed');
            }
        } catch (Throwable $e) {
            error_log('Location retry lock release failed: ' . get_class($e));
            $response = ['error' => 'Unable to complete retry safely'];
            $responseCode = 500;
        }
    }
}

json_response($response, $responseCode);
