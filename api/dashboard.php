<?php
require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();
Auth::requireRole('owner','admin','scheduler','accounting');

$tid = Auth::tenantId();
$pdo = Database::connection();

function scalar(PDO $pdo, string $sql, array $params) {
    $q = $pdo->prepare($sql);
    $q->execute($params);
    return $q->fetchColumn();
}

$timezoneName = scalar(
    $pdo,
    'SELECT timezone FROM tenants WHERE id=?',
    [$tid]
);

try {
    $timezone = new DateTimeZone(
        (string)($timezoneName ?: 'America/New_York')
    );
} catch (Throwable $e) {
    json_response(['error'=>'Invalid tenant timezone'], 500);
}

$today = new DateTimeImmutable('today', $timezone);
$tomorrow = $today->modify('+1 day');
$weekEnd = $today->modify('+7 days');
$monthStart = $today->modify('first day of this month');

$todayStartSql = $today->format('Y-m-d H:i:s');
$tomorrowStartSql = $tomorrow->format('Y-m-d H:i:s');
$weekEndSql = $weekEnd->format('Y-m-d H:i:s');
$monthStartSql = $monthStart->format('Y-m-d H:i:s');

json_response([
    'clients'=>(int)scalar(
        $pdo,
        "SELECT COUNT(*) FROM clients WHERE tenant_id=? AND status='active'",
        [$tid]
    ),
    'contractors'=>(int)scalar(
        $pdo,
        "SELECT COUNT(*) FROM contractors WHERE tenant_id=? AND status='active'",
        [$tid]
    ),
    'appointments_today'=>(int)scalar(
        $pdo,
        "SELECT COUNT(*) FROM appointments
         WHERE tenant_id=?
           AND starts_at>=?
           AND starts_at<?
           AND status NOT IN('cancelled','no_show')",
        [$tid,$todayStartSql,$tomorrowStartSql]
    ),
    'appointments_week'=>(int)scalar(
        $pdo,
        "SELECT COUNT(*) FROM appointments
         WHERE tenant_id=?
           AND starts_at>=?
           AND starts_at<?
           AND status NOT IN('cancelled','no_show')",
        [$tid,$todayStartSql,$weekEndSql]
    ),
    'receivables'=>(float)scalar(
        $pdo,
        "SELECT COALESCE(SUM(balance_due),0) FROM invoices
         WHERE tenant_id=?
           AND status IN('sent','partial','overdue')",
        [$tid]
    ),
    'payments_month'=>(float)scalar(
        $pdo,
        "SELECT COALESCE(SUM(amount),0) FROM payments
         WHERE tenant_id=?
           AND status='succeeded'
           AND paid_at>=?",
        [$tid,$monthStartSql]
    ),
]);
