<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ContractorScheduleValidator.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin');

$tid = (int)$tenant['tenant_id'];
$uid = (int)$user['id'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['error' => 'Method not allowed'], 405);
}

verify_csrf();

$data = request_data();

$listingId = filter_var(
    $data['listing_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$bidId = filter_var(
    $data['bid_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$listingId || !$bidId) {
    json_response([
        'error' => 'Valid listing_id and bid_id required'
    ], 422);
}

$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    /*
     * Resolve the appointment ID without taking locks.
     * The authoritative state is checked again under locks.
     */
    $q = $pdo->prepare("
        SELECT appointment_id
        FROM appointment_bid_listings
        WHERE tenant_id = ? AND id = ?
    ");
    $q->execute([$tid, $listingId]);

    $appointmentId = (int)($q->fetchColumn() ?: 0);

    if (!$appointmentId) {
        json_response(['error' => 'Listing not found'], 404);
    }

    $pdo->beginTransaction();

    /*
     * Global marketplace lock order:
     * appointment -> listing -> bid -> contractor.
     */
    $q = $pdo->prepare("
        SELECT
            id,
            status,
            starts_at,
            ends_at,
            service_id
        FROM appointments
        WHERE tenant_id = ? AND id = ?
        FOR UPDATE
    ");
    $q->execute([$tid, $appointmentId]);
    $appointment = $q->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new DomainException('Appointment not found');
    }

    $q = $pdo->prepare("
        SELECT
            id,
            status,
            awarded_bid_id
        FROM appointment_bid_listings
        WHERE tenant_id = ?
          AND id = ?
          AND appointment_id = ?
        FOR UPDATE
    ");
    $q->execute([$tid, $listingId, $appointmentId]);
    $listing = $q->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        throw new DomainException('Listing not found');
    }

    if ($listing['status'] !== 'open') {
        throw new DomainException(
            'Listing is not open for award'
        );
    }

    if (!in_array(
        $appointment['status'],
        ['tentative', 'scheduled', 'confirmed'],
        true
    )) {
        throw new DomainException(
            'Appointment is not eligible for award'
        );
    }

    /*
     * Appointment DATETIME values represent tenant-local
     * wall-clock time. Reject awards at or after the
     * appointment's actual start time.
     */
    $databaseNow = (string)$pdo
        ->query('SELECT NOW()')
        ->fetchColumn();

    if ((string)$appointment['starts_at'] <= $databaseNow) {
        throw new DomainException(
            'Appointment has already started'
        );
    }

    $q = $pdo->prepare("
        SELECT COUNT(*)
        FROM appointment_contractors
        WHERE appointment_id = ?
    ");
    $q->execute([$appointmentId]);

    if ((int)$q->fetchColumn() !== 0) {
        throw new DomainException(
            'Appointment already has contractor assignments'
        );
    }

    $q = $pdo->prepare("
        SELECT
            id,
            contractor_id,
            pricing_type,
            amount,
            status
        FROM appointment_bids
        WHERE tenant_id = ?
          AND listing_id = ?
          AND id = ?
        FOR UPDATE
    ");
    $q->execute([$tid, $listingId, $bidId]);
    $bid = $q->fetch(PDO::FETCH_ASSOC);

    if (!$bid) {
        throw new DomainException('Bid not found');
    }

    if (!in_array(
        $bid['status'],
        ['submitted', 'shortlisted'],
        true
    )) {
        throw new DomainException(
            'Bid is not eligible for selection'
        );
    }

    $contractorId = (int)$bid['contractor_id'];

    $q = $pdo->prepare("
        SELECT id, status
        FROM contractors
        WHERE tenant_id = ? AND id = ?
        FOR UPDATE
    ");
    $q->execute([$tid, $contractorId]);
    $contractor = $q->fetch(PDO::FETCH_ASSOC);

    if (!$contractor || $contractor['status'] !== 'active') {
        throw new DomainException(
            'Active contractor profile required'
        );
    }

    /*
     * Marketplace exception:
     * geographic service preferences do not prohibit an
     * explicitly submitted contractor bid.
     *
     * Service qualification rejection remains a hard restriction.
     */
    if ($appointment['service_id'] !== null) {
        $q = $pdo->prepare("
            SELECT active
            FROM services
            WHERE tenant_id = ? AND id = ?
        ");
        $q->execute([
            $tid,
            (int)$appointment['service_id']
        ]);

        if ((int)$q->fetchColumn() !== 1) {
            throw new DomainException('Service is not active');
        }

        $q = $pdo->prepare("
            SELECT verification_status
            FROM contractor_service_qualifications
            WHERE tenant_id = ?
              AND contractor_id = ?
              AND service_id = ?
        ");
        $q->execute([
            $tid,
            $contractorId,
            (int)$appointment['service_id']
        ]);

        if ($q->fetchColumn() === 'rejected') {
            throw new DomainException(
                'Contractor qualification rejected for this service'
            );
        }
    }

    $startsAt = (string)$appointment['starts_at'];
    $endsAt = (string)$appointment['ends_at'];

    if (substr($startsAt, 0, 10) !== substr($endsAt, 0, 10)) {
        throw new DomainException(
            'Contractor assignments cannot cross midnight'
        );
    }

    /*
     * Explicit date-specific unavailable periods remain hard
     * restrictions. Recurring preferred hours are advisory
     * for marketplace bids.
     */
    $q = $pdo->prepare("
        SELECT id
        FROM contractor_availability
        WHERE tenant_id = ?
          AND contractor_id = ?
          AND available_date = DATE(?)
          AND is_available = 0
          AND starts_at < TIME(?)
          AND ends_at > TIME(?)
        LIMIT 1
    ");
    $q->execute([
        $tid,
        $contractorId,
        $startsAt,
        $endsAt,
        $startsAt
    ]);

    if ($q->fetchColumn()) {
        throw new DomainException(
            'Contractor has explicit unavailability during appointment'
        );
    }

    check_contractor_conflicts(
        $pdo,
        $tid,
        [$contractorId],
        $startsAt,
        $endsAt,
        $appointmentId
    );

    /*
     * Reuse the established appointment assignment table.
     * Bid amount remains separate from assignment pay_amount.
     */
    $q = $pdo->prepare("
        INSERT INTO appointment_contractors (
            appointment_id,
            contractor_id,
            status,
            pay_amount
        )
        VALUES (?, ?, 'assigned', NULL)
    ");
    $q->execute([$appointmentId, $contractorId]);

    $q = $pdo->prepare("
        UPDATE appointment_bids
        SET status = 'selected'
        WHERE tenant_id = ?
          AND listing_id = ?
          AND id = ?
    ");
    $q->execute([$tid, $listingId, $bidId]);

    $q = $pdo->prepare("
        SELECT id
        FROM appointment_bids
        WHERE tenant_id = ?
          AND listing_id = ?
          AND id <> ?
          AND status IN ('submitted', 'shortlisted')
        ORDER BY id
        FOR UPDATE
    ");
    $q->execute([$tid, $listingId, $bidId]);
    $rejectedIds = array_map(
        'intval',
        $q->fetchAll(PDO::FETCH_COLUMN)
    );

    $q = $pdo->prepare("
        UPDATE appointment_bids
        SET status = 'rejected'
        WHERE tenant_id = ?
          AND listing_id = ?
          AND id <> ?
          AND status IN ('submitted', 'shortlisted')
    ");
    $q->execute([$tid, $listingId, $bidId]);

    $q = $pdo->prepare("
        UPDATE appointment_bid_listings
        SET status = 'awarded',
            awarded_bid_id = ?,
            closed_at = CURRENT_TIMESTAMP
        WHERE tenant_id = ?
          AND id = ?
          AND status = 'open'
    ");
    $q->execute([$bidId, $tid, $listingId]);

    if ($q->rowCount() !== 1) {
        throw new RuntimeException(
            'Listing state changed during award'
        );
    }

    $event = $pdo->prepare("
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

    $event->execute([
        $tid,
        $listingId,
        $bidId,
        $uid,
        'selected',
        json_encode([
            'appointment_id' => $appointmentId,
            'contractor_id' => $contractorId,
            'pricing_type' => $bid['pricing_type'],
            'amount' => $bid['amount'],
            'assignment_status' => 'assigned'
        ], JSON_THROW_ON_ERROR)
    ]);

    foreach ($rejectedIds as $rejectedId) {
        $event->execute([
            $tid,
            $listingId,
            $rejectedId,
            $uid,
            'rejected',
            json_encode([
                'reason' => 'another_bid_selected',
                'selected_bid_id' => $bidId
            ], JSON_THROW_ON_ERROR)
        ]);
    }


    /*
     * Marketplace award notifications.
     *
     * All inserts share the award transaction. A database
     * failure rolls back the award and notification records.
     *
     * Resolve only active tenant members with active users.
     * Never create notifications with a NULL recipient.
     */
    $recipientQuery = $pdo->prepare("
        SELECT tm.user_id
        FROM tenant_memberships tm
        JOIN users u
          ON u.id = tm.user_id
        WHERE tm.tenant_id = ?
          AND tm.user_id = ?
          AND tm.role = ?
          AND tm.status = 'active'
          AND u.status = 'active'
        LIMIT 1
    ");

    $notificationInsert = $pdo->prepare("
        INSERT INTO notifications (
            tenant_id,
            user_id,
            appointment_id,
            type,
            title,
            body
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $resolveRecipient = static function (
        int $candidateUserId,
        string $role
    ) use ($recipientQuery, $tid): ?int {
        if ($candidateUserId <= 0) {
            return null;
        }

        $recipientQuery->execute([
            $tid,
            $candidateUserId,
            $role
        ]);

        $result = $recipientQuery->fetchColumn();

        return $result === false ? null : (int)$result;
    };

    $createNotification = static function (
        int $recipientUserId,
        string $type,
        string $title,
        string $body
    ) use ($notificationInsert, $tid, $appointmentId): void {
        $notificationInsert->execute([
            $tid,
            $recipientUserId,
            $appointmentId,
            $type,
            $title,
            $body
        ]);
    };

    /*
     * Winning contractor.
     */
    $q = $pdo->prepare("
        SELECT user_id
        FROM contractors
        WHERE tenant_id = ?
          AND id = ?
          AND status = 'active'
    ");
    $q->execute([$tid, $contractorId]);

    $winnerUserId = $resolveRecipient(
        (int)($q->fetchColumn() ?: 0),
        'contractor'
    );

    if ($winnerUserId !== null) {
        $createNotification(
            $winnerUserId,
            'marketplace_bid_selected',
            'Your marketplace bid was selected',
            sprintf(
                'Your bid for appointment #%d was selected. '
                . 'You have been assigned to this appointment.',
                $appointmentId
            )
        );
    }

    /*
     * Appointment client.
     */
    $q = $pdo->prepare("
        SELECT c.user_id
        FROM appointments a
        JOIN clients c
          ON c.tenant_id = a.tenant_id
         AND c.id = a.client_id
        WHERE a.tenant_id = ?
          AND a.id = ?
          AND c.status = 'active'
    ");
    $q->execute([$tid, $appointmentId]);

    $clientUserId = $resolveRecipient(
        (int)($q->fetchColumn() ?: 0),
        'client'
    );

    if ($clientUserId !== null) {
        $createNotification(
            $clientUserId,
            'marketplace_contractor_assigned',
            'Contractor assigned to your appointment',
            sprintf(
                'A contractor has been assigned to appointment #%d.',
                $appointmentId
            )
        );
    }

    /*
     * Notify only bidders whose bids were actually
     * transitioned to rejected by this award.
     */
    if ($rejectedIds) {
        $placeholders = implode(
            ',',
            array_fill(0, count($rejectedIds), '?')
        );

        $q = $pdo->prepare("
            SELECT DISTINCT c.user_id
            FROM appointment_bids b
            JOIN contractors c
              ON c.tenant_id = b.tenant_id
             AND c.id = b.contractor_id
            WHERE b.tenant_id = ?
              AND b.listing_id = ?
              AND b.id IN ($placeholders)
              AND b.status = 'rejected'
              AND c.status = 'active'
              AND c.user_id IS NOT NULL
        ");

        $q->execute(array_merge(
            [$tid, $listingId],
            $rejectedIds
        ));

        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $candidateId) {
            $rejectedUserId = $resolveRecipient(
                (int)$candidateId,
                'contractor'
            );

            if ($rejectedUserId === null) {
                continue;
            }

            $createNotification(
                $rejectedUserId,
                'marketplace_bid_rejected',
                'Marketplace listing awarded',
                sprintf(
                    'Another contractor was selected for '
                    . 'appointment #%d.',
                    $appointmentId
                )
            );
        }
    }

    $pdo->commit();

    json_response([
        'ok' => true,
        'listing_id' => $listingId,
        'appointment_id' => $appointmentId,
        'bid_id' => $bidId,
        'contractor_id' => $contractorId,
        'listing_status' => 'awarded',
        'bid_status' => 'selected',
        'assignment_status' => 'assigned'
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(['error' => $e->getMessage()], 409);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Marketplace award error: ' . $e->getMessage());

    json_response([
        'error' => 'Marketplace award could not be completed'
    ], 500);
}
