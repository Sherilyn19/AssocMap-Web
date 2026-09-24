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

    function openCreateModal() {
        form.reset();
        form.setAttribute("action", storeUrl);
        methodField.value = "POST";
        titleEl.textContent = "Add User";
        passwordField.required = true;
        roleHint.classList.add("hidden");
        modal.classList.remove("hidden");
        modal.classList.add("flex");
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
        roleHint.classList.toggle("hidden", !user.is_last_admin);
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    }

    function closeModal() {
        modal.classList.add("hidden");
        modal.classList.remove("flex");
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
}

/** Swaps the open/closed eye SVGs instead of a broken emoji glyph. */
function initPasswordToggle() {
    document.querySelectorAll("[data-toggle-password]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const input = document.getElementById(btn.dataset.togglePassword);
            if (!input) return;
            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";

            const openIcon = btn.querySelector('[data-eye-icon="open"]');
            const closedIcon = btn.querySelector('[data-eye-icon="closed"]');
            openIcon.classList.toggle("hidden", isPassword);
            closedIcon.classList.toggle("hidden", !isPassword);
        });
    });
}
