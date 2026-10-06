<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::tenant();

$tid  = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];

$pdo = Database::connection();

$managementRoles = [
    'owner',
    'admin',
    'scheduler'
];

/*
|--------------------------------------------------------------------------
| Resolve contractor
|--------------------------------------------------------------------------
|
| Management users may specify contractor_id.
|
| Contractors NEVER control contractor_id. Their contractor record is
| derived from the authenticated users.id -> contractors.user_id link.
|
*/

function resolve_availability_contractor(
    PDO $pdo,
    int $tid,
    array $user,
    string $role,
    array $managementRoles,
    int $requestedId = 0
): int {

    if ($role === 'contractor') {

        $q=$pdo->prepare("
            SELECT id
            FROM contractors
            WHERE tenant_id=?
              AND user_id=?
              AND status='active'
            LIMIT 1
        ");

        $q->execute([
            $tid,
            (int)$user['id']
        ]);

        $id=(int)($q->fetchColumn() ?: 0);

        if ($id <= 0) {
            json_response([
                'error'=>'Contractor profile not found'
            ],403);
        }

        return $id;
    }

    if (!in_array($role,$managementRoles,true)) {
        json_response(['error'=>'Forbidden'],403);
    }

    if ($requestedId <= 0) {
        json_response([
            'error'=>'Contractor required'
        ],422);
    }

    $q=$pdo->prepare("
        SELECT id
        FROM contractors
        WHERE id=?
          AND tenant_id=?
    ");

    $q->execute([
        $requestedId,
        $tid
    ]);

    if (!$q->fetchColumn()) {
        json_response([
            'error'=>'Contractor not found'
        ],404);
    }

    return $requestedId;
}


/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $requestedId =
        (int)($_GET['contractor_id'] ?? 0);

    $contractorId =
        resolve_availability_contractor(
            $pdo,
            $tid,
            $user,
            $role,
            $managementRoles,
            $requestedId
        );

    $q=$pdo->prepare("
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
            CASE
                WHEN available_date IS NULL
                THEN 0 ELSE 1
            END,
            weekday,
            available_date,
            starts_at
    ");

    $q->execute([
        $tid,
        $contractorId
    ]);

    json_response([
        'contractor_id'=>$contractorId,
        'availability'=>$q->fetchAll()
    ]);
}


/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();

if (
    $role !== 'contractor' &&
    !in_array($role,$managementRoles,true)
) {
    json_response(['error'=>'Forbidden'],403);
}

$d=request_data();


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $requestedId =
            (int)($d['contractor_id'] ?? 0);

        $contractorId =
            resolve_availability_contractor(
                $pdo,
                $tid,
                $user,
                $role,
                $managementRoles,
                $requestedId
            );

        $weekday =
            ($d['weekday'] ?? '') === ''
                ? null
                : (int)$d['weekday'];

        $availableDate =
            trim($d['available_date'] ?? '')
                ?: null;

        if (
            $weekday === null &&
            $availableDate === null
        ) {
            throw new RuntimeException(
                'Choose a weekday or a specific date'
            );
        }

        if (
            $weekday !== null &&
            ($weekday < 0 || $weekday > 6)
        ) {
            throw new RuntimeException(
                'Invalid weekday'
            );
        }

        if ($availableDate !== null) {

            $weekday=null;

            $dt=DateTime::createFromFormat(
                'Y-m-d',
                $availableDate
            );

            if (
                !$dt ||
                $dt->format('Y-m-d')
                    !== $availableDate
            ) {
                throw new RuntimeException(
                    'Invalid date'
                );
            }
        }

        $startsAt=
            trim($d['starts_at'] ?? '');

        $endsAt=
            trim($d['ends_at'] ?? '');

        if (
            !preg_match(
                '/^\d{2}:\d{2}(:\d{2})?$/',
                $startsAt
            ) ||
            !preg_match(
                '/^\d{2}:\d{2}(:\d{2})?$/',
                $endsAt
            )
        ) {
            throw new RuntimeException(
                'Invalid time'
            );
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
            !empty($d['is_available'])
                ? 1 : 0;

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

    if ($id <= 0) {
        json_response([
            'error'=>'Availability rule required'
        ],422);
    }

    /*
     * Contractor deletion is restricted to rules
     * belonging to their own contractor profile.
     */

    if ($role === 'contractor') {

        $contractorId =
            resolve_availability_contractor(
                $pdo,
                $tid,
                $user,
                $role,
                $managementRoles
            );

        $q=$pdo->prepare("
            DELETE FROM contractor_availability
            WHERE id=?
              AND tenant_id=?
              AND contractor_id=?
        ");

        $q->execute([
            $id,
            $tid,
            $contractorId
        ]);

    } else {

        $q=$pdo->prepare("
            DELETE FROM contractor_availability
            WHERE id=?
              AND tenant_id=?
        ");

        $q->execute([
            $id,
            $tid
        ]);
    }

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
