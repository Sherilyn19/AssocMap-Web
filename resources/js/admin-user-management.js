/**
 * resources/js/admin-user-management.js
 * Add/Edit modal, confirm-action modal, password eye toggle, and
 * toast auto-dismiss for the User Management page. Feature-detects
 * its root elements, so importing it globally is a safe no-op on
 * every other page.
 */

document.addEventListener("DOMContentLoaded", () => {
    initUserModal();
    initConfirmModal();
    initPasswordToggle();
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

function initConfirmModal() {
    const modal = document.getElementById("am-confirm-modal");
    if (!modal) return;

    const titleEl   = document.getElementById("am-confirm-title");
    const messageEl = document.getElementById("am-confirm-message");
    const actionBtn = document.getElementById("am-confirm-action-btn");
    let targetFormId = null;

    document.querySelectorAll("[data-confirm-open]").forEach((btn) => {
        btn.addEventListener("click", () => {
            titleEl.textContent = btn.dataset.confirmTitle || "Are you sure?";
            messageEl.textContent = btn.dataset.confirmMessage || "";
            actionBtn.textContent = btn.dataset.confirmLabel || "Confirm";
            targetFormId = btn.dataset.confirmTarget;
            modal.classList.remove("hidden");
            modal.classList.add("flex");
        });
    });

    actionBtn.addEventListener("click", () => {
        if (targetFormId) {
            const form = document.getElementById(targetFormId);
            if (form) form.submit();
        }
    });

    document.querySelectorAll("[data-confirm-close]").forEach((btn) => {
        btn.addEventListener("click", () => {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
        });
    });
    modal.addEventListener("click", (event) => {
        if (event.target === modal) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
        }
    });
}

function initPasswordToggle() {
    document.querySelectorAll("[data-toggle-password]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const input = document.getElementById(btn.dataset.togglePassword);
            if (!input) return;
            input.type = input.type === "password" ? "text" : "password";
        });
    });
}

function initToast() {
    const toast = document.getElementById("am-toast");
    if (!toast) return;
    setTimeout(() => {
        toast.style.transition = "opacity 0.4s ease";
        toast.style.opacity = "0";
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}