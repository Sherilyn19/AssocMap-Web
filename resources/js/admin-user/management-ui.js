/** Shared feedback for server-rendered management screens. No artificial loading delay. */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-member-management-page], [data-pm-page]');
    if (!page) return;
    const content = document.querySelector('.am-content');
    const heading = page.querySelector('h1');
    const title = document.querySelector('.am-topbar__title');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    // Observe the actual scrolling panel, not window: the dashboard body never scrolls.
    // Only replace duplicate titles on register pages; detail pages keep their context.
    if (heading && title && page.hasAttribute('data-management-register')) {
        title.textContent = heading.textContent.trim();
        // Animate the separator with the module title; the workspace label stays
        // visible at the top and never participates in the scroll transition.
        const titleTrail = document.querySelector('[data-management-title-trail]') || title;
        titleTrail.classList.add('management-context-title');
        const syncTitle = () => {
            const hidden = heading.getBoundingClientRect().bottom > Math.max(content.getBoundingClientRect().top, 80);
            titleTrail.classList.toggle('is-concealed', hidden);
            titleTrail.setAttribute('aria-hidden', String(hidden));
        };
        syncTitle();
        content.addEventListener('scroll', syncTitle, { passive: true });
        window.addEventListener('resize', syncTitle);
    }

    const overlay = document.createElement('div');
    overlay.className = 'management-loading';
    overlay.hidden = true;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML = '<div class="management-loading-card"><span class="management-spinner" aria-hidden="true"></span><strong data-loading-label>Loading…</strong><span>Please wait while your request is processed.</span></div>';
    document.body.append(overlay);
    let recoveryTimer;
    const stop = () => {
        overlay.hidden = true;
        content.removeAttribute('aria-busy');
        clearTimeout(recoveryTimer);
    };
    const start = (label = 'Loading…') => {
        overlay.querySelector('[data-loading-label]').textContent = label;
        overlay.hidden = false;
        content.setAttribute('aria-busy', 'true');
        // A canceled navigation must not leave an indefinite screen blocker.
        clearTimeout(recoveryTimer);
        recoveryTimer = setTimeout(stop, 30000);
    };
    document.addEventListener('management:loading', () => start('Saving changes…'));
    window.addEventListener('pageshow', stop);
    window.addEventListener('load', stop, { once: true });
    if (document.readyState !== 'complete') start();

    // Bubble after module handlers so canceled validation, local dialogs, modified
    // clicks, downloads and same-document anchors never display a false loading state.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        start();
    });
    document.addEventListener('submit', (event) => {
        if (!event.defaultPrevented && event.target.method !== 'dialog' && (!event.target.target || event.target.target === '_self')) {
            start(event.target.method.toLowerCase() === 'get' ? 'Loading records…' : 'Saving changes…');
        }
    });

    // Reveal sections once as they enter view. Content remains visible if JS fails,
    // and reduced-motion users receive the same content without movement.
    if (!reducedMotion.matches && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.animate([{ opacity: 0.5, transform: 'translateY(10px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 280, easing: 'ease-out' });
                observer.unobserve(entry.target);
            });
        }, { root: content, threshold: 0.05 });
        page.querySelectorAll(':scope > header, :scope > section').forEach((section) => observer.observe(section));
    }
});
