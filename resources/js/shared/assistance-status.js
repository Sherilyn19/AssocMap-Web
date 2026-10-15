import '../../css/assistance-status.css';

// One delegated workflow also handles forms inserted into the FO modal.
const active = new WeakSet();
const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const fade = async (element, leaving = false) => {
    if (reduced()) return;
    await element.animate(
        leaving ? [{ opacity: 1 }, { opacity: 0 }] : [{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: 180, easing: 'ease-out' },
    ).finished.catch(() => {});
};

function feedback(form, message, warning = false) {
    const box = form.querySelector('[data-assistance-feedback]');
    box.textContent = message;
    box.className = 'am-assistance-feedback';
    box.dataset.tone = warning ? 'warning' : 'success';
    fade(box);
}

async function json(response) {
    if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Unexpected response');
    const result = await response.json();
    if (!response.ok) {
        const error = new Error(result.message || 'The request could not be completed.');
        error.confirmedFailure = response.status >= 400 && response.status < 500;
        error.messages = Object.values(result.errors || {}).flat();
        throw error;
    }
    return result;
}

function confirmTermination(form) {
    return new Promise(resolve => {
        const dialog = document.createElement('dialog');
        dialog.className = 'am-assistance-dialog space-y-4';
        const headingId = `assistance-confirm-${crypto.randomUUID()}`;
        dialog.setAttribute('aria-labelledby', headingId);
        dialog.innerHTML = `<div class="am-assistance-mark" aria-hidden="true">✓</div>
            <h2 class="font-semibold text-xl">Ready for termination</h2>
            <p>No pending work found.</p><strong data-name></strong>
            <p>This permanently ends assistance and makes this association and its related records read-only.</p>
            <label class="block">Type <strong data-phrase></strong> to confirm.
                <input type="text" autocomplete="off" spellcheck="false" class="block w-full mt-2" data-confirm-input>
            </label>
            <div class="flex flex-wrap gap-3"><button type="button" class="am-user-button am-user-button-secondary" data-cancel>Cancel</button>
            <button type="button" class="am-user-button am-user-button-primary" data-confirm disabled>Confirm termination</button></div>`;
        dialog.querySelector('h2').id = headingId;
        dialog.querySelector('[data-name]').textContent = form.dataset.associationName;
        dialog.querySelector('[data-phrase]').textContent = form.dataset.confirmation;
        const input = dialog.querySelector('[data-confirm-input]');
        const confirm = dialog.querySelector('[data-confirm]');
        let settled = false;
        const finish = async value => {
            if (settled) return;
            settled = true;
            confirm.disabled = true;
            await fade(dialog, true);
            dialog.close();
            dialog.remove();
            form.querySelector('button[type="submit"]')?.focus();
            resolve(value);
        };
        input.addEventListener('input', () => { confirm.disabled = input.value !== form.dataset.confirmation; });
        confirm.addEventListener('click', () => { if (input.value === form.dataset.confirmation) finish(input.value); });
        dialog.querySelector('[data-cancel]').addEventListener('click', () => finish(null));
        dialog.addEventListener('cancel', event => { event.preventDefault(); finish(null); });
        document.body.append(dialog);
        dialog.showModal();
        input.focus();
        fade(dialog);
    });
}

async function save(form) {
    if (active.has(form)) return;
    active.add(form);
    const button = form.querySelector('button[type="submit"]');
    const original = button.textContent;
    const terminating = form.elements.assistance_status.value === 'terminated';
    form.elements.assessment_date.disabled = !terminating;
    form.elements.assessment_date.required = terminating;
    if (!form.reportValidity()) { active.delete(form); return; }
    let writing = false;
    let keepDisabled = false;
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    form.querySelector('[data-assistance-links]').hidden = true;
    try {
        let phrase = '';
        if (terminating) {
            button.textContent = 'Checking pending work…';
            const result = await json(await fetch(form.dataset.eligibilityUrl, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(30000),
            }));
            if (!form.isConnected || (form.closest('dialog') && !form.closest('dialog').open)) return;
            if (!result.eligible) {
                const c = result.counts;
                feedback(form, `Termination blocked: ${c.applications} pending applications, ${c.drafts} unfinished drafts, ${c.attendance} pending attendance entries. Resolve these records or refresh if the association is already read-only.`, true);
                form.querySelector('[data-assistance-links]').hidden = false;
                return;
            }
            form.removeAttribute('aria-busy');
            phrase = await confirmTermination(form);
            if (phrase === null || !form.isConnected || (form.closest('dialog') && !form.closest('dialog').open)) return;
        }
        const body = new FormData(form);
        body.set('assistance_confirmation', phrase);
        writing = true;
        button.textContent = 'Saving…';
        form.setAttribute('aria-busy', 'true');
        // Do not abort or automatically repeat a write: its result may be unknown.
        const result = await json(await fetch(form.action, {
            method: 'POST', body, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }));
        keepDisabled = true;
        form.querySelectorAll('input, textarea, select, button').forEach(el => { el.disabled = true; });
        feedback(form, `✓ ${result.message}`);
        const link = document.createElement('a');
        link.href = result.redirect_url;
        link.className = 'am-user-button am-user-button-secondary';
        link.textContent = 'View updated association';
        form.querySelector('[data-assistance-feedback]').append(document.createElement('br'), link);
        // Keep confirmed success visible until the user chooses to continue.
    } catch (error) {
        keepDisabled = writing && !error.confirmedFailure;
        feedback(form, keepDisabled
            ? 'The save result could not be confirmed. Refresh Association Details before trying again.'
            : (error.messages?.join(' ') || 'The check could not finish. Please try again.'), true);
    } finally {
        form.removeAttribute('aria-busy');
        button.disabled = keepDisabled;
        button.textContent = original;
        active.delete(form);
    }
}

document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-assistance-form]')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    save(form);
}, true);

// Keep the assessment requirement specific to termination.
document.addEventListener('change', event => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || select.name !== 'assistance_status') return;
    const form = select.closest('[data-assistance-form]');
    if (!form) return;
    const terminating = select.value === 'terminated';
    form.elements.assessment_date.disabled = !terminating;
    form.elements.assessment_date.required = terminating;
    form.querySelector('[data-assistance-reason-label]').textContent = terminating
        ? 'Assessment summary / reason for termination' : 'Reason for change';
});
