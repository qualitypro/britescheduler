<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('contractor');

$tid = (int)$tenant['tenant_id'];
$uid = (int)$user['id'];
$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    json_response(['error' => 'Method not allowed'], 405);
}

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
 * GET: Return only the authenticated contractor's bids.
 */
if ($method === 'GET') {
    $q = $pdo->prepare("
        SELECT
            b.id AS bid_id,
            b.listing_id,
            l.appointment_id,
            a.title,
            a.starts_at,
            a.ends_at,
            l.status AS listing_status,
            b.pricing_type,
            b.amount,
            b.message,
            b.status AS bid_status,
            b.submitted_at,
            b.updated_at
        FROM appointment_bids b
        JOIN appointment_bid_listings l
          ON l.tenant_id = b.tenant_id
         AND l.id = b.listing_id
        JOIN appointments a
          ON a.tenant_id = l.tenant_id
         AND a.id = l.appointment_id
        WHERE b.tenant_id = ?
          AND b.contractor_id = ?
        ORDER BY b.updated_at DESC, b.id DESC
        LIMIT 200
    ");

    $q->execute([$tid, $contractorId]);

    json_response([
        'contractor_id' => $contractorId,
        'bids' => $q->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

verify_csrf();

$data = request_data();

if (!is_array($data)) {
    json_response(['error' => 'Invalid request body'], 422);
}

$action = (string)($data['action'] ?? '');

if (!in_array(
    $action,
    ['submit', 'revise', 'withdraw'],
    true
)) {
    json_response(['error' => 'Invalid bid action'], 422);
}

$listingId = filter_var(
    $data['listing_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$listingId) {
    json_response(['error' => 'Valid listing_id required'], 422);
}

$pricingType = null;
$amount = null;
$message = null;

if ($action !== 'withdraw') {
    $pricingType = $data['pricing_type'] ?? null;

    if (!in_array($pricingType, ['hourly', 'flat'], true)) {
        json_response(['error' => 'Invalid pricing_type'], 422);
    }

    $rawAmount = $data['amount'] ?? null;

    if (!is_string($rawAmount) && !is_int($rawAmount)) {
        json_response(['error' => 'Invalid amount'], 422);
    }

    $rawAmount = (string)$rawAmount;

    if (!preg_match(
        '/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',
        $rawAmount
    )) {
        json_response([
            'error' => 'Amount must be a nonnegative monetary value'
        ], 422);
    }

    $amount = number_format((float)$rawAmount, 2, '.', '');

    $rawMessage = $data['message'] ?? '';

    if (!is_string($rawMessage) ||
        mb_strlen($rawMessage, 'UTF-8') > 2000) {
        json_response([
            'error' => 'Message must be at most 2000 characters'
        ], 422);
    }

    $message = trim($rawMessage);
    $message = $message === '' ? null : $message;
}

try {
    $pdo->beginTransaction();

    /*
     * Resolve appointment ID before locking.
     * All authoritative state is rechecked under row locks.
     */
    $q = $pdo->prepare("
        SELECT appointment_id
        FROM appointment_bid_listings
        WHERE tenant_id = ?
          AND id = ?
    ");
    $q->execute([$tid, $listingId]);

    $appointmentId = (int)($q->fetchColumn() ?: 0);

    if (!$appointmentId) {
        $pdo->rollBack();
        json_response(['error' => 'Listing not found'], 404);
    }

    /*
     * Marketplace lock order:
     * appointment -> listing -> bid.
     */
    $q = $pdo->prepare("
        SELECT id, status, starts_at
        FROM appointments
        WHERE tenant_id = ?
          AND id = ?
        FOR UPDATE
    ");
    $q->execute([$tid, $appointmentId]);
    $appointment = $q->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $pdo->rollBack();
        json_response(['error' => 'Appointment not found'], 404);
    }

    $q = $pdo->prepare("
        SELECT id, status
        FROM appointment_bid_listings
        WHERE tenant_id = ?
          AND id = ?
          AND appointment_id = ?
        FOR UPDATE
    ");
    $q->execute([$tid, $listingId, $appointmentId]);
    $listingRow = $q->fetch(PDO::FETCH_ASSOC);

    if (!$listingRow) {
        $pdo->rollBack();
        json_response(['error' => 'Listing not found'], 404);
    }

    $listing = [
        'id' => (int)$listingRow['id'],
        'listing_status' => $listingRow['status'],
        'appointment_id' => $appointmentId,
        'appointment_status' => $appointment['status'],
        'starts_at' => $appointment['starts_at']
    ];

    if ($listing['listing_status'] !== 'open' ||
        !in_array(
            $listing['appointment_status'],
            ['tentative', 'scheduled', 'confirmed'],
            true
        )) {
        $pdo->rollBack();
        json_response([
            'error' => 'Listing is not accepting bids'
        ], 409);
    }

    /*
     * Reject bid changes once the appointment has started.
     * DATETIME values use tenant-local wall-clock time.
     */
    $databaseNow = (string)$pdo
        ->query("SELECT NOW()")
        ->fetchColumn();

    if ((string)$listing['starts_at'] <= $databaseNow) {
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
    $q->execute([(int)$listing['appointment_id']]);

    if ((int)$q->fetchColumn() !== 0) {
        $pdo->rollBack();
        json_response([
            'error' => 'Appointment already assigned'
        ], 409);
    }

    $q = $pdo->prepare("
        SELECT
            id,
            pricing_type,
            amount,
            message,
            status
        FROM appointment_bids
        WHERE tenant_id = ?
          AND listing_id = ?
          AND contractor_id = ?
        FOR UPDATE
    ");

    $q->execute([$tid, $listingId, $contractorId]);
    $existing = $q->fetch(PDO::FETCH_ASSOC);

    $event = match ($action) {
        'submit' => 'submitted',
        'revise' => 'revised',
        'withdraw' => 'withdrawn'
    };
    $details = [];

    if ($action === 'submit') {
        if ($existing) {
            $pdo->rollBack();
            json_response([
                'error' => 'Bid already exists; use revise'
            ], 409);
        }

        $q = $pdo->prepare("
            INSERT INTO appointment_bids (
                tenant_id,
                listing_id,
                contractor_id,
                pricing_type,
                amount,
                message,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, 'submitted')
        ");

        $q->execute([
            $tid,
            $listingId,
            $contractorId,
            $pricingType,
            $amount,
            $message
        ]);

        $bidId = (int)$pdo->lastInsertId();

        $details = [
            'pricing_type' => $pricingType,
            'amount' => $amount,
            'message' => $message
        ];

    } else {
        if (!$existing) {
            $pdo->rollBack();
            json_response(['error' => 'Bid not found'], 404);
        }

        if (!in_array(
            $existing['status'],
            ['submitted', 'shortlisted'],
            true
        )) {
            $pdo->rollBack();
            json_response([
                'error' => 'Bid cannot be changed in its current status'
            ], 409);
        }

        $bidId = (int)$existing['id'];

        if ($action === 'revise') {
            $details = [
                'previous' => [
                    'pricing_type' => $existing['pricing_type'],
                    'amount' => $existing['amount'],
                    'message' => $existing['message'],
                    'status' => $existing['status']
                ],
                'updated' => [
                    'pricing_type' => $pricingType,
                    'amount' => $amount,
                    'message' => $message
                ]
            ];

            $q = $pdo->prepare("
                UPDATE appointment_bids
                SET pricing_type = ?,
                    amount = ?,
                    message = ?,
                    status = 'submitted'
                WHERE tenant_id = ?
                  AND id = ?
            ");

            $q->execute([
                $pricingType,
                $amount,
                $message,
                $tid,
                $bidId
            ]);

        } else {
            $details = [
                'previous_status' => $existing['status']
            ];

            $q = $pdo->prepare("
                UPDATE appointment_bids
                SET status = 'withdrawn'
                WHERE tenant_id = ?
                  AND id = ?
            ");

            $q->execute([$tid, $bidId]);
        }
    }

    $q = $pdo->prepare("
        INSERT INTO appointment_bid_events (
            tenant_id,
            listing_id,
            bid_id,
            actor_user_id,
            event_type,
            details_json
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $q->execute([
        $tid,
        $listingId,
        $bidId,
        $uid,
        $event,
        json_encode(
            $details,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        )
    ]);

    $pdo->commit();

    json_response([
        'ok' => true,
        'action' => $action,
        'bid_id' => $bidId,
        'listing_id' => (int)$listingId,
        'status' => $action === 'withdraw'
            ? 'withdrawn'
            : 'submitted'
    ], $action === 'submit' ? 201 : 200);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Contractor bid error: ' . $e->getMessage());

    json_response([
        'error' => 'Bid operation could not be completed'
    ], 500);
}
