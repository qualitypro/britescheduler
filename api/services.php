<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();

$tid = Auth::tenantId();
$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
|
| This is the administrative service-management endpoint.
| Public/client service discovery should use a separate deliberately
| limited catalog endpoint if self-service booking is added later.
|
*/

if ($_SERVER['REQUEST_METHOD']==='GET') {

    Auth::requireRole(
        'owner',
        'admin',
        'scheduler'
    );

    $q=$pdo->prepare("
        SELECT *
        FROM services
        WHERE tenant_id=?
        ORDER BY name
    ");

    $q->execute([$tid]);

    json_response([
        'services'=>$q->fetchAll()
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
    'scheduler'
);

$d=request_data();

if ($_SERVER['REQUEST_METHOD']==='POST') {

    $q=$pdo->prepare("
        INSERT INTO services(
            tenant_id,
            name,
            description,
            duration_minutes,
            price,
            active
        )
        VALUES(?,?,?,?,?,?)
    ");

    $q->execute([
        $tid,
        $d['name'] ?? '',
        $d['description'] ?? null,
        (int)($d['duration_minutes'] ?? 60),
        $d['price'] ?? 0,
        !empty($d['active']) ? 1 : 0
    ]);

    json_response([
        'ok'=>true,
        'id'=>(int)$pdo->lastInsertId()
    ],201);
}

json_response([
    'error'=>'Method not allowed'
],405);
