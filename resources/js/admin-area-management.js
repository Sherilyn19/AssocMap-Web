/**
 * resources/js/admin-area-management.js
 * Area Management Module (Municipalities + Barangays).
 *
 * This file intentionally stays page-scoped. Generic dropdowns,
 * confirmation modals, and toast notifications are handled by
 * admin-user-management.js through shared data attributes.
 */
document.addEventListener("DOMContentLoaded", () => {
    initTabs();
    initMunicipalityModal();
    initBarangayModal();
    initAreaCardDetails();
    initAreaViewModal();
});

const AM_TAB_ACTIVE_CLASSES = ["bg-assocmap-primary", "text-white"];
const AM_TAB_INACTIVE_CLASSES = ["text-assocmap-text", "hover:bg-assocmap-bg"];

function safeJsonParse(jsonValue, context) {
    try {
        return JSON.parse(jsonValue || "{}");
    } catch (error) {
        console.error(`AssocMap Area Management: invalid JSON for ${context}.`, error);
        return null;
    }
}

function initTabs() {
    const tabs = document.querySelectorAll("[data-am-tab]");
    const panels = document.querySelectorAll("[data-am-tab-panel]");
    if (!tabs.length || !panels.length) return;

    function activateTab(target) {
        tabs.forEach((tab) => {
            const isActive = tab.dataset.amTab === target;
            tab.classList.remove(...AM_TAB_ACTIVE_CLASSES, ...AM_TAB_INACTIVE_CLASSES);
            tab.classList.add(...(isActive ? AM_TAB_ACTIVE_CLASSES : AM_TAB_INACTIVE_CLASSES));
            tab.setAttribute("aria-selected", isActive ? "true" : "false");
        });

        panels.forEach((panel) => {
            panel.classList.toggle("hidden", panel.dataset.amTabPanel !== target);
        });

        document.querySelectorAll("[data-am-tab-input]").forEach((input) => {
            input.value = target;
        });
    }

    const requestedTab = new URLSearchParams(window.location.search).get("tab") || "municipalities";
    const hasRequestedTab = Array.from(tabs).some((tab) => tab.dataset.amTab === requestedTab);
    activateTab(hasRequestedTab ? requestedTab : "municipalities");

    tabs.forEach((tab) => {
        tab.addEventListener("click", () => activateTab(tab.dataset.amTab));
    });
}

function initMunicipalityModal() {
    const modal = document.getElementById("am-municipality-modal");
    if (!modal) return;

    const form = document.getElementById("am-municipality-form");
    const methodField = document.getElementById("am-municipality-form-method");
    const titleEl = document.getElementById("am-municipality-modal-title");
    const nameField = document.getElementById("am-municipality-name");
    const addressField = document.getElementById("am-municipality-address");
    const storeUrl = form?.getAttribute("action") || "";

    function openCreate() {
        if (!form || !methodField || !titleEl) return;
        form.reset();
        form.setAttribute("action", storeUrl);
        methodField.value = "POST";
        titleEl.textContent = "Add Municipality";
        show(modal);
    }

    function openEdit(dataJson) {
        const record = safeJsonParse(dataJson, "municipality edit");
        if (!record || !form || !methodField || !titleEl || !nameField || !addressField) return;

        form.reset();
        form.setAttribute("action", `${storeUrl.replace(/\/$/, "")}/${record.id}`);
        methodField.value = "PUT";
        titleEl.textContent = "Edit Municipality";
        nameField.value = record.name || "";
        addressField.value = record.address || "";
        show(modal);
    }

    document.querySelectorAll('[data-municipality-modal-open="create"]').forEach((btn) => {
        btn.addEventListener("click", openCreate);
    });

    document.querySelectorAll('[data-municipality-modal-open="edit"]').forEach((btn) => {
        btn.addEventListener("click", (event) => {
            event.stopPropagation();
            openEdit(btn.dataset.municipality);
        });
    });

    document.querySelectorAll("[data-municipality-modal-close]").forEach((btn) => {
        btn.addEventListener("click", () => hide(modal));
    });

    modal.addEventListener("click", (event) => {
        if (event.target === modal) hide(modal);
    });
}

function initBarangayModal() {
    const modal = document.getElementById("am-barangay-modal");
    if (!modal) return;

    const form = document.getElementById("am-barangay-form");
    const methodField = document.getElementById("am-barangay-form-method");
    const titleEl = document.getElementById("am-barangay-modal-title");
    const nameField = document.getElementById("am-barangay-name");
    const areaUnitField = document.getElementById("am-barangay-area-unit");
    const storeUrl = form?.getAttribute("action") || "";

    function openCreate() {
        if (!form || !methodField || !titleEl) return;
        form.reset();
        form.setAttribute("action", storeUrl);
        methodField.value = "POST";
        titleEl.textContent = "Add Barangay";
        show(modal);
    }

    function openEdit(dataJson) {
        const record = safeJsonParse(dataJson, "barangay edit");
        if (!record || !form || !methodField || !titleEl || !nameField || !areaUnitField) return;

        form.reset();
        form.setAttribute("action", `${storeUrl.replace(/\/$/, "")}/${record.id}`);
        methodField.value = "PUT";
        titleEl.textContent = "Edit Barangay";
        nameField.value = record.name || "";
        areaUnitField.value = record.area_unit_id || "";
        show(modal);
    }

    document.querySelectorAll('[data-barangay-modal-open="create"]').forEach((btn) => {
        btn.addEventListener("click", openCreate);
    });

    document.querySelectorAll('[data-barangay-modal-open="edit"]').forEach((btn) => {
        btn.addEventListener("click", (event) => {
            event.stopPropagation();
            openEdit(btn.dataset.barangay);
        });
    });

    document.querySelectorAll("[data-barangay-modal-close]").forEach((btn) => {
        btn.addEventListener("click", () => hide(modal));
    });

    modal.addEventListener("click", (event) => {
        if (event.target === modal) hide(modal);
    });
}

function initAreaCardDetails() {
    document.querySelectorAll("[data-area-card-toggle]").forEach((card) => {
        card.addEventListener("click", (event) => {
            if (event.target.closest("button, a, form, input, select, textarea, [data-card-action]")) {
                return;
            }

            const key = card.dataset.areaCardToggle;
            const detailPanel = document.querySelector(`[data-area-card-details="${key}"]`);
            if (!detailPanel) return;

            const willOpen = detailPanel.classList.contains("hidden");
            detailPanel.classList.toggle("hidden", !willOpen);
            card.setAttribute("aria-expanded", willOpen ? "true" : "false");
        });
    });

    document.querySelectorAll("[data-area-details-close]").forEach((button) => {
        button.addEventListener("click", (event) => {
            event.stopPropagation();
            const key = button.dataset.areaDetailsClose;
            const detailPanel = document.querySelector(`[data-area-card-details="${key}"]`);
            const card = document.querySelector(`[data-area-card-toggle="${key}"]`);
            detailPanel?.classList.add("hidden");
            card?.setAttribute("aria-expanded", "false");
        });
    });
}

function initAreaViewModal() {
    const modal = document.getElementById("am-area-view-modal");
    if (!modal) return;

    const titleEl = document.getElementById("am-area-view-title");
    const subtitleEl = document.getElementById("am-area-view-subtitle");
    const bodyEl = document.getElementById("am-area-view-body");

    document.querySelectorAll("[data-area-view]").forEach((button) => {
        button.addEventListener("click", (event) => {
            event.stopPropagation();
            const record = safeJsonParse(button.dataset.areaView, "area view");
            if (!record || !titleEl || !subtitleEl || !bodyEl) return;

            titleEl.textContent = record.title || record.name || "Area Details";
            subtitleEl.textContent = record.subtitle || "Read-only administrative information";
            bodyEl.innerHTML = buildDetailsHtml(record.details || {});
            show(modal);
        });
    });

    document.querySelectorAll("[data-area-view-close]").forEach((button) => {
        button.addEventListener("click", () => hide(modal));
    });

    modal.addEventListener("click", (event) => {
        if (event.target === modal) hide(modal);
    });
}

function buildDetailsHtml(details) {
    const entries = Object.entries(details);
    if (!entries.length) {
        return '<p class="text-sm text-assocmap-secondary">No additional details available.</p>';
    }

    return entries.map(([label, value]) => {
        const safeLabel = escapeHtml(label);
        const safeValue = escapeHtml(value ?? "-");
        return `
            <div class="rounded-lg border border-assocmap-border bg-assocmap-bg px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-assocmap-secondary">${safeLabel}</p>
                <p class="mt-1 text-sm font-semibold text-assocmap-text">${safeValue}</p>
            </div>
        `;
    }).join("");
}

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function show(modal) {
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function hide(modal) {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}