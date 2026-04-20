import api from '../hooks/useApi';
import { unwrapEnvelope } from './unwrap';

// Stats
export async function getAdminStats() {
    const response = await api.get('/admin/stats');
    return unwrapEnvelope(response);
}

// Organizer management
export async function getOrganizers(params = {}) {
    const response = await api.get('/admin/organizers', { params });
    return unwrapEnvelope(response);
}

export async function getPendingOrganizers(params = {}) {
    const response = await api.get('/admin/organizers', { params: { ...params, status: 'pending' } });
    return unwrapEnvelope(response);
}

export async function approveOrganizer(id) {
    const response = await api.post(`/admin/organizers/${id}/approve`);
    return unwrapEnvelope(response);
}

export async function rejectOrganizer(id, reason) {
    const response = await api.post(`/admin/organizers/${id}/reject`, { reason });
    return unwrapEnvelope(response);
}

export async function deactivateOrganizer(id) {
    const response = await api.post(`/admin/organizers/${id}/deactivate`);
    return unwrapEnvelope(response);
}

export async function reactivateOrganizer(id) {
    const response = await api.post(`/admin/organizers/${id}/reactivate`);
    return unwrapEnvelope(response);
}

// Administrators
export async function getAdministrators(params = {}) {
    const response = await api.get('/admin/administrators', { params });
    return unwrapEnvelope(response);
}

export async function createAdministrator(payload) {
    const response = await api.post('/admin/administrators', payload);
    return unwrapEnvelope(response);
}

export async function updateAdministrator(id, payload) {
    const response = await api.put(`/admin/administrators/${id}`, payload);
    return unwrapEnvelope(response);
}

export async function resendAdministratorVerification(id) {
    const response = await api.post(`/admin/administrators/${id}/resend-verification`);
    return unwrapEnvelope(response);
}

export async function deleteAdministrator(id) {
    const response = await api.delete(`/admin/administrators/${id}`);
    return unwrapEnvelope(response);
}

// Events
export async function getAdminEvents(params = {}) {
    const response = await api.get('/admin/events', { params });
    return unwrapEnvelope(response);
}

export async function getAdminEvent(id) {
    const response = await api.get(`/admin/events/${id}`);
    return unwrapEnvelope(response);
}

export async function adminUpdateEvent(id, payload) {
    const response = payload instanceof FormData
        ? await api.post(`/admin/events/${id}`, payload)
        : await api.put(`/admin/events/${id}`, payload);
    return unwrapEnvelope(response);
}

export async function adminCancelEvent(id) {
    const response = await api.post(`/admin/events/${id}/cancel`);
    return unwrapEnvelope(response);
}

export async function adminDeleteEvent(id) {
    const response = await api.delete(`/admin/events/${id}`);
    return unwrapEnvelope(response);
}

export async function adminCreateTier(eventId, payload) {
    const response = await api.post(`/admin/events/${eventId}/tiers`, payload);
    return unwrapEnvelope(response);
}

export async function adminUpdateTier(eventId, tierId, payload) {
    const response = await api.put(`/admin/events/${eventId}/tiers/${tierId}`, payload);
    return unwrapEnvelope(response);
}

export async function adminDeleteTier(eventId, tierId) {
    const response = await api.delete(`/admin/events/${eventId}/tiers/${tierId}`);
    return unwrapEnvelope(response);
}

// Users
export async function getUsers(params = {}) {
    const response = await api.get('/admin/users', { params });
    return unwrapEnvelope(response);
}

// Categories
export async function getAdminCategories() {
    const response = await api.get('/admin/categories');
    return unwrapEnvelope(response);
}

export async function createCategory(payload) {
    const response = await api.post('/admin/categories', payload);
    return unwrapEnvelope(response);
}

export async function updateCategory(id, payload) {
    const response = await api.put(`/admin/categories/${id}`, payload);
    return unwrapEnvelope(response);
}

export async function deleteCategory(id) {
    const response = await api.delete(`/admin/categories/${id}`);
    return unwrapEnvelope(response);
}

// Error logs
export async function getErrorLogs(params = {}) {
    const response = await api.get('/admin/error-logs', { params });
    return unwrapEnvelope(response);
}

export async function getRecentErrors(params = {}) {
    const response = await api.get('/admin/error-logs', { params: { ...params, limit: params.limit ?? 5 } });
    return unwrapEnvelope(response);
}

export async function resolveErrorLog(id, note) {
    const response = await api.post(`/admin/error-logs/${id}/resolve`, { note });
    return unwrapEnvelope(response);
}

// Bookings
export async function getAdminBookings(params = {}) {
    const response = await api.get('/admin/bookings', { params });
    return response.data;
}

export async function adminRefundBooking(id) {
    const response = await api.post(`/admin/bookings/${id}/refund`);
    return response?.data?.message;
}
