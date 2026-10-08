import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { validPosition } from './data';

export function createMap(container, onSelect, onStatus, options = {}) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const map = L.map(container, { zoomAnimation: !reducedMotion, fadeAnimation: !reducedMotion });
    // Remove Leaflet branding while retaining the tile provider's required credit.
    map.attributionControl.setPrefix(false);
    // The Philippines view is only a starting extent when no valid records exist.
    map.setView([12.5, 122], 5);
    const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);
    const layer = L.layerGroup().addTo(map);
    L.control.layers(null, { 'Association locations': layer }, { collapsed: true }).addTo(map);
    let failedTiles = false;
    let loadingTimer;
    function tileStatus(message) {
        onStatus(map.hasLayer(layer) ? message : 'Location pins are hidden. Turn on Association locations in the layer control to show them.');
    }
    function startLoading() {
        clearTimeout(loadingTimer);
        loadingTimer = setTimeout(() => tileStatus('The background map is taking longer to load. Location details remain available.'), 15000);
    }
    startLoading();
    tiles.on('loading', () => { failedTiles = false; startLoading(); });
    tiles.on('tileerror', () => {
        failedTiles = true;
        clearTimeout(loadingTimer);
        tileStatus('Some background map tiles could not load. Pins and location details are still available.');
    });
    tiles.on('load', () => {
        clearTimeout(loadingTimer);
        if (!failedTiles) tileStatus('');
    });
    map.on('overlayremove', () => onStatus('Location pins are hidden. Turn on Association locations in the layer control to show them.'));
    map.on('overlayadd', () => onStatus(failedTiles ? 'Some background map tiles could not load.' : ''));
    let records = [];
    let markers = [];
    let selectedId = null;
    let edit = null;
    let draft = null;
    let originalView = null;
    map.on('click', event => {
        if (edit) {
            const position = event.latlng.wrap();
            edit.pick(position.lat, position.lng);
        }
    });

    function fit() {
        const valid = records.filter(validPosition);
        if (valid.length) map.fitBounds(valid.map(r => [r.latitude, r.longitude]), { padding: [35, 35], maxZoom: 15, animate: false });
        else map.setView([12.5, 122], 5, { animate: false });
    }

    function draw() {
        layer.clearLayers();
        markers = [];
        const groups = [];
        // Group nearby pins by screen distance without changing their saved positions.
        records.filter(record => validPosition(record) && record.id !== edit?.id).forEach(record => {
            const point = map.latLngToLayerPoint([record.latitude, record.longitude]);
            const group = groups.find(item => item.point.distanceTo(point) < 30);
            if (group) group.records.push(record);
            else groups.push({ point, records: [record] });
        });
        groups.forEach(group => {
            const first = group.records[0];
            const multiple = group.records.length > 1;
            const selected = group.records.some(r => r.id === selectedId);
            const label = multiple ? `${group.records.length} locations. Select to choose a record.` : `${first.name}, ${first.published ? 'Published' : 'Unpublished'}`;
            const marker = L.marker([first.latitude, first.longitude], {
                title: label, alt: label,
                icon: L.divIcon({ className: `gis-pin ${multiple ? 'is-group' : first.published ? '' : 'is-unpublished'} ${selected ? 'is-selected' : ''}`, html: multiple ? String(group.records.length) : '', iconSize: [30, 30], iconAnchor: [15, 15] }),
            }).addTo(layer);
            marker.getElement()?.setAttribute('aria-label', label);
            const popup = document.createElement('div');
            popup.className = 'gis-popup';
            group.records.forEach(record => {
                const name = document.createElement('strong');
                name.textContent = record.association;
                const context = document.createElement('div');
                context.textContent = `${record.barangay} · ${record.municipality}`;
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = `View ${record.name}`;
                button.addEventListener('click', () => onSelect(record.id, true));
                popup.append(name, context, button);
            });
            // Grouped markers still need a chooser.
            // Individual FO locations open the compact details modal directly.
            if (multiple || !options.detailsModal) marker.bindPopup(popup);
            marker.on('click', () => {
                if (edit) edit.pick(first.latitude, first.longitude);
                else if (!multiple) onSelect(first.id, false);
            });
            markers.push({ marker, ids: group.records.map(r => r.id) });
        });
    }

    map.on('zoomend', draw);
    const resize = new ResizeObserver(() => map.invalidateSize({ pan: false }));
    resize.observe(container);

    return {
        update(next) { records = next; selectedId = null; fit(); draw(); },
        fit,
        beginEdit(id, pick) {
            originalView = { center: map.getCenter(), zoom: map.getZoom() };
            edit = { id, pick };
            map.closePopup();
            container.classList.add('gis-picking');
            const record = records.find(item => item.id === id);
            if (record && validPosition(record)) map.setView([record.latitude, record.longitude], Math.max(map.getZoom(), 15), { animate: false });
            draw();
        },
        preview(position) {
            if (draft) { map.removeLayer(draft); draft = null; }
            if (position) {
                draft = L.circleMarker(position, { radius: 10, color: '#1d4ed8', fillColor: '#dbeafe', fillOpacity: .9, weight: 3, dashArray: '4 3', interactive: false }).addTo(map);
                draft.bindTooltip('Unsaved location', { permanent: true, direction: 'top' });
            }
        },
        endEdit() {
            if (draft) { map.removeLayer(draft); draft = null; }
            edit = null;
            container.classList.remove('gis-picking');
            if (originalView) map.setView(originalView.center, originalView.zoom, { animate: false });
            originalView = null;
            draw();
        },
        showDraft() {
            if (draft) map.panTo(draft.getLatLng(), { animate: false });
        },
        select(id, focus) {
            selectedId = id;
            const record = records.find(r => r.id === id);
            if (focus && record && validPosition(record)) {
                layer.addTo(map);
                map.setView([record.latitude, record.longitude], Math.max(map.getZoom(), 15), { animate: false });
            }
            // Change the selected ring without rebuilding a popup while it is open.
            markers.forEach(({ marker, ids }) => marker.getElement()?.classList.toggle('is-selected', ids.includes(id)));
            if (options.detailsModal) {
                map.closePopup();
            } else if (focus) {
                markers.find(item => item.ids.includes(id))?.marker.openPopup();
            }
        },
    };
}
