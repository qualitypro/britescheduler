<?php
declare(strict_types=1);

/**
 * Cycle 92G guided setup preview.
 * Deliberately read-only: installation remains CLI-only until a separately
 * audited handoff is implemented. Never store DB/owner secrets in sessions.
 */
use BriteScheduler\Installation\InstallerGate;
use BriteScheduler\Installation\SetupAuthorization;
use BriteScheduler\Installation\FreshInstallInput;

$root = __DIR__;
foreach (['InstallerGate','SetupAuthorization','FreshInstallInput'] as $name) {
    require_once $root . '/app/Installation/' . $name . '.php';
}
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$state = InstallerGate::inspect($root);
if (!$state['setup_may_begin'] || file_exists($root . '/.brite-migration-recovery-required') ||
    is_link($root . '/.brite-migration-recovery-required')) {
    http_response_code(404);
    exit('Setup is unavailable.');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}
$error = '';
$authorized = SetupAuthorization::isAuthorized($root);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !SetupAuthorization::verifyCsrf($csrf)) {
        http_response_code(403);
        $error = 'Session validation failed. Reload the page.';
    } elseif (!$authorized) {
        $token = $_POST['setup_token'] ?? '';
        if (is_string($token) && SetupAuthorization::grant($root, $token)) {
            $authorized = true;
        } else {
            http_response_code(403);
            $error = 'Authorization failed or temporarily locked.';
        }
    } else {
        // A review-only form: never write credentials to disk, session or logs.
        $config = [];
        foreach (['APP_URL','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'] as $key) {
            $config[$key] = $_POST[$key] ?? null;
        }
        $owner = [];
        foreach (['tenant_name','tenant_slug','owner_email','owner_first_name','owner_last_name','owner_password','timezone'] as $key) {
            $owner[$key] = $_POST[$key] ?? null;
        }
        try {
            // A browser cannot safely assert the target database identity.
            // This is a syntax-only review; CLI preflight rechecks DB state.
            FreshInstallInput::validate($root, $config, $owner, (string)$config['DB_NAME']);
            $error = 'Inputs passed initial format checks. Installation is not executed from this page. Use the CLI installer after independent database verification.';
        } catch (Throwable $e) {
            $error = 'Input validation failed. Review the fields and try again.';
        }
    }
}
function setupEscape(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$csrf = SetupAuthorization::csrfToken();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Brite Scheduler | Guided Setup Preview</title>
<style>
body{font:16px/1.5 system-ui,sans-serif;background:#f4f7fb;color:#172338;margin:0;padding:2rem}
main{max-width:700px;margin:auto;background:white;padding:2rem;border-radius:14px;box-shadow:0 8px 24px #17233812}
h1{margin-top:0}label{display:block;font-weight:600;margin:1rem 0 .3rem}
input{box-sizing:border-box;width:100%;padding:.7rem;border:1px solid #8593a6;border-radius:7px;font:inherit}
button{margin-top:1.5rem;padding:.8rem 1.2rem;border:0;border-radius:7px;background:#1456a0;color:white;font:inherit;cursor:pointer}
fieldset{border:1px solid #c9d2df;border-radius:9px;margin:1.5rem 0;padding:1rem}
legend{font-weight:700}.note{background:#edf3fa;padding:1rem;border-radius:7px}.feedback{margin:1rem 0;padding:1rem;border-left:4px solid #1456a0}
</style></head><body><main>
<h1>Brite Scheduler guided setup</h1>
<p class="note"><strong>Preview only.</strong> This page checks setup inputs but cannot install, migrate or create accounts. No submitted credentials are saved by this page.</p>
<?php if ($error !== ''): ?><p class="feedback" role="status"><?=setupEscape($error)?></p><?php endif; ?>
<?php if (!$authorized): ?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=setupEscape($csrf)?>">
<label for="token">Installation setup token</label><input id="token" name="setup_token" type="password" required minlength="32" maxlength="256" autocomplete="off">
<button type="submit">Authorize setup preview</button></form>
<?php else: ?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=setupEscape($csrf)?>">
<fieldset><legend>1. Database and application</legend>
<?php foreach (['APP_URL'=>'Application URL','DB_HOST'=>'Database host','DB_PORT'=>'Database port','DB_NAME'=>'New database name','DB_USER'=>'Database username','DB_PASS'=>'Database password'] as $key=>$label): ?>
<label for="<?=setupEscape($key)?>"><?=setupEscape($label)?></label>
<input id="<?=setupEscape($key)?>" name="<?=setupEscape($key)?>" type="<?= $key==='DB_PASS'?'password':'text' ?>" <?= $key==='DB_PASS'?'':'required' ?> maxlength="2048" autocomplete="off">
<?php endforeach; ?></fieldset>
<fieldset><legend>2. Business information</legend>
<?php foreach (['tenant_name'=>'Business name','tenant_slug'=>'Business slug','timezone'=>'IANA timezone (e.g. America/New_York)'] as $key=>$label): ?>
<label for="<?=setupEscape($key)?>"><?=setupEscape($label)?></label><input id="<?=setupEscape($key)?>" name="<?=setupEscape($key)?>" required autocomplete="off">
<?php endforeach; ?></fieldset>
<fieldset><legend>3. Administrator account</legend>
<?php foreach (['owner_first_name'=>'First name','owner_last_name'=>'Last name','owner_email'=>'Email','owner_password'=>'Password (12+ characters)'] as $key=>$label): ?>
<label for="<?=setupEscape($key)?>"><?=setupEscape($label)?></label>
<input id="<?=setupEscape($key)?>" name="<?=setupEscape($key)?>" type="<?= $key==='owner_password'?'password':($key==='owner_email'?'email':'text') ?>" required autocomplete="off">
<?php endforeach; ?></fieldset>
<button type="submit">4. Validate input format</button>
</form>
<?php endif; ?>
</main></body></html>
