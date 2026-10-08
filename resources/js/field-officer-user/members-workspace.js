/**
 * One Members workspace:
 * - registers change in place;
 * - drafts open in the main modal;
 * - member management opens in the right drawer;
 * - forms preserve input when validation fails.
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-members-workspace]');
    if (!root) return;

    const register = root.querySelector('[data-members-register]');
    const notice = root.querySelector('[data-members-status]');
    const dialog = root.querySelector('[data-record-dialog]');
    const main = root.querySelector('[data-record-body]');
    const title = root.querySelector('[data-record-heading]');
    const close = root.querySelector('[data-record-close]');
    const side = root.querySelector('[data-record-side]');
    const sideBody = root.querySelector('[data-record-side-body]');
    const sideTitle = root.querySelector('[data-member-side-title]');
    const sideClose = root.querySelector('[data-record-side-close]');

    if (!dialog || !register || !side || !dialog.showModal) return;

    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const mobile = matchMedia('(max-width: 767px)');
    const reads = new Map();
    const dirty = new Set();

    let busy = false;
    let opener = null;
    let drawerOpener = null;
    let mainUrl = null;
    let mainSelector = '[data-record-content]';
    let closing = false;
    let registerUrl = location.href;

    // Keep repeated clicks from canceling an unfinished register request.
    let registerLoading = false;

    // Keep the existing loader open until all related requests have finished.
    // Saving may also reload the modal and table before the operation is complete.
    let pendingOperations = 0;

    function beginLoading(label = 'Loading records…') {
        pendingOperations += 1;

        if (pendingOperations === 1) {
            document.dispatchEvent(new CustomEvent('management:loading', {
                detail: { label, managed: true },
            }));
        }
    }

    function finishLoading() {
        pendingOperations = Math.max(0, pendingOperations - 1);

        if (pendingOperations === 0) {
            document.dispatchEvent(new Event('management:loaded'));
        }
    }

    function localUrl(value) {
        const url = new URL(value, location.href);
        if (url.origin !== location.origin) throw new Error('Invalid destination.');
        return url;
    }

    function message(target, text, error = false) {
        target.hidden = false;
        target.className = error ? 'am-members-message is-error' : 'am-members-message';
        target.setAttribute('role', error ? 'alert' : 'status');
        target.textContent = text;
    }

    function formError(form, text) {
        let box = form.querySelector('[data-form-feedback]');
        if (!box) {
            box = document.createElement('div');
            box.dataset.formFeedback = '';
            box.tabIndex = -1;
            form.prepend(box);
        }
        message(box, text, true);
        box.focus();
    }

    function discardAllowed(container) {
        const changed = [...dirty].filter(form => container.contains(form));
        if (!changed.length) return true;
        if (!confirm('Discard your unsaved changes?')) return false;
        changed.forEach(form => dirty.delete(form));
        return true;
    }

    function forgetChanges(container) {
        [...dirty].forEach(form => {
            if (container.contains(form)) dirty.delete(form);
        });
    }

    function stopRead(target) {
        reads.get(target)?.abort();
        reads.delete(target);
    }

    function syncAccess() {
        main.inert = busy || (!side.hidden && mobile.matches);
        side.inert = busy || side.hidden;
    }

    async function animate(element, frames) {
        if (reduced.matches) return;
        await element.animate(frames, {
            duration: 180,
            easing: 'ease-out',
        }).finished.catch(() => {});
    }

    async function getFragment(url, selector, target) {
        stopRead(target);
        const controller = new AbortController();
        reads.set(target, controller);
        // Allow a slow local request to complete, but retain a bounded timeout.
        const timer = setTimeout(() => controller.abort(), 30000);

        // Use the shared loading screen for tabs, filters, and modal details.
        beginLoading();

        try {
            const response = await fetch(localUrl(url), {
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
                signal: controller.signal,
            });

            // A login redirect is not valid modal content.
            if (!response.ok || response.redirected) {
                throw new Error('Content is unavailable. Refresh your session and try again.');
            }

            const page = new DOMParser().parseFromString(
                await response.text(),
                'text/html'
            );

            const fragment = page.querySelector(selector);
            if (!fragment) throw new Error('The expected content could not be loaded.');

            if (reads.get(target) !== controller) return null;
            return document.importNode(fragment, true);
        } finally {
            clearTimeout(timer);
            if (reads.get(target) === controller) reads.delete(target);

            // Always release the loader, including failed or cancelled requests.
            finishLoading();
        }
    }

    async function loadPanel(url, target, selector, focus = true) {
        // The shared overlay provides loading feedback above the modal.
        target.replaceChildren();

        try {
            const fragment = await getFragment(url, selector, target);
            if (!fragment || !dialog.open) return false;

            target.replaceChildren(fragment);
            target.scrollTop = 0;

            if (target === main && fragment.dataset.recordTitle) {
                title.textContent = fragment.dataset.recordTitle;
            }

            if (focus) {
                fragment.tabIndex = -1;
                fragment.focus({ preventScroll: true });
            }

            return true;
        } catch (error) {
            if (!dialog.open || error.name === 'AbortError') return false;

            const warning = document.createElement('p');
            message(warning, error.message, true);

            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'fo-action';
            retry.textContent = 'Reload details';
            retry.addEventListener('click', () => loadPanel(url, target, selector));

            target.replaceChildren(warning, retry);
            return false;
        }
    }

    function hideSide() {
        stopRead(sideBody);
        forgetChanges(sideBody);
        side.hidden = true;
        side.classList.remove('is-open');
        sideBody.replaceChildren();
        dialog.classList.remove('has-association-panel');
        syncAccess();
    }

    async function dismissSide() {
        if (busy || closing || side.hidden || !discardAllowed(sideBody)) return;
        closing = true;

        stopRead(sideBody);
        side.inert = true;
        sideClose.focus({ preventScroll: true });

        await animate(side, [
            { opacity: 1, transform: 'translateX(0)' },
            { opacity: 0, transform: 'translateX(16px)' },
        ]);

        hideSide();
        closing = false;

        if (drawerOpener?.isConnected) drawerOpener.focus({ preventScroll: true });
        else close.focus({ preventScroll: true });
    }

    async function dismissDialog(force = false) {
        if (closing || (busy && !force)) return;
        if (!force && !discardAllowed(dialog)) return;

        closing = true;
        stopRead(main);
        stopRead(sideBody);

        await animate(dialog, [{ opacity: 1 }, { opacity: 0 }]);
        dialog.close();
        closing = false;
    }

    async function openMain(link) {
        if (busy || closing || !discardAllowed(dialog)) return;

        forgetChanges(main);
        hideSide();
        mainUrl = link.href;
        mainSelector = '[data-record-content]';

        if (!dialog.open) {
            opener = link;
            dialog.showModal();
        }

        title.textContent = link.dataset.recordTitle || 'Record details';
        close.focus({ preventScroll: true });
        await loadPanel(mainUrl, main, mainSelector);
    }

    async function openSide(link, manage) {
        if (busy || closing || !discardAllowed(sideBody)) return;
        forgetChanges(sideBody);

        const wasHidden = side.hidden;
        if (!side.contains(link)) drawerOpener = link;

        sideTitle.textContent = manage ? 'Manage member' : 'Association details';
        side.hidden = false;
        side.classList.add('is-open');
        dialog.classList.add('has-association-panel');
        syncAccess();
        sideClose.focus({ preventScroll: true });

        if (wasHidden) {
            // Only the drawer moves. The main modal remains in place.
            animate(side, [
                { opacity: 0, transform: 'translateX(16px)' },
                { opacity: 1, transform: 'translateX(0)' },
            ]);
        }

        await loadPanel(
            link.href,
            sideBody,
            manage ? '[data-member-editor]' : '[data-area-drawer-content]'
        );
    }

        async function refreshRegister(url, historyMode = null, focus = false) {
        // Allow the current request to finish instead of restarting it.
        if (registerLoading) return false;

        const destination = localUrl(url);
        registerLoading = true;
        register.setAttribute('aria-busy', 'true');
        // Loading appears in the shared overlay; keep this area for results and errors.
        notice.hidden = true;

        try {
            const fragment = await getFragment(
                destination,
                '[data-members-register]',
                register
            );

            if (!fragment) return false;

            register.replaceChildren(...fragment.childNodes);
            registerUrl = destination.href;

            if (historyMode) {
                history[historyMode]({}, '', destination);
            }

            notice.hidden = true;

            if (focus) {
                const heading = register.querySelector('h2');

                if (heading) {
                    heading.tabIndex = -1;
                    heading.focus({ preventScroll: true });
                }
            }

            animate(register, [{ opacity: 0.6 }, { opacity: 1 }]);
            return true;
        } catch (error) {
            // A timeout must produce visible feedback rather than appearing unresponsive.
            message(
                notice,
                error.name === 'AbortError'
                    ? 'The server took too long to respond. Try again after checking the Laravel server.'
                    : error.message || 'The register could not load.',
                true
            );

            return false;
        } finally {
            registerLoading = false;
            register.removeAttribute('aria-busy');
        }
    }

    root.addEventListener('input', event => {
        const form = event.target.closest('form[data-track-changes]');
        if (form) dirty.add(form);
    });

    root.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey
            || event.shiftKey || event.altKey) return;

        if (link.matches('[data-member-manage]')) {
            event.preventDefault();
            openSide(link, true);
        } else if (
            link.matches('[data-record-association]')
            || (side.contains(link) && (
                link.matches('[data-area-drawer]')
                || link.closest('[data-drawer-pagination]')
            ))
        ) {
            event.preventDefault();
            openSide(link, false);
        } else if (link.matches('[data-record-open]')) {
            event.preventDefault();
            openMain(link);
        } else if (
            register.contains(link)
            && localUrl(link.href).pathname === localUrl(root.dataset.membersUrl).pathname
        ) {
            event.preventDefault();
            if (!busy) refreshRegister(link.href, 'pushState', true);
        }
    });

    root.addEventListener('submit', async event => {
        const form = event.target;

        if (register.contains(form) && form.method.toLowerCase() === 'get') {
            event.preventDefault();
            if (busy) return;

            const url = localUrl(form.action);
            url.search = new URLSearchParams(new FormData(form)).toString();
            await refreshRegister(url, 'pushState', true);
            return;
        }

        if (!form.matches('[data-member-form]') || !dialog.contains(form)) return;
        event.preventDefault();
        if (busy || !form.reportValidity()) return;

        // Do not submit/cancel a saved draft while its editor has unsaved changes.
        if (!form.matches('[data-track-changes]')
            && [...dirty].some(item => dialog.contains(item))) {
            formError(form, 'Save your profile changes before continuing.');
            return;
        }

        const target = side.contains(form) ? sideBody : main;
        const selector = target === sideBody
            ? '[data-member-editor]'
            : '[data-record-content]';

        const payload = new FormData(form);
        const buttons = [...dialog.querySelectorAll('button')]
            .map(button => [button, button.disabled]);

        const closeAfter = form.hasAttribute('data-close-after-save');
        const nextTab = form.dataset.listTab;

        busy = true;
        buttons.forEach(([button]) => { button.disabled = true; });
        syncAccess();

        let saved = false;
        // Keep the loader visible during the write and subsequent content refreshes.
        beginLoading('Processing your changes…');

        try {
            // Never automatically retry a write: the server may already have committed it.
            const response = await fetch(localUrl(form.action), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
            });

            const data = await response.json().catch(() => null);

            if (!response.ok || response.redirected || !data?.url) {
                const errors = data?.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : '';

                throw new Error(errors || (
                    response.status === 419
                        ? 'Your session expired. Reload the page before saving again.'
                        : response.status === 403
                            ? 'You no longer have permission to perform this action.'
                            : response.status >= 500
                                ? 'The save result could not be confirmed. Reload the record before retrying.'
                                : data?.message || 'The request could not be completed.'
                ));
            }

            saved = true;
            forgetChanges(target);

            if (closeAfter) {
                await dismissDialog(true);
            } else {
                if (target === main) mainUrl = data.url;

                const loaded = await loadPanel(data.url, target, selector, false);
                if (!loaded) {
                    message(notice, `${data.message} Reload details to see the latest record.`);
                }

                // Update the member summary behind the management drawer.
                if (target === sideBody && mainUrl) {
                    await loadPanel(mainUrl, main, mainSelector, false);
                }
            }

            let listUrl = registerUrl;
            if (nextTab) {
                const url = localUrl(root.dataset.membersUrl);
                url.searchParams.set('tab', nextTab);
                listUrl = url.href;
            }

            message(notice, data.message || 'Changes saved.');
            const refreshed = await refreshRegister(listUrl, 'replaceState');

            if (refreshed && dialog.open && !closeAfter) {
                const feedback = document.createElement('p');
                message(feedback, data.message || 'Changes saved.');
                feedback.tabIndex = -1;
                target.prepend(feedback);
            }
        } catch (error) {
            if (!saved && form.isConnected) {
                formError(form, error.message);
            } else {
                message(notice, 'The change was saved, but the display could not refresh. Reload the page.', true);
            }
        } finally {
            finishLoading();
            busy = false;
            buttons.forEach(([button, disabled]) => {
                if (button.isConnected) button.disabled = disabled;
            });
            syncAccess();

            if (saved && dialog.open) {
                const feedback = target.querySelector('.am-members-message');
                (feedback || (target === main ? close : sideClose))
                    .focus({ preventScroll: true });
            } else if (!saved && form.isConnected) {
                form.querySelector('[data-form-feedback]')?.focus();
            } else if (!dialog.open) {
                notice.focus({ preventScroll: true });
            }
        }
    });

    close.addEventListener('click', () => dismissDialog());
    sideClose.addEventListener('click', dismissSide);

    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        if (!side.hidden) dismissSide();
        else dismissDialog();
    });

    dialog.addEventListener('close', () => {
        stopRead(main);
        hideSide();
        forgetChanges(main);
        main.replaceChildren();

        if (opener?.isConnected) opener.focus({ preventScroll: true });
        else root.querySelector('[data-record-open]')?.focus({ preventScroll: true });
    });

    mobile.addEventListener('change', syncAccess);

    window.addEventListener('popstate', () => {
        // Browser Back still restores the selected register.
        if (busy) {
            history.replaceState({}, '', registerUrl);
            return;
        }
        if (dialog.open && !discardAllowed(dialog)) {
            history.pushState({}, '', registerUrl);
            return;
        }
        if (dialog.open) dialog.close();
        refreshRegister(location.href, null, true);
    });

    window.addEventListener('beforeunload', event => {
        if (busy || [...dirty].some(form => form.isConnected)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
});