import React, { createContext, useState, useEffect, useCallback, useContext } from 'react';
import { AuthContext } from './AuthContext';
import { getCart, addToCart as apiAddToCart, removeFromCart as apiRemoveFromCart, clearCart as apiClearCart } from '../api/cartApi';

export const CartContext = createContext(null);

export function CartProvider({ children }) {
    const { user } = useContext(AuthContext);
    const [items, setItems] = useState([]);
    const [total, setTotal] = useState(0);
    const [creditBalance, setCreditBalance] = useState(0);
    const [creditsAfterPurchase, setCreditsAfterPurchase] = useState(0);
    const [sufficient, setSufficient] = useState(true);
    const [expiresAt, setExpiresAt] = useState(null);
    const [loading, setLoading] = useState(false);

    const applyCart = useCallback((data = {}) => {
        setItems(data.items ?? []);
        setTotal(data.total ?? 0);
        setCreditBalance(data.creditBalance ?? user?.creditBalance ?? 0);
        setCreditsAfterPurchase(data.creditsAfterPurchase ?? ((data.creditBalance ?? user?.creditBalance ?? 0) - (data.total ?? 0)));
        setSufficient(data.sufficient ?? true);
        setExpiresAt(data.expiresAt ?? null);
        return data;
    }, [user]);

    const fetchCart = useCallback(async () => {
        if (!user) return null;
        setLoading(true);
        try {
            const data = await getCart();
            return applyCart(data);
        } catch {
            // silently ignore — cart may not exist yet
            return null;
        } finally {
            setLoading(false);
        }
    }, [applyCart, user]);

    useEffect(() => {
        if (user) {
            fetchCart();
        } else {
            applyCart({ items: [], total: 0, creditBalance: 0, creditsAfterPurchase: 0, sufficient: true, expiresAt: null });
        }
    }, [applyCart, user, fetchCart]);

    const addToCart = useCallback(async (tierId, quantity) => {
        const data = await apiAddToCart(tierId, quantity);
        return applyCart(data);
    }, [applyCart]);

    const removeFromCart = useCallback(async (reservationId) => {
        const data = await apiRemoveFromCart(reservationId);
        return applyCart(data);
    }, [applyCart]);

    const clearCart = useCallback((payload = null) => {
        applyCart(payload ?? { items: [], total: 0, creditBalance: user?.creditBalance ?? 0, creditsAfterPurchase: user?.creditBalance ?? 0, sufficient: true, expiresAt: null });
    }, [applyCart, user]);

    const clearCartServer = useCallback(async () => {
        const data = await apiClearCart();
        return applyCart(data);
    }, [applyCart]);

    const itemCount = items.reduce((sum, item) => sum + (item.quantity ?? 1), 0);

    return (
        <CartContext.Provider
            value={{
                items,
                total,
                itemCount,
                creditBalance,
                creditsAfterPurchase,
                sufficient,
                expiresAt,
                loading,
                addToCart,
                removeFromCart,
                clearCart,
                clearCartServer,
                fetchCart,
            }}
        >
            {children}
        </CartContext.Provider>
    );
}
