(function(){
    function onReady(cb){
        if(document.readyState === 'interactive' || document.readyState === 'complete'){
            cb();
        } else {
            document.addEventListener('DOMContentLoaded', cb);
        }
    }

    onReady(function(){
        if (typeof FullCalendar === 'undefined' || typeof EventHubPublicCalendar === 'undefined') {
            return;
        }
        var containers = document.querySelectorAll('.eh-public-calendar');
        if (!containers.length) {
            return;
        }
        containers.forEach(function(container){
            var initialView = container.getAttribute('data-initial-view') || EventHubPublicCalendar.view || 'dayGridMonth';
            var calendar = new FullCalendar.Calendar(container, {
                initialView: initialView,
                firstDay: 1,
                height: 'auto',
                locale: 'nl',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                events: function(fetchInfo, success, fail){
                    var params = new URLSearchParams({
                        start: fetchInfo.startStr,
                        end: fetchInfo.endStr
                    });
                    fetch(EventHubPublicCalendar.restUrl + '?' + params.toString(), {
                        credentials: 'same-origin'
                    }).then(function(r){ return r.json(); })
                    .then(function(payload){
                        success(Array.isArray(payload) ? payload : []);
                    }).catch(function(err){
                        console.error('[EventHub] kalender', err);
                        fail(EventHubPublicCalendar.labels.error);
                    });
                },
                eventClick: function(info){
                    if (info.event && info.event.url) {
                        info.jsEvent.preventDefault();
                        window.open(info.event.url, '_blank');
                    }
                }
            });
            calendar.render();
        });
    });
})();
