// Scan page: collapsible FTP account sections and tile/list view per section
// (both stored per user), plus live refresh when new uploads arrive.
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

    function post(url, payload) {
        var headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        headers[header] = token;

        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: headers, body: JSON.stringify(payload) });
    }

    function setView(section, view) {
        section.classList.toggle('view-list', view === 'list');
        section.classList.toggle('view-grid', view !== 'list');
        section.querySelectorAll('[data-view]').forEach(function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-view') === view ? 'true' : 'false');
        });
    }

    // View switch buttons sit inside <summary>: do not toggle the section.
    container.addEventListener('click', function (event) {
        var button = event.target.closest('[data-view]');
        if (!button) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        var section = button.closest('details.account');
        var view = button.getAttribute('data-view');
        setView(section, view);
        post(container.getAttribute('data-view-url'), { account: section.getAttribute('data-account'), view: view });
    });

    // List view: select all/none and delete the selection.
    function updateBulk(section) {
        var count = section.querySelectorAll('.scan-list input[name="ids[]"]:checked').length;
        var button = section.querySelector('.bulk-delete button');
        if (button) {
            button.disabled = count === 0;
            button.textContent = count > 0 ? 'Ausgewählte löschen (' + count + ')' : 'Ausgewählte löschen';
        }
    }

    container.addEventListener('click', function (event) {
        var button = event.target.closest('[data-select]');
        if (!button) {
            return;
        }
        var section = button.closest('details.account');
        var all = button.getAttribute('data-select') === 'all';
        section.querySelectorAll('.scan-list input[name="ids[]"]').forEach(function (box) { box.checked = all; });
        updateBulk(section);
    });

    container.addEventListener('change', function (event) {
        if (event.target.matches('.scan-list input[name="ids[]"]')) {
            updateBulk(event.target.closest('details.account'));
        }
    });

    container.addEventListener('submit', function (event) {
        if (!event.target.matches('.bulk-delete')) {
            return;
        }
        var section = event.target.closest('details.account');
        var count = section.querySelectorAll('.scan-list input[name="ids[]"]:checked').length;
        if (count === 0 || !window.confirm(count === 1 ? '1 Scan wirklich löschen?' : count + ' Scans wirklich löschen?')) {
            event.preventDefault();
        }
    });

    function save() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            var collapsed = sections().filter(function (s) { return !s.open; })
                .map(function (s) { return s.getAttribute('data-account'); });
            post(container.getAttribute('data-save-url'), { collapsed: collapsed });
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
                // Keep the open/closed state and view the user has right now.
                var state = {};
                var views = {};
                var checked = Array.prototype.map.call(container.querySelectorAll('.scan-list input[name="ids[]"]:checked'), function (b) { return b.value; });
                sections().forEach(function (s) {
                    state[s.getAttribute('data-account')] = s.open;
                    views[s.getAttribute('data-account')] = s.classList.contains('view-list') ? 'list' : 'grid';
                });
                swapping = true;
                container.innerHTML = fresh.innerHTML;
                sections().forEach(function (s) {
                    var id = s.getAttribute('data-account');
                    if (id in state) {
                        s.open = state[id];
                        setView(s, views[id]);
                    }
                });
                // Keep the selection of the list view (deleted scans simply disappear).
                container.querySelectorAll('.scan-list input[name="ids[]"]').forEach(function (box) {
                    box.checked = checked.indexOf(box.value) !== -1;
                });
                sections().forEach(updateBulk);
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
