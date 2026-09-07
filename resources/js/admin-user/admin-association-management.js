/**
 * Association Management interactions.
 * 
 * resources/js/admin-user/admin-association-management.js
 *
 * Uses native DOM APIs only so the module remains small, testable, and compatible
 * with the existing Vite/Tailwind stack.
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-association-page]');

    if (!page) {
        return;
    }

    const barangays = safelyParseJson(page.dataset.barangays, []);

    function safelyParseJson(value, fallback) {
        try {
            return JSON.parse(value ?? '');
        } catch {
            return fallback;
        }
    }

    function openModal(modal) {
        if (!modal) return;

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        window.setTimeout(() => {
            modal.querySelector('input, select, textarea, button')?.focus();
        }, 0);
    }

    function closeModal(modal) {
        if (!modal) return;

        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    function populateBarangays(select, municipalityId, selectedId = '') {
        if (!select) return;

        const matching = barangays.filter(
            (barangay) => String(barangay.area_unit_id) === String(municipalityId)
        );

        select.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = municipalityId
            ? (matching.length ? 'Select barangay' : 'No active barangays available')
            : 'Select municipality first';
        select.appendChild(placeholder);

        matching.forEach((barangay) => {
            const option = document.createElement('option');
            option.value = String(barangay.id);
            option.textContent = barangay.name;
            option.selected = String(barangay.id) === String(selectedId);
            select.appendChild(option);
        });

        select.disabled = !municipalityId || matching.length === 0;
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(document.getElementById(button.dataset.openModal));
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('[data-modal]')));
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal(modal);
            }
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        const municipality = form.querySelector('[data-municipality]');
        const barangay = form.querySelector('[data-barangay]');

        if (!municipality || !barangay) return;

        populateBarangays(barangay, municipality.value, barangay.dataset.selectedValue ?? '');

        municipality.addEventListener('change', () => {
            populateBarangays(barangay, municipality.value);
        });
    });

    const filterMunicipality = document.querySelector('[data-filter-municipality]');
    const filterBarangay = document.querySelector('[data-filter-barangay]');

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

    document.querySelectorAll('[data-edit-association]').forEach((button) => {
        button.addEventListener('click', () => {
            const association = safelyParseJson(button.dataset.editAssociation, null);
            const modal = document.getElementById('edit-association-modal');
            const form = modal?.querySelector('[data-edit-form]');

            if (!association || !modal || !form) return;

            form.action = association.update_url;

            Object.entries(association).forEach(([key, value]) => {
                const field = form.querySelector(`[data-field="${key}"]`);
                if (field && key !== 'sub_unit_id') {
                    field.value = value ?? '';
                }
            });

            const municipality = form.querySelector('[data-municipality]');
            const barangay = form.querySelector('[data-barangay]');
            populateBarangays(barangay, municipality?.value, association.sub_unit_id);

            openModal(modal);
        });
    });

    document.querySelectorAll('[data-confirm-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirmMessage || 'Continue with this action?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});