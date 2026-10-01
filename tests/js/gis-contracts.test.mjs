import test from 'node:test';
import assert from 'node:assert/strict';
import { coordinateError, parseSaveReply, parseMapRecords, submissionToken } from '../../resources/js/shared/gis/contracts.ts';

test('submission receipts use secure UUIDs with or without randomUUID', () => {
    assert.match(submissionToken(), /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
    const source = { getRandomValues: bytes => crypto.getRandomValues(bytes) };
    const first = submissionToken(source);
    assert.match(first, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
    assert.notEqual(first, submissionToken(source));
    assert.throws(() => submissionToken({}));
});

test('coordinates accept zero and boundaries but reject unsafe values', () => {
    for (const value of ['0', '-90', '90', '12.345', '1e1']) assert.equal(coordinateError(value, 90), null);
    for (const value of ['', ' ', 'NaN', 'Infinity', '1e999', '0x10', '91', '-91', '0.'.padEnd(129, '0'), '1e-100000']) assert.notEqual(coordinateError(value, 90), null);
    assert.equal(coordinateError('180', 180), null);
});

test('map payload rejects invalid structure instead of failing during rendering', () => {
    assert.deepEqual(parseMapRecords([]), []);
    for (const payload of [null, {}, [null], [{id: 1}], '[]']) assert.throws(() => parseMapRecords(payload));
    const record = { id:1, association_id:1, name:'Site', association:'Association', municipality:'Area', barangay:'Barangay',
        component:'Program', status:'Active', revision:'a'.repeat(32)+':1', update_url:'/admin/gis/1', publication_url:'/admin/gis/1/publish',
        latitude_text:'10.123456789123', longitude_text:'123', created_at:'', updated_at:'',
        valid:true, published:false, archived:false, editable:true, latitude:10.123456789123, longitude:123 };
    assert.equal(parseMapRecords([record])[0].latitude_text, '10.123456789123');
    assert.throws(() => parseMapRecords([{...record, latitude:Infinity}]));
    assert.throws(() => parseMapRecords([{...record, published:'false'}]));
});
test('success requires a real success response with a valid identifier', () => {
    assert.equal(parseSaveReply(201, { id: 12, message: 'Location added.' }).ok, true);
    for (const body of [null, '<html>Login</html>', {id:'12'}, {id:-1,message:'OK'}, {id:1.5,message:'OK'}]) {
        const result = parseSaveReply(200, body);
        assert.equal(result.ok, false); assert.equal(result.reload, true);
    }
});
test('field errors are checked and unsafe server messages never enter the UI', () => {
    const result = parseSaveReply(422, { errors: { latitude: ['Invalid latitude'], geom: ['ignored'] }, message: 'raw trace' });
    assert.deepEqual(result.errors, {latitude: ['Invalid latitude']});
    assert.equal(result.reload, false);
    assert.equal(parseSaveReply(422, { errors: { revision: ['Invalid revision'] } }).reload, true);
    for (const status of [401,403,404,409,419,500,503]) {
        const reply = parseSaveReply(status, {message: 'SQLSTATE private connection'});
        assert.equal(reply.reload, true); assert.equal(reply.message.includes('SQLSTATE'), false);
    }
});
