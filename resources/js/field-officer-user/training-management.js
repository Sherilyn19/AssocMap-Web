/**
 * Training create/edit/attendance panels.
 * Native dialogs provide keyboard focus containment.
 * The existing management loader covers all panel requests and saves.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('am-officer')) return;

    const dialog = document.createElement('dialog');
    dialog.className = 'fo-training-editor fo-training-ui';
    dialog.setAttribute('aria-labelledby', 'fo-training-panel-title');
    dialog.innerHTML = `
        <header class="fo-training-editor__header">
            <h2 id="fo-training-panel-title" class="font-semibold"></h2>
            <button type="button" class="fo-action" data-training-close>Close</button>
        </header>
        <div class="fo-training-editor__body" data-training-body></div>
    `;
    document.body.append(dialog);

    const body = dialog.querySelector('[data-training-body]');
    const closeButton = dialog.querySelector('[data-training-close]');
    let busy = false;
    let dirty = false;
    let opener = null;
    let currentUrl = null;
    let refreshRegister = false;

    const loading = (label) => {
        document.dispatchEvent(new CustomEvent('management:loading', {
            detail: { label, managed: true },
        }));
    };

    const stopLoading = () => {
        document.dispatchEvent(new Event('management:loaded'));
    };

    function notice(message, success = false) {
        body.querySelector('[data-training-notice]')?.remove();
        const box = document.createElement('p');
        box.dataset.trainingNotice = '';
        box.className = success
            ? 'rounded-lg border border-emerald-200 bg-emerald-50 p-3 mb-4 text-emerald-900'
            : 'fo-warning mb-4';
        box.setAttribute('role', success ? 'status' : 'alert');
        box.tabIndex = -1;
        box.textContent = message;
        body.prepend(box);
        box.focus();
    }

    async function fragment(url) {
        const target = new URL(url, location.href);
        target.searchParams.set('fragment', '1');
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 30000);

        try {
            const response = await fetch(target, {
                signal: controller.signal,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok || response.redirected) {
                throw new Error('This panel is unavailable. Refresh and check your access.');
            }

            const documentCopy = new DOMParser().parseFromString(
                await response.text(), 'text/html'
            );
            const content = documentCopy.querySelector('[data-training-fragment]');

            if (!content) throw new Error('The panel response was incomplete.');
            body.replaceChildren(document.importNode(content, true));
        } finally {
            clearTimeout(timer);
        }
    }

    function closePanel() {
        if (busy) return;
        if (dirty && !window.confirm('Discard your unsaved changes?')) return;

        dirty = false;
        dialog.close();

        // Refresh stale table counts after the user finishes working in the drawer.
        if (refreshRegister) {
            location.reload();
        } else {
            opener?.focus();
        }
    }

    closeButton.addEventListener('click', closePanel);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closePanel();
    });
    dialog.addEventListener('input', () => { dirty = true; });

    // Capture the click before the shared navigation loader handles it.
    document.addEventListener('click', async (event) => {
        const link = event.target instanceof Element
            ? event.target.closest('a[data-training-panel], .fo-training-editor .pagination a')
            : null;

        // Laravel pagination markup is handled by its navigation landmark below.
        const pageLink = event.target instanceof Element
            ? event.target.closest('.fo-training-editor nav[role="navigation"] a')
            : null;

        const target = link || pageLink;
        if (!target || event.button !== 0 || event.ctrlKey || event.metaKey
            || event.shiftKey || event.altKey) return;

        event.preventDefault();
        if (busy) return;
        if (dirty && !window.confirm('Discard your unsaved changes?')) return;

        if (target.hasAttribute('data-training-panel')) {
            opener = target;
            dialog.querySelector('h2').textContent =
                target.dataset.panelTitle || 'Training details';
            dialog.classList.toggle(
                'is-create', target.hasAttribute('data-panel-create')
            );
        }

        currentUrl = target.href;
        dirty = false;
        busy = true;
        body.replaceChildren();
        if (!dialog.open) dialog.showModal();
        loading('Loading training information…');

        try {
            await fragment(currentUrl);
        } catch (error) {
            notice(error.name === 'AbortError'
                ? 'Loading took too long. Close this panel and try again.'
                : error.message);
        } finally {
            busy = false;
            stopLoading();
            (body.querySelector('[data-training-notice]')
                || body.querySelector('input:not([type="hidden"]), select, textarea')
                || closeButton).focus();
        }
    }, true);

    dialog.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-training-write]');
        if (!form) return;

        event.preventDefault();
        if (busy || !form.reportValidity()) return;

        body.querySelectorAll('[data-ajax-error]').forEach(node => node.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach(node => {
            node.removeAttribute('aria-invalid');
        });

        const payload = new FormData(form);
        const buttons = [...form.querySelectorAll('button')];
        const previousStates = buttons.map(button => button.disabled);
        buttons.forEach(button => { button.disabled = true; });
        busy = true;
        loading('Saving training information…');

        try {
            // Do not automatically retry writes: a lost response can follow a commit.
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || response.redirected) {
                for (const [field, messages] of Object.entries(data.errors || {})) {
                    const control = form.elements.namedItem(field);
                    if (!(control instanceof HTMLElement)) continue;

                    control.setAttribute('aria-invalid', 'true');
                    const error = document.createElement('p');
                    error.dataset.ajaxError = '';
                    error.className = 'mt-1 text-sm text-red-800';
                    error.textContent = messages.join(' ');
                    control.insertAdjacentElement('afterend', error);
                }

                throw new Error(response.status === 419
                    ? 'Your session expired. Refresh the page before trying again.'
                    : response.status === 422
                        ? 'Please correct the highlighted fields.'
                        : data.message || 'The change could not be confirmed. Check the record before retrying.');
            }

            dirty = false;
            refreshRegister = true;

            // Attendance remains open for the next participant.
            if (currentUrl && new URL(currentUrl).pathname.endsWith('/attendance')) {
                try {
                    await fragment(currentUrl);
                } catch {
                    notice(`${data.message} Close the panel to refresh the records.`, true);
                    return;
                }
                notice(data.message || 'Attendance saved.', true);
            } else {
                // Saving is complete. Refresh without showing an unsaved-work warning.
                busy = false;
                location.reload();
            }
        } catch (error) {
            notice(error instanceof TypeError
                ? 'The connection was interrupted. Check the record before retrying.'
                : error.message);
        } finally {
            busy = false;
            buttons.forEach((button, index) => {
                button.disabled = previousStates[index];
            });
            stopLoading();
            body.querySelector('[data-training-notice]')?.focus();
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty || busy) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
});