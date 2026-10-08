import '../../css/field-officer-user/gis.css';
import {
    filterRecords,
    optionsFor,
    summarize,
    validPosition,
} from '../shared/gis/data';
import { createEditor } from '../shared/gis/editor';
import { createPublication } from '../shared/gis/publication';
import { parseMapRecords } from '../shared/gis/contracts.ts';

const root = document.querySelector('[data-fo-gis]');
if (root) initialize(root);

async function initialize(root) {
    const status = root.querySelector('[data-gis-map-status]');
    const setStatus = message => {
        status.textContent = message;
        status.hidden = !message;
    };

    let records;

    try {
        records = parseMapRecords(
            JSON.parse(root.querySelector('[data-gis-data]').textContent),
        );

        if (!records.every(record => typeof record.public_visible === 'boolean')) {
            throw new Error('Missing visibility information');
        }
    } catch {
        setStatus('Locations could not load. Reload GIS Mapping.');
        return;
    }

    const fields = Object.fromEntries(
        [...root.querySelectorAll('[data-filter]')]
            .map(input => [input.dataset.filter, input]),
    );

    const rows = [...root.querySelectorAll('[data-gis-row]')];
    const dialog = root.querySelector('[data-fo-gis-details]');
    const content = root.querySelector('[data-fo-gis-detail-content]');
    const actions = root.querySelector('[data-fo-gis-detail-actions]');
    const publication = createPublication(root);

    let map = null;
    let editor = null;
    let visible = records;
    let opener = null;
    let closing = false;

    const reduced = () =>
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function setOptions(field, parent = '') {
        const input = fields[field];
        const previous = input.value;

        input.replaceChildren(input.options[0]);

        optionsFor(records, field, parent).forEach(([id, name]) => {
            input.add(new Option(name, id));
        });

        input.value = [...input.options].some(option => option.value === previous)
            ? previous
            : '';
    }

    ['municipality', 'barangay', 'component'].forEach(field => setOptions(field));

    [...new Set(
        records
            .filter(record => !record.project_archived && record.commodity)
            .map(record => record.commodity),
    )].sort((a, b) => a.localeCompare(b)).forEach(value => {
        fields.commodity.add(new Option(value, value));
    });

    // Use textContent for database values. Never insert names as HTML.
    function element(tag, text, className = '') {
        const node = document.createElement(tag);
        node.textContent = text;
        node.className = className;
        return node;
    }

    function action(label, className, callback) {
        const button = element('button', label, `gis-button ${className}`);
        button.type = 'button';
        button.addEventListener('click', callback);
        actions.append(button);
        return button;
    }

    async function closeDetails(restoreFocus = true) {
        if (!dialog.open || closing) return;
        closing = true;

        if (!reduced()) {
            await dialog.animate(
                [
                    { opacity: 1, transform: 'scale(1)' },
                    { opacity: 0, transform: 'scale(.98)' },
                ],
                { duration: 140, easing: 'ease-in' },
            ).finished.catch(() => undefined);
        }

        dialog.close();
        closing = false;

        if (restoreFocus && opener?.isConnected) {
            opener.focus({ preventScroll: true });
        }
    }

    function showDetails(id, focusMap = true) {
        if (editor?.active || closing) return;

        const record = visible.find(item => item.id === id);
        if (!record) return;

        opener = document.activeElement;
        content.replaceChildren();
        actions.replaceChildren();

        const context = element('div', '', 'fo-gis-detail-context');
        context.append(
            element('h3', record.name),
            element('p', record.association),
            element(
                'span',
                record.public_visible ? 'Publicly visible' : 'Not publicly visible',
                `gis-badge ${record.public_visible
                    ? 'gis-badge-published'
                    : 'gis-badge-unpublished'}`,
            ),
        );

        content.append(context);

        const values = {
            Area: `${record.barangay}, ${record.municipality}`,
            'Program component': record.component,
            Project: record.project_title
                ? `${record.project_title}${record.project_archived ? ' (archived)' : ''}`
                : 'No linked project',
            Commodity: record.commodity || 'Not recorded',
            'Association status': record.status,
            Coordinates: validPosition(record)
                ? `${record.latitude_text}, ${record.longitude_text}`
                : 'Coordinates need review',
        };

        const grid = element('dl', '', 'fo-gis-detail-grid');

        Object.entries(values).forEach(([label, value]) => {
            const field = document.createElement('div');
            field.append(element('dt', label), element('dd', value));
            grid.append(field);
        });

        content.append(grid);

        // Keep an important eligibility warning visible instead of hiding it in a tooltip.
        if (!record.public_visible) {
            const explanation = !record.can_publish
                ? 'Publication is unavailable while this association is inactive or archived.'
                : !record.valid
                    ? 'Correct the coordinates before publishing.'
                    : 'Review this location and select Publish to make it publicly visible.';

            content.append(element('p', explanation, 'fo-gis-notice'));
        }

        const history = element('a', 'View history', 'gis-button');
        history.href = record.history_url;
        history.dataset.gisHistory = '';
        actions.append(history);

        if (record.editable) {
            action('Edit', 'fo-gis-edit', async () => {
                // Close the modal before enabling map-based coordinate selection.
                await closeDetails(false);
                root.querySelector('[data-gis-add]').focus({ preventScroll: true });
                editor.open(record);
            });

            const publish = action(
                record.published ? 'Unpublish' : 'Publish',
                record.published ? '' : 'gis-save-button',
                () => publication.open(record, publish),
            );

            publish.disabled = !record.published
                && (!record.can_publish || !record.valid);

            action('Archive', 'fo-gis-archive', event => {
                publication.open(record, event.currentTarget, 'archive');
            });
        }

        rows.forEach(row => {
            const selected = Number(row.dataset.gisRow) === id;
            row.classList.toggle('is-selected', selected);
            row.querySelector('[data-gis-select]')
                .setAttribute('aria-pressed', String(selected));
        });

        map?.select(id, focusMap);

        if (!dialog.open) dialog.showModal();
        root.querySelector('[data-fo-gis-close]').focus();
    }

    function applyFilters() {
        if (editor?.active) return;

        const state = Object.fromEntries(
            Object.entries(fields).map(([key, input]) => [key, input.value]),
        );

        // Existing shared filters handle names, area, component, and commodity.
        visible = filterRecords(records, state).filter(record =>
            (!state.association
                || String(record.association_id) === state.association)
            && (!state.visibility
                || record.public_visible === (state.visibility === 'public')),
        );

        const ids = new Set(visible.map(record => record.id));

        rows.forEach(row => {
            row.hidden = !ids.has(Number(row.dataset.gisRow));
            row.classList.remove('is-selected');
            row.querySelector('[data-gis-select]').setAttribute('aria-pressed', 'false');
        });

        const count = summarize(visible);

        root.querySelector('[data-gis-summary]').textContent =
            `${visible.length} matching locations · ${count.mapped} mapped`;

        root.querySelector('[data-gis-list-count]').textContent = String(visible.length);
        root.querySelector('[data-gis-empty]').hidden = visible.length !== 0;

        // Marker colors represent effective public visibility.
        // Keep the original publication flag in records for management actions.
        map?.update(visible.map(record => ({
            ...record,
            published: record.public_visible,
        })));
    }

    root.querySelector('[data-fo-gis-filters]').addEventListener('submit', event => {
        event.preventDefault();
        applyFilters();
    });

    fields.municipality.addEventListener('change', () => {
        setOptions('barangay', fields.municipality.value);
    });

    function resetFields() {
        Object.values(fields).forEach(input => { input.value = ''; });
        setOptions('barangay');
    }

    root.querySelector('[data-gis-clear]').addEventListener('click', () => {
        if (editor?.active) return;
        resetFields();
        applyFilters();
    });

    root.querySelectorAll('[data-gis-card]').forEach(button => {
        button.addEventListener('click', () => {
            if (editor?.active) return;

            // A summary-card click shows exactly the scope counted by the card.
            resetFields();
            fields.visibility.value = button.dataset.gisCard;
            applyFilters();

            root.querySelector('.gis-workspace').scrollIntoView({
                block: 'start',
                behavior: reduced() ? 'instant' : 'smooth',
            });
        });
    });

    root.querySelectorAll('[data-gis-select]').forEach(button => {
        button.disabled = false;
        button.addEventListener('click', () => {
            showDetails(Number(button.dataset.gisSelect));
        });
    });

    root.querySelector('[data-fo-gis-close]').addEventListener('click', () => {
        void closeDetails();
    });

    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        void closeDetails();
    });

    root.querySelector('[data-gis-controls]').hidden = false;
    editor = createEditor(root, () => map);
    applyFilters();

    try {
        const savedMessage = sessionStorage.getItem('gis-save-message');

        if (savedMessage) {
            const feedback = root.querySelector('[data-gis-feedback]');
            feedback.textContent = savedMessage;
            feedback.hidden = false;
            sessionStorage.removeItem('gis-save-message');
        }
    } catch {
        // GIS still works when browser storage is unavailable.
    }

    try {
        const { createMap } = await import('../shared/gis/map');

        map = createMap(
            root.querySelector('[data-gis-map]'),
            showDetails,
            setStatus,
            { detailsModal: true },
        );

        applyFilters();
        editor.attachMap();

        const reset = root.querySelector('[data-gis-reset]');
        reset.disabled = editor.active;
        reset.addEventListener('click', () => map.fit());
    } catch {
        setStatus('The map could not load. You can still use the location records.');
    }
}