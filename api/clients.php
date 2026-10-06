<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

$u      = Auth::requireUser();
$tenant = Auth::tenant();

$tid  = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];

$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
|
| Management roles may view the tenant client directory.
| A client may view only the client record linked to their authenticated
| user account.
| Contractors may not enumerate client records.
|
*/

if ($_SERVER['REQUEST_METHOD']==='GET') {

    if ($role === 'client') {

        $q=$pdo->prepare("
            SELECT *
            FROM clients
            WHERE tenant_id=?
              AND user_id=?
            LIMIT 1
        ");

        $q->execute([
            $tid,
            (int)$u['id']
        ]);

        $client=$q->fetch();

        json_response([
            'clients'=>$client ? [$client] : []
        ]);
    }

    if ($role === 'contractor') {
        json_response([
            'error'=>'Forbidden'
        ],403);
    }

    Auth::requireRole(
        'owner',
        'admin',
        'scheduler',
        'accounting'
    );

    $q=$pdo->prepare("
        SELECT *
        FROM clients
        WHERE tenant_id=?
        ORDER BY last_name,first_name
    ");

    $q->execute([$tid]);

    json_response([
        'clients'=>$q->fetchAll()
    ]);
}

/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();

Auth::requireRole(
    'owner',
    'admin',
    'scheduler',
    'accounting'
);

$d=request_data();

if ($_SERVER['REQUEST_METHOD']==='POST') {

    $q=$pdo->prepare("
        INSERT INTO clients(
            tenant_id,
            company_name,
            first_name,
            last_name,
            email,
            phone,
            address1,
            address2,
            city,
            state,
            postal_code,
            notes,
            status
        )
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $q->execute([
        $tid,
        $d['company_name'] ?? null,
        trim($d['first_name'] ?? ''),
        trim($d['last_name'] ?? ''),
        $d['email'] ?? null,
        $d['phone'] ?? null,
        $d['address1'] ?? null,
        $d['address2'] ?? null,
        $d['city'] ?? null,
        $d['state'] ?? null,
        $d['postal_code'] ?? null,
        $d['notes'] ?? null,
        $d['status'] ?? 'active'
    ]);

    json_response([
        'ok'=>true,
        'id'=>(int)$pdo->lastInsertId()
    ],201);
}

if ($_SERVER['REQUEST_METHOD']==='PUT') {

    $id=(int)($d['id'] ?? 0);

    $q=$pdo->prepare("
        UPDATE clients
        SET
            company_name=?,
            first_name=?,
            last_name=?,
            email=?,
            phone=?,
            address1=?,
            address2=?,
            city=?,
            state=?,
            postal_code=?,
            notes=?,
            status=?
        WHERE id=?
          AND tenant_id=?
    ");

    $q->execute([
        $d['company_name'] ?? null,
        $d['first_name'] ?? '',
        $d['last_name'] ?? '',
        $d['email'] ?? null,
        $d['phone'] ?? null,
        $d['address1'] ?? null,
        $d['address2'] ?? null,
        $d['city'] ?? null,
        $d['state'] ?? null,
        $d['postal_code'] ?? null,
        $d['notes'] ?? null,
        $d['status'] ?? 'active',
        $id,
        $tid
    ]);

    json_response([
        'ok'=>true
    ]);
}

json_response([
    'error'=>'Method not allowed'
],405);
