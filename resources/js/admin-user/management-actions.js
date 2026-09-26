/** Shared management confirmation, dropdown and toast contracts. */
export function initConfirmModal() {
    const modal = document.getElementById("am-confirm-modal");
    if (!modal) return;

    const titleEl   = document.getElementById("am-confirm-title");
    const messageEl = document.getElementById("am-confirm-message");
    const actionBtn = document.getElementById("am-confirm-action-btn");
    let targetFormId = null;
    const isDialog = modal instanceof HTMLDialogElement;
    const close = () => {
        if (isDialog) modal.close();
        else { modal.classList.add("hidden"); modal.classList.remove("flex"); }
    };

    document.querySelectorAll("[data-confirm-open]").forEach((btn) => {
        btn.addEventListener("click", () => {
            titleEl.textContent = btn.dataset.confirmTitle || "Are you sure?";
            messageEl.textContent = btn.dataset.confirmMessage || "";
            actionBtn.textContent = btn.dataset.confirmLabel || "Confirm";
// Set the confirmation button color for Archive or Restore.
            // The form and server still check whether the action is allowed.
            if (modal.hasAttribute("data-area-confirm-dialog")) {
                actionBtn.dataset.tone = btn.dataset.confirmTone === "restore" ? "restore" : "archive";
            }
            // Activation and deactivation use distinct colors with the same button sizing.
            if (modal.hasAttribute("data-user-confirm-dialog")) {
                actionBtn.dataset.tone = btn.dataset.confirmTone === "activate" ? "activate" : "deactivate";
            }
            targetFormId = btn.dataset.confirmTarget;
            actionBtn.disabled = false;
            if (isDialog) modal.showModal();
            else { modal.classList.remove("hidden"); modal.classList.add("flex"); }
        });
    });

    actionBtn.addEventListener("click", () => {
        if (targetFormId) {
            const form = document.getElementById(targetFormId);
            if (form && form.dataset.pending !== "true") {
                actionBtn.disabled = true;
                // requestSubmit dispatches shared loading/duplicate-submit handlers.
                form.requestSubmit();
                close();
            }
        }
    });

    document.querySelectorAll("[data-confirm-close]").forEach((btn) => {
        btn.addEventListener("click", () => {
            close();
        });
    });
    modal.addEventListener("click", (event) => {
        if (event.target === modal) {
            close();
        }
    });
}

/** Per-row "..." action menu. Closes on outside click or Escape. */
export function initRowDropdowns() {
    const dropdowns = document.querySelectorAll("[data-am-dropdown]");
    if (!dropdowns.length) return;

    function closeAll(except) {
        dropdowns.forEach((d) => {
            if (d !== except) {
                const menu = d.querySelector("[data-am-dropdown-menu]");
                const toggle = d.querySelector("[data-am-dropdown-toggle]");
                const returnFocus = d.closest("[data-area-management-page], [data-user-table-scroll]") && menu.contains(document.activeElement)
                    && !document.querySelector("dialog[open]");
                menu.classList.add("hidden");
                toggle?.setAttribute("aria-expanded", "false");
                if (returnFocus) toggle?.focus();
            }
        });
    }

    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector("[data-am-dropdown-toggle]");
        const menu = dropdown.querySelector("[data-am-dropdown-menu]");
        if (dropdown.closest("[data-area-management-page], [data-user-table-scroll]")) {
            // Skip disabled actions when using arrow keys.
            // Escape returns focus to More. Tab follows the normal button order.
            const actions = () => [...menu.querySelectorAll("button:not(:disabled), a[href]")];
            toggle.addEventListener("keydown", event => {
                if (!["ArrowDown", "ArrowUp"].includes(event.key)) return;
                event.preventDefault();
                if (menu.classList.contains("hidden")) toggle.click();
                const options = actions();
                (event.key === "ArrowDown" ? options[0] : options.at(-1))?.focus();
            });
            menu.addEventListener("keydown", event => {
                if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) return;
                event.preventDefault();
                const options = actions();
                const current = options.indexOf(document.activeElement);
                const next = event.key === "Home" ? 0 : event.key === "End" ? options.length - 1
                    : (current + (event.key === "ArrowDown" ? 1 : -1) + options.length) % options.length;
                options[next]?.focus();
            });
            dropdown.addEventListener("focusout", event => {
                if (!dropdown.contains(event.relatedTarget)) {
                    menu.classList.add("hidden");
                    toggle.setAttribute("aria-expanded", "false");
                }
            });
        }

        toggle.addEventListener("click", (event) => {
            event.stopPropagation();
            const isHidden = menu.classList.contains("hidden");
            closeAll(dropdown);
            menu.classList.toggle("hidden", !isHidden);
            toggle.setAttribute("aria-expanded", String(isHidden));
            if (isHidden && dropdown.closest("[data-area-management-page], [data-user-table-scroll]")) {
                // Fixed placement avoids clipping inside the responsive table scroller.
                const bounds = toggle.getBoundingClientRect();
                menu.style.position = "fixed";
                menu.style.left = Math.max(8, Math.min(bounds.right - menu.offsetWidth, innerWidth - menu.offsetWidth - 8)) + "px";
                menu.style.top = Math.max(8, Math.min(bounds.bottom + 4, innerHeight - menu.offsetHeight - 8)) + "px";
                menu.style.right = "auto";
                menu.style.zIndex = "40";
            }
        });
    });

    document.addEventListener("click", () => closeAll(null));
    document.addEventListener("scroll", event => {
        if (event.target instanceof Element && event.target.closest("[data-am-dropdown-menu]")) return;
        closeAll(null);
    }, true);
    window.addEventListener("resize", () => closeAll(null));
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") closeAll(null);
    });
}

export function initToast() {
    const toast = document.getElementById("am-toast");
    if (!toast) return;
    setTimeout(() => {
        toast.style.transition = "opacity 0.4s ease";
        toast.style.opacity = "0";
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}
