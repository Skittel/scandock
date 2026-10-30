// Scan page: collapsible FTP account sections (stored per user) and live
// refresh of the list when new uploads arrive.
(function () {
    'use strict';

    var container = document.querySelector('.accordion');
    if (!container) {
        return;
    }
    var header = document.querySelector('meta[name="csrf-header"]').content;
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var saveTimer = null;
    var bulk = false;
    var swapping = false;

    function sections() {
        return Array.prototype.slice.call(container.querySelectorAll('details.account'));
    }

    function save() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            var collapsed = sections().filter(function (s) { return !s.open; })
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

    // "toggle" does not bubble, so listen in the capture phase.
    container.addEventListener('toggle', function (event) {
        if (!bulk && !swapping && event.target.matches('details.account')) {
            save();
        }
    }, true);

    document.querySelectorAll('[data-accordion]').forEach(function (button) {
        button.addEventListener('click', function () {
            var open = button.getAttribute('data-accordion') === 'open';
            bulk = true;
            sections().forEach(function (section) { section.open = open; });
            setTimeout(function () { bulk = false; save(); }, 0);
        });
    });

    // Live refresh: poll a fingerprint, reload the list only when it changed.
    var statusUrl = container.getAttribute('data-status-url');
    var fingerprint = null;
    var pending = container.querySelector('.scan.pending') !== null;

    function refreshList() {
        return fetch(window.location.href, { credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('.accordion');
                if (!fresh) {
                    return;
                }
                // Keep the open/closed state the user has right now.
                var state = {};
                sections().forEach(function (s) { state[s.getAttribute('data-account')] = s.open; });
                swapping = true;
                container.innerHTML = fresh.innerHTML;
                sections().forEach(function (s) {
                    var id = s.getAttribute('data-account');
                    if (id in state) {
                        s.open = state[id];
                    }
                });
                setTimeout(function () { swapping = false; }, 0);
                pending = container.querySelector('.scan.pending') !== null;
            });
    }

    function poll() {
        if (document.hidden) {
            schedule();
            return;
        }
        fetch(statusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data) {
                    return null;
                }
                var changed = fingerprint !== null && data.fingerprint !== fingerprint;
                fingerprint = data.fingerprint;
                pending = data.pending > 0;

                return changed ? refreshList() : null;
            })
            .catch(function () { /* try again later */ })
            .then(schedule);
    }

    function schedule() {
        // Faster while uploads are pending.
        setTimeout(poll, pending ? 2000 : 6000);
    }

    poll();
})();
