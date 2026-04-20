import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import { getAdminEvents, adminCancelEvent, adminDeleteEvent } from '../../api/adminApi';
import Badge from '../../components/common/Badge';
import Pagination from '../../components/common/Pagination';
import ConfirmModal from '../../components/common/ConfirmModal';
import { useToast } from '../../context/ToastContext';

export default function AllEventsPage() {
    const { success, error: showError } = useToast();
    const [events, setEvents]           = useState([]);
    const [loading, setLoading]         = useState(true);
    const [page, setPage]               = useState(1);
    const [totalPages, setTotalPages]   = useState(1);
    const [search, setSearch]           = useState('');

    const [cancelTarget, setCancelTarget] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [actionLoading, setActionLoading] = useState(false);

    const fetchEvents = () => {
        setLoading(true);
        getAdminEvents({ page, search })
            .then(data => {
                setEvents(data.items ?? data ?? []);
                setTotalPages(data.totalPages ?? 1);
            })
            .catch(() => setEvents([]))
            .finally(() => setLoading(false));
    };

    useEffect(fetchEvents, [page, search]);

    const handleCancel = async () => {
        setActionLoading(true);
        try {
            const data = await adminCancelEvent(cancelTarget.id);
            const msg = data?.usersRefunded > 0
                ? `Event cancelled. ${data.usersRefunded} user${data.usersRefunded !== 1 ? 's' : ''} refunded a total of ${data.creditsRefunded} credits.`
                : 'Event cancelled. No users to refund.';
            success(msg);
            setCancelTarget(null);
            fetchEvents();
        } catch (err) {
            showError(err.response?.data?.message ?? 'Failed to cancel event.');
        } finally {
            setActionLoading(false);
        }
    };

    const handleDelete = async () => {
        setActionLoading(true);
        try {
            await adminDeleteEvent(deleteTarget.id);
            success(`"${deleteTarget.name}" has been deleted.`);
            setDeleteTarget(null);
            fetchEvents();
        } catch (err) {
            showError(err.response?.data?.message ?? 'Failed to delete event.');
            setDeleteTarget(null);
        } finally {
            setActionLoading(false);
        }
    };

    const fmt = (iso) => iso
        ? new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';

    return (
        <div>
            <h1 className="page-title mb-1">All Events</h1>
            <p className="text-sm text-gray-500 mb-6">View, cancel, or delete events across all organizers.</p>

            {/* Search */}
            <div className="card mb-4">
                <div className="card-body" style={{ padding: '12px 20px' }}>
                    <input
                        className="form-input"
                        placeholder="Search events by name or organizer…"
                        value={search}
                        onChange={e => { setSearch(e.target.value); setPage(1); }}
                        style={{ maxWidth: '400px' }}
                    />
                </div>
            </div>

            <div className="card">
                {loading ? (
                    <div className="card-body text-sm text-gray-400">Loading…</div>
                ) : events.length === 0 ? (
                    <div className="empty-state">
                        <div className="empty-state-icon">🎭</div>
                        <div className="empty-state-title">No events found</div>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Organizer</th>
                                    <th>Date</th>
                                    <th>Tickets</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.map(ev => (
                                    <tr key={ev.id}>
                                        <td>
                                            <div className="font-medium text-sm">{ev.name}</div>
                                            {ev.venueName && (
                                                <div className="text-xs text-gray-400">{ev.venueName}</div>
                                            )}
                                        </td>
                                        <td className="text-sm">{ev.organizerName ?? '—'}</td>
                                        <td className="text-sm text-gray-500">{fmt(ev.startDate)}</td>
                                        <td className="text-sm">{ev.soldTickets ?? 0}/{ev.totalSeats ?? '∞'}</td>
                                        <td><Badge status={ev.status ?? 'active'} /></td>
                                        <td>
                                            <div className="flex items-center gap-2 flex-wrap">
                                                {/* View */}
                                                <a
                                                    href={`/events/${ev.slug ?? ev.id}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="btn btn-secondary btn-sm"
                                                    title="View event details"
                                                >
                                                    <Eye size={13} strokeWidth={2} className="mr-1" />
                                                    View
                                                </a>

                                                <Link
                                                    to={`/admin/events/${ev.id}/edit`}
                                                    className="btn btn-secondary btn-sm"
                                                    title="Edit event"
                                                >
                                                    <Pencil size={13} strokeWidth={2} className="mr-1" />
                                                    Edit
                                                </Link>

                                                {/* Cancel — only for active events */}
                                                {ev.status === 'active' && (
                                                    <button
                                                        className="btn btn-sm"
                                                        style={{ borderColor: '#f59e0b', color: '#d97706' }}
                                                        onClick={() => setCancelTarget(ev)}
                                                    >
                                                        Cancel
                                                    </button>
                                                )}

                                                {/* Delete */}
                                                <button
                                                    className="btn btn-danger btn-sm"
                                                    title="Permanently delete event"
                                                    onClick={() => setDeleteTarget(ev)}
                                                >
                                                    <Trash2 size={13} strokeWidth={2} className="mr-1" />
                                                    Delete
                                                </button>
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

            {/* Cancel modal */}
            <ConfirmModal
                open={!!cancelTarget}
                title="Cancel Event"
                message={`Are you sure you want to cancel "${cancelTarget?.name}"?`}
                warning="This will refund all ticket holders. This action cannot be undone."
                confirmLabel={actionLoading ? 'Cancelling…' : 'Yes, Cancel Event'}
                danger
                onConfirm={handleCancel}
                onCancel={() => setCancelTarget(null)}
            />

            {/* Delete modal */}
            <ConfirmModal
                open={!!deleteTarget}
                title="Delete Event"
                message={`Permanently delete "${deleteTarget?.name}"?`}
                warning={
                    (deleteTarget?.soldTickets ?? 0) > 0
                        ? 'This event has sold tickets. Cancel it first to refund ticket holders before deleting.'
                        : 'This will permanently remove the event and all its data. This cannot be undone.'
                }
                confirmLabel={actionLoading ? 'Deleting…' : 'Delete Event'}
                danger
                onConfirm={(deleteTarget?.soldTickets ?? 0) > 0 ? undefined : handleDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </div>
    );
}
