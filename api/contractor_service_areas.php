<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::tenant();

$tid = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];

$pdo = Database::connection();

$managementRoles = ['owner', 'admin', 'scheduler'];
$isManager = in_array($role, $managementRoles, true);

if (!$isManager && $role !== 'contractor') {
    json_response(['error' => 'Forbidden'], 403);
}

function csa_contractor(
    PDO $pdo,
    int $tid,
    array $user,
    string $role,
    bool $isManager,
    int $requestedId
): int {
    if ($role === 'contractor') {
        $q = $pdo->prepare("
            SELECT id
            FROM contractors
            WHERE tenant_id = ?
              AND user_id = ?
              AND status = 'active'
            LIMIT 1
        ");
        $q->execute([$tid, (int)$user['id']]);

        $id = (int)($q->fetchColumn() ?: 0);

        if ($id <= 0) {
            json_response(['error' => 'Contractor profile not found'], 403);
        }

        return $id;
    }

    if (!$isManager) {
        json_response(['error' => 'Forbidden'], 403);
    }

    if ($requestedId <= 0) {
        json_response(['error' => 'Contractor required'], 422);
    }

    $q = $pdo->prepare("
        SELECT id FROM contractors
        WHERE tenant_id = ? AND id = ?
    ");
    $q->execute([$tid, $requestedId]);

    if (!$q->fetchColumn()) {
        json_response(['error' => 'Contractor not found'], 404);
    }

    return $requestedId;
}

function csa_payload(array $d): array {
    foreach (['area_type', 'label', 'country_code', 'state_code', 'postal_code'] as $field) {
        if (array_key_exists($field, $d) && !is_string($d[$field])) {
            throw new InvalidArgumentException("Invalid {$field}");
        }
    }

    $type = (string)($d['area_type'] ?? '');
    $label = trim((string)($d['label'] ?? ''));

    if (!in_array($type, ['zip', 'radius'], true)) {
        throw new InvalidArgumentException('Invalid service-area type');
    }

    if ($label === '' || !mb_check_encoding($label, 'UTF-8') || mb_strlen($label, 'UTF-8') > 120) {
        throw new InvalidArgumentException('Label must contain 1–120 valid UTF-8 characters');
    }

    $country = strtoupper(trim((string)($d['country_code'] ?? 'US')));

    if ($country !== 'US') {
        throw new InvalidArgumentException('Only US service areas are supported');
    }

    $activeRaw = $d['active'] ?? 1;

    if (!in_array($activeRaw, [0, 1, '0', '1'], true)) {
        throw new InvalidArgumentException('Invalid active status');
    }

    $active = (int)$activeRaw;

    $state = null;
    $zip = null;
    $latitude = null;
    $longitude = null;
    $radius = null;

    if ($type === 'zip') {
        $state = strtoupper(trim((string)($d['state_code'] ?? '')));
        $zip = trim((string)($d['postal_code'] ?? ''));

        if (!preg_match('/^[A-Z]{2}$/', $state)) {
            throw new InvalidArgumentException('Invalid state code');
        }

        if (!preg_match('/^[0-9]{5}$/', $zip) || $zip === '00000') {
            throw new InvalidArgumentException('Invalid five-digit ZIP code');
        }
    } else {
        $latRaw = $d['center_latitude'] ?? null;
        $lonRaw = $d['center_longitude'] ?? null;
        $radiusRaw = $d['radius_miles'] ?? null;

        foreach ([
            'latitude' => $latRaw,
            'longitude' => $lonRaw,
            'radius' => $radiusRaw
        ] as $name => $value) {
            if (
                !is_scalar($value) ||
                is_bool($value) ||
                trim((string)$value) === '' ||
                !is_numeric($value)
            ) {
                throw new InvalidArgumentException("Invalid {$name}");
            }
        }

        $latitude = (float)$latRaw;
        $longitude = (float)$lonRaw;
        $radius = (float)$radiusRaw;

        if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Latitude out of range');
        }

        if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Longitude out of range');
        }

        if (!is_finite($radius) || $radius <= 0 || $radius > 500) {
            throw new InvalidArgumentException('Radius must be greater than 0 and at most 500 miles');
        }

        if ($radius < 0.01) {
            throw new InvalidArgumentException('Minimum radius is 0.01 miles');
        }
    }

    return [
        'area_type' => $type,
        'label' => $label,
        'country_code' => 'US',
        'state_code' => $state,
        'postal_code' => $zip,
        'center_latitude' => $latitude,
        'center_longitude' => $longitude,
        'radius_miles' => $radius,
        'active' => $active,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $contractorId = csa_contractor(
        $pdo, $tid, $user, $role, $isManager,
        (int)($_GET['contractor_id'] ?? 0)
    );

    $q = $pdo->prepare("
        SELECT id, tenant_id, contractor_id,
               area_type, label, country_code,
               state_code, postal_code,
               center_latitude, center_longitude,
               radius_miles, active,
               created_at, updated_at
        FROM contractor_service_areas
        WHERE tenant_id = ?
          AND contractor_id = ?
        ORDER BY active DESC, label, id
    ");
    $q->execute([$tid, $contractorId]);

    json_response([
        'contractor_id' => $contractorId,
        'service_areas' => $q->fetchAll()
    ]);
}

if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    json_response(['error' => 'Method not allowed'], 405);
}

verify_csrf();

if (!$isManager) {
    json_response(['error' => 'Forbidden'], 403);
}

$d = request_data();

try {
    if ($method === 'POST') {
        $contractorId = csa_contractor(
            $pdo, $tid, $user, $role, $isManager,
            (int)($d['contractor_id'] ?? 0)
        );

        $p = csa_payload($d);

        $q = $pdo->prepare("
            INSERT INTO contractor_service_areas
            (
                tenant_id, contractor_id,
                area_type, label, country_code,
                state_code, postal_code,
                center_latitude, center_longitude,
                radius_miles, active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $q->execute([
            $tid, $contractorId,
            $p['area_type'], $p['label'], $p['country_code'],
            $p['state_code'], $p['postal_code'],
            $p['center_latitude'], $p['center_longitude'],
            $p['radius_miles'], $p['active']
        ]);

        json_response([
            'ok' => true,
            'id' => (int)$pdo->lastInsertId()
        ], 201);
    }

    $rawId = $d['id'] ?? null;

    if (!is_int($rawId) && !is_string($rawId)) {
        throw new InvalidArgumentException('Valid service-area ID required');
    }

    $id = filter_var(
        $rawId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false || $id === null) {
        throw new InvalidArgumentException('Valid service-area ID required');
    }

    $q = $pdo->prepare("
        SELECT id
        FROM contractor_service_areas
        WHERE id = ? AND tenant_id = ?
    ");
    $q->execute([$id, $tid]);

    if (!$q->fetchColumn()) {
        json_response(['error' => 'Service area not found'], 404);
    }

    if ($method === 'DELETE') {
        $q = $pdo->prepare("
            DELETE FROM contractor_service_areas
            WHERE id = ? AND tenant_id = ?
        ");
        $q->execute([$id, $tid]);

        json_response(['ok' => true]);
    }

    $p = csa_payload($d);

    $q = $pdo->prepare("
        UPDATE contractor_service_areas
        SET area_type = ?,
            label = ?,
            country_code = ?,
            state_code = ?,
            postal_code = ?,
            center_latitude = ?,
            center_longitude = ?,
            radius_miles = ?,
            active = ?
        WHERE id = ? AND tenant_id = ?
    ");

    $q->execute([
        $p['area_type'], $p['label'], $p['country_code'],
        $p['state_code'], $p['postal_code'],
        $p['center_latitude'], $p['center_longitude'],
        $p['radius_miles'], $p['active'],
        $id, $tid
    ]);

    json_response(['ok' => true]);

} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 422);
} catch (PDOException $e) {
    error_log('Contractor service-area database error: '.$e->getMessage());
    json_response(['error' => 'Unable to save service area'], 500);
}
