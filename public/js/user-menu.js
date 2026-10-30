// User menu in the header: closes on outside click / Escape and switches the
// light/dark mode instantly (stored per user on the server).
(function () {
    'use strict';

    var menu = document.querySelector('.user-menu');
    if (!menu) {
        return;
    }

    document.addEventListener('click', function (event) {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            menu.open = false;
        }
    });

    var form = menu.querySelector('[data-theme-form]');
    var header = document.querySelector('meta[name="csrf-header"]').content;
    var token = document.querySelector('meta[name="csrf-token"]').content;

    form.addEventListener('click', function (event) {
        var button = event.target.closest('button[name="theme"]');
        if (!button) {
            return;
        }
        event.preventDefault();
        var theme = button.value;
        if (theme === 'auto') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', theme);
        }
        form.querySelectorAll('button[name="theme"]').forEach(function (b) {
            b.setAttribute('aria-pressed', b === button ? 'true' : 'false');
        });
        var headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        headers[header] = token;
        fetch(form.getAttribute('action'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: JSON.stringify({ theme: theme })
        });
    });
})();
