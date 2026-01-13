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
            const required = [];
            const occTable = document.getElementById('eh-occurrences-table');
            let hasOccurrence = false;
            if (occTable) {
                occTable.querySelectorAll('input[name="eh_occurrences[date_start][]"]').forEach(input => {
                    if (input.value) {
                        hasOccurrence = true;
                    }
                });
            }
            if (!hasOccurrence) {
                required.push({ sel: '#_eh_date_start', msg: 'Startdatum is verplicht.' });
            }
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

    (function initLinkedEventSearch() {
        const cptInput = document.getElementById('_eh_linked_event_cpt');
        const searchInput = document.getElementById('eh-linked-event-search');
        const resultsEl = document.querySelector('.eh-linked-event-results');
        const selectedEl = document.querySelector('.eh-linked-event-selected');
        const idInput = document.getElementById('_eh_linked_event_id');
        const nonceEl = document.getElementById('_eh_linked_event_nonce');
        const clearBtn = document.getElementById('eh-linked-event-clear');
        const selectEl = document.getElementById('eh-linked-event-select');

        if (!cptInput || !searchInput || !resultsEl || !selectedEl || !idInput || !nonceEl) {
            return;
        }
        if (typeof ajaxurl === 'undefined') {
            return;
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, (s) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[s]));
        }

        function renderSelected(info) {
            if (!info || !info.id) {
                selectedEl.innerHTML = '<span class="eh-linked-event-empty">Nog geen event gekoppeld.</span>';
                if (clearBtn) {
                    clearBtn.disabled = true;
                }
                if (selectEl) {
                    selectEl.value = '';
                }
                const confirmEl = document.querySelector('.eh-linked-event-confirm');
                if (confirmEl) {
                    confirmEl.innerHTML = '<span class="eh-linked-event-empty">Nog niet gekoppeld of koppeling ongeldig. Kies een event en sla op.</span>';
                    confirmEl.dataset.linkedValid = '0';
                }
                return;
            }
            const title = info.title ? escapeHtml(info.title) : 'Onbekend event';
            const safeEdit = info.editUrl ? escapeHtml(info.editUrl) : '';
            const missing = info.missing ? ' <span class="eh-linked-event-warn">(niet gevonden)</span>' : '';
            const editLink = safeEdit ? ' <a href="' + safeEdit + '" target="_blank" rel="noopener">Bewerk</a>' : '';
            selectedEl.innerHTML = '<span>Gekoppeld:</span> <strong>' + title + '</strong> <span class="eh-linked-event-meta">#' + info.id + '</span>' + missing + editLink;
            if (clearBtn) {
                clearBtn.disabled = false;
            }
            const confirmEl = document.querySelector('.eh-linked-event-confirm');
            if (confirmEl && !info.missing) {
                const viewUrl = info.viewUrl ? escapeHtml(info.viewUrl) : '';
                const viewLink = viewUrl ? ' <a href="' + viewUrl + '" target="_blank" rel="noopener">Bekijk</a>' : '';
                const shouldRender = info.forceConfirm || confirmEl.dataset.linkedValid !== '1';
                if (shouldRender) {
                    confirmEl.innerHTML = '<span class="eh-linked-event-ok">Koppeling geselecteerd (sla op om te bevestigen):</span> <strong>' + title + '</strong> <span class="eh-linked-event-meta">#' + info.id + '</span>' + viewLink + editLink;
                    confirmEl.dataset.linkedValid = '0';
                }
            }
            if (selectEl) {
                const value = String(info.id);
                let opt = selectEl.querySelector('option[value="' + value.replace(/"/g, '\\"') + '"]');
                if (!opt) {
                    opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = (info.title ? info.title : 'Event') + ' (#' + value + ')';
                    if (info.editUrl) {
                        opt.dataset.edit = info.editUrl;
                    }
                    opt.dataset.title = info.title || '';
                    selectEl.appendChild(opt);
                }
                selectEl.value = value;
            }
        }

        const initial = {
            id: parseInt(selectedEl.dataset.currentId || '0', 10) || 0,
            title: selectedEl.dataset.currentTitle || '',
            editUrl: selectedEl.dataset.currentEdit || '',
            missing: selectedEl.dataset.currentMissing === '1'
        };
        if (initial.id) {
            renderSelected(initial);
        } else if (clearBtn) {
            clearBtn.disabled = true;
        }

        function renderMessage(message) {
            resultsEl.innerHTML = '<div class="eh-linked-event-empty">' + escapeHtml(message) + '</div>';
        }

        function renderEmpty(message, actions) {
            resultsEl.innerHTML = '';
            const msg = document.createElement('div');
            msg.className = 'eh-linked-event-empty';
            msg.textContent = message;
            resultsEl.appendChild(msg);
            if (actions && actions.recent) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'button eh-linked-event-action';
                btn.textContent = 'Toon recente events';
                btn.addEventListener('click', () => runSearch('', { recent: true }));
                resultsEl.appendChild(btn);
            }
            if (actions && actions.all) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'button eh-linked-event-action';
                btn.textContent = 'Toon alle events';
                btn.addEventListener('click', () => runSearch('', { all: true }));
                resultsEl.appendChild(btn);
            }
        }

        function renderResults(items, term, useRecent, useAll) {
            resultsEl.innerHTML = '';
            if (!items || !items.length) {
                if (useRecent) {
                    renderEmpty('Geen recente events gevonden.', { all: true });
                } else if (useAll) {
                    renderEmpty('Geen events gevonden.', { recent: true });
                } else {
                    renderEmpty('Geen events gevonden.', { recent: true, all: true });
                }
                return;
            }
            items.forEach((item) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'eh-linked-event-result';
                const status = item.status ? ' [' + item.status + ']' : '';
                btn.textContent = (item.title || 'Onbekend') + status + ' (#' + item.id + ')';
                btn.addEventListener('click', () => {
                    idInput.value = item.id;
                    renderSelected({
                        id: item.id,
                        title: item.title || '',
                        editUrl: item.edit_link || '',
                        viewUrl: item.view_link || '',
                        forceConfirm: true,
                        missing: false
                    });
                    resultsEl.innerHTML = '';
                    searchInput.value = '';
                });
                resultsEl.appendChild(btn);
            });
        }

        let timer = null;
        function runSearch(term, options) {
            const opts = options || {};
            const useRecent = !!opts.recent;
            const useAll = !!opts.all;
            const cpt = (cptInput.value || '').trim();
            if (!cpt) {
                renderMessage('Vul eerst CPT-slug in.');
                return;
            }
            if (!useRecent && !useAll && term.length < 1) {
                resultsEl.innerHTML = '';
                return;
            }
            if (useRecent || useAll) {
                term = '';
            }
            const payload = new URLSearchParams();
            payload.append('action', 'event_hub_search_linked_events');
            payload.append('nonce', nonceEl.value || '');
            payload.append('cpt', cpt);
            payload.append('term', term);
            if (useRecent) {
                payload.append('recent', '1');
            }
            if (useAll) {
                payload.append('all', '1');
            }
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: payload.toString()
            })
                .then((res) => res.json())
                .then((data) => {
                    if (!data || !data.success) {
                        const msg = data && data.data && data.data.message ? data.data.message : 'Zoeken mislukt.';
                        renderMessage(msg);
                        return;
                    }
                    renderResults(data.data || [], term, useRecent, useAll);
                })
                .catch(() => renderMessage('Zoeken mislukt.'));
        }

        searchInput.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => runSearch(searchInput.value.trim()), 250);
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        cptInput.addEventListener('change', () => {
            idInput.value = '';
            renderSelected(null);
            resultsEl.innerHTML = '';
        });
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                idInput.value = '';
                renderSelected(null);
                resultsEl.innerHTML = '';
            });
        }
        if (selectEl) {
            selectEl.addEventListener('change', () => {
                const value = selectEl.value ? parseInt(selectEl.value, 10) : 0;
                if (!value) {
                    idInput.value = '';
                    renderSelected(null);
                    return;
                }
                const opt = selectEl.options[selectEl.selectedIndex];
                const title = opt && opt.dataset && opt.dataset.title ? opt.dataset.title : (opt ? opt.textContent : '');
                const editUrl = opt && opt.dataset ? opt.dataset.edit : '';
                const viewUrl = opt && opt.dataset ? opt.dataset.view : '';
                idInput.value = String(value);
                renderSelected({
                    id: value,
                    title: title || '',
                    editUrl: editUrl || '',
                    viewUrl: viewUrl || '',
                    forceConfirm: true,
                    missing: false
                });
                resultsEl.innerHTML = '';
                searchInput.value = '';
            });
        }
    })();
})();
