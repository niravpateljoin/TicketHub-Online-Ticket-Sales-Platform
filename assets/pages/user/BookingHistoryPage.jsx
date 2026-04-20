import React, { useState, useEffect } from 'react';
import { getBookings } from '../../api/bookingsApi';
import api from '../../hooks/useApi';
import Badge from '../../components/common/Badge';
import Pagination from '../../components/common/Pagination';
import { downloadBlobResponse } from '../../utils/download';
import { SkeletonGroup, SkeletonTableRows } from '../../components/common/Skeleton';

export default function BookingHistoryPage() {
    const [bookings, setBookings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [downloading, setDownloading] = useState(null);

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

    useEffect(() => {
        setLoading(true);
        getBookings({ page })
            .then(data => {
                setBookings(data.items ?? data ?? []);
                setTotalPages(data.totalPages ?? 1);
            })
            .catch(() => setBookings([]))
            .finally(() => setLoading(false));
    }, [page]);

    return (
        <div>
            <h1 className="page-title">My Bookings</h1>

            <div className="card">
                {loading ? (
                    <SkeletonGroup style={{ padding: '0' }}>
                        <div className="table-wrapper" style={{ border: 'none', borderRadius: 0 }}>
                            <table>
                                <thead>
                                    <tr>
                                        {['#', 'Event Name', 'Date', 'Items', 'Total', 'Status', 'Actions'].map(h => (
                                            <th key={h}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody><SkeletonTableRows cols={7} rows={5} /></tbody>
                            </table>
                        </div>
                    </SkeletonGroup>
                ) : bookings.length === 0 ? (
                    <div className="empty-state">
                        <div className="empty-state-icon">📋</div>
                        <div className="empty-state-title">No bookings yet</div>
                        <div className="empty-state-text">Start browsing events!</div>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Event Name</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tickets</th>
                                </tr>
                            </thead>
                            <tbody>
                                {bookings.map(booking => (
                                    <tr key={booking.id}>
                                        <td className="text-gray-400">{booking.id}</td>
                                        <td className="font-medium">{booking.event?.name ?? '—'}</td>
                                        <td>
                                            {booking.event?.startDate
                                                ? new Date(booking.event.startDate).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })
                                                : '—'}
                                        </td>
                                        <td>
                                            {booking.items?.map(i => `${i.quantity}×${i.tierName}`).join(', ') ?? '—'}
                                        </td>
                                        <td className="font-medium">{booking.totalCredits ?? 0} cr</td>
                                        <td>
                                            <Badge status={booking.status} />
                                            {booking.status === 'refunded' && (
                                                <div className="text-xs text-gray-400 mt-1">
                                                    This event was cancelled. Your credits have been refunded.
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            {booking.status === 'confirmed' ? (
                                                <button
                                                    onClick={() => handleDownload(booking.id)}
                                                    disabled={downloading === booking.id}
                                                    className="btn btn-ghost btn-sm">
                                                    {downloading === booking.id ? 'Downloading…' : '↓ Download'}
                                                </button>
                                            ) : (
                                                <span className="text-gray-400">—</span>
                                            )}
                                        </td>
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
