(function() {
    'use strict';

    function showToast(message, variant) {
        if (!message) { return; }
        let container = document.querySelector('.eh-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'eh-toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = 'eh-toast';
        if (variant === 'error') {
            toast.style.background = '#b91c1c';
        }
        toast.textContent = message;
        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    (function initToasts() {
        const toastMessage = window.EventHubAdminUI && window.EventHubAdminUI.toast ? window.EventHubAdminUI.toast : null;
        if (toastMessage) {
            showToast(toastMessage);
        }
    })();

    (function initEventFormValidation() {
        const saveBtn = document.getElementById('eh-save-btn');
        const postForm = document.getElementById('post');
        const publishBtn = document.getElementById('publish');

        function clearErrors() {
            document.querySelectorAll('.eh-field-error').forEach(el => {
                el.classList.remove('eh-field-error');
                const msg = el.querySelector('.eh-error-msg');
                if (msg) { msg.remove(); }
            });
        }

        function addError(input, message) {
            const wrap = input.closest('.field') || input.parentElement;
            if (!wrap) { return; }
            wrap.classList.add('eh-field-error');
            const msg = document.createElement('div');
            msg.className = 'eh-error-msg';
            msg.textContent = message;
            wrap.appendChild(msg);
        }

        function validateEventForm() {
            clearErrors();
            let ok = true;
            const required = [
                { sel: '#_eh_date_start', msg: 'Startdatum is verplicht.' },
            ];
            required.forEach(r => {
                const el = document.querySelector(r.sel);
                if (el && !el.value) {
                    ok = false;
                    addError(el, r.msg);
                }
            });

            const dateStartEl = document.querySelector('#_eh_date_start');
            const dateEndEl = document.querySelector('#_eh_date_end');
            const bookingOpenEl = document.querySelector('#_eh_booking_open');
            const bookingCloseEl = document.querySelector('#_eh_booking_close');
            const capacityEl = document.querySelector('#_eh_capacity');
            const onlineToggle = document.querySelector('input[name=\"_eh_is_online\"]');
            const onlineLink = document.querySelector('#_eh_online_link');

            const parseDT = (el) => {
                if (!el || !el.value) { return null; }
                const t = Date.parse(el.value);
                return isNaN(t) ? null : t;
            };
            const startTs = parseDT(dateStartEl);
            const endTs = parseDT(dateEndEl);
            const bookingOpenTs = parseDT(bookingOpenEl);
            const bookingCloseTs = parseDT(bookingCloseEl);

            if (startTs && endTs && endTs < startTs) {
                ok = false;
                addError(dateEndEl, 'Einddatum moet na de start liggen.');
            }
            if (bookingOpenTs && bookingCloseTs && bookingCloseTs < bookingOpenTs) {
                ok = false;
                addError(bookingCloseEl, 'Sluitmoment kan niet vóór openen liggen.');
            }
            if (bookingOpenTs && startTs && bookingOpenTs > startTs) {
                ok = false;
                addError(bookingOpenEl, 'Openmoment kan niet na de start liggen.');
            }
            if (bookingCloseTs && startTs && bookingCloseTs > startTs + 86400000) {
                // allow close on same day or before start; otherwise warn
                addError(bookingCloseEl, 'Let op: sluitmoment ligt na start (controleer).');
            }

            if (capacityEl && capacityEl.value !== '') {
                const cap = parseInt(capacityEl.value, 10);
                if (isNaN(cap) || cap < 0) {
                    ok = false;
                    addError(capacityEl, 'Capaciteit moet 0 of hoger zijn.');
                }
            }

            if (onlineToggle && onlineToggle.checked && onlineLink && !onlineLink.value) {
                ok = false;
                addError(onlineLink, 'Vul een onlinelink in voor een online sessie.');
            }

            return ok;
        }

        function interceptSubmit(e) {
            if (!validateEventForm()) {
                e.preventDefault();
                showToast('Vul de verplichte velden in.', 'error');
                return false;
            }
            return true;
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', interceptSubmit, true);
        }
        if (publishBtn) {
            publishBtn.addEventListener('click', interceptSubmit, true);
        }
        if (postForm) {
            postForm.addEventListener('submit', function(e){
                if (!validateEventForm()) {
                    e.preventDefault();
                    showToast('Vul de verplichte velden in.', 'error');
                    return false;
                }
            }, true);
        }
    })();
})();
