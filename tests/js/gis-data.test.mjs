import test from 'node:test';
import assert from 'node:assert/strict';
import { filterRecords, optionsFor, summarize, validPosition } from '../../resources/js/shared/gis/data.js';

const records = [
    { id: 1, name: 'Landing site', association: 'Coastal Association', municipality_id: 1, municipality: 'North', barangay_id: 10, barangay: 'Bay', component_id: 1, component: 'Capture', published: true, valid: true, latitude: 0, longitude: 0 },
    { id: 2, name: 'Office', association: 'Coastal Association', municipality_id: 1, municipality: 'North', barangay_id: 10, barangay: 'Bay', component_id: 1, component: 'Capture', published: false, valid: true, latitude: 0, longitude: 0 },
    { id: 3, name: 'Nursery', association: 'Second Association', municipality_id: 2, municipality: 'South', barangay_id: 20, barangay: 'Shore', component_id: 2, component: 'Aquaculture', published: false, valid: false, latitude: null, longitude: null },
];
test('search uses association, municipality, barangay, and site names', () => {
    for (const search of ['coastal', 'NORTH', ' bay ']) assert.equal(filterRecords(records, { search }).length, 2);
    assert.equal(filterRecords(records, { search: 'Nursery' })[0].id, 3);
});
test('combined filters, zero results, and clearing use the same source records', () => {
    assert.deepEqual(filterRecords(records, { municipality: '1', barangay: '10', component: '1', publication: 'unpublished' }).map(r => r.id), [2]);
    assert.equal(filterRecords(records, { municipality: '1', barangay: '20' }).length, 0);
    assert.equal(filterRecords(records, {}).length, 3);
    assert.deepEqual(filterRecords([], {}), []);
});
test('dependent barangay choices remove incompatible options', () => {
    assert.deepEqual(optionsFor(records, 'barangay', '2'), [['20', 'Shore']]);
    assert.equal(optionsFor(records, 'barangay', '999').length, 0);
});
test('counts retain overlapping records and exclude invalid coordinates', () => {
    assert.deepEqual(summarize(records), { mapped: 2, invalid: 1, municipalities: 2, barangays: 2 });
    assert.equal(validPosition(records[0]), true);
    for (const latitude of [null, '', '0', Infinity, NaN, 91, -91]) assert.equal(validPosition({ ...records[0], latitude }), false);
    for (const longitude of [181, -181]) assert.equal(validPosition({ ...records[0], longitude }), false);
    assert.equal(validPosition({ ...records[0], latitude: -90, longitude: 180 }), true);
});
