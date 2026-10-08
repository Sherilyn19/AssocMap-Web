// Reuse one dialog with an optional association drawer.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-record-workspace]').forEach(workspace => {
        const dialog = workspace.querySelector('[data-record-dialog]');
        const body = dialog?.querySelector('[data-record-body]');
        const close = dialog?.querySelector('[data-record-close]');
        const heading = dialog?.querySelector('[data-record-heading]');
        const side = dialog?.querySelector('[data-record-side]');
        const sideBody = side?.querySelector('[data-record-side-body]');
        const sideClose = side?.querySelector('[data-record-side-close]');

        if (!dialog || !body || !close || !dialog.showModal) return;

        const requests = new Map();
        const mobile = matchMedia('(max-width: 767px)');
        let opener;
        let associationOpener;
        // Track each close operation so an old animation cannot hide a reopened panel.
        let sideFade = null;
        let sideCloseVersion = 0;
        let closingSide = false;

        function cancelSideFade() {
            sideCloseVersion++;
            sideFade?.cancel();
            sideFade = null;
            closingSide = false;
        }

        function stop(target) {
            requests.get(target)?.abort();
            requests.delete(target);
            target?.removeAttribute('aria-busy');
        }

        function syncAccess() {
            // On phones the drawer covers the main record.
            body.inert = Boolean(side && !side.hidden && mobile.matches);
        }

        function closeSide(restoreFocus = true) {
            if (!side) return;
            // Closing the parent dialog also cancels any pending drawer animation.
            cancelSideFade();

            stop(sideBody);
            side.hidden = true;
            side.inert = true;
            side.classList.remove('is-open');
            sideBody.replaceChildren();
            dialog.classList.remove('has-association-panel');
            syncAccess();

            if (restoreFocus && associationOpener?.isConnected) {
                associationOpener.focus({ preventScroll: true });
            }
        }

        async function load(url, target, selector) {
            stop(target);
            const controller = new AbortController();
            requests.set(target, controller);
            const timeout = setTimeout(() => controller.abort(), 15000);

            target.setAttribute('aria-busy', 'true');
            target.textContent = 'Loading records…';

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html' },
                    signal: controller.signal,
                });

                if (!response.ok || response.redirected) {
                    throw new Error('Record unavailable');
                }

                const page = new DOMParser().parseFromString(
                    await response.text(), 'text/html'
                );
                const content = page.querySelector(selector);

                if (!content) throw new Error('Details unavailable');
                if (requests.get(target) !== controller || !dialog.open) return;

                target.replaceChildren(document.importNode(content, true));
                target.scrollTop = 0;

                if (target === body && heading && content.dataset.recordTitle) {
                    heading.textContent = content.dataset.recordTitle;
                }
            } catch {
                if (requests.get(target) !== controller || !dialog.open) return;

                const message = document.createElement('p');
                message.className = 'fo-warning';
                message.setAttribute('role', 'alert');
                message.textContent = 'Details could not load. Please try again.';

                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'fo-primary am-button-green mt-3';
                retry.textContent = 'Retry';
                retry.addEventListener('click', () => {
                    (target === sideBody ? sideClose : close)
                        .focus({ preventScroll: true });
                    load(url, target, selector);
                });

                // Keep error recovery inside the current modal.
                target.replaceChildren(message, retry);
            } finally {
                clearTimeout(timeout);
                if (requests.get(target) === controller) {
                    requests.delete(target);
                    target.removeAttribute('aria-busy');
                }
            }
        }

        workspace.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link || event.button !== 0 || event.ctrlKey
                || event.metaKey || event.shiftKey || event.altKey) return;

            const isRecord = link.matches('[data-record-open]');
            const isAssociation = side && (
                link.matches('[data-record-association]')
                || (side.contains(link) && (
                    link.matches('[data-area-drawer]')
                    || link.closest('[data-drawer-pagination]')
                ))
            );

            if (!isRecord && !isAssociation) return;
            event.preventDefault();

            if (!dialog.open) {
                opener = link;
                dialog.showModal();
            }

            if (isAssociation) {
                // Cancel an earlier closing animation before opening new drawer content.
                cancelSideFade();

                if (!side.contains(link)) associationOpener = link;

                dialog.classList.add('has-association-panel');
                side.hidden = false;
                side.inert = false;

                // Fade in place using the existing Area drawer styles.
                void side.offsetWidth;
                side.classList.add('is-open');
                syncAccess();
                sideClose.focus({ preventScroll: true });

                load(link.href, sideBody, '[data-area-drawer-content]');
            } else {
                closeSide(false);
                if (heading) {
                    heading.textContent = link.dataset.recordTitle || 'Record details';
                }
                close.focus({ preventScroll: true });
                load(link.href, body, '[data-record-content]');
            }
        }, true);

        // Fade in place and ignore completion if the user has reopened the drawer.
        async function dismissSide() {
            if (!side || side.hidden || closingSide) return;

            closingSide = true;
            const version = ++sideCloseVersion;
            stop(sideBody);
            side.inert = true;

            try {
                if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    sideFade = side.animate(
                        [{ opacity: 1 }, { opacity: 0 }],
                        { duration: 160, easing: 'ease-out', fill: 'forwards' }
                    );

                    await sideFade.finished;
                }

                if (version !== sideCloseVersion) return;
                closeSide();
            } catch (error) {
                // Reopening or closing the main dialog intentionally cancels the fade.
                if (error.name !== 'AbortError') throw error;
            } finally {
                if (version === sideCloseVersion) {
                    sideFade = null;
                    closingSide = false;
                    side.inert = side.hidden;
                }
            }
        }

        sideClose?.addEventListener('click', dismissSide);
        close.addEventListener('click', () => dialog.close());
        mobile.addEventListener('change', syncAccess);

        // Escape fades out the right drawer before closing the main modal.
        dialog.addEventListener('cancel', event => {
            if (side && !side.hidden) {
                event.preventDefault();
                dismissSide();
            }
        });

        dialog.addEventListener('close', () => {
            stop(body);
            closeSide(false);
            body.replaceChildren();
            if (opener?.isConnected) opener.focus({ preventScroll: true });
        });
    });
});