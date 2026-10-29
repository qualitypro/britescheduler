<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireUser();
$tenant = Auth::tenant();
Auth::requireRole('owner','admin','scheduler','accounting');
require_method('GET');
$tid = (int)$tenant['tenant_id'];
$tz = new DateTimeZone((string)($tenant['timezone'] ?: 'America/New_York'));
$month = (string)($_GET['month'] ?? (new DateTimeImmutable('now',$tz))->format('Y-m'));
if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $month)) json_response(['error'=>'Invalid month'],422);
$status = (string)($_GET['status'] ?? 'all');
$allowed = ['all','tentative','scheduled','confirmed','in_progress','completed','cancelled','no_show'];
if (!in_array($status,$allowed,true)) json_response(['error'=>'Invalid status'],422);
$center = DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01',$tz);
$begin = $center->modify('-1 month');
$end = $center->modify('+2 months');
$days = [];
for($d=$begin;$d<$end;$d=$d->modify('+1 day')) $days[$d->format('Y-m-d')] = ['appointments'=>0,'requests'=>0,'assigned'=>0,'unassigned'=>0,'accepted'=>0,'declined'=>0,'gross_revenue'=>0];
$pdo = Database::connection();
try {
    $q=$pdo->prepare("SELECT DATE(a.starts_at) day, COUNT(*) total,
      SUM(EXISTS(SELECT 1 FROM appointment_contractors ac WHERE ac.appointment_id=a.id)) assigned
      FROM appointments a WHERE a.tenant_id=? AND a.starts_at>=? AND a.starts_at<?
      AND (?='all' OR a.status=?) GROUP BY DATE(a.starts_at)");
    $q->execute([$tid,$begin->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$status,$status]);
    foreach($q as $r) {if(isset($days[$r['day']])) {
        $days[$r['day']]['appointments']=(int)$r['total'];
        $days[$r['day']]['assigned']=(int)$r['assigned'];
        $days[$r['day']]['unassigned']=(int)$r['total']-(int)$r['assigned'];
    }}
    // Booking requests counted by requested appointment date, not submission date.
    $q=$pdo->prepare("SELECT DATE(a.starts_at) day,COUNT(*) total
      FROM client_booking_requests br JOIN appointments a ON a.id=br.appointment_id AND a.tenant_id=br.tenant_id
      WHERE br.tenant_id=? AND a.starts_at>=? AND a.starts_at<?
      AND (?='all' OR a.status=?) GROUP BY DATE(a.starts_at)");
    $q->execute([$tid,$begin->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$status,$status]);
    foreach($q as $r) if(isset($days[$r['day']])) $days[$r['day']]['requests']=(int)$r['total'];
    // Contractor assignment decisions grouped by appointment service date.
    // These are current assignment states, not historical response timestamps.
    $q=$pdo->prepare("SELECT DATE(a.starts_at) day, ac.status decision, COUNT(*) total
      FROM appointment_contractors ac
      JOIN appointments a ON a.id=ac.appointment_id
      JOIN contractors c ON c.id=ac.contractor_id AND c.tenant_id=a.tenant_id
      WHERE a.tenant_id=? AND a.starts_at>=? AND a.starts_at<?
      AND (?='all' OR a.status=?) AND ac.status IN ('accepted','declined')
      GROUP BY DATE(a.starts_at), ac.status");
    $q->execute([$tid,$begin->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$status,$status]);
    foreach($q as $r) if(isset($days[$r['day']])) $days[$r['day']][$r['decision']]=(int)$r['total'];
    // Gross booked value: linked non-void invoice totals where present;
    // otherwise the service's catalog price as an estimate. Each appointment
    // contributes once, so invoiced bookings are not double-counted.
    $q=$pdo->prepare("SELECT DATE(a.starts_at) day,
      ROUND(SUM(CASE WHEN inv.appointment_id IS NOT NULL THEN inv.gross_total
        ELSE COALESCE(s.price,0) END),2) gross_total
      FROM appointments a
      LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
      LEFT JOIN (
        SELECT appointment_id, tenant_id, SUM(total) gross_total
        FROM invoices WHERE status <> 'void' AND appointment_id IS NOT NULL
        GROUP BY tenant_id, appointment_id
      ) inv ON inv.appointment_id=a.id AND inv.tenant_id=a.tenant_id
      WHERE a.tenant_id=? AND a.starts_at>=? AND a.starts_at<?
        AND a.status NOT IN ('cancelled','no_show')
        AND (?='all' OR a.status=?)
      GROUP BY DATE(a.starts_at)");
    $q->execute([$tid,$begin->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$status,$status]);
    foreach($q as $r) if(isset($days[$r['day']])) $days[$r['day']]['gross_revenue']=(float)$r['gross_total'];
    json_response(['ok'=>true,'month'=>$month,'start'=>$begin->format('Y-m-d'),
      'end_exclusive'=>$end->format('Y-m-d'),'timezone'=>$tz->getName(),
      'status'=>$status,'days'=>$days]);
} catch(Throwable $e) {
    error_log('Trends: '.$e->getMessage());
    json_response(['error'=>'Unable to load trends'],500);
}
