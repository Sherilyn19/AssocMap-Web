document.querySelectorAll('[data-gis-transfer-form]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.submitted) { event.preventDefault(); return; }
        form.dataset.submitted = 'true';
        form.querySelector('button[type="submit"]').disabled = true;
        form.querySelector('[data-transfer-status]').textContent = 'Processing request… Please wait for the validation or completion result.';
    });
});

const exportForm = document.querySelector('[data-gis-export]');
if (exportForm) exportForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = exportForm.querySelector('button');
    const status = document.querySelector('[data-gis-export-status]');
    if (button.disabled) return;
    button.disabled = true;
    status.textContent = 'Preparing the filtered download…';
    document.querySelectorAll('[data-filter]').forEach(input => {
        exportForm.elements.namedItem(input.dataset.filter).value = input.value;
    });
    try {
        const response = await fetch(exportForm.action, { method: 'POST', body: new FormData(exportForm), headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(45000) });
        if (!response.ok || response.redirected || !response.headers.get('Content-Disposition')?.startsWith('attachment;')) {
            const failure = await response.json().catch(() => ({}));
            const errors = Object.values(failure.errors || {}).flat();
            throw new Error(errors[0] || 'Download failed. Check your sign-in, filters and processing configuration.');
        }
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a'); link.href = url;
        link.download = 'assocmap-internal-locations.' + exportForm.elements.namedItem('format').value;
        document.body.append(link); link.click(); link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
        status.textContent = 'Download prepared. Keep internal and unpublished location files within authorized use.';
    } catch (error) { status.textContent = error.message || 'Download failed. Please try again.'; }
    finally { button.disabled = false; }
});
