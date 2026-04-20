import api from '../hooks/useApi';
import { unwrapEnvelope } from './unwrap';

export async function getEvents(params = {}) {
    const response = await api.get('/events', { params });
    return unwrapEnvelope(response);
}

export async function getEventFilterOptions() {
    const response = await api.get('/events/filter-options');
    return unwrapEnvelope(response);
}

export async function getEvent(identifier) {
    const response = await api.get(`/events/${identifier}`);
    return unwrapEnvelope(response);
}

export async function joinWaitlist(eventId, tierId) {
    const response = await api.post(`/events/${eventId}/tiers/${tierId}/waitlist`);
    return response.data;
}

export async function leaveWaitlist(eventId, tierId) {
    const response = await api.delete(`/events/${eventId}/tiers/${tierId}/waitlist`);
    return response.data;
}

export async function getWaitlistStatus(eventId, tierId) {
    const response = await api.get(`/events/${eventId}/tiers/${tierId}/waitlist/status`);
    return unwrapEnvelope(response);
}

export async function getMyWaitlist() {
    const response = await api.get('/user/waitlist');
    return unwrapEnvelope(response);
}
