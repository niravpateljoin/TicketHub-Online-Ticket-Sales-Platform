import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays, CircleDollarSign, Receipt, Ticket } from 'lucide-react';
import { getOrganizerStats, getOrganizerEvents } from '../../api/organizerApi';
import StatCard from '../../components/common/StatCard';
import Badge from '../../components/common/Badge';

export default function OrganizerDashboardPage() {
    const [stats, setStats] = useState(null);
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            getOrganizerStats().catch(() => null),
            getOrganizerEvents({ page: 1, limit: 5 }).catch(() => ({ items: [] })),
        ]).then(([s, e]) => {
            setStats(s);
            setEvents(e?.items ?? e ?? []);
        }).finally(() => setLoading(false));
    }, []);

    return (
        <div>
            <h1 className="page-title">Dashboard</h1>

            <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))' }}>
                <StatCard
                    icon={CalendarDays}
                    label="Total Events"
                    value={stats?.totalEvents ?? '—'}
                    sub={stats ? `${stats.activeEvents ?? 0} active · ${stats.soldOutEvents ?? 0} sold out · ${stats.pastEvents ?? 0} past` : undefined}
                />
                <StatCard
                    icon={Receipt}
                    label="Bookings Received"
                    value={stats?.bookingsReceived ?? '—'}
                    sub={stats ? `${stats.ticketsSold ?? 0} tickets sold` : undefined}
                />
                <StatCard
                    icon={CircleDollarSign}
                    label="Gross Revenue"
                    value={stats?.grossRevenue != null ? `${stats.grossRevenue.toLocaleString()} cr` : '—'}
                    sub={stats ? `Fee: ${(stats.systemFee ?? 0).toLocaleString()} cr` : undefined}
                />
                <StatCard
                    icon={Ticket}
                    label="Net Revenue"
                    value={stats?.netRevenue != null ? `${stats.netRevenue.toLocaleString()} cr` : '—'}
                    sub="After 1% platform fee"
                />
            </div>

            <div className="card mb-6">
                <div className="card-header">Performance Snapshot</div>
                <div className="card-body grid gap-4 md:grid-cols-3">
                    <div className="rounded-btn border p-4">
                        <div className="text-xs uppercase tracking-wide text-gray-400 mb-1">Active Events</div>
                        <div className="text-2xl font-bold text-gray-900">{stats?.activeEvents ?? 0}</div>
                        <div className="text-sm text-gray-500 mt-2">Events currently open for discovery and sales.</div>
                    </div>
                    <div className="rounded-btn border p-4">
                        <div className="text-xs uppercase tracking-wide text-gray-400 mb-1">Sold Out</div>
                        <div className="text-2xl font-bold text-gray-900">{stats?.soldOutEvents ?? 0}</div>
                        <div className="text-sm text-gray-500 mt-2">Events manually or automatically closed after inventory fills.</div>
                    </div>
                    <div className="rounded-btn border p-4">
                        <div className="text-xs uppercase tracking-wide text-gray-400 mb-1">Past Events</div>
                        <div className="text-2xl font-bold text-gray-900">{stats?.pastEvents ?? 0}</div>
                        <div className="text-sm text-gray-500 mt-2">Completed events retained for reporting and payouts.</div>
                    </div>
                </div>
            </div>

            <div className="card">
                <div className="card-header">
                    My Events
                    <Link to="/organizer/events" className="btn btn-ghost btn-sm">View all →</Link>
                </div>
                {loading ? (
                    <div className="card-body text-sm text-gray-400">Loading…</div>
                ) : events.length === 0 ? (
                    <div className="empty-state" style={{ padding: '40px 24px' }}>
                        <div className="empty-state-icon">🎭</div>
                        <div className="empty-state-title" style={{ fontSize: '16px' }}>No events yet</div>
                        <Link to="/organizer/events/new" className="btn btn-primary btn-sm">Create Event</Link>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Date</th>
                                    <th>Tickets</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.map(ev => (
                                    <tr key={ev.id}>
                                        <td className="font-medium">{ev.name}</td>
                                        <td>{ev.startDate ? new Date(ev.startDate).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}</td>
                                        <td>{ev.soldTickets ?? 0}/{ev.totalSeats ?? '∞'}</td>
                                        <td><Badge status={ev.status ?? 'draft'} /></td>
                                        <td>
                                            <div className="flex gap-2">
                                                <Link to={`/organizer/events/${ev.id}/edit`} className="btn btn-secondary btn-sm">Edit</Link>
                                                <Link to={`/organizer/events/${ev.id}/bookings`} className="btn btn-ghost btn-sm">Bookings</Link>
                                                <Link to={`/organizer/events/${ev.id}/revenue`} className="btn btn-ghost btn-sm">Revenue</Link>
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
