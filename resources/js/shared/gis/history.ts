/**
 * Read-only GIS history with visible loading, cancellation, and manual retry.
 * Laravel remains responsible for authorizing every requested record.
 */
const page = document.querySelector('[data-gis-page]');

if (page) {
    const dialog = document.createElement('dialog');
    dialog.className = 'gis-history-dialog';
    dialog.setAttribute('aria-labelledby', 'gis-history-title');

    // This markup is static. Database values are rendered by the authorized view.
    dialog.innerHTML = `
        <header class="gis-history-header">
            <div>
                <small>BFAR SAAD Phase II / GIS</small>
                <h2 id="gis-history-title">Location history</h2>
            </div>
            <button type="button" class="gis-button" data-history-close>
                Close ×
            </button>
        </header>

        <div class="gis-history-body">
            <div data-history-loading class="gis-loading-state"
                 role="status" aria-live="polite" hidden>
                <span class="gis-loading-ring" aria-hidden="true"></span>
                <div>
                    <strong>Loading history</strong>
                    <p>Retrieving the recorded GIS actions…</p>
                </div>
            </div>

            <div data-history-failure hidden>
                <p data-history-error role="alert" tabindex="-1"></p>
                <button type="button" class="gis-button" data-history-retry>
                    Try again
                </button>
            </div>

            <div data-history-content></div>
        </div>
    `;

    document.body.append(dialog);

    const content = dialog.querySelector<HTMLElement>('[data-history-content]')!;
    const loading = dialog.querySelector<HTMLElement>('[data-history-loading]')!;
    const failure = dialog.querySelector<HTMLElement>('[data-history-failure]')!;
    const errorBox = dialog.querySelector<HTMLElement>('[data-history-error]')!;
    const closeButton = dialog.querySelector<HTMLButtonElement>('[data-history-close]')!;
    const retryButton = dialog.querySelector<HTMLButtonElement>('[data-history-retry]')!;
    const title = dialog.querySelector<HTMLElement>('#gis-history-title')!;

    let opener: HTMLElement | null = null;
    let controller: AbortController | null = null;
    let requestNumber = 0;
    let lastUrl: URL | null = null;
    let closing = false;

    const reduced = (): boolean =>
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    async function close(): Promise<void> {
        if (!dialog.open || closing) return;

        closing = true;

        // Closing cancels the browser request instead of trapping the user.
        requestNumber++;
        controller?.abort();
        controller = null;

        if (!reduced()) {
            await dialog.animate(
                [
                    { opacity: 1, transform: 'scale(1)' },
                    { opacity: 0, transform: 'scale(.98)' },
                ],
                { duration: 140, easing: 'ease-in' },
            ).finished.catch(() => undefined);
        }

        dialog.close();
        dialog.removeAttribute('aria-busy');
        content.replaceChildren();
        loading.hidden = true;
        closing = false;

        if (opener?.isConnected) opener.focus({ preventScroll: true });
    }

    async function load(url: URL): Promise<void> {
        if (url.origin !== location.origin || closing) return;

        controller?.abort();

        const currentRequest = ++requestNumber;
        const abort = new AbortController();
        controller = abort;
        lastUrl = url;

        let timedOut = false;

        if (!dialog.open) dialog.showModal();

        title.textContent = url.pathname.endsWith('/archived')
            ? 'Archived locations'
            : 'Location history';

        content.replaceChildren();
        failure.hidden = true;
        loading.hidden = false;
        dialog.setAttribute('aria-busy', 'true');

        // Keep a bounded wait. Retrying remains an explicit user action.
        const timeout = window.setTimeout(() => {
            timedOut = true;
            abort.abort();
        }, 20000);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: abort.signal,
            });

            if (response.redirected || [401, 403, 419].includes(response.status)) {
                throw new Error('Your access may have changed. Reload GIS Mapping.');
            }

            if (response.status === 404) {
                throw new Error('This history is no longer available within your assignment.');
            }

            if (!response.ok) {
                throw new Error('History is temporarily unavailable. Please try again.');
            }

            const html = await response.text();
            if (currentRequest !== requestNumber) return;

            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const panel = parsed.querySelector<HTMLElement>('[data-gis-history-panel]');

            if (!panel) throw new Error('The history response could not be displayed.');

            panel.querySelectorAll('script').forEach(script => script.remove());

            title.textContent = panel.dataset.title || 'Location history';
            content.replaceChildren(document.importNode(panel, true));
            dialog.querySelector('.gis-history-body')!.scrollTop = 0;
        } catch (error) {
            if (currentRequest !== requestNumber) return;

            errorBox.textContent = timedOut
                ? 'The history request took too long. You can retry or close this window.'
                : error instanceof Error && error.name === 'AbortError'
                    ? 'The request was cancelled.'
                    : error instanceof Error
                        ? error.message
                        : 'History could not be loaded. Please try again.';

            failure.hidden = false;
        } finally {
            window.clearTimeout(timeout);

            // A cancelled older request must not alter a newer dialog.
            if (currentRequest === requestNumber) {
                controller = null;
                loading.hidden = true;
                dialog.removeAttribute('aria-busy');

                if (!failure.hidden) errorBox.focus();
            }
        }
    }

    closeButton.addEventListener('click', () => { void close(); });

    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        void close();
    });

    retryButton.addEventListener('click', () => {
        if (lastUrl) void load(lastUrl);
    });

    document.addEventListener('click', event => {
        if (
            event.button !== 0 || event.ctrlKey || event.metaKey
            || event.shiftKey || event.altKey
        ) return;

        if (!(event.target instanceof Element)) return;

        const link = event.target.closest<HTMLAnchorElement>('a[data-gis-history]');

        if (!link || (!page.contains(link) && !dialog.contains(link))) return;

        event.preventDefault();

        if (!dialog.open) opener = link;
        void load(new URL(link.href, location.href));
    });
}