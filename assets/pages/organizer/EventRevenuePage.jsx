import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { CircleDollarSign, Receipt, Ticket, Wallet } from 'lucide-react';
import { getEventRevenue } from '../../api/organizerApi';
import { useToast } from '../../context/ToastContext';
import StatCard from '../../components/common/StatCard';
import Badge from '../../components/common/Badge';

export default function EventRevenuePage() {
    const { id } = useParams();
    const { error: showError } = useToast();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let active = true;

        setLoading(true);
        getEventRevenue(id)
            .then((response) => {
                if (!active) {
                    return;
                }

                setData(response);
            })
            .catch((err) => {
                if (!active) {
                    return;
                }

                showError(err.response?.data?.message ?? 'Could not load event revenue.');
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [id]);

    if (loading) {
        return <div className="card card-body text-sm text-gray-400">Loading…</div>;
    }

    const event = data?.event;
    const tiers = data?.tiers ?? [];

    return (
        <div>
            <nav className="breadcrumb">
                <Link to="/organizer/events" className="breadcrumb-link">My Events</Link>
                <span className="breadcrumb-sep">›</span>
                <span className="breadcrumb-current">Revenue: {event?.name ?? 'Event'}</span>
            </nav>
            <div className="flex items-center gap-3 mb-6">
                <h1 className="page-title mb-0">Revenue: {event?.name ?? 'Event'}</h1>
                {event?.status && <Badge status={event.status} />}
            </div>

            <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))' }}>
                <StatCard
                    icon={CircleDollarSign}
                    label="Gross Revenue"
                    value={`${(data?.grossRevenue ?? 0).toLocaleString()} cr`}
                />
                <StatCard
                    icon={Wallet}
                    label="Net Revenue"
                    value={`${(data?.netRevenue ?? 0).toLocaleString()} cr`}
                    sub="After 1% platform fee"
                />
                <StatCard
                    icon={Receipt}
                    label="System Fee"
                    value={`${(data?.systemFee ?? 0).toLocaleString()} cr`}
                />
                <StatCard
                    icon={Ticket}
                    label="Tickets Sold"
                    value={(data?.ticketsSold ?? 0).toLocaleString()}
                />
            </div>

            <div className="grid gap-6 mb-6" style={{ gridTemplateColumns: 'minmax(0, 1.3fr) minmax(280px, 0.7fr)' }}>
                <div className="card">
                    <div className="card-header">Revenue Breakdown by Tier</div>
                    {tiers.length === 0 ? (
                        <div className="empty-state" style={{ padding: '40px 24px' }}>
                            <div className="empty-state-icon">🎟️</div>
                            <div className="empty-state-title">No confirmed sales yet</div>
                            <div className="empty-state-text">Revenue will appear once bookings for this event are confirmed.</div>
                        </div>
                    ) : (
                        <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tier</th>
                                        <th>Tickets Sold</th>
                                        <th>Gross Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tiers.map((tier) => (
                                        <tr key={tier.id}>
                                            <td className="font-medium text-gray-900">{tier.name}</td>
                                            <td>{tier.ticketsSold ?? 0}</td>
                                            <td>{(tier.grossRevenue ?? 0).toLocaleString()} cr</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="card">
                    <div className="card-header">Event Snapshot</div>
                    <div className="card-body space-y-4">
                        <div>
                            <div className="text-xs uppercase tracking-wide text-gray-400 mb-1">Event Date</div>
                            <div className="font-medium text-gray-900">
                                {event?.startDate
                                    ? new Date(event.startDate).toLocaleString('en-IN', {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })
                                    : '—'}
                            </div>
                        </div>

                        <div>
                            <div className="text-xs uppercase tracking-wide text-gray-400 mb-1">Venue</div>
                            <div className="font-medium text-gray-900">{event?.isOnline ? 'Online Event' : (event?.venueName ?? '—')}</div>
                            {!event?.isOnline && event?.venueAddress && (
                                <div className="text-sm text-gray-500 mt-1">{event.venueAddress}</div>
                            )}
                        </div>

                        <div>
                            <div className="text-xs uppercase tracking-wide text-gray-400 mb-1">Category</div>
                            <div className="font-medium text-gray-900">{event?.category ?? '—'}</div>
                        </div>

                        <div className="flex gap-3 pt-2">
                            <Link to={`/organizer/events/${id}/edit`} className="btn btn-secondary btn-sm">
                                Edit Event
                            </Link>
                            <Link to={`/organizer/events/${id}/bookings`} className="btn btn-ghost btn-sm">
                                View Bookings
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
