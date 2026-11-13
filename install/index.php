<?php
declare(strict_types=1);

// Deliberately read-only until a guarded installation state machine is implemented.
// Do not include app/bootstrap.php: it opens sessions and assumes installed config.
$root = dirname(__DIR__);
require_once $root . '/app/Installation/RequirementsChecker.php';
require_once $root . '/app/Installation/MigrationInventory.php';
require_once $root . '/app/Installation/InstallerGate.php';
require_once $root . '/app/Installation/SetupAuthorization.php';
foreach (['SetupRateLimiter','InstallationMutex','ConfigurationWriter','InstallationStatus','MigrationPlan','MigrationPreflight','SqlStatements','MigrationRunner','FirstRunInstaller','BrowserInstaller'] as $component) {
    require_once $root . '/app/Installation/' . $component . '.php';
}
use BriteScheduler\Installation\RequirementsChecker;
use BriteScheduler\Installation\MigrationInventory;
use BriteScheduler\Installation\InstallerGate;
use BriteScheduler\Installation\SetupAuthorization;
use BriteScheduler\Installation\SetupRateLimiter;
use BriteScheduler\Installation\BrowserInstaller;

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (!$https) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Installer access requires a direct HTTPS connection.');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BRITE_INSTALL');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/install','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
$checks = RequirementsChecker::inspect($root);
$gate = InstallerGate::inspect($root);
$notice = '';
$completed = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!SetupAuthorization::verifyCsrf((string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        $notice = 'Invalid setup request.';
    } elseif (($_POST['action'] ?? '') === 'authorize') {
        try {
            $address = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            if (!SetupRateLimiter::attempt($root, $address, false) ||
                !SetupAuthorization::grant($root, (string)($_POST['setup_token'] ?? ''))) {
                http_response_code(403);
                $notice = 'Setup authorization denied or temporarily rate-limited.';
            } else {
                SetupRateLimiter::attempt($root, $address, true);
                $notice = 'Setup access granted for 15 minutes.';
            }
        } catch (Throwable $error) {
            http_response_code(503);
            $notice = 'Setup protection unavailable. Contact the server administrator.';
        }
    } elseif (($_POST['action'] ?? '') === 'complete' && SetupAuthorization::isAuthorized($root)) {
        try {
            $config = [];
            foreach (['APP_URL','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'] as $key) {
                $config[$key] = (string)($_POST[$key] ?? '');
            }
            $owner = [];
            foreach (['email','first_name','last_name','password','tenant_name','tenant_slug'] as $key) {
                $owner[$key] = (string)($_POST[$key] ?? '');
            }
            BrowserInstaller::complete($root, $config, $owner);
            $completed = true;
            $notice = 'Installation complete. The setup wizard is now locked.';
        } catch (Throwable $error) {
            http_response_code(500);
            $notice = 'Installation stopped. Do not retry in the browser. A trusted administrator must inspect configuration, database and recovery state.';
        }
    } else {
        http_response_code(403);
        $notice = 'Setup request not authorized.';
    }
}
$gate = InstallerGate::inspect($root);
$authorized = SetupAuthorization::isAuthorized($root);
$ready = !in_array(false, array_column($checks, 'ok'), true);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Brite Scheduler — Installation diagnostics</title>
<style>body{font:16px system-ui,sans-serif;max-width:760px;margin:5vh auto;padding:0 20px;background:#f8fafc;color:#172554}main{background:white;padding:28px;border-radius:16px;box-shadow:0 8px 30px #0001}label{display:block;margin:12px 0}input:not([type=checkbox]):not([type=hidden]){display:block;box-sizing:border-box;width:100%;padding:10px;margin-top:5px;border:1px solid #94a3b8;border-radius:6px}button{padding:10px 18px;cursor:pointer}li{padding:10px;border-bottom:1px solid #e2e8f0}.pass{color:#166534}.fail{color:#991b1b}small{color:#475569}</style></head>
<body><main><h1>Brite Scheduler installation</h1>
<p>New installations only. Review database backups, configure a dedicated empty MariaDB database, and restrict installer access to trusted administrators.</p>
<h2>Installation access</h2>
<p><?= $gate['installed_lock'] ? 'Installation is locked.' : ($gate['existing_configuration'] ? 'Existing configuration detected; setup writes are disabled.' : ($gate['setup_token_configured'] ? 'A setup token is configured. Installer execution is not yet available.' : 'A private setup authorization token must be configured before installation can begin.')) ?></p>
<?php if ($notice !== ''): ?><p role="status"><?= $escape($notice) ?></p><?php endif; ?>
<?php if ($gate['setup_may_begin'] && !$authorized): ?>
<form method="post" autocomplete="off">
<input type="hidden" name="_csrf" value="<?= $escape(SetupAuthorization::csrfToken()) ?>">
<input type="hidden" name="action" value="authorize">
<label for="setup_token">Private setup authorization token</label>
<input id="setup_token" name="setup_token" type="password" minlength="32" maxlength="256" required autocomplete="off">
<button type="submit">Authorize setup</button>
</form>
<?php elseif ($authorized): ?>
<p role="status">Authorized setup session active. Complete all settings in this single request. Failed installation may require CLI recovery.</p>
<form method="post" autocomplete="off">
<input type="hidden" name="_csrf" value="<?= $escape(SetupAuthorization::csrfToken()) ?>">
<input type="hidden" name="action" value="complete">
<h3>Application and database</h3>
<label>Application URL <input name="APP_URL" type="url" placeholder="https://scheduler.example.com" required></label>
<label>Database host <input name="DB_HOST" value="127.0.0.1" required></label>
<label>Database port <input name="DB_PORT" value="3306" required></label>
<label>Empty database name <input name="DB_NAME" required></label>
<label>Database username <input name="DB_USER" required></label>
<label>Database password <input name="DB_PASS" type="password" required autocomplete="new-password"></label>
<h3>First business and owner</h3>
<label>Business name <input name="tenant_name" required maxlength="150"></label>
<label>Business slug <input name="tenant_slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*"></label>
<label>Owner first name <input name="first_name" required maxlength="100"></label>
<label>Owner last name <input name="last_name" required maxlength="100"></label>
<label>Owner email <input name="email" type="email" required></label>
<label>Owner password <input name="password" type="password" minlength="12" required autocomplete="new-password"></label>
<label><input type="checkbox" required> I confirm the database is dedicated, empty and backed up as appropriate.</label>
<button type="submit">Install Brite Scheduler</button>
</form>
<?php endif; ?>
<h2>System requirements</h2><ul>
<?php foreach ($checks as $name => $check): ?>
<li><strong class="<?= $check['ok'] ? 'pass' : 'fail' ?>"><?= $check['ok'] ? 'PASS' : 'CHECK' ?></strong>
<?= $escape($check['detail']) ?></li>
<?php endforeach; ?></ul>
<p><?= $ready ? 'Basic requirements inspected. Review server security before installation.' : 'Some checks require attention before installation.' ?></p>
<p><small>Migration files discovered: <?= count(MigrationInventory::list($root)) ?>. Migration execution is available only through an explicitly authorized setup operation.</small></p>
<p><small>Restrict access to /install at the web server. Only use this page over direct HTTPS with server-side access restrictions.</small></p>
</main></body></html>
