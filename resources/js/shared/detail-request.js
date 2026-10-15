/**
 * Shared timing for read-only detail requests; never retries a write.
 * @param {AbortController} controller
 * @param {() => void} onSlow
 */
export function trackDetailRequest(controller, onSlow) {
    let timedOut = false;
    let slowTimer;
    let timeout;
    const dispose = () => {
        clearTimeout(slowTimer);
        clearTimeout(timeout);
        controller.signal.removeEventListener('abort', dispose);
    };
    if (!controller.signal.aborted) {
        slowTimer = setTimeout(() => {
            if (!controller.signal.aborted) onSlow();
        }, 8000);
        timeout = setTimeout(() => {
            timedOut = true;
            controller.abort();
        }, 30000);
        controller.signal.addEventListener('abort', dispose, { once: true });
    }
    return { dispose, get timedOut() { return timedOut; } };
}
