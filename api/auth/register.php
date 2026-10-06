<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

require_method('POST');
verify_csrf();

$d = request_data();

$type = strtolower(trim((string)($d['type'] ?? '')));
$firstName = trim((string)($d['first_name'] ?? ''));
$lastName = trim((string)($d['last_name'] ?? ''));
$email = strtolower(trim((string)($d['email'] ?? '')));
$phone = trim((string)($d['phone'] ?? ''));
$company = trim((string)($d['company_name'] ?? ''));
$business = trim((string)($d['business_name'] ?? ''));
$password = (string)($d['password'] ?? '');
$confirm = (string)($d['password_confirm'] ?? '');

if (!in_array($type, ['client', 'contractor'], true)) {
    json_response(['error' => 'Invalid account type'], 422);
}

if ($firstName === '' || $lastName === '') {
    json_response(['error' => 'First and last name are required'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'A valid email address is required'], 422);
}

if (strlen($password) < 8) {
    json_response(['error' => 'Password must be at least 8 characters'], 422);
}

if ($password !== $confirm) {
    json_response(['error' => 'Passwords do not match'], 422);
}

$db = Database::connection();

/*
 * Public registration currently joins the primary active tenant.
 *
 * For the current single-business deployment this is the intended
 * behavior. Later, SaaS tenant registration can use invitations,
 * tenant slugs, or business-specific registration URLs.
 */
$tenant = $db->query(
    "SELECT id
       FROM tenants
      WHERE status='active'
      ORDER BY id
      LIMIT 1"
)->fetch();

if (!$tenant) {
    json_response(['error' => 'Registration is currently unavailable'], 503);
}

$tenantId = (int)$tenant['id'];

$q = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$q->execute([$email]);

if ($q->fetch()) {
    json_response(['error' => 'An account with this email already exists'], 409);
}

try {

    $db->beginTransaction();

    $q = $db->prepare(
        "INSERT INTO users
            (email,password_hash,first_name,last_name,phone,status)
         VALUES
            (?,?,?,?,?,'active')"
    );

    $q->execute([
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $firstName,
        $lastName,
        $phone !== '' ? $phone : null
    ]);

    $userId = (int)$db->lastInsertId();

    $q = $db->prepare(
        "INSERT INTO tenant_memberships
            (tenant_id,user_id,role,status)
         VALUES
            (?,?,?,'active')"
    );

    $q->execute([
        $tenantId,
        $userId,
        $type
    ]);

    if ($type === 'client') {

        $q = $db->prepare(
            "INSERT INTO clients
                (
                    tenant_id,
                    user_id,
                    company_name,
                    first_name,
                    last_name,
                    email,
                    phone,
                    status
                )
             VALUES
                (?,?,?,?,?,?,?,'active')"
        );

        $q->execute([
            $tenantId,
            $userId,
            $company !== '' ? $company : null,
            $firstName,
            $lastName,
            $email,
            $phone !== '' ? $phone : null
        ]);

    } else {

        $q = $db->prepare(
            "INSERT INTO contractors
                (
                    tenant_id,
                    user_id,
                    first_name,
                    last_name,
                    business_name,
                    email,
                    phone,
                    status
                )
             VALUES
                (?,?,?,?,?,?,?,'active')"
        );

        $q->execute([
            $tenantId,
            $userId,
            $firstName,
            $lastName,
            $business !== '' ? $business : null,
            $email,
            $phone !== '' ? $phone : null
        ]);
    }

    $db->commit();

    /*
     * Sign the newly registered user in automatically.
     */
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['tenant_id'] = $tenantId;
    $_SESSION['tenant_role'] = $type;

    json_response([
        'ok' => true,
        'role' => $type,
        'redirect' => Auth::homeUrl($type)
    ], 201);

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log(
        'BriteScheduler registration error: ' .
        $e->getMessage()
    );

    json_response([
        'error' => 'Unable to create account'
    ], 500);
}
