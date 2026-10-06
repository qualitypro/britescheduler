<?php require_once __DIR__.'/app/bootstrap.php';$csrf=csrf_token();?><!doctype html><html><head><meta charset="utf-8"><title>BriteScheduler Sign In</title><style>body{font-family:system-ui;background:#f5f7fb}.box{max-width:360px;margin:10vh auto;background:white;padding:30px;border-radius:12px}input,button{box-sizing:border-box;width:100%;padding:11px;margin:7px 0}</style></head><body><div class="box"><h1>BriteScheduler</h1><form id="f"><input name="email" type="email" placeholder="Email" required><input name="password" type="password" placeholder="Password" required><button>Sign in</button><p id="e"></p></form>

<div style="
    margin-top:24px;
    padding-top:20px;
    border-top:1px solid #e5e7eb;
    text-align:center;
">

    <div style="
        margin-bottom:12px;
        color:#64748b;
    ">
        Don't have an account?
    </div>

    <div style="
        display:flex;
        gap:10px;
        flex-wrap:wrap;
    ">

        <a
            href="<?=htmlspecialchars(app_url('/sign-up.v2.php?type=client'))?>"
            style="
                flex:1;
                min-width:150px;
                box-sizing:border-box;
                padding:10px 12px;
                border:1px solid #cbd5e1;
                border-radius:7px;
                text-decoration:none;
                color:#334155;
                font-weight:600;
            ">
            Sign Up as Client
        </a>

        <a
            href="<?=htmlspecialchars(app_url('/sign-up.v2.php?type=contractor'))?>"
            style="
                flex:1;
                min-width:150px;
                box-sizing:border-box;
                padding:10px 12px;
                border:1px solid #cbd5e1;
                border-radius:7px;
                text-decoration:none;
                color:#334155;
                font-weight:600;
            ">
            Sign Up as Contractor
        </a>

    </div>

</div>
</div><script>f.onsubmit=async x=>{x.preventDefault();let d=Object.fromEntries(new FormData(f));let r=await fetch(<?=json_encode(app_url('/api/auth/login.php'))?>,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':<?=json_encode($csrf)?>},body:JSON.stringify(d)}),j=await r.json();if(r.ok)location=j.redirect;else e.textContent=j.error}</script></body></html>