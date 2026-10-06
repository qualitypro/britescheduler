<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();
$tid = Auth::tenantId();
$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $contractorId = (int)($_GET['contractor_id'] ?? 0);

    if ($contractorId <= 0) {
        json_response(['error'=>'Contractor required'],422);
    }

    $check = $pdo->prepare("
        SELECT id
        FROM contractors
        WHERE id=? AND tenant_id=?
    ");

    $check->execute([$contractorId,$tid]);

    if (!$check->fetchColumn()) {
        json_response(['error'=>'Contractor not found'],404);
    }

    $q = $pdo->prepare("
        SELECT
            id,
            contractor_id,
            weekday,
            available_date,
            starts_at,
            ends_at,
            is_available
        FROM contractor_availability
        WHERE tenant_id=?
          AND contractor_id=?
        ORDER BY
            CASE WHEN available_date IS NULL THEN 0 ELSE 1 END,
            weekday,
            available_date,
            starts_at
    ");

    $q->execute([$tid,$contractorId]);

    json_response([
        'availability'=>$q->fetchAll()
    ]);
}

/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();
Auth::requireRole('owner','admin','scheduler');

$d = request_data();

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $contractorId=(int)($d['contractor_id'] ?? 0);

        $check=$pdo->prepare("
            SELECT id
            FROM contractors
            WHERE id=? AND tenant_id=?
        ");

        $check->execute([$contractorId,$tid]);

        if (!$check->fetchColumn()) {
            throw new RuntimeException('Invalid contractor');
        }

        $weekday =
            ($d['weekday'] ?? '') === ''
                ? null
                : (int)$d['weekday'];

        $availableDate =
            trim($d['available_date'] ?? '') ?: null;

        if ($weekday === null && $availableDate === null) {
            throw new RuntimeException(
                'Choose a weekday or a specific date'
            );
        }

        if ($weekday !== null && ($weekday < 0 || $weekday > 6)) {
            throw new RuntimeException('Invalid weekday');
        }

        /*
         * A specific-date rule should not also contain
         * a recurring weekday.
         */
        if ($availableDate !== null) {
            $weekday=null;

            $dt=DateTime::createFromFormat(
                'Y-m-d',
                $availableDate
            );

            if (
                !$dt ||
                $dt->format('Y-m-d') !== $availableDate
            ) {
                throw new RuntimeException('Invalid date');
            }
        }

        $startsAt=trim($d['starts_at'] ?? '');
        $endsAt=trim($d['ends_at'] ?? '');

        if (
            !preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$startsAt) ||
            !preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$endsAt)
        ) {
            throw new RuntimeException('Invalid time');
        }

        if (strlen($startsAt)===5) {
            $startsAt .= ':00';
        }

        if (strlen($endsAt)===5) {
            $endsAt .= ':00';
        }

        if ($endsAt <= $startsAt) {
            throw new RuntimeException(
                'End time must be after start time'
            );
        }

        $isAvailable =
            !empty($d['is_available']) ? 1 : 0;

        $q=$pdo->prepare("
            INSERT INTO contractor_availability(
                tenant_id,
                contractor_id,
                weekday,
                available_date,
                starts_at,
                ends_at,
                is_available
            )
            VALUES(?,?,?,?,?,?,?)
        ");

        $q->execute([
            $tid,
            $contractorId,
            $weekday,
            $availableDate,
            $startsAt,
            $endsAt,
            $isAvailable
        ]);

        json_response([
            'ok'=>true,
            'id'=>(int)$pdo->lastInsertId()
        ],201);

    } catch(Throwable $e) {

        json_response([
            'error'=>$e->getMessage()
        ],422);
    }
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $id=(int)($d['id'] ?? 0);

    $q=$pdo->prepare("
        DELETE FROM contractor_availability
        WHERE id=? AND tenant_id=?
    ");

    $q->execute([$id,$tid]);

    if (!$q->rowCount()) {
        json_response([
            'error'=>'Availability rule not found'
        ],404);
    }

    json_response(['ok'=>true]);
}

json_response([
    'error'=>'Method not allowed'
],405);
