/**
 * resources/js/sidebar-tooltip.js
 *
 * Shows a fixed-position tooltip next to each sidebar icon when the
 * sidebar is collapsed. Rendered on <body> (not inside the sidebar),
 * so it is never clipped by the rail's own overflow — this is what
 * makes it reliable, unlike a pure-CSS ::after tooltip nested inside
 * a scrollable container.
 *
 * Requires each nav link to have a `data-tip="Label"` attribute
 * (or a child .am-nav-link__label with the text) and the sidebar
 * element to have id="sidebar".
 */
(function () {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    const tooltip = document.createElement('div');
    tooltip.className = 'am-tooltip';
    document.body.appendChild(tooltip);

    function isCollapsed() {
        return sidebar.classList.contains('is-collapsed');
    }

    function showTooltip(link) {
        if (!isCollapsed()) return;

        const label =
            link.dataset.tip ||
            link.querySelector('.am-nav-link__label')?.textContent.trim();

        if (!label) return;

        const rect = link.getBoundingClientRect();
        tooltip.textContent = label;
        tooltip.style.left = rect.right + 12 + 'px';
        tooltip.style.top = rect.top + rect.height / 2 + 'px';
        tooltip.style.transform = 'translateY(-50%)';
        tooltip.classList.add('is-visible');
    }

    function hideTooltip() {
        tooltip.classList.remove('is-visible');
    }

    sidebar.querySelectorAll('.am-nav-link').forEach((link) => {
        link.addEventListener('mouseenter', () => showTooltip(link));
        link.addEventListener('mouseleave', hideTooltip);
        link.addEventListener('focus', () => showTooltip(link));
        link.addEventListener('blur', hideTooltip);
    });

    // Hide instantly on scroll/resize so it doesn't float in the wrong spot
    window.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);
})();