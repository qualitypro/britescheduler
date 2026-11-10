<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/ServerCalendar.php';
require_once dirname(__DIR__).'/app/ServerPagination.php';
function verify(bool $ok,string $label):void { if(!$ok){fwrite(STDERR,'FAIL '.$label.PHP_EOL);exit(1);} echo 'PASS '.$label.PHP_EOL; }
$m=ServerCalendar::range('2026-11-10','month',0);
verify($m['start']==='2026-11-01 00:00:00' && $m['end']==='2026-12-06 00:00:00','Sunday-first month');
$w=ServerCalendar::range('2026-11-10','week',1);
verify($w['start']==='2026-11-09 00:00:00' && $w['end']==='2026-11-16 00:00:00','Monday-first week');
$d=ServerCalendar::range('2026-11-10','day');
verify($d['end']==='2026-11-11 00:00:00','Exclusive day end');
$p=ServerPagination::fromQuery(['page'=>'3','per_page'=>'15']);
verify($p['offset']===30,'Pagination offset');
verify(ServerPagination::metadata($p,32)['has_next']===false,'Pagination metadata');
try{ServerPagination::fromQuery(['per_page'=>'1000']);verify(false,'Bounded limit');}catch(InvalidArgumentException $e){verify(true,'Bounded limit');}
try{ServerCalendar::range('2026-02-30','day');verify(false,'Invalid date');}catch(InvalidArgumentException $e){verify(true,'Invalid date');}
