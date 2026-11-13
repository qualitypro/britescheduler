<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/Registration/VerificationTokens.php';
require_once dirname(__DIR__, 2) . '/app/Registration/EnrollmentThrottle.php';

use BriteScheduler\Registration\VerificationTokens;
use BriteScheduler\Registration\EnrollmentThrottle;

require_method('POST');
verify_csrf();
header('Cache-Control: no-store');
$d = request_data();
$type = strtolower(trim((string)($d['type'] ?? '')));
$first = trim((string)($d['first_name'] ?? ''));
$last = trim((string)($d['last_name'] ?? ''));
$email = strtolower(trim((string)($d['email'] ?? '')));
$phone = trim((string)($d['phone'] ?? ''));
$company = trim((string)($type === 'contractor' ? ($d['business_name'] ?? '') : ($d['company_name'] ?? '')));
$password = (string)($d['password'] ?? '');
$confirmation = (string)($d['password_confirm'] ?? '');
$consent = $d['terms_accepted'] ?? false;
$tenantId = filter_var($d['tenant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!in_array($type, ['client', 'contractor'], true) ||
    !$tenantId || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 190 || mb_strlen($first) < 1 || mb_strlen($first) > 100 ||
    mb_strlen($last) < 1 || mb_strlen($last) > 100 ||
    mb_strlen($phone) > 40 || mb_strlen($company) > 190 ||
    strlen($password) < 12 || strlen($password) > 1024 ||
    !hash_equals($password, $confirmation) || $consent !== true) {
    json_response(['error' => 'Check account type, business, name, email, password (12+ characters), and required consent'], 422);
}
if ($type === 'contractor' && $phone === '') {
    json_response(['error' => 'Contractor phone number is required'], 422);
}
$db = Database::connection();
try {
    EnrollmentThrottle::check($db, (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $email);
} catch (\DomainException $exception) {
    json_response(['error' => $exception->getMessage()], 429);
} catch (\Throwable $exception) {
    error_log('Enrollment throttle unavailable: ' . get_class($exception));
    json_response(['error' => 'Enrollment temporarily unavailable'], 503);
}
$q = $db->prepare("SELECT id FROM tenants WHERE id = ? AND status = 'active'");
$q->execute([$tenantId]);
if (!$q->fetchColumn()) {
    json_response(['error' => 'Business is not accepting enrollment'], 422);
}
$q = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$q->execute([$email]);
if ($q->fetchColumn()) {
    json_response(['error' => 'Unable to submit enrollment. Contact the business if you already have an account.'], 409);
}
$q = $db->prepare("SELECT id FROM registration_applications WHERE tenant_id = ? AND email = ? AND applicant_type = ? AND status NOT IN ('rejected','withdrawn') LIMIT 1");
$q->execute([$tenantId, $email, $type]);
if ($q->fetchColumn()) {
    json_response(['error' => 'An enrollment request is already pending for this email'], 409);
}
$token = VerificationTokens::generate();
$hash = VerificationTokens::hash($token);
$from = getenv('ENROLLMENT_MAIL_FROM') ?: '';
if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Enrollment email delivery is not configured'], 503);
}
try {
    $db->beginTransaction();
    $q = $db->prepare("INSERT INTO registration_applications
        (tenant_id, applicant_type, first_name, last_name, email, phone, organization_name, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_email')");
    $q->execute([$tenantId, $type, $first, $last, $email, $phone ?: null, $company ?: null, password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int)$db->lastInsertId();
    $q = $db->prepare("INSERT INTO registration_tokens (application_id, token_hash, purpose, expires_at)
        VALUES (?, ?, 'email_verify', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR))");
    $q->execute([$id, $hash]);
    $q = $db->prepare("INSERT INTO registration_reviews (application_id, action) VALUES (?, 'submitted')");
    $q->execute([$id]);
    // A token is only useful when email transport accepts it; never return it via API.
    $link = app_url('/verify-enrollment.php');
    $subject = 'Verify your Brite Scheduler enrollment';
    $body = "Your Brite Scheduler enrollment verification token is:\n\n" . $token .
        "\n\nOpen " . $link . " and enter the token within 24 hours.\n" .
        "If you did not request enrollment, ignore this email.\n";
    $headers = ['From' => $from, 'Content-Type' => 'text/plain; charset=UTF-8'];
    if (!mail($email, $subject, $body, $headers)) {
        throw new RuntimeException('Email transport refused enrollment verification');
    }
    $db->commit();
    json_response(['ok' => true, 'pending_verification' => true, 'message' => 'Check your email for a verification token. Your account is not active yet.'], 202);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Enrollment submission failed: ' . get_class($exception));
    json_response(['error' => 'Unable to submit enrollment right now'], 503);
}
