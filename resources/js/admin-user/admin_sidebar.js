/**
 * resources/js/admin-user/admin_sidebar.js
 * Sidebar collapse for desktop and off-canvas drawer for mobile.
 */
const ASSOCMAP_MOBILE_BREAKPOINT = 1024;

document.addEventListener("DOMContentLoaded", initAssocMapSidebar);

function initAssocMapSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const menuBtn = document.getElementById("sidebarMenuBtn");
    const collapseBtn = document.getElementById("sidebarCollapseBtn");

    if (!sidebar) return;

    const isMobile = () => window.innerWidth < ASSOCMAP_MOBILE_BREAKPOINT;

    function setCollapsed(collapsed) {
        sidebar.classList.toggle("is-collapsed", collapsed);
        document.body.classList.toggle("am-sidebar-collapsed", collapsed);
        localStorage.setItem("assocmap.sidebarCollapsed", collapsed ? "1" : "0");

        if (collapseBtn) {
            collapseBtn.setAttribute("aria-expanded", collapsed ? "false" : "true");
            collapseBtn.setAttribute("aria-label", collapsed ? "Expand sidebar" : "Collapse sidebar");
        }
    }

    function applyStoredCollapseState() {
        if (isMobile()) {
            setCollapsed(false);
            return;
        }

        setCollapsed(localStorage.getItem("assocmap.sidebarCollapsed") === "1");
    }

    function toggleCollapse(event) {
        event?.preventDefault();
        event?.stopPropagation();

        if (isMobile()) {
            toggleDrawer();
            return;
        }

        setCollapsed(!sidebar.classList.contains("is-collapsed"));
    }

    function openDrawer() {
        sidebar.classList.add("is-open");
        overlay?.classList.add("is-visible");
        menuBtn?.setAttribute("aria-expanded", "true");
        document.body.style.overflow = "hidden";
    }

    function closeDrawer() {
        sidebar.classList.remove("is-open");
        overlay?.classList.remove("is-visible");
        menuBtn?.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
    }

    function toggleDrawer() {
        sidebar.classList.contains("is-open") ? closeDrawer() : openDrawer();
    }

    menuBtn?.addEventListener("click", (event) => {
        event.preventDefault();
        isMobile() ? toggleDrawer() : toggleCollapse(event);
    });

    collapseBtn?.addEventListener("click", toggleCollapse);
    overlay?.addEventListener("click", closeDrawer);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") closeDrawer();
    });

    window.addEventListener("resize", () => {
        closeDrawer();
        applyStoredCollapseState();
    });

    applyStoredCollapseState();
}