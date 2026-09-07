/** Project-only progressive enhancement. Data and authorization remain server-side. */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-pm-page]');
    if (!page) return;
    page.classList.add('pm-enhanced');
    // The server renders the requested summary as a normal section. Move that same
    // content into a dialog when supported; its links still work without JavaScript.
    const summary = page.querySelector('[data-pm-summary]');
    if (summary && typeof HTMLDialogElement !== 'undefined') {
        const dialog = document.createElement('dialog');
        dialog.className = 'pm-dialog pm-page w-[calc(100%-2rem)] max-w-6xl rounded-xl bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/50';
        dialog.setAttribute('aria-labelledby', 'summary-title');
        dialog.append(summary);
        document.body.append(dialog);
        const returnTarget = document.getElementById(`summary-card-${summary.dataset.pmSummary}`);
        // Remove the summary query without reloading the list, then return keyboard
        // focus to the card that opened these details.
        dialog.addEventListener('close', () => {
            history.replaceState(null, '', summary.dataset.closeUrl);
            returnTarget?.focus();
        });
        summary.querySelector('[data-pm-close]').addEventListener('click', (event) => { event.preventDefault(); dialog.close(); });
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
        dialog.showModal();
        summary.querySelector('h2').focus();
        // Reopen the already-loaded group without navigating away from the focus target.
        returnTarget?.addEventListener('click', (event) => {
            event.preventDefault(); dialog.showModal(); summary.querySelector('h2').focus();
        });
    }

    // One confirmation dialog serves multiple archive forms. Retain the chosen
    // form and trigger so confirmation targets the right record and Cancel restores focus.
    const archiveDialog = page.querySelector('[data-pm-archive-dialog]');
    let archiveForm = null;
    let archiveTrigger = null;
    page.querySelectorAll('[data-pm-archive]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!archiveDialog?.showModal) {
                if (window.confirm(`Archive "${form.dataset.projectTitle}"? Historical records will be retained.`)) HTMLFormElement.prototype.submit.call(form);
                return;
            }
            archiveForm = form; archiveTrigger = event.submitter;
            archiveDialog.querySelector('[data-pm-archive-name]').textContent = form.dataset.projectTitle;
            archiveDialog.showModal();
        });
    });
    archiveDialog?.querySelector('[data-pm-archive-cancel]').addEventListener('click', () => archiveDialog.close());
    archiveDialog?.addEventListener('close', () => archiveTrigger?.focus());
    // Native submit bypasses our submit listener, avoiding a second confirmation.
    // The original form still carries its CSRF token and PATCH method override.
    archiveDialog?.querySelector('[data-pm-archive-confirm]').addEventListener('click', (event) => {
        if (archiveForm) { event.currentTarget.disabled = true; HTMLFormElement.prototype.submit.call(archiveForm); }
    });

    // Desktop and mobile Edit links share one editor per material. Opening only one
    // editor at a time makes the active record clear and keeps field IDs unique.
    const editors = [...page.querySelectorAll('[data-pm-editor]')];
    function openEditor(editor) {
        editors.forEach((other) => { if (other !== editor) other.open = false; });
        editor.open = true;
        editor.querySelector('input:not([type="hidden"]),select')?.focus();
    }
    page.querySelectorAll('[data-pm-open-editor]').forEach((link) => link.addEventListener('click', (event) => {
        const editor = document.getElementById(link.hash.slice(1));
        if (!editor) return;
        event.preventDefault(); editor._pmTrigger = link; openEditor(editor);
        editor.scrollIntoView({ block: 'nearest' });
    }));
    editors.forEach((editor) => {
        editor.addEventListener('toggle', () => {
            if (editor.open) editors.forEach((other) => { if (other !== editor) other.open = false; });
            else if (document.activeElement === editor.querySelector('summary')) (editor._pmTrigger || page.querySelector('[data-pm-open-editor]'))?.focus();
        });
        editor.querySelector('[data-pm-editor-cancel]').addEventListener('click', () => {
            editor.open = false; (editor._pmTrigger || editor.querySelector('summary')).focus();
        });
    });
    // Blade marks the failed form open using the server-generated recovery marker.
    const recovery = editors.find((editor) => editor.open);
    if (recovery) openEditor(recovery);

    page.querySelectorAll('[data-pm-validate]').forEach((form) => {
        // Keep native validation if this module does not load; enhance with inline errors.
        form.noValidate = true;
        const inputs = [...form.querySelectorAll('input:not([type="hidden"]),select,textarea')];
        function validate(input) {
            const error = document.getElementById(`${input.id}-error`);
            if (!error) return input.validity.valid;
            let message = '';
            if (!input.validity.valid) {
                if (input.validity.valueMissing) message = 'This field is required.';
                else if (input.validity.rangeUnderflow) message = input.name === 'quantity' ? 'Quantity must be greater than zero.' : 'Amount must be zero or greater.';
                else if (input.validity.stepMismatch) message = 'Enter a value with no more than two decimal places.';
                else message = input.validationMessage;
            }
            error.textContent = message;
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
            return !message;
        }
        inputs.forEach((input) => {
            input.addEventListener('blur', () => { input.dataset.touched = 'true'; validate(input); });
            input.addEventListener('input', () => { if (input.dataset.touched || input.getAttribute('aria-invalid') === 'true') validate(input); });
            input.addEventListener('change', () => validate(input));
        });
        form.addEventListener('submit', (event) => {
            const invalid = inputs.filter((input) => !validate(input));
            if (invalid.length) { event.preventDefault(); invalid[0].focus(); }
        });
    });
    // This is a display-only estimate, never a submitted total. Keep missing/invalid
    // inputs distinct from zero; the server derives saved material totals independently.
    page.querySelectorAll('[data-pm-cost]').forEach((form) => {
        const quantity = form.elements.quantity;
        const cost = form.elements.unit_cost;
        function updateCost() {
            const amount = quantity.valueAsNumber * cost.valueAsNumber;
            form.querySelector('[data-pm-cost-output]').textContent = quantity.value && cost.value && quantity.validity.valid && cost.validity.valid && Number.isFinite(amount)
                ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount) : 'Not recorded';
        }
        quantity.addEventListener('input', updateCost); cost.addEventListener('input', updateCost); updateCost();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') page.querySelectorAll('[data-pm-menu][open]').forEach((menu) => { menu.open = false; menu.querySelector('summary').focus(); });
    });
    document.addEventListener('click', (event) => page.querySelectorAll('[data-pm-menu][open]').forEach((menu) => { if (!menu.contains(event.target) && !archiveDialog?.open) menu.open = false; }));
    // Give an open summary priority; otherwise focus the failed field or error summary.
    if (!summary) (recovery?.querySelector('[aria-invalid="true"]') || page.querySelector('[data-pm-errors]'))?.focus();
});
