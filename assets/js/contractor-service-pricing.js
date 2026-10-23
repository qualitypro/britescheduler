(() => {
    'use strict';

    const root = document.getElementById(
        'contractorPricingPanel'
    );

    if (!root) return;

    const container = document.getElementById(
        'contractorPricingRows'
    );

    const status = document.getElementById(
        'contractorPricingStatus'
    );

    const endpoint =
        window.BRITE_BASE +
        '/api/contractor_service_pricing.php';

    function message(text, error = false) {
        status.textContent = text;
        status.classList.toggle('danger', error);
    }

    async function request(method, payload = null) {
        const options = {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': window.BRITE_CSRF
            }
        };

        if (payload !== null) {
            options.headers['Content-Type'] =
                'application/json';

            options.body = JSON.stringify(payload);
        }

        const response = await fetch(endpoint, options);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.error || 'Request failed'
            );
        }

        return data;
    }

    function element(tag, text, className) {
        const node = document.createElement(tag);

        if (text !== undefined) {
            node.textContent = text;
        }

        if (className) {
            node.className = className;
        }

        return node;
    }

    function createServiceCard(service) {
        const card = element('article', undefined, 'card');

        const title = element(
            'h3',
            service.service_name
        );

        card.appendChild(title);

        const qualification =
            service.verification_status || 'not requested';

        const detail = element(
            'p',
            'Qualification: ' + qualification,
            'muted'
        );

        card.appendChild(detail);

        if (Number(service.service_active) !== 1) {
            card.appendChild(element(
                'p',
                'Service is inactive.',
                'muted'
            ));
            return card;
        }

        if (qualification === 'not requested') {
            const requestButton = element(
                'button',
                'Request Qualification'
            );

            requestButton.type = 'button';

            const feedback = element(
                'p',
                '',
                'muted'
            );

            feedback.setAttribute('role', 'status');

            requestButton.addEventListener(
                'click',
                async () => {
                    requestButton.disabled = true;
                    feedback.textContent =
                        'Submitting request...';

                    try {
                        const response = await fetch(
                            window.BRITE_BASE +
                            '/api/contractor_service_requests.php',
                            {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': window.BRITE_CSRF
                                },
                                body: JSON.stringify({
                                    service_id:
                                        Number(service.service_id)
                                })
                            }
                        );

                        const result = await response.json();

                        if (!response.ok) {
                            throw new Error(
                                result.error ||
                                'Request failed'
                            );
                        }

                        feedback.textContent =
                            'Qualification request submitted.';

                        await load();
                    } catch (error) {
                        feedback.textContent =
                            'Error: ' + error.message;
                        requestButton.disabled = false;
                    }
                }
            );

            card.append(requestButton, feedback);
            return card;
        }

        if (qualification === 'unverified') {
            card.appendChild(element(
                'p',
                'Pending management approval.',
                'muted'
            ));
            return card;
        }

        if (qualification === 'rejected') {
            card.appendChild(element(
                'p',
                'Qualification rejected. Contact management.',
                'muted'
            ));
            return card;
        }

        if (qualification !== 'verified') {
            card.appendChild(element(
                'p',
                'Qualification status unavailable.',
                'muted'
            ));
            return card;
        }

        const form = element('form');

        form.style.display = 'grid';
        form.style.gridTemplateColumns =
            'repeat(auto-fit, minmax(150px, 1fr))';
        form.style.gap = '12px';
        form.style.alignItems = 'end';

        const typeLabel = element('label', 'Pricing type');
        typeLabel.style.display = 'grid';

        const type = element('select');

        for (const [value, label] of [
            ['hourly', 'Hourly'],
            ['flat', 'Flat rate']
        ]) {
            const option = element('option', label);
            option.value = value;
            type.appendChild(option);
        }

        type.value = service.pricing_type || 'hourly';
        typeLabel.appendChild(type);

        const amountLabel = element('label', 'Amount ($)');
        amountLabel.style.display = 'grid';

        const amount = element('input');
        amount.type = 'number';
        amount.min = '0';
        amount.max = '9999999999.99';
        amount.step = '0.01';
        amount.required = true;
        amount.value = service.amount ?? '';

        amountLabel.appendChild(amount);

        const activeLabel = element('label');
        activeLabel.style.display = 'flex';
        activeLabel.style.alignItems = 'center';
        activeLabel.style.gap = '8px';

        const active = element('input');
        active.type = 'checkbox';

        active.checked =
            service.pricing_active === null ||
            service.pricing_active === undefined
                ? true
                : Number(service.pricing_active) === 1;

        activeLabel.appendChild(active);
        activeLabel.appendChild(
            document.createTextNode('Active offering')
        );

        const save = element('button', 'Save price');
        save.type = 'submit';

        const feedback = element(
            'p',
            '',
            'muted'
        );

        feedback.setAttribute('role', 'status');
        feedback.setAttribute('aria-live', 'polite');

        form.append(
            typeLabel,
            amountLabel,
            activeLabel,
            save
        );

        form.addEventListener('submit', async event => {
            event.preventDefault();

            if (!form.reportValidity()) return;

            save.disabled = true;
            feedback.textContent = 'Saving...';

            try {
                const result = await request('PUT', {
                    service_id: Number(service.service_id),
                    pricing_type: type.value,
                    amount: amount.value,
                    active: active.checked ? 1 : 0
                });

                feedback.textContent =
                    'Saved $' + result.amount +
                    (type.value === 'hourly'
                        ? ' per hour.'
                        : ' flat rate.');
            } catch (error) {
                feedback.textContent =
                    'Error: ' + error.message;
            } finally {
                save.disabled = false;
            }
        });

        card.append(form, feedback);

        return card;
    }

    async function load() {
        message('Loading your service offerings...');
        container.replaceChildren();

        try {
            const data = await request('GET');

            if (!data.services.length) {
                message('No services configured for this tenant.');
                return;
            }

            for (const service of data.services) {
                container.appendChild(
                    createServiceCard(service)
                );
            }

            message(
                data.services.length +
                ' services loaded.'
            );
        } catch (error) {
            message(error.message, true);
        }
    }

    const tab = document.getElementById(
        'contractorPricingTab'
    );

    if (tab) {
        tab.addEventListener('click', () => {
            if (!container.children.length) load();
        });
    }

    // Load immediately so keyboard and programmatic
    // tab activation also have current data.
    load();
})();
