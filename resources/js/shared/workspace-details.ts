/** Load authorized detail fragments on demand; ordinary links work without JavaScript. */
type DetailKind = 'project' | 'training';

function bindDetailDialog(kind: DetailKind): void {
    const dialog = document.querySelector<HTMLDialogElement>(`[data-${kind}-dialog]`);
    const content = dialog?.querySelector<HTMLElement>(`[data-${kind}-content]`);
    if (!dialog || !content || typeof dialog.showModal !== 'function') return;
    let request: AbortController | undefined;
    let opener: HTMLAnchorElement | undefined;

    // Delegation also handles project links loaded inside a training dialog.
    document.addEventListener('click', async (event: MouseEvent) => {
        const link = event.target instanceof Element
            ? event.target.closest<HTMLAnchorElement>(`a[data-${kind}-open]`) : null;
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        opener = link;
        request?.abort();
        const current = new AbortController();
        request = current;
        content.textContent = `Loading ${kind} details…`;
        content.setAttribute('aria-busy', 'true');
        if (!dialog.open) dialog.showModal();
        dialog.scrollTop = 0;
        document.body.classList.add('am-project-modal-open');
        try {
            const url = new URL(link.href);
            url.searchParams.set('details', '1');
            const response = await fetch(url, {
                signal: current.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            // Redirects may be a login response; do not insert that page into a dialog.
            if (!response.ok || response.redirected) throw new Error('Details unavailable');
            const html = await response.text();
            if (current.signal.aborted) return;
            content.innerHTML = html;
        } catch (error: unknown) {
            if (current.signal.aborted) return;
            content.textContent = `The ${kind} details could not load. `;
            const fallback = document.createElement('a');
            fallback.href = link.href;
            fallback.className = 'underline';
            fallback.textContent = `Open the full ${kind} page`;
            content.append(fallback);
        } finally {
            if (request === current) content.removeAttribute('aria-busy');
        }
    });
    dialog.querySelector(`[data-${kind}-close]`)?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        request?.abort();
        // Keep the underlying training dialog locked while its project dialog closes.
        if (!document.querySelector('dialog[open]')) document.body.classList.remove('am-project-modal-open');
        opener?.focus();
    });
}

bindDetailDialog('project');
bindDetailDialog('training');
export {};
