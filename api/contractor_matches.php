<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ServiceAreaMatcher.php';

$user = Auth::requireUser();
$tenant = Auth::tenant();

$tenantId = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];

if (!in_array($role, ['owner', 'admin', 'scheduler'], true)) {
    json_response(['error' => 'Forbidden'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
}

$rawLocationId = $_GET['location_id'] ?? null;

if (!is_int($rawLocationId) && !is_string($rawLocationId)) {
    json_response(['error' => 'Valid location ID required'], 422);
}

$locationId = filter_var(
    $rawLocationId,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($locationId === false || $locationId === null) {
    json_response(['error' => 'Valid location ID required'], 422);
}

$pdo = Database::connection();

try {
    /*
     * Explicit tenant-scoped existence check.
     *
     * This prevents distinguishing a missing location
     * from a location belonging to another tenant.
     */
    $query = $pdo->prepare(
        'SELECT id
         FROM client_locations
         WHERE id = ?
           AND tenant_id = ?
         LIMIT 1'
    );

    $query->execute([$locationId, $tenantId]);

    if (!$query->fetchColumn()) {
        json_response(['error' => 'Location not found'], 404);
    }

    $matches = ServiceAreaMatcher::matchLocation(
        $pdo,
        $tenantId,
        (int)$locationId
    );

    json_response([
        'ok' => true,
        'location_id' => (int)$locationId,
        'contractors' => $matches,
        'count' => count($matches)
    ]);

} catch (PDOException $exception) {
    error_log(
        'Contractor matching database error: ' .
        $exception->getMessage()
    );

    json_response(
        ['error' => 'Unable to retrieve contractor matches'],
        500
    );

} catch (InvalidArgumentException $exception) {
    json_response(
        ['error' => 'Invalid matching request'],
        422
    );
}
