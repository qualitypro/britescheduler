<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
}

$tenant = Auth::requireRole('owner', 'admin', 'client');

$tid = (int)$tenant['tenant_id'];
$uid = (int)$user['id'];
$role = (string)$tenant['role'];

$listingId = filter_var(
    $_GET['listing_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$listingId) {
    json_response(['error' => 'Valid listing_id required'], 422);
}

$pdo = Database::connection();

$isManager = in_array($role, ['owner', 'admin'], true);

if (!$isManager) {
    $q = $pdo->prepare("
        SELECT id
        FROM clients
        WHERE tenant_id = ?
          AND user_id = ?
          AND status = 'active'
        LIMIT 1
    ");

    $q->execute([$tid, $uid]);
    $clientId = (int)($q->fetchColumn() ?: 0);

    if (!$clientId) {
        json_response(['error' => 'Client access required'], 403);
    }
}

/*
 * Client ownership is enforced in SQL.
 * A listing belonging to another client is indistinguishable
 * from a nonexistent listing.
 */
$sql = "
    SELECT
        l.id AS listing_id,
        l.appointment_id,
        l.status AS listing_status,
        l.opened_at,
        l.closed_at,
        a.client_id,
        a.title,
        a.starts_at,
        a.ends_at,
        a.status AS appointment_status
    FROM appointment_bid_listings l
    JOIN appointments a
      ON a.tenant_id = l.tenant_id
     AND a.id = l.appointment_id
    WHERE l.tenant_id = ?
      AND l.id = ?
";

$params = [$tid, $listingId];

if (!$isManager) {
    $sql .= " AND a.client_id = ?";
    $params[] = $clientId;
}

$sql .= " LIMIT 1";

$q = $pdo->prepare($sql);
$q->execute($params);

$listing = $q->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    json_response(['error' => 'Listing not found'], 404);
}

/*
 * All statuses are counted for authorized viewers.
 * Withdrawn and rejected bids are excluded from active counts.
 */
$q = $pdo->prepare("
    SELECT
        COUNT(*) AS total_bids,
        COALESCE(SUM(
            CASE
                WHEN status IN (
                    'submitted',
                    'shortlisted',
                    'selected',
                    'accepted'
                ) THEN 1
                ELSE 0
            END
        ), 0) AS active_bids,
        COALESCE(SUM(
            CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END
        ), 0) AS withdrawn_bids,
        MAX(submitted_at) AS latest_submission
    FROM appointment_bids
    WHERE tenant_id = ?
      AND listing_id = ?
");

$q->execute([$tid, $listingId]);
$summary = $q->fetch(PDO::FETCH_ASSOC);

$response = [
    'ok' => true,
    'listing' => [
        'listing_id' => (int)$listing['listing_id'],
        'appointment_id' => (int)$listing['appointment_id'],
        'title' => $listing['title'],
        'starts_at' => $listing['starts_at'],
        'ends_at' => $listing['ends_at'],
        'listing_status' => $listing['listing_status'],
        'appointment_status' => $listing['appointment_status'],
        'opened_at' => $listing['opened_at']
    ],
    'summary' => [
        'total_bids' => (int)$summary['total_bids'],
        'active_bids' => (int)$summary['active_bids'],
        'withdrawn_bids' => (int)$summary['withdrawn_bids'],
        'latest_submission' => $summary['latest_submission']
    ]
];

if ($isManager) {
    $q = $pdo->prepare("
        SELECT
            b.id AS bid_id,
            b.contractor_id,
            b.pricing_type,
            b.amount,
            b.message,
            b.status,
            b.submitted_at,
            b.updated_at
        FROM appointment_bids b
        WHERE b.tenant_id = ?
          AND b.listing_id = ?
        ORDER BY
            CASE
                WHEN b.status IN (
                    'submitted', 'shortlisted', 'selected', 'accepted'
                ) THEN 0
                ELSE 1
            END,
            b.submitted_at ASC,
            b.id ASC
    ");

    $q->execute([$tid, $listingId]);
    $response['bids'] = $q->fetchAll(PDO::FETCH_ASSOC);
}

/*
 * Clients may view eligible bids only after the listing
 * ownership and tenant authorization checks above succeed.
 *
 * Withdrawn and rejected bids are not included.
 * Contractor contact details are intentionally excluded.
 */
if (!$isManager) {
    $q = $pdo->prepare("
        SELECT
            b.id AS bid_id,
            b.contractor_id,
            c.first_name,
            c.last_name,
            c.business_name,
            b.pricing_type,
            b.amount,
            b.message,
            b.status,
            b.submitted_at,
            b.updated_at
        FROM appointment_bids b
        JOIN contractors c
          ON c.id = b.contractor_id
         AND c.tenant_id = b.tenant_id
        WHERE b.tenant_id = ?
          AND b.listing_id = ?
          AND b.status IN (
              'submitted',
              'shortlisted',
              'selected',
              'accepted'
          )
        ORDER BY
            CASE b.status
                WHEN 'selected' THEN 0
                WHEN 'accepted' THEN 1
                WHEN 'shortlisted' THEN 2
                ELSE 3
            END,
            b.submitted_at ASC,
            b.id ASC
    ");

    $q->execute([$tid, $listingId]);

    $response['bids'] = $q->fetchAll(PDO::FETCH_ASSOC);
}

json_response($response);
