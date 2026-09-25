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
    let opener = null;

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
        form.reset();
        // Form defaults may contain recovered input; a deliberate new attempt should start blank.
        nameField.value = "";
        emailField.value = "";
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
    form.addEventListener("submit", () => { submitButton.disabled = true; });
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
