import api from '../hooks/useApi';
import { unwrapEnvelope } from './unwrap';

// Stats
export async function getOrganizerStats() {
    const response = await api.get('/organizer/stats');
    return unwrapEnvelope(response);
}

// Events CRUD
export async function getOrganizerEvents(params = {}) {
    const response = await api.get('/organizer/events', { params });
    return unwrapEnvelope(response);
}

export async function getOrganizerEvent(id) {
    const response = await api.get(`/organizer/events/${id}`);
    return unwrapEnvelope(response);
}

export async function createEvent(formData) {
    const response = await api.post('/organizer/events', formData);
    return unwrapEnvelope(response);
}

export async function updateEvent(id, formData) {
    const response = formData instanceof FormData
        ? await api.post(`/organizer/events/${id}`, formData)
        : await api.put(`/organizer/events/${id}`, formData);
    return unwrapEnvelope(response);
}

export async function deleteEvent(id) {
    await api.delete(`/organizer/events/${id}`);
}

export async function updateEventStatus(id, status) {
    const response = await api.patch(`/organizer/events/${id}/status`, { status });
    return unwrapEnvelope(response);
}

export async function cancelEvent(id) {
    const response = await api.post(`/organizer/events/${id}/cancel`);
    return unwrapEnvelope(response);
}

// Tiers
export async function createTier(eventId, tierData) {
    const response = await api.post(`/organizer/events/${eventId}/tiers`, tierData);
    return unwrapEnvelope(response);
}

export async function updateTier(eventId, tierId, tierData) {
    const response = await api.put(`/organizer/events/${eventId}/tiers/${tierId}`, tierData);
    return unwrapEnvelope(response);
}

export async function deleteTier(eventId, tierId) {
    await api.delete(`/organizer/events/${eventId}/tiers/${tierId}`);
}

// Revenue & bookings
export async function getRevenue(params = {}) {
    const response = await api.get('/organizer/revenue', { params });
    return unwrapEnvelope(response);
}

export async function getEventBookings(eventId, params = {}) {
    const response = await api.get(`/organizer/events/${eventId}/bookings`, { params });
    return unwrapEnvelope(response);
}

export async function getEventRevenue(eventId) {
    const response = await api.get(`/organizer/events/${eventId}/revenue`);
    return unwrapEnvelope(response);
}

export async function checkInTicket(qrToken) {
    const response = await api.post('/organizer/checkin', { qrToken });
    return response.data;
}

export async function getCheckInHistory(eventId = null) {
    const params = eventId ? { eventId } : {};
    const response = await api.get('/organizer/checkin/history', { params });
    return unwrapEnvelope(response);
}
