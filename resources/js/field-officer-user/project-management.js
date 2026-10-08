/**
 * FO project editors reuse the shared loader and existing server validation.
 * Create opens centrally; editing opens as a right-side dialog.
 * Successful writes reload the current workspace to refresh its database totals.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('am-officer')) return;
    if (!document.querySelector('.fo-projects')) return;

    const editor = document.createElement('dialog');
    editor.className = 'fo-project-editor';
    editor.setAttribute('aria-labelledby', 'fo-project-editor-title');
    editor.innerHTML = `
        <header class="fo-dialog-header">
            <div>
                <p class="fo-eyebrow">Field Officer workspace</p>
                <h2 id="fo-project-editor-title"></h2>
            </div>
            <button type="button" class="fo-dialog-close" data-editor-close>
                Close <span aria-hidden="true">×</span>
            </button>
        </header>
        <div class="fo-project-editor-body" data-editor-body></div>
    `;
    document.body.append(editor);

    const body = editor.querySelector('[data-editor-body]');
    const heading = editor.querySelector('#fo-project-editor-title');
    const close = editor.querySelector('[data-editor-close]');

    let busy = false;
    let dirty = false;
    let opener = null;

    function loading(label) {
        document.dispatchEvent(new CustomEvent('management:loading', {
            detail: { label, managed: true },
        }));
    }

    function loaded() {
        document.dispatchEvent(new Event('management:loaded'));
    }

    function feedback(text) {
        let box = body.querySelector('[data-editor-feedback]');

        if (!box) {
            box = document.createElement('p');
            box.dataset.editorFeedback = '';
            box.className = 'fo-warning mb-4';
            box.setAttribute('role', 'alert');
            box.tabIndex = -1;
            body.prepend(box);
        }

        // Server messages are text, never inserted as executable markup.
        box.textContent = text;
        box.focus();
    }

    function dismiss() {
        if (busy) return;
        if (dirty && !confirm('Discard your unsaved changes?')) return;

        editor.close();
    }

    close.addEventListener('click', dismiss);
    editor.addEventListener('cancel', event => {
        event.preventDefault();
        dismiss();
    });

    editor.addEventListener('close', () => {
        dirty = false;
        body.replaceChildren();
        if (opener?.isConnected) opener.focus();
    });

    editor.addEventListener('input', event => {
        // Archive confirmation is not an unsaved profile change.
        if (event.target.closest('form:not([data-project-archive])')) {
            dirty = true;
        }
    });

    document.addEventListener('click', async event => {
        const link = event.target.closest('a[data-project-manage]');

        if (!link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        if (busy) return;

        opener = link;
        heading.textContent = link.dataset.editorTitle || 'Manage project';
        editor.classList.toggle('is-create', link.hasAttribute('data-editor-create'));
        body.replaceChildren();
        dirty = false;
        editor.showModal();
        busy = true;

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 30000);

        loading('Loading editor…');

        try {
            const response = await fetch(link.href, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
                signal: controller.signal,
            });

            if (!response.ok || response.redirected) {
                throw new Error('The editor is unavailable. Refresh and try again.');
            }

            const page = new DOMParser().parseFromString(
                await response.text(),
                'text/html'
            );
            const fragment = page.querySelector('[data-project-editor]');

            if (!fragment) throw new Error('The editor content could not be loaded.');

            body.append(document.importNode(fragment, true));
        } catch (error) {
            feedback(error.name === 'AbortError'
                ? 'The editor took too long to load. Close it and try again.'
                : error.message);
        } finally {
            clearTimeout(timer);
            busy = false;
            loaded();

            const target = body.querySelector('[data-editor-feedback]')
                || body.querySelector('input:not([type="hidden"]), select')
                || close;

            target.focus();
        }
    });

    editor.addEventListener('submit', async event => {
        const form = event.target.closest('form[data-project-write]');
        if (!form) return;

        event.preventDefault();
        if (busy || !form.reportValidity()) return;

        if (form.hasAttribute('data-project-archive') && dirty) {
            feedback('Save your changes or close and reopen the editor before archiving.');
            return;
        }

        // Capture input before disabling buttons. Never automatically retry a write.
        const payload = new FormData(form);
        const buttons = [...editor.querySelectorAll('button')];
        const originalStates = buttons.map(button => button.disabled);

        busy = true;
        buttons.forEach(button => { button.disabled = true; });
        loading('Saving changes…');

        let saved = false;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || response.redirected || !result?.message) {
                const errors = result?.errors
                    ? Object.values(result.errors).flat().join(' ')
                    : '';

                throw new Error(errors || (
                    response.status === 419
                        ? 'Your session expired. Reload before trying again.'
                        : response.status >= 500
                            ? 'The save result could not be confirmed. Reload before retrying.'
                            : result?.message || 'The request could not be completed.'
                ));
            }

            saved = true;
            dirty = false;

            // Stay on the current project workspace, retaining its URL filters.
            location.reload();
        } catch (error) {
            feedback(error.message);
        } finally {
            if (!saved) {
                busy = false;
                buttons.forEach((button, index) => {
                    button.disabled = originalStates[index];
                });
                loaded();
                body.querySelector('[data-editor-feedback]')?.focus();
            }
        }
    });

    // Material filtering does not request or expose records from other projects.
    document.addEventListener('change', event => {
        if (!event.target.matches('select[data-material-state]')) return;

        const section = event.target.closest('[data-project-materials]');
        const state = event.target.value;
        const rows = [...section.querySelectorAll('[data-material-row]')];

        rows.forEach(row => {
            row.hidden = state !== 'all' && row.dataset.materialState !== state;
        });

        const empty = section.querySelector('[data-material-filter-empty]');
        if (empty) {
            empty.hidden = rows.length === 0 || rows.some(row => !row.hidden);
        }
    });

    window.addEventListener('beforeunload', event => {
        if (dirty || (busy && !editor.open)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
});