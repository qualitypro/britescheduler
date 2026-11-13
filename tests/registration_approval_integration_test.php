<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Registration/Policy.php';
require_once dirname(__DIR__) . '/app/Registration/ApplicationLifecycle.php';
require_once dirname(__DIR__) . '/app/Registration/ApprovalService.php';

use BriteScheduler\Registration\ApprovalService;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn(): string => gmdate('Y-m-d H:i:s'), 0);
$pdo->exec("CREATE TABLE registration_applications (
 id INTEGER PRIMARY KEY, tenant_id INTEGER, applicant_type TEXT, first_name TEXT,
 last_name TEXT, email TEXT, phone TEXT, organization_name TEXT, password_hash TEXT,
 status TEXT, email_verified_at TEXT, reviewed_by INTEGER, reviewed_at TEXT, provisioned_user_id INTEGER
)");
$pdo->exec("CREATE TABLE registration_verifications (
 application_id INTEGER, verification_type TEXT, status TEXT, expires_at TEXT
)");
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE,
 password_hash TEXT, first_name TEXT, last_name TEXT, phone TEXT, status TEXT)");
$pdo->exec("CREATE TABLE tenant_memberships (tenant_id INTEGER, user_id INTEGER, role TEXT, status TEXT)");
$pdo->exec("CREATE TABLE clients (tenant_id INTEGER, user_id INTEGER, company_name TEXT,
 first_name TEXT, last_name TEXT, email TEXT, phone TEXT, status TEXT)");
$pdo->exec("CREATE TABLE contractors (tenant_id INTEGER, user_id INTEGER, business_name TEXT,
 first_name TEXT, last_name TEXT, email TEXT, phone TEXT, status TEXT)");
$pdo->exec("CREATE TABLE registration_reviews (application_id INTEGER, reviewer_id INTEGER,
 action TEXT, reason TEXT)");
$insert = $pdo->prepare("INSERT INTO registration_applications
 (id,tenant_id,applicant_type,first_name,last_name,email,phone,password_hash,status,email_verified_at)
 VALUES (?,11,?,'Test','Applicant',?,?,?,'pending_review','2026-11-01')");
$insert->execute([1,'contractor','contractor@example.test','5551231234',password_hash('test-password-123', PASSWORD_DEFAULT)]);
$insert->execute([2,'client','client@example.test',null,password_hash('test-password-456', PASSWORD_DEFAULT)]);
$pdo->exec("INSERT INTO registration_verifications VALUES (1,'email','verified',NULL),(2,'email','verified',NULL)");
$expectDenied = static function (int $id) use ($pdo): void {
    try { ApprovalService::approve($pdo,11,99,$id); }
    catch (DomainException $expected) { return; }
    throw new RuntimeException('Unverified contractor was approved');
};
$expectDenied(1);
if ((int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() !== 0 ||
    $pdo->query("SELECT status FROM registration_applications WHERE id=1")->fetchColumn() !== 'pending_review') {
    throw new RuntimeException('Denied contractor approval changed persistent state');
}
$userId = ApprovalService::approve($pdo,11,99,2);
if ($userId < 1 || (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn() !== 1 ||
    (int)$pdo->query("SELECT COUNT(*) FROM tenant_memberships")->fetchColumn() !== 1 ||
    $pdo->query("SELECT status FROM registration_applications WHERE id=2")->fetchColumn() !== 'approved') {
    throw new RuntimeException('Approved client was not provisioned atomically');
}
echo "PASS: transactional approval blocks unverified contractor and provisions verified client\n";
