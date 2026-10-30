// Eye icon next to a masked secret: loads the value on demand and toggles it.
(function () {
    'use strict';

    document.querySelectorAll('.secret').forEach(function (box) {
        var value = box.querySelector('.secret-value');
        var button = box.querySelector('.secret-toggle');
        var strike = button.querySelector('.eye-off');
        var mask = value.textContent;
        var plain = null;
        var shown = false;

        function show(visible) {
            shown = visible;
            value.textContent = visible ? plain : mask;
            strike.hidden = !visible;
            var text = visible ? 'Passwort verbergen' : 'Passwort anzeigen';
            button.title = text;
            button.setAttribute('aria-label', text);
        }

        button.addEventListener('click', function () {
            if (shown) {
                show(false);
                return;
            }
            if (plain !== null) {
                show(true);
                return;
            }
            fetch(box.getAttribute('data-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                .then(function (data) {
                    plain = data.password;
                    show(true);
                })
                .catch(function () { value.textContent = 'Fehler beim Laden'; });
        });
    });
})();
