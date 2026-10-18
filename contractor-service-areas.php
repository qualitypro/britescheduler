<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

Auth::requireRole('owner', 'admin', 'scheduler', 'contractor');

$serviceAreaTenant = Auth::tenant();
$isContractorSelfService = (
    ($serviceAreaTenant['role'] ?? '') === 'contractor'
);

$pageTitle = $isContractorSelfService
    ? 'My Service Areas'
    : 'Contractor Service Areas';

require __DIR__ . '/partials/app_header.php';
?>

<link rel="stylesheet" href="/dashboard/assets/vendor/leaflet/leaflet.css">

<style>
[hidden] {
    display: none !important;
}

#sa-radius-map {
    width: 100%;
    height: 340px;
    min-height: 340px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    margin: 12px 0;
}
.sa-radius-fields {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}
.sa-radius-fields input {
    width: 100%;
    box-sizing: border-box;
}
@media(max-width: 650px) {
    .sa-radius-fields {
        grid-template-columns: 1fr;
    }
}

.sa-layout {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) minmax(0, 2fr);
    gap: 18px;
}
.sa-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 14px;
}
.sa-field input,
.sa-field select {
    width: 100%;
    box-sizing: border-box;
}
.sa-table-wrap {
    overflow-x: auto;
}
.sa-status {
    margin: 12px 0;
    min-height: 24px;
}
.sa-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.sa-btn {
    background: #172033;
    color: white;
    border: 0;
    padding: 10px 16px;
}
.sa-btn:disabled {
    opacity: .55;
    cursor: not-allowed;
}
.sa-pill {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 999px;
    background: #e2e8f0;
    font-size: 12px;
}
.sa-pill.active {
    background: #dcfce7;
    color: #166534;
}
@media(max-width: 800px) {
    .sa-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<h1><?= htmlspecialchars($pageTitle) ?></h1>

<p class="muted">
<?php if ($isContractorSelfService): ?>
    Manage the ZIP codes and geographic radius where you provide services.
    Your coverage areas are associated with your contractor profile.
<?php else: ?>
    Manage geographic coverage for contractors in your organization.
    ZIP-code coverage and radius-based coverage are supported.
<?php endif; ?>
</p>

<div class="card">
    <label class="sa-field" for="sa-contractor"<?= $isContractorSelfService ? ' hidden' : '' ?>>
        <strong>Select contractor</strong>
        <select id="sa-contractor">
            <option value="">Loading contractors...</option>
        </select>
    </label>
</div>

<div class="sa-layout" style="margin-top:18px">

    <section class="card">
        <h2 id="sa-form-title">Add ZIP Coverage</h2>

        <form id="sa-zip-form">

            <label class="sa-field">
                Coverage label
                <input
                    name="label"
                    maxlength="120"
                    placeholder="Example: Cocoa Service Area"
                    required
                >
            </label>

            <label class="sa-field">
                State
                <input
                    name="state_code"
                    maxlength="2"
                    minlength="2"
                    value="FL"
                    required
                >
            </label>

            <label class="sa-field">
                ZIP code
                <input
                    name="postal_code"
                    inputmode="numeric"
                    pattern="[0-9]{5}"
                    maxlength="5"
                    placeholder="32922"
                    required
                >
            </label>

            <label>
                <input type="checkbox" name="active" checked>
                Active coverage
            </label>

            <div style="margin-top:18px">
                <button class="sa-btn" type="submit" id="sa-save">
                    Add ZIP Coverage
                </button>
                <button type="button" id="sa-cancel" hidden>
                    Cancel Edit
                </button>
            </div>

        </form>
    </section>

    <section class="card">
        <h2>Configured Coverage Areas</h2>

        <div id="sa-status" class="sa-status muted" role="status" aria-live="polite">
            <?= $isContractorSelfService
                ? 'Loading your coverage areas...'
                : 'Select a contractor to view coverage.' ?>
        </div>

        <div class="sa-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Type</th>
                        <th>Coverage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sa-rows"></tbody>
            </table>
            <div id="sa-areas-pagination"></div>
        </div>
    </section>

</div>

<section class="card" style="margin-top:18px">
    <h2>Radius Coverage Map</h2>
    <p class="muted">
        Click the map to choose a coverage center, then adjust
        the radius in miles. The circle previews the coverage boundary.
    </p>

    <h3 id="sa-radius-title">Add Radius Coverage</h3>

    <form id="sa-radius-form">
        <label class="sa-field">
            Coverage label
            <input
                name="label"
                maxlength="120"
                placeholder="Example: Greater Cocoa Coverage"
                required
            >
        </label>

        <div class="sa-radius-fields">
            <label class="sa-field">
                Latitude
                <input
                    name="center_latitude"
                    type="number"
                    min="-90"
                    max="90"
                    step="any"
                    value="28.3861"
                    required
                >
            </label>

            <label class="sa-field">
                Longitude
                <input
                    name="center_longitude"
                    type="number"
                    min="-180"
                    max="180"
                    step="any"
                    value="-80.7419"
                    required
                >
            </label>

            <label class="sa-field">
                Radius (miles)
                <input
                    name="radius_miles"
                    type="number"
                    min="0.01"
                    max="500"
                    step="0.01"
                    value="10"
                    required
                >
            </label>
        </div>

        <label>
            <input type="checkbox" name="active" checked>
            Active coverage
        </label>

        <div id="sa-radius-map"
             aria-label="Interactive radius coverage map"></div>

        <div id="sa-radius-status"
             class="sa-status muted"
             role="status"
             aria-live="polite">
            Click the map to select a coverage center.
        </div>

        <button
            class="sa-btn"
            type="submit"
            id="sa-radius-save"
        >
            Add Radius Coverage
        </button>

        <button
            type="button"
            id="sa-radius-cancel"
            hidden
        >
            Cancel Edit
        </button>
    </form>
</section>


<?php if (!$isContractorSelfService): ?>
<section class="card" style="margin-top:18px">
    <h2>Find Matching Contractors</h2>
    <p class="muted">
        Select a client service location to find active contractors
        whose ZIP or radius coverage includes that location.
        This lookup does not assign appointments or change records.
    </p>

    <div class="sa-radius-fields">
        <label class="sa-field">
            Client
            <select id="sa-match-client">
                <option value="">Loading clients...</option>
            </select>
        </label>

        <label class="sa-field">
            Service location
            <select id="sa-match-location" disabled>
                <option value="">Select a client first</option>
            </select>
        </label>

        <div class="sa-field">
            <span>Coverage lookup</span>
            <button type="button"
                    class="sa-btn"
                    id="sa-match-run"
                    disabled>
                Find Matching Contractors
            </button>
        </div>
    </div>

    <p id="sa-match-diagnostics"
       class="muted"
       role="status"
       aria-live="polite">
        Select a client and service location.
    </p>

    <div class="sa-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Contractor</th>
                    <th>Matching coverage</th>
                    <th>Match details</th>
                </tr>
            </thead>
            <tbody id="sa-match-results">
                <tr>
                    <td colspan="3">
                        No matching search performed.
                    </td>
                </tr>
            </tbody>
        </table>
        <div id="sa-match-pagination"></div>
    </div>
</section>
<?php endif; ?>

<script src="/dashboard/assets/vendor/leaflet/leaflet.js"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-pagination.js'))?>"></script>
<script>
(() => {
    'use strict';

    const radiusForm = document.getElementById('sa-radius-form');
    const radiusStatus = document.getElementById('sa-radius-status');
    const radiusSave = document.getElementById('sa-radius-save');
    const radiusCancel = document.getElementById('sa-radius-cancel');
    const radiusTitle = document.getElementById('sa-radius-title');

    let editingRadius = null;

    let radiusMap = null;
    let radiusCircle = null;
    let radiusMarker = null;
    let savedRadiusLayer = null;

    function radiusValues() {
        const lat = Number(
            radiusForm.elements.namedItem('center_latitude').value
        );
        const lon = Number(
            radiusForm.elements.namedItem('center_longitude').value
        );
        const miles = Number(
            radiusForm.elements.namedItem('radius_miles').value
        );

        if (
            !radiusForm.elements.namedItem('center_latitude').value.trim() ||
            !radiusForm.elements.namedItem('center_longitude').value.trim() ||
            !radiusForm.elements.namedItem('radius_miles').value.trim() ||
            !Number.isFinite(lat) || lat < -90 || lat > 90 ||
            !Number.isFinite(lon) || lon < -180 || lon > 180 ||
            !Number.isFinite(miles) || miles < 0.01 || miles > 500
        ) {
            return null;
        }

        return {lat, lon, miles};
    }

    function updateRadiusPreview() {
        if (!radiusMap) return;

        const values = radiusValues();

        if (!values) {
            radiusStatus.textContent =
                'Enter valid coordinates and a radius from 0.01 to 500 miles.';
            return;
        }

        const center = [values.lat, values.lon];

        if (!radiusCircle) {
            radiusCircle = L.circle(center, {
                radius: values.miles * 1609.344,
                color: '#2563eb',
                fillOpacity: 0.12
            }).addTo(radiusMap);
        } else {
            radiusCircle.setLatLng(center);
            radiusCircle.setRadius(values.miles * 1609.344);
        }

        if (!radiusMarker) {
            radiusMarker = L.marker(center).addTo(radiusMap);
        } else {
            radiusMarker.setLatLng(center);
        }

        radiusStatus.textContent =
            `Preview: ${values.miles} miles around ` +
            `${values.lat.toFixed(5)}, ${values.lon.toFixed(5)}`;
    }

    function renderSavedRadiusAreas(areas) {
        if (!radiusMap || !savedRadiusLayer) return;

        savedRadiusLayer.clearLayers();

        for (const area of areas) {
            if (area.area_type !== 'radius') continue;

            const lat = Number(area.center_latitude);
            const lon = Number(area.center_longitude);
            const miles = Number(area.radius_miles);

            if (
                area.center_latitude === null ||
                area.center_longitude === null ||
                area.radius_miles === null ||
                !Number.isFinite(lat) ||
                !Number.isFinite(lon) ||
                !Number.isFinite(miles) ||
                lat < -90 || lat > 90 ||
                lon < -180 || lon > 180 ||
                miles < 0.01 || miles > 500
            ) {
                continue;
            }

            const active = Number(area.active) === 1;

            const circle = L.circle([lat, lon], {
                radius: miles * 1609.344,
                color: active ? '#15803d' : '#64748b',
                fillColor: active ? '#22c55e' : '#94a3b8',
                fillOpacity: active ? 0.10 : 0.04,
                weight: 2,
                dashArray: active ? null : '6 6',
                interactive: true
            });

            const popup = document.createElement('div');

            const title = document.createElement('strong');
            title.textContent = area.label || 'Radius Coverage';

            const details = document.createElement('div');
            details.textContent =
                `${miles} miles — ${active ? 'Active' : 'Inactive'}`;

            popup.appendChild(title);
            popup.appendChild(details);

            circle.bindPopup(popup);
            savedRadiusLayer.addLayer(circle);
        }
    }

    function initializeRadiusMap() {
        if (typeof L === 'undefined') {
            radiusStatus.textContent = 'Leaflet library unavailable.';
            return;
        }

        radiusMap = L.map('sa-radius-map').setView(
            [28.3861, -80.7419], 10
        );

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }
        ).addTo(radiusMap);

        savedRadiusLayer = L.layerGroup().addTo(radiusMap);

        radiusMap.on('click', event => {
            radiusForm.elements.namedItem('center_latitude').value =
                event.latlng.lat.toFixed(6);
            radiusForm.elements.namedItem('center_longitude').value =
                event.latlng.lng.toFixed(6);

            updateRadiusPreview();
        });

        for (const field of [
            'center_latitude',
            'center_longitude',
            'radius_miles'
        ]) {
            radiusForm.elements.namedItem(field).addEventListener(
                'input',
                updateRadiusPreview
            );
        }

        updateRadiusPreview();
    }

    function resetRadiusEditor() {
        editingRadius = null;
        radiusForm.reset();
        radiusTitle.textContent = 'Add Radius Coverage';
        radiusSave.textContent = 'Add Radius Coverage';
        radiusCancel.hidden = true;
        updateRadiusPreview();
    }

    function beginRadiusEdit(area) {
        if (mutationInProgress || area.area_type !== 'radius') return;

        resetEditor();
        editingRadius = area;

        radiusForm.elements.namedItem('label').value =
            area.label || '';
        radiusForm.elements.namedItem('center_latitude').value =
            area.center_latitude;
        radiusForm.elements.namedItem('center_longitude').value =
            area.center_longitude;
        radiusForm.elements.namedItem('radius_miles').value =
            area.radius_miles;
        radiusForm.elements.namedItem('active').checked =
            Number(area.active) === 1;

        radiusTitle.textContent = 'Edit Radius Coverage';
        radiusSave.textContent = 'Save Radius Changes';
        radiusCancel.hidden = false;

        updateRadiusPreview();

        const values = radiusValues();

        if (values && radiusMap) {
            radiusMap.setView([values.lat, values.lon], 10);
            radiusMap.invalidateSize();
        }

        radiusForm.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    radiusCancel.addEventListener('click', () => {
        if (mutationInProgress) return;
        resetRadiusEditor();
    });

    radiusForm.addEventListener('submit', async event => {
        event.preventDefault();

        if (mutationInProgress) return;

        const id = contractorId();

        if (id === null) {
            radiusStatus.textContent = 'Select a contractor first.';
            return;
        }

        const values = radiusValues();

        if (!values) {
            radiusStatus.textContent =
                'Enter valid coordinates and radius.';
            return;
        }

        const payload = {
            contractor_id: id,
            area_type: 'radius',
            label: radiusForm.elements.namedItem('label').value.trim(),
            center_latitude: values.lat,
            center_longitude: values.lon,
            radius_miles: values.miles,
            active: radiusForm.elements.namedItem('active').checked ? 1 : 0
        };

        if (editingRadius !== null) {
            payload.id = Number(editingRadius.id);
        }

        const method = editingRadius !== null ? 'PUT' : 'POST';

        setMutationBusy(true);
        radiusStatus.textContent = 'Saving radius coverage...';

        try {
            await api('/api/contractor_service_areas.php', {
                method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });

            resetRadiusEditor();
            await loadAreas();

            radiusStatus.textContent =
                'Radius coverage saved successfully.';

        } catch (error) {
            radiusStatus.textContent =
                error.message || 'Unable to save radius coverage.';
        } finally {
            setMutationBusy(false);
        }
    });

    initializeRadiusMap();

    const contractorSelect = document.getElementById('sa-contractor');
    const form = document.getElementById('sa-zip-form');
    const rows = document.getElementById('sa-rows');
    const status = document.getElementById('sa-status');
    const saveButton = document.getElementById('sa-save');
    const cancelButton = document.getElementById('sa-cancel');
    const formTitle = document.getElementById('sa-form-title');

    let requestSequence = 0;
    let editingArea = null;
    let mutationInProgress = false;

    function setMutationBusy(busy) {
        mutationInProgress = busy;
        contractorSelect.disabled = busy;
        saveButton.disabled = busy;
        cancelButton.disabled = busy;
        radiusSave.disabled = busy;
        radiusCancel.disabled = busy;

        radiusForm.querySelectorAll('input').forEach(input => {
            input.disabled = busy;
        });
        rows.querySelectorAll('button').forEach(button => {
            button.disabled = busy;
        });
    }

    function resetEditor() {
        editingArea = null;
        form.reset();
        formTitle.textContent = 'Add ZIP Coverage';
        saveButton.textContent = 'Add ZIP Coverage';
        cancelButton.hidden = true;
    }

    function beginEdit(area) {
        if (area.area_type !== 'zip') return;

        if (mutationInProgress) return;

        resetRadiusEditor();
        editingArea = area;
        form.elements.namedItem('label').value = area.label || '';
        form.elements.namedItem('state_code').value = area.state_code || '';
        form.elements.namedItem('postal_code').value = area.postal_code || '';
        form.elements.namedItem('active').checked = Number(area.active) === 1;

        formTitle.textContent = 'Edit ZIP Coverage';
        saveButton.textContent = 'Save Changes';
        cancelButton.hidden = false;
        form.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    cancelButton.addEventListener('click', resetEditor);

    function setStatus(message, error = false) {
        status.textContent = message;
        status.classList.toggle('danger', error);
    }

    function contractorId() {
        const id = Number(contractorSelect.value);
        return Number.isSafeInteger(id) && id > 0 ? id : null;
    }

    function cell(tr, value) {
        const td = document.createElement('td');
        td.textContent = String(value ?? '');
        tr.appendChild(td);
        return td;
    }

    const areaPager = new BritePagination({
        container: document.getElementById('sa-areas-pagination'),
        render: renderAreas
    });

    function renderAreas(areas) {
        rows.replaceChildren();

        if (!areas.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 5;
            td.textContent = 'No service areas configured.';
            tr.appendChild(td);
            rows.appendChild(tr);
            return;
        }

        for (const area of areas) {
            const tr = document.createElement('tr');

            cell(tr, area.label);
            cell(tr, area.area_type === 'zip' ? 'ZIP' : 'Radius');

            if (area.area_type === 'zip') {
                cell(tr, `${area.postal_code ?? ''}, ${area.state_code ?? ''}`);
            } else {
                cell(
                    tr,
                    `${area.radius_miles ?? ''} miles around ` +
                    `${area.center_latitude ?? ''}, ${area.center_longitude ?? ''}`
                );
            }

            const td = cell(tr, '');
            const pill = document.createElement('span');
            const active = Number(area.active) === 1;

            pill.className = 'sa-pill' + (active ? ' active' : '');
            pill.textContent = active ? 'Active' : 'Inactive';

            td.appendChild(pill);

            const actions = document.createElement('td');
            const controls = document.createElement('div');
            controls.className = 'sa-actions';

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.textContent = 'Edit';

            edit.addEventListener('click', () => {
                if (area.area_type === 'radius') {
                    beginRadiusEdit(area);
                } else {
                    beginEdit(area);
                }
            });

            controls.appendChild(edit);

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.textContent = active ? 'Deactivate' : 'Activate';

            toggle.addEventListener('click', async () => {
                if (mutationInProgress) return;

                if (!confirm(
                    `${active ? 'Deactivate' : 'Activate'} "${area.label}"?`
                )) return;

                setMutationBusy(true);

                const payload = {
                    id: Number(area.id),
                    contractor_id: contractorId(),
                    area_type: area.area_type,
                    label: area.label,
                    active: active ? 0 : 1
                };

                if (area.area_type === 'zip') {
                    payload.state_code = area.state_code;
                    payload.postal_code = area.postal_code;
                } else {
                    payload.center_latitude = area.center_latitude;
                    payload.center_longitude = area.center_longitude;
                    payload.radius_miles = area.radius_miles;
                }

                try {
                    await api('/api/contractor_service_areas.php', {
                        method: 'PUT',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(payload)
                    });

                    resetEditor();
                    resetRadiusEditor();
                    await loadAreas();
                } catch (error) {
                    setStatus(error.message || 'Unable to update coverage.', true);
                } finally {
                    setMutationBusy(false);
                }
            });

            controls.appendChild(toggle);
            actions.appendChild(controls);
            tr.appendChild(actions);
            rows.appendChild(tr);
        }
    }

    async function loadAreas() {
        const id = contractorId();
        const sequence = ++requestSequence;

        resetEditor();
        resetRadiusEditor();
        rows.replaceChildren();

        if (id === null) {
            areaPager.setRecords([], true);
        }

        if (savedRadiusLayer) {
            savedRadiusLayer.clearLayers();
        }

        if (id === null) {
            setStatus('Select a contractor to view coverage.');
            return;
        }

        setStatus('Loading service areas...');

        try {
            const response = await api(
                '/api/contractor_service_areas.php?contractor_id=' +
                encodeURIComponent(String(id))
            );

            if (sequence !== requestSequence) return;

            const areas = Array.isArray(response.service_areas)
                ? response.service_areas
                : [];

            areaPager.setRecords(areas);
            renderSavedRadiusAreas(areas);
            setStatus(`${areas.length} service area(s) configured.`);

        } catch (error) {
            if (sequence !== requestSequence) return;

            setStatus(error.message || 'Unable to load service areas.', true);
        }
    }

    async function loadContractors() {
        try {
            const response = await api('/api/contractors.php');
            const contractors = Array.isArray(response.contractors)
                ? response.contractors
                : [];

            contractorSelect.replaceChildren();

            const placeholder = new Option(
                'Select a contractor',
                ''
            );

            contractorSelect.add(placeholder);

            for (const contractor of contractors) {
                const name = [
                    contractor.first_name,
                    contractor.last_name
                ].filter(Boolean).join(' ');

                const displayName = contractor.business_name
                    ? `${name} — ${contractor.business_name}`
                    : name;

                contractorSelect.add(
                    new Option(displayName, String(contractor.id))
                );
            }

            if (contractors.length) {
                contractorSelect.value = String(contractors[0].id);
                await loadAreas();
            } else {
                setStatus('No contractors found.');
            }

        } catch (error) {
            contractorSelect.replaceChildren(
                new Option('Unable to load contractors', '')
            );
            setStatus(error.message || 'Unable to load contractors.', true);
        }
    }

    contractorSelect.addEventListener('change', () => {
        areaPager.setRecords([], true);
        loadAreas();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (mutationInProgress) return;

        const id = contractorId();

        if (id === null) {
            setStatus('Select a contractor first.', true);
            return;
        }

        const data = new FormData(form);

        const payload = {
            contractor_id: id,
            area_type: 'zip',
            label: String(data.get('label') || '').trim(),
            state_code: String(data.get('state_code') || '').trim().toUpperCase(),
            postal_code: String(data.get('postal_code') || '').trim(),
            active: data.get('active') === 'on' ? 1 : 0
        };

        if (editingArea !== null) {
            payload.id = Number(editingArea.id);
        }

        const method = editingArea !== null ? 'PUT' : 'POST';

        setMutationBusy(true);
        setStatus('Saving ZIP coverage...');

        try {
            await api('/api/contractor_service_areas.php', {
                method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            resetEditor();
            await loadAreas();
            setStatus('ZIP coverage saved successfully.');

        } catch (error) {
            setStatus(error.message || 'Unable to save ZIP coverage.', true);
        } finally {
            setMutationBusy(false);
        }
    });

    loadContractors();

    <?php if (!$isContractorSelfService): ?>
    // Contractor matching is read-only and tenant-scoped by the APIs.
    const matchClient = document.getElementById('sa-match-client');
    const matchLocation = document.getElementById('sa-match-location');
    const matchRun = document.getElementById('sa-match-run');
    const matchDiagnostics =
        document.getElementById('sa-match-diagnostics');
    const matchResults =
        document.getElementById('sa-match-results');

    let matchLocations = [];
    let matchRequestSequence = 0;

    function matchCell(row, value) {
        const td = document.createElement('td');
        td.textContent = String(value ?? '');
        row.appendChild(td);
        return td;
    }

    const matchPager = new BritePagination({
        container: document.getElementById('sa-match-pagination'),
        render: renderMatches
    });

    function renderMatches(contractors) {
        matchResults.replaceChildren();

        if (!contractors.length) {
            const row = document.createElement('tr');
            matchCell(row, 'No eligible contractors matched this location.')
                .colSpan = 3;
            matchResults.appendChild(row);
            return;
        }

        for (const contractor of contractors) {
            const row = document.createElement('tr');

            const name = [
                contractor.first_name,
                contractor.last_name
            ].filter(Boolean).join(' ');

            matchCell(
                row,
                contractor.business_name
                    ? `${name} — ${contractor.business_name}`
                    : name
            );

            const areas = Array.isArray(
                contractor.matching_areas
            ) ? contractor.matching_areas : [];

            matchCell(
                row,
                areas.map(area =>
                    `${area.label} (${String(area.area_type).toUpperCase()})`
                ).join('; ')
            );

            matchCell(
                row,
                areas.map(area =>
                    area.area_type === 'radius' &&
                    area.distance_miles !== null &&
                    Number.isFinite(Number(area.distance_miles))
                        ? `${Number(area.distance_miles).toFixed(2)} miles from radius center`
                        : 'ZIP coverage match'
                ).join('; ')
            );

            matchResults.appendChild(row);
        }
    }

    function clearMatchResults(message) {
        matchPager.setRecords([], true);
        matchResults.replaceChildren();
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 3;
        cell.textContent = message;
        row.appendChild(cell);
        matchResults.appendChild(row);
    }

    function selectedMatchLocation() {
        return matchLocations.find(
            location =>
                String(location.id) === matchLocation.value
        ) || null;
    }

    function describeMatchLocation() {
        const location = selectedMatchLocation();

        if (!location) {
            matchDiagnostics.textContent =
                'Select a service location.';
            return;
        }

        const zip = String(location.postal_code || '').trim();
        const usableZip = /^\\d{5}(?:-\\d{4})?$/.test(zip)
            && zip.substring(0, 5) !== '00000';

        const lat = Number(location.latitude);
        const lon = Number(location.longitude);

        const hasCoordinates =
            location.geocoding_status === 'resolved' &&
            location.latitude !== null &&
            location.longitude !== null &&
            Number.isFinite(lat) &&
            Number.isFinite(lon) &&
            Math.abs(lat) <= 90 &&
            Math.abs(lon) <= 180;

        const capabilities = [];

        if (usableZip) {
            capabilities.push('ZIP matching available');
        } else {
            capabilities.push('No usable ZIP code');
        }

        if (hasCoordinates) {
            capabilities.push('Radius matching available');
        } else {
            capabilities.push(
                'Radius matching unavailable: coordinates not resolved'
            );
        }

        matchDiagnostics.textContent =
            capabilities.join(' · ');
    }

    async function loadMatchClients() {
        const sequence = ++matchRequestSequence;
        matchClient.disabled = true;
        matchLocation.disabled = true;
        matchRun.disabled = true;

        try {
            const response = await api('/api/clients.php');

            if (sequence !== matchRequestSequence) return;

            const clients = Array.isArray(response.clients)
                ? response.clients
                : [];

            matchClient.replaceChildren(
                new Option('Select a client', '')
            );

            for (const client of clients) {
                const name = [
                    client.first_name,
                    client.last_name
                ].filter(Boolean).join(' ');

                matchClient.add(
                    new Option(name || `Client ${client.id}`,
                        String(client.id))
                );
            }

            matchClient.disabled = false;

            matchDiagnostics.textContent = clients.length
                ? 'Select a client to load service locations.'
                : 'No clients available.';

        } catch (error) {
            if (sequence !== matchRequestSequence) return;

            matchClient.replaceChildren(
                new Option('Unable to load clients', '')
            );

            matchDiagnostics.textContent =
                error.message || 'Unable to load clients.';
        }
    }

    async function loadMatchLocations() {
        const sequence = ++matchRequestSequence;
        matchLocations = [];

        matchLocation.replaceChildren(
            new Option('Select a service location', '')
        );

        matchLocation.disabled = true;
        matchRun.disabled = true;
        clearMatchResults('No matching search performed.');

        const clientId = Number(matchClient.value);

        if (!Number.isSafeInteger(clientId) || clientId < 1) {
            matchDiagnostics.textContent =
                'Select a client to load service locations.';
            return;
        }

        matchDiagnostics.textContent =
            'Loading client service locations...';

        try {
            const response = await api(
                '/api/client_locations.php?client_id=' +
                encodeURIComponent(String(clientId))
            );

            if (sequence !== matchRequestSequence) return;

            matchLocations = Array.isArray(response.locations)
                ? response.locations
                : [];

            for (const location of matchLocations) {
                const description = [
                    location.label,
                    location.city,
                    location.state,
                    location.postal_code
                ].filter(Boolean).join(' — ');

                matchLocation.add(
                    new Option(description, String(location.id))
                );
            }

            matchLocation.disabled = matchLocations.length === 0;

            matchDiagnostics.textContent = matchLocations.length
                ? 'Select a location to evaluate contractor coverage.'
                : 'This client has no saved service locations.';

        } catch (error) {
            if (sequence !== matchRequestSequence) return;

            matchDiagnostics.textContent =
                error.message || 'Unable to load service locations.';
        }
    }

    matchClient.addEventListener('change', loadMatchLocations);

    matchLocation.addEventListener('change', () => {
        ++matchRequestSequence;
        matchRun.disabled = !selectedMatchLocation();
        clearMatchResults('No matching search performed.');
        describeMatchLocation();
    });

    matchRun.addEventListener('click', async () => {
        const location = selectedMatchLocation();
        if (!location) return;

        const sequence = ++matchRequestSequence;
        const locationId = Number(location.id);

        matchRun.disabled = true;
        clearMatchResults('Searching contractor coverage...');
        matchDiagnostics.textContent =
            'Evaluating ZIP and radius coverage...';

        try {
            const response = await api(
                '/api/contractor_matches.php?location_id=' +
                encodeURIComponent(String(locationId))
            );

            if (sequence !== matchRequestSequence) return;

            const contractors = Array.isArray(response.contractors)
                ? response.contractors
                : [];

            matchPager.setRecords(contractors, true);

            describeMatchLocation();

            matchDiagnostics.textContent +=
                ` · ${contractors.length} matching contractor(s).`;

        } catch (error) {
            if (sequence !== matchRequestSequence) return;

            clearMatchResults('Matching lookup failed.');
            matchDiagnostics.textContent =
                error.message || 'Unable to retrieve matches.';

        } finally {
            if (sequence === matchRequestSequence) {
                matchRun.disabled = !selectedMatchLocation();
            }
        }
    });

    loadMatchClients();
    <?php endif; ?>

})();
</script>

<?php require __DIR__ . '/partials/app_footer.php'; ?>
