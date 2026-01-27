(function () {
    if (typeof EventHubEventsList === 'undefined' || !EventHubEventsList.dashboardBase) {
        return;
    }

    var dashboardBase = EventHubEventsList.dashboardBase;
    var cpt = EventHubEventsList.cpt || '';
    var title = EventHubEventsList.title || '';
    var selector = '#the-list tr' + (cpt ? '.type-' + cpt : '');
    var rows = document.querySelectorAll(selector);

    Array.prototype.forEach.call(rows, function (row) {
        var idAttr = row.getAttribute('id') || '';
        var postId = idAttr.replace('post-', '');
        if (!postId) {
            return;
        }

        var titleLink = row.querySelector('a.row-title');
        if (!titleLink) {
            return;
        }

        var url = dashboardBase + '&event_id=' + encodeURIComponent(postId);
        titleLink.setAttribute('href', url);
        if (title) {
            titleLink.setAttribute('title', title);
            titleLink.setAttribute('aria-label', title);
        }
    });
})();
