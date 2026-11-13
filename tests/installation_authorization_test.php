<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/InstallerGate.php';
require_once dirname(__DIR__) . '/app/Installation/SetupAuthorization.php';
use BriteScheduler\Installation\SetupAuthorization;
$root = sys_get_temp_dir() . '/brite-setup-auth-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) throw new RuntimeException('Cannot create test root');
$secret = str_repeat('s', 40);
try {
    putenv('BRITE_INSTALL_SETUP_TOKEN=' . $secret);
    if (SetupAuthorization::authorize($root, str_repeat('x', 40))) throw new RuntimeException('Wrong token accepted');
    if (!SetupAuthorization::authorize($root, $secret)) throw new RuntimeException('Correct token rejected');
    touch($root . '/.env');
    if (SetupAuthorization::authorize($root, $secret)) throw new RuntimeException('Existing configuration bypass');
    unlink($root . '/.env');
    session_start();
    if (SetupAuthorization::isAuthorized($root)) throw new RuntimeException('Unverified session accepted');
    if (!SetupAuthorization::grant($root, $secret)) throw new RuntimeException('Setup grant rejected');
    if (!SetupAuthorization::isAuthorized($root)) throw new RuntimeException('Authorized session rejected');
    if (SetupAuthorization::verifyCsrf(str_repeat('0', 64))) throw new RuntimeException('Invalid CSRF accepted');
    if (!SetupAuthorization::verifyCsrf(SetupAuthorization::csrfToken())) throw new RuntimeException('Valid CSRF rejected');
    $_SESSION['brite_install_authorized_until'] = time() - 1;
    if (SetupAuthorization::isAuthorized($root)) throw new RuntimeException('Expired setup session accepted');
    touch($root . '/.brite-installed.lock');
    if (SetupAuthorization::isAuthorized($root)) throw new RuntimeException('Installed lock bypass');
    echo "PASS: installer token, CSRF, expiration, and lock security checks\n";
} finally {
    @unlink($root . '/.env');
    @unlink($root . '/.brite-installed.lock');
    @rmdir($root);
    putenv('BRITE_INSTALL_SETUP_TOKEN');
}
