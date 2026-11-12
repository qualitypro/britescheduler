<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/ServerPagination.php';
$assert=static function(bool $ok,string $name):void{
 if(!$ok){fwrite(STDERR,"FAIL: {$name}\n");exit(1);}
 echo "PASS: {$name}\n";
};
foreach([[],['page'=>'1','per_page'=>'1'],['page'=>'100000','per_page'=>'100'],['page'=>25,'per_page'=>50]] as $query){
 $p=ServerPagination::fromQuery($query);
 $assert($p['offset']===($p['page']-1)*$p['per_page'],'offset calculation');
}
foreach([['page'=>'0'],['page'=>'100001'],['page'=>'1.5'],['page'=>[]],['per_page'=>'0'],['per_page'=>'101'],['per_page'=>'-1'],['per_page'=>['25']]] as $query){
 try{ServerPagination::fromQuery($query);$assert(false,'reject invalid query');}
 catch(InvalidArgumentException $e){$assert(true,'reject invalid query');}
}
$p=ServerPagination::fromQuery(['page'=>2,'per_page'=>25]);
$m=ServerPagination::metadata($p,51);
$assert($m['total_pages']===3&&$m['has_next']&&$m['has_previous'],'middle page metadata');
$m=ServerPagination::metadata(ServerPagination::fromQuery([]),0);
$assert($m['total_pages']===1&&!$m['has_next']&&!$m['has_previous'],'empty metadata');
echo "PASS: pagination boundary suite\n";
