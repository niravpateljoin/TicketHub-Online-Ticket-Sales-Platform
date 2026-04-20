import React, { createContext, useState, useEffect, useCallback } from 'react';
import { getToken, setToken, removeToken, decodeToken, isTokenExpired } from '../utils/auth';
import { getMe } from '../api/authApi';

export const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [token, setTokenState] = useState(() => getToken());
    const [loading, setLoading] = useState(true);

    const normalizeUser = useCallback((payload) => {
        if (!payload) return null;

        return {
            id: payload.id,
            name: payload.name ?? null,
            email: payload.email,
            pendingEmail: payload.pendingEmail ?? null,
            roles: payload.roles ?? [],
            creditBalance: payload.creditBalance ?? 0,
            isVerified: payload.isVerified ?? true,
            approvalStatus: payload.approvalStatus ?? null,
        };
    }, []);

    useEffect(() => {
        if (token && !isTokenExpired(token)) {
            const decoded = decodeToken(token);
            if (decoded) {
                setUser(normalizeUser(decoded));
            }

            getMe()
                .then((data) => setUser(normalizeUser(data)))
                .catch(() => {});
        } else if (token) {
            // Token exists but expired — clear it
            removeToken();
            setTokenState(null);
            setUser(null);
        }
        setLoading(false);
    }, [token, normalizeUser]);

    const login = useCallback((newToken, userPayload = null) => {
        setToken(newToken);
        setTokenState(newToken);

        if (userPayload) {
            setUser(normalizeUser(userPayload));
            return;
        }

        const decoded = decodeToken(newToken);
        if (decoded) {
            setUser(normalizeUser(decoded));
        }
    }, [normalizeUser]);

    const logout = useCallback(() => {
        removeToken();
        setTokenState(null);
        setUser(null);
    }, []);

    const hasRole = useCallback((role) => {
        return user?.roles?.includes(role) ?? false;
    }, [user]);

    const setCurrentUser = useCallback((payload) => {
        setUser(normalizeUser(payload));
    }, [normalizeUser]);

    return (
        <AuthContext.Provider value={{ user, token, loading, login, logout, hasRole, setCurrentUser }}>
            {children}
        </AuthContext.Provider>
    );
}
