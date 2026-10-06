<?php
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

    appointmentId.value='';
    appointmentStatus.value='scheduled';

    formTitle.textContent='New Appointment';
    saveButton.textContent='Create Appointment';
    deleteButton.style.display='none';
    appointmentError.textContent='';
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

function editAppointment(event){

    const a=event.extendedProps;

    appointmentId.value=event.id;

    clientId.value=a.client_id || '';
    serviceId.value=a.service_id || '';

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
                },

                eventClick:info=>{
                    editAppointment(info.event);
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
