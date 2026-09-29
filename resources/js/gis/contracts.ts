export type FieldErrors = Record<string, string[]>;
export type SaveReply = { ok: true; id: number; message: string }
    | { ok: false; message: string; errors: FieldErrors; reload: boolean };

export function submissionToken(source: Crypto = globalThis.crypto): string {
    if (typeof source?.randomUUID === 'function') return source.randomUUID();
    // getRandomValues also works on local HTTP demos. Never use Math.random for a receipt.
    const bytes = source.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function coordinateError(value: string, limit: number): string | null {
    if (value.length > 128) return 'Use no more than 128 characters for a coordinate.';
    if (value.trim() === '') return 'Enter a coordinate.';
    // Accept decimal numbers, including zero. Reject empty text, Infinity, and hex values.
    if (!/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim())) return 'Enter a valid number.';
    const exponent = value.trim().split(/e/i)[1];
    if (exponent && Math.abs(Number(exponent)) > 1000) return 'Use a coordinate exponent from -1000 to 1000.';
    const number = Number(value);
    if (!Number.isFinite(number) || number < -limit || number > limit) return `Enter a number from ${-limit} to ${limit}.`;
    return null;
}

function object(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function parseSaveReply(status: number, body: unknown): SaveReply {
    if ((status === 200 || status === 201) && object(body)
        && typeof body.id === 'number' && Number.isSafeInteger(body.id) && body.id > 0 && typeof body.message === 'string') {
        return { ok: true, id: body.id, message: body.message };
    }
    const errors: FieldErrors = {};
    if (status === 422 && object(body) && object(body.errors)) {
        for (const field of ['association_id', 'project_id', 'location_name', 'latitude', 'longitude', 'revision', 'publication', 'submission_token']) {
            const messages = body.errors[field];
            if (Array.isArray(messages) && messages.every(message => typeof message === 'string')) errors[field] = messages;
        }
        if (errors.revision?.length || errors.submission_token?.length) {
            return { ok: false, message: 'Reload GIS Mapping before editing this location again.', errors: {}, reload: true };
        }
        if (errors.publication?.length) {
            return { ok: false, message: 'Correct the location name and coordinates before publishing.', errors, reload: false };
        }
        return { ok: false, message: 'Check the fields below. Your changes have not been saved.', errors, reload: false };
    }
    // Use our own messages, never a server trace or unexpected HTML returned after login expiry.
    const messages: Record<number, string> = {
        401: 'Your session has expired. Reload GIS Mapping and sign in again.',
        403: 'You do not have permission to save this location. Reload GIS Mapping.',
        404: 'This location is no longer available. Reload GIS Mapping.',
        409: 'This location changed after you opened the form. Reload GIS Mapping before editing again.',
        419: 'Your session has expired. Reload GIS Mapping and sign in again.',
    };
    return {
        ok: false,
        message: messages[status] ?? 'The save could not be confirmed. Refresh and check the location before trying again.',
        errors, reload: true,
    };
}

export function parseMapRecords(value: unknown): Record<string, unknown>[] {
    if (!Array.isArray(value)) throw new Error('Invalid GIS records');
    for (const record of value) {
        if (!object(record) || !Number.isSafeInteger(record.id) || Number(record.id) <= 0
            || !Number.isSafeInteger(record.association_id)
            || !['name', 'association', 'municipality', 'barangay', 'component', 'status', 'revision', 'update_url', 'publication_url', 'latitude_text', 'longitude_text', 'created_at', 'updated_at'].every(key => typeof record[key] === 'string')
            || !['valid', 'published', 'archived', 'editable'].every(key => typeof record[key] === 'boolean')
            || !/^[a-f0-9]{32}:[0-9]+$/.test(String(record.revision))) throw new Error('Invalid GIS record');
        for (const key of ['latitude', 'longitude']) {
            if (record[key] !== null && (typeof record[key] !== 'number' || !Number.isFinite(record[key]))) throw new Error('Invalid GIS position');
        }
    }
    return value;
}
