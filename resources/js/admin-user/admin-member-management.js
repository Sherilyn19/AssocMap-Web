/**
 * resources/js/admin-user/admin-member-management.js
 *
 * Interface behavior only. Laravel remains the source of truth for
 * authorization, validation, duplicate prevention, and archive rules.
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-member-management-page]');

    if (!page) {
        return;
    }

    const barangays = safelyParseJson(page.dataset.barangays, []);
    let activeModal = null;
    let activeTrigger = null;

    function safelyParseJson(value, fallback) {
        try {
            return JSON.parse(value ?? '');
        } catch {
            return fallback;
        }
    }

    function focusableElements(modal) {
        return Array.from(
            modal.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
                'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )
        ).filter((element) => !element.hasAttribute('hidden') && element.offsetParent !== null);
    }

    function openModal(modal, trigger = null) {
        if (!modal) return;

        if (activeModal && activeModal !== modal) {
            closeModal(activeModal, false);
        }

        activeModal = modal;
        activeTrigger = trigger ?? document.activeElement;

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        window.setTimeout(() => {
            const focusable = focusableElements(modal);
            (focusable[0] ?? modal.querySelector('[data-modal-panel]'))?.focus();
        }, 0);
    }

    function closeModal(modal, restoreFocus = true) {
        if (!modal) return;

        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');

        if (restoreFocus && activeTrigger instanceof HTMLElement) {
            activeTrigger.focus();
        }

        if (activeModal === modal) {
            activeModal = null;
            activeTrigger = null;
        }
    }

    function trapFocus(event, modal) {
        if (event.key !== 'Tab') return;

        const focusable = focusableElements(modal);

        if (focusable.length === 0) {
            event.preventDefault();
            modal.querySelector('[data-modal-panel]')?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function text(value, fallback = '-') {
        const normalized = String(value ?? '').trim();
        return normalized === '' ? fallback : normalized;
    }

    function setText(modal, field, value) {
        modal.querySelectorAll(`[data-detail-field="${field}"]`).forEach((element) => {
            element.textContent = text(value);
        });
    }

    function populateBarangays(select, municipalityId, selectedId = '') {
        if (!select) return;

        const matching = barangays.filter(
            (barangay) => String(barangay.area_unit_id) === String(municipalityId)
        );

        const initialLabel = select.dataset.allLabel || 'All barangays';
        select.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = municipalityId
            ? (matching.length ? initialLabel : 'No barangays available')
            : 'Select municipality first';
        select.appendChild(placeholder);

        matching.forEach((barangay) => {
            const option = document.createElement('option');
            option.value = String(barangay.id);
            option.textContent = barangay.name + (barangay.is_archived ? ' (Archived)' : '');
            option.selected = String(barangay.id) === String(selectedId);
            select.appendChild(option);
        });

        select.disabled = !municipalityId;
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(document.getElementById(button.dataset.openModal), button);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(button.closest('[data-modal]'));
        });
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(modal);
                return;
            }

            trapFocus(event, modal);
        });

        modal.querySelector('[data-modal-backdrop]')?.addEventListener('click', (event) => {
            if (event.target === event.currentTarget) {
                closeModal(modal);
            }
        });
    });

    document.querySelectorAll('[data-member-details]').forEach((button) => {
        button.addEventListener('click', () => {
            const member = safelyParseJson(button.dataset.memberDetails, null);
            const modal = document.getElementById('member-details-modal');

            if (!member || !modal) return;

            Object.entries(member).forEach(([key, value]) => setText(modal, key, value));

            const fullRecord = modal.querySelector('[data-member-full-record]');
            if (fullRecord) {
                fullRecord.href = member.show_url || '#';
            }

            openModal(modal, button);
        });
    });

    document.querySelectorAll('[data-edit-member]').forEach((button) => {
        button.addEventListener('click', () => {
            const member = safelyParseJson(button.dataset.editMember, null);
            const modal = document.getElementById('edit-member-modal');
            const form = modal?.querySelector('[data-edit-member-form]');

            if (!member || !modal || !form) return;

            form.action = member.update_url;

            Object.entries(member).forEach(([key, value]) => {
                const field = form.querySelector(`[data-member-field="${key}"]`);
                if (field) {
                    field.value = value ?? '';
                }
            });

            const nameTarget = modal.querySelector('[data-edit-member-name]');
            if (nameTarget) {
                nameTarget.textContent = member.full_name || 'Member';
            }

            openModal(modal, button);
        });
    });

    document.querySelectorAll('[data-archive-member]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById('archive-member-modal');
            const form = modal?.querySelector('[data-archive-form]');

            if (!modal || !form) return;

            form.action = button.dataset.archiveUrl || '#';

            const nameTarget = modal.querySelector('[data-archive-member-name]');
            if (nameTarget) {
                nameTarget.textContent = button.dataset.memberName || 'this member';
            }

            openModal(modal, button);
        });
    });

    const filterMunicipality = page.querySelector('[data-filter-municipality]');
    const filterBarangay = page.querySelector('[data-filter-barangay]');

    if (filterMunicipality && filterBarangay) {
        const params = new URLSearchParams(window.location.search);

        populateBarangays(
            filterBarangay,
            filterMunicipality.value,
            params.get('sub_unit_id') ?? ''
        );

        filterMunicipality.addEventListener('change', () => {
            populateBarangays(filterBarangay, filterMunicipality.value);
        });
    }
});