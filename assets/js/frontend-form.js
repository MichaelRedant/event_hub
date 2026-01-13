(function () {
    'use strict';

    const config = window.EventHubForms || {};
    const endpoint = config.endpoint || '';
    const sessionEndpoint = config.sessionEndpoint || (endpoint ? endpoint.replace(/\/register$/, '/session') : '');

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
        ensureHoneypot(form);

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
        payload.occurrence_id = parseInt(payload.occurrence_id || '0', 10);
        payload.people_count = parseInt(payload.people_count || '1', 10);
        payload.consent_marketing = formData.get('consent_marketing') ? 1 : 0;
        payload._eh_hp = formData.get('_eh_hp') || '';
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

    const ensureHoneypot = (form) => {
        if (!form.querySelector('input[name="_eh_hp"]')) {
            const hp = document.createElement('input');
            hp.type = 'text';
            hp.name = '_eh_hp';
            hp.value = '';
            hp.tabIndex = -1;
            hp.autocomplete = 'off';
            hp.style.position = 'absolute';
            hp.style.left = '-9999px';
            hp.style.opacity = '0';
            form.appendChild(hp);
        }
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
        document.querySelectorAll('[data-eventhub-open]').forEach(bindCard);
        document.querySelectorAll('[data-event-hub-form]').forEach(bindOccurrenceSelect);
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
        let cards = [];
        const baseSelector = `.eh-session-card[data-eventhub-session="${session.id}"]`;
        if (session.occurrence_id) {
            const occSelector = `${baseSelector}[data-eventhub-occurrence="${session.occurrence_id}"]`;
            cards = document.querySelectorAll(occSelector);
            if (!cards.length) {
                cards = document.querySelectorAll(baseSelector);
            }
        } else {
            cards = document.querySelectorAll(baseSelector);
        }
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

    const bindCard = (button) => {
        if (!button || button.dataset.ehBound === '1') {
            return;
        }
        button.dataset.ehBound = '1';
        button.addEventListener('click', (event) => {
            const sessionId = resolveSessionId(button);
            const occurrenceId = resolveOccurrenceId(button);
            if (!sessionId) {
                return;
            }
            event.preventDefault();
            openSessionModal(sessionId, occurrenceId);
        });
    };

    const resolveSessionId = (button) => {
        const attr = button.dataset.eventhubOpen || '';
        let id = parseInt(attr || '0', 10);
        if (!id) {
            const card = button.closest('[data-eventhub-session]');
            if (card) {
                id = parseInt(card.getAttribute('data-eventhub-session') || '0', 10);
            }
        }
        return id;
    };

    const resolveOccurrenceId = (button) => {
        const attr = button.dataset.eventhubOccurrence || '';
        let id = parseInt(attr || '0', 10);
        if (!id) {
            const card = button.closest('[data-eventhub-occurrence]');
            if (card) {
                id = parseInt(card.getAttribute('data-eventhub-occurrence') || '0', 10);
            }
        }
        return id;
    };

    const openSessionModal = (sessionId, occurrenceId) => {
        if (!sessionEndpoint) {
            return;
        }
        showModalLoading();
        const url = new URL(sessionEndpoint);
        url.searchParams.set('session_id', sessionId);
        if (occurrenceId) {
            url.searchParams.set('occurrence_id', occurrenceId);
        }
        fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
            },
        }).then((resp) => resp.json())
            .then((data) => {
                if (!data || !data.success || !data.session) {
                    showModalError(getMessage('error', 'Kon event niet laden.'));
                    return;
                }
                renderSessionModal(data.session);
            })
            .catch(() => showModalError(getMessage('error', 'Kon event niet laden.')));
    };

    const modalRoot = () => {
        let root = document.querySelector('.eh-modal');
        if (!root) {
            root = document.createElement('div');
            root.className = 'eh-modal';
            root.innerHTML = '<div class="eh-modal__overlay"></div><div class="eh-modal__dialog"><button class="eh-modal__close" aria-label="Sluiten">&times;</button><div class="eh-modal__content"></div></div>';
            document.body.appendChild(root);
            root.querySelector('.eh-modal__overlay').addEventListener('click', closeModal);
            root.querySelector('.eh-modal__close').addEventListener('click', closeModal);
            document.addEventListener('keyup', (evt) => {
                if (evt.key === 'Escape') {
                    closeModal();
                }
            });
        }
        return root;
    };

    const closeModal = () => {
        const root = document.querySelector('.eh-modal');
        if (root) {
            root.classList.remove('is-visible');
            root.classList.remove('is-error');
            const content = root.querySelector('.eh-modal__content');
            if (content) {
                content.innerHTML = '';
            }
        }
    };

    const showModalLoading = () => {
        const root = modalRoot();
        const content = root.querySelector('.eh-modal__content');
        root.classList.add('is-visible');
        if (content) {
            content.innerHTML = '<div class="eh-modal__loading">' + getMessage('loading', 'Bezig met laden...') + '</div>';
        }
    };

    const showModalError = (message) => {
        const root = modalRoot();
        const content = root.querySelector('.eh-modal__content');
        root.classList.add('is-visible');
        root.classList.add('is-error');
        if (content) {
            content.innerHTML = '<div class="eh-modal__error">' + message + '</div>';
        }
    };

    const renderSessionModal = (session) => {
        const root = modalRoot();
        const content = root.querySelector('.eh-modal__content');
        if (!content) {
            return;
        }
        const badge = session.badge ? `<span class="eh-badge ${session.badge.class || ''}">${session.badge.label || ''}</span>` : '';
        const meta = [];
        if (session.date_label) {
            meta.push(`<div class="eh-meta" data-eventhub-date>${session.date_label}${session.time_range ? ' | ' + session.time_range : ''}</div>`);
        }
        if (session.location_label) {
            meta.push(`<div class="eh-meta">${session.location_label}</div>`);
        }
        if (session.address) {
            meta.push(`<div class="eh-meta">${session.address}</div>`);
        }
        if (session.organizer) {
            meta.push(`<div class="eh-meta"><strong>${session.organizer}</strong></div>`);
        }
        if (session.staff) {
            meta.push(`<div class="eh-meta">${session.staff}</div>`);
        }
        if (session.price !== undefined && session.price !== null && session.price !== '') {
            meta.push(`<div class="eh-meta"><strong>${getMessage('price', 'Prijs')}:</strong> ${session.price}</div>`);
        }
        if (session.ticket_note) {
            meta.push(`<div class="eh-meta">${session.ticket_note}</div>`);
        }

        const availability = `<div class="eh-meta eh-availability" data-eventhub-availability>${session.availability_label || ''}</div>` +
            (session.waitlist_label ? `<div class="eh-meta eh-waitlist" data-eventhub-waitlist>${session.waitlist_label}</div>` : '');

        const share = buildShareBar(session);

        const canRegister = session.can_register && session.module_enabled;
        const waitlistMode = !!session.waitlist_mode;
        const registerNotice = session.register_notice || '';

        let formHtml = '';
        if (!session.module_enabled) {
            formHtml = `<div class="eh-alert notice">${getMessage('external', 'Inschrijvingen verlopen extern voor dit event.')}</div>`;
        } else if (!canRegister && registerNotice) {
            formHtml = `<div class="eh-alert notice">${registerNotice}</div>`;
        } else {
            formHtml = buildForm(session, waitlistMode);
        }

        content.innerHTML = `
            <div class="eh-modal__header" style="--eh-accent:${session.color || '#2271b1'};${session.hero_image ? 'background-image:url(' + session.hero_image + ');' : ''}">
                <div class="eh-modal__hero-overlay"></div>
                <div class="eh-modal__header-content">
                    ${badge}
                    <h2>${session.title || ''}</h2>
                    ${meta.join('')}
                    ${availability}
                </div>
            </div>
            <div class="eh-modal__body">
                ${share}
                ${session.content || ''}
                ${formHtml}
            </div>
        `;

        root.classList.remove('is-error');
        root.classList.add('is-visible');

        content.querySelectorAll('[data-event-hub-form]').forEach(bindForm);
        bindOccurrenceSelect(content);
        if (session.captcha && session.captcha.enabled) {
            ensureCaptchaToken(session.captcha);
        }
    };

    const buildForm = (session, waitlistMode) => {
        const hide = Array.isArray(session.hide_fields) ? session.hide_fields : [];
        const fields = [];
        fields.push(fieldInput('first_name', getMessage('first_name', 'Voornaam'), true));
        fields.push(fieldInput('last_name', getMessage('last_name', 'Familienaam'), true));
        fields.push(fieldInput('email', getMessage('email', 'E-mail'), true, 'email'));
        if (Array.isArray(session.occurrences) && session.occurrences.length) {
            fields.push(occurrenceSelect(session.occurrences, session.occurrence_id || 0));
        }
        if (!hide.includes('phone')) {
            fields.push(fieldInput('phone', getMessage('phone', 'Telefoon'), false));
        }
        if (!hide.includes('company')) {
            fields.push(fieldInput('company', getMessage('company', 'Bedrijf'), false));
        }
        if (!hide.includes('vat')) {
            fields.push(fieldInput('vat', getMessage('vat', 'BTW-nummer'), false));
        }
        if (!hide.includes('role')) {
            fields.push(fieldInput('role', getMessage('role', 'Rol'), false));
        }
        if (!hide.includes('people_count')) {
            fields.push(numberInput('people_count', getMessage('people_count', 'Aantal personen'), session.state && session.state.capacity ? session.state.capacity : 99));
        }
        if (Array.isArray(session.extra_fields) && session.extra_fields.length) {
            session.extra_fields.forEach((field) => {
                const slug = field.slug;
                const label = field.label || slug;
                const required = !!field.required;
                if (!slug || !label) {
                    return;
                }
                if (field.type === 'textarea') {
                    fields.push(textareaInput(`extra[${slug}]`, label, required));
                } else if (field.type === 'select') {
                    fields.push(selectInput(`extra[${slug}]`, label, field.options || [], required));
                } else {
                    fields.push(fieldInput(`extra[${slug}]`, label, required));
                }
            });
        }
        if (!hide.includes('marketing')) {
            fields.push(checkboxInput('consent_marketing', getMessage('marketing_optin', 'Ik wil relevante communicatie ontvangen.')));
        }
        const hasOccurrences = Array.isArray(session.occurrences) && session.occurrences.length;
        if (waitlistMode || hasOccurrences) {
            fields.push(waitlistOptInInput(getMessage('waitlist_opt_in', 'Zet me op de wachtlijst indien volzet.'), waitlistMode, hasOccurrences && !waitlistMode));
        }

        const submitLabel = waitlistMode ? getMessage('waitlist_submit', 'Op wachtlijst plaatsen') : getMessage('submit', 'Inschrijven');

        return `
            <div class="eh-session-form" data-event-hub-form="1">
                <div class="eh-form-feedback"></div>
                <form class="eh-form" data-ehevent="${session.id || 0}">
                    <input type="hidden" name="session_id" value="${session.id || 0}" />
                    ${waitlistMode ? '<input type="hidden" name="waitlist_opt_in" value="1" />' : ''}
                    <div class="eh-grid">
                        ${fields.join('')}
                    </div>
                    <input type="hidden" name="eh_captcha_token" id="eh_captcha_token" value="">
                    <button type="submit" class="eh-btn">${submitLabel}</button>
                </form>
            </div>
        `;
    };

    const fieldInput = (name, label, required = false, type = 'text') => {
        const req = required ? ' required' : '';
        return `<div class="field"><label>${label}${required ? ' *' : ''}</label><input type="${type}" name="${name}"${req}></div>`;
    };

    const numberInput = (name, label, max) => {
        const safeMax = Math.max(1, parseInt(max || '1', 10));
        return `<div class="field"><label>${label}</label><input type="number" name="${name}" min="1" max="${safeMax}" value="1"></div>`;
    };

    const textareaInput = (name, label, required = false) => {
        const req = required ? ' required' : '';
        return `<div class="field"><label>${label}${required ? ' *' : ''}</label><textarea name="${name}" rows="3"${req}></textarea></div>`;
    };

    const selectInput = (name, label, options, required = false) => {
        const req = required ? ' required' : '';
        const opts = (options || []).map((opt) => `<option value="${opt}">${opt}</option>`).join('');
        return `<div class="field"><label>${label}${required ? ' *' : ''}</label><select name="${name}"${req}><option value="">${getMessage('choose', 'Maak een keuze')}</option>${opts}</select></div>`;
    };

    const occurrenceSelect = (occurrences, selectedId) => {
        const opts = (occurrences || []).map((occ) => {
            const labelParts = [];
            if (occ.date_label) {
                labelParts.push(occ.date_label);
            }
            if (occ.time_range) {
                labelParts.push(occ.time_range);
            }
            if (occ.availability_label) {
                labelParts.push(occ.availability_label);
            }
            const label = labelParts.filter(Boolean).join(' | ');
            const selected = Number(occ.id) === Number(selectedId) ? ' selected' : '';
            const dataAttrs = ` data-date-label="${occ.date_label || ''}" data-time-range="${occ.time_range || ''}" data-availability="${occ.availability_label || ''}" data-waitlist="${occ.waitlist_label || ''}" data-full="${occ.state && occ.state.is_full ? '1' : '0'}"`;
            return `<option value="${occ.id}"${selected}${dataAttrs}>${label || occ.id}</option>`;
        }).join('');
        return `<div class="field full"><label>${getMessage('occurrence', 'Kies datum')} *</label><select name="occurrence_id" required><option value="">${getMessage('choose', 'Maak een keuze')}</option>${opts}</select></div>`;
    };

    const checkboxInput = (name, label, checked = false) => {
        return `<div class="field full checkbox"><label><input type="checkbox" name="${name}" value="1"${checked ? ' checked' : ''}> ${label}</label></div>`;
    };

    const waitlistOptInInput = (label, checked, hidden) => {
        const style = hidden ? ' style="display:none"' : '';
        return `<div class="field full checkbox" data-eventhub-waitlist-optin${style}><label><input type="checkbox" name="waitlist_opt_in" value="1"${checked ? ' checked' : ''}> ${label}</label></div>`;
    };

    const bindOccurrenceSelect = (root) => {
        if (!root) {
            return;
        }
        const select = root.querySelector('select[name="occurrence_id"]');
        if (!select) {
            return;
        }
        const dateEl = root.querySelector('[data-eventhub-date]');
        const availabilityEl = root.querySelector('[data-eventhub-availability]');
        const waitlistEl = root.querySelector('[data-eventhub-waitlist]');
        const waitlistOptIn = root.querySelector('[data-eventhub-waitlist-optin]');

        const update = () => {
            const opt = select.selectedOptions && select.selectedOptions[0] ? select.selectedOptions[0] : null;
            if (!opt) {
                return;
            }
            const dateLabel = opt.getAttribute('data-date-label') || '';
            const timeRange = opt.getAttribute('data-time-range') || '';
            if (dateEl) {
                dateEl.textContent = dateLabel + (timeRange ? ' | ' + timeRange : '');
            }
            if (availabilityEl) {
                availabilityEl.textContent = opt.getAttribute('data-availability') || '';
            }
            if (waitlistEl) {
                waitlistEl.textContent = opt.getAttribute('data-waitlist') || '';
            }
            if (waitlistOptIn) {
                const isFull = opt.getAttribute('data-full') === '1';
                waitlistOptIn.style.display = isFull ? '' : 'none';
            }
        };

        select.addEventListener('change', update);
        update();
    };

    const buildShareBar = (session) => {
        const url = session.permalink || window.location.href;
        const title = encodeURIComponent(session.title || '');
        const shareUrl = encodeURIComponent(url);
        const mailSubject = encodeURIComponent((config.messages && config.messages.shareSubject) || 'Interessant event');
        const mailBody = encodeURIComponent(`${session.title || ''}\n${url}`);
        return `
            <div class="eh-share">
                <span class="eh-share__label">${getMessage('share', 'Deel dit event')}:</span>
                <div class="eh-share__buttons">
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=${shareUrl}" class="eh-share__btn eh-share__btn--in" target="_blank" rel="noopener" aria-label="Deel op LinkedIn">in</a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=${shareUrl}" class="eh-share__btn eh-share__btn--fb" target="_blank" rel="noopener" aria-label="Deel op Facebook">f</a>
                    <a href="mailto:?subject=${mailSubject}&body=${mailBody}" class="eh-share__btn eh-share__btn--mail" aria-label="Deel via e-mail">@</a>
                    <button class="eh-share__btn eh-share__btn--copy" type="button" data-ehaction="copy-link" data-link="${url}" aria-label="Kopieer link">⧉</button>
                </div>
            </div>
        `;
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (target && target.matches('.eh-share__btn--copy')) {
            event.preventDefault();
            const link = target.getAttribute('data-link') || window.location.href;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(link).then(() => {
                    target.classList.add('is-copied');
                    setTimeout(() => target.classList.remove('is-copied'), 1200);
                }).catch(() => {
                    fallbackCopy(link, target);
                });
            } else {
                fallbackCopy(link, target);
            }
        }
    });

    const fallbackCopy = (text, target) => {
        const input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        try { document.execCommand('copy'); } catch (e) { /* ignore */ }
        document.body.removeChild(input);
        if (target) {
            target.classList.add('is-copied');
            setTimeout(() => target.classList.remove('is-copied'), 1200);
        }
    };

    const ensureCaptchaToken = (captcha) => {
        if (!captcha || !captcha.enabled || !captcha.site_key) {
            return;
        }
        if (captcha.provider === 'hcaptcha') {
            loadCaptchaScript('hcaptcha', `https://js.hcaptcha.com/1/api.js?render=${captcha.site_key}`, () => {
                if (window.hcaptcha) {
                    window.hcaptcha.ready(() => {
                        window.hcaptcha.execute(captcha.site_key, { action: 'eventhub_register' }).then((token) => {
                            const target = document.getElementById('eh_captcha_token');
                            if (target) {
                                target.value = token;
                            }
                        });
                    });
                }
            });
        } else {
            loadCaptchaScript('grecaptcha', `https://www.google.com/recaptcha/api.js?render=${captcha.site_key}`, () => {
                if (window.grecaptcha) {
                    window.grecaptcha.ready(() => {
                        window.grecaptcha.execute(captcha.site_key, { action: 'eventhub_register' }).then((token) => {
                            const target = document.getElementById('eh_captcha_token');
                            if (target) {
                                target.value = token;
                            }
                        });
                    });
                }
            });
        }
    };

    const loadCaptchaScript = (key, src, callback) => {
        if (document.querySelector(`script[data-eh-captcha="${key}"]`)) {
            callback();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.defer = true;
        script.dataset.ehCaptcha = key;
        script.onload = callback;
        document.head.appendChild(script);
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
