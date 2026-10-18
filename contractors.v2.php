<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler');
$qualificationCanEdit = in_array((string)Auth::tenant()['role'], ['owner','admin'], true);
 $pageTitle='Contractors';require __DIR__.'/partials/app_header.php';?>
<h1>Contractors</h1><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="business_name" placeholder="Business"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><input name="hourly_rate" type="number" step=".01" placeholder="Hourly rate"><button>Add contractor</button></div></form></div>
<div class="card" style="margin-top:18px"><table><thead><tr><th>Name</th><th>Business</th><th>Email</th><th>Phone</th><th>Rate</th><th>Qualifications</th></tr></thead><tbody id="rows"></tbody></table><div id="contractorPagination"></div></div>

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
        document.getElementById('rows').innerHTML =
            contractors.length
                ? contractors.map(c => `
            <tr>
                <td>${esc(c.first_name)} ${esc(c.last_name)}</td>
                <td>${esc(c.business_name)}</td>
                <td>${esc(c.email)}</td>
                <td>${esc(c.phone)}</td>
                <td>${c.hourly_rate
                    ? '$' + Number(c.hourly_rate).toFixed(2)
                    : ''}</td>
                <td>
                    <button type="button"
                        data-qualification-contractor="${Number(c.id)}">
                        Manage Qualifications
                    </button>
                </td>
            </tr>
        `).join('')
                : '<tr><td colspan="6">No contractors found.</td></tr>';
    }
});

async function load(){
    const d = await api('/api/contractors.php');
    contractorPager.setRecords(d.contractors);
}

let activeQualificationContractor = null;

document.getElementById('rows').addEventListener('click', async e => {
    const button = e.target.closest(
        '[data-qualification-contractor]'
    );
    if (!button) return;

    const contractorId = Number(
        button.dataset.qualificationContractor
    );

    const name = button.closest('tr')
        .querySelector('td').textContent.trim();

    activeQualificationContractor = contractorId;

    const panel = document.getElementById('qualificationPanel');
    panel.style.display = '';

    document.getElementById('qualificationContractor')
        .textContent = name;

    qualificationPager.setRecords([], true);
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
</script><?php require __DIR__.'/partials/app_footer.php';?>
