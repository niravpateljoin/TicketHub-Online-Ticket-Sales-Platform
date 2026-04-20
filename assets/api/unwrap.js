export function unwrapEnvelope(response) {
    const payload = response?.data;

    if (!payload || typeof payload !== 'object') {
        return payload;
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'data') && payload.meta) {
        const { data, meta, message, ...rest } = payload;
        return {
            ...meta,
            ...rest,
            items: Array.isArray(data) ? data : [],
            message,
        };
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'data')) {
        return payload.data;
    }

    return payload;
}
