<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/ModuleRegistry.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    Auth::requireUser();
    $tenant=Auth::tenant();
    if (!isset(ModuleRegistry::activeFor($tenant)['intelligence'])) {
        http_response_code(403);
        echo json_encode(['error'=>'Intelligence is unavailable']); exit;
    }
    if (!in_array((string)($tenant['role']??''),['owner','admin','accounting'],true)) {
        http_response_code(403);
        echo json_encode(['error'=>'Financial metrics require owner, admin or accounting access']); exit;
    }
    $tid=(int)$tenant['tenant_id'];
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){
        header('Allow: GET, HEAD');http_response_code(405);
        echo json_encode(['error'=>'Method not allowed']);exit;
    }
    $catalog=[
      'revenue_received'=>['name'=>'Payments received','group'=>'Realized','description'=>'Successful payment amounts by payment date'],
      'invoiced'=>['name'=>'Invoiced value','group'=>'Billing','description'=>'Non-void invoice totals by invoice creation date'],
      'outstanding'=>['name'=>'Outstanding receivables','group'=>'Billing','description'=>'Remaining balances on non-void invoices created during the selected period'],
      'opportunity_won'=>['name'=>'Completed service value','group'=>'Opportunities','description'=>'Estimated service value of completed appointments'],
      'opportunity_open'=>['name'=>'Open opportunity value','group'=>'Opportunities','description'=>'Estimated service value of tentative, scheduled, confirmed and in-progress appointments'],
      'opportunity_lost'=>['name'=>'Lost opportunity value','group'=>'Opportunities','description'=>'Estimated service value of cancelled and no-show appointments'],
    ];
    if (($_GET['action']??'')==='catalog') {
        echo json_encode(['metrics'=>array_map(static fn($key,$m)=>['key'=>$key]+$m,array_keys($catalog),array_values($catalog))],JSON_THROW_ON_ERROR); exit;
    }
    $requested=(string)($_GET['metrics']??'revenue_received,outstanding,opportunity_open,opportunity_lost');
    if(strlen($requested)>1024)throw new InvalidArgumentException('Metric selection is too long');
    $keys=array_values(array_unique(array_filter(explode(',',$requested))));
    if (!$keys || count($keys)>6 || array_diff($keys,array_keys($catalog))) throw new InvalidArgumentException('Select 1–6 financial metrics');
    $interval=(string)($_GET['interval']??'month');
    if (!in_array($interval,['day','week','month'],true)) throw new InvalidArgumentException('Invalid interval');
    $from=(string)($_GET['from']??date('Y-m-01',strtotime('-5 months')));
    $to=(string)($_GET['to']??date('Y-m-d'));
    foreach ([$from,$to] as $date) {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$d || $d->format('Y-m-d')!==$date) throw new InvalidArgumentException('Invalid date');
    }
    if ($from>$to || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days>730) throw new InvalidArgumentException('Date range must be 0–730 days');
    $format=match($interval) {
        'day'=>"'%Y-%m-%d'",
        'week'=>"'%Y-%m-%d'",
        default=>"'%Y-%m'"
    };
    $bucket=static function(string $date) use($interval,$format):string {
        if ($interval==='week') return "DATE_FORMAT(DATE_SUB($date,INTERVAL WEEKDAY($date) DAY),$format)";
        return "DATE_FORMAT($date,$format)";
    };
    $pdo=Database::connection();
    $series=[];
    foreach ($keys as $key) {
        if ($key==='revenue_received') {
            $date='paid_at';
            $expr="SUM(CASE WHEN status='succeeded' THEN amount ELSE 0 END)";
            $table='payments';$join='';
            $extra='';
        } elseif ($key==='invoiced' || $key==='outstanding') {
            $date='created_at';$table='invoices';$join='';
            $expr=$key==='invoiced'?"SUM(CASE WHEN status<>'void' THEN total ELSE 0 END)":"SUM(CASE WHEN status<>'void' THEN balance_due ELSE 0 END)";
            $extra='';
        } else {
            $date='a.starts_at';$table='appointments a';
            $join='LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id';
            $statuses=match($key) {
                'opportunity_won'=>"'completed'",
                'opportunity_lost'=>"'cancelled','no_show'",
                default=>"'tentative','scheduled','confirmed','in_progress'"
            };
            $expr="SUM(CASE WHEN a.status IN ($statuses) THEN COALESCE(s.price,0) ELSE 0 END)";
            $extra='';
        }
        $tenantColumn=str_starts_with($key,'opportunity_')?'a.tenant_id':'tenant_id';
        $sql="SELECT ".$bucket($date)." AS bucket, COALESCE(ROUND($expr,2),0) AS value FROM $table $join WHERE $tenantColumn=? AND $date >= ? AND $date < DATE_ADD(?,INTERVAL 1 DAY) GROUP BY bucket ORDER BY bucket";
        $stmt=$pdo->prepare($sql);
        $stmt->execute([$tid,$from,$to]);
        $points=array_map(static fn($r)=>['bucket'=>(string)$r['bucket'],'value'=>(float)$r['value']],$stmt->fetchAll(PDO::FETCH_ASSOC));
        $series[]=['key'=>$key,'name'=>$catalog[$key]['name'],'group'=>$catalog[$key]['group'],'description'=>$catalog[$key]['description'],'unit'=>'USD','points'=>$points,'total'=>round(array_sum(array_column($points,'value')),2)];
    }
    echo json_encode(['from'=>$from,'to'=>$to,'interval'=>$interval,'currency'=>'USD','valuation_note'=>'Opportunity amounts estimate service list prices at the time of reporting, not historical contracted prices. Missing service prices count as zero. Payments and invoices are separate accounting measures and must not be added to opportunity totals. Outstanding receivables are balances on invoices created in the selected period, not an as-of balance.', 'series'=>$series],JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Intelligence financial metrics: '.$e->getMessage());
    http_response_code(500);echo json_encode(['error'=>'Unable to load financial metrics']);
}
