import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { getOrganizerEvents, cancelEvent } from '../../api/organizerApi';
import Badge from '../../components/common/Badge';
import Pagination from '../../components/common/Pagination';
import ConfirmModal from '../../components/common/ConfirmModal';
import { useToast } from '../../context/ToastContext';

export default function EventListPage() {
    const { success, error: showError } = useToast();
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [cancelTarget, setCancelTarget] = useState(null);

    const fetchEvents = () => {
        setLoading(true);
        getOrganizerEvents({ page })
            .then(data => {
                setEvents(data.items ?? data ?? []);
                setTotalPages(data.totalPages ?? 1);
            })
            .catch(() => setEvents([]))
            .finally(() => setLoading(false));
    };

    useEffect(fetchEvents, [page]);

    const handleCancel = async () => {
        try {
            await cancelEvent(cancelTarget.id);
            success('Your event has been cancelled and all ticket holders have been refunded.');
            setCancelTarget(null);
            fetchEvents();
        } catch (err) {
            showError(err.response?.data?.message ?? 'Could not cancel event.');
        }
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <h1 className="page-title mb-0">My Events</h1>
                <Link to="/organizer/events/new" className="btn btn-primary">
                    + Create Event
                </Link>
            </div>

            <div className="card">
                {loading ? (
                    <div className="card-body text-sm text-gray-400">Loading…</div>
                ) : events.length === 0 ? (
                    <div className="empty-state">
                        <div className="empty-state-icon">🎭</div>
                        <div className="empty-state-title">No events yet</div>
                        <div className="empty-state-text">Create your first event to get started.</div>
                        <Link to="/organizer/events/new" className="btn btn-primary">Create Event</Link>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Date</th>
                                    <th>Tickets Sold</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.map(ev => (
                                    <tr key={ev.id}>
                                        <td>
                                            <div className="font-medium text-gray-900">{ev.name}</div>
                                            {ev.venueName && <div className="text-xs text-gray-400">{ev.venueName}</div>}
                                        </td>
                                        <td>
                                            {ev.startDate
                                                ? new Date(ev.startDate).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
                                                : '—'}
                                        </td>
                                        <td>{ev.soldTickets ?? 0} / {ev.totalSeats ?? '∞'}</td>
                                        <td><Badge status={ev.status ?? 'draft'} /></td>
                                        <td>
                                            <div className="flex gap-2">
                                                <Link to={`/organizer/events/${ev.id}/edit`} className="btn btn-secondary btn-sm">Edit</Link>
                                                <Link to={`/organizer/events/${ev.id}/bookings`} className="btn btn-ghost btn-sm">Bookings</Link>
                                                <Link to={`/organizer/events/${ev.id}/revenue`} className="btn btn-ghost btn-sm">Revenue</Link>
                                                {ev.status !== 'cancelled' && (
                                                    <button
                                                        className="btn btn-danger btn-sm"
                                                        onClick={() => setCancelTarget(ev)}
                                                    >
                                                        Cancel
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />

            <ConfirmModal
                open={!!cancelTarget}
                title="Cancel Event"
                message={`Are you sure you want to cancel "${cancelTarget?.name}"?`}
                warning="Confirmed bookings will be refunded and pending reservations will be released."
                confirmLabel="Yes, Cancel Event"
                danger
                onConfirm={handleCancel}
                onCancel={() => setCancelTarget(null)}
            />
        </div>
    );
}
