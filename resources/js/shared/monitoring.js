document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-monitoring-form]');
    if (!form) return;
    const project = form.querySelector('[name="project_id"]');
    const material = form.querySelector('[name="project_material_id"]');
    if (project && material) {
        const options = [...material.options];
        const filterMaterials = () => {
            const selected = material.value;
            material.replaceChildren(...options.filter(option => !option.value || option.dataset.project === project.value));
            material.value = [...material.options].some(option => option.value === selected) ? selected : '';
        };
        project.addEventListener('change', filterMaterials);
        filterMaterials();
    }

    // Match the form to the selected project's fixed unit.
    // The backend independently enforces the same restriction.
    const unit = form.querySelector('[name="output_unit_code"]');
    const specification = form.querySelector('[name="output_unit_spec"]');
    const unitData = form.querySelector('[data-project-unit-data]');

    if (project && unit && specification && unitData) {
        const settings = JSON.parse(unitData.textContent);
        const packageUnits = ['pack', 'bottle', 'jar', 'can', 'box', 'bag', 'tray'];
        const specificationBox = form.querySelector('[data-unit-specification]');
        const message = form.querySelector('[data-unit-message]');

        const syncPackage = () => {
            const packaged = packageUnits.includes(unit.value);
            specificationBox.hidden = !packaged;
            specification.required = packaged;

            if (!packaged) specification.value = '';
        };

        const syncProjectUnit = () => {
            const fixed = settings[project.value] || {};
            const locked = Boolean(fixed.code);

            // Disabled options keep the select's chosen value in the submitted form.
            for (const option of unit.options) {
                option.disabled = locked && option.value !== fixed.code;
            }

            if (locked) {
                unit.value = fixed.code;
                specification.value = fixed.spec || '';
            }

            specification.readOnly = locked;
            message.textContent = locked
                ? 'This project’s production unit is fixed. Use it for target and actual output.'
                : 'Select the unit carefully. It becomes fixed when this record is saved.';

            syncPackage();
        };

        project.addEventListener('change', syncProjectUnit);
        unit.addEventListener('change', syncPackage);
        syncProjectUnit();
    }

    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Saving…';
    });
    window.addEventListener('pageshow', () => {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = false;
        button.textContent = 'Save Record';
    });
});

// Native disclosures work without JavaScript; Escape closes them and returns focus.
function closeIncomeDetails(details) {
    details.open = false;
    details.querySelector('summary').focus();
}
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-income-close]');
    if (button) closeIncomeDetails(button.closest('[data-income-details]'));
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const details = event.target.closest('[data-income-details][open]');
    if (details) {
        closeIncomeDetails(details);
    }
});
