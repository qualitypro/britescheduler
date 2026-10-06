<?php

require_once __DIR__.'/app/bootstrap.php';

if (Auth::user()) {
    header('Location: '.app_url('/dashboard.v2.php'));
    exit;
}

$csrf = csrf_token();

$type = strtolower(trim($_GET['type'] ?? 'client'));

if (!in_array($type, ['client','contractor'], true)) {
    $type = 'client';
}

$isContractor = $type === 'contractor';

?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Create Account - BriteScheduler</title>

<style>
body{
    margin:0;
    font-family:system-ui,sans-serif;
    background:#f5f7fb;
    color:#1f2937;
}
.auth-wrap{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:30px 18px;
}
.auth-card{
    width:100%;
    max-width:520px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:16px;
    padding:30px;
    box-shadow:0 8px 30px #00000012;
}
h1{
    margin:0 0 6px;
}
.subtitle{
    color:#64748b;
    margin-bottom:24px;
}
.row{
    display:flex;
    gap:12px;
}
.field{
    flex:1;
    margin-bottom:15px;
}
label{
    display:block;
    font-weight:600;
    margin-bottom:6px;
}
input{
    width:100%;
    box-sizing:border-box;
    padding:11px;
    border:1px solid #cbd5e1;
    border-radius:7px;
}
button{
    width:100%;
    padding:12px;
    border:0;
    border-radius:7px;
    background:#172033;
    color:white;
    font-weight:700;
    cursor:pointer;
}
.error{
    display:none;
    background:#fee2e2;
    color:#991b1b;
    padding:10px;
    border-radius:7px;
    margin-bottom:15px;
}
.links{
    text-align:center;
    margin-top:20px;
}
.links a{
    color:#334155;
}
.account-type{
    display:flex;
    gap:8px;
    margin-bottom:22px;
}
.account-type a{
    flex:1;
    text-align:center;
    padding:10px;
    border:1px solid #cbd5e1;
    border-radius:7px;
    text-decoration:none;
    color:#334155;
}
.account-type a.active{
    background:#172033;
    color:#fff;
    border-color:#172033;
}
@media(max-width:600px){
    .row{display:block}
}
</style>
</head>

<body>

<div class="auth-wrap">

<div class="auth-card">

<h1>Create Account</h1>

<div class="subtitle">
    Sign up as a <?= $isContractor ? 'contractor' : 'client' ?>.
</div>

<div class="account-type">

<a
 href="<?=htmlspecialchars(app_url('/sign-up.v2.php?type=client'))?>"
 class="<?=$type==='client'?'active':''?>">
Client
</a>

<a
 href="<?=htmlspecialchars(app_url('/sign-up.v2.php?type=contractor'))?>"
 class="<?=$type==='contractor'?'active':''?>">
Contractor
</a>

</div>

<div id="error" class="error"></div>

<form id="signupForm">

<input type="hidden"
       id="type"
       value="<?=htmlspecialchars($type)?>">

<div class="row">

<div class="field">
<label>First Name</label>
<input id="firstName"
       autocomplete="given-name"
       required>
</div>

<div class="field">
<label>Last Name</label>
<input id="lastName"
       autocomplete="family-name"
       required>
</div>

</div>

<?php if($isContractor): ?>

<div class="field">
<label>Business Name</label>
<input id="businessName"
       autocomplete="organization">
</div>

<?php else: ?>

<div class="field">
<label>Company</label>
<input id="companyName"
       autocomplete="organization">
</div>

<?php endif; ?>

<div class="field">
<label>Email</label>
<input type="email"
       id="email"
       autocomplete="email"
       required>
</div>

<div class="field">
<label>Phone</label>
<input type="tel"
       id="phone"
       autocomplete="tel">
</div>

<div class="field">
<label>Password</label>
<input type="password"
       id="password"
       autocomplete="new-password"
       minlength="8"
       required>
</div>

<div class="field">
<label>Confirm Password</label>
<input type="password"
       id="passwordConfirm"
       autocomplete="new-password"
       minlength="8"
       required>
</div>

<button type="submit">
Create <?= $isContractor ? 'Contractor' : 'Client' ?> Account
</button>

</form>

<div class="links">
Already have an account?
<a href="<?=htmlspecialchars(app_url('/sign-in.v2.php'))?>">
Sign In
</a>
</div>

</div>
</div>

<script>

const BASE =
    <?=json_encode(rtrim($_ENV['APP_URL'] ?? 'http://localhost/dashboard','/'))?>;

const CSRF =
    <?=json_encode($csrf)?>;

const form =
    document.getElementById('signupForm');

const errorBox =
    document.getElementById('error');

form.addEventListener('submit', async e=>{

    e.preventDefault();

    errorBox.style.display='none';

    const payload={
        type:document.getElementById('type').value,
        first_name:document.getElementById('firstName').value.trim(),
        last_name:document.getElementById('lastName').value.trim(),
        email:document.getElementById('email').value.trim(),
        phone:document.getElementById('phone').value.trim(),
        password:document.getElementById('password').value,
        password_confirm:document.getElementById('passwordConfirm').value,
        company_name:
            document.getElementById('companyName')?.value.trim() || '',
        business_name:
            document.getElementById('businessName')?.value.trim() || ''
    };

    try{

        const r=await fetch(
            BASE+'/api/auth/register.php',
            {
                method:'POST',
                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-Token':CSRF
                },
                body:JSON.stringify(payload)
            }
        );

        const j=await r.json();

        if(!r.ok){
            throw new Error(j.error || 'Registration failed');
        }

        location.href=j.redirect;

    }catch(err){

        errorBox.textContent=err.message;
        errorBox.style.display='block';
    }

});

</script>

</body>
</html>
