import '../../css/gis.css';
import { filterRecords, optionsFor, summarize, validPosition } from './data';
import { createEditor } from './editor';
import { createPublication } from './publication';
import { parseMapRecords } from './contracts.ts';

const page = document.querySelector('[data-gis-page]');
if (page) initialize(page);

async function initialize(root) {
    const status = root.querySelector('[data-gis-map-status]');
    function setStatus(message) { status.textContent = message; status.hidden = !message; }
    let records;
    try { records = parseMapRecords(JSON.parse(root.querySelector('[data-gis-data]').textContent)); }
    catch { setStatus('Map data could not load. Reload the page or use the location list.'); return; }
    const fields = Object.fromEntries([...root.querySelectorAll('[data-filter]')].map(input => [input.dataset.filter, input]));
    const filters = () => Object.fromEntries(Object.entries(fields).map(([key, input]) => [key, input.value]));
    const rows = [...root.querySelectorAll('[data-gis-row]')];
    const details = root.querySelector('[data-gis-details]');
    let visible = records;
    let selectedId = null;
    let map = null;
    let editor = null;
    const publication = createPublication(root);

    function setOptions(field, parent = '') {
        const input = fields[field];
        const previous = input.value;
        input.replaceChildren(input.options[0]);
        optionsFor(records, field, parent).forEach(([id, name]) => input.add(new Option(name, id)));
        input.value = [...input.options].some(option => option.value === previous) ? previous : '';
    }
    ['municipality', 'barangay', 'component'].forEach(field => setOptions(field));

    function clearSelection() {
        selectedId = null;
        details.hidden = true;
        rows.forEach(row => {
            row.classList.remove('is-selected');
            row.querySelector('button').setAttribute('aria-pressed', 'false');
        });
        map?.select(null, false);
    }

    function select(id, focusMap = true) {
        if (editor?.active) return;
        const record = visible.find(item => item.id === id);
        if (!record) return;
        selectedId = id;
        const content = root.querySelector('[data-gis-detail-content]');
        content.replaceChildren();
        const title = document.createElement('h3');
        title.className = 'font-bold text-slate-900';
        title.textContent = record.name;
        content.append(title);
        const info = document.createElement('dl');
        info.className = 'space-y-2 text-xs';
        const entries = {
            Association: record.association,
            Area: `${record.barangay}, ${record.municipality}`,
            'Program component': record.component,
            'Association status': `${record.status}${record.archived ? ' · Archived' : ''}`,
            Publication: record.published ? 'Published' : 'Unpublished',
            Created: record.created_at || 'Not recorded',
            Updated: record.updated_at || 'Not recorded',
            Coordinates: validPosition(record) ? `${record.latitude.toFixed(6)}, ${record.longitude.toFixed(6)}` : 'Coordinates need review; no pin is shown.',
        };
        Object.entries(entries).forEach(([label, value]) => {
            const term = document.createElement('dt'); term.className = 'font-semibold text-slate-600'; term.textContent = label;
            const description = document.createElement('dd'); description.textContent = value;
            info.append(term, description);
        });
        content.append(info);
        if (record.editable) {
            const edit = document.createElement('button');
            edit.type = 'button'; edit.className = 'gis-button'; edit.textContent = 'Edit location';
            edit.addEventListener('click', () => editor?.open(record));
            content.append(edit);
            const publish = document.createElement('button');
            publish.type = 'button'; publish.className = 'gis-button ml-2';
            publish.textContent = record.published ? 'Unpublish location' : 'Publish location';
            publish.disabled = !record.published && (!record.valid || !record.name.trim());
            publish.addEventListener('click', () => publication.open(record, publish));
            content.append(publish);
        }
        if (record.association_url) {
            const link = document.createElement('a'); link.href = record.association_url;
            link.className = 'inline-block text-sm text-assocmap-primary underline'; link.textContent = 'View association'; content.append(link);
        }
        details.hidden = false;
        rows.forEach(row => {
            const selected = Number(row.dataset.gisRow) === id;
            row.classList.toggle('is-selected', selected);
            row.querySelector('button').setAttribute('aria-pressed', String(selected));
        });
        map?.select(id, focusMap);
        details.focus({ preventScroll: true });
        if (window.matchMedia('(max-width: 900px)').matches) details.scrollIntoView({ behavior: 'instant', block: 'nearest' });
    }

    function update() {
        if (editor?.active) return;
        const state = filters();
        visible = filterRecords(records, state);
        const ids = new Set(visible.map(record => record.id));
        rows.forEach(row => { row.hidden = !ids.has(Number(row.dataset.gisRow)); });
        root.querySelector('[data-gis-empty]').hidden = visible.length !== 0;
        const count = summarize(visible);
        root.querySelector('[data-gis-summary]').textContent = `${visible.length} ${visible.length === 1 ? 'location' : 'locations'} · ${count.mapped} mapped · ${count.municipalities} ${count.municipalities === 1 ? 'municipality' : 'municipalities'} · ${count.barangays} ${count.barangays === 1 ? 'barangay' : 'barangays'}${count.invalid ? ` · ${count.invalid} with coordinates needing review` : ''}`;
        const labels = { search: 'Search', municipality: 'Municipality', barangay: 'Barangay', component: 'Program', publication: 'Publication' };
        const active = Object.entries(state).filter(([, value]) => value).map(([key, value]) => `${labels[key]}: ${key === 'search' ? value : fields[key].selectedOptions[0].textContent}`);
        root.querySelector('[data-gis-active]').textContent = active.length ? active.join(' · ') : 'No filters applied.';
        clearSelection();
        map?.update(visible);
    }

    root.querySelector('[data-gis-controls]').hidden = false;
    let timer;
    Object.entries(fields).forEach(([key, input]) => input.addEventListener(key === 'search' ? 'input' : 'change', () => {
        if (key === 'municipality') setOptions('barangay', fields.municipality.value);
        clearTimeout(timer);
        if (key === 'search') timer = setTimeout(update, 200); else update();
    }));
    root.querySelector('[data-gis-clear]').addEventListener('click', () => {
        clearTimeout(timer);
        Object.values(fields).forEach(input => { input.value = ''; });
        setOptions('barangay'); update();
    });
    root.querySelectorAll('[data-gis-select]').forEach(button => {
        button.disabled = false;
        button.addEventListener('click', () => select(Number(button.dataset.gisSelect)));
    });
    root.querySelector('[data-gis-close]').addEventListener('click', () => {
        const button = rows.find(row => Number(row.dataset.gisRow) === selectedId)?.querySelector('button');
        clearSelection(); button?.focus();
    });
    details.addEventListener('keydown', event => { if (event.key === 'Escape') root.querySelector('[data-gis-close]').click(); });
    update();
    editor = createEditor(root, () => map);
    try {
        const savedMessage = sessionStorage.getItem('gis-save-message');
        if (savedMessage) {
            const feedback = root.querySelector('[data-gis-feedback]');
            feedback.textContent = savedMessage; feedback.hidden = false;
            sessionStorage.removeItem('gis-save-message');
        }
    } catch { /* The map also works when browser storage is unavailable. */ }
    try {
        const { createMap } = await import('./map');
        map = createMap(root.querySelector('[data-gis-map]'), select, setStatus);
        map.update(visible);
        editor.attachMap();
        const reset = root.querySelector('[data-gis-reset]');
        reset.disabled = editor.active;
        reset.addEventListener('click', () => map.fit());
        setStatus('Loading background map…');
    } catch {
        setStatus('The map could not start. Reload the page or use the location list and filters.');
    }
}
