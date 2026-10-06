<?php

$pageTitle='Invoice';

require __DIR__.'/partials/app_header.php';

?>

<style>

.invoice-toolbar{
    max-width:920px;
    margin:0 auto 16px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.invoice-paper{
    max-width:920px;
    margin:0 auto;
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:46px 52px;
    box-shadow:0 3px 14px #0000000d;
}

.invoice-top{
    display:flex;
    justify-content:space-between;
    gap:40px;
    padding-bottom:28px;
    border-bottom:2px solid #172033;
}

.business-name{
    font-size:26px;
    font-weight:750;
    margin-bottom:8px;
}

.business-contact{
    line-height:1.55;
    color:#475569;
}

.invoice-title{
    text-align:right;
}

.invoice-title h1{
    margin:0 0 8px;
    font-size:34px;
    letter-spacing:2px;
}

.invoice-number{
    font-weight:650;
}

.status{
    display:inline-block;
    margin-top:12px;
    padding:6px 13px;
    border-radius:999px;
    background:#eef2ff;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.5px;
    font-size:12px;
}

.invoice-info{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:60px;
    margin:32px 0;
}

.section-label{
    color:#64748b;
    text-transform:uppercase;
    letter-spacing:1px;
    font-size:12px;
    font-weight:700;
    margin-bottom:9px;
}

.bill-to{
    line-height:1.6;
}

.meta-table{
    width:100%;
    background:transparent;
}

.meta-table td{
    padding:4px 0;
    border:0;
}

.meta-table td:last-child{
    text-align:right;
    font-weight:600;
}

.appointment-box{
    margin-bottom:30px;
    padding:16px 18px;
    background:#f8fafc;
    border-radius:8px;
}

.items-table{
    margin-top:10px;
}

.items-table th{
    background:#172033;
    color:#fff;
    border:0;
}

.items-table th:nth-child(n+2),
.items-table td:nth-child(n+2){
    text-align:right;
}

.summary{
    width:360px;
    margin:24px 0 0 auto;
}

.summary td{
    border:0;
    padding:6px 8px;
}

.summary td:last-child{
    text-align:right;
}

.summary .total-row td{
    border-top:1px solid #cbd5e1;
    padding-top:10px;
    font-weight:700;
}

.balance-box{
    margin-top:8px;
    padding:14px 12px;
    background:#172033;
    color:#fff;
    border-radius:7px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.balance-box strong{
    font-size:22px;
}

.paid-stamp{
    margin:30px 0 0 auto;
    width:max-content;
    border:3px solid #334155;
    padding:8px 18px;
    font-size:18px;
    font-weight:800;
    letter-spacing:2px;
    transform:rotate(-2deg);
}

.invoice-notes{
    margin-top:36px;
    padding-top:22px;
    border-top:1px solid #e2e8f0;
}

.payment-history{
    margin-top:34px;
}

.payment-history th:nth-child(3),
.payment-history td:nth-child(3),
.payment-history th:nth-child(4),
.payment-history td:nth-child(4){
    text-align:right;
}

.invoice-footer{
    margin-top:42px;
    padding-top:20px;
    border-top:1px solid #e2e8f0;
    text-align:center;
    color:#64748b;
    font-size:13px;
}

#error{
    max-width:920px;
    margin:0 auto 12px;
}

@media(max-width:700px){

    .invoice-paper{
        padding:25px;
    }

    .invoice-top,
    .invoice-info{
        display:block;
    }

    .invoice-title{
        text-align:left;
        margin-top:25px;
    }

    .invoice-info > div{
        margin-bottom:25px;
    }

    .summary{
        width:100%;
    }
}

@page{
    size:Letter;
    margin:0.45in;
}

@media print{

    body{
        background:#fff;
    }

    .top,
    .nav,
    .invoice-toolbar,
    #error{
        display:none !important;
    }

    .wrap{
        max-width:none;
        margin:0;
        padding:0;
    }

    .invoice-paper{
        max-width:none;
        margin:0;
        padding:0;
        border:0;
        border-radius:0;
        box-shadow:none;
    }

    table,
    tr,
    .appointment-box,
    .summary,
    .payment-history{
        break-inside:avoid;
        page-break-inside:avoid;
    }

    .items-table th{
        background:#172033 !important;
        color:#fff !important;
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
    }

    .balance-box{
        background:#172033 !important;
        color:#fff !important;
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
    }
}

</style>

<div id="error" class="danger"></div>

<div class="invoice-toolbar">

    <button type="button"
            onclick="location.href=window.BRITE_BASE+'/billing.php'">
        Back to Billing
    </button>

    <button type="button"
            onclick="window.print()">
        Print / Save PDF
    </button>

    <button id="markSent"
            type="button"
            style="display:none">
        Mark Sent
    </button>

    <button id="voidInvoice"
            type="button"
            style="display:none">
        Void Invoice
    </button>

</div>

<div class="invoice-paper">

    <div id="invoice">
        Loading invoice...
    </div>

</div>

<script>

const invoiceId =
    Number(
        new URLSearchParams(location.search).get('id')
    );

const output =
    document.getElementById('invoice');

const errorBox =
    document.getElementById('error');

const markSent =
    document.getElementById('markSent');

const voidInvoice =
    document.getElementById('voidInvoice');

function esc(value){

    return String(value ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
}

function money(value,currency='USD'){

    try{
        return new Intl.NumberFormat(
            'en-US',
            {
                style:'currency',
                currency
            }
        ).format(Number(value || 0));
    }catch(e){
        return '$'+Number(value || 0).toFixed(2);
    }
}

function parseDate(value){

    if(!value){
        return null;
    }

    const d=new Date(
        String(value).replace(' ','T')
    );

    return Number.isNaN(d.getTime())
        ? null
        : d;
}

function dateOnly(value){

    const d=parseDate(value);

    return d
        ? d.toLocaleDateString(
            'en-US',
            {
                month:'short',
                day:'numeric',
                year:'numeric'
            }
        )
        : '';
}

function dateTime(value){

    const d=parseDate(value);

    return d
        ? d.toLocaleString(
            'en-US',
            {
                month:'short',
                day:'numeric',
                year:'numeric',
                hour:'numeric',
                minute:'2-digit'
            }
        )
        : '';
}

function phone(value){

    const raw=String(value || '').trim();
    const digits=raw.replace(/\D/g,'');

    if(digits.length===10){
        return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;
    }

    if(digits.length===11 && digits[0]==='1'){
        return `+1 (${digits.slice(1,4)}) ${digits.slice(4,7)}-${digits.slice(7)}`;
    }

    return raw;
}

function lines(values){

    return values
        .filter(v=>String(v || '').trim()!=='')
        .map(v=>esc(v))
        .join('<br>');
}

function cityStateZip(city,state,zip){

    let left=[city,state]
        .filter(Boolean)
        .join(', ');

    return [left,zip]
        .filter(Boolean)
        .join(' ');
}

function statusLabel(status){

    return {
        draft:'Draft',
        sent:'Sent',
        partial:'Partial',
        paid:'Paid',
        overdue:'Overdue',
        void:'Void'
    }[status] || status;
}

function render(data){

    const i=data.invoice;
    const currency=i.tenant_currency || 'USD';

    const businessAddress=lines([
        i.tenant_address1,
        i.tenant_address2,
        cityStateZip(
            i.tenant_city,
            i.tenant_state,
            i.tenant_postal_code
        )
    ]);

    const businessContact=lines([
        i.tenant_phone ? phone(i.tenant_phone) : '',
        i.tenant_email,
        i.tenant_website
    ]);

    const clientAddress=lines([
        i.company_name,
        i.client_name,
        i.address1,
        i.address2,
        cityStateZip(
            i.city,
            i.state,
            i.postal_code
        ),
        i.client_email,
        i.client_phone ? phone(i.client_phone) : ''
    ]);

    const itemRows=(data.items || [])
        .map(item=>`
            <tr>
                <td>${esc(item.description)}</td>
                <td>${Number(item.quantity).toFixed(2)}</td>
                <td>${money(item.unit_price,currency)}</td>
                <td>${money(item.amount,currency)}</td>
            </tr>
        `)
        .join('');

    const paymentRows=(data.payments || [])
        .map(p=>`
            <tr>
                <td>${esc(dateTime(p.paid_at || p.created_at))}</td>
                <td>${esc(p.method || '')}</td>
                <td>${esc(p.status)}</td>
                <td>${money(p.amount,p.currency || currency)}</td>
            </tr>
        `)
        .join('');

    output.innerHTML=`

        <div class="invoice-top">

            <div>

                <div class="business-name">
                    ${esc(i.tenant_name)}
                </div>

                ${
                    businessAddress
                    ? `<div class="business-contact">${businessAddress}</div>`
                    : ''
                }

                ${
                    businessContact
                    ? `<div class="business-contact"
                            style="margin-top:5px">
                           ${businessContact}
                       </div>`
                    : ''
                }

            </div>

            <div class="invoice-title">

                <h1>INVOICE</h1>

                <div class="invoice-number">
                    ${esc(i.invoice_number)}
                </div>

                <div class="status">
                    ${esc(statusLabel(i.status))}
                </div>

            </div>

        </div>

        <div class="invoice-info">

            <div>

                <div class="section-label">
                    Bill To
                </div>

                <div class="bill-to">
                    ${clientAddress}
                </div>

            </div>

            <div>

                <div class="section-label">
                    Invoice Details
                </div>

                <table class="meta-table">

                    <tr>
                        <td>Invoice Date</td>
                        <td>${esc(dateOnly(i.created_at))}</td>
                    </tr>

                    <tr>
                        <td>Due Date</td>
                        <td>${esc(dateOnly(i.due_at))}</td>
                    </tr>

                    <tr>
                        <td>Status</td>
                        <td>${esc(statusLabel(i.status))}</td>
                    </tr>

                </table>

            </div>

        </div>

        ${
            i.appointment_title
            ? `
                <div class="appointment-box">

                    <div class="section-label">
                        Service Appointment
                    </div>

                    <strong>
                        ${esc(i.appointment_title)}
                    </strong>

                    ${
                        i.appointment_starts_at
                        ? `<div>${esc(dateTime(i.appointment_starts_at))}</div>`
                        : ''
                    }

                </div>
              `
            : ''
        }

        <table class="items-table">

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

        <table class="summary">

            <tr>
                <td>Subtotal</td>
                <td>${money(i.subtotal,currency)}</td>
            </tr>

            <tr>
                <td>Tax</td>
                <td>${money(i.tax_amount,currency)}</td>
            </tr>

            <tr class="total-row">
                <td>Total</td>
                <td>${money(i.total,currency)}</td>
            </tr>

            <tr>
                <td>Payments</td>
                <td>-${money(i.amount_paid,currency)}</td>
            </tr>

        </table>

        <div class="summary">

            <div class="balance-box">

                <span>
                    BALANCE DUE
                </span>

                <strong>
                    ${money(i.balance_due,currency)}
                </strong>

            </div>

        </div>

        ${
            i.status==='paid' ||
            Number(i.balance_due) <= 0
            ? `<div class="paid-stamp">PAID IN FULL</div>`
            : ''
        }

        ${
            i.payment_instructions
            ? `
                <div class="invoice-notes">

                    <div class="section-label">
                        Payment Instructions
                    </div>

                    <div>
                        ${esc(i.payment_instructions)
                            .replaceAll('\n','<br>')}
                    </div>

                </div>
              `
            : ''
        }

        ${
            i.notes
            ? `
                <div class="invoice-notes">

                    <div class="section-label">
                        Notes
                    </div>

                    <div>
                        ${esc(i.notes)
                            .replaceAll('\n','<br>')}
                    </div>

                </div>
              `
            : ''
        }

        ${
            data.payments && data.payments.length
            ? `
                <div class="payment-history">

                    <div class="section-label">
                        Payment History
                    </div>

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

        ${
            i.invoice_footer
            ? `
                <div class="invoice-footer">
                    ${esc(i.invoice_footer)
                        .replaceAll('\n','<br>')}
                </div>
              `
            : ''
        }
    `;

    markSent.style.display =
        i.status==='draft'
            ? 'inline-block'
            : 'none';

    voidInvoice.style.display =
        ['draft','sent','overdue'].includes(i.status) &&
        Number(i.amount_paid || 0)===0
            ? 'inline-block'
            : 'none';
}

async function loadInvoice(){

    if(!invoiceId){

        errorBox.textContent='Invalid invoice.';
        return;
    }

    try{

        errorBox.textContent='';

        const data=await api(
            '/api/invoices.php?id='+
            encodeURIComponent(invoiceId)
        );

        render(data);

    }catch(e){

        errorBox.textContent=e.message;
    }
}

async function changeStatus(status){

    try{

        errorBox.textContent='';

        await api(
            '/api/invoices.php',
            {
                method:'PUT',
                headers:{
                    'Content-Type':'application/json'
                },
                body:JSON.stringify({
                    id:invoiceId,
                    status
                })
            }
        );

        await loadInvoice();

    }catch(e){

        errorBox.textContent=e.message;
    }
}

markSent.onclick=async ()=>{

    if(confirm('Mark this invoice as sent?')){
        await changeStatus('sent');
    }
};

voidInvoice.onclick=async ()=>{

    if(confirm('Void this invoice?')){
        await changeStatus('void');
    }
};

loadInvoice();

</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
