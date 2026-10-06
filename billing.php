<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','accounting');


$pageTitle='Billing';

require __DIR__.'/partials/app_header.php';

?>

<h1>Billing & Payments</h1>

<div id="billingError"
     class="danger"
     style="margin-bottom:12px"></div>

<div class="grid">

    <div class="card">
        <div class="muted">
            Outstanding Receivables
        </div>
        <div class="metric"
             id="receivablesMetric">
            $0.00
        </div>
    </div>

    <div class="card">
        <div class="muted">
            Total Invoiced
        </div>
        <div class="metric"
             id="invoicedMetric">
            $0.00
        </div>
    </div>

    <div class="card">
        <div class="muted">
            Successful Payments
        </div>
        <div class="metric"
             id="paymentsMetric">
            $0.00
        </div>
    </div>

</div>

<div class="card"
     style="margin-top:18px">

    <h2>Invoices</h2>

    <div style="overflow-x:auto">

        <table>

            <thead>
                <tr>
                    <th>#</th>
                    <th>Client</th>
                    <th>Appointment</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Due Date</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody id="invoiceRows"></tbody>

        </table>

    </div>

</div>

<div class="card"
     id="paymentCard"
     style="margin-top:18px;display:none">

    <h2>Record Payment</h2>

    <div id="paymentInvoice"
         style="margin-bottom:14px"></div>

    <form id="paymentForm">

        <input type="hidden"
               id="paymentInvoiceId">

        <div class="row">

            <label>
                Amount
                <input type="number"
                       id="paymentAmount"
                       min="0.01"
                       step="0.01"
                       required>
            </label>

            <label>
                Method
                <select id="paymentMethod">
                    <option value="cash">Cash</option>
                    <option value="check">Check</option>
                    <option value="card">Card / External Processor</option>
                    <option value="ach">ACH</option>
                    <option value="zelle">Zelle</option>
                    <option value="other">Other</option>
                </select>
            </label>

            <label>
                Payment Date
                <input type="datetime-local"
                       id="paymentDate">
            </label>

        </div>

        <div class="row">

            <label>
                Provider
                <input type="text"
                       id="paymentProvider"
                       placeholder="Optional">
            </label>

            <label>
                Provider / Transaction Reference
                <input type="text"
                       id="paymentReference"
                       placeholder="Optional">
            </label>

        </div>

        <div class="row">

            <label>
                Notes
                <textarea id="paymentNotes"
                          rows="3"
                          placeholder="Optional"></textarea>
            </label>

        </div>

        <button type="submit">
            Record Payment
        </button>

        <button type="button"
                id="cancelPayment">
            Cancel
        </button>

    </form>

</div>

<div class="card"
     style="margin-top:18px">

    <h2>Payment History</h2>

    <div style="overflow-x:auto">

        <table>

            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Reference</th>
                </tr>
            </thead>

            <tbody id="paymentRows"></tbody>

        </table>

    </div>

</div>

<script>

const billingError =
    document.getElementById('billingError');

const invoiceRows =
    document.getElementById('invoiceRows');

const paymentRows =
    document.getElementById('paymentRows');

const paymentCard =
    document.getElementById('paymentCard');

const paymentForm =
    document.getElementById('paymentForm');

const paymentInvoiceId =
    document.getElementById('paymentInvoiceId');

const paymentInvoice =
    document.getElementById('paymentInvoice');

const paymentAmount =
    document.getElementById('paymentAmount');

const paymentMethod =
    document.getElementById('paymentMethod');

const paymentDate =
    document.getElementById('paymentDate');

const paymentProvider =
    document.getElementById('paymentProvider');

const paymentReference =
    document.getElementById('paymentReference');

const paymentNotes =
    document.getElementById('paymentNotes');

const cancelPayment =
    document.getElementById('cancelPayment');

let invoices = [];
let payments = [];

function money(value){
    return '$' + Number(value || 0).toFixed(2);
}

function escapeHtml(value){

    return String(value ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
}

function displayDate(value){

    if(!value){
        return '';
    }

    const normalized =
        value.replace(' ','T');

    const d =
        new Date(normalized);

    if(Number.isNaN(d.getTime())){
        return value;
    }

    return d.toLocaleString();
}

function statusLabel(status){

    const labels={
        draft:'Draft',
        sent:'Sent',
        partial:'Partial',
        paid:'Paid',
        void:'Void',
        overdue:'Overdue'
    };

    return labels[status] || status;
}


window.viewInvoice=function(id){

    location.href =
        window.BRITE_BASE +
        '/invoice.php?id=' +
        encodeURIComponent(id);
};

function renderInvoices(){

    if(!invoices.length){

        invoiceRows.innerHTML=
            '<tr><td colspan="9" class="muted">No invoices yet.</td></tr>';

        return;
    }

    invoiceRows.innerHTML =
        invoices.map(x=>{

            const balance =
                Number(x.balance_due || 0);

            const paid =
                Number(x.amount_paid || 0);

            /*
             * Every invoice can be viewed regardless of
             * payment state.
             */
            let action=`
                <button type="button"
                        onclick="viewInvoice(${Number(x.id)})">
                    View Invoice
                </button>
            `;

            /*
             * Only invoices with an outstanding balance
             * can accept another payment.
             */
            if(
                balance > 0 &&
                x.status !== 'void'
            ){
                action += `
                    <button type="button"
                            onclick="openPayment(${Number(x.id)})"
                            style="margin-left:6px">
                        Record Payment
                    </button>
                `;
            }

            return `
                <tr>
                    <td>${escapeHtml(x.invoice_number)}</td>
                    <td>${escapeHtml(x.client_name)}</td>
                    <td>${escapeHtml(x.appointment_title || '')}</td>
                    <td>${escapeHtml(statusLabel(x.status))}</td>
                    <td>${money(x.total)}</td>
                    <td>${money(paid)}</td>
                    <td>${money(balance)}</td>
                    <td>${escapeHtml(displayDate(x.due_at))}</td>
                    <td>${action}</td>
                </tr>
            `;
        }).join('');
}

function renderPayments(){

    if(!payments.length){

        paymentRows.innerHTML=
            '<tr><td colspan="7" class="muted">No payments yet.</td></tr>';

        return;
    }

    paymentRows.innerHTML =
        payments.map(x=>`
            <tr>
                <td>${escapeHtml(x.invoice_number || '')}</td>
                <td>${escapeHtml(x.client_name)}</td>
                <td>${money(x.amount)}</td>
                <td>${escapeHtml(x.method || '')}</td>
                <td>${escapeHtml(x.status)}</td>
                <td>${escapeHtml(displayDate(x.paid_at))}</td>
                <td>${escapeHtml(x.provider_reference || '')}</td>
            </tr>
        `).join('');
}

function renderMetrics(){

    const totalInvoiced =
        invoices
            .filter(x=>x.status !== 'void')
            .reduce(
                (sum,x)=>sum+Number(x.total || 0),
                0
            );

    const receivables =
        invoices
            .filter(x=>x.status !== 'void')
            .reduce(
                (sum,x)=>sum+Number(x.balance_due || 0),
                0
            );

    const successfulPayments =
        payments
            .filter(x=>x.status === 'succeeded')
            .reduce(
                (sum,x)=>sum+Number(x.amount || 0),
                0
            );

    document.getElementById(
        'invoicedMetric'
    ).textContent=money(totalInvoiced);

    document.getElementById(
        'receivablesMetric'
    ).textContent=money(receivables);

    document.getElementById(
        'paymentsMetric'
    ).textContent=money(successfulPayments);
}

async function loadBilling(){

    billingError.textContent='';

    try{

        const [invoiceData,paymentData] =
            await Promise.all([
                api('/api/invoices.php'),
                api('/api/payments.php')
            ]);

        invoices =
            invoiceData.invoices || [];

        payments =
            paymentData.payments || [];

        renderInvoices();
        renderPayments();
        renderMetrics();

    }catch(e){

        billingError.textContent=e.message;
    }
}

window.openPayment=function(id){

    const invoice =
        invoices.find(
            x=>Number(x.id)===Number(id)
        );

    if(!invoice){
        return;
    }

    paymentInvoiceId.value=
        invoice.id;

    paymentAmount.value=
        Number(invoice.balance_due).toFixed(2);

    paymentInvoice.innerHTML=`
        <strong>${escapeHtml(invoice.invoice_number)}</strong>
        · ${escapeHtml(invoice.client_name)}
        · Balance ${money(invoice.balance_due)}
    `;

    paymentProvider.value='';
    paymentReference.value='';
    paymentNotes.value='';

    /*
     * Leave date blank so the server records its
     * authoritative current time.
     */
    paymentDate.value='';

    paymentCard.style.display='block';

    paymentCard.scrollIntoView({
        behavior:'smooth',
        block:'start'
    });
};

cancelPayment.onclick=()=>{

    paymentCard.style.display='none';
    paymentForm.reset();
    paymentInvoiceId.value='';
};

paymentForm.addEventListener(
    'submit',
    async e=>{

        e.preventDefault();

        billingError.textContent='';

        const invoiceId =
            Number(paymentInvoiceId.value);

        const amount =
            Number(paymentAmount.value);

        if(!invoiceId){
            billingError.textContent=
                'Invoice required';
            return;
        }

        if(!amount || amount <= 0){
            billingError.textContent=
                'Enter a valid payment amount';
            return;
        }

        const payload={
            invoice_id:invoiceId,
            amount:amount,
            currency:'USD',
            status:'succeeded',
            method:paymentMethod.value,
            provider:
                paymentProvider.value || null,
            provider_reference:
                paymentReference.value || null,
            notes:
                paymentNotes.value || null
        };

        if(paymentDate.value){
            payload.paid_at=
                paymentDate.value;
        }

        try{

            const result =
                await api(
                    '/api/payments.php',
                    {
                        method:'POST',
                        headers:{
                            'Content-Type':
                                'application/json'
                        },
                        body:JSON.stringify(payload)
                    }
                );

            alert(
                `${money(result.amount)} payment recorded. ` +
                `Remaining balance: ${money(result.invoice.balance_due)}`
            );

            paymentCard.style.display='none';
            paymentForm.reset();
            paymentInvoiceId.value='';

            await loadBilling();

        }catch(e){

            billingError.textContent=
                e.message;
        }
    }
);

loadBilling();

</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
