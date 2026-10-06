<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();

$tid = Auth::tenantId();
$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| Recalculate invoice financial state from payment ledger
|--------------------------------------------------------------------------
*/

function recalculate_invoice(PDO $pdo, int $tenantId, int $invoiceId): array
{
    $q=$pdo->prepare("
        SELECT
            id,
            total,
            status
        FROM invoices
        WHERE id=?
          AND tenant_id=?
        FOR UPDATE
    ");

    $q->execute([
        $invoiceId,
        $tenantId
    ]);

    $invoice=$q->fetch();

    if (!$invoice) {
        throw new RuntimeException('Invoice not found');
    }

    $p=$pdo->prepare("
        SELECT COALESCE(SUM(amount),0)
        FROM payments
        WHERE tenant_id=?
          AND invoice_id=?
          AND status='succeeded'
    ");

    $p->execute([
        $tenantId,
        $invoiceId
    ]);

    $paid=round((float)$p->fetchColumn(),2);
    $total=round((float)$invoice['total'],2);
    $balance=max(0,round($total-$paid,2));

    /*
     * Preserve void invoices.
     * Otherwise payment state determines paid/partial.
     * An unpaid invoice remains draft/sent/overdue.
     */
    if ($invoice['status'] === 'void') {
        $newStatus='void';
    } elseif ($balance <= 0) {
        $newStatus='paid';
    } elseif ($paid > 0) {
        $newStatus='partial';
    } else {
        $newStatus=$invoice['status'];
    }

    $u=$pdo->prepare("
        UPDATE invoices
        SET balance_due=?,
            status=?
        WHERE id=?
          AND tenant_id=?
    ");

    $u->execute([
        $balance,
        $newStatus,
        $invoiceId,
        $tenantId
    ]);

    return [
        'total'=>$total,
        'paid'=>$paid,
        'balance_due'=>$balance,
        'status'=>$newStatus
    ];
}

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD']==='GET') {

    $q=$pdo->prepare("
        SELECT
            p.*,
            CONCAT(c.first_name,' ',c.last_name) AS client_name,
            i.invoice_number
        FROM payments p

        JOIN clients c
          ON c.id=p.client_id
         AND c.tenant_id=p.tenant_id

        LEFT JOIN invoices i
          ON i.id=p.invoice_id
         AND i.tenant_id=p.tenant_id

        WHERE p.tenant_id=?

        ORDER BY
            COALESCE(p.paid_at,p.created_at) DESC,
            p.id DESC
    ");

    $q->execute([$tid]);

    json_response([
        'payments'=>$q->fetchAll()
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

$d=request_data();

/*
|--------------------------------------------------------------------------
| POST — Record invoice payment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD']==='POST') {

    $invoiceId=(int)($d['invoice_id'] ?? 0);

    if ($invoiceId <= 0) {
        json_response([
            'error'=>'Invoice required'
        ],422);
    }

    $amount=round(
        (float)($d['amount'] ?? 0),
        2
    );

    if ($amount <= 0) {
        json_response([
            'error'=>'Payment amount must be greater than zero'
        ],422);
    }

    $status=$d['status'] ?? 'succeeded';

    $allowedStatuses=[
        'pending',
        'succeeded',
        'failed',
        'refunded',
        'void'
    ];

    if (!in_array($status,$allowedStatuses,true)) {
        json_response([
            'error'=>'Invalid payment status'
        ],422);
    }

    $currency=strtoupper(
        trim($d['currency'] ?? 'USD')
    );

    if (!preg_match('/^[A-Z]{3}$/',$currency)) {
        json_response([
            'error'=>'Invalid currency'
        ],422);
    }

    try {

        $pdo->beginTransaction();

        /*
         * Lock and validate invoice.
         * Client comes from the invoice — never from browser input.
         */
        $q=$pdo->prepare("
            SELECT
                id,
                client_id,
                invoice_number,
                status,
                total,
                balance_due
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

        if ($invoice['status']==='void') {
            throw new RuntimeException(
                'Cannot record payment against a void invoice'
            );
        }

        /*
         * Determine authoritative balance from the ledger,
         * not the potentially stale balance_due field.
         */
        $sum=$pdo->prepare("
            SELECT COALESCE(SUM(amount),0)
            FROM payments
            WHERE tenant_id=?
              AND invoice_id=?
              AND status='succeeded'
        ");

        $sum->execute([
            $tid,
            $invoiceId
        ]);

        $alreadyPaid=
            round((float)$sum->fetchColumn(),2);

        $total=
            round((float)$invoice['total'],2);

        $currentBalance=
            max(0,round($total-$alreadyPaid,2));

        if ($status==='succeeded') {

            if ($currentBalance <= 0) {
                throw new RuntimeException(
                    'Invoice is already paid'
                );
            }

            if ($amount > $currentBalance) {
                throw new RuntimeException(
                    'Payment exceeds remaining balance of $' .
                    number_format($currentBalance,2)
                );
            }
        }

        $method=
            trim($d['method'] ?? '') ?: null;

        $provider=
            trim($d['provider'] ?? '') ?: null;

        $providerReference=
            trim($d['provider_reference'] ?? '') ?: null;

        $notes=
            trim($d['notes'] ?? '') ?: null;

        $paidAt=
            trim($d['paid_at'] ?? '');

        if ($paidAt==='') {
            $paidAt=date('Y-m-d H:i:s');
        } else {
            try {
                $paidAt=(new DateTime($paidAt))
                    ->format('Y-m-d H:i:s');
            } catch(Throwable $e) {
                throw new RuntimeException(
                    'Invalid payment date'
                );
            }
        }

        $insert=$pdo->prepare("
            INSERT INTO payments(
                tenant_id,
                invoice_id,
                client_id,
                amount,
                currency,
                status,
                method,
                provider,
                provider_reference,
                paid_at,
                notes
            )
            VALUES(?,?,?,?,?,?,?,?,?,?,?)
        ");

        $insert->execute([
            $tid,
            $invoiceId,
            (int)$invoice['client_id'],
            $amount,
            $currency,
            $status,
            $method,
            $provider,
            $providerReference,
            $paidAt,
            $notes
        ]);

        $paymentId=
            (int)$pdo->lastInsertId();

        $financials=
            recalculate_invoice(
                $pdo,
                $tid,
                $invoiceId
            );

        $pdo->commit();

        json_response([
            'ok'=>true,
            'id'=>$paymentId,
            'invoice_id'=>$invoiceId,
            'invoice_number'=>$invoice['invoice_number'],
            'amount'=>$amount,
            'invoice'=>$financials
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
