// Shared drawer headings work across roles through data attributes.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-context-drawer]').forEach(drawer => {
        const heading = drawer.querySelector('[data-drawer-heading]');
        const scrollArea = drawer.querySelector('[data-drawer-scroll]');

        if (!heading || !scrollArea) return;

        const baseTitle = heading.dataset.baseTitle
            || heading.textContent.trim();
        const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
        let frame = 0;
        let animation;

        function updateHeading() {
            frame = 0;

            const introduction = scrollArea.querySelector('[data-drawer-context]');
            let nextTitle = baseTitle;

            // Add context only after the introduction leaves the visible area.
            if (introduction && !drawer.hidden) {
                const introductionBottom = introduction.getBoundingClientRect().bottom;
                const visibleTop = scrollArea.getBoundingClientRect().top;

                if (introductionBottom <= visibleTop + 8) {
                    nextTitle = `${baseTitle} / ${introduction.dataset.drawerContext}`;
                }
            }

            if (heading.textContent.trim() === nextTitle) return;

            animation?.cancel();
            heading.textContent = nextTitle;

            // Animate only the title, keeping the drawer and its controls still.
            if (!reducedMotion.matches && !drawer.hidden) {
                animation = heading.animate(
                    [
                        { opacity: 0, transform: 'translateY(4px)' },
                        { opacity: 1, transform: 'translateY(0)' },
                    ],
                    { duration: 180, easing: 'ease-out' }
                );
            }
        }

        function scheduleUpdate() {
            if (!frame) frame = requestAnimationFrame(updateHeading);
        }

        scrollArea.addEventListener('scroll', scheduleUpdate, { passive: true });

        // Loaded tabs, pagination, and loading messages replace drawer content.
        const contentObserver = new MutationObserver(scheduleUpdate);
        contentObserver.observe(scrollArea, { childList: true, subtree: true });

        const visibilityObserver = new MutationObserver(scheduleUpdate);
        visibilityObserver.observe(drawer, {
            attributes: true,
            attributeFilter: ['hidden'],
        });

        const resizeObserver = new ResizeObserver(scheduleUpdate);
        resizeObserver.observe(scrollArea);

        reducedMotion.addEventListener('change', () => {
            animation?.cancel();
            scheduleUpdate();
        });

        scheduleUpdate();
    });
});