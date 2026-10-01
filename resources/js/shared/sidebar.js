/** Shared desktop navigation and mobile drawer. CSS owns the breakpoint. */
document.addEventListener('DOMContentLoaded', initAssocMapSidebar);

function initAssocMapSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const overlay = document.getElementById('sidebarOverlay');
    const menu = document.getElementById('sidebarMenuBtn');
    const collapse = document.getElementById('sidebarCollapseBtn');
    const close = document.getElementById('sidebarCloseBtn');
    const main = document.querySelector('.am-main');
    const isMobile = () => getComputedStyle(document.body).getPropertyValue('--am-drawer-mode').trim() === '1';
    let mobile = isMobile();
    let preferredCollapse = false;
    let previousOverflow = '';
    try { preferredCollapse = localStorage.getItem('assocmap.sidebarCollapsed') === '1'; } catch { /* Navigation still works when storage is unavailable. */ }

    function setCollapsed(value) {
        sidebar.classList.toggle('is-collapsed', value);
        document.body.classList.toggle('am-sidebar-collapsed', value);
        collapse?.setAttribute('aria-expanded', String(!value));
        collapse?.setAttribute('aria-label', value ? 'Expand sidebar' : 'Collapse sidebar');
    }

    function closeDrawer(returnFocus = true) {
        const wasOpen = sidebar.classList.contains('is-open');
        sidebar.classList.remove('is-open');
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        overlay?.classList.remove('is-visible');
        menu?.setAttribute('aria-expanded', 'false');
        if (main) main.inert = false;
        if (wasOpen) document.body.style.overflow = previousOverflow;
        sidebar.inert = mobile;
        if (mobile) sidebar.setAttribute('aria-hidden', 'true');
        else sidebar.removeAttribute('aria-hidden');
        if (returnFocus && wasOpen && mobile) menu?.focus();
    }

    function openDrawer() {
        if (!mobile) return;
        previousOverflow = document.body.style.overflow;
        sidebar.inert = false;
        sidebar.removeAttribute('aria-hidden');
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        sidebar.classList.add('is-open');
        overlay?.classList.add('is-visible');
        menu?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        close?.focus();
        if (main) main.inert = true;
    }

    menu?.addEventListener('click', openDrawer);
    close?.addEventListener('click', () => closeDrawer());
    overlay?.addEventListener('click', () => closeDrawer());
    collapse?.addEventListener('click', () => {
        preferredCollapse = !preferredCollapse;
        setCollapsed(preferredCollapse);
        try { localStorage.setItem('assocmap.sidebarCollapsed', preferredCollapse ? '1' : '0'); } catch { /* Keep the current session usable. */ }
    });
    document.addEventListener('keydown', (event) => {
        if (!mobile || !sidebar.classList.contains('is-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); closeDrawer(); }
        if (event.key !== 'Tab') return;
        const controls = [...sidebar.querySelectorAll('a[href], button:not(:disabled)')].filter((element) => element.getClientRects().length);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    window.addEventListener('resize', () => {
        const nextMobile = isMobile();
        if (nextMobile === mobile) return;
        const focusWasInSidebar = sidebar.contains(document.activeElement);
        const focusWasMenu = document.activeElement === menu;
        mobile = nextMobile;
        closeDrawer(false);
        setCollapsed(!mobile && preferredCollapse);
        if (mobile && focusWasInSidebar) menu?.focus();
        else if (!mobile && (focusWasInSidebar || focusWasMenu)) collapse?.focus();
    });
    setCollapsed(!mobile && preferredCollapse);
    closeDrawer(false);
}
