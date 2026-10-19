<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

Auth::requireUser();

$tenant = Auth::requireRole('client');
$tenantId = (int)$tenant['tenant_id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response([
        'error' => 'Method not allowed'
    ], 405);
}

try {
    $pdo = Database::connection();

    $stmt = $pdo->prepare(
        'SELECT
            id,
            name,
            description,
            duration_minutes,
            price
         FROM services
         WHERE tenant_id = ?
           AND active = 1
         ORDER BY name, id'
    );

    $stmt->execute([$tenantId]);

    json_response([
        'services' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {
    error_log(
        'Client service catalog error: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Unable to load available services'
    ], 500);
}
