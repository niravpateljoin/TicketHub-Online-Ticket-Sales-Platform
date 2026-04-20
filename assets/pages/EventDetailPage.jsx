import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { CalendarDays, Clock3, MapPin, UserRound } from 'lucide-react';
import { getEvent, joinWaitlist, leaveWaitlist } from '../api/eventsApi';
import { useAuth } from '../hooks/useAuth';
import { useCart } from '../hooks/useCart';
import { useToast } from '../context/ToastContext';
import Badge from '../components/common/Badge';
import FlashSaleCountdown from '../components/events/FlashSaleCountdown';
import { SkeletonEventDetail } from '../components/common/Skeleton';
import { ROLES } from '../utils/constants';

export default function EventDetailPage() {
    const { slug } = useParams();
    const { user } = useAuth();
    const { addToCart } = useCart();
    const { success, error } = useToast();
    const navigate = useNavigate();

    const [event, setEvent] = useState(null);
    const [loading, setLoading] = useState(true);
    const [addingTier, setAddingTier] = useState(null);
    const [quantities, setQuantities] = useState({});
    const [waitlistState, setWaitlistState] = useState({});  // tierId -> 'none'|'joined'|'loading'

    useEffect(() => {
        setLoading(true);
        getEvent(slug)
            .then((data) => setEvent(data))
            .catch(() => setEvent(null))
            .finally(() => setLoading(false));
    }, [slug]);

    const handleAddToCart = async (tier) => {
        if (!user) {
            navigate('/login');
            return;
        }

        const quantity = quantities[tier.id] ?? 1;
        setAddingTier(tier.id);

        try {
            await addToCart(tier.id, quantity);
            success(`${tier.name} added to cart.`);
        } catch (err) {
            error(err.response?.data?.message ?? 'Could not add this ticket tier to cart.');
        } finally {
            setAddingTier(null);
        }
    };

    const handleWaitlist = async (tier) => {
        if (!user) { navigate('/login'); return; }
        const current = waitlistState[tier.id] ?? 'none';
        setWaitlistState(s => ({ ...s, [tier.id]: 'loading' }));
        try {
            if (current === 'joined') {
                await leaveWaitlist(event.id, tier.id);
                success('Removed from waitlist.');
                setWaitlistState(s => ({ ...s, [tier.id]: 'none' }));
            } else {
                await joinWaitlist(event.id, tier.id);
                success('Added to waitlist! We\'ll email you when seats open up.');
                setWaitlistState(s => ({ ...s, [tier.id]: 'joined' }));
            }
        } catch (err) {
            error(err.response?.data?.message ?? 'Could not update waitlist.');
            setWaitlistState(s => ({ ...s, [tier.id]: current }));
        }
    };

    if (loading) {
        return <SkeletonEventDetail />;
    }

    if (!event) {
        return (
            <div className="card">
                <div className="empty-state">
                    <div className="empty-state-icon">🎟</div>
                    <div className="empty-state-title">Event not found</div>
                    <Link to="/events" className="btn btn-primary">Back to Events</Link>
                </div>
            </div>
        );
    }

    const tiers = event.tiers ?? [];
    const notices = buildEventNotices(event);

    return (
        <div>
            <Link to="/events" className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary mb-4">
                ← Back to Events
            </Link>

            <div style={{ height: '320px', borderRadius: '8px', overflow: 'hidden', marginBottom: '24px', background: '#1A1D23' }}>
                {event.bannerUrl ? (
                    <img src={event.bannerUrl} alt={event.name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                ) : (
                    <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '80px' }}>
                        🎭
                    </div>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2" style={{ marginBottom: '8px' }}>
                <Badge status={event.status ?? 'active'} />
                {event.category && <Badge status="active" label={event.category} />}
            </div>

            <h1 className="font-bold text-gray-900" style={{ fontSize: '28px', lineHeight: 1.2, marginBottom: '16px' }}>{event.name}</h1>

            {notices.map((notice) => (
                <div key={notice.title} className="card" style={{ marginBottom: '16px' }}>
                    <div
                        className="card-body"
                        style={{
                            background: notice.background,
                            border: `1px solid ${notice.border}`,
                            color: notice.color,
                        }}
                    >
                        <div className="font-semibold mb-1">{notice.title}</div>
                        <div className="text-sm">{notice.body}</div>
                    </div>
                </div>
            ))}

            <div className="grid gap-6" style={{ gridTemplateColumns: 'minmax(0, 1fr) 360px', alignItems: 'start' }}>
                <div className="space-y-4">
                    <div className="card">
                        <div className="card-header">Event Information</div>
                        <div className="card-body space-y-3 text-sm text-gray-700">
                            <div className="flex items-start gap-3">
                                <CalendarDays size={18} strokeWidth={1.8} className="text-gray-400 mt-0.5" />
                                <div>
                                    <div className="font-medium text-gray-900">Date & Time</div>
                                    <div>{formatDateTime(event.startDate ?? event.dateTime)}</div>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <MapPin size={18} strokeWidth={1.8} className="text-gray-400 mt-0.5" />
                                <div>
                                    <div className="font-medium text-gray-900">Venue</div>
                                    <div>{event.isOnline ? 'Online Event' : event.venueName}</div>
                                    {!event.isOnline && event.venueAddress && (
                                        <div className="text-gray-500">{event.venueAddress}</div>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <UserRound size={18} strokeWidth={1.8} className="text-gray-400 mt-0.5" />
                                <div>
                                    <div className="font-medium text-gray-900">Organizer</div>
                                    <div>{event.organizerName ?? event.organizer?.name ?? 'Organizer'}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {event.description && (
                        <div className="card">
                            <div className="card-header">About This Event</div>
                            <div className="card-body">
                                <p className="text-sm text-gray-600" style={{ lineHeight: 1.7, whiteSpace: 'pre-wrap' }}>
                                    {event.description}
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <div className="card" style={{ position: 'sticky', top: '80px' }}>
                        <div className="card-header">Ticket Tiers</div>
                        {tiers.length === 0 ? (
                            <div className="card-body text-sm text-gray-500">No ticket tiers are available for this event yet.</div>
                        ) : (
                            <div style={{ padding: '0' }}>
                                {tiers.map((tier, index) => {
                                    const purchaseState = getTierPurchaseState(event, tier);
                                    const quantity = quantities[tier.id] ?? 1;
                                    const maxQuantity = Math.max(1, tier.availableSeats ?? 1);
                                    const canDecrease = quantity > 1;
                                    const canIncrease = quantity < maxQuantity;
                                    const isLast = index === tiers.length - 1;
                                    const isUserAccount = user?.roles?.includes(ROLES.USER);

                                    return (
                                        <div key={tier.id} style={{ padding: '18px 20px', borderBottom: isLast ? 'none' : '1px solid #E2E8F0' }}>
                                            <div className="flex items-start justify-between gap-3 mb-2">
                                                <div>
                                                    <div className="font-semibold text-gray-900">{tier.name}</div>
                                                    <div className="text-xs text-gray-500 mt-1">
                                                        Base: {tier.basePrice?.toLocaleString() ?? 0} cr
                                                        {' · '}
                                                        Final: {tier.finalPrice?.toLocaleString() ?? tier.price?.toLocaleString() ?? 0} cr
                                                    </div>
                                                </div>
                                                <Badge status={purchaseState.badgeStatus} label={purchaseState.badgeLabel} />
                                            </div>

                                            <div className="grid gap-2 text-xs text-gray-500 mb-4">
                                                <div>
                                                    <strong className="text-gray-700">Availability:</strong> {tier.availableSeats ?? 0} seats left
                                                </div>
                                                <div>
                                                    <strong className="text-gray-700">Sale Window:</strong> {describeSaleWindow(tier)}
                                                </div>
                                                <div className="flex items-start gap-2">
                                                    <Clock3 size={13} strokeWidth={1.8} className="mt-0.5 flex-shrink-0" />
                                                    {tier.saleStartsAt && new Date(tier.saleStartsAt) > new Date()
                                                        ? <FlashSaleCountdown saleStartsAt={tier.saleStartsAt} />
                                                        : <span>{purchaseState.message}</span>
                                                    }
                                                </div>
                                            </div>

                                            {purchaseState.purchasable && isUserAccount && (
                                                <div className="ticket-tier-actions">
                                                    <div className="ticket-quantity" aria-label={`Quantity for ${tier.name}`}>
                                                        <button
                                                            type="button"
                                                            className="ticket-quantity__btn"
                                                            onClick={() => setQuantities((current) => ({
                                                                ...current,
                                                                [tier.id]: Math.max(1, (current[tier.id] ?? 1) - 1),
                                                            }))}
                                                            disabled={!canDecrease}
                                                            aria-label={`Decrease quantity for ${tier.name}`}
                                                        >
                                                            −
                                                        </button>
                                                        <span className="ticket-quantity__value">{quantity}</span>
                                                        <button
                                                            type="button"
                                                            className="ticket-quantity__btn"
                                                            onClick={() => setQuantities((current) => ({
                                                                ...current,
                                                                [tier.id]: Math.min(tier.availableSeats ?? 1, (current[tier.id] ?? 1) + 1),
                                                            }))}
                                                            disabled={!canIncrease}
                                                            aria-label={`Increase quantity for ${tier.name}`}
                                                        >
                                                            +
                                                        </button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        className="btn btn-primary btn-sm ticket-add-btn"
                                                        onClick={() => handleAddToCart(tier)}
                                                        disabled={addingTier === tier.id}
                                                    >
                                                        {addingTier === tier.id ? 'Adding…' : 'Add to Cart'}
                                                    </button>
                                                </div>
                                            )}

                                            {purchaseState.purchasable && !user && (
                                                <Link to="/login" className="btn btn-secondary btn-sm btn-full">
                                                    Login to Book
                                                </Link>
                                            )}

                                            {purchaseState.purchasable && user && !isUserAccount && (
                                                <button type="button" className="btn btn-secondary btn-sm btn-full" disabled>
                                                    Only user accounts can book tickets
                                                </button>
                                            )}

                                            {!purchaseState.purchasable && purchaseState.badgeStatus === 'sold_out' && (
                                                <button
                                                    type="button"
                                                    className={`btn btn-sm btn-full ${waitlistState[tier.id] === 'joined' ? 'btn-secondary' : 'btn-primary'}`}
                                                    disabled={waitlistState[tier.id] === 'loading'}
                                                    onClick={() => handleWaitlist(tier)}
                                                    style={{ background: waitlistState[tier.id] === 'joined' ? undefined : '#6366F1' }}
                                                >
                                                    {waitlistState[tier.id] === 'loading'
                                                        ? 'Please wait…'
                                                        : waitlistState[tier.id] === 'joined'
                                                            ? 'Leave Waitlist'
                                                            : 'Join Waitlist'}
                                                </button>
                                            )}

                                            {!purchaseState.purchasable && purchaseState.badgeStatus !== 'sold_out' && (
                                                <button type="button" className="btn btn-secondary btn-sm btn-full" disabled>
                                                    {purchaseState.ctaLabel}
                                                </button>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

function buildEventNotices(event) {
    const notices = [];
    const now = new Date();
    const eventDate = event.startDate ? new Date(event.startDate) : null;

    if (event.status === 'cancelled') {
        notices.push({
            title: 'This event has been cancelled',
            body: 'Bookings for this event have been refunded and new ticket purchases are disabled.',
            background: '#FFF5F5',
            border: '#FEB2B2',
            color: '#C53030',
        });
    }

    if (eventDate && eventDate < now) {
        notices.push({
            title: 'This event has passed',
            body: 'Tickets are no longer available, but event details remain visible for reference.',
            background: '#F7FAFC',
            border: '#CBD5E0',
            color: '#4A5568',
        });
    }

    if (event.status === 'postponed') {
        notices.push({
            title: 'This event has been postponed',
            body: 'Sales are currently paused until the organizer confirms the new schedule.',
            background: '#FFFBEB',
            border: '#F6E05E',
            color: '#B7791F',
        });
    }

    return notices;
}

function getTierPurchaseState(event, tier) {
    const now = new Date();
    const eventDate = new Date(event.startDate ?? event.dateTime);
    const saleStartsAt = tier.saleStartsAt ? new Date(tier.saleStartsAt) : null;
    const saleEndsAt = tier.saleEndsAt ? new Date(tier.saleEndsAt) : null;
    const availableSeats = tier.availableSeats ?? 0;

    if (event.status === 'cancelled') {
        return {
            purchasable: false,
            badgeStatus: 'cancelled',
            badgeLabel: 'Cancelled',
            message: 'This event has been cancelled and cannot accept new bookings.',
            ctaLabel: 'Event Cancelled',
        };
    }

    if (eventDate <= now) {
        return {
            purchasable: false,
            badgeStatus: 'deactivated',
            badgeLabel: 'Closed',
            message: 'This event has already taken place. Tickets are no longer on sale.',
            ctaLabel: 'Event Passed',
        };
    }

    if (event.status === 'postponed') {
        return {
            purchasable: false,
            badgeStatus: 'postponed',
            badgeLabel: 'Postponed',
            message: 'Ticket sales are paused while the organizer updates the schedule.',
            ctaLabel: 'Sales Paused',
        };
    }

    if (event.status !== 'active') {
        return {
            purchasable: false,
            badgeStatus: 'deactivated',
            badgeLabel: 'Unavailable',
            message: 'This event is not currently open for new bookings.',
            ctaLabel: 'Unavailable',
        };
    }

    if (saleStartsAt && saleStartsAt > now) {
        return {
            purchasable: false,
            badgeStatus: 'pending',
            badgeLabel: 'Not Open',
            message: `Sales start ${formatDateTime(saleStartsAt.toISOString())}.`,
            ctaLabel: 'Sale Not Yet Open',
        };
    }

    if (saleEndsAt && saleEndsAt < now) {
        return {
            purchasable: false,
            badgeStatus: 'deactivated',
            badgeLabel: 'Ended',
            message: 'The sale window for this tier has already ended.',
            ctaLabel: 'Sale Ended',
        };
    }

    if (availableSeats <= 0 || tier.status === 'sold_out' || event.status === 'sold_out') {
        return {
            purchasable: false,
            badgeStatus: 'sold_out',
            badgeLabel: 'Sold Out',
            message: 'No seats remain in this tier right now.',
            ctaLabel: 'Sold Out',
        };
    }

    return {
        purchasable: true,
        badgeStatus: 'approved',
        badgeLabel: 'On Sale',
        message: 'This tier is currently available for booking.',
        ctaLabel: 'Add to Cart',
    };
}

function describeSaleWindow(tier) {
    const start = tier.saleStartsAt ? formatDateTime(tier.saleStartsAt) : null;
    const end = tier.saleEndsAt ? formatDateTime(tier.saleEndsAt) : null;

    if (!start && !end) {
        return 'On sale now';
    }

    if (start && end) {
        return `${start} to ${end}`;
    }

    if (start) {
        return `Starts ${start}`;
    }

    return `Ends ${end}`;
}

function formatDateTime(value) {
    if (!value) {
        return 'TBA';
    }

    return new Date(value).toLocaleString('en-IN', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
