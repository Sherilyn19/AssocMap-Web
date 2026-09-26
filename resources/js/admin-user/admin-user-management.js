import { initConfirmModal, initRowDropdowns, initToast } from "./management-actions";
/**
 * resources/js/admin-user/admin-user-management.js
 * Add/Edit modal, confirm-action modal, SVG password eye toggle,
 * row action dropdown, and toast auto-dismiss. Feature-detects its
 * root elements, so a global import is a safe no-op on other pages.
 */
document.addEventListener("DOMContentLoaded", () => {
    initUserModal();
    initConfirmModal();
    initPasswordToggle();
    initRowDropdowns();
    initToast();
});

function initUserModal() {
    const modal = document.getElementById("admin-user-modal");
    if (!modal) return;

    const form         = document.getElementById("admin-user-form");
    const methodField   = document.getElementById("admin-user-form-method");
    const titleEl       = document.getElementById("admin-user-modal-title");
    const nameField     = document.getElementById("admin-user-name");
    const emailField    = document.getElementById("admin-user-email");
    const passwordField = document.getElementById("admin-user-password");
    const roleField     = document.getElementById("admin-user-role");
    const roleHint      = document.getElementById("admin-user-role-hint");
    const storeUrl      = form.getAttribute("action");
    const associationField = document.getElementById("admin-user-association");
    const associationSection = document.getElementById("admin-user-association-section");
    const submitButton = form.querySelector('[type="submit"]');
    const existingAccountNote = document.getElementById('admin-user-existing-account-note');
    let opener = null;
    let editingId = null;

    function syncAssociationOptions() {
        // Add may open an existing account. Edit cannot move an account onto another
        // account's association, so the database's uniqueness rule remains intact.
        let available = 0;
        associationField.querySelectorAll('option[data-account-id]').forEach(option => {
            const occupied = option.dataset.accountId && option.dataset.accountId !== String(editingId);
            const archived = option.dataset.archived === 'true';
            option.disabled = archived || (editingId !== null && Boolean(occupied));
            option.textContent = option.dataset.name + (archived ? ' (archived)' : occupied
                ? (editingId === null ? ' (edit existing account)' : ' (account already exists)') : '');
            if (!option.disabled) available++;
        });
        document.getElementById('admin-user-association-hint').textContent = available
            ? (editingId === null
                ? 'Select an association. If it already has an account, its existing details will open for editing.'
                : 'Keep this association or choose one without an account.')
            : 'No current associations are available. Register or restore an association first.';
    }

    function syncAssociation() {
        // Keep the association field relevant to the chosen role. The server repeats this check.
        const shared = roleField.value === associationField.dataset.memberRole;
        associationSection.classList.toggle("hidden", !shared);
        associationField.disabled = !shared;
        associationField.required = shared;
        if (!shared) associationField.value = "";
    }

    function prepareModal() {
        // Each opening starts with a masked, empty password and no errors from another attempt.
        opener = document.activeElement;
        passwordField.value = "";
        passwordField.type = "password";
        const eyeButton = form.querySelector('[data-toggle-password]');
        eyeButton.setAttribute("aria-label", "Show password");
        eyeButton.setAttribute("aria-pressed", "false");
        eyeButton.querySelector('[data-eye-icon="open"]').classList.remove("hidden");
        eyeButton.querySelector('[data-eye-icon="closed"]').classList.add("hidden");
        submitButton.disabled = false;
        form.querySelectorAll('[data-user-errors], [role="alert"]').forEach(el => el.classList.add("hidden"));
        syncAssociation();
        nameField.focus();
    }

    function openCreateModal() {
        existingAccountNote.classList.add('hidden');
        editingId = null;
        syncAssociationOptions();
        form.reset();
        // Form defaults may contain recovered input; a deliberate new attempt should start blank.
        nameField.value = "";
        emailField.value = "";
        roleField.value = "";
        associationField.value = "";
        form.setAttribute("action", storeUrl);
        methodField.value = "POST";
        titleEl.textContent = "Add User";
        passwordField.required = true;
        passwordField.placeholder = "Enter a password (at least 8 characters)";
        roleHint.classList.add("hidden");
        modal.classList.remove("hidden");
        modal.classList.add("flex");
        prepareModal();
    }

    function openEditModal(userJson) {
        const user = JSON.parse(userJson);
        existingAccountNote.classList.add('hidden');
        editingId = user.id;
        syncAssociationOptions();
        form.reset();
        form.setAttribute("action", storeUrl.replace(/\/users\/?$/, "/users/" + user.id));
        methodField.value = "PUT";
        titleEl.textContent = "Edit User";
        passwordField.required = false;
        nameField.value = user.name;
        emailField.value = user.email;
        roleField.value = user.role_id;
        associationField.value = user.association_id ?? "";
        passwordField.placeholder = "Leave blank to keep current password";
        roleHint.classList.toggle("hidden", !user.is_last_admin);
        modal.classList.remove("hidden");
        modal.classList.add("flex");
        prepareModal();
    }

    function closeModal() {
        modal.classList.add("hidden");
        modal.classList.remove("flex");
        passwordField.value = "";
        // Return keyboard users to the control that opened the form.
        opener?.focus();
    }

    document.querySelectorAll('[data-admin-user-modal-open="create"]').forEach((btn) => {
        btn.addEventListener("click", openCreateModal);
    });
    document.querySelectorAll('[data-admin-user-modal-open="edit"]').forEach((btn) => {
        btn.addEventListener("click", () => openEditModal(btn.dataset.user));
    });
    document.querySelectorAll("[data-admin-user-modal-close]").forEach((btn) => {
        btn.addEventListener("click", closeModal);
    });
    modal.addEventListener("click", (event) => {
        if (event.target === modal) closeModal();
    });
    roleField.addEventListener("change", syncAssociation);
    associationField.addEventListener('change', () => {
        const option = associationField.selectedOptions[0];
        if (editingId !== null || !option?.dataset.accountId) return;
        try {
            // Use the server's existing account ID and switch to PUT. Selecting an
            // association never submits a form or overwrites the account immediately.
            const account = JSON.parse(option.dataset.account);
            if (!account?.id || String(account.id) !== option.dataset.accountId) {
                throw new Error('Account details are unavailable.');
            }
            const associationName = option.dataset.name;
            const originalOpener = opener;
            openEditModal(JSON.stringify(account));
            // Closing this edit should return to Add User, not the now-hidden selector.
            opener = originalOpener;
            existingAccountNote.textContent = `Editing the existing shared account for ${associationName}. Leave the password blank to keep it.${account.is_active ? '' : ' This account is inactive; editing does not activate it.'}`;
            existingAccountNote.classList.remove('hidden');
        } catch (error) {
            // Never leave a create request pointing at an occupied association when
            // its edit data cannot be read. Keep the entered fields for recovery.
            associationField.value = '';
            existingAccountNote.textContent = 'The existing account could not be loaded. Refresh the page and try again.';
            existingAccountNote.classList.remove('hidden');
        }
    });
    form.addEventListener("submit", () => { submitButton.disabled = true; });
    // Back navigation can restore the form from browser cache with Save still disabled.
    window.addEventListener("pageshow", () => { submitButton.disabled = false; });
    modal.addEventListener("keydown", (event) => {
        if (event.key === "Escape") closeModal();
        if (event.key !== "Tab") return;
        // Cycle through visible controls so keyboard focus remains within the open dialog.
        const fields = [...modal.querySelectorAll('input, select, button, [tabindex="0"]')]
            .filter(el => !el.disabled && el.type !== "hidden" && el.getClientRects().length);
        const first = fields[0], last = fields.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    const recovery = JSON.parse(form.dataset.recovery || "null");
    if (recovery) {
        // Reopen the failed operation using server-provided safe input. Passwords are never restored.
        const data = recovery.input;
        if (recovery.form.mode === "edit") openEditModal(JSON.stringify({...data, id: recovery.form.id}));
        else openCreateModal();
        nameField.value = data.name ?? "";
        emailField.value = data.email ?? "";
        roleField.value = data.role_id ?? "";
        associationField.value = data.association_id ?? "";
        syncAssociation();
        form.querySelectorAll('[data-user-errors], [role="alert"]').forEach(el => el.classList.remove("hidden"));
    }
}

/** Swaps the open/closed eye SVGs instead of a broken emoji glyph. */
function initPasswordToggle() {
    document.querySelectorAll("[data-toggle-password]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const input = document.getElementById(btn.dataset.togglePassword);
            if (!input) return;
            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            btn.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
            btn.setAttribute("aria-pressed", String(isPassword));

            const openIcon = btn.querySelector('[data-eye-icon="open"]');
            const closedIcon = btn.querySelector('[data-eye-icon="closed"]');
            openIcon.classList.toggle("hidden", isPassword);
            closedIcon.classList.toggle("hidden", !isPassword);
        });
    });
}
