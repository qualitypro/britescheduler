<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

$u      = Auth::requireUser();
$tenant = Auth::tenant();

$tid  = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];

$pdo = Database::connection();


/*
|--------------------------------------------------------------------------
| Resolve client identity for client portal
|--------------------------------------------------------------------------
*/

$authenticatedClientId=null;

if ($role === 'client') {

    $cq=$pdo->prepare("
        SELECT id
        FROM clients
        WHERE tenant_id=?
          AND user_id=?
          AND status='active'
        LIMIT 1
    ");

    $cq->execute([
        $tid,
        (int)$u['id']
    ]);

    $authenticatedClientId=
        (int)($cq->fetchColumn() ?: 0);

    if ($authenticatedClientId <= 0) {
        json_response([
            'error'=>'Client profile not found'
        ],403);
    }
}


/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| GET — Single invoice detail
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['id'])
) {

    $invoiceId=(int)$_GET['id'];

    if ($invoiceId <= 0) {
        json_response([
            'error'=>'Invalid invoice'
        ],422);
    }

    $sql="
        SELECT
            i.*,

            CONCAT(
                c.first_name,
                ' ',
                c.last_name
            ) AS client_name,

            c.company_name,
            c.email AS client_email,
            c.phone AS client_phone,
            c.address1,
            c.address2,
            c.city,
            c.state,
            c.postal_code,

            a.title AS appointment_title,
            a.starts_at AS appointment_starts_at,
            a.ends_at AS appointment_ends_at,

            t.name AS tenant_name,
            t.currency AS tenant_currency,

            t.business_email AS tenant_email,
            t.business_phone AS tenant_phone,
            t.website AS tenant_website,

            t.address1 AS tenant_address1,
            t.address2 AS tenant_address2,
            t.city AS tenant_city,
            t.state AS tenant_state,
            t.postal_code AS tenant_postal_code,

            t.invoice_footer,
            t.payment_instructions,

            COALESCE((
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.invoice_id=i.id
                  AND p.tenant_id=i.tenant_id
                  AND p.status='succeeded'
            ),0) AS amount_paid

        FROM invoices i

        JOIN clients c
          ON c.id=i.client_id
         AND c.tenant_id=i.tenant_id

        JOIN tenants t
          ON t.id=i.tenant_id

        LEFT JOIN appointments a
          ON a.id=i.appointment_id
         AND a.tenant_id=i.tenant_id

        WHERE i.id=?
          AND i.tenant_id=?
    ";

    $invoiceParams=[
        $invoiceId,
        $tid
    ];

    /*
     * Clients may only retrieve invoices
     * belonging to their linked client record.
     */
    if ($role === 'client') {

        $sql .= "
          AND i.client_id=?
        ";

        $invoiceParams[]=
            $authenticatedClientId;
    }

    /*
     * Contractors have no invoice access.
     */
    elseif ($role === 'contractor') {

        json_response([
            'error'=>'Forbidden'
        ],403);
    }

    elseif (
        !in_array(
            $role,
            [
                'owner',
                'admin',
                'accounting'
            ],
            true
        )
    ) {

        json_response([
            'error'=>'Forbidden'
        ],403);
    }

    $sql .= " LIMIT 1";

    $q=$pdo->prepare($sql);

    $q->execute($invoiceParams);

    $invoice=$q->fetch();

    if (!$invoice) {
        json_response([
            'error'=>'Invoice not found'
        ],404);
    }

    /*
     * Overdue is derived for display.
     * Paid, partial and void states remain authoritative.
     */
    if (
        in_array(
            $invoice['status'],
            ['draft','sent','overdue'],
            true
        ) &&
        (float)$invoice['balance_due'] > 0 &&
        !empty($invoice['due_at']) &&
        strtotime($invoice['due_at']) < time()
    ) {
        $invoice['status']='overdue';
    }

    $items=$pdo->prepare("
        SELECT
            id,
            description,
            quantity,
            unit_price,
            amount
        FROM invoice_items
        WHERE invoice_id=?
        ORDER BY id
    ");

    $items->execute([
        $invoiceId
    ]);

    $payments=$pdo->prepare("
        SELECT
            id,
            amount,
            currency,
            status,
            method,
            provider,
            provider_reference,
            paid_at,
            notes,
            created_at
        FROM payments
        WHERE tenant_id=?
          AND invoice_id=?
        ORDER BY
            COALESCE(paid_at,created_at) DESC,
            id DESC
    ");

    $payments->execute([
        $tid,
        $invoiceId
    ]);

    json_response([
        'invoice'=>$invoice,
        'items'=>$items->fetchAll(),
        'payments'=>$payments->fetchAll()
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    /*
     * Contractors do not have billing access.
     */
    if ($role === 'contractor') {

        json_response([
            'error'=>'Forbidden'
        ],403);
    }


    if (
        $role !== 'client' &&
        !in_array(
            $role,
            [
                'owner',
                'admin',
                'accounting'
            ],
            true
        )
    ) {

        json_response([
            'error'=>'Forbidden'
        ],403);
    }


    $where="i.tenant_id=?";

    $params=[$tid];


    /*
     * Client invoice list is locked to
     * authenticated clients.id.
     */
    if ($role === 'client') {

        $where .= "
            AND i.client_id=?
        ";

        $params[]=
            $authenticatedClientId;
    }


    $q=$pdo->prepare("
        SELECT
            i.*,

            CONCAT(
                c.first_name,
                ' ',
                c.last_name
            ) AS client_name,

            a.title AS appointment_title,

            a.starts_at
                AS appointment_starts_at,

            COALESCE((
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.invoice_id=i.id
                  AND p.tenant_id=i.tenant_id
                  AND p.status='succeeded'
            ),0) AS amount_paid

        FROM invoices i

        JOIN clients c
          ON c.id=i.client_id
         AND c.tenant_id=i.tenant_id

        LEFT JOIN appointments a
          ON a.id=i.appointment_id
         AND a.tenant_id=i.tenant_id

        WHERE {$where}

        ORDER BY i.created_at DESC
    ");

    $q->execute($params);

    json_response([
        'invoices'=>$q->fetchAll()
    ]);
}

/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();

Auth::requireRole(
    'owner',
    'admin',
    'accounting'
);

$d = request_data();

/*
|--------------------------------------------------------------------------
| POST — Generate invoice from appointment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $appointmentId =
        (int)($d['appointment_id'] ?? 0);

    if ($appointmentId <= 0) {
        json_response([
            'error'=>'Appointment required'
        ],422);
    }

    try {

        /*
         * Fetch everything needed from trusted database data.
         * We do NOT trust the browser for service price,
         * client ownership, or appointment status.
         */
        $q = $pdo->prepare("
            SELECT
                a.id,
                a.client_id,
                a.service_id,
                a.title,
                a.starts_at,
                a.ends_at,
                a.status,
                s.name AS service_name,
                s.price AS service_price
            FROM appointments a

            LEFT JOIN services s
              ON s.id=a.service_id
             AND s.tenant_id=a.tenant_id

            WHERE a.id=?
              AND a.tenant_id=?
            LIMIT 1
        ");

        $q->execute([
            $appointmentId,
            $tid
        ]);

        $appointment=$q->fetch();

        if (!$appointment) {
            throw new RuntimeException(
                'Appointment not found'
            );
        }

        if (!$appointment['client_id']) {
            throw new RuntimeException(
                'Appointment must have a client before invoicing'
            );
        }

        if (!$appointment['service_id']) {
            throw new RuntimeException(
                'Appointment must have a service before invoicing'
            );
        }

        if ($appointment['status'] !== 'completed') {
            throw new RuntimeException(
                'Only completed appointments can be invoiced'
            );
        }

        /*
         * Application-level idempotency check.
         * The UNIQUE database constraint provides the
         * final concurrency-safe protection.
         */
        $existing=$pdo->prepare("
            SELECT
                id,
                invoice_number
            FROM invoices
            WHERE tenant_id=?
              AND appointment_id=?
            LIMIT 1
        ");

        $existing->execute([
            $tid,
            $appointmentId
        ]);

        if ($row=$existing->fetch()) {
            throw new RuntimeException(
                'Appointment already has invoice ' .
                $row['invoice_number']
            );
        }

        $price=round(
            (float)$appointment['service_price'],
            2
        );

        if ($price < 0) {
            throw new RuntimeException(
                'Service price is invalid'
            );
        }

        /*
         * Generate a tenant-scoped human-readable number.
         * Timestamp + appointment ID makes accidental
         * collisions extremely unlikely; the database
         * unique constraint is authoritative.
         */
        $invoiceNumber =
            'INV-' .
            date('Ymd-His') .
            '-' .
            $appointmentId;

        $dueAt = !empty($d['due_at'])
            ? $d['due_at']
            : date(
                'Y-m-d H:i:s',
                strtotime('+14 days')
            );

        $notes =
            trim($d['notes'] ?? '') ?: null;

        $pdo->beginTransaction();

        $insert=$pdo->prepare("
            INSERT INTO invoices(
                tenant_id,
                client_id,
                appointment_id,
                invoice_number,
                status,
                subtotal,
                tax_amount,
                total,
                balance_due,
                due_at,
                notes
            )
            VALUES(
                ?,?,?,?,
                'draft',
                ?,
                0,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $insert->execute([
            $tid,
            (int)$appointment['client_id'],
            $appointmentId,
            $invoiceNumber,
            $price,
            $price,
            $price,
            $dueAt,
            $notes
        ]);

        $invoiceId =
            (int)$pdo->lastInsertId();

        $description =
            trim(
                ($appointment['service_name'] ?: $appointment['title']) .
                ' - ' .
                date(
                    'M j, Y',
                    strtotime($appointment['starts_at'])
                )
            );

        $item=$pdo->prepare("
            INSERT INTO invoice_items(
                invoice_id,
                description,
                quantity,
                unit_price,
                amount
            )
            VALUES(?,?,?,?,?)
        ");

        $item->execute([
            $invoiceId,
            $description,
            1,
            $price,
            $price
        ]);

        $pdo->commit();

        json_response([
            'ok'=>true,
            'id'=>$invoiceId,
            'invoice_number'=>$invoiceNumber,
            'total'=>$price
        ],201);

    } catch(Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'error'=>$e->getMessage()
        ],422);
    }
}


/*
|--------------------------------------------------------------------------
| PUT — Invoice lifecycle
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

    $invoiceId=(int)($d['id'] ?? 0);

    if ($invoiceId <= 0) {
        json_response([
            'error'=>'Invoice required'
        ],422);
    }

    try {

        $pdo->beginTransaction();

        $q=$pdo->prepare("
            SELECT
                id,
                status,
                total,
                balance_due,
                due_at
            FROM invoices
            WHERE id=?
              AND tenant_id=?
            FOR UPDATE
        ");

        $q->execute([
            $invoiceId,
            $tid
        ]);

        $invoice=$q->fetch();

        if (!$invoice) {
            throw new RuntimeException(
                'Invoice not found'
            );
        }

        $newStatus=
            $d['status'] ?? $invoice['status'];

        /*
         * paid and partial are controlled by payments.
         * overdue is derived from due_at.
         */
        if (!in_array(
            $newStatus,
            ['draft','sent','void'],
            true
        )) {
            throw new RuntimeException(
                'Invalid manual invoice status'
            );
        }

        if ($invoice['status']==='paid') {
            throw new RuntimeException(
                'Paid invoices cannot be manually changed'
            );
        }

        if (
            $invoice['status']==='partial' &&
            $newStatus==='draft'
        ) {
            throw new RuntimeException(
                'Partially paid invoices cannot return to draft'
            );
        }

        if ($newStatus==='void') {

            $p=$pdo->prepare("
                SELECT COUNT(*)
                FROM payments
                WHERE tenant_id=?
                  AND invoice_id=?
                  AND status='succeeded'
            ");

            $p->execute([
                $tid,
                $invoiceId
            ]);

            if ((int)$p->fetchColumn() > 0) {
                throw new RuntimeException(
                    'Invoice with successful payments cannot be voided'
                );
            }
        }

        $dueAt=$invoice['due_at'];

        if (array_key_exists('due_at',$d)) {

            if (!$d['due_at']) {

                $dueAt=null;

            } else {

                try {

                    $dueAt=(new DateTime(
                        $d['due_at']
                    ))->format(
                        'Y-m-d H:i:s'
                    );

                } catch(Throwable $e) {

                    throw new RuntimeException(
                        'Invalid due date'
                    );
                }
            }
        }

        if (array_key_exists('notes',$d)) {

            $notes=
                trim((string)$d['notes']) ?: null;

            $u=$pdo->prepare("
                UPDATE invoices
                SET
                    status=?,
                    due_at=?,
                    notes=?
                WHERE id=?
                  AND tenant_id=?
            ");

            $u->execute([
                $newStatus,
                $dueAt,
                $notes,
                $invoiceId,
                $tid
            ]);

        } else {

            $u=$pdo->prepare("
                UPDATE invoices
                SET
                    status=?,
                    due_at=?
                WHERE id=?
                  AND tenant_id=?
            ");

            $u->execute([
                $newStatus,
                $dueAt,
                $invoiceId,
                $tid
            ]);
        }

        $pdo->commit();

        json_response([
            'ok'=>true,
            'id'=>$invoiceId,
            'status'=>$newStatus,
            'due_at'=>$dueAt
        ]);

    } catch(Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'error'=>$e->getMessage()
        ],422);
    }
}

json_response([
    'error'=>'Method not allowed'
],405);
