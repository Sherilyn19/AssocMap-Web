export type FieldErrors = Record<string, string[]>;
export type SaveReply = { ok: true; id: number; message: string }
    | { ok: false; message: string; errors: FieldErrors; reload: boolean };

export function coordinateError(value: string, limit: number): string | null {
    if (value.trim() === '') return 'Enter a coordinate.';
    // Accept decimal numbers, including zero. Reject empty text, Infinity, and hex values.
    if (!/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim())) return 'Enter a valid number.';
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
        for (const field of ['association_id', 'location_name', 'latitude', 'longitude', 'revision']) {
            const messages = body.errors[field];
            if (Array.isArray(messages) && messages.every(message => typeof message === 'string')) errors[field] = messages;
        }
        if (errors.revision?.length) {
            return { ok: false, message: 'Reload GIS Mapping before editing this location again.', errors: {}, reload: true };
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
