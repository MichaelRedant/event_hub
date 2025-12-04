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
                    action: 'event_hub_calendar_events',
                    start: fetchInfo.startStr,
                    end: fetchInfo.endStr,
                    _ajax_nonce: eventHubCalendar.nonce
                });
                fetch(eventHubCalendar.ajaxUrl + '?' + params.toString(), {
                    credentials: 'same-origin'
                }).then(function(response){
                    return response.json();
                }).then(function(payload){
                    if (payload && payload.success) {
                        var events = payload.data || [];
                        successCallback(events);
                    } else {
                        failureCallback(payload && payload.data ? payload.data : eventHubCalendar.labels.error);
                    }
                }).catch(function(error){
                    console.error('Event Hub kalender', error);
                    failureCallback(error);
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
    });
})();
