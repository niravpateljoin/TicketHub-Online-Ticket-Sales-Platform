import React, { useCallback, useEffect, useState } from 'react';
import { getAdminBookings, adminRefundBooking } from '../../api/adminApi';
import Pagination from '../../components/common/Pagination';
import ConfirmModal from '../../components/common/ConfirmModal';
import { useToast } from '../../context/ToastContext';
import Badge from '../../components/common/Badge';

const STATUS_OPTIONS = [
    { value: '', label: 'All Statuses' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'refunded', label: 'Refunded' },
    { value: 'cancelled', label: 'Cancelled' },
];

function statusVariant(status) {
    if (status === 'confirmed') return 'approved';
    if (status === 'refunded') return 'pending';
    return 'rejected';
}

export default function BookingManagementPage() {
    const { success, error } = useToast();
    const [bookings, setBookings] = useState([]);
    const [total, setTotal] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState('');
    const [search, setSearch] = useState('');
    const [searchInput, setSearchInput] = useState('');
    const [loading, setLoading] = useState(true);
    const [refundTarget, setRefundTarget] = useState(null);
    const [refunding, setRefunding] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const data = await getAdminBookings({ page, perPage: 20, status, search });
            setBookings(data.data ?? []);
            setTotal(data.meta?.total ?? 0);
            setTotalPages(data.meta?.totalPages ?? 1);
        } catch {
            error('Failed to load bookings.');
        } finally {
            setLoading(false);
        }
    }, [page, status, search, error]);

    useEffect(() => { load(); }, [load]);

    const handleSearch = (e) => {
        e.preventDefault();
        setSearch(searchInput);
        setPage(1);
    };

    const handleRefund = async () => {
        if (!refundTarget) return;
        setRefunding(true);
        try {
            const msg = await adminRefundBooking(refundTarget.id);
            success(msg ?? 'Booking refunded.');
            setRefundTarget(null);
            load();
        } catch (err) {
            error(err.response?.data?.message ?? 'Refund failed.');
        } finally {
            setRefunding(false);
        }
    };

    return (
        <div style={{ padding: '32px 24px', maxWidth: '1200px', margin: '0 auto' }}>
            <h1 className="font-bold text-gray-900 mb-6" style={{ fontSize: '22px' }}>Booking Management</h1>

            {/* Filters */}
            <div className="card mb-6" style={{ padding: '16px 20px' }}>
                <div className="flex flex-wrap gap-3 items-center">
                    <form onSubmit={handleSearch} className="flex gap-2 flex-1" style={{ minWidth: '240px' }}>
                        <input
                            type="text"
                            className="form-input"
                            placeholder="Search by user email or event name…"
                            value={searchInput}
                            onChange={e => setSearchInput(e.target.value)}
                            style={{ flex: 1 }}
                        />
                        <button type="submit" className="btn btn-primary" style={{ whiteSpace: 'nowrap' }}>Search</button>
                    </form>
                    <select
                        className="form-input"
                        style={{ width: 'auto', minWidth: '160px' }}
                        value={status}
                        onChange={e => { setStatus(e.target.value); setPage(1); }}
                    >
                        {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                </div>
            </div>

            {/* Summary */}
            <p className="text-sm text-gray-500 mb-4">{total} booking{total !== 1 ? 's' : ''} found</p>

            {loading ? (
                <div className="text-center text-gray-400 py-16">Loading…</div>
            ) : bookings.length === 0 ? (
                <div className="card text-center text-gray-400 py-16">No bookings found.</div>
            ) : (
                <>
                    <div className="card" style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '14px' }}>
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
                                    {['#', 'User', 'Event', 'Credits', 'Status', 'Date', 'Action'].map(h => (
                                        <th key={h} style={{ padding: '10px 14px', textAlign: 'left', fontWeight: 600, color: '#374151', whiteSpace: 'nowrap' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {bookings.map((b, i) => (
                                    <tr key={b.id} style={{ borderBottom: '1px solid var(--color-border)', background: i % 2 === 0 ? 'transparent' : '#FAFAFA' }}>
                                        <td style={{ padding: '10px 14px', color: '#6B7280' }}>#{b.id}</td>
                                        <td style={{ padding: '10px 14px' }}>{b.userEmail}</td>
                                        <td style={{ padding: '10px 14px', maxWidth: '200px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{b.event?.name}</td>
                                        <td style={{ padding: '10px 14px', fontWeight: 600 }}>{b.totalCredits.toLocaleString()}</td>
                                        <td style={{ padding: '10px 14px' }}>
                                            <Badge status={statusVariant(b.status)} label={b.status} />
                                        </td>
                                        <td style={{ padding: '10px 14px', color: '#6B7280', whiteSpace: 'nowrap' }}>
                                            {new Date(b.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        </td>
                                        <td style={{ padding: '10px 14px' }}>
                                            {b.status === 'confirmed' && (
                                                <button
                                                    className="btn btn-secondary"
                                                    style={{ fontSize: '12px', padding: '4px 12px' }}
                                                    onClick={() => setRefundTarget(b)}
                                                >
                                                    Refund
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {totalPages > 1 && (
                        <div className="mt-6">
                            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
                        </div>
                    )}
                </>
            )}

            <ConfirmModal
                open={!!refundTarget}
                title="Confirm Refund"
                message={`Refund booking #${refundTarget?.id} for ${refundTarget?.userEmail}? ${refundTarget?.totalCredits?.toLocaleString()} credits will be returned to their account.`}
                confirmLabel={refunding ? 'Refunding…' : 'Yes, Refund'}
                onConfirm={handleRefund}
                onCancel={() => setRefundTarget(null)}
            />
        </div>
    );
}
