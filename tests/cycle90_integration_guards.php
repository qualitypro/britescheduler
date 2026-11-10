<?php
declare(strict_types=1);
// Source-level integration guards for Cycle 90. Execute with php tests/cycle90_integration_guards.php.
// These do not replace authenticated API, browser, or database tests.
$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{
 $source=file_get_contents($root.'/'.$path);
 if($source===false)throw new RuntimeException('Missing source: '.$path);
 return $source;
};
$checks=[
 ['modules/crm/page.php','TenantClock::today($db,$tid)'],
 ['modules/crm/page.php',"if(in_array(\$tab,['customers','pipeline','leads','activities'],true))"],
 ['modules/crm/page.php','ORDER BY a.created_at DESC,a.id DESC'],
 ['modules/crm/page.php','ORDER BY updated_at DESC,id DESC'],
 ['modules/crm/page.php','SELECT stage,COUNT(*) AS total'],
 ['modules/crm/page.php','crmListTotal'],
 ['modules/crm/list_pager.php','crmListUrl'],
 ['api/contractor_marketplace.php',"'facets'"],
 ['api/contractor_marketplace.php','ServerPagination::metadata'],
 ['assets/js/brite-pagination.js','this.retry.hidden = false'],
 ['bidding.php','biddingProjectsPagination'],
 ['api/client_quote_requests.php',"'r.tenant_id=? AND r.client_id=?'"],
 ['api/contractor_quote_inbox.php',"'r.tenant_id=? AND r.contractor_id=?'"],
 ['api/contractor_marketplace.php',"'Auth::requireRole('contractor')'"],
 ['api/client_quote_requests.php',"'Auth::requireRole('client')'"],
 ['api/contractor_marketplace.php',"'$marketParams=[$tid,$today]'"],
 ['api/client_quote_requests.php',"'if(!is_string($rawSearch))'"],
 ['api/contractor_quote_inbox.php',"'if(!is_string($rawSearch))'"],
];
foreach($checks as [$path,$needle]){
 if(!str_contains($read($path),$needle)){
  fwrite(STDERR,"FAIL: {$path} missing {$needle}\n");exit(1);
 }
 echo "PASS: {$path}: {$needle}\n";
}
echo "PASS: Cycle 90 source integration guards\n";
