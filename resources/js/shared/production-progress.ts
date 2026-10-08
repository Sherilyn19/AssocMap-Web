/**
 * Animate server-provided progress.
 * The browser does not calculate or save authoritative output totals.
 */
export function initializeProductionProgress(root: ParentNode): void {
    const reducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    );

    root.querySelectorAll<HTMLElement>('[data-water-progress]').forEach(gauge => {
        if (gauge.dataset.initialized === 'true') return;
        gauge.dataset.initialized = 'true';

        const raw = Number(gauge.dataset.waterProgress);
        const level = Number.isFinite(raw)
            ? Math.min(100, Math.max(0, raw))
            : 0;

        const fill = gauge.querySelector<HTMLElement>('.am-water-fill');
        if (!fill) return;

        const showFinalLevel = (): void => {
            fill.style.height = `${level}%`;
        };

        if (reducedMotion.matches) {
            showFinalLevel();
            return;
        }

        // Two frames allow the initial empty state to render before filling.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (!gauge.isConnected) return;
                showFinalLevel();
                gauge.classList.add('is-filling');
            });
        });
    });
}