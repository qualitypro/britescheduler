<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/ModuleRegistry.php';
require_once __DIR__.'/Providers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    Auth::requireUser();
    $tenant=Auth::tenant();
    if (!isset(ModuleRegistry::activeFor($tenant)['intelligence'])) {
        http_response_code(403); echo json_encode(['error'=>'Intelligence is not enabled for this role']); exit;
    }
    $tid=(int)$tenant['tenant_id'];
    $metrics=[
        'appointments'=>['name'=>'Appointments','source'=>'Scheduling','table'=>'appointments','date'=>'starts_at','expr'=>'COUNT(*)'],
        'completed'=>['name'=>'Completed appointments','source'=>'Scheduling','table'=>'appointments','date'=>'starts_at','expr'=>"SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END)"],
        'cancelled'=>['name'=>'Cancelled appointments','source'=>'Scheduling','table'=>'appointments','date'=>'starts_at','expr'=>"SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END)"],
        'scheduled_hours'=>['name'=>'Scheduled hours','source'=>'Scheduling','table'=>'appointments','date'=>'starts_at','expr'=>'ROUND(SUM(TIMESTAMPDIFF(MINUTE,starts_at,ends_at))/60,2)'],
        'new_clients'=>['name'=>'New clients','source'=>'Clients','table'=>'clients','date'=>'created_at','expr'=>'COUNT(*)'],
        'new_contractors'=>['name'=>'New contractors','source'=>'Contractors','table'=>'contractors','date'=>'created_at','expr'=>'COUNT(*)'],
        'invoices'=>['name'=>'Invoices issued','source'=>'Finance','table'=>'invoices','date'=>'created_at','expr'=>'COUNT(*)'],
        'invoice_total'=>['name'=>'Invoiced amount','source'=>'Finance','table'=>'invoices','date'=>'created_at','expr'=>'ROUND(SUM(total),2)'],
        'outstanding'=>['name'=>'Outstanding balance','source'=>'Finance','table'=>'invoices','date'=>'created_at','expr'=>'ROUND(SUM(balance_due),2)'],
        'payments'=>['name'=>'Successful payments','source'=>'Finance','table'=>'payments','date'=>'paid_at','expr'=>"ROUND(SUM(CASE WHEN status='succeeded' THEN amount ELSE 0 END),2)"],
    ];
    $pdo=Database::connection();
    $metrics=array_merge($metrics,IntelligenceProviders::discover($pdo,$tenant));
    $catalog=array_map(static fn($k,$m)=>['key'=>$k,'name'=>$m['name'],'source'=>$m['source']],array_keys($metrics),array_values($metrics));
    if (($_GET['action']??'')==='catalog') {
        echo json_encode(['metrics'=>$catalog],JSON_THROW_ON_ERROR); exit;
    }
    $keys=array_values(array_unique(array_filter(explode(',',(string)($_GET['metrics']??'appointments,completed')))));
    if (!$keys || count($keys)>8 || array_diff($keys,array_keys($metrics))) throw new InvalidArgumentException('Select 1–8 valid metrics');
    $interval=(string)($_GET['interval']??'month');
    if (!in_array($interval,['day','week','month'],true)) throw new InvalidArgumentException('Invalid interval');
    $from=(string)($_GET['from']??date('Y-m-01',strtotime('-5 months')));
    $to=(string)($_GET['to']??date('Y-m-d'));
    foreach ([$from,$to] as $date) {
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$dt || $dt->format('Y-m-d')!==$date) throw new InvalidArgumentException('Invalid date');
    }
    if ($from>$to || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days>730) throw new InvalidArgumentException('Date range must be 0–730 days');
    $bucket=match($interval) {
        'day'=>'DATE_FORMAT(%s,\'%Y-%m-%d\')',
        'week'=>'DATE_FORMAT(DATE_SUB(%s,INTERVAL WEEKDAY(%s) DAY),\'%Y-%m-%d\')',
        default=>'DATE_FORMAT(%s,\'%Y-%m\')'
    };
    $series=[];
    foreach($keys as $key){
        $m=$metrics[$key];$date=$m['date'];
        $group=sprintf($bucket,$date,$date);
        $sql="SELECT $group AS bucket, {$m['expr']} AS value FROM {$m['table']} WHERE tenant_id=? AND $date >= ? AND $date < DATE_ADD(?,INTERVAL 1 DAY) GROUP BY bucket ORDER BY bucket";
        $stmt=$pdo->prepare($sql);$stmt->execute([$tid,$from,$to]);
        $series[]=['key'=>$key,'name'=>$m['name'],'source'=>$m['source'],'points'=>array_map(static fn($r)=>['bucket'=>$r['bucket'],'value'=>(float)$r['value']],$stmt->fetchAll(PDO::FETCH_ASSOC))];
    }
    header('Cache-Control: no-store');
    echo json_encode(['from'=>$from,'to'=>$to,'interval'=>$interval,'series'=>$series],JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Intelligence metrics: '.$e->getMessage());
    http_response_code(500);echo json_encode(['error'=>'Unable to load Intelligence metrics']);
}
