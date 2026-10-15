<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler');

$pageTitle='Schedule';
require __DIR__.'/partials/app_header.php';
?>

<script src="<?=htmlspecialchars(app_url('/events/dist/index.global.min.js'))?>"></script>

<style>
.scheduler-layout{
    display:grid;
    grid-template-columns:320px minmax(0,1fr);
    gap:18px;
}
.scheduler-form label{
    display:block;
    font-size:13px;
    font-weight:600;
    margin-top:10px;
}
.scheduler-form input,
.scheduler-form select,
.scheduler-form textarea{
    width:100%;
    box-sizing:border-box;
    margin-top:4px;
}
.scheduler-form textarea{
    min-height:70px;
}
#calendar{
    min-height:700px;
}

.contractor-recommendations{
    margin-top:16px;
    padding:14px;
    border:1px solid #cbd5e1;
    border-radius:8px;
}

.contractor-recommendations h3{
    margin:0 0 8px;
    font-size:16px;
}

.recommendations-help{
    font-size:12px;
    line-height:1.5;
    opacity:.8;
}

#loadRecommendations{
    margin-top:10px;
}

#recommendationsStatus{
    font-size:13px;
    margin:12px 0;
}

#recommendationsResults{
    display:grid;
    gap:10px;
}

.recommendation-card{
    padding:12px;
    border:1px solid #cbd5e1;
    border-radius:8px;
}

.recommendation-score{
    font-weight:700;
    font-size:18px;
}

.recommendation-details{
    font-size:12px;
    line-height:1.6;
}
@media(max-width:900px){
    .scheduler-layout{
        grid-template-columns:1fr;
    }
}
</style>

<h1>Schedule</h1>

<div class="scheduler-layout">

<div class="card">
    <h2 id="formTitle">New Appointment</h2>

    <form id="appointmentForm" class="scheduler-form">

        <input type="hidden" name="id" id="appointmentId">

        <label>Client</label>
        <select name="client_id" id="clientId">
            <option value="">No client</option>
        </select>

        <label>Service</label>
        <select name="service_id" id="serviceId">
            <option value="">No service</option>
        </select>

        <label>Contractor</label>
        <select id="contractorId">
            <option value="">Unassigned</option>
        </select>

        <!-- Advisory contractor recommendations; no automatic assignment. -->
        <section id="contractorRecommendations"
                 class="contractor-recommendations"
                 aria-labelledby="recommendationsTitle">

            <h3 id="recommendationsTitle">
                Contractor Recommendations
            </h3>

            <p class="recommendations-help">
                Review geographic coverage, availability,
                qualifications, and workload before selecting
                a contractor.
            </p>

            <button type="button"
                    id="loadRecommendations"
                    disabled>
                Find Recommended Contractors
            </button>

            <p id="recommendationsStatus"
               role="status"
               aria-live="polite">
                Select a saved client location and appointment
                times to view recommendations.
            </p>

            <div id="recommendationsResults"></div>

            <p class="recommendations-help">
                Recommendations are advisory.
                Selecting a contractor does not assign them
                until the appointment is saved.
            </p>
        </section>

        <label>Title</label>
        <input name="title"
               id="appointmentTitle"
               required
               placeholder="Appointment">

        <label>Start</label>
        <input name="starts_at"
               id="startsAt"
               type="datetime-local"
               required>

        <label>End</label>
        <input name="ends_at"
               id="endsAt"
               type="datetime-local"
               required>

        <label>Status</label>
        <select name="status" id="appointmentStatus">
            <option value="tentative">Tentative</option>
            <option value="scheduled" selected>Scheduled</option>
            <option value="confirmed">Confirmed</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No Show</option>
        </select>

        <label>Saved Client Location</label>
        <select name="client_location_id" id="clientLocationId">
            <option value="">Manual / Other Location</option>
        </select>

        <label>Location</label>
        <input name="location"
               id="appointmentLocation"
               placeholder="Location">

        <label>Description</label>
        <textarea name="description"
                  id="appointmentDescription"
                  placeholder="Notes"></textarea>

        <div style="margin-top:15px">
            <button type="submit" id="saveButton">Create Appointment</button>
            <button type="button" id="newButton">Clear</button>
            <button type="button" id="deleteButton"
                    style="display:none">Delete</button>
<button type="button"
        id="invoiceButton"
        style="display:none">
    Generate Invoice
</button>
        </div>

        <p id="appointmentError" class="danger"></p>
    </form>
</div>

<div class="card">
    <div id="calendar"></div>
</div>

</div>

<script>
let calendar;
let services=[];

const appointmentForm = document.getElementById('appointmentForm');
const appointmentId = document.getElementById('appointmentId');
const invoiceButton = document.getElementById('invoiceButton');
const clientId = document.getElementById('clientId');
const serviceId = document.getElementById('serviceId');
const contractorId = document.getElementById('contractorId');
const appointmentTitle = document.getElementById('appointmentTitle');
const startsAt = document.getElementById('startsAt');
const endsAt = document.getElementById('endsAt');
const appointmentStatus = document.getElementById('appointmentStatus');
const appointmentLocation = document.getElementById('appointmentLocation');
const clientLocationId = document.getElementById('clientLocationId');
let locationLoadSequence = 0;

// Advisory recommendations never save or assign contractors.
const recommendationsButton =
    document.getElementById('loadRecommendations');
const recommendationsStatus =
    document.getElementById('recommendationsStatus');
const recommendationsResults =
    document.getElementById('recommendationsResults');

let recommendationSequence = 0;
let recommendationSignature = '';

function recommendationInputs(){
    const location = clientLocationId.value;
    const start = sqlDateTime(startsAt.value);
    const end = sqlDateTime(endsAt.value);

    if(!location || !start || !end || end <= start){
        return null;
    }

    return {
        location_id: location,
        starts_at: start,
        ends_at: end,
        service_id: serviceId.value,
        exclude_appointment_id: appointmentId.value
    };
}

function recommendationKey(inputs){
    return JSON.stringify(inputs);
}

function invalidateRecommendations(){
    ++recommendationSequence;
    recommendationSignature = '';
    recommendationsResults.replaceChildren();

    const inputs = recommendationInputs();
    recommendationsButton.disabled = !inputs;

    recommendationsStatus.textContent = inputs
        ? 'Ready to find recommended contractors.'
        : 'Select a saved client location and valid appointment times.';
}

function recommendationText(parent, className, content){
    const element = document.createElement('div');
    element.className = className;
    element.textContent = content;
    parent.appendChild(element);
    return element;
}

function renderRecommendations(contractors){
    recommendationsResults.replaceChildren();

    if(!contractors.length){
        recommendationsStatus.textContent =
            'No contractors match this location and time. ' +
            'Review geographic coverage and availability.';
        return;
    }

    recommendationsStatus.textContent =
        contractors.length + ' contractor recommendation(s) found.';

    for(const contractor of contractors){
        const ranking = contractor.ranking || {};
        const workload = ranking.workload || {};
        const card = document.createElement('article');
        card.className = 'recommendation-card';

        recommendationText(
            card,
            '',
            [
                contractor.first_name,
                contractor.last_name
            ].filter(Boolean).join(' ')
        );

        recommendationText(
            card,
            'recommendation-score',
            Number(ranking.score || 0).toFixed(2) + ' / 100'
        );

        const details = [
            'Qualification: ' +
                (contractor.qualification_status || 'unverified'),
            'Availability: ' +
                (contractor.availability_ok && contractor.conflict_free
                    ? 'Available'
                    : 'Restricted'),
            'Geographic coverage: ' +
                (contractor.geographic_match ? 'Matched' : 'Not matched'),
            'Workload: ' + Number(workload.hours || 0) +
                ' hours / ' + Number(workload.jobs || 0) + ' jobs'
        ];

        recommendationText(
            card,
            'recommendation-details',
            details.join(' | ')
        );

        for(const explanation of ranking.explanations || []){
            recommendationText(
                card,
                'recommendation-details',
                explanation
            );
        }

        const select = document.createElement('button');
        select.type = 'button';
        select.textContent = 'Select Contractor';

        const eligible = Boolean(contractor.eligible_for_review);
        select.disabled = !eligible;

        if(!eligible){
            select.title = 'Contractor is not eligible for review';
        }

        select.addEventListener('click', () => {
            const inputs = recommendationInputs();

            if(!inputs ||
               recommendationKey(inputs) !== recommendationSignature){
                invalidateRecommendations();
                return;
            }

            const id = String(contractor.contractor_id);

            if(!Array.from(contractorId.options)
                .some(option => option.value === id)){
                recommendationsStatus.textContent =
                    'Contractor is no longer in the selection list.';
                return;
            }

            contractorId.value = id;

            recommendationsStatus.textContent =
                'Contractor selected. Save the appointment ' +
                'to submit the assignment.';
        });

        card.appendChild(select);
        recommendationsResults.appendChild(card);
    }
}

recommendationsButton.addEventListener('click', async () => {
    const inputs = recommendationInputs();

    if(!inputs){
        invalidateRecommendations();
        return;
    }

    const sequence = ++recommendationSequence;
    const signature = recommendationKey(inputs);

    recommendationSignature = '';
    recommendationsResults.replaceChildren();
    recommendationsButton.disabled = true;
    recommendationsStatus.textContent = 'Loading recommendations...';

    const query = new URLSearchParams({
        location_id: inputs.location_id,
        starts_at: inputs.starts_at,
        ends_at: inputs.ends_at
    });

    if(inputs.service_id){
        query.set('service_id', inputs.service_id);
    }

    if(inputs.exclude_appointment_id){
        query.set(
            'exclude_appointment_id',
            inputs.exclude_appointment_id
        );
    }

    try{
        const data = await api(
            '/api/contractor_recommendations.php?' + query.toString()
        );

        if(sequence !== recommendationSequence ||
           recommendationKey(recommendationInputs()) !== signature){
            return;
        }

        recommendationSignature = signature;
        renderRecommendations(data.contractors || []);

    }catch(error){
        if(sequence === recommendationSequence){
            recommendationSignature = '';
            recommendationsResults.replaceChildren();
            recommendationsStatus.textContent =
                'Unable to load recommendations: ' + error.message;
        }
    }finally{
        if(sequence === recommendationSequence){
            recommendationsButton.disabled = !recommendationInputs();
        }
    }
});

for(const element of [
    clientLocationId,
    startsAt,
    endsAt,
    serviceId,
    appointmentId
]){
    element.addEventListener('change', invalidateRecommendations);
    element.addEventListener('input', invalidateRecommendations);
}

async function loadClientLocations(selectedId='', usePrimary=true){
    const sequence = ++locationLoadSequence;
    const selectedClient = clientId.value;

    clientLocationId.innerHTML =
        '<option value="">Manual / Other Location</option>';
    invalidateRecommendations();

    if(!selectedClient) return;

    const data = await api(
        '/api/client_locations.php?client_id=' +
        encodeURIComponent(selectedClient)
    );

    if(sequence !== locationLoadSequence ||
       clientId.value !== selectedClient) return;

    clientLocationId.innerHTML =
        '<option value="">Manual / Other Location</option>' +
        data.locations.map(l =>
            `<option value="${Number(l.id)}">` +
            `${esc(l.label)} — ${esc(l.address1)}, ` +
            `${esc(l.city)}, ${esc(l.state)} ` +
            `${esc(l.postal_code)}</option>`
        ).join('');

    if(selectedId){
        clientLocationId.value = String(selectedId);
    } else if(usePrimary) {
        const primary = data.locations.find(l => Number(l.is_primary) === 1);
        if(primary) clientLocationId.value = String(primary.id);
    }

    invalidateRecommendations();
}

clientId.onchange = async () => {
    invalidateRecommendations();
    clientLocationId.innerHTML =
        '<option value="">Manual / Other Location</option>';
    appointmentLocation.value = '';
    try {
        await loadClientLocations();
    } catch(e) {
        appointmentError.textContent = e.message;
    }
};

const appointmentDescription = document.getElementById('appointmentDescription');
const appointmentError = document.getElementById('appointmentError');
const formTitle = document.getElementById('formTitle');
const saveButton = document.getElementById('saveButton');
const newButton = document.getElementById('newButton');
const deleteButton = document.getElementById('deleteButton');

function esc(s){
    return String(s ?? '').replace(/[&<>"']/g,x=>({
        '&':'&amp;',
        '<':'&lt;',
        '>':'&gt;',
        '"':'&quot;',
        "'":'&#039;'
    }[x]));
}

function localDateTime(value){
    if(!value) return '';

    const d=new Date(value);

    const pad=n=>String(n).padStart(2,'0');

    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function sqlDateTime(value){
    if(!value) return null;
    return value.replace('T',' ') + ':00';
}

async function loadLookups(){

    const [clientsData,contractorsData,servicesData]=await Promise.all([
        api('/api/clients.php'),
        api('/api/contractors.php'),
        api('/api/services.php')
    ]);

    clientId.innerHTML=
        '<option value="">No client</option>'+
        clientsData.clients.map(c=>
            `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name)}</option>`
        ).join('');

    contractorId.innerHTML=
        '<option value="">Unassigned</option>'+
        contractorsData.contractors.map(c=>
            `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name)}</option>`
        ).join('');

    services=servicesData.services.filter(s=>Number(s.active));

    serviceId.innerHTML=
        '<option value="">No service</option>'+
        services.map(s=>
            `<option value="${s.id}">${esc(s.name)} — ${Number(s.duration_minutes)} min</option>`
        ).join('');
}

function resetForm(){
    invoiceButton.style.display = 'none';


    appointmentForm.reset();
    ++locationLoadSequence;
    clientLocationId.innerHTML =
        '<option value="">Manual / Other Location</option>';

    appointmentId.value='';
    appointmentStatus.value='scheduled';

    formTitle.textContent='New Appointment';
    saveButton.textContent='Create Appointment';
    deleteButton.style.display='none';
    appointmentError.textContent='';
    invalidateRecommendations();
}

serviceId.onchange=()=>{

    const service=services.find(
        s=>String(s.id)===String(serviceId.value)
    );

    if(!service || !startsAt.value) return;

    const start=new Date(startsAt.value);
    start.setMinutes(start.getMinutes()+Number(service.duration_minutes));

    endsAt.value=localDateTime(start);
};

async function loadEvents(info,success,failure){

    try {

        const d=await api(
            '/api/appointments.php?start='+
            encodeURIComponent(info.startStr)+
            '&end='+
            encodeURIComponent(info.endStr)
        );

        success(d.appointments.map(a=>({

            id:String(a.id),

            title:
                (a.client_first
                    ? `${a.client_first} ${a.client_last} — `
                    : '') +
                (a.service_name || a.title),

            start:a.starts_at,
            end:a.ends_at,

            backgroundColor:
                a.contractor_colors
                    ? a.contractor_colors.split(',')[0]
                    : undefined,

            borderColor:
                a.contractor_colors
                    ? a.contractor_colors.split(',')[0]
                    : undefined,

            extendedProps:a

        })));

    } catch(e){
        failure(e);
    }
}

async function persistCalendarMove(info){

    const event=info.event;
    const a=event.extendedProps;

    try{

        const contractorIds=String(a.contractor_ids || '')
            .split(',')
            .filter(Boolean)
            .map(Number);

        await api('/api/appointments.php',{
            method:'PUT',
            headers:{
                'Content-Type':'application/json'
            },
            body:JSON.stringify({
                id:Number(event.id),
                client_id:a.client_id || null,
                client_location_id:a.client_location_id || null,
                service_id:a.service_id || null,
                title:a.title || 'Appointment',
                description:a.description || null,
                starts_at:sqlDateTime(
                    localDateTime(event.start)
                ),
                ends_at:sqlDateTime(
                    localDateTime(event.end)
                ),
                status:a.status || 'scheduled',
                location:a.location || null,
                is_public:Number(a.is_public || 0) === 1,
                contractor_ids:contractorIds
            })
        });

        calendar.refetchEvents();

    }catch(e){

        info.revert();

        alert(e.message);
    }
}

async function editAppointment(event){

    const a=event.extendedProps;

    appointmentId.value=event.id;

    clientId.value=a.client_id || '';
    serviceId.value=a.service_id || '';

    try {
        await loadClientLocations(a.client_location_id || '', false);
    } catch(e) {
        appointmentError.textContent = e.message;
    }

    const contractors=String(a.contractor_ids || '')
        .split(',')
        .filter(Boolean);

    contractorId.value=contractors[0] || '';

    appointmentTitle.value=a.title || 'Appointment';
    startsAt.value=localDateTime(a.starts_at);
    endsAt.value=localDateTime(a.ends_at);
    appointmentStatus.value=a.status || 'scheduled';
    appointmentLocation.value=a.location || '';
    appointmentDescription.value=a.description || '';

    formTitle.textContent='Edit Appointment';
    saveButton.textContent='Save Changes';
    deleteButton.style.display='inline-block';
    invoiceButton.style.display =
        a.status === 'completed'
            ? 'inline-block'
            : 'none';


    invalidateRecommendations();
    window.scrollTo({top:0,behavior:'smooth'});
}

appointmentForm.onsubmit=async e=>{

    e.preventDefault();
    appointmentError.textContent='';

    try {

        const d=Object.fromEntries(
            new FormData(appointmentForm)
        );

        // Explicitly capture relationship selections.
        d.client_id = clientId.value || null;
        d.client_location_id = clientLocationId.value || null;
        d.service_id = serviceId.value || null;

        d.contractor_ids =
            contractorId.value
                ? [Number(contractorId.value)]
                : [];

        d.starts_at=sqlDateTime(d.starts_at);
        d.ends_at=sqlDateTime(d.ends_at);

        const editing=Boolean(d.id);

        if(editing){
            d.id=Number(d.id);
        } else {
            delete d.id;
        }

        await api('/api/appointments.php',{
            method:editing ? 'PUT' : 'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(d)
        });

        resetForm();
        calendar.refetchEvents();

    } catch(e){
        appointmentError.textContent=e.message;
    }
};

newButton.onclick=resetForm;

deleteButton.onclick=async ()=>{

    if(!appointmentId.value) return;

    if(!confirm('Delete this appointment?')) return;

    try {

        await api('/api/appointments.php',{
            method:'DELETE',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                id:Number(appointmentId.value)
            })
        });

        resetForm();
        calendar.refetchEvents();

    } catch(e){
        appointmentError.textContent=e.message;
    }
};


invoiceButton.onclick = async () => {

    if (!appointmentId.value) {
        alert('Select an appointment first.');
        return;
    }

    if (!confirm(
        'Generate an invoice for this completed appointment?'
    )) {
        return;
    }

    if (typeof appointmentError !== 'undefined') {
        appointmentError.textContent = '';
    }

    try {

        const result = await api(
            '/api/invoices.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    appointment_id:
                        Number(appointmentId.value)
                })
            }
        );

        alert(
            `Invoice ${result.invoice_number} created for $${Number(result.total).toFixed(2)}`
        );

        window.location.href =
            window.BRITE_BASE + '/billing.php';

    } catch (e) {

        if (typeof appointmentError !== 'undefined') {
            appointmentError.textContent = e.message;
        } else {
            alert(e.message);
        }
    }
};

document.addEventListener('DOMContentLoaded',async ()=>{

    try {

        await loadLookups();

        calendar=new FullCalendar.Calendar(
            document.getElementById('calendar'),
            {
                initialView:'dayGridMonth',

                headerToolbar:{
                    left:'prev,next today',
                    center:'title',
                    right:'dayGridMonth,timeGridWeek,timeGridDay'
                },

                selectable:true,
                editable:true,
                eventDurationEditable:true,
                eventStartEditable:true,

                events:loadEvents,

                eventDrop:async info=>{
                    await persistCalendarMove(info);
                },

                eventResize:async info=>{
                    await persistCalendarMove(info);
                },

                select:info=>{
                    resetForm();

                    startsAt.value=localDateTime(info.start);

                    let end=new Date(info.start);
                    end.setHours(end.getHours()+1);

                    endsAt.value=localDateTime(end);
                    invalidateRecommendations();
                },

                eventClick:async info=>{
                    await editAppointment(info.event);
                }
            }
        );

        calendar.render();

    } catch(e){
        appointmentError.textContent=e.message;
    }
});
</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
