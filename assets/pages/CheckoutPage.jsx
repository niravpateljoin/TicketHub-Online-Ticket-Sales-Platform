import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCart } from '../hooks/useCart';
import { useAuth } from '../hooks/useAuth';
import { useToast } from '../context/ToastContext';
import { confirmCheckout, getCheckoutSummary } from '../api/checkoutApi';
import { SkeletonCheckout } from '../components/common/Skeleton';

function CountdownTimer({ expiresAt, now }) {
    const diff = Math.max(0, Math.floor((new Date(expiresAt).getTime() - now) / 1000));
    const minutes = Math.floor(diff / 60);
    const seconds = diff % 60;
    const className = diff < 120 ? 'timer-danger' : 'timer-ok';

    return <span className={className}>{String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}</span>;
}

export default function CheckoutPage() {
    const { clearCart, fetchCart } = useCart();
    const { user, setCurrentUser } = useAuth();
    const { success, error: showError } = useToast();
    const navigate = useNavigate();
    const [summary, setSummary] = useState(null);
    const [loadingSummary, setLoadingSummary] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [now, setNow] = useState(() => Date.now());
    const [rateLimitUntil, setRateLimitUntil] = useState(null);

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, []);

    useEffect(() => {
        let active = true;

        setLoadingSummary(true);
        getCheckoutSummary()
            .then((data) => {
                if (!active) {
                    return;
                }

                setSummary(data);
            })
            .catch((err) => {
                if (!active) {
                    return;
                }

                showError(err.response?.data?.message ?? 'Could not load checkout summary.');
                navigate('/cart');
            })
            .finally(() => {
                if (active) {
                    setLoadingSummary(false);
                }
            });

        return () => {
            active = false;
        };
    }, [navigate, showError]);

    const items = summary?.items ?? [];
    const total = summary?.total ?? 0;
    const balance = summary?.creditBalance ?? user?.creditBalance ?? 0;
    const afterPurchase = summary?.creditsAfterPurchase ?? (balance - total);
    const rateLimitRemaining = rateLimitUntil ? Math.max(0, Math.ceil((rateLimitUntil - now) / 1000)) : 0;
    const hasExpiredItems = useMemo(
        () => items.some(item => item.expiresAt && new Date(item.expiresAt).getTime() <= now),
        [items, now]
    );

    useEffect(() => {
        if (!hasExpiredItems) {
            return;
        }

        fetchCart().catch(() => {});
        showError('One or more reservations expired. Please review your cart again.');
        navigate('/cart');
    }, [fetchCart, hasExpiredItems, navigate, showError]);

    const handleConfirm = async () => {
        if (!summary?.idempotencyKey) {
            showError('Checkout session is not ready yet.');
            return;
        }

        setProcessing(true);
        try {
            const data = await confirmCheckout(summary.idempotencyKey);
            setRateLimitUntil(null);
            const nextCreditBalance = typeof data.newCreditBalance === 'number'
                ? data.newCreditBalance
                : user?.creditBalance ?? 0;

            clearCart({
                items: [],
                total: 0,
                creditBalance: nextCreditBalance,
                creditsAfterPurchase: nextCreditBalance,
                sufficient: true,
                expiresAt: null,
            });

            if (user) {
                setCurrentUser({
                    ...user,
                    creditBalance: nextCreditBalance,
                });
            }

            success(data.alreadyProcessed ? 'Checkout was already completed.' : 'Booking confirmed! Tickets are on their way.');
            navigate(`/checkout/success/${data.bookingId ?? data.id}`, {
                state: {
                    newCreditBalance: nextCreditBalance,
                    totalCredits: data.totalCredits ?? total,
                },
            });
        } catch (err) {
            if (err.isRateLimited) {
                setRateLimitUntil(Date.now() + (err.retryAfter * 1000));
                showError(`Too many checkout attempts. Try again in ${err.retryAfter}s.`);
            } else {
                showError(err.response?.data?.message ?? 'Checkout failed. Please try again.');
            }
            fetchCart().catch(() => {});
        } finally {
            setProcessing(false);
        }
    };

    if (loadingSummary) {
        return <SkeletonCheckout />;
    }

    return (
        <div>
            <h1 className="page-title">Confirm Purchase</h1>

            {summary?.expiresAt && (
                <div className="mb-4 p-3 rounded-btn flex items-center gap-2 text-sm font-medium"
                    style={{ background: '#FFFBEB', border: '1px solid #FAF089', color: '#D69E2E' }}>
                    <span>⚠</span>
                    <span>Confirm within <CountdownTimer expiresAt={summary.expiresAt} now={now} /> to keep these reservations.</span>
                </div>
            )}

            {rateLimitRemaining > 0 && (
                <div className="mb-4 p-3 rounded-btn flex items-center gap-2 text-sm font-medium"
                    style={{ background: '#FFF5F5', border: '1px solid #FEB2B2', color: '#C53030' }}>
                    <span>⏱</span>
                    <span>Too many checkout attempts. Please wait {rateLimitRemaining}s before trying again.</span>
                </div>
            )}

            <div className="grid gap-6" style={{ gridTemplateColumns: '1fr 320px', alignItems: 'start' }}>
                <div className="card">
                    <div className="card-header">Order Review</div>
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Tier</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map(item => (
                                    <tr key={item.reservationId ?? item.id}>
                                        <td>
                                            <div className="font-medium">{item.eventName ?? '—'}</div>
                                            <div className="text-xs text-gray-500 mt-1">{item.eventDateTime ? new Date(item.eventDateTime).toLocaleString() : ''}</div>
                                        </td>
                                        <td>
                                            <div>{item.tierName ?? '—'}</div>
                                            <div className="text-xs text-gray-500 mt-1">{item.unitPrice ?? item.price ?? 0} cr each</div>
                                        </td>
                                        <td>{item.quantity ?? 1}</td>
                                        <td className="font-medium">{item.subtotal ?? item.totalCredits ?? item.price ?? 0} cr</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="card" style={{ position: 'sticky', top: '80px' }}>
                    <div className="card-header">Payment Summary</div>
                    <div className="card-body">
                        {items.map(item => (
                            <div key={item.reservationId ?? item.id} className="flex justify-between text-sm text-gray-600 mb-2">
                                <span>{item.quantity}× {item.tierName}</span>
                                <span>{item.subtotal ?? item.totalCredits ?? item.price ?? 0} cr</span>
                            </div>
                        ))}

                        <div className="border-t border-border my-3" />

                        <div className="flex justify-between font-bold text-gray-900 mb-4">
                            <span>Total</span>
                            <span>{total} credits</span>
                        </div>

                        <div className="text-sm space-y-1 mb-5" style={{ color: '#718096' }}>
                            <div className="flex justify-between">
                                <span>Your Balance</span>
                                <span>{balance.toLocaleString()} cr</span>
                            </div>
                            <div className="flex justify-between font-medium"
                                style={{ color: afterPurchase < 0 ? '#E53E3E' : '#38A169' }}>
                                <span>After Purchase</span>
                                <span>{afterPurchase.toLocaleString()} cr</span>
                            </div>
                        </div>

                        <button
                            className="btn btn-primary btn-full"
                            onClick={handleConfirm}
                            disabled={processing || afterPurchase < 0 || hasExpiredItems || rateLimitRemaining > 0}
                        >
                            {processing ? 'Processing…' : 'Confirm Purchase'}
                        </button>

                        <div className="text-center mt-3 text-xs text-gray-400">
                            🔒 Secured checkout
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
