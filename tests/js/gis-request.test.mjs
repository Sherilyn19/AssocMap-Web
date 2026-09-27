import test from 'node:test';
import assert from 'node:assert/strict';
import { saveLocation } from '../../resources/js/gis/request.js';

test('writes send CSRF and submission token once and parse a confirmed result', async t => {
    let calls = 0;
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        calls++;
        assert.equal(url, '/admin/gis');
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf');
        assert.equal(JSON.parse(options.body).submission_token, 'receipt');
        return new Response(JSON.stringify({id: 3, message:'Location added.'}), {status:201, headers:{'content-type':'application/json'}});
    });
    assert.equal((await saveLocation('/admin/gis', 'POST', {submission_token:'receipt'}, 'csrf')).ok, true);
    assert.equal(calls, 1);
});

test('network and unreadable responses block resubmission without automatic retries', async t => {
    let calls = 0;
    t.mock.method(globalThis, 'fetch', async () => { calls++; throw new TypeError('Offline'); });
    const result = await saveLocation('/admin/gis', 'POST', {}, 'csrf');
    assert.equal(result.ok, false);
    assert.equal(result.reload, true);
    assert.equal(calls, 1);
});

test('redirected login pages never become a successful mutation', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ redirected:true, status:200,
        headers:new Headers({'content-type':'text/html'}) }));
    const result = await saveLocation('/admin/gis/1/publish', 'PATCH', {}, 'csrf');
    assert.equal(result.ok, false);
    assert.equal(result.reload, true);
    assert.match(result.message, /session has expired/);
});
