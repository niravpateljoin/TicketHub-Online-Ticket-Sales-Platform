import axios from 'axios';
import { getToken, removeToken } from '../utils/auth';

const api = axios.create({
    baseURL: '/api',
    headers: { 'Content-Type': 'application/json' },
});

// Attach JWT to every request
api.interceptors.request.use((config) => {
    if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
        if (config.headers) {
            delete config.headers['Content-Type'];
        }
    }

    const token = getToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// On 401: clear token and redirect to login ONLY if user had an active session
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && getToken()) {
            removeToken();
            window.location.href = '/login';
        }

        if (error.response?.status === 429) {
            const retryAfter = Number(error.response?.data?.retryAfter ?? error.response?.headers?.['retry-after'] ?? 60);
            error.isRateLimited = true;
            error.retryAfter = Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : 60;
        }

        return Promise.reject(error);
    }
);

export default api;
