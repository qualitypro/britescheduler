<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();$tenant=Auth::tenant();$csrf=csrf_token();
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($pageTitle??'BriteScheduler')?></title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f5f7fb;color:#1f2937}.top{background:#172033;color:#fff;padding:14px 24px;display:flex;justify-content:space-between}.nav{background:#fff;padding:10px 24px;border-bottom:1px solid #ddd}.nav a{margin-right:18px;color:#334155;text-decoration:none}.wrap{max-width:1250px;margin:24px auto;padding:0 18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;box-shadow:0 2px 8px #00000008}.metric{font-size:28px;font-weight:700}table{width:100%;border-collapse:collapse;background:#fff}th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}input,select,textarea,button{padding:9px;border:1px solid #cbd5e1;border-radius:6px}button{cursor:pointer}.row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.row>*{flex:1;min-width:140px}.muted{color:#64748b}.danger{color:#b91c1c}
</style>
<script>
window.BRITE_CSRF=<?=json_encode($csrf)?>;
window.BRITE_BASE=<?=json_encode(rtrim($_ENV['APP_URL'] ?? 'http://localhost/dashboard','/'))?>;

async function api(url,opt={}){
    opt.headers={
        ...(opt.headers||{}),
        'X-CSRF-Token':window.BRITE_CSRF
    };

    if(url.startsWith('/')){
        url=window.BRITE_BASE+url;
    }

    let r=await fetch(url,opt);
    let j=await r.json();

    if(!r.ok)throw new Error(j.error||'Request failed');
    return j;
}
</script>
</head><body><div class="top"><b>BriteScheduler</b><span><?=htmlspecialchars($tenant['name'])?> · <?=htmlspecialchars($user['first_name'].' '.$user['last_name'])?> (<?=htmlspecialchars($tenant['role'])?>)</span></div>
<div class="nav"><a href="<?=htmlspecialchars(app_url('/dashboard.v2.php'))?>">Dashboard</a><a href="<?=htmlspecialchars(app_url('/clients.v2.php'))?>">Clients</a><a href="<?=htmlspecialchars(app_url('/contractors.v2.php'))?>">Contractors</a><a href="<?=htmlspecialchars(app_url('/contractor-availability.php'))?>">Availability</a><a href="<?=htmlspecialchars(app_url('/services.v2.php'))?>">Services</a><a href="<?=htmlspecialchars(app_url('/scheduling.php'))?>">Schedule</a><a href="<?=htmlspecialchars(app_url('/billing.php'))?>">Billing</a></div><main class="wrap">
