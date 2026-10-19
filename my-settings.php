<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

Auth::requireUser();

$pageTitle = 'My Settings';

require __DIR__ . '/partials/app_header.php';
?>

<style>
.brite-my-settings {
    max-width: 1050px;
    margin: 0 auto;
    padding: 8px 0 36px;
    color: var(--brite-text);
}

.brite-my-settings * {
    box-sizing: border-box;
}

.brite-my-settings h1 {
    margin: 0 0 8px;
    font-size: clamp(1.65rem, 3vw, 2.15rem);
}

.brite-my-settings h2 {
    margin: 0 0 8px;
    font-size: 1.15rem;
}

.brite-my-settings p {
    color: var(--brite-text-secondary);
    line-height: 1.55;
}

.brite-settings-intro {
    margin-bottom: 26px;
}

.brite-settings-section {
    background: var(--brite-surface);
    border: 1px solid var(--brite-border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
}

.brite-settings-section > p {
    margin: 0 0 20px;
}

.brite-palette-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.brite-palette-option {
    display: block;
    position: relative;
    cursor: pointer;
}

.brite-palette-option input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.brite-palette-preview {
    display: block;
    border: 2px solid var(--brite-border);
    border-radius: 13px;
    padding: 12px;
    transition: border-color 150ms ease;
}

.brite-palette-option input:checked +
.brite-palette-preview {
    border-color: var(--brite-indigo);
    box-shadow: 0 0 0 2px var(--brite-indigo-soft);
}

.brite-palette-option input:focus-visible +
.brite-palette-preview {
    outline: 3px solid var(--brite-indigo);
    outline-offset: 3px;
}

.brite-palette-demo {
    display: flex;
    height: 85px;
    border-radius: 8px;
    overflow: hidden;
    background: var(--demo-canvas);
}

.brite-palette-demo-sidebar {
    width: 27%;
    background: var(--demo-sidebar);
    padding: 12px 8px;
}

.brite-palette-demo-sidebar span {
    display: block;
    height: 6px;
    margin-bottom: 10px;
    border-radius: 3px;
    background: rgba(255,255,255,.45);
}

.brite-palette-demo-content {
    flex: 1;
    padding: 12px;
}

.brite-palette-demo-card {
    height: 38px;
    border-radius: 6px;
    background: var(--demo-surface);
    padding: 9px;
}

.brite-palette-demo-card span {
    display: block;
    height: 7px;
    width: 60%;
    border-radius: 3px;
    background: var(--demo-accent);
}

.brite-palette-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 11px;
    font-weight: 650;
}

.brite-palette-check {
    color: var(--brite-indigo);
    visibility: hidden;
}

.brite-palette-option input:checked +
.brite-palette-preview .brite-palette-check {
    visibility: visible;
}

.brite-palette-ocean {
    --demo-sidebar: #172554;
    --demo-accent: #4f46e5;
    --demo-canvas: #f8fafc;
    --demo-surface: #ffffff;
}

.brite-palette-forest {
    --demo-sidebar: #14532d;
    --demo-accent: #15803d;
    --demo-canvas: #f0fdf4;
    --demo-surface: #ffffff;
}

.brite-palette-midnight {
    --demo-sidebar: #020617;
    --demo-accent: #6366f1;
    --demo-canvas: #020617;
    --demo-surface: #1e293b;
}

.brite-palette-slate {
    --demo-sidebar: #44403c;
    --demo-accent: #b45309;
    --demo-canvas: #faf7f2;
    --demo-surface: #ffffff;
}

.brite-settings-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.brite-settings-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.brite-settings-field select {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    background: var(--brite-surface);
    color: var(--brite-text);
    border: 1px solid var(--brite-border-strong);
    border-radius: 9px;
}

.brite-settings-toggle {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 0;
    cursor: pointer;
}

.brite-settings-toggle + .brite-settings-toggle {
    border-top: 1px solid var(--brite-border);
}

.brite-settings-toggle input {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    accent-color: var(--brite-indigo);
}

.brite-settings-toggle strong,
.brite-settings-toggle small {
    display: block;
}

.brite-settings-toggle small {
    margin-top: 4px;
    color: var(--brite-text-muted);
    line-height: 1.4;
}

.brite-settings-status {
    position: sticky;
    bottom: 16px;
    display: inline-flex;
    align-items: center;
    min-height: 42px;
    padding: 10px 16px;
    border-radius: 10px;
    background: var(--brite-surface);
    border: 1px solid var(--brite-border);
    box-shadow: var(--brite-shadow-md);
    color: var(--brite-text);
    font-size: .9rem;
}

.brite-settings-status[data-state="error"] {
    border-color: var(--brite-danger);
}

@media (max-width: 680px) {
    .brite-palette-grid,
    .brite-settings-fields {
        grid-template-columns: 1fr;
    }

    .brite-settings-section {
        padding: 18px;
    }
}
</style>

<div class="brite-my-settings">
    <div class="brite-settings-intro">
        <h1>My Settings</h1>
        <p>
            Make Brite Scheduler work the way you prefer.
            Your settings are saved automatically and follow
            your account across sessions.
        </p>
    </div>

    <section class="brite-settings-section"
             aria-labelledby="appearanceHeading">
        <h2 id="appearanceHeading">Appearance</h2>
        <p>Choose a color palette. Changes appear immediately.</p>

        <div class="brite-palette-grid"
             id="paletteOptions">

<?php
$palettes = [
    'ocean' => 'Ocean Blue',
    'forest' => 'Forest Green',
    'midnight' => 'Midnight',
    'slate' => 'Warm Slate',
];

foreach ($palettes as $value => $label):
?>
            <label class="brite-palette-option">
                <input type="radio"
                       name="color_palette"
                       value="<?=htmlspecialchars($value, ENT_QUOTES, 'UTF-8')?>">
                <span class="brite-palette-preview">
                    <span class="brite-palette-demo brite-palette-<?=htmlspecialchars($value, ENT_QUOTES, 'UTF-8')?>"
                          aria-hidden="true">
                        <span class="brite-palette-demo-sidebar">
                            <span></span><span></span><span></span>
                        </span>
                        <span class="brite-palette-demo-content">
                            <span class="brite-palette-demo-card">
                                <span></span>
                            </span>
                        </span>
                    </span>
                    <span class="brite-palette-label">
                        <?=htmlspecialchars($label, ENT_QUOTES, 'UTF-8')?>
                        <span class="brite-palette-check"
                              aria-hidden="true">✓</span>
                    </span>
                </span>
            </label>
<?php endforeach; ?>
        </div>
    </section>

    <section class="brite-settings-section"
             aria-labelledby="calendarHeading">
        <h2 id="calendarHeading">Calendar</h2>
        <p>Set your preferred calendar display options.</p>

        <div class="brite-settings-fields">
            <div class="brite-settings-field">
                <label for="calendar_view">Default view</label>
                <select id="calendar_view">
                    <option value="month">Month</option>
                    <option value="week">Week</option>
                    <option value="day">Day</option>
                </select>
            </div>

            <div class="brite-settings-field">
                <label for="week_starts_on">Start of week</label>
                <select id="week_starts_on">
                    <option value="sunday">Sunday</option>
                    <option value="monday">Monday</option>
                </select>
            </div>

            <div class="brite-settings-field">
                <label for="time_format">Time format</label>
                <select id="time_format">
                    <option value="12h">12-hour (3:30 PM)</option>
                    <option value="24h">24-hour (15:30)</option>
                </select>
            </div>
        </div>
    </section>

    <section class="brite-settings-section"
             aria-labelledby="accessibilityHeading">
        <h2 id="accessibilityHeading">Accessibility</h2>
        <p>Adjust motion and contrast for a comfortable experience.</p>

        <label class="brite-settings-toggle">
            <input type="checkbox" id="reduced_motion">
            <span>
                <strong>Reduce motion</strong>
                <small>Minimize interface animations and transitions.</small>
            </span>
        </label>

        <label class="brite-settings-toggle">
            <input type="checkbox" id="high_contrast">
            <span>
                <strong>Higher contrast</strong>
                <small>Make borders and interface elements more distinct.</small>
            </span>
        </label>
    </section>

    <section class="brite-settings-section"
             aria-labelledby="notificationsHeading">
        <h2 id="notificationsHeading">Notifications</h2>
        <p>Choose your preferred notification settings.</p>

        <label class="brite-settings-toggle">
            <input type="checkbox"
                   id="in_app_notifications">
            <span>
                <strong>In-app notifications</strong>
                <small>Save your preference for in-app alerts.</small>
            </span>
        </label>
    </section>

    <div class="brite-settings-status"
         id="settingsStatus"
         role="status"
         aria-live="polite">
        Loading your settings…
    </div>
</div>

<script>
(() => {
    'use strict';

    const status = document.getElementById('settingsStatus');

    const fields = [
        'calendar_view',
        'week_starts_on',
        'time_format',
        'reduced_motion',
        'high_contrast',
        'in_app_notifications'
    ];

    let ready = false;
    let saving = Promise.resolve();

    function showStatus(message, error = false) {
        status.textContent = message;
        status.dataset.state = error ? 'error' : 'ok';
    }

    function applyAppearance(preferences) {
        if (preferences.color_palette) {
            document.body.dataset.briteTheme =
                preferences.color_palette;
        }

        if (preferences.reduced_motion !== undefined) {
            document.body.dataset.briteReducedMotion =
                Number(preferences.reduced_motion) ? '1' : '0';
        }

        if (preferences.high_contrast !== undefined) {
            document.body.dataset.briteHighContrast =
                Number(preferences.high_contrast) ? '1' : '0';
        }
    }

    async function savePreference(field, value) {
        saving = saving.catch(() => {}).then(async () => {
            showStatus('Saving…');

            try {
                const result = await api(
                    '/api/user_preferences.php',
                    {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            [field]: value
                        })
                    }
                );

                applyAppearance(result.preferences);
                showStatus('All changes saved');
            } catch (error) {
                showStatus(
                    error.message || 'Unable to save settings',
                    true
                );
            }
        });

        return saving;
    }

    async function initialize() {
        try {
            const result = await api(
                '/api/user_preferences.php'
            );

            const preferences = result.preferences;

            const selected = document.querySelector(
                'input[name="color_palette"][value="' +
                preferences.color_palette + '"]'
            );

            if (selected) {
                selected.checked = true;
            }

            for (const field of fields) {
                const input = document.getElementById(field);

                if (input.type === 'checkbox') {
                    input.checked = Boolean(
                        Number(preferences[field])
                    );
                } else {
                    input.value = preferences[field];
                }
            }

            applyAppearance(preferences);
            ready = true;
            showStatus('All changes saved');
        } catch (error) {
            showStatus(
                error.message || 'Unable to load settings',
                true
            );
        }
    }

    document.querySelectorAll(
        'input[name="color_palette"]'
    ).forEach(input => {
        input.addEventListener('change', () => {
            if (!ready || !input.checked) return;

            const value = input.value;

            applyAppearance({color_palette: value});
            savePreference('color_palette', value);
        });
    });

    for (const field of fields) {
        const input = document.getElementById(field);

        input.addEventListener('change', () => {
            if (!ready) return;

            const value = input.type === 'checkbox'
                ? (input.checked ? 1 : 0)
                : input.value;

            if (field === 'reduced_motion' ||
                field === 'high_contrast') {
                applyAppearance({[field]: value});
            }

            savePreference(field, value);
        });
    }

    initialize();
})();
</script>

<?php require __DIR__ . '/partials/app_footer.php'; ?>
