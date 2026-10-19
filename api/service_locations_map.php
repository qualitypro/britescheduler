<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

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

try {
    $pdo = Database::connection();

    /*
     * Tenant ID is derived exclusively from the authenticated
     * tenant context, never from query parameters.
     *
     * Only resolved locations with valid coordinates are returned.
     */
    $query = $pdo->prepare(
        'SELECT
             l.id,
             l.client_id,
             l.label,
             l.address1,
             l.address2,
             l.city,
             l.state,
             l.postal_code,
             l.latitude,
             l.longitude,
             l.is_primary,
             c.first_name,
             c.last_name,
             c.company_name
         FROM client_locations l
         INNER JOIN clients c
             ON c.id = l.client_id
            AND c.tenant_id = l.tenant_id
         WHERE l.tenant_id = ?
           AND l.geocoding_status = ?
           AND l.latitude IS NOT NULL
           AND l.longitude IS NOT NULL
           AND l.latitude BETWEEN -90 AND 90
           AND l.longitude BETWEEN -180 AND 180
         ORDER BY
             c.last_name,
             c.first_name,
             l.is_primary DESC,
             l.label,
             l.id'
    );

    $query->execute([$tenantId, 'resolved']);

    $locations = $query->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'ok' => true,
        'locations' => $locations,
        'count' => count($locations)
    ]);

} catch (PDOException $exception) {
    error_log(
        'Service locations map database error: ' .
        $exception->getMessage()
    );

    json_response([
        'error' => 'Unable to retrieve service locations'
    ], 500);
}
