import React, { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useCart } from '../hooks/useCart';
import { useToast } from '../context/ToastContext';

function CountdownTimer({ expiresAt, now }) {
    const diff = Math.max(0, Math.floor((new Date(expiresAt).getTime() - now) / 1000));
    const time = { m: Math.floor(diff / 60), s: diff % 60, total: diff };
    const pad = n => String(n).padStart(2, '0');
    const cls = time.total < 120 ? 'timer-danger' : 'timer-ok';
    return <span className={cls}>{pad(time.m)}:{pad(time.s)}</span>;
}

export default function CartPage() {
    const { items, total, creditBalance, creditsAfterPurchase, sufficient, expiresAt, removeFromCart, fetchCart, loading } = useCart();
    const { error: showError } = useToast();
    const navigate = useNavigate();
    const [removing, setRemoving] = useState(null);
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, []);

    useEffect(() => {
        if (items.length === 0) {
            return undefined;
        }

        const id = window.setInterval(() => {
            fetchCart().catch(() => {});
        }, 30000);

        return () => window.clearInterval(id);
    }, [fetchCart, items.length]);

    const hasExpiredItems = useMemo(
        () => items.some(item => item.expiresAt && new Date(item.expiresAt).getTime() <= now),
        [items, now]
    );

    useEffect(() => {
        if (!hasExpiredItems) {
            return;
        }

        fetchCart().catch(() => {});
    }, [fetchCart, hasExpiredItems]);

    const canCheckout = sufficient && !hasExpiredItems && items.length > 0;

    const handleRemove = async (reservationId) => {
        setRemoving(reservationId);
        try {
            await removeFromCart(reservationId);
        } catch {
            showError('Could not remove item');
        } finally {
            setRemoving(null);
        }
    };

    return (
        <div>
            <h1 className="page-title">My Cart</h1>

            {items.length === 0 ? (
                <div className="card">
                    <div className="empty-state">
                        <div className="empty-state-icon">🛒</div>
                        <div className="empty-state-title">Your cart is empty</div>
                        <div className="empty-state-text">Browse events to add tickets.</div>
                        <Link to="/events" className="btn btn-primary">Browse Events</Link>
                    </div>
                </div>
            ) : (
                <>
                    {expiresAt && (
                        <div className="mb-4 p-3 rounded-btn flex items-center gap-2 text-sm font-medium"
                            style={{ background: '#FFFBEB', border: '1px solid #FAF089', color: '#D69E2E' }}>
                            <span>⚠</span>
                            <span>Reservation expires in <CountdownTimer expiresAt={expiresAt} now={now} /> — complete checkout to secure your tickets</span>
                        </div>
                    )}

                    {hasExpiredItems && (
                        <div className="mb-4 p-3 rounded-btn text-sm font-medium"
                            style={{ background: '#FFF5F5', border: '1px solid #FEB2B2', color: '#E53E3E' }}>
                            One or more reservations expired. Refreshing your cart now.
                        </div>
                    )}

                    <div className="grid gap-6" style={{ gridTemplateColumns: '1fr 320px', alignItems: 'start' }}>
                        <div className="card">
                            <div className="card-header">Cart Items ({items.length})</div>
                            <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Tier</th>
                                            <th>Qty</th>
                                            <th>Price</th>
                                            <th>Expires In</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map(item => (
                                            <tr key={item.reservationId ?? item.id}>
                                                <td>
                                                    <div className="font-medium text-gray-900">{item.eventName ?? '—'}</div>
                                                    <div className="text-xs text-gray-500 mt-1">{item.eventDateTime ? new Date(item.eventDateTime).toLocaleString() : ''}</div>
                                                </td>
                                                <td className="text-gray-600">{item.tierName ?? '—'}</td>
                                                <td>{item.quantity ?? 1}</td>
                                                <td>
                                                    <div className="font-medium">{item.subtotal ?? item.totalCredits ?? item.price ?? 0} cr</div>
                                                    <div className="text-xs text-gray-500 mt-1">
                                                        {item.unitPrice ?? item.price ?? 0} cr each
                                                        {typeof item.systemFee === 'number' ? ` · fee ${item.systemFee} cr` : ''}
                                                    </div>
                                                </td>
                                                <td>
                                                    {item.expiresAt
                                                        ? <CountdownTimer expiresAt={item.expiresAt} now={now} />
                                                        : <span className="text-green-600">✅</span>}
                                                </td>
                                                <td>
                                                    <button
                                                        className="btn btn-danger btn-sm"
                                                        onClick={() => handleRemove(item.reservationId ?? item.id)}
                                                        disabled={removing === (item.reservationId ?? item.id)}
                                                    >
                                                        {removing === (item.reservationId ?? item.id) ? '…' : '✕'}
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="card" style={{ position: 'sticky', top: '80px' }}>
                            <div className="card-header">Order Summary</div>
                            <div className="card-body">
                                <div className="flex justify-between text-sm text-gray-600 mb-2">
                                    <span>Subtotal</span>
                                    <span>{total} credits</span>
                                </div>
                                <div className="border-t border-border my-3" />
                                <div className="flex justify-between font-bold text-gray-900 mb-4">
                                    <span>Total</span>
                                    <span>{total} credits</span>
                                </div>
                                <div className="text-sm space-y-1 mb-4" style={{ color: '#718096' }}>
                                    <div className="flex justify-between">
                                        <span>Your balance</span>
                                        <span>{creditBalance.toLocaleString()} cr</span>
                                    </div>
                                    <div className="flex justify-between" style={{ color: creditsAfterPurchase < 0 ? '#E53E3E' : '#38A169' }}>
                                        <span>After purchase</span>
                                        <span>{creditsAfterPurchase.toLocaleString()} cr</span>
                                    </div>
                                </div>

                                {!sufficient && (
                                    <div className="mb-3 p-2 rounded-btn text-xs"
                                        style={{ background: '#FFF5F5', color: '#E53E3E', border: '1px solid #FEB2B2' }}>
                                        Insufficient credits
                                    </div>
                                )}

                                <button
                                    className="btn btn-primary btn-full"
                                    disabled={!canCheckout}
                                    onClick={() => navigate('/checkout')}
                                >
                                    {loading ? 'Refreshing…' : 'Proceed to Checkout →'}
                                </button>
                            </div>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
