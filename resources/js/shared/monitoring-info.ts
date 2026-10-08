/**
 * Enhance native information disclosures.
 * Click/tap remains available when hover is unavailable.
 */
let hovered: HTMLDetailsElement | null = null;
let closeTimer: ReturnType<typeof setTimeout> | undefined;

const clearCloseTimer = (): void => {
    if (closeTimer !== undefined) clearTimeout(closeTimer);
    closeTimer = undefined;
};

document.addEventListener('pointerover', event => {
    // Touch users open information by tapping the native summary.
    if (event.pointerType !== 'mouse') return;

    const target = event.target;
    if (!(target instanceof Element)) return;

    const info = target.closest<HTMLDetailsElement>(
        'details[data-monitoring-info]',
    );

    if (!info) return;

    clearCloseTimer();

    if (hovered && hovered !== info) hovered.open = false;

    hovered = info;
    info.open = true;
});

document.addEventListener('pointerout', event => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const info = target.closest<HTMLDetailsElement>(
        'details[data-monitoring-info]',
    );

    if (!info || info !== hovered) return;

    const next = event.relatedTarget;
    if (next instanceof Node && info.contains(next)) return;

    clearCloseTimer();

    // Allow movement from the icon into its explanation.
    closeTimer = setTimeout(() => {
        if (!info.contains(document.activeElement)) info.open = false;
        if (hovered === info) hovered = null;
    }, 160);
});

document.addEventListener('click', event => {
    const target = event.target;
    if (!(target instanceof Node)) return;

    document.querySelectorAll<HTMLDetailsElement>(
        'details[data-monitoring-info][open]',
    ).forEach(info => {
        if (!info.contains(target)) info.open = false;
    });
});

// Dismiss information before Escape closes the surrounding modal.
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;

    const opened = document.querySelectorAll<HTMLDetailsElement>(
        'details[data-monitoring-info][open]',
    );

    if (!opened.length) return;

    event.preventDefault();
    event.stopPropagation();
    clearCloseTimer();

    opened.forEach(info => { info.open = false; });
    hovered = null;
}, true);