import { coordinateError, parseSaveReply } from './contracts.ts';

export function createEditor(root, getMap) {
    // Find the form controls inside this GIS page. getMap provides the map once it is ready.
    const panel = root.querySelector('[data-gis-editor]');
    const form = panel.querySelector('form');
    const fields = Object.fromEntries(['association_id', 'location_name', 'latitude', 'longitude'].map(name => [name, form.elements.namedItem(name)]));
    const save = panel.querySelector('[data-gis-save]');
    const cancel = panel.querySelector('[data-gis-cancel]');
    const message = panel.querySelector('[data-gis-save-message]');
    const reload = panel.querySelector('[data-gis-reload]');
    const add = root.querySelector('[data-gis-add]');
    // Track whether the form is open, saving, or waiting for a reload before another save.
    let active = false;
    let busy = false;
    let blocked = false;
    let editing = null;
    let returnFocus = null;
    let attachedMap = null;
    let saved = false;

    // Show a message and move keyboard focus to it without moving the page.
    function feedback(text) {
        message.textContent = text;
        message.hidden = !text;
        if (text) message.focus({ preventScroll: true });
    }

    // Remove old field messages before checking the form again.
    function clearErrors() {
        panel.querySelectorAll('[data-gis-error]').forEach(node => { node.textContent = ''; });
        Object.values(fields).forEach(field => field.removeAttribute('aria-invalid'));
    }

    // Put each error beside its field and focus the first field the user can correct.
    function showErrors(errors) {
        clearErrors();
        Object.entries(fields).forEach(([name, field]) => {
            const messages = errors[name];
            if (!messages?.length) return;
            panel.querySelector(`[data-gis-error="${name}"]`).textContent = messages.join(' ');
            field.setAttribute('aria-invalid', 'true');
        });
        const first = Object.values(fields).find(field => field.getAttribute('aria-invalid') === 'true' && !field.disabled);
        first?.focus();
    }

    // Clear only the message for the field that the user has changed.
    function clearFieldError(name) {
        fields[name].removeAttribute('aria-invalid');
        panel.querySelector(`[data-gis-error="${name}"]`).textContent = '';
    }

    // Show a temporary pin only when both coordinates are valid. This does not save anything.
    function preview() {
        if (!active || busy) return;
        const valid = !coordinateError(fields.latitude.value, 90) && !coordinateError(fields.longitude.value, 180);
        getMap()?.preview(valid ? [Number(fields.latitude.value), Number(fields.longitude.value)] : null);
        panel.querySelector('[data-gis-draft-state]').textContent = valid
            ? 'Temporary position selected. Save to keep these changes.' : 'Enter valid coordinates or choose a position on the map.';
    }

    // Connect map clicks to the form. This also works when the map loads after the form opens.
    function attachMap() {
        const map = getMap();
        if (!active || !map || attachedMap === map) return;
        attachedMap = map;
        map.beginEdit(editing?.id ?? null, (latitude, longitude) => {
            if (busy || blocked) return;
            // Copy the chosen position into the fields using eight decimal places.
            fields.latitude.value = latitude.toFixed(8);
            fields.longitude.value = longitude.toFixed(8);
            clearFieldError('latitude');
            clearFieldError('longitude');
            preview();
        });
        preview();
        map.showDraft();
    }

    // Open a blank form for a new location, or fill it with the selected location.
    function open(record = null) {
        if (active || (record && !record.editable)) return;
        returnFocus = document.activeElement;
        editing = record;
        active = true;
        busy = false;
        blocked = false;
        saved = false;
        form.reset();
        clearErrors();
        feedback('');
        reload.hidden = true;
        panel.querySelector('[data-gis-fields]').disabled = false;
        // Existing locations keep their association. Missing coordinates stay blank, not zero.
        fields.association_id.disabled = Boolean(record);
        fields.association_id.value = record ? String(record.association_id) : '';
        fields.location_name.value = record?.name ?? '';
        fields.latitude.value = record?.latitude == null ? '' : String(record.latitude);
        fields.longitude.value = record?.longitude == null ? '' : String(record.longitude);
        save.disabled = false;
        cancel.disabled = false;
        save.textContent = 'Save location';
        panel.querySelector('#gis-editor-title').textContent = record ? 'Edit location' : 'Add location';
        panel.querySelector('[data-gis-publication-note]').textContent = record
            ? 'This edit keeps the current publication status and association.' : 'New locations are saved as unpublished.';
        root.classList.add('gis-is-editing');
        panel.hidden = false;
        add.disabled = true;
        // Keep filters and map reset from interrupting the current edit.
        root.querySelectorAll('[data-filter], [data-gis-clear], [data-gis-reset]').forEach(control => { control.disabled = true; });
        attachMap();
        (record ? fields.location_name : fields.association_id).focus({ preventScroll: true });
        panel.scrollIntoView({ block: 'nearest', behavior: 'instant' });
    }

    // Cancel the draft, restore the map, and return focus to the button that opened the form.
    function close() {
        if (busy || saved) return;
        active = false;
        attachedMap?.endEdit();
        attachedMap = null;
        panel.hidden = true;
        root.classList.remove('gis-is-editing');
        add.disabled = false;
        root.querySelectorAll('[data-filter], [data-gis-clear]').forEach(control => { control.disabled = false; });
        root.querySelector('[data-gis-reset]').disabled = !getMap();
        returnFocus?.focus({ preventScroll: true });
    }

    // Update the preview as values change. After a coordinate change, bring the pin into view.
    Object.entries(fields).forEach(([name, field]) => field.addEventListener('input', () => { clearFieldError(name); preview(); }));
    for (const name of ['latitude', 'longitude']) fields[name].addEventListener('change', () => {
        if (!active || busy || blocked) return;
        if (!coordinateError(fields.latitude.value, 90) && !coordinateError(fields.longitude.value, 180)) {
            preview();
            getMap()?.showDraft();
        }
    });
    // Enable the form buttons after their controls are ready. Escape also cancels the edit.
    add.hidden = false;
    add.addEventListener('click', () => open());
    cancel.addEventListener('click', close);
    panel.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); close(); } });

    // Save through a request so errors can be shown without losing the entered values.
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || blocked) return;
        // Check the form before sending it. Laravel must also validate it on the server.
        const errors = {};
        if (!editing && !fields.association_id.value) errors.association_id = ['Select an association.'];
        const name = fields.location_name.value.trim();
        if (!name || [...name].length > 255) errors.location_name = ['Enter a location name of 1 to 255 characters.'];
        for (const [field, limit] of [['latitude', 90], ['longitude', 180]]) {
            const error = coordinateError(fields[field].value, limit);
            if (error) errors[field] = [error];
        }
        if (Object.keys(errors).length) { feedback('Check the fields below.'); showErrors(errors); return; }
        clearErrors();
        // Send only the allowed fields. The revision lets Laravel detect an outdated edit.
        const payload = { location_name: name, latitude: fields.latitude.value, longitude: fields.longitude.value };
        if (editing) payload.revision = editing.revision;
        else payload.association_id = fields.association_id.value;
        // Disable controls while saving to prevent repeated clicks or changes during the request.
        busy = true;
        save.disabled = true;
        cancel.disabled = true;
        panel.querySelector('[data-gis-fields]').disabled = true;
        save.textContent = 'Saving…';
        feedback('Saving location…');
        // Stop waiting after 25 seconds. This does not guarantee that the server stopped saving.
        const abort = new AbortController();
        const timeout = setTimeout(() => abort.abort(), 25000);
        try {
            // POST creates a location; PUT edits one. Include the session and Laravel CSRF token.
            const response = await fetch(editing ? editing.update_url : form.action, {
                method: editing ? 'PUT' : 'POST', credentials: 'same-origin', signal: abort.signal,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements.namedItem('_token').value },
                body: JSON.stringify(payload),
            });
            // Check the response structure. A redirected login page must not count as a successful save.
            const body = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
            const result = parseSaveReply(response.redirected ? 401 : response.status, body);
            if (result.ok) {
                saved = true;
                blocked = true;
                feedback(`${result.message} Reloading saved locations…`);
                // Reload from the database after a confirmed commit. Never insert a guessed success pin.
                try { sessionStorage.setItem('gis-save-message', result.message); } catch { /* Saving still succeeded if browser storage is unavailable. */ }
                reload.hidden = false;
                window.location.reload();
                return;
            }
            // Keep the entered values. Some errors allow corrections; others require a reload.
            blocked = result.reload;
            reload.hidden = !result.reload;
            feedback(result.message);
            showErrors(result.errors);
        } catch {
            // A lost response does not prove the save failed. Do not repeat it automatically.
            blocked = true;
            reload.hidden = false;
            feedback('The save could not be confirmed. Refresh and check the location before trying again.');
        } finally {
            // Always clear the timer. Allow another save only when the result says it is safe.
            clearTimeout(timeout);
            busy = false;
            save.disabled = blocked;
            cancel.disabled = saved;
            save.textContent = saved ? 'Saved' : 'Save location';
            panel.querySelector('[data-gis-fields]').disabled = blocked;
            if (!blocked) panel.querySelector('[aria-invalid="true"]:not(:disabled)')?.focus();
        }
    });

    // Give the GIS page access to the editor controls and its current open/closed state.
    return { open, attachMap, get active() { return active; } };
}
