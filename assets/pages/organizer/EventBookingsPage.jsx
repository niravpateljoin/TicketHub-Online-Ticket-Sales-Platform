import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getEventBookings } from '../../api/organizerApi';
import Badge from '../../components/common/Badge';
import Pagination from '../../components/common/Pagination';

export default function EventBookingsPage() {
    const { id } = useParams();
    const [bookings, setBookings] = useState([]);
    const [eventName, setEventName] = useState('');
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    useEffect(() => {
        setLoading(true);
        getEventBookings(id, { page })
            .then(data => {
                setBookings(data.items ?? data.bookings ?? []);
                setTotalPages(data.totalPages ?? 1);
                setEventName(data.eventName ?? '');
            })
            .catch(() => setBookings([]))
            .finally(() => setLoading(false));
    }, [id, page]);

    return (
        <div>
            <nav className="breadcrumb">
                <Link to="/organizer/events" className="breadcrumb-link">My Events</Link>
                <span className="breadcrumb-sep">›</span>
                <span className="breadcrumb-current">Bookings: {eventName || '…'}</span>
            </nav>
            <h1 className="page-title">Bookings: {eventName || '…'}</h1>

            <div className="card">
                {loading ? (
                    <div className="card-body text-sm text-gray-400">Loading…</div>
                ) : bookings.length === 0 ? (
                    <div className="empty-state">
                        <div className="empty-state-icon">📋</div>
                        <div className="empty-state-title">No bookings yet</div>
                        <div className="empty-state-text">Bookings will appear here once users start purchasing tickets.</div>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Booked At</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {bookings.map(b => (
                                    <tr key={b.id}>
                                        <td className="text-gray-400">{b.id}</td>
                                        <td>
                                            <div className="font-medium">{b.userEmail ?? `User #${b.userId}`}</div>
                                        </td>
                                        <td>
                                            {b.items?.map(i => `${i.quantity}× ${i.tierName}`).join(', ') ?? '—'}
                                        </td>
                                        <td className="font-medium">{b.totalCredits ?? 0} cr</td>
                                        <td>
                                            {b.createdAt
                                                ? new Date(b.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
                                                : '—'}
                                        </td>
                                        <td><Badge status={b.status} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
        </div>
    );
}
