import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getMyWaitlist, leaveWaitlist } from '../../api/eventsApi';
import { useToast } from '../../context/ToastContext';
import Badge from '../../components/common/Badge';
import ConfirmModal from '../../components/common/ConfirmModal';
import { SkeletonWaitlist } from '../../components/common/Skeleton';

function statusVariant(s) {
    if (s === 'pending')   return 'pending';
    if (s === 'notified')  return 'approved';
    if (s === 'booked')    return 'approved';
    return 'rejected';
}

export default function WaitlistPage() {
    const { success, error } = useToast();
    const [entries, setEntries] = useState([]);
    const [loading, setLoading] = useState(true);
    const [leaveTarget, setLeaveTarget] = useState(null);
    const [leaving, setLeaving] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const data = await getMyWaitlist();
            setEntries(Array.isArray(data) ? data : (data?.items ?? []));
        } catch {
            error('Failed to load waitlist.');
        } finally {
            setLoading(false);
        }
    }, [error]);

    useEffect(() => { load(); }, [load]);

    const handleLeave = async () => {
        if (!leaveTarget) return;
        setLeaving(true);
        try {
            await leaveWaitlist(leaveTarget.event.id, leaveTarget.tier.id);
            success('Removed from waitlist.');
            setLeaveTarget(null);
            load();
        } catch (err) {
            error(err.response?.data?.message ?? 'Failed to leave waitlist.');
        } finally {
            setLeaving(false);
        }
    };

    const active = entries.filter(e => e.status === 'pending' || e.status === 'notified');
    const past   = entries.filter(e => e.status === 'booked' || e.status === 'cancelled');

    return (
        <div style={{ padding: '32px 24px', maxWidth: '900px', margin: '0 auto' }}>
            <h1 className="font-bold text-gray-900 mb-2" style={{ fontSize: '22px' }}>My Waitlist</h1>
            <p className="text-sm text-gray-500 mb-8">You'll be notified by email when seats become available.</p>

            {loading ? (
                <SkeletonWaitlist rows={3} />
            ) : entries.length === 0 ? (
                <div className="card text-center py-16">
                    <p className="text-gray-500 mb-4">You're not on any waitlists.</p>
                    <Link to="/events" className="btn btn-primary">Browse Events</Link>
                </div>
            ) : (
                <>
                    {active.length > 0 && (
                        <section className="mb-8">
                            <h2 className="font-semibold text-gray-700 mb-4">Active ({active.length})</h2>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                {active.map(e => (
                                    <div key={e.id} className="card" style={{ padding: '18px 20px' }}>
                                        <div className="flex items-start justify-between gap-4 flex-wrap">
                                            <div>
                                                <Link to={`/events/${e.event.slug}`} className="font-semibold text-gray-900 hover:text-primary" style={{ fontSize: '16px' }}>
                                                    {e.event.name}
                                                </Link>
                                                <div className="text-sm text-gray-500 mt-1">
                                                    Tier: <strong>{e.tier.name}</strong> ·{' '}
                                                    Joined {new Date(e.joinedAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                                </div>
                                                {e.status === 'notified' && (
                                                    <div className="text-sm mt-2" style={{ color: '#16A34A', fontWeight: 600 }}>
                                                        Seats are available — book now!
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <Badge status={statusVariant(e.status)} label={e.status === 'notified' ? 'Seats Available' : 'Waiting'} />
                                                {e.status === 'notified' && (
                                                    <Link to={`/events/${e.event.slug}`} className="btn btn-primary" style={{ fontSize: '13px', padding: '5px 14px' }}>
                                                        Book Now
                                                    </Link>
                                                )}
                                                <button
                                                    className="btn btn-secondary"
                                                    style={{ fontSize: '12px', padding: '4px 12px' }}
                                                    onClick={() => setLeaveTarget(e)}
                                                >
                                                    Leave
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}

                    {past.length > 0 && (
                        <section>
                            <h2 className="font-semibold text-gray-400 mb-4">Past ({past.length})</h2>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                {past.map(e => (
                                    <div key={e.id} className="card" style={{ padding: '14px 20px', opacity: 0.65 }}>
                                        <div className="flex items-center justify-between gap-4">
                                            <div>
                                                <span className="font-medium text-gray-700">{e.event.name}</span>
                                                <span className="text-gray-400 text-sm ml-2">· {e.tier.name}</span>
                                            </div>
                                            <Badge status={statusVariant(e.status)} label={e.status} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                </>
            )}

            <ConfirmModal
                isOpen={!!leaveTarget}
                title="Leave Waitlist"
                message={`Remove yourself from the waitlist for ${leaveTarget?.tier?.name} at ${leaveTarget?.event?.name}?`}
                confirmLabel={leaving ? 'Removing…' : 'Yes, Leave'}
                onConfirm={handleLeave}
                onCancel={() => setLeaveTarget(null)}
                disabled={leaving}
            />
        </div>
    );
}
