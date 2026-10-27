<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();
Auth::requireRole('owner','admin');
$pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){
    json_response(['profile'=>[
        'first_name'=>$user['first_name'],
        'last_name'=>$user['last_name'],
        'email'=>$user['email']
    ]]);
}
if($_SERVER['REQUEST_METHOD']!=='PUT') json_response(['error'=>'Method not allowed'],405);
verify_csrf();
$data=request_data();
$first=trim((string)($data['first_name']??''));
$last=trim((string)($data['last_name']??''));
$email=trim((string)($data['email']??''));
if($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL))
    json_response(['error'=>'Valid name and email required'],422);
if(strlen($first)>150||strlen($last)>150||strlen($email)>254)
    json_response(['error'=>'Field too long'],422);
try {
    $q=$pdo->prepare('UPDATE users SET first_name=?,last_name=?,email=? WHERE id=? AND status=?');
    $q->execute([$first,$last,$email,(int)$user['id'],'active']);
} catch(PDOException $e) {
    if($e->getCode()==='23000') json_response(['error'=>'Email already in use'],409);
    throw $e;
}
json_response(['ok'=>true]);
