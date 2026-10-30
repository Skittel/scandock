// Collapsible FTP account sections on the scan page. The collapsed sections
// are stored per user on the server.
(function () {
    'use strict';

    var container = document.querySelector('.accordion');
    if (!container) {
        return;
    }
    var sections = Array.prototype.slice.call(container.querySelectorAll('details.account'));
    var header = document.querySelector('meta[name="csrf-header"]').content;
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var timer = null;
    var bulk = false;

    function save() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var collapsed = sections.filter(function (s) { return !s.open; })
                .map(function (s) { return parseInt(s.getAttribute('data-account'), 10); });
            var headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            headers[header] = token;
            fetch(container.getAttribute('data-save-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify({ collapsed: collapsed })
            });
        }, 300);
    }

    sections.forEach(function (section) {
        section.addEventListener('toggle', function () {
            if (!bulk) {
                save();
            }
        });
    });

    document.querySelectorAll('[data-accordion]').forEach(function (button) {
        button.addEventListener('click', function () {
            var open = button.getAttribute('data-accordion') === 'open';
            bulk = true;
            sections.forEach(function (section) { section.open = open; });
            // "toggle" events fire asynchronously; save once afterwards.
            setTimeout(function () { bulk = false; save(); }, 0);
        });
    });
})();
