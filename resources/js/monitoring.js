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
