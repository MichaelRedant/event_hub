(function () {
    'use strict';

    const config = window.EventHubForms || {};
    const endpoint = config.endpoint || '';

    if (!endpoint) {
        return;
    }

    const getMessage = (key, fallback) => {
        if (config.messages && Object.prototype.hasOwnProperty.call(config.messages, key)) {
            return config.messages[key];
        }
        return fallback;
    };

    const bindForm = (wrapper) => {
        if (!wrapper || wrapper.dataset.ehBound === '1') {
            return;
        }
        const form = wrapper.querySelector('form');
        if (!form) {
            return;
        }
        wrapper.dataset.ehBound = '1';

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitForm(wrapper, form);
        });
    };

    const submitForm = (wrapper, form) => {
        const button = form.querySelector('button[type="submit"]');
        const feedback = ensureFeedback(wrapper);
        const formData = new FormData(form);
        const payload = {};

        formData.forEach((value, key) => {
            payload[key] = value;
        });

        payload.session_id = parseInt(payload.session_id || form.dataset.ehevent || 0, 10);
        payload.people_count = parseInt(payload.people_count || '1', 10);
        payload.consent_marketing = formData.get('consent_marketing') ? 1 : 0;
        const captchaField = document.getElementById('eh_captcha_token');
        payload.captcha_token = captchaField ? captchaField.value : '';

        if (!payload.session_id) {
            showMessage(feedback, 'error', getMessage('error', 'Formulier is onvolledig.'));
            return;
        }

        wrapper.classList.add('eh-form--loading');
        showMessage(feedback, 'notice', getMessage('sending', 'Bezig met verzenden…'));
        if (button) {
            button.disabled = true;
        }

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce || '',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
            .then(async (response) => {
                const body = await response.json().catch(() => ({}));
                return { ok: response.ok, body };
            })
            .then(({ ok, body }) => {
                if (ok && body && body.success) {
                    form.reset();
                    showMessage(feedback, 'success', body.message || getMessage('success', 'Bedankt! Je inschrijving is ontvangen.'));
                    wrapper.classList.add('eh-form--success');
                    handleRegistrationSuccess(body.session || null);
                } else {
                    const message = (body && body.message) || getMessage('error', 'Verzenden mislukt. Controleer je invoer en probeer opnieuw.');
                    showMessage(feedback, 'error', message);
                }
            })
            .catch(() => {
                showMessage(feedback, 'error', getMessage('error', 'Er ging iets mis. Probeer opnieuw.'));
            })
            .finally(() => {
                wrapper.classList.remove('eh-form--loading');
                if (button) {
                    button.disabled = false;
                }
            });
    };

    const ensureFeedback = (wrapper) => {
        let box = wrapper.querySelector('.eh-form-feedback');
        if (!box) {
            box = document.createElement('div');
            box.className = 'eh-form-feedback';
            wrapper.insertBefore(box, wrapper.firstChild);
        }
        return box;
    };

    const showMessage = (container, type, text) => {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        const div = document.createElement('div');
        div.className = `eh-alert ${type === 'success' ? 'success' : type === 'error' ? 'error' : 'notice'}`;
        div.textContent = text;
        container.appendChild(div);
    };

    const scan = () => {
        document.querySelectorAll('[data-event-hub-form]').forEach(bindForm);
    };

    const handleRegistrationSuccess = (session) => {
        if (!session) {
            return;
        }
        document.dispatchEvent(new CustomEvent('eventhub:registration-success', {
            detail: session,
        }));
        updateEventListCards(session);
    };

    const updateEventListCards = (session) => {
        const cards = document.querySelectorAll(`.eh-session-card[data-eventhub-session="${session.id}"]`);
        if (!cards.length) {
            return;
        }
        cards.forEach((card) => {
            if (session.status_label) {
                let badge = card.querySelector('[data-eventhub-status]');
                if (!badge && session.status_label) {
                    badge = document.createElement('span');
                    badge.setAttribute('data-eventhub-status', '');
                    badge.className = 'eh-badge';
                    card.insertBefore(badge, card.firstChild);
                }
                if (badge) {
                    badge.textContent = session.status_label;
                    badge.className = `eh-badge ${session.status_class || ''}`;
                }
            }

            const availabilityEl = card.querySelector('[data-eventhub-availability]');
            if (availabilityEl) {
                if (session.available_label) {
                    availabilityEl.textContent = session.available_label;
                } else {
                    availabilityEl.remove();
                }
            } else if (session.available_label) {
                const meta = document.createElement('div');
                meta.className = 'eh-meta eh-availability';
                meta.setAttribute('data-eventhub-availability', '');
                meta.textContent = session.available_label;
                card.appendChild(meta);
            }

            const button = card.querySelector('[data-eventhub-button]');
            if (button) {
                if (session.button_disabled) {
                    button.classList.add('is-disabled');
                } else {
                    button.classList.remove('is-disabled');
                }
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        const hook = () => scan();
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', hook);
        window.elementorFrontend.hooks.addAction('frontend/element_ready/eventhub_session_detail.default', hook);
        window.elementorFrontend.hooks.addAction('frontend/element_ready/eventhub_session_form.default', hook);
    }
})();
