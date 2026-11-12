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
| Management roles may view the tenant contractor directory.
| A contractor may view only the contractor record linked to their
| authenticated user account.
| Clients and accounting users may not enumerate contractors.
|
*/

if ($_SERVER['REQUEST_METHOD']==='GET') {

    if ($role === 'contractor') {

        $q=$pdo->prepare("
            SELECT *
            FROM contractors
            WHERE tenant_id=?
              AND user_id=?
            LIMIT 1
        ");

        $q->execute([
            $tid,
            (int)$u['id']
        ]);

        $contractor=$q->fetch();

        json_response([
            'contractors'=>$contractor ? [$contractor] : []
        ]);
    }

    if (
        $role === 'client' ||
        $role === 'accounting'
    ) {
        json_response([
            'error'=>'Forbidden'
        ],403);
    }

    Auth::requireRole(
        'owner',
        'admin',
        'scheduler'
    );

    // Opt-in pagination; preserve unpaginated responses for existing selects.
    if (isset($_GET['page']) || isset($_GET['per_page'])) {
        require_once dirname(__DIR__).'/app/ServerPagination.php';
        try {
            $paging=ServerPagination::fromQuery($_GET);
        } catch (InvalidArgumentException $e) {
            json_response(['error'=>$e->getMessage()],422);
        }
        $count=$pdo->prepare('SELECT COUNT(*) FROM contractors WHERE tenant_id=?');
        $count->execute([$tid]);
        $total=(int)$count->fetchColumn();
        $limit=$paging['per_page'];
        $offset=$paging['offset'];
        // Only allow known sortable columns; never interpolate user input.
        $sort = $_GET['sort'] ?? 'name';
        $direction = $_GET['direction'] ?? 'asc';
        if (!is_string($sort) || !in_array($sort,['name','created_at','updated_at','status'],true) ||
            !is_string($direction) || !in_array($direction,['asc','desc'],true)) {
            json_response(['error'=>'Invalid sort parameters'],422);
        }
        $order = [
            'name'=>'last_name,first_name',
            'created_at'=>'created_at',
            'updated_at'=>'updated_at',
            'status'=>'status'
        ][$sort];
        $dir = strtoupper($direction);
        if ($sort==='name') {
            $order='last_name '.$dir.',first_name '.$dir;
        } else {
            $order.=' '.$dir;
        }
        $q=$pdo->prepare("SELECT * FROM contractors WHERE tenant_id=? ORDER BY {$order},id {$dir} LIMIT {$limit} OFFSET {$offset}");
        $q->execute([$tid]);
        json_response(['contractors'=>$q->fetchAll(), 'pagination'=>ServerPagination::metadata($paging,$total)]);
    }

    $q=$pdo->prepare("
        SELECT *
        FROM contractors
        WHERE tenant_id=?
        ORDER BY last_name,first_name
    ");

    $q->execute([$tid]);

    json_response([
        'contractors'=>$q->fetchAll()
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
        INSERT INTO contractors(
            tenant_id,
            first_name,
            last_name,
            business_name,
            email,
            phone,
            hourly_rate,
            color,
            skills,
            status
        )
        VALUES(?,?,?,?,?,?,?,?,?,?)
    ");

    $q->execute([
        $tid,
        $d['first_name'] ?? '',
        $d['last_name'] ?? '',
        $d['business_name'] ?? null,
        $d['email'] ?? null,
        $d['phone'] ?? null,
        $d['hourly_rate'] ?? null,
        $d['color'] ?? null,
        $d['skills'] ?? null,
        $d['status'] ?? 'active'
    ]);

    json_response([
        'ok'=>true,
        'id'=>(int)$pdo->lastInsertId()
    ],201);
}

if ($_SERVER['REQUEST_METHOD']==='PUT') {

    $rawId=$d['id'] ?? null;
    if (!is_scalar($rawId) || !ctype_digit((string)$rawId) || (int)$rawId<1) {
        json_response(['error'=>'Invalid record ID'],422);
    }
    $id=(int)$rawId;
    foreach (['first_name','last_name'] as $field) {
        if (!isset($d[$field]) || !is_string($d[$field]) ||
            trim($d[$field])==='' || strlen($d[$field])>255) {
            json_response(['error'=>'Invalid '.$field],422);
        }
    }
    if (!in_array($d['status'] ?? null,['active','inactive'],true)) {
        json_response(['error'=>'Invalid status'],422);
    }
    if (isset($d['email']) && $d['email']!=='' &&
        (!is_string($d['email']) || !filter_var($d['email'],FILTER_VALIDATE_EMAIL))) {
        json_response(['error'=>'Invalid email'],422);
    }
    $exists=$pdo->prepare('SELECT id FROM contractors WHERE id=? AND tenant_id=?');
    $exists->execute([$id,$tid]);
    if (!$exists->fetchColumn()) {
        json_response(['error'=>'Record not found'],404);
    }

    $q=$pdo->prepare("
        UPDATE contractors
        SET
            first_name=?,
            last_name=?,
            business_name=?,
            email=?,
            phone=?,
            hourly_rate=?,
            color=?,
            skills=?,
            status=?
        WHERE id=?
          AND tenant_id=?
    ");

    $q->execute([
        $d['first_name'] ?? '',
        $d['last_name'] ?? '',
        $d['business_name'] ?? null,
        $d['email'] ?? null,
        $d['phone'] ?? null,
        $d['hourly_rate'] ?? null,
        $d['color'] ?? null,
        $d['skills'] ?? null,
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
