/** Association forms: preserve the user's work and make pending/failed saves explicit. */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-association-page]');
    if (!page) return;
    const parse = (value, fallback) => { try { return JSON.parse(value ?? '') ?? fallback; } catch { return fallback; } };
    const barangays = parse(page.dataset.barangays, []);
    const historyBarangays = parse(page.dataset.filterBarangays, barangays);
    const recovery = parse(page.dataset.recovery, null);
    let activeModal = null;
    let opener = null;
    let inertSiblings = [];
    let pendingForm = null;
    const uncertainActions = new Set();
    const editDrafts = new Map();
    const confirmation = page.querySelector('[data-association-confirm]');
    let confirmationForm = null;
    let confirmedForm = null;
    let confirmationTrigger = null;
    const errorBox = form => form.hasAttribute('data-confirm-form') && confirmation?.open
        ? confirmation.querySelector('[data-form-error]') : form.querySelector('[data-form-error]');
    const focusable = (modal) => [...modal.querySelectorAll('button, a[href], input, select, textarea, [tabindex="0"]')].filter(el => !el.disabled && el.getClientRects().length);
    function openModal(modal) {
        if (!modal || pendingForm) return;
        opener = document.activeElement;
        activeModal = modal;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        // Inert siblings at every ancestor level, including the dashboard sidebar.
        let current = modal;
        while (current.parentElement && current !== document.body) {
            [...current.parentElement.children].forEach(el => {
                if (el !== current && !el.inert && el.tagName !== 'SCRIPT' && !el.classList.contains('management-loading')) { el.inert = true; inertSiblings.push(el); }
            });
            current = current.parentElement;
        }
        (modal.querySelector('[aria-invalid="true"]') || focusable(modal)[0])?.focus();
    }
    function closeModal() {
        if (!activeModal) return;
        if (pendingForm && activeModal.contains(pendingForm)) return;
        activeModal.classList.add('hidden');
        activeModal.setAttribute('aria-hidden', 'true');
        inertSiblings.forEach(el => { el.inert = false; });
        inertSiblings = [];
        document.body.classList.remove('overflow-hidden');
        activeModal = null;
        if (opener?.isConnected) opener.focus();
    }
    function populate(select, municipality, selected = '', filtering = false) {
        if (!select) return;
        const source = filtering ? historyBarangays : barangays;
        const matching = source.filter(row => !municipality && filtering || String(row.area_unit_id) === String(municipality));
        select.replaceChildren(new Option(filtering ? 'All barangays' : municipality ? 'Select barangay' : 'Select municipality first', ''));
        matching.forEach(row => select.add(new Option(row.name, String(row.id), false, String(row.id) === String(selected))));
        select.disabled = !filtering && (!municipality || matching.length === 0);
    }
    page.querySelectorAll('form').forEach(form => {
        const municipality = form.querySelector('[data-municipality]');
        const barangay = form.querySelector('[data-barangay]');
        if (!municipality || !barangay) return;
        populate(barangay, municipality.value, barangay.dataset.selectedValue);
        municipality.addEventListener('change', () => populate(barangay, municipality.value));
    });
    const municipality = page.querySelector('[data-filter-municipality]');
    const barangay = page.querySelector('[data-filter-barangay]');
    if (municipality && barangay) {
        populate(barangay, municipality.value, new URLSearchParams(location.search).get('sub_unit_id'), true);
        municipality.addEventListener('change', () => populate(barangay, municipality.value, '', true));
    }
    page.querySelectorAll('[data-open-modal]').forEach(button => button.addEventListener('click', () => openModal(document.getElementById(button.dataset.openModal))));
    page.querySelectorAll('[data-close-modal]').forEach(button => button.addEventListener('click', closeModal));
    page.querySelectorAll('[data-edit-association]').forEach(button => button.addEventListener('click', () => {
        const row = parse(button.dataset.editAssociation, null);
        const modal = document.getElementById('edit-association-modal');
        const form = modal?.querySelector('form');
        if (!row || !form) return;
        if (pendingForm) return;
        // Keep attempted edits when switching records. A shared modal must not
        // accidentally unlock a previously uncertain save or lose its draft.
        if (form.dataset.loadedAction) editDrafts.set(form.dataset.loadedAction, Object.fromEntries(new FormData(form)));
        form.reset();
        clearErrors(form);
        form.action = row.update_url;
        form.dataset.loadedAction = form.action;
        const values = editDrafts.get(form.action) || row;
        Object.entries(values).forEach(([key, value]) => {
            const field = form.querySelector(`[data-field="${key}"]`);
            if (field && key !== 'sub_unit_id') field.value = value ?? '';
        });
        populate(form.querySelector('[data-barangay]'), values.area_unit_id, values.sub_unit_id);
        const unknown = uncertainActions.has(form.action);
        form.dataset.outcomeUnknown = String(unknown);
        form.querySelectorAll('button[type="submit"]').forEach(el => { el.disabled = unknown; });
        openModal(modal);
        if (unknown) {
            showError(form, 'This record has an unconfirmed save. Check the records before making another change.');
            addRecordsLink(form);
        }
    }));
    document.addEventListener('keydown', event => {
        if (!activeModal || document.querySelector('.management-loading[open]')) return;
        if (event.key === 'Escape') { event.preventDefault(); closeModal(); }
        if (event.key !== 'Tab') return;
        const fields = focusable(activeModal);
        const first = fields[0], last = fields.at(-1);
        if (!fields.length) return;
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    function clearErrors(form) {
        form.querySelectorAll('[data-field-error]').forEach(el => { el.textContent = ''; });
        form.querySelectorAll('[aria-invalid]').forEach(el => el.setAttribute('aria-invalid', 'false'));
        const box = errorBox(form);
        if (box) { box.replaceChildren(); box.classList.add('hidden'); }
    }
    function showError(form, message, errors = {}) {
        const box = errorBox(form);
        if (box) { box.textContent = message; box.classList.remove('hidden'); box.tabIndex = -1; }
        Object.entries(errors).forEach(([key, messages]) => {
            const field = form.elements.namedItem(key);
            const output = [...form.querySelectorAll('[data-field-error]')].find(el => el.dataset.fieldError === key);
            if (output) output.textContent = Array.isArray(messages) ? messages.join(' ') : String(messages);
            if (field instanceof HTMLElement) field.setAttribute('aria-invalid', 'true');
        });
        (form.querySelector('[aria-invalid="true"]') || box)?.focus();
    }
    page.querySelectorAll('form[method="POST"]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (pendingForm || !form.reportValidity()) return;
            const uncertain = uncertainActions.has(form.action) || form.dataset.outcomeUnknown === 'true';
            if (uncertain && !form.hasAttribute('data-confirm-form')) return;
            if (form.hasAttribute('data-confirm-form') && confirmedForm !== form) {
                confirmationForm = form;
                confirmationTrigger = event.submitter;
                if (confirmation?.showModal) {
                    const restoring = form.dataset.confirmAction === 'restore';
                    confirmation.querySelector('h2').textContent = restoring ? 'Restore Association?' : 'Archive Association?';
                    confirmation.querySelector('[data-confirm-record]').textContent = form.dataset.confirmName;
                    confirmation.querySelector('#association-confirm-description').textContent = form.dataset.confirmMessage;
                    const button = confirmation.querySelector('[data-confirm-submit]');
                    button.textContent = restoring ? 'Restore Association' : 'Archive Association';
                    button.classList.toggle('!bg-red-800', !restoring);
                    button.classList.toggle('!bg-emerald-800', restoring);
                    button.disabled = uncertain;
                    try {
                        confirmation.showModal();
                        clearErrors(form);
                        if (uncertain) {
                            showError(form, 'This record has an unconfirmed save. Check the records before making another change.');
                            addRecordsLink(form);
                        }
                        confirmation.querySelector('[data-confirm-cancel]').focus();
                        return;
                    } catch {
                        // Older/unsupported dialog implementations retain a confirmation.
                    }
                }
                if (uncertain || !window.confirm(form.dataset.confirmMessage || 'Continue?')) return;
            }
            if (uncertain) return;
            confirmedForm = null;
            clearErrors(form);
            // Capture inputs before disabling submit controls. The server remains authoritative.
            const body = new FormData(form);
            const buttons = [...form.querySelectorAll('button[type="submit"]')];
            if (form === confirmationForm && confirmation?.open) buttons.push(confirmation.querySelector('[data-confirm-submit]'));
            buttons.forEach(el => { el.disabled = true; });
            pendingForm = form;
            form.setAttribute('aria-busy', 'true');
            document.dispatchEvent(new CustomEvent('management:loading', { detail: { label: /archive/.test(form.action) ? 'Archiving association…' : /restore/.test(form.action) ? 'Restoring association…' : /representative/.test(form.action) ? 'Saving representative…' : 'Saving association…' } }));
            let unknown = false;
            let navigating = false;
            try {
                const response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Unexpected response');
                const result = await response.json();
                if (response.ok && result.redirect_url) {
                    navigating = true;
                    window.location.assign(result.redirect_url);
                    return;
                }
                // A confirmed save with a failed session must also block duplicate submission.
                unknown = Boolean(result.outcome_unknown || result.mutation_completed);
                showError(form, result.message || 'The request failed. Please check the form.', result.errors || {});
            } catch {
                // A network failure does not prove rollback. Do not automatically repeat a write.
                unknown = true;
                showError(form, 'The connection was interrupted. Your entered values remain here. Check the records before submitting again; the save may already have completed.');
            } finally {
                if (!navigating) {
                    document.dispatchEvent(new Event('management:loaded'));
                    pendingForm = null;
                    form.removeAttribute('aria-busy');
                    (form.querySelector('[aria-invalid="true"]') || errorBox(form))?.focus();
                    buttons.forEach(el => { el.disabled = unknown; });
                    form.dataset.outcomeUnknown = String(unknown);
                    if (unknown) {
                        uncertainActions.add(form.action);
                        addRecordsLink(form);
                    }
                }
            }
        });
    });
    document.addEventListener('management:slow', () => {
        if (pendingForm) {
            showError(pendingForm, 'Still waiting for the server. This save remains in progress; leaving the page does not cancel it. Please do not submit again.');
            addRecordsLink(pendingForm);
        }
    });
    function addRecordsLink(form) {
        const link = document.createElement('a');
        link.href = page.dataset.recordsUrl;
        link.target = '_blank'; link.rel = 'noopener';
        link.className = 'ml-2 font-semibold underline';
        link.textContent = 'Check association records in a new tab';
        errorBox(form)?.append(link);
    }
    // Submit the original CSRF/PATCH form only after confirmation. Failed writes
    // leave this dialog and its error visible; Cancel never starts a request.
    confirmation?.querySelector('[data-confirm-submit]').addEventListener('click', () => {
        if (!confirmationForm || pendingForm) return;
        confirmedForm = confirmationForm;
        confirmationForm.requestSubmit();
    });
    confirmation?.querySelector('[data-confirm-cancel]').addEventListener('click', () => {
        if (!pendingForm) confirmation.close();
    });
    confirmation?.addEventListener('cancel', event => { if (pendingForm) event.preventDefault(); });
    confirmation?.addEventListener('close', () => {
        confirmedForm = null;
        // Cancel also closes the More menu. Return focus to its visible summary,
        // not to a hidden (or uncertain-save-disabled) Archive/Restore button.
        const returnTarget = confirmationTrigger?.closest('[data-association-menu]')?.querySelector('summary') || confirmationTrigger;
        returnTarget?.focus();
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || confirmation?.open) return;
        page.querySelectorAll('[data-association-menu][open]').forEach(menu => {
            menu.open = false; menu.querySelector('summary').focus();
        });
    });
    document.addEventListener('click', event => {
        if (confirmation?.open) return;
        page.querySelectorAll('[data-association-menu][open]').forEach(menu => {
            if (!menu.contains(event.target)) menu.open = false;
        });
    });
    const filters = page.querySelector('[data-association-filters]');
    if (filters) {
        const serialize = () => new URLSearchParams(new FormData(filters)).toString();
        const applied = serialize();
        const updateHint = () => {
            const changed = serialize() !== applied;
            const hint = filters.querySelector('[data-filter-hint]');
            hint.textContent = changed ? 'Filters changed — apply to update the records.' : 'Choose filters, then apply to update the records.';
            hint.classList.toggle('text-amber-700', changed);
        };
        filters.addEventListener('input', updateHint);
        filters.addEventListener('change', updateHint);
    }
    const card = page.querySelector('[data-association-card-details]');
    if (card && !recovery?.mode && typeof HTMLDialogElement !== 'undefined') {
        const marker = document.createComment('Association card fallback');
        card.before(marker);
        const dialog = document.createElement('dialog');
        dialog.className = 'am-association-dialog w-[calc(100%-2rem)] max-w-5xl rounded-xl bg-white p-0 shadow-xl backdrop:bg-slate-950/50';
        dialog.setAttribute('aria-labelledby', 'association-card-title');
        dialog.append(card);
        document.body.append(dialog);
        const returnTarget = document.getElementById(`association-card-${card.dataset.associationCardDetails}`);
        try {
            dialog.showModal();
            card.querySelector('h2').focus();
            dialog.addEventListener('close', () => {
                try { history.replaceState(null, '', card.dataset.closeUrl); }
                catch { /* The Close link remains a normal navigation fallback. */ }
                returnTarget?.focus();
            });
            card.querySelector('[data-card-close]').addEventListener('click', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault(); dialog.close();
            });
            returnTarget?.addEventListener('click', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                try { dialog.showModal(); card.querySelector('h2').focus(); }
                catch { window.location.assign(returnTarget.href); }
            });
        } catch {
            // A dialog failure must not hide successfully loaded card records.
            marker.after(card);
            dialog.remove();
        }
    }
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            pendingForm = null;
            page.querySelectorAll('form').forEach(form => {
                form.removeAttribute('aria-busy');
                if (form.dataset.outcomeUnknown !== 'true') form.querySelectorAll('button[type="submit"]').forEach(el => { el.disabled = false; });
            });
        }
    });
    if (recovery?.mode === 'edit' || recovery?.mode === 'create') {
        const modal = document.getElementById(`${recovery.mode}-association-modal`);
        const form = modal?.querySelector('form');
        if (recovery.mode === 'edit' && form) form.dataset.loadedAction = form.action;
        openModal(modal);
    }
});
