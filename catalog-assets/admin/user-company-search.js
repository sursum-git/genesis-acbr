(function () {
    function createElement(tag, attributes, text) {
        const element = document.createElement(tag);
        Object.entries(attributes || {}).forEach(([key, value]) => {
            if (value === null || value === undefined) {
                return;
            }
            element.setAttribute(key, String(value));
        });
        if (text !== undefined) {
            element.textContent = text;
        }

        return element;
    }

    function selectedIds(root) {
        return new Set(Array.from(root.querySelectorAll('input[type="hidden"][name="empresa_ids[]"]')).map((input) => input.value));
    }

    function addCompany(root, item) {
        const id = String(item.id);
        if (!id || selectedIds(root).has(id)) {
            return;
        }

        const selected = root.querySelector('[data-company-selected]');
        const empty = selected.querySelector('[data-company-empty]');
        if (empty) {
            empty.remove();
        }

        const chip = createElement('span', {
            class: 'badge text-bg-secondary d-inline-flex align-items-center gap-2',
            'data-company-chip': id,
        });
        chip.appendChild(createElement('span', {}, item.label || item.name || id));
        chip.appendChild(createElement('input', { type: 'hidden', name: 'empresa_ids[]', value: id }));

        const remove = createElement('button', {
            type: 'button',
            class: 'btn-close btn-close-white',
            'aria-label': 'Remover empresa',
            'data-company-remove': id,
        });
        chip.appendChild(remove);
        selected.appendChild(chip);
    }

    function renderResults(root, items) {
        const results = root.querySelector('[data-company-results]');
        results.innerHTML = '';

        if (!items.length) {
            results.appendChild(createElement('div', { class: 'list-group-item text-body-secondary small' }, 'Nenhuma empresa encontrada.'));
            return;
        }

        const ids = selectedIds(root);
        items.forEach((item) => {
            const id = String(item.id);
            const button = createElement('button', {
                type: 'button',
                class: 'list-group-item list-group-item-action py-1',
                'data-company-add': id,
                disabled: ids.has(id) ? 'disabled' : null,
            }, item.label || item.name || id);
            button.dataset.companyPayload = JSON.stringify(item);
            results.appendChild(button);
        });
    }

    function initialize(root) {
        const input = root.querySelector('[data-company-input]');
        const results = root.querySelector('[data-company-results]');
        const url = root.dataset.companySearchUrl || '';
        if (!input || !results || !url || input.disabled) {
            return;
        }

        let timeout = null;
        input.addEventListener('input', () => {
            window.clearTimeout(timeout);
            const query = input.value.trim();
            if (query.length < 2) {
                results.innerHTML = '';
                return;
            }

            timeout = window.setTimeout(async () => {
                results.innerHTML = '<div class="list-group-item text-body-secondary small">Buscando...</div>';
                try {
                    const response = await window.fetch(url + '?q=' + encodeURIComponent(query), {
                        headers: { Accept: 'application/json' },
                    });
                    const payload = await response.json();
                    renderResults(root, Array.isArray(payload.items) ? payload.items : []);
                } catch (error) {
                    results.innerHTML = '<div class="list-group-item text-danger small">Falha ao buscar empresas.</div>';
                }
            }, 250);
        });

        root.addEventListener('click', (event) => {
            const addButton = event.target.closest('[data-company-add]');
            if (addButton) {
                addCompany(root, JSON.parse(addButton.dataset.companyPayload || '{}'));
                addButton.disabled = true;
                input.value = '';
                results.innerHTML = '';
                return;
            }

            const removeButton = event.target.closest('[data-company-remove]');
            if (removeButton) {
                removeButton.closest('[data-company-chip]')?.remove();
            }
        });
    }

    document.querySelectorAll('[data-company-search]').forEach(initialize);
})();
