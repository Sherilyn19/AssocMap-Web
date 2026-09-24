/** Area interactions; shared confirmation/menu/toast behavior lives in management-actions.js. */
document.addEventListener("DOMContentLoaded", () => {
    const page = document.querySelector("[data-area-management-page]");
    if (!page) return;
    initDialogFocus(page);
    initValuePreviews(page);
    const recovery = parseJson(document.getElementById("am-form-recovery")?.dataset.recovery);
    const activate = initTabs(recovery);
    initForm("municipality", recovery, activate);
    initForm("barangay", recovery, activate);
    initCards();
    initDetails();
    initSubmissionFeedback(page);
});
function parseJson(value) {
    try { return JSON.parse(value || "null"); }
    catch (error) { console.error("Area form data could not be read.", error); return null; }
}
function initTabs(recovery) {
    const tabs = [...document.querySelectorAll("[data-am-tab]")];
    function activate(target) {
        if (!["municipalities", "barangays"].includes(target)) target = "municipalities";
        tabs.forEach(tab => {
            const selected = tab.dataset.amTab === target;
            tab.setAttribute("aria-selected", String(selected));
            tab.tabIndex = selected ? 0 : -1;
            tab.classList.toggle("bg-assocmap-primary", selected);
            tab.classList.toggle("text-white", selected);
            tab.classList.toggle("text-assocmap-text", !selected);
        });
        document.querySelectorAll("[data-am-tab-panel]").forEach(panel => {
            panel.classList.toggle("hidden", panel.dataset.amTabPanel !== target);
        });
        // Defense: URL state survives refresh, pagination and mutation redirects.
        const url = new URL(location.href);
        url.searchParams.set("tab", target);
        history.replaceState(null, "", url);
    }
    activate(recovery ? (recovery.entity === "barangay" ? "barangays" : "municipalities") : new URL(location.href).searchParams.get("tab"));
    tabs.forEach((tab, index) => {
        tab.addEventListener("click", () => activate(tab.dataset.amTab));
        tab.addEventListener("keydown", event => {
            if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === "Home" ? 0 : event.key === "End" ? tabs.length - 1
                : (index + (event.key === "ArrowRight" ? 1 : -1) + tabs.length) % tabs.length;
            activate(tabs[next].dataset.amTab);
            tabs[next].focus();
        });
    });
    return activate;
}
function initForm(entity, recovery, activate) {
    const modal = document.getElementById("am-" + entity + "-modal");
    const form = document.getElementById("am-" + entity + "-form");
    const storeUrl = form.action;
    const title = document.getElementById("am-" + entity + "-modal-title");
    const method = document.getElementById("am-" + entity + "-form-method");
    const label = entity === "municipality" ? "Municipality" : "Barangay";
    const parent = form.elements.namedItem("area_unit_id");
    function open(record = {}, recover = false) {
        form.reset();
        form.querySelectorAll("[data-area-errors]").forEach(error => { error.hidden = !recover; });
        parent?.querySelectorAll("[data-unavailable-parent]").forEach(option => option.remove());
        const id = Number(record.id);
        const editing = Number.isSafeInteger(id) && id > 0;
        form.action = editing ? storeUrl.replace(/\/$/, "") + "/" + id : storeUrl;
        method.value = editing ? "PUT" : "POST";
        title.textContent = (editing ? "Edit " : "Add ") + label;
        for (const name of ["name", "address", "area_unit_id"]) {
            const field = form.elements.namedItem(name);
            if (!field) continue;
            const value = record[name] ?? "";
            if (name === "area_unit_id" && value && ![...field.options].some(option => option.value === String(value))) {
                // Retain a stale parent visibly without making it eligible for assignment.
                const option = new Option("Previous municipality unavailable — select a current municipality", String(value));
                option.disabled = true;
                option.dataset.unavailableParent = "";
                field.add(option);
            }
            field.value = value;
            field.dispatchEvent(new Event("input"));
        }
        activate(entity === "barangay" ? "barangays" : "municipalities");
        modal.showModal(); // Native dialogs provide focus containment, Escape and focus return.
        form.elements.namedItem("name").focus();
    }
    document.querySelectorAll("[data-" + entity + "-modal-open]").forEach(button => {
        button.addEventListener("click", () => {
            if (button.disabled) return;
            const record = button.dataset[entity + "ModalOpen"] === "edit" ? parseJson(button.dataset[entity]) : {};
            if (record) open(record);
        });
    });
    modal.querySelectorAll("[data-" + entity + "-modal-close]").forEach(button => button.addEventListener("click", () => modal.close()));
    if (recovery?.entity === entity) open(recovery, true);
}
function initCards() {
    document.querySelectorAll("[data-area-card]").forEach(card => {
        const panel = document.getElementById(card.dataset.areaCard);
        const toggleButton = card.querySelector("[data-area-card-toggle]");
        function toggle() {
            const expanded = toggleButton.getAttribute("aria-expanded") !== "true";
            panel.classList.toggle("hidden", !expanded);
            card.classList.toggle("is-expanded", expanded);
            toggleButton.setAttribute("aria-expanded", String(expanded));
            toggleButton.querySelector("[data-area-card-toggle-label]").textContent = expanded ? "Hide summary" : "Show summary";
        }
        // Defense: a real button supplies Enter/Space behavior without nesting
        // View/Edit/Archive buttons inside an element that also claims to be a button.
        toggleButton.addEventListener("click", toggle);
        card.addEventListener("click", event => {
            if (!event.target.closest("button, a, form, input, select, textarea")) toggle();
        });
    });
}
function initDetails() {
    const modal = document.getElementById("am-view-modal");
    const title = document.getElementById("am-view-title");
    const subtitle = document.getElementById("am-view-subtitle");
    const body = document.getElementById("am-view-body");
    let activeRequest;
    async function load(url) {
        activeRequest?.abort();
        const controller = new AbortController();
        activeRequest = controller;
        const timeout = setTimeout(() => controller.abort(), 15000);
        title.textContent = "Area Details";
        subtitle.textContent = "";
        body.textContent = "Loading details…";
        body.setAttribute("aria-busy", "true");
        if (!modal.open) {
            modal.showModal();
            title.focus(); // Begin at the heading, not a Close button below long content.
        }
        try {
            const response = await fetch(url, { headers: { Accept: "application/json" }, signal: controller.signal });
            if (!response.ok || response.redirected) throw new Error(response.status === 404 ? "This record no longer exists. Refresh the list."
                : [401, 403, 419].includes(response.status) || response.redirected ? "Your session may have expired. Refresh the page."
                : "Details could not be loaded. Please try again.");
            const record = await response.json();
            if (activeRequest !== controller || !modal.open) return;
            title.textContent = record.name;
            subtitle.textContent = (record.type === "municipality" ? "Municipality" : "Barangay") + " · " + record.status;
            const details = {
                Province: record.province,
                ...(record.type === "municipality" ? { "Address / Description": record.address, "Current Barangays": record.barangay_count, "Total Barangays": record.total_barangay_count }
                    : { Municipality: record.municipality, "Municipality Archive State": record.municipality_status }),
                "Current Associations": record.association_count, Created: record.created_at, Updated: record.updated_at,
            };
            // Defense: escape database text before inserting HTML (stored-XSS protection).
            body.innerHTML = '<dl class="grid gap-3 sm:grid-cols-2">' + Object.entries(details).map(([label, value]) =>
                '<div class="rounded-lg border border-assocmap-border p-3"><dt class="text-xs text-assocmap-secondary">' + escapeHtml(label) +
                '</dt><dd class="mt-1 break-words text-sm font-semibold">' + escapeHtml(value ?? "Not recorded") + '</dd></div>').join("") + "</dl>";
            if (record.type === "municipality") {
                const barangays = record.barangays || [];
                const heading = document.createElement("h4");
                heading.className = "mt-5 font-semibold";
                heading.textContent = record.barangays_truncated ? "First " + barangays.length + " of " + record.total_barangay_count + " barangays" : "Barangays";
                body.append(heading);
                const list = document.createElement("ul");
                list.className = "mt-2 space-y-1 text-sm";
                barangays.forEach(child => {
                    const item = document.createElement("li");
                    item.textContent = child.name + " · " + child.status;
                    list.append(item);
                });
                if (!barangays.length) list.textContent = "No barangays recorded.";
                body.append(list);
                const link = document.createElement("a");
                link.href = record.barangays_url;
                link.textContent = "Open all barangay records";
                link.className = "mt-3 inline-block text-assocmap-primary underline";
                body.append(link);
            }
        } catch (error) {
            if (activeRequest !== controller || !modal.open) return;
            body.textContent = controller.signal.aborted ? "Loading took too long. Please try again." :
                (error instanceof SyntaxError ? "Your session may have expired. Refresh the page." : error.message);
            const retry = document.createElement("button");
            retry.type = "button"; retry.textContent = "Retry"; retry.className = "ml-3 rounded border px-3 py-2";
            retry.addEventListener("click", () => load(url));
            body.append(retry);
        } finally {
            clearTimeout(timeout);
            if (activeRequest === controller) body.removeAttribute("aria-busy");
        }
    }
    document.querySelectorAll("[data-area-view-url], [data-brgy-view-url]").forEach(button => {
        button.addEventListener("click", () => load(button.dataset.areaViewUrl || button.dataset.brgyViewUrl));
    });
    modal.querySelector("[data-view-modal-close]").addEventListener("click", () => modal.close());
    modal.addEventListener("close", () => { activeRequest?.abort(); activeRequest = null; });
}
function initSubmissionFeedback(page) {
    page.addEventListener("submit", event => {
        const form = event.target;
        if (form.method.toLowerCase() === "get") return;
        if (form.dataset.pending === "true") { event.preventDefault(); return; }
        form.dataset.pending = "true";
        form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = true; });
        // Never automatically retry a write: a lost response may follow a successful commit.
        setTimeout(() => {
            const warning = document.createElement("p");
            warning.setAttribute("role", "alert");
            warning.className = "mt-3 text-sm text-red-700";
            warning.textContent = "The request is taking longer than expected. Check the record in a new tab before retrying.";
            (form.closest("dialog") || form.parentElement).append(warning);
        }, 30000);
    });
    window.addEventListener("pageshow", () => {
        page.querySelectorAll("form[data-pending]").forEach(form => {
            delete form.dataset.pending;
            form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = false; });
        });
    });
}
function escapeHtml(value) {
    return String(value).replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;").replaceAll("'", "&#039;");
}

function initDialogFocus(page) {
    const returnTargets = new WeakMap();
    // Capture before the trigger opens its dialog or the shared menu hides it.
    page.addEventListener("click", event => {
        const trigger = event.target.closest("[data-municipality-modal-open], [data-barangay-modal-open], [data-area-view-url], [data-brgy-view-url], [data-confirm-open]");
        if (!trigger || trigger.disabled) return;
        const id = trigger.hasAttribute("data-confirm-open") ? "am-confirm-modal"
            : trigger.hasAttribute("data-municipality-modal-open") ? "am-municipality-modal"
            : trigger.hasAttribute("data-barangay-modal-open") ? "am-barangay-modal" : "am-view-modal";
        const modal = document.getElementById(id);
        // Defense: a row action becomes hidden when its menu closes. Return to
        // the visible row-menu toggle instead so keyboard users keep their place.
        returnTargets.set(modal, trigger.closest("[data-am-dropdown]")?.querySelector("[data-am-dropdown-toggle]") || trigger);
    }, true);
    page.querySelectorAll("dialog").forEach(modal => {
        modal.addEventListener("close", () => {
            if (document.querySelector("dialog[open]")) return; // Do not steal focus from the saving overlay.
            const target = returnTargets.get(modal);
            const fallback = page.querySelector('[data-am-tab][aria-selected="true"]');
            (target?.isConnected && !target.disabled && target.getClientRects().length ? target : fallback)?.focus();
        });
    });
}

function initValuePreviews(page) {
    page.querySelectorAll("[data-area-value-preview]").forEach(preview => {
        const field = document.getElementById(preview.dataset.areaValuePreview);
        const update = () => {
            const value = field instanceof HTMLSelectElement ? (field.selectedOptions[0]?.textContent || "") : field.value;
            // Native inputs/selects cannot wrap. Mirror long values as escaped
            // text so the full name stays readable while editing on a small screen.
            preview.textContent = value;
            preview.hidden = !field.value || value.length < 40;
        };
        field.addEventListener("input", update);
        field.addEventListener("change", update);
        update();
    });
}
