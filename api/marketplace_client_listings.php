<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('client');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
}

$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];
$pdo = Database::connection();

$clientQuery = $pdo->prepare("
    SELECT id
    FROM clients
    WHERE tenant_id = ?
      AND user_id = ?
      AND status = 'active'
    LIMIT 1
");

$clientQuery->execute([$tenantId, $userId]);
$clientId = (int)($clientQuery->fetchColumn() ?: 0);

if ($clientId <= 0) {
    json_response(['error' => 'Client not found'], 404);
}

$query = $pdo->prepare("
    SELECT
        l.id AS listing_id,
        l.appointment_id,
        l.status AS listing_status,
        a.title,
        a.starts_at,
        a.ends_at,
        a.status AS appointment_status,
        (
            SELECT COUNT(*)
            FROM appointment_bids b
            WHERE b.tenant_id = l.tenant_id
              AND b.listing_id = l.id
              AND b.status IN (
                  'submitted',
                  'shortlisted',
                  'selected',
                  'accepted'
              )
        ) AS eligible_bid_count
    FROM appointment_bid_listings l
    JOIN appointments a
      ON a.id = l.appointment_id
     AND a.tenant_id = l.tenant_id
    WHERE l.tenant_id = ?
      AND a.client_id = ?
    ORDER BY a.starts_at DESC, l.id DESC
    LIMIT 200
");

$query->execute([$tenantId, $clientId]);

$listings = array_map(
    static function (array $row): array {
        return [
            'listing_id' => (int)$row['listing_id'],
            'appointment_id' => (int)$row['appointment_id'],
            'listing_status' => $row['listing_status'],
            'title' => $row['title'],
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'appointment_status' => $row['appointment_status'],
            'eligible_bid_count' =>
                (int)$row['eligible_bid_count']
        ];
    },
    $query->fetchAll(PDO::FETCH_ASSOC)
);

json_response([
    'ok' => true,
    'listings' => $listings
]);
