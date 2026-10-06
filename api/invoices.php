<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();

$tid = Auth::tenantId();
$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $q = $pdo->prepare("
        SELECT
            i.*,
            CONCAT(c.first_name,' ',c.last_name) AS client_name,
            a.title AS appointment_title,
            a.starts_at AS appointment_starts_at,

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

        WHERE i.tenant_id=?

        ORDER BY i.created_at DESC
    ");

    $q->execute([$tid]);

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

json_response([
    'error'=>'Method not allowed'
],405);
