import '../../css/assistance-status.css';

// Delegated events also support forms loaded into association modals.
const active = new WeakSet();

const labels = {
    proposal: {
        title: 'Confirm proposal',
        date: 'Effective date',
        reason: 'Reason for proposal',
        message: 'Record this association as proposed for program assistance.',
    },
    accepted: {
        title: 'Confirm acceptance',
        date: 'Effective date',
        reason: 'Reason for acceptance',
        message: 'Record this association as accepted for program assistance.',
    },
    terminated: {
        title: 'Confirm termination',
        date: 'Assessment date',
        reason: 'Assessment summary / reason for termination',
        message: 'Permanently end assistance and make all association records read-only.',
    },
};

async function fade(element, leaving = false) {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    await element.animate(
        leaving
            ? [{ opacity: 1 }, { opacity: 0 }]
            : [{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: 180, easing: 'ease-out' },
    ).finished.catch(() => {});
}

function feedback(form, message, warning = false) {
    const box = form.querySelector('[data-assistance-feedback]');
    box.textContent = message;
    box.className = 'am-assistance-feedback';
    box.dataset.tone = warning ? 'warning' : 'success';
    fade(box);
}

function syncFields(form) {
    const selected = labels[form.elements.assistance_status.value];
    const visible = Boolean(selected);

    form.querySelector('[data-assistance-date]').hidden = !visible;
    form.querySelector('[data-assistance-details]').hidden = !visible;
    form.querySelector('[data-assistance-termination]').hidden =
        form.elements.assistance_status.value !== 'terminated';

    for (const name of ['assessment_date', 'reason']) {
        form.elements[name].disabled = !visible;
        form.elements[name].required = visible;
    }

    form.querySelector('button[type="submit"]').disabled = !visible;

    if (selected) {
        form.querySelector('[data-assistance-date-label]').textContent = selected.date;
        form.querySelector('[data-assistance-reason-label]').textContent = selected.reason;
    }
}

async function readJson(response) {
    if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('Unexpected response');
    }

    const result = await response.json();

    if (!response.ok) {
        const error = new Error(result.message || 'The request could not be completed.');
        error.confirmedFailure = response.status >= 400 && response.status < 500;
        error.messages = Object.values(result.errors || {}).flat();
        throw error;
    }

    return result;
}

function stillOpen(form) {
    const parent = form.closest('dialog');
    return form.isConnected && (!parent || parent.open);
}

function confirmChange(form, status, body) {
    return new Promise(resolve => {
        const selected = labels[status];
        const dialog = document.createElement('dialog');
        const headingId = `assistance-confirm-${crypto.randomUUID()}`;

        dialog.className = 'am-assistance-dialog space-y-4';
        dialog.setAttribute('aria-labelledby', headingId);

        dialog.innerHTML = `
            <h2 class="font-semibold text-xl"></h2>
            <strong data-name></strong>
            <p data-message></p>
            <p data-date></p>
            <p class="whitespace-pre-line" data-reason></p>
            <label class="block">
                Type <strong data-phrase></strong> to confirm.
                <input type="text" autocomplete="off" spellcheck="false"
                       class="block w-full mt-2" data-confirm-input>
            </label>
            <div class="flex flex-wrap gap-3">
                <button type="button"
                        class="am-user-button am-user-button-secondary"
                        data-cancel>Cancel</button>
                <button type="button"
                        class="am-user-button am-user-button-primary"
                        data-confirm disabled>Confirm</button>
            </div>`;

        dialog.querySelector('h2').id = headingId;
        dialog.querySelector('h2').textContent = selected.title;
        dialog.querySelector('[data-name]').textContent = form.dataset.associationName;
        dialog.querySelector('[data-message]').textContent = selected.message;
        dialog.querySelector('[data-date]').textContent =
            `${selected.date}: ${body.get('assessment_date')}`;
        dialog.querySelector('[data-reason]').textContent =
            `${selected.reason}: ${body.get('reason')}`;
        dialog.querySelector('[data-phrase]').textContent = form.dataset.confirmation;

        const input = dialog.querySelector('[data-confirm-input]');
        const button = dialog.querySelector('[data-confirm]');
        let settled = false;

        const finish = async value => {
            if (settled) return;
            settled = true;
            button.disabled = true;
            await fade(dialog, true);
            dialog.close();
            dialog.remove();
            resolve(value);
        };

        input.addEventListener('input', () => {
            button.disabled = input.value !== form.dataset.confirmation;
        });

        button.addEventListener('click', () => {
            if (input.value === form.dataset.confirmation) finish(input.value);
        });

        dialog.querySelector('[data-cancel]').addEventListener('click', () => finish(null));
        dialog.addEventListener('cancel', event => {
            event.preventDefault();
            finish(null);
        });

        document.body.append(dialog);
        dialog.showModal();
        input.focus();
        fade(dialog);
    });
}

async function save(form) {
    if (active.has(form)) return;

    syncFields(form);
    if (!form.reportValidity()) return;

    const status = form.elements.assistance_status.value;
    if (!labels[status]) return;

    active.add(form);

    // Confirm and save the same values, even while awaiting the server.
    const body = new FormData(form);
    const controls = [...form.querySelectorAll('input, textarea, select, button')];
    const previousDisabled = controls.map(control => control.disabled);
    const button = form.querySelector('button[type="submit"]');
    const originalText = button.textContent;

    controls.forEach(control => { control.disabled = true; });
    form.setAttribute('aria-busy', 'true');

    let writing = false;
    let keepDisabled = false;

    try {
        if (status === 'terminated') {
            button.textContent = 'Checking pending work…';

            const result = await readJson(await fetch(form.dataset.eligibilityUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(30000),
            }));

            if (!stillOpen(form)) return;

            if (!result.eligible) {
                const counts = result.counts;
                feedback(
                    form,
                    `Termination unavailable: ${counts.applications} pending applications, `
                    + `${counts.drafts} unfinished drafts, and ${counts.attendance} pending attendance entries. `
                    + 'Resolve pending work. If all counts are zero, refresh the association status.',
                    true,
                );
                return;
            }
        }

        form.removeAttribute('aria-busy');

        const phrase = await confirmChange(form, status, body);
        if (phrase === null || !stillOpen(form)) return;

        body.set('assistance_confirmation', phrase);
        button.textContent = 'Saving…';
        form.setAttribute('aria-busy', 'true');
        writing = true;

        // Never automatically retry a write whose outcome is unknown.
        const result = await readJson(await fetch(form.action, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }));

        keepDisabled = true;
        feedback(form, `✓ ${result.message}`);

        const link = document.createElement('a');
        link.href = result.redirect_url;
        link.className = 'am-user-button am-user-button-secondary';
        link.textContent = 'View updated association';

        form.querySelector('[data-assistance-feedback]').append(
            document.createElement('br'),
            link,
        );
    } catch (error) {
        keepDisabled = writing && !error.confirmedFailure;

        feedback(
            form,
            keepDisabled
                ? 'The save result could not be confirmed. Refresh Association Details before trying again.'
                : (error.messages?.join(' ') || 'The request could not finish. Please try again.'),
            true,
        );
    } finally {
        form.removeAttribute('aria-busy');
        button.textContent = originalText;

        if (!keepDisabled) {
            controls.forEach((control, index) => {
                control.disabled = previousDisabled[index];
            });
            button.focus();
        }

        active.delete(form);
    }
}

// Status radios reuse the existing date, reason and confirmation workflow.
document.addEventListener('change', event => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement)
        || input.type !== 'radio'
        || input.name !== 'assistance_status') return;

    const form = input.closest('[data-assistance-form]');
    if (form && !active.has(form)) syncFields(form);
});

document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-assistance-form]')) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    save(form);
}, true);