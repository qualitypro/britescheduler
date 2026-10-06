<?php

$pageTitle='Invoice';

require __DIR__.'/partials/app_header.php';

?>

<style>

.invoice-sheet{
    max-width:900px;
    margin:0 auto;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:32px;
}

.invoice-heading{
    display:flex;
    justify-content:space-between;
    gap:30px;
    align-items:flex-start;
    margin-bottom:30px;
}

.invoice-number{
    font-size:28px;
    font-weight:700;
}

.invoice-meta{
    text-align:right;
}

.invoice-address{
    line-height:1.6;
}

.invoice-total{
    max-width:360px;
    margin-left:auto;
    margin-top:24px;
}

.invoice-total table td:last-child{
    text-align:right;
}

.invoice-actions{
    max-width:900px;
    margin:0 auto 18px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.status-badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:999px;
    background:#eef2ff;
    font-weight:600;
}

@media print{

    body{
        background:#fff;
    }

    .top,
    .nav,
    .invoice-actions,
    #invoiceError{
        display:none !important;
    }

    .wrap{
        max-width:none;
        margin:0;
        padding:0;
    }

    .invoice-sheet{
        border:0;
        box-shadow:none;
        max-width:none;
        padding:0;
    }
}

</style>

<div id="invoiceError"
     class="danger"
     style="max-width:900px;margin:0 auto 12px"></div>

<div class="invoice-actions">

    <button type="button"
            onclick="window.location.href=window.BRITE_BASE+'/billing.php'">
        Back to Billing
    </button>

    <button type="button"
            onclick="window.print()">
        Print Invoice
    </button>

    <button type="button"
            id="markSent"
            style="display:none">
        Mark Sent
    </button>

    <button type="button"
            id="voidInvoice"
            style="display:none">
        Void Invoice
    </button>

</div>

<div class="invoice-sheet">

    <div id="invoiceContent">
        Loading invoice...
    </div>

</div>

<script>

const params =
    new URLSearchParams(
        window.location.search
    );

const invoiceId =
    Number(params.get('id'));

const invoiceError =
    document.getElementById('invoiceError');

const invoiceContent =
    document.getElementById('invoiceContent');

const markSent =
    document.getElementById('markSent');

const voidInvoice =
    document.getElementById('voidInvoice');

let currentInvoice=null;

function money(value,currency='USD'){

    try{
        return new Intl.NumberFormat(
            undefined,
            {
                style:'currency',
                currency:currency
            }
        ).format(Number(value || 0));
    }catch(e){
        return '$'+Number(value || 0).toFixed(2);
    }
}

function escapeHtml(value){

    return String(value ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
}

function dateOnly(value){

    if(!value){
        return '';
    }

    const d =
        new Date(
            value.replace(' ','T')
        );

    if(Number.isNaN(d.getTime())){
        return value;
    }

    return d.toLocaleDateString();
}

function dateTime(value){

    if(!value){
        return '';
    }

    const d =
        new Date(
            value.replace(' ','T')
        );

    if(Number.isNaN(d.getTime())){
        return value;
    }

    return d.toLocaleString();
}

function statusLabel(value){

    const labels={
        draft:'Draft',
        sent:'Sent',
        partial:'Partial',
        paid:'Paid',
        overdue:'Overdue',
        void:'Void'
    };

    return labels[value] || value;
}

function addressHtml(i){

    const lines=[];

    if(i.company_name){
        lines.push(i.company_name);
    }

    lines.push(i.client_name);

    if(i.address1){
        lines.push(i.address1);
    }

    if(i.address2){
        lines.push(i.address2);
    }

    const locality=[
        i.city,
        i.state,
        i.postal_code
    ].filter(Boolean).join(' ');

    if(locality){
        lines.push(locality);
    }

    if(i.client_email){
        lines.push(i.client_email);
    }

    if(i.client_phone){
        lines.push(i.client_phone);
    }

    return lines
        .map(escapeHtml)
        .join('<br>');
}

function render(data){

    const i=data.invoice;
    const currency=i.tenant_currency || 'USD';

    currentInvoice=i;

    const itemRows =
        (data.items || []).map(item=>`
            <tr>
                <td>${escapeHtml(item.description)}</td>
                <td>${Number(item.quantity).toFixed(2)}</td>
                <td>${money(item.unit_price,currency)}</td>
                <td>${money(item.amount,currency)}</td>
            </tr>
        `).join('');

    const paymentRows =
        (data.payments || []).map(p=>`
            <tr>
                <td>${escapeHtml(dateTime(p.paid_at))}</td>
                <td>${escapeHtml(p.method || '')}</td>
                <td>${escapeHtml(p.status)}</td>
                <td>${money(p.amount,p.currency || currency)}</td>
            </tr>
        `).join('');

    invoiceContent.innerHTML=`

        <div class="invoice-heading">

            <div>
                <div class="invoice-number">
                    INVOICE
                </div>

                <h2>
                    ${escapeHtml(i.tenant_name)}
                </h2>
            </div>

            <div class="invoice-meta">

                <strong>
                    ${escapeHtml(i.invoice_number)}
                </strong>

                <p>
                    <span class="status-badge">
                        ${escapeHtml(statusLabel(i.status))}
                    </span>
                </p>

                <div>
                    Created:
                    ${escapeHtml(dateOnly(i.created_at))}
                </div>

                <div>
                    Due:
                    ${escapeHtml(dateOnly(i.due_at))}
                </div>

            </div>

        </div>

        <div class="grid"
             style="margin-bottom:28px">

            <div>
                <div class="muted">
                    Bill To
                </div>

                <div class="invoice-address">
                    ${addressHtml(i)}
                </div>
            </div>

            <div>
                <div class="muted">
                    Service Appointment
                </div>

                <strong>
                    ${escapeHtml(i.appointment_title || '')}
                </strong>

                <div>
                    ${escapeHtml(dateTime(i.appointment_starts_at))}
                </div>
            </div>

        </div>

        <table>

            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Rate</th>
                    <th>Amount</th>
                </tr>
            </thead>

            <tbody>
                ${itemRows}
            </tbody>

        </table>

        <div class="invoice-total">

            <table>

                <tr>
                    <td>Subtotal</td>
                    <td>${money(i.subtotal,currency)}</td>
                </tr>

                <tr>
                    <td>Tax</td>
                    <td>${money(i.tax_amount,currency)}</td>
                </tr>

                <tr>
                    <td><strong>Total</strong></td>
                    <td><strong>${money(i.total,currency)}</strong></td>
                </tr>

                <tr>
                    <td>Paid</td>
                    <td>${money(i.amount_paid,currency)}</td>
                </tr>

                <tr>
                    <td><strong>Balance Due</strong></td>
                    <td><strong>${money(i.balance_due,currency)}</strong></td>
                </tr>

            </table>

        </div>

        ${
            i.notes
            ? `
                <div style="margin-top:28px">
                    <div class="muted">Notes</div>
                    <div>${escapeHtml(i.notes)}</div>
                </div>
              `
            : ''
        }

        ${
            data.payments && data.payments.length
            ? `
                <div style="margin-top:32px">

                    <h3>Payment History</h3>

                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${paymentRows}
                        </tbody>
                    </table>

                </div>
              `
            : ''
        }
    `;

    markSent.style.display =
        i.status === 'draft'
            ? 'inline-block'
            : 'none';

    voidInvoice.style.display =
        ['draft','sent','overdue'].includes(i.status) &&
        Number(i.amount_paid || 0) === 0
            ? 'inline-block'
            : 'none';
}

async function loadInvoice(){

    if(!invoiceId){

        invoiceError.textContent=
            'Invalid invoice';

        return;
    }

    invoiceError.textContent='';

    try{

        const data =
            await api(
                '/api/invoices.php?id=' +
                encodeURIComponent(invoiceId)
            );

        render(data);

    }catch(e){

        invoiceError.textContent=
            e.message;
    }
}

async function updateStatus(status){

    invoiceError.textContent='';

    try{

        await api(
            '/api/invoices.php',
            {
                method:'PUT',
                headers:{
                    'Content-Type':'application/json'
                },
                body:JSON.stringify({
                    id:invoiceId,
                    status:status
                })
            }
        );

        await loadInvoice();

    }catch(e){

        invoiceError.textContent=
            e.message;
    }
}

markSent.onclick=async ()=>{

    if(!confirm(
        'Mark this invoice as sent?'
    )){
        return;
    }

    await updateStatus('sent');
};

voidInvoice.onclick=async ()=>{

    if(!confirm(
        'Void this invoice? This cannot be used when successful payments exist.'
    )){
        return;
    }

    await updateStatus('void');
};

loadInvoice();

</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
