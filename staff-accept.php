<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
$token=(string)($_GET['token']??'');
if(!preg_match('/^[a-f0-9]{64}$/D',$token)){http_response_code(400);exit('Invalid invitation link');}
$pdo=Database::connection();
$q=$pdo->prepare("SELECT i.email,i.first_name,i.role,i.expires_at,t.name FROM staff_invitations i JOIN tenants t ON t.id=i.tenant_id WHERE i.token_hash=? AND i.status='pending' AND i.expires_at>UTC_TIMESTAMP() AND t.status='active' LIMIT 1");
$q->execute([hash('sha256',$token)]);$inv=$q->fetch(PDO::FETCH_ASSOC);
if(!$inv){http_response_code(410);exit('Invitation expired, revoked or already accepted');}
$q=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$q->execute([$inv['email']]);
$existing=(bool)$q->fetchColumn();
$session=Auth::user();
$ready=$existing && $session && strcasecmp((string)$session['email'],(string)$inv['email'])===0;
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Accept staff invitation</title><style>body{font:16px system-ui;background:#f5f7fb;padding:3rem 1rem}.card{max-width:440px;margin:auto;padding:2rem;border-radius:12px;background:white}input,button{display:block;width:100%;box-sizing:border-box;padding:12px;margin:12px 0}</style></head><body><main class="card"><h1>Staff invitation</h1>
<p>You've been invited to join <strong><?=htmlspecialchars($inv['name'],ENT_QUOTES,'UTF-8')?></strong> as <?=htmlspecialchars($inv['role'],ENT_QUOTES,'UTF-8')?>.</p>
<p>Invitation for <?=htmlspecialchars($inv['email'],ENT_QUOTES,'UTF-8')?>.</p>
<?php if($existing&&!$ready): ?>
<p>This email already has an account. <a href="<?=htmlspecialchars(app_url('/sign-in.v2.php'),ENT_QUOTES,'UTF-8')?>">Sign in</a> with that account, then reopen this invitation link.</p>
<?php else: ?>
<form id="acceptForm">
<?php if(!$existing): ?><label>Create a password (12 characters minimum)<input type="password" name="password" minlength="12" autocomplete="new-password" required></label><?php endif; ?>
<button type="submit">Accept invitation</button><p id="feedback" role="status"></p>
</form>
<?php endif; ?>
</main><script>
const form=document.getElementById('acceptForm');
if(form)form.addEventListener('submit',async event=>{
 event.preventDefault();const button=form.querySelector('button');button.disabled=true;
 try{
  const payload={token:<?=json_encode($token)?>,...Object.fromEntries(new FormData(form))};
  const r=await fetch(<?=json_encode(app_url('/api/staff_invitation_accept.php'))?>,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':<?=json_encode(csrf_token())?>},body:JSON.stringify(payload)});
  const data=await r.json();if(!r.ok)throw Error(data.error||'Unable to accept invitation');
  form.replaceChildren();const p=document.createElement('p');p.textContent=data.message;form.append(p);
  const a=document.createElement('a');a.href=<?=json_encode(app_url('/sign-in.v2.php'))?>;a.textContent='Sign in';form.append(a);
 }catch(e){document.getElementById('feedback').textContent=e.message;button.disabled=false;}
});
</script></body></html>
