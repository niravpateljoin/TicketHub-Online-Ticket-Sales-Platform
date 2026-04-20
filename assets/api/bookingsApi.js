import api from '../hooks/useApi';
import { unwrapEnvelope } from './unwrap';

export async function getBookings(params = {}) {
    const normalized = typeof params === 'number' ? { page: params } : params;
    const response = await api.get('/bookings', { params: normalized });
    return unwrapEnvelope(response);
}

export async function getBooking(id) {
    const response = await api.get(`/bookings/${id}`);
    return unwrapEnvelope(response);
}

export async function downloadTicket(qrToken) {
    const response = await api.get(`/tickets/${qrToken}/download`, {
        responseType: 'blob',
    });
    return response.data; // Blob (PDF)
}

export async function requestRefund(bookingId) {
    const response = await api.post(`/bookings/${bookingId}/refund`);
    return unwrapEnvelope(response);
}
