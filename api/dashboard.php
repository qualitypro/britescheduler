<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
Auth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();
function scalar(PDO $pdo,string $sql,array $p){$q=$pdo->prepare($sql);$q->execute($p);return $q->fetchColumn();}
json_response([
 'clients'=>(int)scalar($pdo,"SELECT COUNT(*) FROM clients WHERE tenant_id=? AND status='active'",[$tid]),
 'contractors'=>(int)scalar($pdo,"SELECT COUNT(*) FROM contractors WHERE tenant_id=? AND status='active'",[$tid]),
 'appointments_today'=>(int)scalar($pdo,"SELECT COUNT(*) FROM appointments WHERE tenant_id=? AND DATE(starts_at)=CURDATE() AND status NOT IN('cancelled','no_show')",[$tid]),
 'appointments_week'=>(int)scalar($pdo,"SELECT COUNT(*) FROM appointments WHERE tenant_id=? AND starts_at>=CURDATE() AND starts_at<DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND status NOT IN('cancelled','no_show')",[$tid]),
 'receivables'=>(float)scalar($pdo,"SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE tenant_id=? AND status IN('sent','partial','overdue')",[$tid]),
 'payments_month'=>(float)scalar($pdo,"SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id=? AND status='succeeded' AND paid_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')",[$tid]),
]);
