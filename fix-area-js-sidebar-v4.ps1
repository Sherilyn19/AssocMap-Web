# ============================================================
# fix-area-js-sidebar-v4.ps1
# AssocMap Web - emergency fix for corrupted Area JS and sidebar toggle
# Writes UTF-8 without BOM and creates backups before every change.
# ============================================================

$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host "[AssocMap] $Message" -ForegroundColor Cyan
}

function Write-Ok {
    param([string]$Message)
    Write-Host "[OK] $Message" -ForegroundColor Green
}

function Write-Warn {
    param([string]$Message)
    Write-Host "[WARN] $Message" -ForegroundColor Yellow
}

function Get-ProjectRoot {
    if (Test-Path (Join-Path (Get-Location) 'artisan')) {
        return (Get-Location).Path
    }

    $defaultRoot = 'D:\Capstone-AssocMap-Web'
    if (Test-Path (Join-Path $defaultRoot 'artisan')) {
        return $defaultRoot
    }

    throw 'Laravel project root not found. Run this script inside D:\Capstone-AssocMap-Web.'
}

function Backup-File {
    param(
        [string]$ProjectRoot,
        [string]$RelativePath,
        [string]$BackupRoot
    )

    $source = Join-Path $ProjectRoot $RelativePath
    if (-not (Test-Path $source)) {
        Write-Warn "Skipping backup because file does not exist: $RelativePath"
        return
    }

    $destination = Join-Path $BackupRoot $RelativePath
    $destinationDirectory = Split-Path $destination -Parent
    if (-not (Test-Path $destinationDirectory)) {
        New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null
    }

    Copy-Item $source $destination -Force
    Write-Ok "Backed up $RelativePath"
}

function Write-Utf8NoBom {
    param(
        [string]$Path,
        [string]$Content
    )

    $directory = Split-Path $Path -Parent
    if (-not (Test-Path $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $encoding)
}

function Run-Command {
    param(
        [string]$Command,
        [string]$WorkingDirectory
    )

    Write-Step "Running: $Command"
    Push-Location $WorkingDirectory
    try {
        cmd /c $Command
        if ($global:LASTEXITCODE -ne 0) {
            throw ("Command failed with exit code {0}: {1}" -f $global:LASTEXITCODE, $Command)
        }
    }
    finally {
        Pop-Location
    }
}

$ProjectRoot = Get-ProjectRoot
$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupRoot = Join-Path $ProjectRoot "storage\app\assocmap-patches\area-js-sidebar-v4\$Timestamp"

Write-Step "Project root: $ProjectRoot"
Write-Step "Backup folder: $BackupRoot"
New-Item -ItemType Directory -Path $BackupRoot -Force | Out-Null

$filesToBackup = @(
    'resources\js\admin-area-management.js',
    'resources\js\admin_sidebar.js',
    'resources\js\app.js',
    'resources\css\app.css'
)

foreach ($file in $filesToBackup) {
    Backup-File -ProjectRoot $ProjectRoot -RelativePath $file -BackupRoot $BackupRoot
}

# ------------------------------------------------------------
# 1) Hard-replace corrupted Area Management JS.
# This file was previously mojibake-corrupted, which stops the
# entire Vite module graph and prevents sidebar JS from running.
# ------------------------------------------------------------
$AreaJs = @'
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
'@

Write-Utf8NoBom -Path (Join-Path $ProjectRoot 'resources\js\admin-area-management.js') -Content $AreaJs
Write-Ok 'Replaced corrupted resources/js/admin-area-management.js'

# ------------------------------------------------------------
# 2) Replace sidebar JS with a defensive, reliable implementation.
# ------------------------------------------------------------
$SidebarJs = @'
/**
 * resources/js/admin_sidebar.js
 * Sidebar collapse for desktop and off-canvas drawer for mobile.
 */
const ASSOCMAP_MOBILE_BREAKPOINT = 1024;

document.addEventListener("DOMContentLoaded", initAssocMapSidebar);

function initAssocMapSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const menuBtn = document.getElementById("sidebarMenuBtn");
    const collapseBtn = document.getElementById("sidebarCollapseBtn");

    if (!sidebar) return;

    const isMobile = () => window.innerWidth < ASSOCMAP_MOBILE_BREAKPOINT;

    function setCollapsed(collapsed) {
        sidebar.classList.toggle("is-collapsed", collapsed);
        document.body.classList.toggle("am-sidebar-collapsed", collapsed);
        localStorage.setItem("assocmap.sidebarCollapsed", collapsed ? "1" : "0");

        if (collapseBtn) {
            collapseBtn.setAttribute("aria-expanded", collapsed ? "false" : "true");
            collapseBtn.setAttribute("aria-label", collapsed ? "Expand sidebar" : "Collapse sidebar");
        }
    }

    function applyStoredCollapseState() {
        if (isMobile()) {
            setCollapsed(false);
            return;
        }

        setCollapsed(localStorage.getItem("assocmap.sidebarCollapsed") === "1");
    }

    function toggleCollapse(event) {
        event?.preventDefault();
        event?.stopPropagation();

        if (isMobile()) {
            toggleDrawer();
            return;
        }

        setCollapsed(!sidebar.classList.contains("is-collapsed"));
    }

    function openDrawer() {
        sidebar.classList.add("is-open");
        overlay?.classList.add("is-visible");
        menuBtn?.setAttribute("aria-expanded", "true");
        document.body.style.overflow = "hidden";
    }

    function closeDrawer() {
        sidebar.classList.remove("is-open");
        overlay?.classList.remove("is-visible");
        menuBtn?.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
    }

    function toggleDrawer() {
        sidebar.classList.contains("is-open") ? closeDrawer() : openDrawer();
    }

    menuBtn?.addEventListener("click", (event) => {
        event.preventDefault();
        isMobile() ? toggleDrawer() : toggleCollapse(event);
    });

    collapseBtn?.addEventListener("click", toggleCollapse);
    overlay?.addEventListener("click", closeDrawer);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") closeDrawer();
    });

    window.addEventListener("resize", () => {
        closeDrawer();
        applyStoredCollapseState();
    });

    applyStoredCollapseState();
}
'@

Write-Utf8NoBom -Path (Join-Path $ProjectRoot 'resources\js\admin_sidebar.js') -Content $SidebarJs
Write-Ok 'Replaced resources/js/admin_sidebar.js'

# ------------------------------------------------------------
# 3) Normalize the Vite entrypoint.
# ------------------------------------------------------------
$AppJs = @'
/**
 * resources/js/app.js
 * Main Vite JavaScript entry point for AssocMap Web.
 */
import './bootstrap';
import './admin_sidebar';
import './admin-user-management';
import './admin-area-management';
'@

Write-Utf8NoBom -Path (Join-Path $ProjectRoot 'resources\js\app.js') -Content $AppJs
Write-Ok 'Normalized resources/js/app.js imports'

# ------------------------------------------------------------
# 4) Append/refresh sidebar CSS hotfix overrides.
# ------------------------------------------------------------
$CssPath = Join-Path $ProjectRoot 'resources\css\app.css'
if (-not (Test-Path $CssPath)) {
    throw 'resources/css/app.css was not found.'
}

$CssContent = [System.IO.File]::ReadAllText($CssPath)
$CssContent = [System.Text.RegularExpressions.Regex]::Replace(
    $CssContent,
    '(?s)\/\* ============================================================\s+SIDEBAR-HOTFIX-V4-START.*?SIDEBAR-HOTFIX-V4-END\s+============================================================ \*\/',
    ''
)

$SidebarCssFix = @'

/* ============================================================
   SIDEBAR-HOTFIX-V4-START
   Defensive overrides for reliable desktop collapse and mobile drawer.
   Safe to keep at the end of app.css because it only targets AssocMap
   sidebar state classes.
   SIDEBAR-HOTFIX-V4-END
   ============================================================ */
@layer components {
    .am-sidebar__collapse-btn {
        cursor: pointer;
        pointer-events: auto;
    }

    .am-sidebar.is-collapsed .am-sidebar__brand {
        pointer-events: auto;
    }

    .am-sidebar.is-collapsed .am-sidebar__collapse-btn {
        display: flex;
        width: 2rem;
        height: 2rem;
        margin-left: 0;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background-color: rgba(255, 255, 255, 0.08);
    }

    .am-sidebar.is-collapsed .am-sidebar__collapse-btn:hover {
        background-color: rgba(255, 255, 255, 0.16);
    }

    body.am-sidebar-collapsed .am-main {
        margin-left: 76px;
    }

    @media (min-width: 1024px) {
        #sidebar.is-collapsed ~ .am-main {
            margin-left: 76px;
        }
    }

    @media (max-width: 1023px) {
        body.am-sidebar-collapsed .am-main,
        #sidebar.is-collapsed ~ .am-main {
            margin-left: 0;
        }

        .am-sidebar.is-collapsed {
            width: 256px;
        }
    }
}
'@

Write-Utf8NoBom -Path $CssPath -Content ($CssContent.TrimEnd() + $SidebarCssFix + [Environment]::NewLine)
Write-Ok 'Refreshed sidebar CSS hotfix block in resources/css/app.css'

# ------------------------------------------------------------
# 5) Clear Laravel view/cache and verify Vite production build.
# ------------------------------------------------------------
Run-Command -Command 'php artisan optimize:clear' -WorkingDirectory $ProjectRoot
Run-Command -Command 'npm run build' -WorkingDirectory $ProjectRoot

Write-Host ''
Write-Host 'Patch completed successfully.' -ForegroundColor Green
Write-Host 'Restart npm run dev / php artisan serve if they are currently running, then hard-refresh the browser with Ctrl+F5.' -ForegroundColor Yellow
