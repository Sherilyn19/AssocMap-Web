import { parseSaveReply } from './contracts.ts';

// A timeout can happen after the commit. Never retry a write automatically.
export async function saveLocation(url, method, payload, token) {
    const abort = new AbortController();
    const timeout = setTimeout(() => abort.abort(), 25000);
    try {
        const response = await fetch(url, {
            method, credentials: 'same-origin', signal: abort.signal,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(payload),
        });
        const body = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        return parseSaveReply(response.redirected ? 401 : response.status, body);
    } catch {
        return parseSaveReply(0, null);
    } finally {
        clearTimeout(timeout);
    }
}

export function reloadSaved(message) {
    try { sessionStorage.setItem('gis-save-message', message); } catch { /* Saving does not depend on browser storage. */ }
    window.location.reload();
}
