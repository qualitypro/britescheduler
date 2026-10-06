<?php
$pageTitle='Contractor Availability';
require __DIR__.'/partials/app_header.php';
?>

<style>
.availability-layout{
    display:grid;
    grid-template-columns:340px minmax(0,1fr);
    gap:18px;
}
.availability-form label{
    display:block;
    font-weight:600;
    font-size:13px;
    margin-top:10px;
}
.availability-form input,
.availability-form select{
    width:100%;
    box-sizing:border-box;
    margin-top:4px;
}
.rule-available{
    font-weight:600;
}
.rule-unavailable{
    font-weight:600;
}
@media(max-width:900px){
    .availability-layout{
        grid-template-columns:1fr;
    }
}
</style>

<h1>Contractor Availability</h1>

<div class="availability-layout">

<div class="card">

    <h2>Add Availability Rule</h2>

    <form id="availabilityForm"
          class="availability-form">

        <label>Contractor</label>
        <select id="contractorId"
                name="contractor_id"
                required>
            <option value="">Select contractor</option>
        </select>

        <label>Rule Type</label>
        <select id="ruleType">
            <option value="weekly">
                Weekly recurring hours
            </option>
            <option value="date">
                Specific date
            </option>
        </select>

        <div id="weekdayContainer">
            <label>Weekday</label>
            <select id="weekday"
                    name="weekday">
                <option value="0">Sunday</option>
                <option value="1">Monday</option>
                <option value="2">Tuesday</option>
                <option value="3">Wednesday</option>
                <option value="4">Thursday</option>
                <option value="5">Friday</option>
                <option value="6">Saturday</option>
            </select>
        </div>

        <div id="dateContainer"
             style="display:none">
            <label>Specific Date</label>
            <input id="availableDate"
                   type="date"
                   name="available_date">
        </div>

        <label>Start</label>
        <input id="startsAt"
               name="starts_at"
               type="time"
               value="08:00"
               required>

        <label>End</label>
        <input id="endsAt"
               name="ends_at"
               type="time"
               value="17:00"
               required>

        <label>Status</label>
        <select id="isAvailable"
                name="is_available">
            <option value="1">Available</option>
            <option value="">
                Unavailable / Time Off
            </option>
        </select>

        <div style="margin-top:15px">
            <button type="submit">
                Add Rule
            </button>
        </div>

        <p id="availabilityError"
           class="danger"></p>

    </form>

</div>

<div class="card">

    <h2>Availability Rules</h2>

    <p class="muted">
        Specific-date rules will later override
        recurring weekly hours.
    </p>

    <table>
        <thead>
        <tr>
            <th>When</th>
            <th>Hours</th>
            <th>Status</th>
            <th></th>
        </tr>
        </thead>

        <tbody id="availabilityRows">
        </tbody>
    </table>

</div>

</div>

<script>

const contractorId =
    document.getElementById('contractorId');

const availabilityForm =
    document.getElementById('availabilityForm');

const ruleType =
    document.getElementById('ruleType');

const weekday =
    document.getElementById('weekday');

const availableDate =
    document.getElementById('availableDate');

const weekdayContainer =
    document.getElementById('weekdayContainer');

const dateContainer =
    document.getElementById('dateContainer');

const startsAt =
    document.getElementById('startsAt');

const endsAt =
    document.getElementById('endsAt');

const isAvailable =
    document.getElementById('isAvailable');

const availabilityRows =
    document.getElementById('availabilityRows');

const availabilityError =
    document.getElementById('availabilityError');

const weekdays=[
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday'
];

function esc(s){
    return String(s ?? '').replace(
        /[&<>"']/g,
        x=>({
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            '"':'&quot;',
            "'":'&#039;'
        }[x])
    );
}

function shortTime(value){

    if(!value) return '';

    const parts=value.split(':');

    let hour=Number(parts[0]);
    const minute=parts[1];

    const suffix=hour >= 12 ? 'PM' : 'AM';

    hour=hour % 12;

    if(hour===0) hour=12;

    return `${hour}:${minute} ${suffix}`;
}

async function loadContractors(){

    const d=await api('/api/contractors.php');

    contractorId.innerHTML=
        '<option value="">Select contractor</option>'+
        d.contractors.map(c=>
            `<option value="${c.id}">
                ${esc(c.first_name)} ${esc(c.last_name)}
            </option>`
        ).join('');
}

async function loadAvailability(){

    availabilityRows.innerHTML='';

    if(!contractorId.value){
        return;
    }

    const d=await api(
        '/api/contractor_availability.php?contractor_id='+
        encodeURIComponent(contractorId.value)
    );

    if(!d.availability.length){

        availabilityRows.innerHTML=`
            <tr>
                <td colspan="4"
                    class="muted">
                    No availability rules configured.
                </td>
            </tr>
        `;

        return;
    }

    availabilityRows.innerHTML=
        d.availability.map(r=>{

            const when=r.available_date
                ? esc(r.available_date)
                : weekdays[Number(r.weekday)];

            const status=Number(r.is_available)
                ? 'Available'
                : 'Unavailable';

            const css=Number(r.is_available)
                ? 'rule-available'
                : 'rule-unavailable';

            return `
                <tr>
                    <td>${when}</td>

                    <td>
                        ${shortTime(r.starts_at)}
                        –
                        ${shortTime(r.ends_at)}
                    </td>

                    <td class="${css}">
                        ${status}
                    </td>

                    <td>
                        <button
                            type="button"
                            onclick="deleteRule(${Number(r.id)})">
                            Delete
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
}

ruleType.onchange=()=>{

    const specific=
        ruleType.value==='date';

    weekdayContainer.style.display=
        specific ? 'none' : '';

    dateContainer.style.display=
        specific ? '' : 'none';

    weekday.disabled=specific;
    availableDate.disabled=!specific;

    if(specific){
        weekday.value='1';
    }else{
        availableDate.value='';
    }
};

contractorId.onchange=loadAvailability;

availabilityForm.onsubmit=async e=>{

    e.preventDefault();

    availabilityError.textContent='';

    try{

        if(!contractorId.value){
            throw new Error(
                'Select a contractor'
            );
        }

        const specific=
            ruleType.value==='date';

        if(specific && !availableDate.value){
            throw new Error(
                'Choose a specific date'
            );
        }

        const payload={
            contractor_id:
                Number(contractorId.value),

            weekday:
                specific
                    ? null
                    : Number(weekday.value),

            available_date:
                specific
                    ? availableDate.value
                    : null,

            starts_at:
                startsAt.value,

            ends_at:
                endsAt.value,

            is_available:
                isAvailable.value==='1'
        };

        await api(
            '/api/contractor_availability.php',
            {
                method:'POST',
                headers:{
                    'Content-Type':
                        'application/json'
                },
                body:JSON.stringify(payload)
            }
        );

        await loadAvailability();

    }catch(e){

        availabilityError.textContent=
            e.message;
    }
};

async function deleteRule(id){

    if(!confirm(
        'Delete this availability rule?'
    )){
        return;
    }

    try{

        await api(
            '/api/contractor_availability.php',
            {
                method:'DELETE',
                headers:{
                    'Content-Type':
                        'application/json'
                },
                body:JSON.stringify({id})
            }
        );

        await loadAvailability();

    }catch(e){

        availabilityError.textContent=
            e.message;
    }
}

document.addEventListener(
    'DOMContentLoaded',
    async ()=>{

        try{

            ruleType.dispatchEvent(
                new Event('change')
            );

            await loadContractors();

        }catch(e){

            availabilityError.textContent=
                e.message;
        }
    }
);

</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
