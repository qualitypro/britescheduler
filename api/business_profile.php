<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();

$tid = Auth::tenantId();
$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
|
| This endpoint exposes business configuration, including invoice footer
| and payment instructions. It is restricted to business administrators.
| Invoice presentation retrieves its required business data separately
| through the authorized invoice workflow.
|
*/

Auth::requireRole(
    'owner',
    'admin'
);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $q=$pdo->prepare("
        SELECT
            id,
            name,
            slug,
            timezone,
            currency,
            business_email,
            business_phone,
            website,
            address1,
            address2,
            city,
            state,
            postal_code,
            invoice_footer,
            payment_instructions
        FROM tenants
        WHERE id=?
        LIMIT 1
    ");

    $q->execute([$tid]);

    $tenant=$q->fetch();

    if (!$tenant) {
        json_response([
            'error'=>'Tenant not found'
        ],404);
    }

    json_response([
        'business'=>$tenant
    ]);
}

verify_csrf();

$d=request_data();

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

    $name=trim((string)($d['name'] ?? ''));

    if ($name==='') {
        json_response([
            'error'=>'Business name is required'
        ],422);
    }

    $currency=strtoupper(
        trim((string)($d['currency'] ?? 'USD'))
    );

    if (!preg_match('/^[A-Z]{3}$/',$currency)) {
        json_response([
            'error'=>'Currency must be a 3-letter code'
        ],422);
    }

    $timezone=trim(
        (string)($d['timezone'] ?? 'America/New_York')
    );

    try {
        new DateTimeZone($timezone);
    } catch(Throwable $e) {
        json_response([
            'error'=>'Invalid timezone'
        ],422);
    }

    $email=trim(
        (string)($d['business_email'] ?? '')
    );

    if (
        $email !== '' &&
        !filter_var($email,FILTER_VALIDATE_EMAIL)
    ) {
        json_response([
            'error'=>'Invalid business email'
        ],422);
    }

    $website=trim(
        (string)($d['website'] ?? '')
    );

    if (
        $website !== '' &&
        !preg_match(
            '~^https?://~i',
            $website
        )
    ) {
        $website='https://'.$website;
    }

    if (
        $website !== '' &&
        !filter_var($website,FILTER_VALIDATE_URL)
    ) {
        json_response([
            'error'=>'Invalid website'
        ],422);
    }

    $nullable=function($value) {
        $value=trim((string)$value);
        return $value==='' ? null : $value;
    };

    $q=$pdo->prepare("
        UPDATE tenants
        SET
            name=?,
            timezone=?,
            currency=?,
            business_email=?,
            business_phone=?,
            website=?,
            address1=?,
            address2=?,
            city=?,
            state=?,
            postal_code=?,
            invoice_footer=?,
            payment_instructions=?
        WHERE id=?
    ");

    $q->execute([
        $name,
        $timezone,
        $currency,
        $nullable($email),
        $nullable($d['business_phone'] ?? ''),
        $nullable($website),
        $nullable($d['address1'] ?? ''),
        $nullable($d['address2'] ?? ''),
        $nullable($d['city'] ?? ''),
        $nullable($d['state'] ?? ''),
        $nullable($d['postal_code'] ?? ''),
        $nullable($d['invoice_footer'] ?? ''),
        $nullable($d['payment_instructions'] ?? ''),
        $tid
    ]);

    json_response([
        'ok'=>true
    ]);
}

json_response([
    'error'=>'Method not allowed'
],405);
