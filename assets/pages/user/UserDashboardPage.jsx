import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays, CreditCard, Ticket } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { getBookings } from '../../api/bookingsApi';
import StatCard from '../../components/common/StatCard';
import Badge from '../../components/common/Badge';
import api from '../../hooks/useApi';
import { downloadBlobResponse } from '../../utils/download';
import { SkeletonGroup, SkeletonStatCard, SkeletonTableRows } from '../../components/common/Skeleton';

export default function UserDashboardPage() {
    const { user } = useAuth();
    const [bookings, setBookings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [downloading, setDownloading] = useState(null);

    useEffect(() => {
        getBookings({ page: 1, perPage: 50 })
            .then((data) => setBookings(data.items ?? []))
            .catch(() => setBookings([]))
            .finally(() => setLoading(false));
    }, []);

    const upcoming = [...bookings]
        .filter((booking) => {
            const status = booking.status?.toLowerCase();
            return status === 'confirmed' && booking.event?.startDate && new Date(booking.event.startDate) > new Date();
        })
        .sort((a, b) => new Date(a.event.startDate) - new Date(b.event.startDate));

    const upcomingTickets = upcoming.reduce((sum, booking) => (
        sum + (booking.items ?? []).reduce((itemSum, item) => itemSum + (item.quantity ?? 0), 0)
    ), 0);

    const handleDownload = async (bookingId) => {
        setDownloading(bookingId);
        try {
            const response = await api.get(`/bookings/${bookingId}/ticket`, { responseType: 'blob' });
            downloadBlobResponse(response, `booking-${bookingId}-ticket.pdf`);
        } catch {
            alert('Could not download ticket. Please try again.');
        } finally {
            setDownloading(null);
        }
    };

    if (loading) {
        return (
            <div>
                <h1 className="page-title">My Dashboard</h1>
                <SkeletonGroup>
                    <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
                        <SkeletonStatCard />
                        <SkeletonStatCard />
                        <SkeletonStatCard />
                    </div>
                    <div className="card">
                        <div className="card-header" style={{ borderBottom: '1px solid #F1F5F9' }}>
                            <div style={{ height: 14, width: 130, background: '#E2E8F0', borderRadius: 4 }} />
                        </div>
                        <div className="table-wrapper" style={{ border: 'none', borderRadius: 0 }}>
                            <table>
                                <tbody><SkeletonTableRows cols={6} rows={3} /></tbody>
                            </table>
                        </div>
                    </div>
                </SkeletonGroup>
            </div>
        );
    }

    return (
        <div>
            <h1 className="page-title">My Dashboard</h1>

            <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
                <StatCard
                    icon={CreditCard}
                    label="Credit Balance"
                    value={`${(user?.creditBalance ?? 0).toLocaleString()} cr`}
                    sub="Available for future bookings"
                />
                <StatCard
                    icon={CalendarDays}
                    label="Upcoming Events"
                    value={upcoming.length}
                    sub={upcoming.length > 0 ? `Next: ${upcoming[0].event?.name ?? 'Upcoming event'}` : 'Browse events to book your first ticket'}
                />
                <StatCard
                    icon={Ticket}
                    label="Tickets Booked"
                    value={upcomingTickets}
                    sub={`${bookings.length} total bookings`}
                />
            </div>

            <div className="card">
                <div className="card-header">
                    Upcoming Events
                    <Link to="/user/bookings" className="btn btn-ghost btn-sm">View all →</Link>
                </div>
                {upcoming.length === 0 ? (
                    <div className="empty-state" style={{ padding: '40px 24px' }}>
                        <div className="empty-state-icon">📋</div>
                        <div className="empty-state-title" style={{ fontSize: '16px' }}>No upcoming events</div>
                        <div className="empty-state-text">Browse the catalog and book a future event to see it here.</div>
                        <Link to="/events" className="btn btn-primary btn-sm">Browse Events</Link>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Tickets</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {upcoming.map((booking) => (
                                    <tr key={booking.id}>
                                        <td>
                                            <div className="font-medium text-gray-900">{booking.event?.name ?? '—'}</div>
                                            <div className="text-xs text-gray-500">{booking.items?.map((item) => `${item.quantity}× ${item.tierName}`).join(', ') ?? '—'}</div>
                                        </td>
                                        <td>
                                            {booking.event?.startDate
                                                ? new Date(booking.event.startDate).toLocaleString('en-IN', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })
                                                : '—'}
                                        </td>
                                        <td>{booking.event?.isOnline ? 'Online Event' : (booking.event?.venueName ?? '—')}</td>
                                        <td>{(booking.items ?? []).reduce((sum, item) => sum + (item.quantity ?? 0), 0)}</td>
                                        <td><Badge status={booking.status} /></td>
                                        <td>
                                            <div className="flex gap-2">
                                                <Link to={`/events/${booking.event?.slug ?? booking.event?.id}`} className="btn btn-ghost btn-sm">
                                                    View Event
                                                </Link>
                                                {booking.status === 'confirmed' ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDownload(booking.id)}
                                                        disabled={downloading === booking.id}
                                                        className="btn btn-secondary btn-sm"
                                                    >
                                                        {downloading === booking.id ? 'Downloading…' : 'Download Ticket'}
                                                    </button>
                                                ) : '—'}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
