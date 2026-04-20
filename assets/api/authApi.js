import api from '../hooks/useApi';
import { unwrapEnvelope } from './unwrap';

export async function login(email, password) {
    const { data } = await api.post('/auth/login', { email, password });
    return data; // { token }
}

export async function getMe() {
    const response = await api.get('/auth/me');
    return unwrapEnvelope(response);
}

export async function updateMyProfile(payload) {
    const response = await api.put('/auth/me', payload);
    return {
        ...unwrapEnvelope(response),
        message: response?.data?.message,
    };
}

export async function registerUser(formData) {
    const response = await api.post('/auth/register', formData);
    return unwrapEnvelope(response);
}

export async function registerOrganizer(formData) {
    const response = await api.post('/auth/register/organizer', formData);
    return unwrapEnvelope(response);
}

export async function verifyEmail(token) {
    const response = await api.get('/auth/verify-email', { params: { token } });
    return {
        ...unwrapEnvelope(response),
        message: response?.data?.message,
    };
}

export async function forgotPassword(email) {
    const response = await api.post('/auth/forgot-password', { email });
    return response?.data?.message;
}

export async function resetPassword(token, password) {
    const response = await api.post('/auth/reset-password', { token, password });
    return response?.data?.message;
}
