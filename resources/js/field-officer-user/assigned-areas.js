// Load the styles used by Field Officer area pages.
import '../../css/field-officer-areas.css';

document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-officer-areas]');
    if (!page) return;

    const dialog = page.querySelector('[data-area-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const main = dialog.querySelector('[data-area-content]');
    const side = dialog.querySelector('[data-area-side]');
    const body = side.querySelector('[data-drawer-content]');
    const closePanel = side.querySelector('[data-drawer-close]');
    const closeDialog = dialog.querySelector('[data-area-close]');
    const mobile = matchMedia('(max-width: 767px)');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');

    const requests = new Map();
    let opener = null;
    let panelOpener = null;
    let closeTimer;

    function syncMainAccess() {
        // On mobile, the drawer covers the table, so hide it from keyboard access.
        main.inert = mobile.matches && !side.hidden
            && side.classList.contains('is-open');
    }
    mobile.addEventListener('change', syncMainAccess);

    function stopRequest(target) {
        requests.get(target)?.abort();
        requests.delete(target);
        target.removeAttribute('aria-busy');
    }

    function closeDrawer(immediate = false, restoreFocus = true) {
        clearTimeout(closeTimer);
        stopRequest(body);
        side.inert = true;
        side.classList.remove('is-open');
        main.inert = false;

        if (restoreFocus && panelOpener?.isConnected) panelOpener.focus({ preventScroll: true });

        const finish = () => {
            side.hidden = true;
            body.replaceChildren();
        };
        if (immediate || reduced.matches) finish();
        // Hide the panel after its fade-out finishes.
        else closeTimer = setTimeout(finish, 200);
    }

    function openDrawer(link) {
        clearTimeout(closeTimer);
        if (!side.contains(link)) panelOpener = link;

        side.hidden = false;
        side.inert = false;

        // Establish the transparent state before fading the panel into place.
        void side.offsetWidth;
        side.classList.add('is-open');
        syncMainAccess();
        closePanel.focus({ preventScroll: true });
    }

    async function load(url, target, selector) {
        stopRequest(target);
        const controller = new AbortController();
        requests.set(target, controller);

        target.setAttribute('aria-busy', 'true');
        target.textContent = 'Loading records…';
        const timeout = setTimeout(() => controller.abort(), 15000);

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: { Accept: 'text/html' },
            });

            if (!response.ok || response.redirected) {
                throw new Error('Unable to load records.');
            }

            const html = await response.text();
            const documentCopy = new DOMParser().parseFromString(html, 'text/html');
            const fragment = documentCopy.querySelector(selector);

            if (!fragment) throw new Error('Expected records were not returned.');
            if (requests.get(target) !== controller || !dialog.open) return;

            target.replaceChildren(document.importNode(fragment, true));
            target.scrollTop = 0;
        } catch {
            // Ignore responses from requests replaced by another click or closure.
            if (requests.get(target) !== controller || !dialog.open) return;

            const message = document.createElement('p');
            message.className = 'fo-warning';
            message.setAttribute('role', 'alert');
            message.textContent = 'Records could not load. Retry or open the full page.';

            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'fo-primary mt-3';
            retry.textContent = 'Retry';
            retry.addEventListener('click', () => {
                (target === body ? closePanel : closeDialog).focus({ preventScroll: true });
                load(url, target, selector);
            });

            const fallback = document.createElement('a');
            fallback.href = url;
            fallback.className = 'fo-action mt-3 ml-2';
            fallback.textContent = 'Open full page';

            target.replaceChildren(message, retry, fallback);
        } finally {
            clearTimeout(timeout);
            if (requests.get(target) === controller) {
                requests.delete(target);
                target.removeAttribute('aria-busy');
            }
        }
    }

    page.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey
            || event.shiftKey || event.altKey) return;

        const drawerLink = link.matches('[data-area-drawer]')
            || Boolean(link.closest('[data-drawer-pagination]'));
        const coverageLink = link.matches('[data-area-details]')
            || Boolean(link.closest('[data-area-pagination]'));

        if (!drawerLink && !coverageLink) return;

        // Prevent the shared navigation overlay for requests handled inside the dialog.
        event.preventDefault();

        if (!dialog.open) {
            opener = link;
            dialog.showModal();
        }

        if (drawerLink) {
            openDrawer(link);
            load(link.href, body, '[data-area-drawer-content]');
        } else {
            closeDrawer(true, false);
            closeDialog.focus({ preventScroll: true });
            load(link.href, main, '[data-area-detail-content]');
        }
    }, true);

    closePanel.addEventListener('click', () => closeDrawer());
    closeDialog.addEventListener('click', () => dialog.close());

    dialog.addEventListener('cancel', event => {
        // Escape closes the drawer first, then the coverage dialog.
        if (!side.hidden && side.classList.contains('is-open')) {
            event.preventDefault();
            closeDrawer();
        }
    });

    dialog.addEventListener('close', () => {
        stopRequest(main);
        closeDrawer(true, false);
        opener?.focus({ preventScroll: true });
    });

    // Dismiss expanded status explanations without closing the dialog.
    page.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const status = event.target.closest('.fo-status');
        if (status?.open) {
            event.preventDefault();
            event.stopPropagation();
            status.open = false;
        }
    });
});
