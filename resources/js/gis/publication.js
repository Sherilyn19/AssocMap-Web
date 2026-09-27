import { saveLocation, reloadSaved } from './request';

export function createPublication(root) {
    const dialog = root.querySelector('[data-gis-publication]');
    const confirm = dialog.querySelector('[data-publication-confirm]');
    const cancel = dialog.querySelector('[data-publication-cancel]');
    const message = dialog.querySelector('[data-publication-message]');
    const reload = dialog.querySelector('[data-publication-reload]');
    let record = null;
    let busy = false;
    let blocked = false;
    let trigger = null;

    function open(selected, button) {
        if (busy || !selected.editable) return;
        record = selected;
        trigger = button;
        blocked = false;
        message.hidden = true;
        reload.hidden = true;
        confirm.disabled = false;
        cancel.disabled = false;
        const action = record.published ? 'Unpublish' : 'Publish';
        dialog.querySelector('#gis-publication-title').textContent = `${action} location`;
        dialog.querySelector('[data-publication-name]').textContent = `${record.name} · ${record.association}`;
        dialog.querySelector('#gis-publication-description').textContent = record.published
            ? 'This location will be removed from the published collection. Its record and history will be kept.'
            : 'This location will be eligible for public viewing. Confirm that the name and map position are correct.';
        confirm.textContent = `${action} location`;
        dialog.showModal();
        cancel.focus();
    }

    confirm.addEventListener('click', async () => {
        if (busy || blocked || !record) return;
        busy = true;
        confirm.disabled = true;
        cancel.disabled = true;
        confirm.textContent = 'Saving…';
        dialog.setAttribute('aria-busy', 'true');
        const result = await saveLocation(record.publication_url, 'PATCH', { revision: record.revision }, root.querySelector('[name="_token"]').value);
        busy = false;
        dialog.removeAttribute('aria-busy');
        blocked = result.ok || result.reload || Boolean(result.errors.association_id);
        message.textContent = result.message;
        message.hidden = false;
        message.focus();
        reload.hidden = !blocked;
        cancel.disabled = result.ok;
        confirm.disabled = blocked || Boolean(!result.ok && result.errors.publication);
        confirm.textContent = result.ok ? 'Saved' : (record.published ? 'Unpublish location' : 'Publish location');
        if (result.ok) reloadSaved(result.message);
    });
    cancel.addEventListener('click', () => { if (!busy) dialog.close(); });
    dialog.addEventListener('cancel', event => { if (busy || cancel.disabled) event.preventDefault(); });
    dialog.addEventListener('close', () => { record = null; trigger?.focus(); });
    return { open };
}
