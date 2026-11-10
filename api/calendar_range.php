<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/ServerCalendar.php';
Auth::requireUser();
$tenant=Auth::tenant();
$role=(string)$tenant['role'];
if (!in_array($role,['owner','admin','scheduler','accounting','contractor','client'],true)) {
    json_response(['error'=>'Forbidden'],403);
}
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') {
    header('Allow: GET');
    json_response(['error'=>'Method not allowed'],405);
}
$pdo=Database::connection();
$tid=(int)$tenant['tenant_id'];
try {
    $q=$pdo->prepare('SELECT timezone FROM tenants WHERE id=? LIMIT 1');
    $q->execute([$tid]);
    $timezone=(string)$q->fetchColumn();
    $zone=new DateTimeZone($timezone);
    $date=(string)($_GET['date']??(new DateTimeImmutable('now',$zone))->format('Y-m-d'));
    $view=(string)($_GET['view']??'month');
    $weekStart=(string)($_GET['week_start']??'0');
    if (!in_array($weekStart,['0','1'],true)) {
        throw new InvalidArgumentException('Invalid week start');
    }
    $range=ServerCalendar::range($date,$view,(int)$weekStart);
    json_response(['ok'=>true,'range'=>$range,'timezone'=>$timezone,'end_exclusive'=>true]);
} catch (InvalidArgumentException $e) {
    json_response(['error'=>$e->getMessage()],422);
} catch (Throwable $e) {
    error_log('Calendar range API: '.$e->getMessage());
    json_response(['error'=>'Unable to calculate calendar range'],500);
}
