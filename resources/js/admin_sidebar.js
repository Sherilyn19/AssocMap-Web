/**
 * resources/js/admin_sidebar.js
 * Sidebar collapse (desktop) + off-canvas drawer (mobile) behaviour.
 * Imported once from resources/js/app.js.
 */

const MOBILE_BREAKPOINT = 1024;

function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuBtn = document.getElementById('sidebarMenuBtn');       // topbar hamburger
    const collapseBtn = document.getElementById('sidebarCollapseBtn'); // sidebar chevron

    if (!sidebar || !overlay || !menuBtn || !collapseBtn) {
        return; // this page doesn't use the dashboard layout — nothing to wire up
    }

    const isMobile = () => window.innerWidth < MOBILE_BREAKPOINT;

    function applyStoredCollapseState() {
        const collapsed = localStorage.getItem('assocmap.sidebarCollapsed') === '1';
        if (collapsed && !isMobile()) {
            sidebar.classList.add('is-collapsed');
        }
    }

    function toggleCollapse() {
        sidebar.classList.toggle('is-collapsed');
        localStorage.setItem(
            'assocmap.sidebarCollapsed',
            sidebar.classList.contains('is-collapsed') ? '1' : '0'
        );
    }

    function openDrawer() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
        menuBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        menuBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    menuBtn.addEventListener('click', () => {
        if (isMobile()) {
            sidebar.classList.contains('is-open') ? closeDrawer() : openDrawer();
        } else {
            toggleCollapse();
        }
    });

    collapseBtn.addEventListener('click', toggleCollapse);
    overlay.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDrawer();
    });

    let lastIsMobile = isMobile();
    window.addEventListener('resize', () => {
        const nowMobile = isMobile();
        if (nowMobile !== lastIsMobile) {
            closeDrawer();
            sidebar.classList.remove('is-collapsed');
            applyStoredCollapseState();
            lastIsMobile = nowMobile;
        }
    });

    applyStoredCollapseState();
}

document.addEventListener('DOMContentLoaded', initSidebar);