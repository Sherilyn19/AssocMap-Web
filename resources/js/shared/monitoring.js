function initializeMonitoringForm(form) {
    if (form.dataset.initialized) return;
    form.dataset.initialized = 'true';

    const project = form.elements.namedItem('project_id');
    const material = form.elements.namedItem('project_material_id');

    if (project && material instanceof HTMLSelectElement) {
        const options = [...material.options];

        const filter = () => {
            const previous = material.value;
            material.replaceChildren(...options.filter(option =>
                !option.value || option.dataset.project === project.value
            ));
            material.value = [...material.options]
                .some(option => option.value === previous) ? previous : '';
        };

        project.addEventListener('change', filter);
        filter();
    }

    const unit = form.elements.namedItem('output_unit_code');
    const specification = form.elements.namedItem('output_unit_spec');
    const settingsElement = form.querySelector('[data-project-unit-data]');

    if (project && unit instanceof HTMLSelectElement && specification && settingsElement) {
        const settings = JSON.parse(settingsElement.textContent);
        const packaged = ['pack', 'bottle', 'jar', 'can', 'box', 'bag', 'tray'];

        const syncPackage = () => {
            specification.required = packaged.includes(unit.value);
        };

        const syncProject = () => {
            const fixed = settings[project.value] || {};

            for (const option of unit.options) {
                option.disabled = Boolean(fixed.code) && option.value !== fixed.code;
            }

            if (fixed.code) {
                unit.value = fixed.code;
                specification.value = fixed.spec || '';
            }

            specification.readOnly = Boolean(fixed.code);
            syncPackage();
        };

        project.addEventListener('change', syncProject);
        unit.addEventListener('change', syncPackage);
        syncProject();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-monitoring-form]').forEach(initializeMonitoringForm);
    if (!document.querySelector('[data-monitoring-open]')) return;

    const dialog = document.createElement('dialog');
    dialog.className = 'am-monitoring-dialog';
    dialog.setAttribute('aria-labelledby', 'monitoring-dialog-title');
    dialog.innerHTML = `
        <header class="am-monitoring-dialog__header">
            <div>
                <p class="am-monitoring-dialog__eyebrow">BFAR SAAD Phase II / Monitoring</p>
                <h2 id="monitoring-dialog-title">Monitoring details</h2>
            </div>
            <button type="button" data-monitoring-close class="am-monitoring-close">
                Close <span aria-hidden="true">&times;</span>
            </button>
        </header>
        <div class="am-monitoring-dialog__body">
            <div data-monitoring-message role="alert" tabindex="-1" hidden></div>
            <button type="button" data-monitoring-refresh class="fo-action" hidden>
                Reload records
            </button>
            <div data-monitoring-content></div>
        </div>
    `;
    document.body.append(dialog);

    const content = dialog.querySelector('[data-monitoring-content]');
    const message = dialog.querySelector('[data-monitoring-message]');
    const closeButton = dialog.querySelector('[data-monitoring-close]');
    const refreshButton = dialog.querySelector('[data-monitoring-refresh]');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');

    let busy = false;
    let closing = false;
    let opener = null;

    const loading = label => document.dispatchEvent(
        new CustomEvent('management:loading', { detail: { label, managed: true } })
    );
    const loaded = () => document.dispatchEvent(new Event('management:loaded'));

    function report(text) {
        message.textContent = text;
        message.hidden = false;
        message.focus();
    }

    async function fetchWithTimeout(url, options = {}) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 60000);

        try {
            return await fetch(url, {
                credentials: 'same-origin',
                ...options,
                signal: controller.signal,
            });
        } finally {
            clearTimeout(timer);
        }
    }

    async function close() {
        if (busy || closing || !dialog.open) return;
        closing = true;

        if (!reducedMotion.matches) {
            await dialog.animate(
                [{ opacity: 1, transform: 'scale(1)' }, { opacity: 0, transform: 'scale(.985)' }],
                { duration: 140, easing: 'ease-in' }
            ).finished.catch(() => {});
        }

        dialog.close();
        closing = false;
    }

    closeButton.addEventListener('click', close);
    refreshButton.addEventListener('click', () => location.reload());

    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        close();
    });

    dialog.addEventListener('close', () => {
        content.replaceChildren();
        opener?.focus();
    });

    async function openPanel(link) {
        if (busy || closing) return;
        const alreadyOpen = dialog.open;
        const mode = link.dataset.monitoringOpen;

        if (!alreadyOpen) opener = link;

        busy = true;
        closeButton.disabled = true;
        message.hidden = true;
        refreshButton.hidden = true;

        if (!alreadyOpen) {
            dialog.classList.toggle('is-drawer', mode === 'edit' || mode === 'details');
            dialog.classList.toggle('is-achievement', mode === 'achievement');
            content.replaceChildren();
            dialog.showModal();
        }

        loading('Loading monitoring details…');

        try {
            const response = await fetchWithTimeout(link.href, {
                headers: { Accept: 'text/html' },
            });

            if (!response.ok || response.redirected) {
                throw new Error('Details are unavailable. Your access or the record may have changed.');
            }

            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const panel = parsed.querySelector('[data-monitoring-panel]');
            if (!panel) throw new Error('The monitoring panel could not be loaded.');

            // Never execute scripts from a fetched dashboard document.
            panel.querySelectorAll('script:not([type="application/json"])')
                .forEach(script => script.remove());

            const title = panel.dataset.panelTitle
                || panel.querySelector('h1')?.textContent.trim()
                || 'Monitoring details';

            dialog.querySelector('#monitoring-dialog-title').textContent = title;
            content.replaceChildren(document.importNode(panel, true));

            const form = content.querySelector('[data-monitoring-form]');
            if (form) initializeMonitoringForm(form);

            dialog.querySelector('.am-monitoring-dialog__body').scrollTop = 0;
        } catch (error) {
            report(error.name === 'AbortError'
                ? 'Loading took too long. Close the panel and try again.'
                : error.message);
        } finally {
            busy = false;
            closeButton.disabled = false;
            loaded();
            if (!message.hidden) message.focus();
            else closeButton.focus();
        }
    }

    // Intercept only explicitly marked links; normal filters and pagination still work.
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-monitoring-open]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey
            || event.shiftKey || event.altKey) return;

        event.preventDefault();
        openPanel(link);
    }, true);

    dialog.addEventListener('click', event => {
        if (!event.target.closest('[data-monitoring-cancel]')) return;
        event.preventDefault();
        close();
    });

    dialog.addEventListener('submit', async event => {
        const form = event.target.closest('[data-monitoring-form]');
        if (!form) return;
        event.preventDefault();

        if (busy || !form.reportValidity()) return;

        const button = form.querySelector('button[type="submit"]');
        const label = button.textContent;
        let uncertain = false;
        let saved = false;

        busy = true;
        button.disabled = true;
        closeButton.disabled = true;
        button.textContent = 'Saving…';
        message.hidden = true;
        refreshButton.hidden = true;

        form.querySelectorAll('[data-monitoring-field-error]')
            .forEach(element => { element.textContent = ''; });
        form.querySelectorAll('[aria-invalid]')
            .forEach(element => element.removeAttribute('aria-invalid'));

        loading('Saving monitoring record…');

        try {
            const response = await fetchWithTimeout(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });
            const result = await response.json().catch(() => null);

            if (response.status === 422) {
                const errors = result?.errors || {};

                for (const [name, texts] of Object.entries(errors)) {
                    form.elements.namedItem(name)?.setAttribute('aria-invalid', 'true');

                    const output = [...form.querySelectorAll('[data-monitoring-field-error]')]
                        .find(element => element.dataset.monitoringFieldError === name);

                    if (output) output.textContent = texts.join(' ');
                }

                report(Object.values(errors).flat().join(' ')
                    || result?.message || 'Check the entered values.');
                return;
            }

            if (!response.ok || response.redirected || !result) {
                uncertain = response.status >= 500 || response.redirected || !result;
                throw new Error(result?.message || 'The save could not be confirmed.');
            }

            saved = true;
            location.reload();
        } catch (error) {
            if (error.name === 'AbortError' || error instanceof TypeError) uncertain = true;

            report(uncertain
                ? 'The save could not be confirmed. Reload records and check the entry before submitting again.'
                : error.message);

            refreshButton.hidden = !uncertain;
        } finally {
            busy = false;
            closeButton.disabled = false;
            button.disabled = uncertain || saved;
            button.textContent = label;
            loaded();
            if (!message.hidden) message.focus();
        }
    });
});

// Preserve the existing Administrator income disclosures.
document.addEventListener('click', event => {
    const details = event.target.closest('[data-income-close]')?.closest('[data-income-details]');
    if (!details) return;
    details.open = false;
    details.querySelector('summary')?.focus();
});

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const details = event.target.closest('[data-income-details][open]');
    if (!details) return;
    details.open = false;
    details.querySelector('summary')?.focus();
});