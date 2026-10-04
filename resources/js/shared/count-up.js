// Turn each digit like a calendar page, leaving time to see each new number.
const minimumDuration = 2400;
const minimumStep = 650;

function makeHalf(text, position, turning = false) {
    const half = document.createElement('span');
    half.className = `am-count-half am-count-${position}${turning ? ' am-count-turn' : ''}`;
    const content = document.createElement('span');
    content.textContent = text;
    half.append(content);
    return half;
}

function flipDigit(digit, next, duration) {
    digit.animations.forEach(animation => animation.cancel());
    const previous = digit.value;
    digit.value = next;
    const top = makeHalf(previous, 'top', true);
    const bottom = makeHalf(next, 'bottom', true);
    digit.element.replaceChildren(
        makeHalf(next, 'top'), makeHalf(previous, 'bottom'), top, bottom,
    );
    // The old upper page folds down first; the new lower page then opens over it.
    digit.animations = [
        top.animate([
            { transform: 'rotateX(0deg)', filter: 'brightness(1)', offset: 0 },
            { transform: 'rotateX(-90deg)', filter: 'brightness(.75)', offset: .5 },
            { transform: 'rotateX(-90deg)', filter: 'brightness(.75)', offset: 1 },
        ], { duration, easing: 'ease-in-out', fill: 'forwards' }),
        bottom.animate([
            { transform: 'rotateX(90deg)', filter: 'brightness(.75)', offset: 0 },
            { transform: 'rotateX(90deg)', filter: 'brightness(.75)', offset: .5 },
            { transform: 'rotateX(0deg)', filter: 'brightness(1)', offset: 1 },
        ], { duration, easing: 'ease-in-out', fill: 'forwards' }),
    ];
}

function initializeCounters() {
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const prepared = new WeakSet();
    const pending = new Map();
    const active = new Map();
    let frame;

    function finish(element, state) {
        state.digits.forEach(digit => digit.animations.forEach(animation => animation.cancel()));
        state.visual.textContent = state.finalText;
        active.delete(element);
    }

    function tick(now) {
        active.forEach((state, element) => {
            if (!element.isConnected || motion.matches) {
                finish(element, state);
                return;
            }
            state.start ??= now;
            const elapsed = now - state.start;
            const step = Math.floor(elapsed / state.interval);
            state.digits.forEach(digit => {
                const next = Math.min(step, digit.target);
                if (String(next) !== digit.value) flipDigit(digit, String(next), state.interval * .82);
            });
            // Keep the final turn visible until its lower page has fully opened.
            if (elapsed >= (state.steps + 1) * state.interval) finish(element, state);
        });
        frame = active.size ? requestAnimationFrame(tick) : undefined;
    }

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const state = pending.get(entry.target);
            if (!state) return;
            observer.unobserve(entry.target);
            pending.delete(entry.target);
            if (motion.matches) finish(entry.target, state);
            else {
                active.set(entry.target, state);
                frame ??= requestAnimationFrame(tick);
            }
        });
    });

    function prepare(element) {
        if (prepared.has(element)) return;
        prepared.add(element);
        const finalText = element.textContent.trim();
        const match = finalText.match(/^(\d[\d,]*)(?:\.(\d+))?(%)?$/);
        if (!match || motion.matches) return;
        const target = Number(finalText.replaceAll(',', '').replace('%', ''));
        if (!Number.isFinite(target) || target === 0) return;
        // Screen readers receive the final value once, without announcing every step.
        const accessible = document.createElement('span');
        accessible.className = 'sr-only';
        accessible.textContent = finalText;
        const visual = document.createElement('span');
        visual.className = 'am-count-flip';
        visual.setAttribute('aria-hidden', 'true');
        // Match the actual card surface so overlapping pages also work on tinted cards.
        let surface = element;
        while (surface) {
            const background = getComputedStyle(surface).backgroundColor;
            if (background !== 'transparent' && background !== 'rgba(0, 0, 0, 0)') {
                visual.style.setProperty('--count-surface', background);
                break;
            }
            surface = surface.parentElement;
        }
        const digits = [];
        for (const character of finalText) {
            const part = document.createElement('span');
            if (/\d/.test(character)) {
                part.className = 'am-count-digit';
                part.append(makeHalf('0', 'top'), makeHalf('0', 'bottom'));
                digits.push({ element: part, target: Number(character), value: '0', animations: [] });
            } else {
                part.textContent = character;
            }
            visual.append(part);
        }
        const steps = Math.max(...digits.map(digit => digit.target));
        element.replaceChildren(accessible, visual);
        pending.set(element, {
            finalText, digits, visual, steps,
            interval: Math.max(minimumStep, minimumDuration / (steps + 1)),
        });
        observer.observe(element);
    }

    function scan(root) {
        if (!(root instanceof Element)) return;
        if (root.matches('[data-count-up]')) prepare(root);
        root.querySelectorAll('[data-count-up]').forEach(prepare);
    }

    scan(document.body);
    // Newly fetched cards use the same behavior without changing page-specific code.
    new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(scan));
    }).observe(document.body, { childList: true, subtree: true });

    motion.addEventListener('change', () => {
        if (!motion.matches) return;
        active.forEach((state, element) => finish(element, state));
        pending.forEach((state, element) => finish(element, state));
        pending.clear();
        observer.disconnect();
        cancelAnimationFrame(frame);
        frame = undefined;
    });
}

if ('IntersectionObserver' in window) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCounters, { once: true });
    } else initializeCounters();
}
