<?php
declare(strict_types=1);

// Deliberately read-only until a guarded installation state machine is implemented.
// Do not include app/bootstrap.php: it opens sessions and assumes installed config.
$root = dirname(__DIR__);
require_once $root . '/app/Installation/RequirementsChecker.php';
require_once $root . '/app/Installation/MigrationInventory.php';
require_once $root . '/app/Installation/InstallerGate.php';
require_once $root . '/app/Installation/SetupAuthorization.php';
use BriteScheduler\Installation\RequirementsChecker;
use BriteScheduler\Installation\MigrationInventory;
use BriteScheduler\Installation\InstallerGate;
use BriteScheduler\Installation\SetupAuthorization;

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
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!SetupAuthorization::verifyCsrf((string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        $notice = 'Invalid setup request.';
    } elseif (!SetupAuthorization::grant($root, (string)($_POST['setup_token'] ?? ''))) {
        http_response_code(403);
        $notice = 'Setup authorization failed or installation is already configured.';
    } else {
        $notice = 'Setup access granted for 15 minutes. Installation writes are not yet enabled.';
    }
}
$authorized = SetupAuthorization::isAuthorized($root);
$ready = !in_array(false, array_column($checks, 'ok'), true);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Brite Scheduler — Installation diagnostics</title>
<style>body{font:16px system-ui,sans-serif;max-width:760px;margin:5vh auto;padding:0 20px;background:#f8fafc;color:#172554}main{background:white;padding:28px;border-radius:16px;box-shadow:0 8px 30px #0001}li{padding:10px;border-bottom:1px solid #e2e8f0}.pass{color:#166534}.fail{color:#991b1b}small{color:#475569}</style></head>
<body><main><h1>Brite Scheduler installation</h1>
<p>Pre-installation diagnostics only. This page cannot change your database, write configuration, or create accounts.</p>
<h2>Installation access</h2>
<p><?= $gate['installed_lock'] ? 'Installation is locked.' : ($gate['existing_configuration'] ? 'Existing configuration detected; setup writes are disabled.' : ($gate['setup_token_configured'] ? 'A setup token is configured. Installer execution is not yet available.' : 'A private setup authorization token must be configured before installation can begin.')) ?></p>
<?php if ($notice !== ''): ?><p role="status"><?= $escape($notice) ?></p><?php endif; ?>
<?php if ($gate['setup_may_begin'] && !$authorized): ?>
<form method="post" autocomplete="off">
<input type="hidden" name="_csrf" value="<?= $escape(SetupAuthorization::csrfToken()) ?>">
<label for="setup_token">Private setup authorization token</label>
<input id="setup_token" name="setup_token" type="password" minlength="32" maxlength="256" required autocomplete="off">
<button type="submit">Authorize setup</button>
</form>
<?php elseif ($authorized): ?><p role="status">Authorized setup session active. Database and configuration writes remain disabled.</p><?php endif; ?>
<h2>System requirements</h2><ul>
<?php foreach ($checks as $name => $check): ?>
<li><strong class="<?= $check['ok'] ? 'pass' : 'fail' ?>"><?= $check['ok'] ? 'PASS' : 'CHECK' ?></strong>
<?= $escape($check['detail']) ?></li>
<?php endforeach; ?></ul>
<p><?= $ready ? 'Basic checks passed. Additional security checks and the installation wizard are still required.' : 'Some checks require attention before installation.' ?></p>
<p><small>Migration files discovered: <?= count(MigrationInventory::list($root)) ?>. No migrations have been executed.</small></p>
<p><small>Restrict access to /install at the web server. This page does not establish installation authorization.</small></p>
</main></body></html>
