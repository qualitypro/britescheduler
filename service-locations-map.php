<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

Auth::requireRole('owner', 'admin', 'scheduler');

$pageTitle = 'Service Locations Map';

require __DIR__ . '/partials/app_header.php';
?>

<style>
.service-map-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
}
.service-map-stat {
    font-weight: 600;
}
#serviceLocationsMap {
    height: 580px;
    max-width: 100%;
    border-radius: 8px;
}
#serviceMapMessage {
    margin-top: 12px;
}
</style>

<h1>Service Locations Map</h1>

<p class="muted">
    View all verified client service locations
    across your organization.
</p>

<div class="card">

    <div class="service-map-toolbar">
        <span class="service-map-stat">
            Locations: <span id="locationCount">0</span>
        </span>

        <span class="service-map-stat">
            Clients: <span id="clientCount">0</span>
        </span>

        <button type="button" id="refreshServiceMap">
            Refresh Map
        </button>

        <a href="<?= htmlspecialchars(
            app_url('/client-locations.php'),
            ENT_QUOTES,
            'UTF-8'
        ) ?>">
            Manage Locations
        </a>
    </div>

    <div
        id="serviceLocationsMap"
        role="region"
        aria-label="Tenant-wide client service locations map">
    </div>

    <p
        id="serviceMapMessage"
        class="muted"
        role="status"
        aria-live="polite">
        Loading service locations...
    </p>

</div>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        app_url('/assets/vendor/leaflet/leaflet.css'),
        ENT_QUOTES,
        'UTF-8'
    ) ?>">

<script src="<?= htmlspecialchars(
    app_url('/assets/vendor/leaflet/leaflet.js'),
    ENT_QUOTES,
    'UTF-8'
) ?>"></script>

<script>
'use strict';

const serviceMap = L.map('serviceLocationsMap')
    .setView([28.39, -80.61], 10);

L.tileLayer(
    'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }
).addTo(serviceMap);

const serviceMarkers = L.featureGroup()
    .addTo(serviceMap);

const locationCountElement =
    document.getElementById('locationCount');

const clientCountElement =
    document.getElementById('clientCount');

const serviceMapMessage =
    document.getElementById('serviceMapMessage');

const refreshButton =
    document.getElementById('refreshServiceMap');

const endpoint = <?= json_encode(
    app_url('/api/service_locations_map.php'),
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

function escapeHtml(value) {
    return String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[character])
    );
}

function clientName(location) {
    const company = String(
        location.company_name ?? ''
    ).trim();

    const person = [
        location.first_name,
        location.last_name
    ].filter(Boolean).join(' ').trim();

    return company || person || 'Unnamed Client';
}

function locationAddress(location) {
    return [
        location.address1,
        location.address2,
        location.city,
        location.state,
        location.postal_code
    ].filter(Boolean).join(', ');
}

async function refreshServiceLocationsMap() {
    refreshButton.disabled = true;
    serviceMapMessage.textContent =
        'Loading service locations...';

    try {
        const response = await fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(
                'Unable to load locations (HTTP ' +
                response.status + ').'
            );
        }

        const data = await response.json();

        if (data.ok !== true ||
            !Array.isArray(data.locations)) {
            throw new Error('Invalid map API response.');
        }

        const nextMarkers = [];
        const clientIds = new Set();

        for (const location of data.locations) {
            if (location.latitude === null ||
                location.longitude === null ||
                location.latitude === '' ||
                location.longitude === '') {
                continue;
            }

            const latitude = Number(location.latitude);
            const longitude = Number(location.longitude);

            if (!Number.isFinite(latitude) ||
                !Number.isFinite(longitude) ||
                Math.abs(latitude) > 90 ||
                Math.abs(longitude) > 180) {
                continue;
            }

            const popup =
                '<strong>' +
                escapeHtml(location.label) +
                '</strong>' +
                '<p>Client: ' +
                escapeHtml(clientName(location)) +
                '</p>' +
                '<p>' +
                escapeHtml(locationAddress(location)) +
                '</p>' +
                (Number(location.is_primary) === 1
                    ? '<p>Primary Location</p>'
                    : '');

            const marker = L.marker([
                latitude,
                longitude
            ]).bindPopup(popup);

            nextMarkers.push(marker);
            clientIds.add(String(location.client_id));
        }

        serviceMarkers.clearLayers();

        for (const marker of nextMarkers) {
            marker.addTo(serviceMarkers);
        }

        locationCountElement.textContent =
            String(nextMarkers.length);

        clientCountElement.textContent =
            String(clientIds.size);

        if (nextMarkers.length > 0) {
            serviceMap.fitBounds(
                serviceMarkers.getBounds().pad(0.2),
                { maxZoom: 15 }
            );

            serviceMapMessage.textContent =
                nextMarkers.length +
                ' verified service location(s) displayed.';
        } else {
            serviceMapMessage.textContent =
                'No verified service locations available.';
        }

        serviceMap.invalidateSize();

    } catch (error) {
        serviceMapMessage.textContent =
            error.message || 'Unable to load map.';

    } finally {
        refreshButton.disabled = false;
    }
}

refreshButton.addEventListener(
    'click',
    refreshServiceLocationsMap
);

refreshServiceLocationsMap();
</script>

<?php require __DIR__ . '/partials/app_footer.php'; ?>
