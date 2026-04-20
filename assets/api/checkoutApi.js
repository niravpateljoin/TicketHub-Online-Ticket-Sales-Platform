import api from '../hooks/useApi';
import { unwrapEnvelope } from './unwrap';

function createIdempotencyKey() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export async function getCheckoutSummary() {
    const response = await api.get('/checkout');
    return unwrapEnvelope(response);
}

export async function confirmCheckout(idempotencyKey = createIdempotencyKey()) {
    const response = await api.post('/checkout/confirm', { idempotencyKey }, {
        headers: { 'Idempotency-Key': idempotencyKey },
    });
    return unwrapEnvelope(response); // { bookingId, ... }
}

export async function checkout(idempotencyKey = createIdempotencyKey()) {
    return confirmCheckout(idempotencyKey);
}

export async function getOrder(bookingId) {
    const response = await api.get(`/bookings/${bookingId}`);
    return unwrapEnvelope(response);
}
