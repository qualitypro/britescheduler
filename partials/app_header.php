<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();$tenant=Auth::tenant();$csrf=csrf_token();

$dashboardPath = match ($tenant['role']) {
    'client' => '/client-dashboard.php',
    'contractor' => '/contractor-dashboard.php',
    default => '/dashboard.v2.php',
};
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($pageTitle??'BriteScheduler')?></title>
<link rel="stylesheet"
      href="<?=htmlspecialchars(app_url('/assets/css/brite-design-system.css'))?>">

<link rel="stylesheet"
      href="<?=htmlspecialchars(app_url('/assets/css/brite-app-shell.css'))?>">
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


async function briteSignOut(){

    try{

        await api('/api/auth/logout.php',{
            method:'POST'
        });

        location.href =
            window.BRITE_BASE +
            '/sign-in.v2.php';

    }catch(err){

        alert(
            err.message ||
            'Unable to sign out'
        );
    }
}

</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const current = location.pathname.split('/').pop();
    document.querySelectorAll('.brite-sidebar-nav a').forEach(function (link) {
        const target = new URL(link.href, location.href).pathname.split('/').pop();
        if (target === current) {
            link.setAttribute('aria-current', 'page');
        }
    });
});
</script>
</head>
<body class="brite-app">

<aside class="brite-sidebar" id="briteSidebar" aria-label="Main navigation">
    <a class="brite-brand"
       href="<?=htmlspecialchars(app_url($dashboardPath),ENT_QUOTES,'UTF-8')?>">
        <span class="brite-brand-mark" aria-hidden="true">B</span>
        <span>Brite Scheduler</span>
    </a>


    <nav class="brite-sidebar-nav" aria-label="Primary">

<?php if(in_array($tenant['role'],['owner','admin','scheduler','accounting'],true)): ?>
<div class="brite-nav-group-label">Workspace</div>
<a href="<?=htmlspecialchars(app_url('/dashboard.v2.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></span>
Dashboard
</a>
<?php endif; ?>

<?php if(in_array($tenant['role'],['owner','admin','scheduler'],true)): ?>
<a href="<?=htmlspecialchars(app_url('/scheduling.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 15h3M8 18h3"/></svg></span>
Schedule
</a>
<?php endif; ?>

<?php if(in_array($tenant['role'],['owner','admin','scheduler','accounting'],true)): ?>
<div class="brite-nav-group-label">Management</div>
<a href="<?=htmlspecialchars(app_url('/clients.v2.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
Clients
</a>
<?php endif; ?>

<?php if(in_array($tenant['role'],['owner','admin','scheduler'],true)): ?>
<a href="<?=htmlspecialchars(app_url('/client-locations.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
Locations
</a>
<a href="<?=htmlspecialchars(app_url('/service-locations-map.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15"/><circle cx="12" cy="12" r="2"/></svg></span>
Service Locations Map
</a>
<a href="<?=htmlspecialchars(app_url('/contractors.v2.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 18h20M4 18v-3a8 8 0 0 1 16 0v3M12 7v6M8 8v3M16 8v3"/></svg></span>
Contractors
</a>
<a href="<?=htmlspecialchars(app_url('/contractor-service-areas.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15"/></svg></span>
Service Areas
</a>
<a href="<?=htmlspecialchars(app_url('/contractor-availability.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
Availability
</a>
<a href="<?=htmlspecialchars(app_url('/services.v2.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12a20 20 0 0 0 18 0"/></svg></span>
Services
</a>
<?php endif; ?>

<?php if(in_array($tenant['role'],['owner','admin','accounting'],true)): ?>
<div class="brite-nav-group-label">Administration</div>
<a href="<?=htmlspecialchars(app_url('/billing.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg></span>
Billing
</a>
<?php endif; ?>

<?php if(in_array($tenant['role'],['owner','admin'],true)): ?>
<a href="<?=htmlspecialchars(app_url('/settings.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.86 2.86-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 1.55V21h-4v-.05A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.86-2.86.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3v-4h.05A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.88L4.2 7.06 7.06 4.2l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3h4v.05A1.7 1.7 0 0 0 15 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.86 2.86-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.55 1H21v4h-.05A1.7 1.7 0 0 0 19.4 15Z"/></svg></span>
Settings
</a>
<?php endif; ?>

<?php if($tenant['role']==='contractor'): ?>
<div class="brite-nav-group-label">Workspace</div>
<a href="<?=htmlspecialchars(app_url('/contractor-dashboard.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></span>
My Dashboard
</a>
<a href="<?=htmlspecialchars(app_url('/contractor-service-areas.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15"/></svg></span>
My Service Areas
</a>
<a href="<?=htmlspecialchars(app_url('/my-availability.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
My Availability
</a>
<?php endif; ?>

<?php if($tenant['role']==='client'): ?>
<a class="brite-client-booking-nav"
   href="<?=htmlspecialchars(app_url('/client-dashboard.php?book=1'),ENT_QUOTES,'UTF-8')?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M12 14v5M9.5 16.5h5"/></svg></span>
Book a Service
</a>
<div class="brite-nav-group-label">Workspace</div>
<a href="<?=htmlspecialchars(app_url('/client-dashboard.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></span>
My Dashboard
</a>
<a href="<?=htmlspecialchars(app_url('/client-locations.php'))?>">
<span class="brite-nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
My Locations
</a>
<?php endif; ?>

</nav>

    <div class="brite-sidebar-footer">
        <?=htmlspecialchars($tenant['name'])?>
    </div>
</aside>

<button
    type="button"
    class="brite-sidebar-backdrop"
    id="briteSidebarBackdrop"
    aria-label="Close navigation"
    tabindex="-1"></button>

<div class="brite-workspace">

    <header class="brite-topbar">
        <div style="display:flex;align-items:center;gap:12px;min-width:0">
            <button
                type="button"
                class="brite-mobile-toggle"
                id="briteMobileToggle"
                aria-label="Open navigation"
                aria-controls="briteSidebar"
                aria-expanded="false">&#9776;</button>

            <div class="brite-topbar-title">
                <?=htmlspecialchars($pageTitle??'Dashboard')?>
            </div>
        </div>

        <div class="brite-topbar-actions">
            <div class="brite-account">
                <div class="brite-avatar" aria-hidden="true">
                    <?=htmlspecialchars(strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)))?>
                </div>

                <div class="brite-account-details">
                    <span class="brite-account-name">
                        <?=htmlspecialchars($user['first_name'].' '.$user['last_name'])?>
                    </span>
                    <span class="brite-account-role">
                        <?=htmlspecialchars(ucfirst($tenant['role']))?>
                    </span>
                </div>
            </div>

            <button type="button"
                    class="brite-signout"
                    onclick="briteSignOut()">Sign Out</button>
        </div>
    </header>

    <main class="brite-content">
