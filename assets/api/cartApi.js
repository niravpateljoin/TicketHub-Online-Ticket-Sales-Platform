import api from '../hooks/useApi';
import { unwrapEnvelope } from './unwrap';

export async function getCart() {
    const response = await api.get('/cart');
    return unwrapEnvelope(response); // { items: [...], total: 0 }
}

export async function addToCart(tierId, quantity) {
    const response = await api.post('/cart', { tierId, quantity });
    return unwrapEnvelope(response); // updated cart
}

export async function removeFromCart(reservationId) {
    const response = await api.delete(`/cart/${reservationId}`);
    return unwrapEnvelope(response); // updated cart
}

export async function clearCart() {
    const response = await api.delete('/cart');
    return unwrapEnvelope(response);
}
