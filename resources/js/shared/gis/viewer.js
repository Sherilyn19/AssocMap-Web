import '../../../css/gis.css';
import '../../../css/gis-viewer.css';

const root = document.querySelector('[data-gis-viewer]');

if (root) {
    // Normal GET submission keeps filtering functional without JavaScript.
    root.querySelector('[data-viewer-filter-form]')?.addEventListener('submit', () => {
        root.querySelector('[data-viewer-filter-loading]').hidden = false;
    });

    const source = root.querySelector('[data-viewer-records]');
    if (source) void initialize();
}

async function initialize() {
    const status = root.querySelector('[data-viewer-status]');
    const dialog = root.querySelector('[data-viewer-details]');
    const content = root.querySelector('[data-viewer-detail-content]');
    const closeButton = root.querySelector('[data-viewer-close]');
    const reset = root.querySelector('[data-viewer-reset]');

    const reduced = () =>
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let map = null;
    let opener = null;
    let closing = false;
    let records;

    function setStatus(message) {
        status.textContent = message;
        status.hidden = !message;
    }

    try {
        records = JSON.parse(
            root.querySelector('[data-viewer-records]').textContent,
        ).map((record, index) => ({
            ...record,

            // These are page-local selection IDs, not database management IDs.
            id: index,
            valid: Number.isFinite(record.latitude)
                && Number.isFinite(record.longitude),
            published: record.published ?? true,
        }));
    } catch {
        setStatus('Location details could not load. Please reload the page.');
        return;
    }

    function text(tag, value, className = '') {
        const node = document.createElement(tag);
        node.textContent = value;
        node.className = className;
        return node;
    }

    function select(id, focusMap = true) {
        if (closing) return;

        const record = records.find(item => item.id === id);
        if (!record) return;

        opener = document.activeElement;
        content.replaceChildren();

        content.append(
            text('span', record.component, 'gis-explorer-component'),
            text('h3', record.name),
            text('p', record.association),
        );

        const values = {
            Area: `${record.barangay}, ${record.municipality}`,
            Project: record.project_title || 'No project information listed',
            Commodity: record.commodity || 'Not listed',
            Coordinates: `${record.latitude}, ${record.longitude}`,
        };

        const details = document.createElement('dl');

        Object.entries(values).forEach(([label, value]) => {
            const field = document.createElement('div');
            field.append(text('dt', label), text('dd', value));
            details.append(field);
        });

        content.append(details);

        // Render only fields supplied by the existing authorized read endpoint.
        // No management, member, account, or audit information is requested.
        map?.select(id, focusMap);

        root.querySelectorAll('[data-viewer-row]').forEach(row => {
            row.classList.toggle('is-selected', Number(row.dataset.viewerRow) === id);
        });

        if (!dialog.open) dialog.showModal();
        closeButton.focus();
    }

    async function close() {
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

        if (opener?.isConnected) opener.focus({ preventScroll: true });
    }

    closeButton.addEventListener('click', () => { void close(); });
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        void close();
    });

    // Record details remain usable even if the map library or tiles fail.
    root.querySelectorAll('[data-viewer-select]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', () => {
            select(Number(button.dataset.viewerSelect));
        });
    });

    try {
        const { createMap } = await import('./map');

        map = createMap(
            root.querySelector('[data-viewer-map]'),
            select,
            setStatus,
            { detailsModal: true },
        );

        map.update(records);
        reset.disabled = false;
        reset.addEventListener('click', () => map.fit());
    } catch {
        setStatus('The interactive map could not load. Location details remain available in the list.');
    }
}