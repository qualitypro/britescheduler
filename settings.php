<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin');


$pageTitle='Settings';

require __DIR__.'/partials/app_header.php';

?>

<h1>Business Settings</h1>

<p class="muted">
    Configure the business information displayed on invoices
    and other customer-facing documents.
</p>

<div class="card">

    <h2>Business Profile</h2>

    <form id="businessForm">

        <div class="row">

            <div>
                <label>Business Name</label><br>
                <input id="businessName"
                       required>
            </div>

            <div>
                <label>Business Email</label><br>
                <input id="businessEmail"
                       type="email">
            </div>

            <div>
                <label>Business Phone</label><br>
                <input id="businessPhone">
            </div>

        </div>

        <div class="row">

            <div>
                <label>Website</label><br>
                <input id="website"
                       placeholder="https://example.com">
            </div>

            <div>
                <label>Currency</label><br>
                <input id="currency"
                       maxlength="3"
                       value="USD">
            </div>

            <div>
                <label>Timezone</label><br>
                <input id="timezone"
                       value="America/New_York">
            </div>

        </div>

        <h3>Business Address</h3>

        <div class="row">

            <div>
                <label>Address</label><br>
                <input id="address1">
            </div>

            <div>
                <label>Address 2</label><br>
                <input id="address2">
            </div>

        </div>

        <div class="row">

            <div>
                <label>City</label><br>
                <input id="city">
            </div>

            <div>
                <label>State</label><br>
                <input id="state">
            </div>

            <div>
                <label>ZIP / Postal Code</label><br>
                <input id="postalCode">
            </div>

        </div>

        <h3>Invoice Configuration</h3>

        <div style="margin-bottom:16px">

            <label>Payment Instructions</label><br>

            <textarea id="paymentInstructions"
                      rows="4"
                      style="width:100%"
                      placeholder="Payment methods, remittance instructions, or other customer payment information."></textarea>

        </div>

        <div style="margin-bottom:16px">

            <label>Invoice Footer</label><br>

            <textarea id="invoiceFooter"
                      rows="3"
                      style="width:100%"
                      placeholder="Thank you for your business."></textarea>

        </div>

        <button type="submit">
            Save Business Profile
        </button>

        <span id="saveStatus"
              style="margin-left:12px"></span>

    </form>

</div>

<script>

const businessForm =
    document.getElementById('businessForm');

const saveStatus =
    document.getElementById('saveStatus');

const fields={
    name:document.getElementById('businessName'),
    business_email:document.getElementById('businessEmail'),
    business_phone:document.getElementById('businessPhone'),
    website:document.getElementById('website'),
    currency:document.getElementById('currency'),
    timezone:document.getElementById('timezone'),
    address1:document.getElementById('address1'),
    address2:document.getElementById('address2'),
    city:document.getElementById('city'),
    state:document.getElementById('state'),
    postal_code:document.getElementById('postalCode'),
    payment_instructions:document.getElementById('paymentInstructions'),
    invoice_footer:document.getElementById('invoiceFooter')
};

async function loadBusiness(){

    try{

        const data =
            await api('/api/business_profile.php');

        const b=data.business;

        Object.entries(fields).forEach(
            ([key,element])=>{
                element.value=b[key] ?? '';
            }
        );

    }catch(e){

        saveStatus.textContent=e.message;
        saveStatus.className='danger';
    }
}

businessForm.addEventListener(
    'submit',
    async event=>{

        event.preventDefault();

        saveStatus.textContent='Saving...';
        saveStatus.className='muted';

        const payload={};

        Object.entries(fields).forEach(
            ([key,element])=>{
                payload[key]=element.value;
            }
        );

        try{

            await api(
                '/api/business_profile.php',
                {
                    method:'PUT',
                    headers:{
                        'Content-Type':'application/json'
                    },
                    body:JSON.stringify(payload)
                }
            );

            saveStatus.textContent='Saved';
            saveStatus.className='';

        }catch(e){

            saveStatus.textContent=e.message;
            saveStatus.className='danger';
        }
    }
);

loadBusiness();

</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
