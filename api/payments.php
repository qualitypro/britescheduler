<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
Auth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){$q=$pdo->prepare("SELECT p.*,CONCAT(c.first_name,' ',c.last_name) client_name,i.invoice_number FROM payments p JOIN clients c ON c.id=p.client_id AND c.tenant_id=p.tenant_id LEFT JOIN invoices i ON i.id=p.invoice_id AND i.tenant_id=p.tenant_id WHERE p.tenant_id=? ORDER BY p.created_at DESC");$q->execute([$tid]);json_response(['payments'=>$q->fetchAll()]);}
verify_csrf();Auth::requireRole('owner','admin','accounting');$d=request_data();
if($_SERVER['REQUEST_METHOD']==='POST'){
 $pdo->beginTransaction();try{
  $q=$pdo->prepare("INSERT INTO payments(tenant_id,invoice_id,client_id,amount,currency,status,method,provider,provider_reference,paid_at,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
  $q->execute([$tid,int_or_null($d['invoice_id']??null),(int)$d['client_id'],$d['amount'],$d['currency']??'USD',$d['status']??'succeeded',$d['method']??null,$d['provider']??null,$d['provider_reference']??null,$d['paid_at']??date('Y-m-d H:i:s'),$d['notes']??null]);
  if(!empty($d['invoice_id']) && ($d['status']??'succeeded')==='succeeded'){
   $x=$pdo->prepare("UPDATE invoices SET balance_due=GREATEST(0,balance_due-?),status=IF(balance_due-?<=0,'paid','partial') WHERE id=? AND tenant_id=?");
   $x->execute([$d['amount'],$d['amount'],(int)$d['invoice_id'],$tid]);
  }
  $pdo->commit();json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201);
 }catch(Throwable $e){$pdo->rollBack();json_response(['error'=>$e->getMessage()],422);}
}
json_response(['error'=>'Method not allowed'],405);
