// Filter the actual records supplied by Laravel; never substitute demo coordinates.
export function filterRecords(records, filters) {
    const search = (filters.search || '').trim().toLocaleLowerCase();
    return records.filter(record => {
        const text = [record.name, record.association, record.municipality, record.barangay].join(' ').toLocaleLowerCase();
        return (!search || text.includes(search))
            && (!filters.municipality || String(record.municipality_id) === filters.municipality)
            && (!filters.barangay || String(record.barangay_id) === filters.barangay)
            && (!filters.component || String(record.component_id) === filters.component)
            && (!filters.publication || record.published === (filters.publication === 'published'))
            && (!filters.commodity || (!record.project_archived && record.commodity === filters.commodity));
    });
}

export function validPosition(record) {
    return record.valid === true && Number.isFinite(record.latitude) && Number.isFinite(record.longitude)
        && record.latitude >= -90 && record.latitude <= 90 && record.longitude >= -180 && record.longitude <= 180;
}

export function optionsFor(records, field, parent = '') {
    const values = new Map();
    records.forEach(record => {
        if (field === 'barangay' && parent && String(record.municipality_id) !== parent) return;
        if (record[`${field}_id`] != null) values.set(String(record[`${field}_id`]), record[field]);
    });
    return [...values].sort((a, b) => a[1].localeCompare(b[1]));
}

export function summarize(records) {
    return {
        mapped: records.filter(validPosition).length,
        invalid: records.filter(record => !validPosition(record)).length,
        municipalities: new Set(records.map(record => record.municipality_id).filter(id => id != null)).size,
        barangays: new Set(records.map(record => record.barangay_id).filter(id => id != null)).size,
    };
}
