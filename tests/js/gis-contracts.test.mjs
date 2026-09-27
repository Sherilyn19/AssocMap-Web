import test from 'node:test';
import assert from 'node:assert/strict';
import { coordinateError, parseSaveReply } from '../../resources/js/gis/contracts.ts';

test('coordinates accept zero and boundaries but reject unsafe values', () => {
    for (const value of ['0', '-90', '90', '12.345', '1e1']) assert.equal(coordinateError(value, 90), null);
    for (const value of ['', ' ', 'NaN', 'Infinity', '1e999', '0x10', '91', '-91']) assert.notEqual(coordinateError(value, 90), null);
    assert.equal(coordinateError('180', 180), null);
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
