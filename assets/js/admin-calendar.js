(function(){
    function onReady(cb){
        if(document.readyState === 'interactive' || document.readyState === 'complete'){
            cb();
        } else {
            document.addEventListener('DOMContentLoaded', cb);
        }
    }

    onReady(function(){
        var container = document.getElementById('eh-admin-calendar');
        if (!container || typeof FullCalendar === 'undefined' || typeof eventHubCalendar === 'undefined') {
            return;
        }

        var activeStatus = '';
        var activeTerm = '';
        var activeOnline = '';

        var calendar = new FullCalendar.Calendar(container, {
            initialView: 'dayGridMonth',
            firstDay: 1,
            height: 'auto',
            locale: 'nl',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
            events: function(fetchInfo, successCallback, failureCallback){
                var params = new URLSearchParams({
                    start: fetchInfo.startStr,
                    end: fetchInfo.endStr
                });
                fetch(eventHubCalendar.restUrl + '?' + params.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': eventHubCalendar.nonce
                    }
                }).then(function(response){
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                }).then(function(events){
                    successCallback(Array.isArray(events) ? events : []);
                }).catch(function(error){
                    console.error('Event Hub kalender', error);
                    failureCallback(error || eventHubCalendar.labels.error);
                });
            },
            eventClick: function(info){
                if (info.event && info.event.url) {
                    info.jsEvent.preventDefault();
                    window.open(info.event.url, '_blank');
                }
            },
            dateClick: function(info){
                if (!eventHubCalendar.newEventUrl) {
                    return;
                }
                var start = info.dateStr;
                var url = eventHubCalendar.newEventUrl + '&eh_start=' + encodeURIComponent(start);
                window.open(url, '_blank');
            }
        });

        calendar.render();

        function refilter() {
            var evs = calendar.getEvents();
            evs.forEach(function(ev){
                var show = true;
                var props = ev.extendedProps || {};
                if (activeStatus && props.status !== activeStatus) { show = false; }
                if (activeOnline && String(props.is_online) !== activeOnline) { show = false; }
                if (activeTerm) {
                    var terms = props.terms || [];
                    if (terms.indexOf(activeTerm) === -1) { show = false; }
                }
                ev.setProp('display', show ? 'auto' : 'none');
            });
        }

        var statusChips = document.querySelectorAll('.eh-chip[data-status]');
        statusChips.forEach(function(chip){
            chip.addEventListener('click', function(){
                statusChips.forEach(function(c){ c.classList.remove('active'); });
                chip.classList.add('active');
                activeStatus = chip.getAttribute('data-status') || '';
                refilter();
            });
        });

        var termSelect = document.getElementById('eh-cal-term');
        if (termSelect) {
            termSelect.addEventListener('change', function(){
                activeTerm = termSelect.value || '';
                refilter();
            });
        }
        var onlineSelect = document.getElementById('eh-cal-online');
        if (onlineSelect) {
            onlineSelect.addEventListener('change', function(){
                activeOnline = onlineSelect.value || '';
                refilter();
            });
        }

        // Quick peek modal
        var modal;
        function closeModal() {
            if (modal) {
                modal.remove();
                modal = null;
            }
        }

        function badge(text, cls) {
            var span = document.createElement('span');
            span.className = 'eh-badge-pill ' + (cls || '');
            span.textContent = text;
            return span;
        }

        calendar.setOption('eventClick', function(info){
            info.jsEvent.preventDefault();
            var ev = info.event;
            var props = ev.extendedProps || {};
            closeModal();

            modal = document.createElement('div');
            modal.className = 'eh-cal-modal';
            modal.innerHTML = '<div class="eh-cal-modal__backdrop"></div><div class="eh-cal-modal__card"><button class="eh-cal-modal__close" aria-label="Close">×</button><div class="eh-cal-modal__body"></div></div>';
            document.body.appendChild(modal);

            modal.querySelector('.eh-cal-modal__close').addEventListener('click', closeModal);
            modal.querySelector('.eh-cal-modal__backdrop').addEventListener('click', closeModal);

            var body = modal.querySelector('.eh-cal-modal__body');
            var title = document.createElement('h3');
            title.textContent = ev.title || '';
            body.appendChild(title);

            var meta = document.createElement('div');
            meta.className = 'eh-cal-meta';
            var status = (props.status || '').toLowerCase();
            meta.appendChild(badge(status ? status : 'status', 'status-' + status));
            if (props.is_online) {
                meta.appendChild(badge('Online', 'status-online'));
            } else if (props.location) {
                var loc = badge(props.location, '');
                loc.classList.add('status-location');
                meta.appendChild(loc);
            }
            body.appendChild(meta);

            var when = document.createElement('div');
            when.className = 'eh-cal-when';
            var start = ev.start ? ev.start.toLocaleString() : '';
            var end = ev.end ? ev.end.toLocaleString() : '';
            when.textContent = end ? (start + ' - ' + end) : start;
            body.appendChild(when);

            var stats = document.createElement('div');
            stats.className = 'eh-cal-stats';
            var capacity = props.capacity && props.capacity > 0 ? props.capacity : null;
            var booked = props.booked || 0;
            var waitlist = props.waitlist || 0;
            var items = [
                {label: 'Inschrijvingen', value: booked + (capacity ? (' / ' + capacity) : '')},
                {label: 'Wachtlijst', value: waitlist}
            ];
            items.forEach(function(item){
                var row = document.createElement('div');
                row.className = 'eh-cal-stat';
                row.innerHTML = '<span>' + item.label + '</span><strong>' + item.value + '</strong>';
                stats.appendChild(row);
            });
            body.appendChild(stats);

            var actions = document.createElement('div');
            actions.className = 'eh-cal-actions';
            var eventId = props.event_id;
            var occId = props.occurrence_id || '';
            function buildUrl(base, extra) {
                var url = base;
                url += (base.indexOf('?') === -1 ? '?' : '&') + 'session_id=' + encodeURIComponent(eventId);
                if (occId) {
                    url += '&occurrence_id=' + encodeURIComponent(occId);
                }
                if (extra) {
                    url += extra;
                }
                return url;
            }
            var dashboardUrl = eventHubCalendar.dashboardBase + '&event_id=' + encodeURIComponent(eventId) + (occId ? '&occurrence_id=' + encodeURIComponent(occId) : '');
            var regsUrl = buildUrl(eventHubCalendar.registrationsBase);
            var newRegUrl = buildUrl(eventHubCalendar.newRegistrationBase, '&action=new');

            [
                {label:'Dashboard', url: dashboardUrl, primary:true},
                {label:'Registraties', url: regsUrl},
                {label:'Nieuwe inschrijving', url: newRegUrl}
            ].forEach(function(btn){
                var a = document.createElement('a');
                a.href = btn.url;
                a.target = '_blank';
                a.rel = 'noopener';
                a.textContent = btn.label;
                a.className = 'button' + (btn.primary ? ' button-primary' : '');
                actions.appendChild(a);
            });
            body.appendChild(actions);
        });

        // Export modal helper
        function openExportModal(events) {
            closeModal();
            modal = document.createElement('div');
            modal.className = 'eh-cal-modal';
            modal.innerHTML = '<div class="eh-cal-modal__backdrop"></div><div class="eh-cal-modal__card"><button class="eh-cal-modal__close" aria-label="Close">×</button><div class="eh-cal-modal__body"></div></div>';
            document.body.appendChild(modal);
            modal.querySelector('.eh-cal-modal__close').addEventListener('click', closeModal);
            modal.querySelector('.eh-cal-modal__backdrop').addEventListener('click', closeModal);

            var body = modal.querySelector('.eh-cal-modal__body');
            var title = document.createElement('h3');
            title.textContent = eventHubCalendar.labels.export_title || 'Selecteer events';
            body.appendChild(title);

            if (!events.length) {
                var empty = document.createElement('p');
                empty.textContent = eventHubCalendar.labels.export_none || 'Geen events in deze maand.';
                body.appendChild(empty);
                return;
            }

            var help = document.createElement('p');
            help.className = 'description';
            help.textContent = eventHubCalendar.labels.export_help || '';
            body.appendChild(help);

            var list = document.createElement('div');
            list.className = 'eh-export-list';
            events.forEach(function(ev){
                var props = ev.extendedProps || {};
                var label = document.createElement('label');
                label.className = 'eh-export-item';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = ev.id;
                cb.dataset.eventId = props.event_id || ev.id;
                cb.dataset.occurrenceId = props.occurrence_id || '';
                cb.checked = true;
                var titleSpan = document.createElement('span');
                titleSpan.className = 'eh-export-title';
                titleSpan.textContent = ev.title || '(zonder titel)';
                var metaSpan = document.createElement('span');
                metaSpan.className = 'eh-export-meta';
                var start = ev.start ? ev.start.toLocaleString() : '';
                metaSpan.textContent = start;
                label.appendChild(cb);
                label.appendChild(titleSpan);
                label.appendChild(metaSpan);
                list.appendChild(label);
            });
            body.appendChild(list);

            var format = document.createElement('select');
            format.innerHTML = ''
                + '<option value="csv">' + (eventHubCalendar.labels.format_csv || 'CSV') + '</option>'
                + '<option value="xlsx">' + (eventHubCalendar.labels.format_xlsx || 'XLSX') + '</option>'
                + '<option value="json">' + (eventHubCalendar.labels.format_json || 'JSON') + '</option>';

            var actions = document.createElement('div');
            actions.className = 'eh-cal-actions';
            var exportBtn = document.createElement('button');
            exportBtn.className = 'button button-primary';
            exportBtn.type = 'button';
            exportBtn.textContent = eventHubCalendar.labels.export || 'Exporteer';
            actions.appendChild(format);
            actions.appendChild(exportBtn);
            body.appendChild(actions);

            exportBtn.addEventListener('click', function(){
                var formatVal = format.value || 'csv';
                var selected = list.querySelectorAll('input[type="checkbox"]:checked');
                if (!selected.length) {
                    closeModal();
                    return;
                }
                selected.forEach(function(cb){
                    var sessionId = cb.dataset.eventId;
                    var occId = cb.dataset.occurrenceId || '';
                    var url = eventHubCalendar.exportBase + '?page=event-hub-registrations&download=' + encodeURIComponent(formatVal)
                        + '&download_nonce=' + encodeURIComponent(eventHubCalendar.exportNonce)
                        + '&session_id=' + encodeURIComponent(sessionId)
                        + (occId ? '&occurrence_id=' + encodeURIComponent(occId) : '');
                    window.open(url, '_blank');
                });
                closeModal();
            });
        }

        // Export button in toolbar
        var toolbar = container.parentElement && container.parentElement.querySelector('.fc-header-toolbar');
        if (toolbar && eventHubCalendar.exportBase && eventHubCalendar.exportNonce) {
            var exportBtn = document.createElement('button');
            exportBtn.type = 'button';
            exportBtn.className = 'button';
            exportBtn.style.marginLeft = '8px';
            exportBtn.textContent = eventHubCalendar.labels.export || 'Exporteer';
            exportBtn.addEventListener('click', function(){
                try {
                    var view = calendar.view;
                    var start = view.currentStart;
                    var end = view.currentEnd;
                    var evs = calendar.getEvents().filter(function(ev){
                        return ev.start && ev.start >= start && ev.start < end;
                    });
                    openExportModal(evs);
                } catch (e) {
                    console.warn('Event Hub export mislukte', e);
                }
            });
            var right = toolbar.querySelector('.fc-toolbar-chunk:nth-child(3)') || toolbar;
            right.appendChild(exportBtn);
        }
    });
})();
