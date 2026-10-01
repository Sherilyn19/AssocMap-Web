import '../../../css/gis.css';
import { createMap } from './map';

const root = document.querySelector('[data-gis-viewer]');
const source = root?.querySelector('[data-viewer-records]');
if (source) {
    const status = root.querySelector('[data-viewer-status]');
    const records = JSON.parse(source.textContent).map((record, index) => ({
        ...record, id: index, valid: true, published: record.published ?? true,
    }));
    const map = createMap(root.querySelector('[data-viewer-map]'), (id, focus) => map.select(id, focus), message => {
        status.textContent = message || 'Map ready. Select a location to view its details.';
    });
    map.update(records);
    root.querySelectorAll('[data-viewer-select]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', () => {
            map.select(Number(button.dataset.viewerSelect), true);
            root.querySelector('[data-viewer-map]').scrollIntoView({ block: 'nearest' });
        });
    });
}
