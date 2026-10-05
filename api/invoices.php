<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
Auth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){$q=$pdo->prepare("SELECT i.*,CONCAT(c.first_name,' ',c.last_name) client_name FROM invoices i JOIN clients c ON c.id=i.client_id AND c.tenant_id=i.tenant_id WHERE i.tenant_id=? ORDER BY i.created_at DESC");$q->execute([$tid]);json_response(['invoices'=>$q->fetchAll()]);}
verify_csrf();Auth::requireRole('owner','admin','accounting');$d=request_data();
if($_SERVER['REQUEST_METHOD']==='POST'){
 $num=$d['invoice_number']??('INV-'.date('Ymd-His'));
 $total=(float)($d['total']??0);
 $q=$pdo->prepare("INSERT INTO invoices(tenant_id,client_id,appointment_id,invoice_number,status,subtotal,tax_amount,total,balance_due,due_at,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
 $q->execute([$tid,(int)$d['client_id'],int_or_null($d['appointment_id']??null),$num,$d['status']??'draft',$d['subtotal']??$total,$d['tax_amount']??0,$total,$d['balance_due']??$total,$d['due_at']??null,$d['notes']??null]);
 json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201);
}
json_response(['error'=>'Method not allowed'],405);
