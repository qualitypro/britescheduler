<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler');
$qualificationCanEdit = in_array((string)Auth::tenant()['role'], ['owner','admin'], true);
 $pageTitle='Contractors';require __DIR__.'/partials/app_header.php';?>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-directory-accordion.css'),ENT_QUOTES,'UTF-8')?>">
<h1>Contractors</h1><p class="muted">Expand a contractor to edit their profile and manage service qualifications.</p><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="business_name" placeholder="Business"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><input name="hourly_rate" type="number" step=".01" placeholder="Hourly rate"><button>Add contractor</button></div></form></div>
<section class="brite-directory-list" id="rows" aria-label="Contractor records" style="margin-top:18px"></section><div id="contractorPagination"></div>

<div class="card" id="qualificationPanel"
     style="margin-top:18px;display:none">
  <h2>Contractor Service Qualifications</h2>
  <p id="qualificationContractor" class="muted"></p>
  <p id="qualificationMessage" role="status"></p>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Service</th>
          <th>Availability</th>
          <th>Verification</th>
          <th>Verified at</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="qualificationRows"></tbody>
    </table>
    <div id="qualificationPagination"></div>
  </div>
</div>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-pagination.js'))?>"></script>
<script>
window.BRITE_QUALIFICATION_CAN_EDIT =
    <?=json_encode($qualificationCanEdit)?>;
function esc(s){return String(s??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[x]))}

const contractorPager = new BritePagination({
    container: document.getElementById('contractorPagination'),
    render(contractors) {
        const list=document.getElementById('rows');
        list.replaceChildren();
        contractors.forEach(c=>{
            const details=document.createElement('details');
            details.className='brite-directory-record';
            details.dataset.contractorId=String(c.id);
            const summary=document.createElement('summary');
            summary.className='brite-directory-summary';
            const main=document.createElement('span');
            main.className='brite-directory-summary-main';
            const title=document.createElement('strong');
            title.textContent=[c.first_name,c.last_name].filter(Boolean).join(' ')||'Unnamed contractor';
            const subtitle=document.createElement('small');
            subtitle.textContent=[c.business_name,c.email,c.phone].filter(Boolean).join(' · ');
            main.append(title,subtitle);
            const state=document.createElement('span');
            state.className='brite-directory-state';
            state.textContent=c.status||'Unknown';
            summary.append(main,state);
            details.append(summary);
            const form=document.createElement('form');
            form.className='brite-directory-form';
            const fields=[
                ['first_name','First name','text',true],['last_name','Last name','text',true],
                ['business_name','Business','text'],['email','Email','email'],
                ['phone','Phone','tel'],['hourly_rate','Hourly rate','number'],
                ['skills','Skills','textarea'],['color','Color','text'],
                ['status','Status','status']
            ];
            const grid=document.createElement('div');
            grid.className='brite-directory-fields';
            fields.forEach(([name,label,type,required])=>{
                const wrap=document.createElement('div');wrap.className='brite-directory-field';
                const labelNode=document.createElement('label');
                const id='contractor-'+c.id+'-'+name;
                labelNode.htmlFor=id;labelNode.textContent=label;
                let input;
                if(type==='textarea'){input=document.createElement('textarea');input.rows=3;}
                else if(type==='status'){
                    input=document.createElement('select');
                    ['active','inactive'].forEach(value=>{
                        const option=document.createElement('option');
                        option.value=value;option.textContent=value;input.append(option);
                    });
                }else{
                    input=document.createElement('input');
                    input.type=type;
                    if(type==='number'){input.min='0';input.step='0.01';}
                }
                input.name=name;input.id=id;input.required=!!required;
                input.value=c[name]??'';
                wrap.append(labelNode,input);grid.append(wrap);
            });
            const actions=document.createElement('div');
            actions.className='brite-directory-actions';
            const save=document.createElement('button');save.type='submit';save.textContent='Save changes';
            const reset=document.createElement('button');reset.type='button';reset.textContent='Cancel';
            reset.addEventListener('click',()=>{form.reset();feedback.textContent='';details.open=false;});
            const feedback=document.createElement('span');
            feedback.className='brite-directory-feedback';feedback.setAttribute('role','status');
            actions.append(save,reset,feedback);form.append(grid,actions);
            form.addEventListener('submit',async event=>{
                event.preventDefault();save.disabled=true;feedback.textContent='Saving…';
                try {
                    const payload=Object.fromEntries(new FormData(form));payload.id=Number(c.id);
                    await api('/api/contractors.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
                    Object.assign(c,payload);
                    title.textContent=[c.first_name,c.last_name].filter(Boolean).join(' ');
                    subtitle.textContent=[c.business_name,c.email,c.phone].filter(Boolean).join(' · ');
                    state.textContent=c.status||'Unknown';
                    feedback.textContent='Changes saved';
                }catch(error){feedback.textContent='Save failed: '+error.message;}
                finally{save.disabled=false;}
            });
            details.append(form);
            const qualifications=document.createElement('div');
            qualifications.className='brite-directory-form';
            const qualificationButton=document.createElement('button');
            qualificationButton.type='button';
            qualificationButton.dataset.qualificationContractor=String(c.id);
            qualificationButton.textContent='Manage service qualifications';
            qualifications.append(qualificationButton);
            const mount=document.createElement('div');
            mount.dataset.qualificationMount='true';
            qualifications.append(mount);
            details.append(qualifications);
            const offeringSection=document.createElement('section');
            offeringSection.className='brite-directory-form';
            const offeringHeading=document.createElement('h3');
            offeringHeading.textContent='Services offered and pricing';
            const offeringMount=document.createElement('div');
            offeringMount.dataset.offeringMount=String(c.id);
            offeringSection.append(offeringHeading,offeringMount);
            details.append(offeringSection);
            details.addEventListener('toggle',()=>{
                if(details.open)loadOfferings(Number(c.id),offeringMount);
            });
            details.addEventListener('toggle',()=>{
                if(!details.open && activeQualificationContractor===Number(c.id)){
                    activeQualificationContractor=null;
                    ++qualificationRequestSequence;
                    document.getElementById('qualificationPanel').style.display='none';
                }
            });
            list.append(details);
        });
        if(!contractors.length){const p=document.createElement('p');p.textContent='No contractors found.';list.append(p);}
    }
});

async function load(){
    const d = await api('/api/contractors.php');
    contractorPager.setRecords(d.contractors);
}

async function loadOfferings(contractorId,mount){
    mount.textContent='Loading service offerings…';
    try{
        const response=await api('/api/contractor_service_offerings.php?contractor_id='+encodeURIComponent(contractorId));
        if(!mount.isConnected)return;
        mount.replaceChildren();
        for(const offering of response.offerings){
            const form=document.createElement('form');
            form.className='brite-directory-fields';
            const name=document.createElement('strong');
            name.textContent=offering.service_name+(Number(offering.service_active)?'':' (inactive)');
            const offered=document.createElement('label');
            const check=document.createElement('input');check.type='checkbox';check.checked=Number(offering.offered)===1;
            offered.append(check,document.createTextNode(' Offers service'));
            const pricing=document.createElement('select');
            for(const model of ['flat','hourly']){
                const opt=document.createElement('option');opt.value=model;opt.textContent=model==='flat'?'Flat rate':'Hourly rate';
                pricing.append(opt);
            }
            pricing.value=offering.pricing_model;
            const rate=document.createElement('input');rate.type='number';rate.min='0';rate.max='9999999999';rate.step='0.01';
            rate.placeholder='Rate (optional)';rate.value=offering.rate??'';
            const notes=document.createElement('input');notes.maxLength=500;notes.placeholder='Offering notes';
            notes.value=offering.notes??'';
            const save=document.createElement('button');save.type='submit';save.textContent='Save offering';
            const status=document.createElement('span');status.setAttribute('role','status');
            if(!window.BRITE_QUALIFICATION_CAN_EDIT){
                for(const control of [check,pricing,rate,notes,save])control.disabled=true;
            }
            form.append(name,offered,pricing,rate,notes,save,status);
            form.addEventListener('submit',async event=>{
                event.preventDefault();save.disabled=true;status.textContent='Saving…';
                try{
                    await api('/api/contractor_service_offerings.php',{
                        method:'PUT',headers:{'Content-Type':'application/json'},
                        body:JSON.stringify({
                            contractor_id:contractorId,service_id:Number(offering.service_id),
                            offered:check.checked?1:0,pricing_model:pricing.value,rate:rate.value,notes:notes.value
                        })
                    });
                    status.textContent='Offering saved';
                }catch(error){status.textContent='Save failed: '+error.message;}
                finally{save.disabled=false;}
            });
            mount.append(form);
        }
        if(!response.offerings.length)mount.textContent='No tenant services available.';
    }catch(error){mount.textContent='Unable to load offerings: '+error.message;}
}

let activeQualificationContractor = null;

document.getElementById('rows').addEventListener('click', async e => {
    const button=e.target.closest('[data-qualification-contractor]');
    if(!button)return;
    const contractorId=Number(button.dataset.qualificationContractor);
    const record=button.closest('[data-contractor-id]');
    if(!record)return;
    const mount=record.querySelector('[data-qualification-mount]');
    const panel=document.getElementById('qualificationPanel');
    mount.append(panel);
    panel.style.display='';
    activeQualificationContractor=contractorId;
    document.getElementById('qualificationContractor').textContent=
        record.querySelector('summary strong')?.textContent||'Contractor';
    qualificationPager.setRecords([],true);
    await loadQualifications(contractorId);
});

const qualificationPager = new BritePagination({
    container: document.getElementById('qualificationPagination'),
    render: renderQualificationRows
});

function renderQualificationRows(qualifications){
    const tbody = document.getElementById('qualificationRows');

    if (!qualifications.length) {
        tbody.innerHTML =
            '<tr><td colspan="5" class="muted">No service qualifications found.</td></tr>';
        return;
    }

        tbody.innerHTML = qualifications.map(q => {
            const status = q.verification_status;
            const editable = window.BRITE_QUALIFICATION_CAN_EDIT;

            return `
                <tr>
                    <td>${esc(q.service_name)}</td>
                    <td>${Number(q.service_active)
                        ? 'Active' : 'Inactive'}</td>
                    <td>${esc(status)}</td>
                    <td>${esc(q.verified_at || '—')}</td>
                    <td>${editable ? `
                        <select data-service-id="${Number(q.service_id)}"
                                aria-label="Qualification for ${esc(q.service_name)}">
                            ${['unverified','verified','rejected']
                                .map(v => `
                                    <option value="${v}"
                                        ${v === status ? 'selected' : ''}>
                                        ${v}
                                    </option>
                                `).join('')}
                        </select>
                        <button type="button"
                            data-save-service="${Number(q.service_id)}">
                            Save
                        </button>
                    ` : 'Read only'}</td>
                </tr>
            `;
        }).join('');
}

let qualificationRequestSequence = 0;

async function loadQualifications(contractorId){
    const tbody = document.getElementById('qualificationRows');
    const message = document.getElementById('qualificationMessage');
    const request = ++qualificationRequestSequence;

    message.textContent = 'Loading qualifications...';
    tbody.replaceChildren();

    try {
        const d = await api(
            '/api/contractor_service_qualifications.php' +
            '?contractor_id=' + encodeURIComponent(contractorId)
        );

        if (
            request !== qualificationRequestSequence ||
            activeQualificationContractor !== contractorId
        ) return;

        const qualifications = Array.isArray(d.qualifications)
            ? d.qualifications : [];

        qualificationPager.setRecords(qualifications);
        message.textContent =
            qualifications.length + ' service qualifications loaded.';
    } catch (err) {
        if (
            request === qualificationRequestSequence &&
            activeQualificationContractor === contractorId
        ) {
            message.textContent = 'Unable to load: ' + err.message;
        }
    }
}

document.getElementById('qualificationRows')
    .addEventListener('click', async e => {
        const button = e.target.closest('[data-save-service]');
        if (!button || !window.BRITE_QUALIFICATION_CAN_EDIT) return;

        const contractorId = activeQualificationContractor;
        const serviceId = Number(button.dataset.saveService);
        const row = button.closest('tr');
        const select = row.querySelector('[data-service-id]');
        const status = select.value;

        button.disabled = true;

        const message = document.getElementById('qualificationMessage');

        try {
            await api('/api/contractor_service_qualifications.php', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    contractor_id: contractorId,
                    service_id: serviceId,
                    verification_status: status
                })
            });

            if (activeQualificationContractor === contractorId) {
                await loadQualifications(contractorId);
                message.textContent =
                    'Qualification updated successfully.';
            }
        } catch (err) {
            message.textContent = 'Update failed: ' + err.message;
            button.disabled = false;
        }
    });

f.onsubmit=async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(f));await api('/api/contractors.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});f.reset();load()};load();
</script><link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-single-active-records.css'),ENT_QUOTES,'UTF-8')?>">
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-owner-single-active.js'),ENT_QUOTES,'UTF-8')?>" defer></script>
<?php require __DIR__.'/partials/app_footer.php';?>
