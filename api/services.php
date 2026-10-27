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


/*
|----------------------------------------------------------------------
| Update existing tenant service (preserve ID and historical references)
|----------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD']==='PUT' || $_SERVER['REQUEST_METHOD']==='PATCH') {
    $rawId=$d['id'] ?? null;
    if (!is_scalar($rawId) || !ctype_digit((string)$rawId) || (int)$rawId < 1) {
        json_response(['error'=>'Invalid service ID'],422);
    }
    $id=(int)$rawId;
    $name=trim((string)($d['name'] ?? ''));
    $description=trim((string)($d['description'] ?? ''));
    $duration=filter_var($d['duration_minutes'] ?? null,FILTER_VALIDATE_INT);
    $rawPrice=$d['price'] ?? null;
    if ($name==='' || strlen($name)>255 || $duration===false ||
        $duration<1 || $duration>10080 ||
        !is_scalar($rawPrice) || !preg_match('/^\\d{1,9}(?:\\.\\d{1,2})?$/',(string)$rawPrice)) {
        json_response(['error'=>'Invalid service name, duration or price'],422);
    }
    if (!in_array((string)($d['active'] ?? ''),['0','1'],true)) {
        json_response(['error'=>'Invalid active status'],422);
    }
    $q=$pdo->prepare('UPDATE services
        SET name=?, description=?, duration_minutes=?, price=?, active=?
        WHERE id=? AND tenant_id=?');
    $q->execute([
        $name,$description==='' ? null : $description,$duration,
        (string)$rawPrice,(int)$d['active'],$id,$tid
    ]);
    $check=$pdo->prepare('SELECT id FROM services WHERE id=? AND tenant_id=?');
    $check->execute([$id,$tid]);
    if (!$check->fetchColumn()) {
        json_response(['error'=>'Service not found'],404);
    }
    json_response(['ok'=>true,'id'=>$id]);
}

json_response([
    'error'=>'Method not allowed'
],405);
