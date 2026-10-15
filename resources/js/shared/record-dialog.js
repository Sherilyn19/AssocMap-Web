import { trackDetailRequest } from './detail-request';

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

       async function load(url, target, selector, preserve = false) {
            stop(target);

            const controller = new AbortController();
            requests.set(target, controller);

            const isCurrent = () =>
                requests.get(target) === controller && dialog.open;

            const previous = preserve ? target.querySelector(selector) : null;
            const scrollTop = target.scrollTop;
            const focused = target.contains(document.activeElement)
                ? document.activeElement
                : null;

            const focusName = focused?.getAttribute('name');
            const focusSubmit = focused?.matches('button[type="submit"]');

            let clearContent = false;
            let failureMessage = 'Unable to load details. Check your connection and retry.';

            // Remove only the previous loading/error notice when refreshing.
            target.querySelectorAll('[data-record-load-notice]')
                .forEach(notice => notice.remove());

            const notice = document.createElement('div');
            notice.dataset.recordLoadNotice = '';

            const message = document.createElement('p');
            message.setAttribute('role', 'status');
            message.textContent = previous ? 'Updating overview…' : 'Loading records…';
            notice.append(message);

            target.setAttribute('aria-busy', 'true');

            if (previous) {
                target.prepend(notice);
            } else {
                target.replaceChildren(notice);
            }

            const timing = trackDetailRequest(controller, () => {
                if (isCurrent()) message.textContent =
                    'Loading is taking longer than usual. You can still close this window.';
            });

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html' },
                    signal: controller.signal,
                });

                if (response.redirected || response.status === 401) {
                    clearContent = true;
                    failureMessage = 'Your session may have expired. Refresh and sign in again.';
                    throw new Error('Session unavailable');
                }

                if (!response.ok) {
                    clearContent = [403, 404].includes(response.status);
                    failureMessage = clearContent
                        ? 'This record is unavailable or you no longer have access.'
                        : 'The server could not load these details. Please retry shortly.';

                    throw new Error(`HTTP ${response.status}`);
                }

                const html = await response.text();
                if (!isCurrent()) return;

                const page = new DOMParser().parseFromString(html, 'text/html');
                const content = page.querySelector(selector);

                if (!content) {
                    failureMessage = 'Unexpected server response. Refresh the page and retry.';
                    throw new Error('Missing record content');
                }

                const replacement = document.importNode(content, true);
                target.replaceChildren(replacement);
                target.scrollTop = previous ? scrollTop : 0;

                if (target === body && heading && content.dataset.recordTitle) {
                    heading.textContent = content.dataset.recordTitle;
                }

                // Restore keyboard focus only when it was inside the replaced content.
                if (focused) {
                    const form = replacement.querySelector('[data-record-filter]');
                    const nextFocus = focusName
                        ? form?.elements.namedItem(focusName)
                        : focusSubmit
                            ? form?.querySelector('button[type="submit"]')
                            : null;

                    (nextFocus instanceof HTMLElement
                        ? nextFocus
                        : target === sideBody ? sideClose : close)
                        ?.focus({ preventScroll: true });
                }

                // Animate only the refreshed content, leaving the drawer untouched.
                if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    replacement.animate(
                        [
                            { opacity: 0, transform: 'translateY(3px)' },
                            { opacity: 1, transform: 'translateY(0)' },
                        ],
                        { duration: 180, easing: 'ease-out' }
                    );
                }
            } catch {
                // Closing the modal or starting a newer request is intentional.
                if (!isCurrent()) return;
                if (controller.signal.aborted && !timing.timedOut) return;

                message.className = 'fo-warning';
                message.setAttribute('role', 'alert');
                message.textContent = timing.timedOut
                    ? 'Loading exceeded 30 seconds. Please retry when the connection is stable.'
                    : failureMessage;

                if (previous && !clearContent) {
                    message.textContent +=
                        ' The overview below still shows the previously loaded results.';
                }

                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'fo-primary am-button-green mt-3';
                retry.textContent = 'Retry';

                retry.addEventListener('click', () => {
                    (target === sideBody ? sideClose : close)
                        ?.focus({ preventScroll: true });

                    load(url, target, selector, preserve);
                });

                notice.replaceChildren(message, retry);

                // Preserve content on temporary failure, but clear it after access denial.
                if (clearContent) {
                    target.replaceChildren(notice);
                    if (target === body) closeSide(false);
                }
            } finally {
                timing.dispose();

                if (requests.get(target) === controller) {
                    requests.delete(target);
                    target.removeAttribute('aria-busy');
                }
            }
        }

        // Refresh the panel containing the filter without closing either panel.
        workspace.addEventListener('submit', event => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)
                || !form.matches('[data-record-filter]')
                || !dialog.open) {
                return;
            }

            let target;
            let selector;

            if (sideBody?.contains(form)) {
                target = sideBody;
                selector = '[data-area-drawer-content]';
            } else if (body.contains(form)) {
                target = body;
                selector = '[data-record-content]';
            } else {
                return;
            }

            event.preventDefault();

            const url = new URL(form.action, location.href);
            url.search = new URLSearchParams(new FormData(form)).toString();

            // Reuse loading feedback, retry handling, focus restoration and animation.
            load(url.href, target, selector, true);
        }, true);

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