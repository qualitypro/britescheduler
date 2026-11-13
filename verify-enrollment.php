<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Registration/Policy.php';
require_once __DIR__ . '/app/Registration/ApplicationLifecycle.php';
require_once __DIR__ . '/app/Registration/VerificationTokens.php';
require_once __DIR__ . '/app/Registration/EmailVerificationService.php';

use BriteScheduler\Registration\EmailVerificationService;

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

if (session_status() !== PHP_SESSION_ACTIVE) {
    http_response_code(500);
    exit('Session unavailable');
}
if (!isset($_SESSION['enrollment_verify_csrf'])) {
    $_SESSION['enrollment_verify_csrf'] = bin2hex(random_bytes(32));
}
$message = 'Enter the verification link supplied to your email to continue.';
$success = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['token'] ?? '';
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($token) || !is_string($csrf) ||
        !hash_equals($_SESSION['enrollment_verify_csrf'], $csrf)) {
        http_response_code(400);
        $message = 'Unable to verify this request.';
    } else {
        try {
            EmailVerificationService::consume(Database::connection(), $token);
            $message = 'Email verified. Your enrollment is now awaiting review.';
            $success = true;
        } catch (\Throwable $exception) {
            http_response_code(400);
            $message = 'The verification link is invalid or expired. Please request a new link.';
        }
    }
}
$escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enrollment verification</title>
<style>body{font:16px system-ui,sans-serif;max-width:520px;margin:10vh auto;padding:24px;color:#172554;background:#f8fafc}main{background:#fff;padding:28px;border-radius:14px}input,button{font:inherit;padding:10px;width:100%;box-sizing:border-box;margin-top:12px}</style></head>
<body><main><h1>Verify your enrollment</h1><p role="status"><?= $escape($message) ?></p>
<?php if (!$success): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['enrollment_verify_csrf']) ?>">
<label for="token">Verification token</label><input id="token" name="token" type="text" minlength="64" maxlength="64" pattern="[a-f0-9]{64}" autocomplete="off" required>
<button type="submit">Verify email</button></form>
<?php endif; ?></main></body></html>
