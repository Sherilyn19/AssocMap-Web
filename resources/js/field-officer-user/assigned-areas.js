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

// Area analytics: accessible detail modal, overlay help, and section highlighting.
(() => {
    const root = document.querySelector('[data-officer-areas]');
    const dialog = document.getElementById('area-analytics-dialog');

    if (!root || !dialog) return;

    const body = dialog.querySelector('[data-area-analytics-body]');
    const title = dialog.querySelector('#area-analytics-title');
    const jump = dialog.querySelector('[data-area-analytics-jump]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    let trigger = null;
    let destination = null;
    let helpButton = null;
    let helpBubble = null;
    let pinnedHelp = false;
    let hideTimer;
    let highlightTimer;
    let highlighted = null;

    // Native popovers appear above normal page stacking contexts.
    function closeHelp() {
        clearTimeout(hideTimer);
        if (helpBubble?.matches(':popover-open')) helpBubble.hidePopover();
        helpButton?.setAttribute('aria-expanded', 'false');
        helpButton = null;
        helpBubble = null;
        pinnedHelp = false;
    }

    function positionHelp() {
        if (!helpButton || !helpBubble) return;

        const anchor = helpButton.getBoundingClientRect();
        const bubble = helpBubble.getBoundingClientRect();
        const gap = 10;
        const left = Math.max(12, Math.min(
            anchor.left + anchor.width / 2 - bubble.width / 2,
            window.innerWidth - bubble.width - 12
        ));

        // Prefer above the icon; flip below when the top edge has insufficient room.
        const preferredTop = anchor.top - bubble.height - gap;
        const top = preferredTop >= 12
            ? preferredTop
            : anchor.bottom + gap;

        helpBubble.style.left = `${left}px`;
        helpBubble.style.top = `${Math.max(12, Math.min(
            top, window.innerHeight - bubble.height - 12
        ))}px`;
    }

    function showHelp(button) {
        clearTimeout(hideTimer);
        if (helpButton === button) return;
        closeHelp();

        helpButton = button;
        helpBubble = document.getElementById(button.dataset.areaHelp);
        helpBubble.showPopover();
        helpButton.setAttribute('aria-expanded', 'true');
        positionHelp();
    }

    // Allow the pointer to move from the icon onto the bubble without dismissing it.
    function scheduleHelpClose() {
        clearTimeout(hideTimer);
        hideTimer = window.setTimeout(() => {
            if (!pinnedHelp
                && document.activeElement !== helpButton
                && !helpButton?.matches(':hover')
                && !helpBubble?.matches(':hover')) {
                closeHelp();
            }
        }, 180);
    }

    // Connect tooltips on initial load and after coverage records arrive.
    const boundHelpButtons = new WeakSet();

    function bindAreaHelp() {
        // Close a bubble if its record was replaced.
        if (helpButton && !helpButton.isConnected) closeHelp();

        root.querySelectorAll('[data-area-help]').forEach((button) => {
            const bubble = document.getElementById(button.dataset.areaHelp);
            if (!bubble || boundHelpButtons.has(button)) return;
            boundHelpButtons.add(button); // Prevent duplicate listeners.

            button.addEventListener('pointerenter', (event) => {
                if (event.pointerType === 'mouse') showHelp(button);
            });
            button.addEventListener('pointerleave', scheduleHelpClose);
            button.addEventListener('focus', () => showHelp(button));
            button.addEventListener('blur', scheduleHelpClose);

            // Support tapping the icon on mobile.
            button.addEventListener('click', () => {
                if (helpButton === button && pinnedHelp) {
                    closeHelp();
                } else {
                    showHelp(button);
                    pinnedHelp = true;
                }
            });

            bubble.addEventListener('pointerenter', () => clearTimeout(hideTimer));
            bubble.addEventListener('pointerleave', scheduleHelpClose);
        });
    }

    bindAreaHelp();
    new MutationObserver(bindAreaHelp).observe(root, {
        childList: true,
        subtree: true,
    });

    // Dismiss any open tooltip when coverage closes.
    root.querySelector('[data-area-dialog]')?.addEventListener('close', closeHelp);

    document.addEventListener('pointerdown', (event) => {
        if (helpButton
            && !helpButton.contains(event.target)
            && !helpBubble.contains(event.target)) closeHelp();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && helpButton) {
            event.preventDefault();
            closeHelp();
        }
    });

    // Reposition overlays during scrolling, including nested content containers.
    window.addEventListener('resize', positionHelp);
    document.addEventListener('scroll', positionHelp, true);

    root.querySelectorAll('[data-area-metric]').forEach((button) => {
        button.addEventListener('click', () => {
            closeHelp();
            trigger = button;

            const key = button.dataset.areaMetric;
            const template = document.getElementById(`area-metric-template-${key}`);

            title.textContent = button.querySelector('.area-metric__label').textContent;
            body.replaceChildren(template.content.cloneNode(true));
            body.scrollTop = 0;

            destination = document.getElementById(
                key === 'issues' ? 'area-geographic-issues' : 'area-filtered-coverage'
            );

            // Empty results have no meaningful related-record action.
            jump.hidden = !body.querySelector('.area-analytics-record') || !destination;
            dialog.showModal();
        });
    });

    dialog.querySelector('[data-area-analytics-close]')
        .addEventListener('click', () => dialog.close());

    dialog.addEventListener('click', (event) => {
        const box = dialog.getBoundingClientRect();
        if (event.target === dialog && (
            event.clientX < box.left || event.clientX > box.right
            || event.clientY < box.top || event.clientY > box.bottom
        )) dialog.close();
    });

    // Native dialog handles Escape and focus containment.
    dialog.addEventListener('close', () => {
        trigger?.focus({ preventScroll: true });

        if (!dialog.dataset.jump) return;
        delete dialog.dataset.jump;

        const target = destination;
        if (!target) return;
        if (target instanceof HTMLDetailsElement) target.open = true;

        requestAnimationFrame(() => {
            target.focus({ preventScroll: true });
            target.scrollIntoView({
                behavior: reducedMotion.matches ? 'instant' : 'smooth',
                block: 'start',
            });

            // Restart a two-second visual cue, including repeated selections.
            clearTimeout(highlightTimer);
            highlighted?.classList.remove('area-analytics-highlight');
            highlighted = target;
            void target.offsetWidth;
            target.classList.add('area-analytics-highlight');
            highlightTimer = window.setTimeout(() => {
                target.classList.remove('area-analytics-highlight');
            }, 2000);
        });
    });

    jump.addEventListener('click', () => {
        dialog.dataset.jump = 'true';
        dialog.close();
    });
})();