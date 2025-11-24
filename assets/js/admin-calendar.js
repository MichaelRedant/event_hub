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
                        successCallback(payload.data || []);
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
    });
})();
