// Autocomplete for adding users and groups to a private FTP account.
(function () {
    'use strict';

    document.querySelectorAll('form.member-picker').forEach(function (form) {
        var input = form.querySelector('input[name="member_ref"]');
        var type = form.querySelector('input[name="member_type"]');
        var label = form.querySelector('input[name="label"]');
        var list = form.querySelector('.suggestions');
        var url = form.getAttribute('data-search-url');
        var timer = null;
        var active = -1;
        var items = [];

        function close() {
            list.hidden = true;
            list.innerHTML = '';
            active = -1;
        }

        function choose(item) {
            input.value = item.ref;
            type.value = item.type;
            label.value = item.label;
            close();
            form.submit();
        }

        function render(results) {
            items = results;
            list.innerHTML = '';
            active = -1;
            if (!results.length) {
                close();
                return;
            }
            results.forEach(function (item, index) {
                var li = document.createElement('li');
                var name = document.createElement('strong');
                var detail = document.createElement('small');
                name.textContent = item.label;
                detail.textContent = (item.type === 'group' ? 'Gruppe · ' : 'Person · ') + item.detail;
                li.appendChild(name);
                li.appendChild(detail);
                li.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(items[index]);
                });
                list.appendChild(li);
            });
            list.hidden = false;
        }

        function highlight(index) {
            var nodes = list.querySelectorAll('li');
            nodes.forEach(function (node) { node.classList.remove('active'); });
            if (index >= 0 && index < nodes.length) {
                nodes[index].classList.add('active');
            }
            active = index;
        }

        input.addEventListener('input', function () {
            // Typing manually invalidates a previous selection.
            type.value = '';
            label.value = '';
            clearTimeout(timer);
            var query = input.value.trim();
            if (query.length < 2) {
                close();
                return;
            }
            timer = setTimeout(function () {
                fetch(url + '?q=' + encodeURIComponent(query), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : { results: [] }; })
                    .then(function (data) {
                        if (input.value.trim() === query) {
                            render(data.results || []);
                        }
                    })
                    .catch(close);
            }, 250);
        });

        input.addEventListener('keydown', function (event) {
            if (list.hidden) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(Math.min(active + 1, items.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(Math.max(active - 1, 0));
            } else if (event.key === 'Enter' && active >= 0) {
                event.preventDefault();
                choose(items[active]);
            } else if (event.key === 'Escape') {
                close();
            }
        });

        input.addEventListener('blur', function () { setTimeout(close, 150); });
    });
})();
