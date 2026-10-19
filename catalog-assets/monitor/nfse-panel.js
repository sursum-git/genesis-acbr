(function () {
    const app = document.getElementById('nfse-panel-app');
    if (!app) {
        return;
    }

    const tabs = Array.from(app.querySelectorAll('[data-nfse-tab]'));
    const panels = Array.from(app.querySelectorAll('[data-nfse-panel]'));
    const status = document.getElementById('nfse-result-status');
    const output = document.getElementById('nfse-result-output');

    function openTab(name) {
        tabs.forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.nfseTab === name);
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.nfsePanel !== name;
        });
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => openTab(tab.dataset.nfseTab));
    });

    function formData(form) {
        return Object.fromEntries(new FormData(form).entries());
    }

    function showResult(response, payload) {
        status.textContent = String(response.status);
        status.className = response.ok ? 'badge text-bg-success' : 'badge text-bg-danger';
        output.textContent = JSON.stringify(payload, null, 2);
        openTab('retorno');
    }

    async function submitJson(form, url, payload) {
        const button = form.querySelector('[type="submit"]');
        if (button) {
            button.disabled = true;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const body = await response.json().catch(() => ({ mensagem: 'Resposta sem JSON.' }));
            showResult(response, body);
        } catch (error) {
            showResult({ ok: false, status: 'erro' }, { message: error.message });
        } finally {
            if (button) {
                button.disabled = false;
            }
        }
    }

    async function submitGet(form, url, payload) {
        const button = form.querySelector('[type="submit"]');
        if (button) {
            button.disabled = true;
        }

        try {
            const response = await fetch(`${url}?${new URLSearchParams(payload).toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const body = await response.json().catch(() => ({ mensagem: 'Resposta sem JSON.' }));
            showResult(response, body);
        } catch (error) {
            showResult({ ok: false, status: 'erro' }, { message: error.message });
        } finally {
            if (button) {
                button.disabled = false;
            }
        }
    }

    const bindings = [
        ['nfse-rps-form', app.dataset.emitRpsUrl],
        ['nfse-cancel-form', app.dataset.cancelUrl],
        ['nfse-substitute-form', app.dataset.substituteUrl],
        ['nfse-national-event-form', app.dataset.nationalEventUrl],
    ];

    bindings.forEach(([id, url]) => {
        const form = document.getElementById(id);
        if (!form || !url) {
            return;
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitJson(form, url, formData(form));
        });
    });

    const consultDpsForm = document.getElementById('nfse-consult-dps-form');
    if (consultDpsForm) {
        consultDpsForm.addEventListener('submit', (event) => {
            event.preventDefault();
            submitGet(consultDpsForm, app.dataset.consultDpsUrl, formData(consultDpsForm));
        });
    }

    const consultNfseForm = document.getElementById('nfse-consult-nfse-form');
    if (consultNfseForm) {
        consultNfseForm.addEventListener('submit', (event) => {
            event.preventDefault();
            submitGet(consultNfseForm, app.dataset.consultNfseUrl, formData(consultNfseForm));
        });
    }

    window.NfsePanel = {
        openTab,
    };
}());
