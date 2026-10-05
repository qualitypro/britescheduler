<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$u=Auth::requireUser(); $tid=Auth::tenantId(); $pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $q=$pdo->prepare("SELECT * FROM contractors WHERE tenant_id=? ORDER BY last_name,first_name");$q->execute([$tid]);json_response(['contractors'=>$q->fetchAll()]);
}
verify_csrf();Auth::requireRole('owner','admin','scheduler');$d=request_data();
if($_SERVER['REQUEST_METHOD']==='POST'){
 $q=$pdo->prepare("INSERT INTO contractors(tenant_id,first_name,last_name,business_name,email,phone,hourly_rate,color,skills,status) VALUES(?,?,?,?,?,?,?,?,?,?)");
 $q->execute([$tid,$d['first_name']??'',$d['last_name']??'',$d['business_name']??null,$d['email']??null,$d['phone']??null,$d['hourly_rate']??null,$d['color']??null,$d['skills']??null,$d['status']??'active']);
 json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
 $id=(int)($d['id']??0);$q=$pdo->prepare("UPDATE contractors SET first_name=?,last_name=?,business_name=?,email=?,phone=?,hourly_rate=?,color=?,skills=?,status=? WHERE id=? AND tenant_id=?");
 $q->execute([$d['first_name']??'',$d['last_name']??'',$d['business_name']??null,$d['email']??null,$d['phone']??null,$d['hourly_rate']??null,$d['color']??null,$d['skills']??null,$d['status']??'active',$id,$tid]);json_response(['ok'=>true]);
}
json_response(['error'=>'Method not allowed'],405);
