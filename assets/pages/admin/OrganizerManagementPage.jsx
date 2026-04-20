import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { getOrganizers, approveOrganizer, rejectOrganizer, deactivateOrganizer, reactivateOrganizer } from '../../api/adminApi';
import Badge from '../../components/common/Badge';
import Pagination from '../../components/common/Pagination';
import ConfirmModal from '../../components/common/ConfirmModal';
import { useToast } from '../../context/ToastContext';

const TABS = [
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Active' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'deactivated', label: 'Deactivated' },
];

export default function OrganizerManagementPage() {
    const { success, error: showError } = useToast();
    const [searchParams, setSearchParams] = useSearchParams();
    const activeTab = searchParams.get('tab') ?? 'pending';

    const [organizers, setOrganizers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [counts, setCounts] = useState({});
    const [actionTarget, setActionTarget] = useState(null); // { org, action }

    const fetchOrganizers = () => {
        setLoading(true);
        getOrganizers({ status: activeTab, page })
            .then(data => {
                setOrganizers(data.items ?? data ?? []);
                setTotalPages(data.totalPages ?? 1);
                if (data.counts) setCounts(data.counts);
            })
            .catch(() => setOrganizers([]))
            .finally(() => setLoading(false));
    };

    useEffect(() => { setPage(1); }, [activeTab]);
    useEffect(fetchOrganizers, [activeTab, page]);

    const handleAction = async () => {
        const { org, action } = actionTarget;
        try {
            if (action === 'approve') await approveOrganizer(org.id);
            else if (action === 'reject') await rejectOrganizer(org.id);
            else if (action === 'deactivate') await deactivateOrganizer(org.id);
            else if (action === 'reactivate') await reactivateOrganizer(org.id);

            const verb = {
                approve: 'approved',
                reject: 'rejected',
                deactivate: 'deactivated',
                reactivate: 'reactivated',
            }[action];

            success(`Organizer ${verb}.`);
            setActionTarget(null);
            fetchOrganizers();
        } catch (err) {
            showError(err.response?.data?.message ?? 'Action failed.');
        }
    };

    return (
        <div>
            <h1 className="page-title">Organizers</h1>

            {/* Tabs */}
            <div className="tabs">
                {TABS.map(tab => (
                    <button
                        key={tab.key}
                        className={`tab-item${activeTab === tab.key ? ' active' : ''}`}
                        onClick={() => setSearchParams({ tab: tab.key })}
                    >
                        {tab.label}
                        {counts[tab.key] > 0 && (
                            <span className="ml-1.5 text-xs font-bold bg-primary text-white rounded-badge px-1.5 py-0.5">
                                {counts[tab.key]}
                            </span>
                        )}
                    </button>
                ))}
            </div>

            <div className="card">
                {loading ? (
                    <div className="card-body text-sm text-gray-400">Loading…</div>
                ) : organizers.length === 0 ? (
                    <div className="empty-state">
                        <div className="empty-state-icon">🏢</div>
                        <div className="empty-state-title">No organizers found</div>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name / Email</th>
                                    <th>Organization</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {organizers.map(org => (
                                    <tr key={org.id}>
                                        <td>
                                            <div className="font-medium text-sm">{org.email}</div>
                                        </td>
                                        <td className="text-sm">{org.organizationName ?? '—'}</td>
                                        <td className="text-sm text-gray-500">
                                            {org.createdAt
                                                ? new Date(org.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
                                                : '—'}
                                        </td>
                                        <td><Badge status={org.status ?? org.approvalStatus ?? activeTab} /></td>
                                        <td>
                                            <div className="flex gap-2">
                                                {activeTab === 'pending' && (
                                                    <>
                                                        <button
                                                            className="btn btn-primary btn-sm"
                                                            onClick={() => setActionTarget({ org, action: 'approve' })}
                                                        >
                                                            ✓ Approve
                                                        </button>
                                                        <button
                                                            className="btn btn-danger btn-sm"
                                                            onClick={() => setActionTarget({ org, action: 'reject' })}
                                                        >
                                                            ✕ Reject
                                                        </button>
                                                    </>
                                                )}
                                                {activeTab === 'approved' && (
                                                    <button
                                                        className="btn btn-danger btn-sm"
                                                        onClick={() => setActionTarget({ org, action: 'deactivate' })}
                                                    >
                                                        Deactivate
                                                    </button>
                                                )}
                                                {activeTab === 'rejected' && (
                                                    <button
                                                        className="btn btn-primary btn-sm"
                                                        onClick={() => setActionTarget({ org, action: 'approve' })}
                                                    >
                                                        Approve
                                                    </button>
                                                )}
                                                {activeTab === 'deactivated' && (
                                                    <button
                                                        className="btn btn-primary btn-sm"
                                                        onClick={() => setActionTarget({ org, action: 'reactivate' })}
                                                    >
                                                        Reactivate
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
                open={!!actionTarget}
                title={{
                    approve: 'Approve Organizer',
                    reject: 'Reject Organizer',
                    deactivate: 'Deactivate Organizer',
                    reactivate: 'Reactivate Organizer',
                }[actionTarget?.action] ?? 'Organizer Action'}
                message={`Are you sure you want to ${actionTarget?.action} "${actionTarget?.org?.organizationName ?? actionTarget?.org?.email}"?`}
                confirmLabel={{
                    approve: '✓ Approve',
                    reject: '✕ Reject',
                    deactivate: 'Deactivate',
                    reactivate: 'Reactivate',
                }[actionTarget?.action] ?? 'Confirm'}
                danger={['reject', 'deactivate'].includes(actionTarget?.action)}
                onConfirm={handleAction}
                onCancel={() => setActionTarget(null)}
            />
        </div>
    );
}
